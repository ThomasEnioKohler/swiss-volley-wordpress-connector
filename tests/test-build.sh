#!/usr/bin/env bash
#
# Tests für die Build-Pipeline (bin/build.sh).
#
# Arbeitet auf einer vollständigen Kopie des Plugin-Baums in einem
# temporären Verzeichnis; die echten Projektdateien werden nie verändert.
#
# Aufruf: bash tests/test-build.sh
# Voraussetzungen: bash, php >= 8.1, python3, zip, unzip

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PASS=0
FAIL=0
TMPDIRS=()

pass() {
	printf 'PASS  %s\n' "$1"
	PASS=$((PASS + 1))
}

fail() {
	printf 'FAIL  %s\n      %s\n' "$1" "$2"
	FAIL=$((FAIL + 1))
}

# Legt eine vollständige Arbeitskopie des Plugin-Baums an, wie sie
# bin/build.sh zum Bauen braucht (bin/, tests/harness.php, das komplette
# Plugin-Verzeichnis inkl. languages/, docs/, README.md).
fixture() {
	local dir
	dir="$(mktemp -d)"
	TMPDIRS+=("$dir")
	mkdir -p "$dir/bin" "$dir/tests"
	cp "$ROOT"/bin/*.sh "$dir/bin/"
	cp "$ROOT"/bin/*.py "$dir/bin/"
	cp "$ROOT/CHANGELOG.md" "$dir/CHANGELOG.md"
	cp "$ROOT/tests/harness.php" "$dir/tests/harness.php"
	cp -R "$ROOT/volleyball-schedules-for-swiss-volley" "$dir/volleyball-schedules-for-swiss-volley"
	printf '%s' "$dir"
}

cleanup() {
	local dir
	for dir in ${TMPDIRS+"${TMPDIRS[@]}"}; do
		rm -rf "$dir"
	done
}
trap cleanup EXIT

test_build_erfolgreich() {
	local dir version zip
	dir="$(fixture)"

	if ! bash "$dir/bin/build.sh" > "$dir/build.log" 2>&1; then
		fail "build.sh läuft mit Exit 0 durch und erzeugt das ZIP" \
			"$(tail -20 "$dir/build.log")"
		return
	fi

	version="$(bash "$dir/bin/version.sh")"
	zip="$dir/dist/volleyball-schedules-for-swiss-volley-$version.zip"
	if [ -f "$zip" ]; then
		pass "build.sh läuft mit Exit 0 durch und erzeugt das ZIP"
	else
		fail "build.sh läuft mit Exit 0 durch und erzeugt das ZIP" \
			"ZIP $zip nicht gefunden. Log: $(tail -20 "$dir/build.log")"
	fi
}

# Mutationsbeweis für den Inhaltscheck aus Schritt 5/5: Vor dem Fix prüfte
# der Schritt nur "unzip -l | tail -1" und damit gar nichts. Diese Mutation
# baut den zip-Aufruf so um, dass die Hauptdatei des Plugins fehlt (wie bei
# einer kaputten Exclude-Liste) — der Build muss das jetzt erkennen und mit
# Exit 1 abbrechen, statt ein ZIP ohne Hauptdatei stillschweigend fertig zu
# melden.
test_build_erkennt_zip_ohne_hauptdatei() {
	local dir lineno insert_line
	dir="$(fixture)"

	# Zeilennummer des zip-Aufrufs ermitteln, statt die Zeile per awk/regex
	# nachzubauen — vermeidet Unterschiede in der Escape-Behandlung von
	# "-v"-Zuweisungen zwischen awk-Implementationen (BSD/GNU).
	lineno="$(grep -n -F -- 'zip -rq "$ZIP" volleyball-schedules-for-swiss-volley' "$dir/bin/build.sh" | head -1 | cut -d: -f1)"
	if [ -z "$lineno" ]; then
		fail "build.sh erkennt ein ZIP ohne die Hauptdatei" \
			"Zeile mit dem zip-Aufruf in build.sh nicht gefunden"
		return
	fi

	# Zusätzliches -x direkt nach dieser Zeile einfügen ($'...' liefert den
	# literalen Tab und Backslash ohne weitere Interpretation durch ein
	# externes Werkzeug).
	insert_line=$'\t-x \'volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php\' \\'
	{
		head -n "$lineno" "$dir/bin/build.sh"
		printf '%s\n' "$insert_line"
		tail -n "+$((lineno + 1))" "$dir/bin/build.sh"
	} > "$dir/bin/build.sh.tmp"
	mv "$dir/bin/build.sh.tmp" "$dir/bin/build.sh"

	if bash "$dir/bin/build.sh" > "$dir/build.log" 2>&1; then
		fail "build.sh erkennt ein ZIP ohne die Hauptdatei" \
			"build.sh lief mit Exit 0 durch, obwohl die Hauptdatei fehlt. Log: $(tail -20 "$dir/build.log")"
	else
		pass "build.sh erkennt ein ZIP ohne die Hauptdatei"
	fi
}

# Das Build-Verzeichnis geht 1:1 nach SVN. Entwickler-Dateien darin
# landen sonst in der oeffentlichen Installation.
test_build_verzeichnis_ist_sauber() {
	local dir build
	dir="$(fixture)"

	if ! bash "$dir/bin/build.sh" > "$dir/build.log" 2>&1; then
		fail "build.sh erzeugt ein sauberes Build-Verzeichnis" \
			"$(tail -20 "$dir/build.log")"
		return
	fi

	build="$dir/dist/build/volleyball-schedules-for-swiss-volley"
	if [ ! -f "$build/volleyball-schedules-for-swiss-volley.php" ]; then
		fail "build.sh erzeugt ein sauberes Build-Verzeichnis" \
			"Hauptdatei fehlt in $build"
		return
	fi
	if [ -d "$build/docs" ] || [ -f "$build/README.md" ]; then
		fail "build.sh erzeugt ein sauberes Build-Verzeichnis" \
			"docs/ oder README.md im Build-Verzeichnis"
		return
	fi
	if ! ls "$build"/languages/*.mo > /dev/null 2>&1; then
		fail "build.sh erzeugt ein sauberes Build-Verzeichnis" \
			"keine .mo-Dateien im Build-Verzeichnis"
		return
	fi

	pass "build.sh erzeugt ein sauberes Build-Verzeichnis"
}

test_build_erfolgreich
test_build_erkennt_zip_ohne_hauptdatei
test_build_verzeichnis_ist_sauber

printf '\n%d bestanden, %d fehlgeschlagen\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
