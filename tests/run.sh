#!/bin/sh
# Generates a POT for tests/fixture and diffs it (minus header) against tests/expected.pot.
set -e
cd "$(dirname "$0")/.."
pot=$(mktemp)
trap 'rm -f "$pot"' EXIT
wp --require=command.php i18n make-pot tests/fixture "$pot"
sed '1,/^$/d' "$pot" | diff -u tests/expected.pot - && echo OK
