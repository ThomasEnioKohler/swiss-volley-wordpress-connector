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
PLUGIN="$ROOT/swiss-volley-connector/swiss-volley-connector.php"
README="$ROOT/swiss-volley-connector/readme.txt"
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

# Gibt den Wert aus, wenn genau eine Zeile gefunden wurde, sonst nichts.
genau_eine() {
	local wert="$1"
	if [ -z "$wert" ] || [ "$wert" != "${wert%$'\n'*}" ]; then
		return 0
	fi
	printf '%s' "$wert"
}

v_header="$(genau_eine "$(sed -nE 's/^ \* Version: +([0-9]+\.[0-9]+\.[0-9]+) *$/\1/p' "$PLUGIN")")"
v_konstante="$(genau_eine "$(sed -nE "s/^define\( 'SVC_VERSION', '([0-9]+\.[0-9]+\.[0-9]+)' \);.*$/\1/p" "$PLUGIN")")"
v_stable="$(genau_eine "$(sed -nE 's/^Stable tag: +([0-9]+\.[0-9]+\.[0-9]+) *$/\1/p' "$README")")"
v_changelog="$(sed -nE 's/^## \[([0-9]+\.[0-9]+\.[0-9]+)\].*$/\1/p' "$CHANGELOG" | head -1)"

tabelle() {
	printf '  %-16s %-52s %s\n' "Quelle" "Datei" "Wert" >&2
	printf '  %-16s %-52s %s\n' "Plugin-Header" "swiss-volley-connector/swiss-volley-connector.php" "${v_header:-(nicht gefunden)}" >&2
	printf '  %-16s %-52s %s\n' "SVC_VERSION" "swiss-volley-connector/swiss-volley-connector.php" "${v_konstante:-(nicht gefunden)}" >&2
	printf '  %-16s %-52s %s\n' "Stable tag" "swiss-volley-connector/readme.txt" "${v_stable:-(nicht gefunden)}" >&2
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
