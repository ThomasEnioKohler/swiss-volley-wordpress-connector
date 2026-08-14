#!/usr/bin/env bash
#
# Hebt die Plugin-Version an allen Stellen an und legt einen leeren
# Changelog-Abschnitt an.
#
# Aufruf: bin/bump-version.sh X.Y.Z
#
# Committet und taggt bewusst nicht — das macht der Command /release
# nach Rückfrage.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php"
README="$ROOT/volleyball-schedules-for-swiss-volley/readme.txt"
CHANGELOG="$ROOT/CHANGELOG.md"
PLATZHALTER="- TODO: Änderungen beschreiben"

NEU="${1:-}"
if [ -z "$NEU" ]; then
	echo "Aufruf: bin/bump-version.sh X.Y.Z" >&2
	exit 1
fi

if ! printf '%s' "$NEU" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'; then
	echo "Fehler: '$NEU' ist keine Version der Form MAJOR.MINOR.PATCH." >&2
	exit 1
fi

# Bricht mit Tabelle ab, wenn der Ist-Zustand driftet.
ALT="$("$ROOT/bin/version.sh")"

# Gibt 0 zurück, wenn $1 echt grösser als $2 ist.
version_groesser() {
	local IFS=.
	local -a a=($1) b=($2)
	local i
	for i in 0 1 2; do
		if [ "${a[i]}" -gt "${b[i]}" ]; then return 0; fi
		if [ "${a[i]}" -lt "${b[i]}" ]; then return 1; fi
	done
	return 1
}

if ! version_groesser "$NEU" "$ALT"; then
	echo "Fehler: $NEU ist nicht grösser als die aktuelle Version $ALT." >&2
	exit 1
fi

# sed in eine Temporärdatei, weil BSD- und GNU-sed bei -i unterschiedlich sind.
ersetze() { # <datei> <sed-ausdruck>
	local file="$1" ausdruck="$2"
	sed -E "$ausdruck" "$file" > "$file.tmp"
	mv "$file.tmp" "$file"
}

# Punkte maskieren: unmaskiert würde "." in "0.1.7" jedes Zeichen treffen.
ALT_RE="${ALT//./\\.}"

ersetze "$PLUGIN" "s/^( \* Version: +)$ALT_RE *\$/\\1$NEU/"
ersetze "$PLUGIN" "s/^(define\\( 'VSSV_VERSION', ')$ALT_RE(' \\);.*)\$/\\1$NEU\\2/"
ersetze "$README" "s/^(Stable tag: +)$ALT_RE *\$/\\1$NEU/"

HEUTE="$(date +%F)"
awk -v ver="$NEU" -v tag="$HEUTE" -v platzhalter="$PLATZHALTER" '
	!eingefuegt && /^## \[/ {
		printf "## [%s] - %s\n\n%s\n\n", ver, tag, platzhalter
		eingefuegt = 1
	}
	{ print }
	END {
		if (!eingefuegt) {
			printf "\n## [%s] - %s\n\n%s\n", ver, tag, platzhalter
		}
	}
' "$CHANGELOG" > "$CHANGELOG.tmp"
mv "$CHANGELOG.tmp" "$CHANGELOG"

"$ROOT/bin/sync-readme-changelog.sh" > /dev/null

printf 'Version %s -> %s\n' "$ALT" "$NEU"
printf '  %-46s Version:\n' "volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php"
printf '  %-46s VSSV_VERSION\n' "volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php"
printf '  %-46s Stable tag:\n' "volleyball-schedules-for-swiss-volley/readme.txt"
printf '  %-46s neuer Abschnitt ## [%s] - %s\n' "CHANGELOG.md" "$NEU" "$HEUTE"
printf '  %-46s neu erzeugt\n' "volleyball-schedules-for-swiss-volley/readme.txt"
printf '\nNoch zu tun: %s in CHANGELOG.md ersetzen, dann sync erneut laufen lassen.\n' "$PLATZHALTER"
