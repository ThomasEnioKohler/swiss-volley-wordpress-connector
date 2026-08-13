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

# --- Ergebnis --------------------------------------------------------------

printf '\n%d bestanden, %d fehlgeschlagen\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
