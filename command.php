<?php
/**
 * Replaces `wp i18n make-pot` with a version that also scans *.twig files.
 *
 * Loaded through Composer's autoloader (`wp package install`, or `--require=vendor/autoload.php`),
 * or directly with `--require=vendor/achttienvijftien/make-pot-twig/command.php`.
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

// Loaded directly via --require: pull in the autoloader of whichever project installed us.
if ( ! class_exists( Twig\Environment::class ) ) {
	foreach ( [ __DIR__ . '/vendor/autoload.php', dirname( __DIR__, 2 ) . '/autoload.php' ] as $autoload ) {
		if ( is_file( $autoload ) ) {
			require_once $autoload;
			break;
		}
	}
}

// WP-CLI registers its bundled commands after packages and --require files load, which would
// override ours; this hook runs once everything is registered, right before dispatch.
WP_CLI::add_hook(
	'find_command_to_run_pre',
	static function () {
		WP_CLI::add_command( 'i18n make-pot', AchttienVijftien\MakePotTwig\MakePotCommand::class );
	}
);
