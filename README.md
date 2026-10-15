# make-pot-twig

Makes `wp i18n make-pot` pick up translatable strings in `*.twig` files. PHP only: no gulp, no generated cache files.

Templates are parsed with Twig's own parser. The gettext calls it finds (`__()`, `_x()`, `_n()`, `esc_html__()`, …) go through WP-CLI's PHP extractor, so text domains, contexts, plurals, `php-format` flags, `--subtract` and the placeholder audit all behave as they do for PHP files. References point at the template: `#: templates/foo.twig:12`.

## Install

As a dev dependency of a theme or plugin:

```sh
composer require --dev achttienvijftien/make-pot-twig
```

```json
"scripts": {
  "make-pot": "wp i18n make-pot . languages/my-theme.pot --require=vendor/autoload.php"
}
```

Or for every project on your machine: `wp package install <path-or-git-url>`.

All `make-pot` flags work as usual. `--skip-php` skips Twig too, since Twig strings are translated at PHP runtime.

## How it hooks in

`command.php` registers `MakePotCommand`, a subclass of WP-CLI's command, as `i18n make-pot`. Registration happens on the `find_command_to_run_pre` hook, because WP-CLI registers its bundled commands after packages and `--require` files have loaded. The subclass adds the Twig strings from `set_default_headers()`, the only point where the parent's `extract_strings()` passes its `Translations` instance out.

The Twig environment accepts any function, filter, test or tag name, so Timber and theme extensions don't need to be loaded. Unknown tags are stubbed: their arguments are skipped, and the body of a block tag is scanned like the rest of the template. A template that still fails to parse produces a warning and is skipped.

## Not supported

- `{# translators: … #}` comments. Twig's lexer drops comments.
- Named arguments (`__(text: 'x')`). They are read in the order they're written.
- Indirect calls such as `fn('__', 'x', 'domain')`.
- Gettext calls inside an unknown tag's own arguments (`{% mytag __('x') %}`). Calls in the tag's body are found.
- Custom operators registered by extensions. These cause a parse warning.

## Test

```sh
composer install && composer test
```

This builds a POT for `tests/fixture` and diffs it against `tests/expected.pot`. It needs `wp` on your `PATH`.
