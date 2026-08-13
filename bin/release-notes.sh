#!/usr/bin/env bash
#
# Gibt den Changelog-Abschnitt einer Version als Markdown aus — der Text,
# der im GitHub-Release als Beschreibung erscheint.
#
# Aufruf: bin/release-notes.sh X.Y.Z

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CHANGELOG="$ROOT/CHANGELOG.md"
PLATZHALTER="- TODO: Änderungen beschreiben"

VERSION="${1:-}"
if [ -z "$VERSION" ]; then
	echo "Aufruf: bin/release-notes.sh X.Y.Z" >&2
	exit 1
fi

# Alles zwischen "## [VERSION]" und der nächsten "## ["-Zeile,
# führende Leerzeilen abgeschnitten.
inhalt="$(awk -v ver="$VERSION" '
	index($0, "## [" ver "]") == 1 { drin = 1; next }
	drin && /^## \[/ { exit }
	drin { print }
' "$CHANGELOG" | awk 'NF { gefunden = 1 } gefunden { print }')"

if [ -z "$inhalt" ]; then
	echo "Fehler: In CHANGELOG.md gibt es keinen Abschnitt '## [$VERSION]' oder er ist leer." >&2
	exit 1
fi

if printf '%s' "$inhalt" | grep -qF -- "$PLATZHALTER"; then
	echo "Fehler: Der Abschnitt '## [$VERSION]' enthält noch den Platzhalter." >&2
	echo "Trage die Änderungen in CHANGELOG.md ein, bevor du releast." >&2
	exit 1
fi

printf '%s\n' "$inhalt"
