#!/usr/bin/env bash
#
# Liest die Version aus allen Quellen und prüft, dass sie übereinstimmen.
#
# Aufruf:
#   bin/version.sh                 druckt die Version, Exit 0
#   bin/version.sh --expect X.Y.Z  prüft zusätzlich gegen X.Y.Z (Git-Tag)
#
# Bei jeder Abweichung: Tabelle aller Fundstellen auf stderr, Exit 1.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php"
README="$ROOT/volleyball-schedules-for-swiss-volley/readme.txt"
CHANGELOG="$ROOT/CHANGELOG.md"

EXPECT=""
if [ "${1:-}" = "--expect" ]; then
	EXPECT="${2:-}"
	if [ -z "$EXPECT" ]; then
		echo "Fehler: --expect braucht eine Version." >&2
		exit 1
	fi
elif [ -n "${1:-}" ]; then
	echo "Unbekanntes Argument: $1" >&2
	echo "Aufruf: bin/version.sh [--expect X.Y.Z]" >&2
	exit 1
fi

# Zählt die Treffer (Zeilen) einer möglicherweise leeren Mehrzeilen-Zeichenkette.
anzahl_treffer() {
	local wert="$1"
	if [ -z "$wert" ]; then
		printf '0'
	else
		printf '%s\n' "$wert" | wc -l | tr -d ' \t'
	fi
}

# Gibt den Wert aus, wenn genau eine Zeile gefunden wurde, sonst nichts.
genau_eine() {
	local wert="$1"
	if [ "$(anzahl_treffer "$wert")" != "1" ]; then
		return 0
	fi
	printf '%s' "$wert"
}

v_header_roh="$(sed -nE 's/^ \* Version: +([0-9]+\.[0-9]+\.[0-9]+) *$/\1/p' "$PLUGIN")"
v_konstante_roh="$(sed -nE "s/^define\( 'VSSV_VERSION', '([0-9]+\.[0-9]+\.[0-9]+)' \);.*$/\1/p" "$PLUGIN")"
v_stable_roh="$(sed -nE 's/^Stable tag: +([0-9]+\.[0-9]+\.[0-9]+) *$/\1/p' "$README")"

v_header="$(genau_eine "$v_header_roh")"
v_konstante="$(genau_eine "$v_konstante_roh")"
v_stable="$(genau_eine "$v_stable_roh")"
v_changelog="$(sed -nE 's/^## \[([0-9]+\.[0-9]+\.[0-9]+)\].*$/\1/p' "$CHANGELOG" | head -1)"

# Diagnosetext für die Tabelle: unterscheidet "nicht gefunden" (0 Treffer)
# von "mehrdeutig" (mehr als 1 Treffer) statt beides gleich zu behandeln.
diagnose() { # <wert> <roh>
	local wert="$1" roh="$2" n
	if [ -n "$wert" ]; then
		printf '%s' "$wert"
		return 0
	fi
	n="$(anzahl_treffer "$roh")"
	if [ "$n" -eq 0 ]; then
		printf '(nicht gefunden)'
	else
		printf '(mehrdeutig: %s Treffer)' "$n"
	fi
}

# Doppelte Versionsabschnitte fallen sonst nicht auf: release-notes.sh
# nähme nur den ersten, und der Generator schriebe zwei identische
# "= X.Y.Z ="-Blöcke in readme.txt.
doppelt="$(sed -nE 's/^## \[([0-9]+\.[0-9]+\.[0-9]+)\].*$/\1/p' "$CHANGELOG" | sort | uniq -d)"
if [ -n "$doppelt" ]; then
	echo "Fehler: CHANGELOG.md enthält mehrfach denselben Versionsabschnitt: $(printf '%s' "$doppelt" | tr '\n' ' ')" >&2
	exit 1
fi

tabelle() {
	printf '  %-16s %-52s %s\n' "Quelle" "Datei" "Wert" >&2
	printf '  %-16s %-52s %s\n' "Plugin-Header" "volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php" "$(diagnose "$v_header" "$v_header_roh")" >&2
	printf '  %-16s %-52s %s\n' "VSSV_VERSION" "volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php" "$(diagnose "$v_konstante" "$v_konstante_roh")" >&2
	printf '  %-16s %-52s %s\n' "Stable tag" "volleyball-schedules-for-swiss-volley/readme.txt" "$(diagnose "$v_stable" "$v_stable_roh")" >&2
	printf '  %-16s %-52s %s\n' "CHANGELOG.md" "CHANGELOG.md" "${v_changelog:-(nicht gefunden)}" >&2
	if [ -n "$EXPECT" ]; then
		printf '  %-16s %-52s %s\n' "Erwartet (Tag)" "-" "$EXPECT" >&2
	fi
}

if [ -z "$v_header" ] || [ -z "$v_konstante" ] || [ -z "$v_stable" ] || [ -z "$v_changelog" ]; then
	echo "Fehler: Mindestens eine Versionsangabe fehlt oder ist mehrdeutig." >&2
	echo >&2
	tabelle
	exit 1
fi

if [ "$v_header" != "$v_konstante" ] || [ "$v_header" != "$v_stable" ] || [ "$v_header" != "$v_changelog" ]; then
	echo "Fehler: Die Versionsangaben stimmen nicht überein." >&2
	echo >&2
	tabelle
	exit 1
fi

if [ -n "$EXPECT" ] && [ "$EXPECT" != "$v_header" ]; then
	echo "Fehler: Der erwartete Wert $EXPECT passt nicht zu den Dateien." >&2
	echo >&2
	tabelle
	exit 1
fi

printf '%s\n' "$v_header"
