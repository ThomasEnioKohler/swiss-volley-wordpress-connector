#!/usr/bin/env bash
#
# Volleyball Schedules for Swiss Volley – Build-Pipeline.
#
# Führt aus:
#   0. Versionskonsistenz (bin/version.sh) und readme.txt/CHANGELOG.md-Abgleich
#   1. PHP-Syntaxcheck (php -l) über alle Plugin-Dateien
#   2. Testsuite (tests/harness.php) mit error_reporting=E_ALL
#   3. Neugenerierung der Übersetzungsvorlage (.pot)
#   4. i18n-Prüfung (tests/test-i18n.sh: ASCII-Quellstrings, Text Domain, Katalog-Vollständigkeit)
#   5. Übersetzungskataloge (.mo aus .po via msgfmt)
#   6. JSON-Sprachkataloge für den Block-Editor (aus blocks.js + .po)
#   7. Bau des Build-Verzeichnisses nach dist/build/volleyball-schedules-for-swiss-volley
#   8. Bau des installierbaren ZIPs aus dem Build-Verzeichnis nach dist/volleyball-schedules-for-swiss-volley-<version>.zip
#   9. ZIP-Inhalt prüfen
#
# Die i18n-Prüfung läuft hier und nicht nur als separater CI-Schritt, damit
# ein getaggter Release nie unvollständige Kataloge ausliefert, selbst wenn
# der Tag von einem Branch-Stand gepusht wird, den CI so nie gesehen hat.
#
# Aufruf:  bin/build.sh
# Voraussetzungen: bash, php >= 8.1, python3, zip, gettext (msgfmt, msgcmp)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT/volleyball-schedules-for-swiss-volley"
DIST_DIR="$ROOT/dist"

echo "== 0/9 Versionskonsistenz =="
VERSION="$("$ROOT/bin/version.sh")"
"$ROOT/bin/sync-readme-changelog.sh" --check
echo "OK  $VERSION"

echo "== 1/9 PHP-Syntaxcheck =="
while IFS= read -r -d '' file; do
	php -l "$file" > /dev/null
done < <(find "$PLUGIN_DIR" -name '*.php' -print0)
echo "OK"

echo "== 2/9 Tests =="
php -d error_reporting=E_ALL -d display_errors=1 "$ROOT/tests/harness.php"

echo "== 3/9 Übersetzungsvorlage (.pot) =="
python3 "$ROOT/bin/make-pot.py" "$PLUGIN_DIR"

echo "== 4/9 i18n-Prüfung =="
bash "$ROOT/tests/test-i18n.sh"

echo "== 5/9 Übersetzungskataloge (.mo) =="
for po in "$PLUGIN_DIR"/languages/*.po; do
	msgfmt -o "${po%.po}.mo" "$po"
	echo "OK  $(basename "${po%.po}.mo")"
done

echo "== 6/9 JSON-Sprachkataloge (Block-Editor) =="
python3 "$ROOT/bin/make-blocks-json.py" "$PLUGIN_DIR"

echo "== 7/9 Build-Verzeichnis =="
BUILD_DIR="$DIST_DIR/build/volleyball-schedules-for-swiss-volley"
rm -rf "$DIST_DIR/build"
mkdir -p "$BUILD_DIR"
( cd "$PLUGIN_DIR" && tar -cf - \
	--exclude='.DS_Store' \
	--exclude='README.md' \
	--exclude='docs' \
	--exclude='languages' \
	. ) | ( cd "$BUILD_DIR" && tar -xf - )
echo "OK  $BUILD_DIR"

echo "== 8/9 ZIP bauen =="
mkdir -p "$DIST_DIR"
ZIP="$DIST_DIR/volleyball-schedules-for-swiss-volley-$VERSION.zip"
rm -f "$ZIP"
# Aus dem bereits bereinigten Build-Verzeichnis gezippt, nicht aus
# PLUGIN_DIR: ein zweites, unabhaengig gepflegtes Exclude-Muster hier
# wuerde ueber kurz oder lang vom Build-Verzeichnis abweichen (siehe
# Schritt 7/9) und ZIP und SVN wieder auseinanderlaufen lassen.
( cd "$DIST_DIR/build" && zip -rq "$ZIP" volleyball-schedules-for-swiss-volley \
	-x '*.DS_Store' )

echo "== 9/9 ZIP prüfen =="
unzip -l "$ZIP" | tail -1
if ! unzip -p "$ZIP" volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php \
	| grep -q "define( 'VSSV_VERSION', '$VERSION' );"; then
	echo "Fehler: ZIP enthält volleyball-schedules-for-swiss-volley.php nicht mit VSSV_VERSION $VERSION." >&2
	exit 1
fi

echo
echo "Fertig: $ZIP"
