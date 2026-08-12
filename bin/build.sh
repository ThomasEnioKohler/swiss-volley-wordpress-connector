#!/usr/bin/env bash
#
# Swiss Volley Connector – Build-Pipeline.
#
# Führt aus:
#   1. PHP-Syntaxcheck (php -l) über alle Plugin-Dateien
#   2. Testsuite (tests/harness.php) mit error_reporting=E_ALL
#   3. Neugenerierung der Übersetzungsvorlage (.pot)
#   4. Bau des installierbaren ZIPs nach dist/swiss-volley-connector-<version>.zip
#
# Aufruf:  bin/build.sh
# Voraussetzungen: bash, php >= 8.1, python3, zip

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT/swiss-volley-connector"
DIST_DIR="$ROOT/dist"

echo "== 1/4 PHP-Syntaxcheck =="
while IFS= read -r -d '' file; do
	php -l "$file" > /dev/null
done < <(find "$PLUGIN_DIR" -name '*.php' -print0)
echo "OK"

echo "== 2/4 Tests =="
php -d error_reporting=E_ALL -d display_errors=1 "$ROOT/tests/harness.php"

echo "== 3/4 Übersetzungsvorlage (.pot) =="
python3 "$ROOT/bin/make-pot.py" "$PLUGIN_DIR"

echo "== 4/4 ZIP bauen =="
# sed statt grep -oP: BSD-grep (macOS) kennt kein PCRE (-P).
VERSION="$(sed -n "s/.*define( *'SVC_VERSION', *'\([0-9.][0-9.]*\)'.*/\1/p" "$PLUGIN_DIR/swiss-volley-connector.php")"
if [ -z "$VERSION" ]; then
	echo "Fehler: SVC_VERSION konnte nicht aus $PLUGIN_DIR/swiss-volley-connector.php gelesen werden." >&2
	exit 1
fi
mkdir -p "$DIST_DIR"
ZIP="$DIST_DIR/swiss-volley-connector-$VERSION.zip"
rm -f "$ZIP"
( cd "$ROOT" && zip -rq "$ZIP" swiss-volley-connector -x '*.DS_Store' )

echo
echo "Fertig: $ZIP"
