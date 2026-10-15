<?php

namespace AchttienVijftien\MakePotTwig;

use WP_CLI\I18n\MakePotCommand as BaseMakePotCommand;

/**
 * `wp i18n make-pot`, plus strings from *.twig files.
 *
 * Inherits the parent's __invoke() and with it the docblock WP-CLI reads the synopsis from,
 * so all flags and help output stay the same.
 */
class MakePotCommand extends BaseMakePotCommand {

	/**
	 * The parent's extract_strings() hands its Translations instance to this method before
	 * extracting PHP strings, so Twig strings added here go through the same --subtract and
	 * audit steps as everything else.
	 *
	 * @param \Gettext\Translations $translations Translations instance.
	 */
	protected function set_default_headers( $translations ) {
		parent::set_default_headers( $translations );

		// Twig strings are translated at PHP runtime, so they belong with PHP strings (not in e.g. a JS-only POT).
		if ( $this->skip_php ) {
			return;
		}

		// Normally set right after this method; the scanner filters on it, so set it now.
		if ( $this->domain ) {
			$translations->setDomain( $this->domain );
		}

		TwigCodeExtractor::fromDirectory(
			$this->source,
			$translations,
			[
				'include'       => $this->include,
				'exclude'       => $this->exclude,
				'extensions'    => [ 'twig' ],
				'addReferences' => $this->location,
			]
		);
	}
}
