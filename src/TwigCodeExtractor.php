<?php

namespace AchttienVijftien\MakePotTwig;

use Gettext\Extractors\Extractor;
use Gettext\Translations;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use WP_CLI;
use WP_CLI\I18n\IterableCodeExtractor;
use WP_CLI\I18n\PhpCodeExtractor;

/**
 * Extracts gettext calls from Twig templates.
 *
 * Parses each template with Twig, rewrites the gettext calls it finds into PHP (each call on its
 * original template line) and hands that to WP-CLI's PHP extractor, so domains, contexts, plurals
 * and flags are handled exactly like in PHP files.
 */
final class TwigCodeExtractor extends Extractor {
	use IterableCodeExtractor;

	/**
	 * {@inheritdoc}
	 */
	public static function fromString( $text, Translations $translations, array $options = [] ) {
		try {
			$php = self::toPhp( $text, $options['file'] ?? '' );
		} catch ( Error $e ) {
			WP_CLI::warning( 'Could not parse template: ' . $e->getMessage() );
			return;
		}

		PhpCodeExtractor::fromString( $php, $translations, $options );
	}

	/**
	 * Returns PHP containing only the template's gettext calls, line numbers preserved.
	 *
	 * Arguments that aren't literals (e.g. the number passed to _n()) become null.
	 *
	 * @throws Error When the template can't be parsed.
	 */
	public static function toPhp( string $twig, string $name = '' ): string {
		$env   = self::env();
		$calls = [];
		self::collect( $env->parse( $env->tokenize( new Source( $twig, $name ) ) ), $calls );
		usort( $calls, fn( $a, $b ) => $a[0] <=> $b[0] );

		$php  = '<?php';
		$line = 1;
		foreach ( $calls as [ $call_line, $code ] ) {
			$php .= str_repeat( "\n", $call_line - $line ) . " $code;";
			$line = $call_line;
		}

		return $php;
	}

	/**
	 * Twig environment that accepts any function, filter or test name, since templates use
	 * whatever Timber and the theme register at runtime.
	 */
	private static function env(): Environment {
		static $env;

		if ( ! $env ) {
			$env = new Environment( new ArrayLoader() );
			$env->registerUndefinedFunctionCallback( fn( $name ) => new TwigFunction( $name ) );
			$env->registerUndefinedFilterCallback( fn( $name ) => new TwigFilter( $name ) );
			$env->registerUndefinedTestCallback( fn( $name ) => new TwigTest( $name ) );
		}

		return $env;
	}

	/**
	 * Collects [ line, php ] for every gettext call in the tree.
	 */
	private static function collect( Node $node, array &$calls ): void {
		if ( $node instanceof FunctionExpression && isset( PhpCodeExtractor::$options['functions'][ $node->getAttribute( 'name' ) ] ) ) {
			$args = [];
			// ponytail: named arguments (__(text: 'x')) are emitted positionally in written order.
			foreach ( $node->getNode( 'arguments' ) as $arg ) {
				$value = $arg instanceof ConstantExpression ? $arg->getAttribute( 'value' ) : null;
				// Double-quoted with escaped newlines, so each call stays on one line of the generated PHP.
				$args[] = is_string( $value ) ? '"' . addcslashes( $value, "\\\"\$\n\r" ) . '"' : var_export( $value, true );
			}
			$calls[] = [ $node->getTemplateLine(), $node->getAttribute( 'name' ) . '(' . implode( ', ', $args ) . ')' ];
		}

		foreach ( $node as $child ) {
			self::collect( $child, $calls );
		}

		// {% embed %} bodies live outside the main tree.
		if ( $node instanceof ModuleNode ) {
			foreach ( $node->getAttribute( 'embedded_templates' ) as $embedded ) {
				self::collect( $embedded, $calls );
			}
		}
	}
}
