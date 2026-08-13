#!/usr/bin/env bash
#
# Erzeugt die Sektion "== Changelog ==" in readme.txt aus CHANGELOG.md.
#
# Aufruf:
#   bin/sync-readme-changelog.sh           schreibt readme.txt
#   bin/sync-readme-changelog.sh --check   prüft nur, schreibt nichts

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CHANGELOG="$ROOT/CHANGELOG.md"
README="$ROOT/volleyball-schedules-for-swiss-volley/readme.txt"

CHECK=0
if [ "${1:-}" = "--check" ]; then
	CHECK=1
elif [ -n "${1:-}" ]; then
	echo "Unbekanntes Argument: $1" >&2
	echo "Aufruf: bin/sync-readme-changelog.sh [--check]" >&2
	exit 1
fi

# Erzeugt die vollständige Sektion "== Changelog ==" auf stdout.
#
# Aufbau der Ausgabe (entspricht dem bisherigen Aufbau der readme.txt):
#   == Changelog ==
#   <leer>
#   = X.Y.Z =
#   * <Eintrag>
#   <leer>
#   = X.Y.Y =
#   ...
generate() {
	printf '== Changelog ==\n'
	awk '
		/^## \[[0-9]+\.[0-9]+\.[0-9]+\]/ {
			match($0, /\[[0-9]+\.[0-9]+\.[0-9]+\]/)
			version = substr($0, RSTART + 1, RLENGTH - 2)
			printf "\n= %s =\n", version
			category = ""
			next
		}
		version == "" { next }          # Kopfteil der CHANGELOG.md überspringen
		/^$/ { next }                   # Leerzeilen innerhalb eines Abschnitts erlaubt
		/^### / { category = substr($0, 5); next }
		/^- / {
			text = substr($0, 3)
			if (category == "") {
				printf "* %s\n", text
			} else {
				printf "* %s: %s\n", category, text
			}
			next
		}
		{
			printf "Fehler: Nicht verstandene Zeile in CHANGELOG.md (Version %s): %s\n", version, $0 > "/dev/stderr"
			exit 1
		}
	' "$CHANGELOG"
}

# Alles vor der Zeile "== Changelog ==" unverändert übernehmen.
head_part() {
	sed -n '1,/^== Changelog ==/p' "$README" | sed '$d'
}

neu="$(head_part; generate)"

if [ "$CHECK" -eq 1 ]; then
	if printf '%s\n' "$neu" | diff -q "$README" - > /dev/null 2>&1; then
		echo "readme.txt ist synchron mit CHANGELOG.md"
	else
		echo "Fehler: readme.txt weicht von CHANGELOG.md ab." >&2
		echo "Nicht die readme.txt ändern, sondern CHANGELOG.md pflegen und" >&2
		echo "bin/sync-readme-changelog.sh laufen lassen." >&2
		echo >&2
		printf '%s\n' "$neu" | diff -u "$README" - >&2 || true
		exit 1
	fi
else
	printf '%s\n' "$neu" > "$README.tmp"
	mv "$README.tmp" "$README"
	echo "readme.txt aus CHANGELOG.md erzeugt"
fi
