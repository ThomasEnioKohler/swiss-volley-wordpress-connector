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
	local dir referenz erzeugt
	dir="$(fixture)"
	referenz="$dir/referenz.txt"
	erzeugt="$dir/erzeugt.txt"

	# Die Sektion, wie sie vor der Migration in readme.txt stand.
	sed -n '/^== Changelog ==/,$p' "$dir/swiss-volley-connector/readme.txt" > "$referenz"

	bash "$dir/bin/sync-readme-changelog.sh" > /dev/null
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

test_sync_roundtrip
test_sync_check_ok
test_sync_check_erkennt_handedit

# --- version.sh ------------------------------------------------------------

# Ersetzt in einer Datei der Arbeitskopie einen String durch einen anderen.
verbiege() { # <datei> <suchen> <ersetzen>
	local file="$1" suchen="$2" ersetzen="$3"
	sed -E "s/$suchen/$ersetzen/" "$file" > "$file.tmp"
	mv "$file.tmp" "$file"
}

test_version_gleichstand() {
	local dir
	dir="$(fixture)"
	assert_stdout "version.sh druckt die Version bei Gleichstand" "0.1.7" \
		bash "$dir/bin/version.sh"
}

test_version_drift_header() {
	local dir
	dir="$(fixture)"
	verbiege "$dir/swiss-volley-connector/swiss-volley-connector.php" \
		'^ \* Version: +0\.1\.7' ' * Version:           0.9.9'
	assert_exit "version.sh erkennt Drift im Plugin-Header" 1 \
		bash "$dir/bin/version.sh"
}

test_version_drift_konstante() {
	local dir
	dir="$(fixture)"
	verbiege "$dir/swiss-volley-connector/swiss-volley-connector.php" \
		"SVC_VERSION', '0\.1\.7'" "SVC_VERSION', '0.9.9'"
	assert_exit "version.sh erkennt Drift bei SVC_VERSION" 1 \
		bash "$dir/bin/version.sh"
}

test_version_drift_stable_tag() {
	local dir
	dir="$(fixture)"
	verbiege "$dir/swiss-volley-connector/readme.txt" \
		'^Stable tag: 0\.1\.7' 'Stable tag: 0.9.9'
	assert_exit "version.sh erkennt Drift bei Stable tag" 1 \
		bash "$dir/bin/version.sh"
}

test_version_drift_changelog() {
	local dir
	dir="$(fixture)"
	verbiege "$dir/CHANGELOG.md" '^## \[0\.1\.7\]' '## [0.9.9]'
	assert_exit "version.sh erkennt Drift in CHANGELOG.md" 1 \
		bash "$dir/bin/version.sh"
}

test_version_expect_passt() {
	local dir
	dir="$(fixture)"
	assert_exit "version.sh --expect akzeptiert die passende Version" 0 \
		bash "$dir/bin/version.sh" --expect 0.1.7
}

test_version_expect_weicht_ab() {
	local dir
	dir="$(fixture)"
	assert_exit "version.sh --expect lehnt eine abweichende Version ab" 1 \
		bash "$dir/bin/version.sh" --expect 0.2.0
}

test_version_doppelter_changelog_abschnitt() {
	local dir
	dir="$(fixture)"
	# Zweiten Abschnitt mit derselben Version anlegen.
	awk '/^## \[0\.1\.6\]/ && !g { print "## [0.1.7]"; print ""; print "- Doppelt"; print ""; g = 1 } { print }' \
		"$dir/CHANGELOG.md" > "$dir/CHANGELOG.md.tmp"
	mv "$dir/CHANGELOG.md.tmp" "$dir/CHANGELOG.md"
	assert_exit "version.sh erkennt einen doppelten Versionsabschnitt" 1 \
		bash "$dir/bin/version.sh"
}

test_version_gleichstand
test_version_drift_header
test_version_drift_konstante
test_version_drift_stable_tag
test_version_drift_changelog
test_version_expect_passt
test_version_expect_weicht_ab
test_version_doppelter_changelog_abschnitt

# --- Ergebnis --------------------------------------------------------------

printf '\n%d bestanden, %d fehlgeschlagen\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
