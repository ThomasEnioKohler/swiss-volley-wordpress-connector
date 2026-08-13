#!/usr/bin/env bash
#
# Volleyball Schedules for Swiss Volley – Build-Pipeline.
#
# Führt aus:
#   0. Versionskonsistenz (bin/version.sh) und readme.txt/CHANGELOG.md-Abgleich
#   1. PHP-Syntaxcheck (php -l) über alle Plugin-Dateien
#   2. Testsuite (tests/harness.php) mit error_reporting=E_ALL
#   3. Neugenerierung der Übersetzungsvorlage (.pot)
#   4. Bau des installierbaren ZIPs nach dist/volleyball-schedules-for-swiss-volley-<version>.zip
#   5. ZIP-Inhalt prüfen
#
# Aufruf:  bin/build.sh
# Voraussetzungen: bash, php >= 8.1, python3, zip

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT/volleyball-schedules-for-swiss-volley"
DIST_DIR="$ROOT/dist"

echo "== 0/5 Versionskonsistenz =="
VERSION="$("$ROOT/bin/version.sh")"
"$ROOT/bin/sync-readme-changelog.sh" --check
echo "OK  $VERSION"

echo "== 1/5 PHP-Syntaxcheck =="
while IFS= read -r -d '' file; do
	php -l "$file" > /dev/null
done < <(find "$PLUGIN_DIR" -name '*.php' -print0)
echo "OK"

echo "== 2/5 Tests =="
php -d error_reporting=E_ALL -d display_errors=1 "$ROOT/tests/harness.php"

echo "== 3/5 Übersetzungsvorlage (.pot) =="
python3 "$ROOT/bin/make-pot.py" "$PLUGIN_DIR"

echo "== 4/5 ZIP bauen =="
mkdir -p "$DIST_DIR"
ZIP="$DIST_DIR/volleyball-schedules-for-swiss-volley-$VERSION.zip"
rm -f "$ZIP"
( cd "$ROOT" && zip -rq "$ZIP" volleyball-schedules-for-swiss-volley \
	-x '*.DS_Store' \
	-x 'volleyball-schedules-for-swiss-volley/README.md' \
	-x 'volleyball-schedules-for-swiss-volley/docs/*' )

echo "== 5/5 ZIP prüfen =="
unzip -l "$ZIP" | tail -1
if ! unzip -p "$ZIP" volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php \
	| grep -q "define( 'VSSV_VERSION', '$VERSION' );"; then
	echo "Fehler: ZIP enthält volleyball-schedules-for-swiss-volley.php nicht mit VSSV_VERSION $VERSION." >&2
	exit 1
fi

echo
echo "Fertig: $ZIP"
