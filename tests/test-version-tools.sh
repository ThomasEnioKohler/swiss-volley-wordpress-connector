#!/usr/bin/env bash
#
# Tests für die Versions-Werkzeuge unter bin/.
#
# Arbeitet ausschliesslich auf Kopien in einem temporären Verzeichnis;
# die echten Projektdateien werden nie verändert.
#
# Aufruf: bash tests/test-version-tools.sh

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

# Legt eine Arbeitskopie des Projekts an und gibt deren Pfad aus.
# Die Skripte finden darin ihr ROOT über bin/../ und fassen nichts Echtes an.
fixture() {
	local dir
	dir="$(mktemp -d)"
	TMPDIRS+=("$dir")
	mkdir -p "$dir/bin" "$dir/swiss-volley-connector"
	cp "$ROOT"/bin/*.sh "$dir/bin/"
	cp "$ROOT/CHANGELOG.md" "$dir/CHANGELOG.md"
	cp "$ROOT/swiss-volley-connector/readme.txt" "$dir/swiss-volley-connector/"
	cp "$ROOT/swiss-volley-connector/swiss-volley-connector.php" "$dir/swiss-volley-connector/"
	printf '%s' "$dir"
}

cleanup() {
	local dir
	for dir in ${TMPDIRS+"${TMPDIRS[@]}"}; do
		rm -rf "$dir"
	done
}
trap cleanup EXIT

# assert_exit <Name> <erwarteter Code> <Befehl...>
assert_exit() {
	local name="$1" want="$2"
	shift 2
	local out got
	out="$("$@" 2>&1)"
	got=$?
	if [ "$got" -eq "$want" ]; then
		pass "$name"
	else
		fail "$name" "Exit $got statt $want. Ausgabe: $out"
	fi
}

# assert_stdout <Name> <erwartete Ausgabe> <Befehl...>
assert_stdout() {
	local name="$1" want="$2"
	shift 2
	local got
	got="$("$@" 2>/dev/null)"
	if [ "$got" = "$want" ]; then
		pass "$name"
	else
		fail "$name" "Ausgabe '$got' statt '$want'"
	fi
}

# --- sync-readme-changelog.sh ---------------------------------------------

test_sync_roundtrip() {
	local dir referenz erzeugt rc
	dir="$(fixture)"
	referenz="$dir/referenz.txt"
	erzeugt="$dir/erzeugt.txt"

	# Die Sektion, wie sie vor der Migration in readme.txt stand — MUSS vor
	# dem Verfälschen gesichert werden, sonst würde die Referenz mit
	# verfälscht.
	sed -n '/^== Changelog ==/,$p' "$dir/swiss-volley-connector/readme.txt" > "$referenz"

	# readme.txt jetzt gezielt verfälschen: nur ein echter Schreibvorgang
	# des Generators kann die Datei wieder auf den Originalstand bringen.
	# Bricht der Generator ab (oder schreibt er gar nicht), bleibt die
	# Verfälschung stehen und der folgende diff schlägt zu Recht fehl.
	printf '\n* Verfaelscht fuer den Test\n' >> "$dir/swiss-volley-connector/readme.txt"

	bash "$dir/bin/sync-readme-changelog.sh" > /dev/null
	rc=$?
	if [ "$rc" -ne 0 ]; then
		fail "sync erzeugt die Changelog-Sektion byteweise identisch" \
			"Generator brach mit Exit $rc ab"
		return
	fi

	sed -n '/^== Changelog ==/,$p' "$dir/swiss-volley-connector/readme.txt" > "$erzeugt"

	if diff -u "$referenz" "$erzeugt" > "$dir/diff.txt"; then
		pass "sync erzeugt die Changelog-Sektion byteweise identisch"
	else
		fail "sync erzeugt die Changelog-Sektion byteweise identisch" \
			"$(head -20 "$dir/diff.txt")"
	fi
}

test_sync_check_ok() {
	local dir
	dir="$(fixture)"
	assert_exit "sync --check meldet Gleichstand" 0 \
		bash "$dir/bin/sync-readme-changelog.sh" --check
}

test_sync_check_erkennt_handedit() {
	local dir
	dir="$(fixture)"
	printf '\n* Von Hand eingefügt\n' >> "$dir/swiss-volley-connector/readme.txt"
	assert_exit "sync --check erkennt Handedit an readme.txt" 1 \
		bash "$dir/bin/sync-readme-changelog.sh" --check
}

test_sync_meldet_unverstandene_zeile() {
	local dir ausgabe rc
	dir="$(fixture)"

	# Fortsetzungszeile eines umbrochenen Aufzählungspunkts einfügen — sie
	# beginnt weder mit "### " noch mit "- " und darf nicht wortlos wegfallen.
	awk '{ print } /Interaktive Gruppierung/ && !getroffen {
		print "  (Fortsetzungszeile ohne Bindestrich)"; getroffen = 1
	}' "$dir/CHANGELOG.md" > "$dir/CHANGELOG.md.tmp"
	mv "$dir/CHANGELOG.md.tmp" "$dir/CHANGELOG.md"

	ausgabe="$(bash "$dir/bin/sync-readme-changelog.sh" 2>&1 1>/dev/null)"
	rc=$?

	if [ "$rc" -eq 1 ] && printf '%s' "$ausgabe" | grep -qF -- 'Fortsetzungszeile ohne Bindestrich'; then
		pass "sync meldet eine nicht verstandene Zeile im Versionsabschnitt und bricht ab"
	else
		fail "sync meldet eine nicht verstandene Zeile im Versionsabschnitt und bricht ab" \
			"Exit $rc, Ausgabe: $ausgabe"
	fi
}

test_sync_roundtrip
test_sync_check_ok
test_sync_check_erkennt_handedit
test_sync_meldet_unverstandene_zeile

# --- version.sh ------------------------------------------------------------

# Ersetzt in einer Datei der Arbeitskopie einen String durch einen anderen.
verbiege() { # <datei> <suchen> <ersetzen>
	local file="$1" suchen="$2" ersetzen="$3"
	sed -E "s/$suchen/$ersetzen/" "$file" > "$file.tmp"
	mv "$file.tmp" "$file"
}

# Liest SVC_VERSION aus der Arbeitskopie.
#
# Die Tests dürfen keine feste Versionsnummer enthalten: Sonst treffen die
# Suchmuster nach dem nächsten Bump nichts mehr, es wird gar keine Drift
# erzeugt, und die Drift-Tests prüfen stillschweigend nichts.
ist_version() { # <fixture-verzeichnis>
	sed -nE "s/^define\( 'SVC_VERSION', '([0-9]+\.[0-9]+\.[0-9]+)' \);.*\$/\1/p" \
		"$1/swiss-volley-connector/swiss-volley-connector.php"
}

# Eine Version, die garantiert über jeder real vorkommenden liegt — als Ziel
# für Bump-Tests, damit sie nicht an der aktuellen Nummer kleben.
ZIEL_HOCH="99.0.0"

test_version_gleichstand() {
	local dir
	dir="$(fixture)"
	assert_stdout "version.sh druckt die Version bei Gleichstand" "$(ist_version "$dir")" \
		bash "$dir/bin/version.sh"
}

test_version_drift_header() {
	local dir
	dir="$(fixture)"
	verbiege "$dir/swiss-volley-connector/swiss-volley-connector.php" \
		'^ \* Version: +[0-9]+\.[0-9]+\.[0-9]+ *$' ' * Version:           0.9.9'
	assert_exit "version.sh erkennt Drift im Plugin-Header" 1 \
		bash "$dir/bin/version.sh"
}

test_version_drift_konstante() {
	local dir
	dir="$(fixture)"
	verbiege "$dir/swiss-volley-connector/swiss-volley-connector.php" \
		"SVC_VERSION', '[0-9]+\.[0-9]+\.[0-9]+'" "SVC_VERSION', '0.9.9'"
	assert_exit "version.sh erkennt Drift bei SVC_VERSION" 1 \
		bash "$dir/bin/version.sh"
}

test_version_drift_stable_tag() {
	local dir
	dir="$(fixture)"
	verbiege "$dir/swiss-volley-connector/readme.txt" \
		'^Stable tag: +[0-9]+\.[0-9]+\.[0-9]+ *$' 'Stable tag: 0.9.9'
	assert_exit "version.sh erkennt Drift bei Stable tag" 1 \
		bash "$dir/bin/version.sh"
}

test_version_drift_changelog() {
	local dir
	dir="$(fixture)"
	awk '/^## \[/ && !g { print "## [0.9.9]"; g = 1; next } { print }' \
		"$dir/CHANGELOG.md" > "$dir/CHANGELOG.md.tmp"
	mv "$dir/CHANGELOG.md.tmp" "$dir/CHANGELOG.md"
	assert_exit "version.sh erkennt Drift in CHANGELOG.md" 1 \
		bash "$dir/bin/version.sh"
}

test_version_expect_passt() {
	local dir
	dir="$(fixture)"
	assert_exit "version.sh --expect akzeptiert die passende Version" 0 \
		bash "$dir/bin/version.sh" --expect "$(ist_version "$dir")"
}

test_version_expect_weicht_ab() {
	local dir
	dir="$(fixture)"
	assert_exit "version.sh --expect lehnt eine abweichende Version ab" 1 \
		bash "$dir/bin/version.sh" --expect 9.9.9
}

test_version_doppelter_changelog_abschnitt() {
	local dir
	dir="$(fixture)"
	# Zweiten Abschnitt mit derselben Version anlegen.
	printf '\n## [%s]\n\n- Doppelt\n' "$(ist_version "$dir")" >> "$dir/CHANGELOG.md"
	assert_exit "version.sh erkennt einen doppelten Versionsabschnitt" 1 \
		bash "$dir/bin/version.sh"
}

test_version_header_mehrdeutig() {
	local dir ausgabe rc
	dir="$(fixture)"
	# Eine zweite " * Version:"-Zeile einfügen, statt eine bestehende zu
	# überschreiben — die Quelle liefert dann zwei Treffer.
	awk '{ print } /^ \* Version: +[0-9]+\.[0-9]+\.[0-9]+ *$/ && !g { print; g = 1 }' \
		"$dir/swiss-volley-connector/swiss-volley-connector.php" > "$dir/tmp.php"
	mv "$dir/tmp.php" "$dir/swiss-volley-connector/swiss-volley-connector.php"

	ausgabe="$(bash "$dir/bin/version.sh" 2>&1 1>/dev/null)"
	rc=$?

	if [ "$rc" -eq 1 ] && printf '%s' "$ausgabe" | grep -qF -- 'mehrdeutig'; then
		pass "version.sh erkennt einen mehrdeutigen Treffer und weist ihn als solchen aus"
	else
		fail "version.sh erkennt einen mehrdeutigen Treffer und weist ihn als solchen aus" \
			"Exit $rc, Ausgabe: $ausgabe"
	fi
}

test_version_gleichstand
test_version_header_mehrdeutig
test_version_drift_header
test_version_drift_konstante
test_version_drift_stable_tag
test_version_drift_changelog
test_version_expect_passt
test_version_expect_weicht_ab
test_version_doppelter_changelog_abschnitt

# --- bump-version.sh -------------------------------------------------------

test_bump_schreibt_alle_stellen() {
	local dir
	dir="$(fixture)"
	bash "$dir/bin/bump-version.sh" "$ZIEL_HOCH" > /dev/null 2>&1
	assert_stdout "bump-version.sh hebt alle vier Quellen an" "$ZIEL_HOCH" \
		bash "$dir/bin/version.sh"
}

test_bump_legt_changelog_abschnitt_an() {
	local dir
	dir="$(fixture)"
	bash "$dir/bin/bump-version.sh" "$ZIEL_HOCH" > /dev/null 2>&1
	if grep -qE "^## \[$(printf '%s' "$ZIEL_HOCH" | sed 's/\./\\./g')\] - [0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]\$" "$dir/CHANGELOG.md" \
		&& grep -qF -- '- TODO: Änderungen beschreiben' "$dir/CHANGELOG.md"; then
		pass "bump-version.sh legt einen datierten CHANGELOG-Abschnitt mit Platzhalter an"
	else
		fail "bump-version.sh legt einen datierten CHANGELOG-Abschnitt mit Platzhalter an" \
			"$(head -12 "$dir/CHANGELOG.md")"
	fi
}

test_bump_haelt_readme_synchron() {
	local dir
	dir="$(fixture)"
	bash "$dir/bin/bump-version.sh" "$ZIEL_HOCH" > /dev/null 2>&1
	assert_exit "bump-version.sh hinterlässt readme.txt synchron" 0 \
		bash "$dir/bin/sync-readme-changelog.sh" --check
}

test_bump_lehnt_ungueltiges_format_ab() {
	local dir
	dir="$(fixture)"
	assert_exit "bump-version.sh lehnt '0.2' ab" 1 \
		bash "$dir/bin/bump-version.sh" 0.2
}

test_bump_lehnt_gleichstand_ab() {
	local dir
	dir="$(fixture)"
	assert_exit "bump-version.sh lehnt die aktuelle Version ab" 1 \
		bash "$dir/bin/bump-version.sh" "$(ist_version "$dir")"
}

test_bump_lehnt_rueckwaerts_ab() {
	local dir
	dir="$(fixture)"
	assert_exit "bump-version.sh lehnt einen Rückwärtssprung ab" 1 \
		bash "$dir/bin/bump-version.sh" 0.0.1
}

test_bump_lehnt_drift_ab() {
	local dir
	dir="$(fixture)"
	verbiege "$dir/swiss-volley-connector/readme.txt" \
		'^Stable tag: +[0-9]+\.[0-9]+\.[0-9]+ *$' 'Stable tag: 0.9.9'
	assert_exit "bump-version.sh verweigert den Bump auf driftendem Stand" 1 \
		bash "$dir/bin/bump-version.sh" "$ZIEL_HOCH"
}

test_bump_schreibt_alle_stellen
test_bump_legt_changelog_abschnitt_an
test_bump_haelt_readme_synchron
test_bump_lehnt_ungueltiges_format_ab
test_bump_lehnt_gleichstand_ab
test_bump_lehnt_rueckwaerts_ab
test_bump_lehnt_drift_ab

# --- release-notes.sh ------------------------------------------------------

test_notes_liefert_abschnitt() {
	local dir ausgabe
	dir="$(fixture)"
	ausgabe="$(bash "$dir/bin/release-notes.sh" 0.1.7 2>/dev/null)"
	if printf '%s' "$ausgabe" | grep -q '^### Neu$' \
		&& printf '%s' "$ausgabe" | grep -q 'Interaktive Gruppierung' \
		&& ! printf '%s' "$ausgabe" | grep -q '^## \['; then
		pass "release-notes.sh liefert den Abschnitt ohne die Überschrift"
	else
		fail "release-notes.sh liefert den Abschnitt ohne die Überschrift" "$ausgabe"
	fi
}

test_notes_endet_vor_naechster_version() {
	local dir ausgabe
	dir="$(fixture)"
	ausgabe="$(bash "$dir/bin/release-notes.sh" 0.1.7 2>/dev/null)"
	if printf '%s' "$ausgabe" | grep -q 'Gruppierung von Spiellisten'; then
		fail "release-notes.sh endet vor der nächsten Version" \
			"Inhalt von 0.1.6 ist mit ausgegeben worden"
	else
		pass "release-notes.sh endet vor der nächsten Version"
	fi
}

test_notes_fehlender_abschnitt() {
	local dir
	dir="$(fixture)"
	assert_exit "release-notes.sh scheitert bei fehlendem Abschnitt" 1 \
		bash "$dir/bin/release-notes.sh" 9.9.9
}

test_notes_platzhalter() {
	local dir
	dir="$(fixture)"
	bash "$dir/bin/bump-version.sh" "$ZIEL_HOCH" > /dev/null 2>&1
	assert_exit "release-notes.sh scheitert bei unausgefülltem Platzhalter" 1 \
		bash "$dir/bin/release-notes.sh" "$ZIEL_HOCH"
}

test_notes_ohne_argument() {
	local dir
	dir="$(fixture)"
	assert_exit "release-notes.sh scheitert ohne Versionsargument" 1 \
		bash "$dir/bin/release-notes.sh"
}

test_notes_nur_kategorie_ohne_eintrag() {
	local dir
	dir="$(fixture)"
	# Abschnitt besteht nur aus einer Kategorie-Überschrift, kein "- "-Eintrag.
	awk '/^## \[0\.1\.6\]/ && !g { print "## [9.8.7]"; print ""; print "### Neu"; print ""; g = 1 } { print }' \
		"$dir/CHANGELOG.md" > "$dir/CHANGELOG.md.tmp"
	mv "$dir/CHANGELOG.md.tmp" "$dir/CHANGELOG.md"
	assert_exit "release-notes.sh scheitert bei einem Abschnitt nur mit Kategorie-Überschrift" 1 \
		bash "$dir/bin/release-notes.sh" 9.8.7
}

test_notes_liefert_abschnitt
test_notes_endet_vor_naechster_version
test_notes_fehlender_abschnitt
test_notes_platzhalter
test_notes_ohne_argument
test_notes_nur_kategorie_ohne_eintrag

# --- Ergebnis --------------------------------------------------------------

printf '\n%d bestanden, %d fehlgeschlagen\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
