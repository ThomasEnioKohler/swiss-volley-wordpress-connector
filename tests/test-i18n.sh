#!/usr/bin/env bash
#
# Prüft die Quellsprache und die Vollständigkeit der Übersetzungen.
#
# Aufruf: bash tests/test-i18n.sh
# Voraussetzungen: bash, python3, msgfmt (gettext)

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/volleyball-schedules-for-swiss-volley"
POT="$PLUGIN/languages/volleyball-schedules-for-swiss-volley.pot"
PASS=0
FAIL=0

pass() { printf 'PASS  %s\n' "$1"; PASS=$((PASS + 1)); }
fail() { printf 'FAIL  %s\n      %s\n' "$1" "$2"; FAIL=$((FAIL + 1)); }

python3 "$ROOT/bin/make-pot.py" "$PLUGIN" > /dev/null

# Deutsche Quellstrings fallen über Umlaute und Eszett auf. Reine
# ASCII-Prüfung, damit auch typografische Zeichen auffliegen, die in
# msgid nichts zu suchen haben. Die Prüfung läuft über python3 statt
# grep -P: BSD-grep auf macOS kennt -P nicht.
# Geprüft werden sowohl msgid- als auch msgctxt-Zeilen: msgctxt ist das
# Kontext-Argument von _x()/esc_html_x() & Co. und damit genauso Teil der
# Quellsprache wie msgid — ein rein deutscher Kontext ("Ranglisten-Spalte")
# würde sonst unbemerkt ins POT und in Task 3s Kataloge durchrutschen.
test_pot_ist_ascii() {
	local treffer
	treffer="$(python3 - "$POT" <<'PY'
import sys
pfad = sys.argv[1]
for nr, zeile in enumerate(open(pfad, encoding='utf-8'), 1):
    if (zeile.startswith('msgid ') or zeile.startswith('msgctxt ')) and not zeile.isascii():
        print(f'{nr}: {zeile.rstrip()}')
PY
)"
	if [ -z "$treffer" ]; then
		pass "POT enthält ausschliesslich ASCII-msgid-/msgctxt-Einträge"
	else
		fail "POT enthält ausschliesslich ASCII-msgid-/msgctxt-Einträge" \
			"Nicht-ASCII in: $treffer"
	fi
}

# Ein falscher Textdomain-String faellt im Betrieb nicht auf: die
# Uebersetzung greift stumm nicht, der englische Quelltext erscheint.
# Geprueft wird deshalb, dass KEIN Uebersetzungsaufruf eine andere
# Domain verwendet als den Slug.
test_textdomain_konsistent() {
	local falsch
	falsch="$(grep -rnoE "(__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e|esc_html_x|esc_attr_x)\( *'[^']*' *(, *'[^']*' *)*, *'[^']+'" \
		"$PLUGIN" --include='*.php' --include='*.js' \
		| grep -v "'volleyball-schedules-for-swiss-volley'" || true)"
	if [ -z "$falsch" ]; then
		pass "alle Übersetzungsaufrufe verwenden die Text Domain des Slugs"
	else
		fail "alle Übersetzungsaufrufe verwenden die Text Domain des Slugs" "$falsch"
	fi
}

# Ein Katalog, der die POT nicht vollstaendig abdeckt, zeigt im Betrieb
# eine gemischtsprachige Oberflaeche - das faellt oft erst Nutzern auf.
test_katalog_vollstaendig() { # <locale>
	local locale="$1"
	local po="$PLUGIN/languages/volleyball-schedules-for-swiss-volley-$locale.po"
	local mo="${po%.po}.mo"
	local fehlend

	if [ ! -f "$po" ]; then
		fail "$locale ist vollständig übersetzt" "$po fehlt"
		return
	fi
	if [ ! -f "$mo" ]; then
		fail "$locale ist vollständig übersetzt" "$mo fehlt (msgfmt vergessen?)"
		return
	fi

	# msgcmp meldet sowohl fehlende als auch leere Eintraege - genau die
	# beiden Faelle, die im Betrieb eine gemischtsprachige Oberflaeche
	# ergeben.
	fehlend="$(msgcmp "$po" "$POT" 2>&1 || true)"
	if [ -z "$fehlend" ]; then
		pass "$locale ist vollständig übersetzt"
	else
		fail "$locale ist vollständig übersetzt" "$fehlend"
	fi
}

test_pot_ist_ascii
test_textdomain_konsistent
test_katalog_vollstaendig de_CH
test_katalog_vollstaendig de_DE

printf '\n%d bestanden, %d fehlgeschlagen\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
