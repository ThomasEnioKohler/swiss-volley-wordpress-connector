# Release- und Versionshandling — Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein Git-Tag `vX.Y.Z` erzeugt einen GitHub-Release mit installierbarem ZIP und echten Changelog-Notes; jede Versionsabweichung bricht den Vorgang ab, bevor etwas veröffentlicht wird.

**Architecture:** Vier kleine Bash-Skripte unter `bin/` tragen die gesamte Prüf- und Schreiblogik und laufen lokal wie in CI identisch. `CHANGELOG.md` im Repo-Root ist die einzige gepflegte Changelog-Quelle; die Sektion `== Changelog ==` in `readme.txt` wird daraus erzeugt und per `--check` verifiziert. Der Claude-Command `/release` ist nur der fragende Fahrer über denselben Skripten.

**Tech Stack:** Bash (portabel für BSD/macOS und GNU/Linux), `sed -E`, `awk`, GitHub Actions, `softprops/action-gh-release@v2`.

## Global Constraints

- Zielplattformen: macOS (BSD-Werkzeuge) **und** ubuntu-latest (GNU). Jedes Skript muss auf beiden laufen.
- `grep -P` ist verboten — BSD-grep kennt kein PCRE. Für Reguläres `sed -E` oder `grep -E` verwenden.
- `sed -i` ist verboten — BSD und GNU erwarten unterschiedliche Syntax. Stattdessen in `$file.tmp` schreiben und `mv`.
- `sort -V` nicht verwenden — Versionsvergleich als reine Bash-Funktion (siehe Task 3).
- Keine neuen Abhängigkeiten. Erlaubt ist, was `bin/build.sh` schon voraussetzt: bash, php, python3, zip.
- Alle Skripte beginnen mit `#!/usr/bin/env bash`, sind ausführbar (`chmod +x`) und ermitteln das Projektwurzelverzeichnis mit `ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"`.
- Nutzersichtbare Skriptausgaben auf Deutsch, Fehlermeldungen nach stderr, Erfolgsausgaben nach stdout.
- Commit-Messages auf Englisch, Conventional Commits, mit Trailer `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.
- Der Platzhalter für einen noch nicht ausgefüllten Changelog-Eintrag lautet wörtlich `- TODO: Änderungen beschreiben`. `bin/release-notes.sh` muss ihn erkennen und den Release verweigern.
- Die vier Versionsquellen sind: Plugin-Header `Version:`, `define( 'SVC_VERSION', … )`, `Stable tag:` in `readme.txt`, oberste `## [X.Y.Z]` in `CHANGELOG.md`.
- Die Sektion `== Changelog ==` in `readme.txt` ist **generiert**. Sie ist keine Versionsquelle und wird nie von Hand editiert.

## Dateiübersicht

| Datei | Verantwortung | Task |
| --- | --- | --- |
| `CHANGELOG.md` | Kanonische Changelog-Historie, Keep-a-Changelog | 1 |
| `bin/sync-readme-changelog.sh` | Erzeugt/prüft `== Changelog ==` in `readme.txt` | 1 |
| `tests/test-version-tools.sh` | Bash-Tests für alle vier Skripte | 1–4 |
| `bin/version.sh` | Liest und vergleicht die vier Versionsquellen | 2 |
| `bin/bump-version.sh` | Hebt die Version an, legt CHANGELOG-Abschnitt an | 3 |
| `bin/release-notes.sh` | Schneidet den Release-Body aus `CHANGELOG.md` | 4 |
| `bin/build.sh` | Neuer Schritt 0, Version aus `version.sh`, ZIP-Ausschlüsse | 5 |
| `.github/workflows/release.yml` | Tag-Prüfung, Build, Release mit ZIP und Notes | 6 |
| `.github/workflows/ci.yml` | Eigener Job für die Versions-Werkzeuge | 6 |
| `.claude/commands/release.md` | Slash-Command `/release` | 7 |
| `README.md` | Dokumentierter Release-Ablauf | 8 |

---

### Task 1: CHANGELOG.md und Generator für readme.txt

Der Beweis dieser Aufgabe ist der Round-Trip: Aus der migrierten `CHANGELOG.md` muss der Generator die Sektion `== Changelog ==` byteweise so erzeugen, wie sie heute in `readme.txt` steht. Erst dann ist belegt, dass die Formatumstellung für Nutzer folgenlos ist.

**Files:**
- Create: `CHANGELOG.md`
- Create: `bin/sync-readme-changelog.sh`
- Create: `tests/test-version-tools.sh`
- Read (Quelle der Migration): `swiss-volley-connector/readme.txt:86-118`

**Interfaces:**
- Consumes: nichts
- Produces:
  - `CHANGELOG.md` mit Abschnitten `## [X.Y.Z]` (optional gefolgt von ` - YYYY-MM-DD`), Kategorien als `### <Name>`, Einträge als `- <Text>`
  - `bash bin/sync-readme-changelog.sh` → schreibt `readme.txt`, Exit 0
  - `bash bin/sync-readme-changelog.sh --check` → schreibt nichts; Exit 0 bei Gleichstand, Exit 1 mit Diff auf stderr bei Abweichung
  - `tests/test-version-tools.sh` mit den Hilfsfunktionen `pass`, `fail`, `fixture`, `assert_exit`, `assert_stdout`, die Task 2–4 weiterverwenden

- [ ] **Step 1: Sicherungskopie der heutigen Changelog-Sektion anlegen**

Diese Datei ist die Referenz für den Round-Trip-Test und wird nicht committet.

```bash
cd "/Users/thomaskohler/Entwicklung/Swiss Volley WordPress Connector"
sed -n '/^== Changelog ==/,$p' swiss-volley-connector/readme.txt > /tmp/changelog-referenz.txt
wc -l /tmp/changelog-referenz.txt
```

Erwartet: 33 Zeilen.

- [ ] **Step 2: Testgerüst und den fehlschlagenden Round-Trip-Test schreiben**

Datei `tests/test-version-tools.sh`:

```bash
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
```

- [ ] **Step 3: Test ausführen, Fehlschlag bestätigen**

Run: `bash tests/test-version-tools.sh`
Expected: FAIL — `cp: .../CHANGELOG.md: No such file or directory` bzw. `bin/sync-readme-changelog.sh: No such file or directory`.

- [ ] **Step 4: CHANGELOG.md aus readme.txt migrieren**

Quelle ist `swiss-volley-connector/readme.txt` ab Zeile 86. Umwandlungsregel, wörtlich anzuwenden:

| readme.txt | CHANGELOG.md |
| --- | --- |
| `= 0.1.7 =` | `## [0.1.7]` |
| `* Neu: <Text>` | `### Neu` (einmal je Kategorieblock) plus `- <Text>` |
| `* <Text>` ohne Kategorie-Präfix | `- <Text>` direkt unter der Versionsüberschrift |

Die Texte selbst werden **zeichengenau übernommen** — keine Umformulierung, keine Kürzung, keine Korrektur von Tippfehlern. Historische Einträge bekommen kein Datum.

Kopf der Datei:

```markdown
# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung an [Semantic Versioning](https://semver.org/lang/de/).

Diese Datei ist die gepflegte Quelle. Die Sektion `== Changelog ==` in
`swiss-volley-connector/readme.txt` wird daraus erzeugt — dort nichts von Hand
ändern, sondern `bin/sync-readme-changelog.sh` laufen lassen.
```

Danach die acht Versionen. 0.1.7 bis 0.1.1 haben je genau eine `* Neu:`-Zeile, werden also zu:

```markdown
## [0.1.7]

### Neu
- <Text der 0.1.7-Zeile, zeichengenau ohne das Präfix "Neu: ">
```

0.1.0 hat neun Zeilen ohne Präfix und wird zu:

```markdown
## [0.1.0]

- Erste Version (Private Beta)
- Anbindung an die offizielle Swiss-Volley-API (/indoor/games, /indoor/rankings)
- <weitere sieben Zeilen, zeichengenau>
```

**Nicht abtippen.** Umlaute, Guillemets («») und Pfeile (→) müssen zeichengenau erhalten bleiben, deshalb wird die Umwandlung mechanisch erledigt. Dieser Befehl ist gegen die echte Datei erprobt und erzeugt genau die Struktur, die der Generator in Step 5 wieder zurückverwandelt:

```bash
{
	printf '# Changelog\n\n'
	printf 'Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.\n\n'
	printf 'Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),\n'
	printf 'die Versionierung an [Semantic Versioning](https://semver.org/lang/de/).\n\n'
	printf 'Diese Datei ist die gepflegte Quelle. Die Sektion `== Changelog ==` in\n'
	printf '`swiss-volley-connector/readme.txt` wird daraus erzeugt — dort nichts von Hand\n'
	printf 'ändern, sondern `bin/sync-readme-changelog.sh` laufen lassen.\n'

	sed -n '/^== Changelog ==/,$p' swiss-volley-connector/readme.txt | awk '
		/^= [0-9]+\.[0-9]+\.[0-9]+ =$/ { printf "\n## [%s]\n", $2; kat = ""; next }
		/^\* [A-Za-zÄÖÜäöü]+: / {
			i = index($0, ": ")
			neuekat = substr($0, 3, i - 3)
			text = substr($0, i + 2)
			if (neuekat != kat) { printf "\n### %s\n", neuekat; kat = neuekat }
			printf "- %s\n", text
			next
		}
		/^\* / {
			if (kat == "") { printf "\n" }
			printf "- %s\n", substr($0, 3)
			kat = "-ohne-kategorie-"
			next
		}
	'
} > CHANGELOG.md
```

Danach sichtprüfen: `head -20 CHANGELOG.md` und `sed -n '/## \[0.1.0\]/,$p' CHANGELOG.md`. Erwartet: acht Abschnitte `## [0.1.7]` bis `## [0.1.0]`, die oberen sieben je mit `### Neu` und einem Eintrag, der letzte mit neun Einträgen ohne Kategorie-Überschrift.

- [ ] **Step 5: Generator implementieren**

Datei `bin/sync-readme-changelog.sh`:

```bash
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
README="$ROOT/swiss-volley-connector/readme.txt"

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
```

- [ ] **Step 6: Ausführbar machen und Tests laufen lassen**

Run:
```bash
chmod +x bin/sync-readme-changelog.sh tests/test-version-tools.sh
bash tests/test-version-tools.sh
```
Expected: `3 bestanden, 0 fehlgeschlagen`, Exit 0.

Schlägt der Round-Trip fehl, zeigt der Diff die abweichende Zeile — dort wurde bei der Migration ein Zeichen verändert. Die Migration korrigieren, nicht den Generator anpassen.

- [ ] **Step 7: Beweisen, dass die echte readme.txt unverändert bleibt**

Run:
```bash
bash bin/sync-readme-changelog.sh
git diff --stat swiss-volley-connector/readme.txt
```
Expected: **keine Ausgabe** von `git diff` — der Generator reproduziert die Datei exakt.

Zusätzlich gegen die Referenz aus Step 1 prüfen:
```bash
diff -u /tmp/changelog-referenz.txt <(sed -n '/^== Changelog ==/,$p' swiss-volley-connector/readme.txt)
```
Expected: keine Ausgabe.

- [ ] **Step 8: Commit**

```bash
git add CHANGELOG.md bin/sync-readme-changelog.sh tests/test-version-tools.sh
git commit -m "feat(changelog): make CHANGELOG.md the maintained source

The readme.txt changelog section is now generated from CHANGELOG.md by
bin/sync-readme-changelog.sh, so the two can no longer drift apart. The
existing 0.1.0-0.1.7 entries were migrated verbatim; only the format
markers changed.

A round-trip test proves the migration was lossless: the generator has to
reproduce the readme.txt changelog section byte for byte, all eight
versions. Running the generator against the real file leaves it unchanged.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: bin/version.sh — die vier Versionsquellen vergleichen

**Files:**
- Create: `bin/version.sh`
- Modify: `tests/test-version-tools.sh` (Tests anhängen vor dem Ergebnisblock)

**Interfaces:**
- Consumes: `CHANGELOG.md` aus Task 1
- Produces:
  - `bash bin/version.sh` → Version auf stdout (z. B. `0.1.7`), Exit 0
  - `bash bin/version.sh --expect X.Y.Z` → zusätzlicher Abgleich gegen `X.Y.Z`
  - Exit 1 mit Tabelle aller Fundstellen auf stderr bei jeder Abweichung
  - Wird von Task 3 (`bump-version.sh`), Task 5 (`build.sh`) und Task 6 (Workflows) aufgerufen

- [ ] **Step 1: Fehlschlagende Tests schreiben**

In `tests/test-version-tools.sh` **vor** dem Abschnitt `# --- Ergebnis ---` einfügen:

```bash
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

test_version_gleichstand
test_version_drift_header
test_version_drift_konstante
test_version_drift_stable_tag
test_version_drift_changelog
test_version_expect_passt
test_version_expect_weicht_ab
```

- [ ] **Step 2: Tests ausführen, Fehlschlag bestätigen**

Run: `bash tests/test-version-tools.sh`
Expected: die sieben neuen Tests scheitern, weil `bin/version.sh` fehlt (`No such file or directory`). Die drei Tests aus Task 1 bleiben grün.

- [ ] **Step 3: bin/version.sh implementieren**

```bash
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
```

- [ ] **Step 4: Tests ausführen**

Run:
```bash
chmod +x bin/version.sh
bash tests/test-version-tools.sh
```
Expected: `10 bestanden, 0 fehlgeschlagen`, Exit 0.

- [ ] **Step 5: Gegen das echte Projekt prüfen**

Run: `bash bin/version.sh`
Expected: `0.1.7`

- [ ] **Step 6: Commit**

```bash
git add bin/version.sh tests/test-version-tools.sh
git commit -m "feat(version): cross-check the four version sources

bin/version.sh reads the plugin header, SVC_VERSION, the readme.txt stable
tag and the topmost CHANGELOG.md heading, and prints the version only when
all four agree. Any mismatch prints a table of every location and exits 1,
so a drifting source is named rather than guessed at.

--expect compares against a fifth value, which the release workflow uses to
hold the git tag against the files before it builds anything.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: bin/bump-version.sh — Version anheben

**Files:**
- Create: `bin/bump-version.sh`
- Modify: `tests/test-version-tools.sh`

**Interfaces:**
- Consumes: `bash bin/version.sh` (Task 2), `bash bin/sync-readme-changelog.sh` (Task 1)
- Produces: `bash bin/bump-version.sh X.Y.Z` schreibt Plugin-Header, `SVC_VERSION`, `Stable tag:`, fügt `## [X.Y.Z] - YYYY-MM-DD` mit der Platzhalterzeile `- TODO: Änderungen beschreiben` in `CHANGELOG.md` ein und ruft den Generator. Committet und taggt **nicht**.

- [ ] **Step 1: Fehlschlagende Tests schreiben**

In `tests/test-version-tools.sh` vor `# --- Ergebnis ---` einfügen:

```bash
# --- bump-version.sh -------------------------------------------------------

test_bump_schreibt_alle_stellen() {
	local dir
	dir="$(fixture)"
	bash "$dir/bin/bump-version.sh" 0.2.0 > /dev/null 2>&1
	assert_stdout "bump-version.sh hebt alle vier Quellen auf 0.2.0" "0.2.0" \
		bash "$dir/bin/version.sh"
}

test_bump_legt_changelog_abschnitt_an() {
	local dir
	dir="$(fixture)"
	bash "$dir/bin/bump-version.sh" 0.2.0 > /dev/null 2>&1
	if grep -qE '^## \[0\.2\.0\] - [0-9]{4}-[0-9]{2}-[0-9]{2}$' "$dir/CHANGELOG.md" \
		&& grep -qF '- TODO: Änderungen beschreiben' "$dir/CHANGELOG.md"; then
		pass "bump-version.sh legt einen datierten CHANGELOG-Abschnitt mit Platzhalter an"
	else
		fail "bump-version.sh legt einen datierten CHANGELOG-Abschnitt mit Platzhalter an" \
			"$(head -12 "$dir/CHANGELOG.md")"
	fi
}

test_bump_haelt_readme_synchron() {
	local dir
	dir="$(fixture)"
	bash "$dir/bin/bump-version.sh" 0.2.0 > /dev/null 2>&1
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
		bash "$dir/bin/bump-version.sh" 0.1.7
}

test_bump_lehnt_rueckwaerts_ab() {
	local dir
	dir="$(fixture)"
	assert_exit "bump-version.sh lehnt einen Rückwärtssprung ab" 1 \
		bash "$dir/bin/bump-version.sh" 0.1.6
}

test_bump_lehnt_drift_ab() {
	local dir
	dir="$(fixture)"
	verbiege "$dir/swiss-volley-connector/readme.txt" \
		'^Stable tag: 0\.1\.7' 'Stable tag: 0.9.9'
	assert_exit "bump-version.sh verweigert den Bump auf driftendem Stand" 1 \
		bash "$dir/bin/bump-version.sh" 0.2.0
}

test_bump_schreibt_alle_stellen
test_bump_legt_changelog_abschnitt_an
test_bump_haelt_readme_synchron
test_bump_lehnt_ungueltiges_format_ab
test_bump_lehnt_gleichstand_ab
test_bump_lehnt_rueckwaerts_ab
test_bump_lehnt_drift_ab
```

- [ ] **Step 2: Tests ausführen, Fehlschlag bestätigen**

Run: `bash tests/test-version-tools.sh`
Expected: die sieben neuen Tests scheitern (`bin/bump-version.sh: No such file or directory`), die zehn bisherigen bleiben grün.

- [ ] **Step 3: bin/bump-version.sh implementieren**

```bash
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
PLUGIN="$ROOT/swiss-volley-connector/swiss-volley-connector.php"
README="$ROOT/swiss-volley-connector/readme.txt"
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
ersetze "$PLUGIN" "s/^(define\\( 'SVC_VERSION', ')$ALT_RE(' \\);.*)\$/\\1$NEU\\2/"
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
printf '  %-46s Version:\n' "swiss-volley-connector/swiss-volley-connector.php"
printf '  %-46s SVC_VERSION\n' "swiss-volley-connector/swiss-volley-connector.php"
printf '  %-46s Stable tag:\n' "swiss-volley-connector/readme.txt"
printf '  %-46s neuer Abschnitt ## [%s] - %s\n' "CHANGELOG.md" "$NEU" "$HEUTE"
printf '  %-46s neu erzeugt\n' "swiss-volley-connector/readme.txt"
printf '\nNoch zu tun: %s in CHANGELOG.md ersetzen, dann sync erneut laufen lassen.\n' "$PLATZHALTER"
```

- [ ] **Step 4: Tests ausführen**

Run:
```bash
chmod +x bin/bump-version.sh
bash tests/test-version-tools.sh
```
Expected: `17 bestanden, 0 fehlgeschlagen`, Exit 0.

- [ ] **Step 5: Prüfen, dass das echte Projekt unberührt blieb**

Run: `git status --short`
Expected: nur `bin/bump-version.sh` und `tests/test-version-tools.sh` als geändert bzw. neu. Die Tests dürfen weder `CHANGELOG.md` noch `readme.txt` noch die PHP-Datei verändert haben.

- [ ] **Step 6: Commit**

```bash
git add bin/bump-version.sh tests/test-version-tools.sh
git commit -m "feat(version): add bin/bump-version.sh

Raises the version in all four sources at once and opens a dated
CHANGELOG.md section with a placeholder line, then regenerates the
readme.txt changelog so the tree stays consistent.

It refuses to run on a drifting tree, rejects malformed versions, and
rejects anything that is not strictly greater than the current version, so
a typo cannot silently move the plugin backwards. Committing and tagging
are deliberately left to /release, which asks first.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: bin/release-notes.sh — Release-Body erzeugen

**Files:**
- Create: `bin/release-notes.sh`
- Modify: `tests/test-version-tools.sh`

**Interfaces:**
- Consumes: `CHANGELOG.md` (Task 1)
- Produces: `bash bin/release-notes.sh X.Y.Z` gibt den Abschnittsinhalt als Markdown auf stdout aus; Exit 1, wenn der Abschnitt fehlt, leer ist oder noch `- TODO: Änderungen beschreiben` enthält. Wird von Task 6 im Release-Workflow aufgerufen.

- [ ] **Step 1: Fehlschlagende Tests schreiben**

In `tests/test-version-tools.sh` vor `# --- Ergebnis ---` einfügen:

```bash
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
	bash "$dir/bin/bump-version.sh" 0.2.0 > /dev/null 2>&1
	assert_exit "release-notes.sh scheitert bei unausgefülltem Platzhalter" 1 \
		bash "$dir/bin/release-notes.sh" 0.2.0
}

test_notes_ohne_argument() {
	local dir
	dir="$(fixture)"
	assert_exit "release-notes.sh scheitert ohne Versionsargument" 1 \
		bash "$dir/bin/release-notes.sh"
}

test_notes_liefert_abschnitt
test_notes_endet_vor_naechster_version
test_notes_fehlender_abschnitt
test_notes_platzhalter
test_notes_ohne_argument
```

- [ ] **Step 2: Tests ausführen, Fehlschlag bestätigen**

Run: `bash tests/test-version-tools.sh`
Expected: die fünf neuen Tests scheitern, die 17 bisherigen bleiben grün.

- [ ] **Step 3: bin/release-notes.sh implementieren**

```bash
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

if printf '%s' "$inhalt" | grep -qF "$PLATZHALTER"; then
	echo "Fehler: Der Abschnitt '## [$VERSION]' enthält noch den Platzhalter." >&2
	echo "Trage die Änderungen in CHANGELOG.md ein, bevor du releast." >&2
	exit 1
fi

printf '%s\n' "$inhalt"
```

- [ ] **Step 4: Tests ausführen**

Run:
```bash
chmod +x bin/release-notes.sh
bash tests/test-version-tools.sh
```
Expected: `22 bestanden, 0 fehlgeschlagen`, Exit 0.

- [ ] **Step 5: Ausgabe für 0.1.7 ansehen**

Run: `bash bin/release-notes.sh 0.1.7`
Expected: `### Neu` gefolgt von der Zeile zur interaktiven Gruppierung — genau der Text, der später im Release steht.

- [ ] **Step 6: Commit**

```bash
git add bin/release-notes.sh tests/test-version-tools.sh
git commit -m "feat(release): extract release notes from CHANGELOG.md

bin/release-notes.sh cuts the section for one version out of CHANGELOG.md
and prints it as markdown for the GitHub release body, so the release text
is the changelog users read rather than a list of commit subjects.

It exits 1 when the section is missing, empty, or still holds the bump
placeholder, which stops a release from going out with no notes at all.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: bin/build.sh an die Werkzeuge anschliessen

**Files:**
- Modify: `bin/build.sh:1-45`

**Interfaces:**
- Consumes: `bash bin/version.sh`, `bash bin/sync-readme-changelog.sh --check`
- Produces: `bash bin/build.sh` bricht bei Versionsdrift oder handeditierter `readme.txt` ab, bevor gebaut wird; das ZIP enthält keine Entwickler-Doku mehr

- [ ] **Step 1: Kopfkommentar und Schritt 0 einfügen**

In `bin/build.sh` den Kommentarblock am Dateianfang um die neue Reihenfolge ergänzen und nach den Pfad-Variablen (`DIST_DIR=…`) einfügen:

```bash
echo "== 0/5 Versionskonsistenz =="
VERSION="$("$ROOT/bin/version.sh")"
"$ROOT/bin/sync-readme-changelog.sh" --check
echo "OK  $VERSION"
```

- [ ] **Step 2: Schrittzähler anpassen**

Die vier bestehenden `echo`-Zeilen umschreiben:

| vorher | nachher |
| --- | --- |
| `echo "== 1/4 PHP-Syntaxcheck =="` | `echo "== 1/5 PHP-Syntaxcheck =="` |
| `echo "== 2/4 Tests =="` | `echo "== 2/5 Tests =="` |
| `echo "== 3/4 Übersetzungsvorlage (.pot) =="` | `echo "== 3/5 Übersetzungsvorlage (.pot) =="` |
| `echo "== 4/4 ZIP bauen =="` | `echo "== 4/5 ZIP bauen =="` |

Danach als letzten Schritt vor der Schlussmeldung ergänzen:

```bash
echo "== 5/5 ZIP prüfen =="
unzip -l "$ZIP" | tail -1
```

- [ ] **Step 3: Die eigene Versions-Extraktion entfernen**

Diesen Block in Schritt 4 ersatzlos löschen — `$VERSION` kommt jetzt aus Schritt 0:

```bash
# sed statt grep -oP: BSD-grep (macOS) kennt kein PCRE (-P).
VERSION="$(sed -n "s/.*define( *'SVC_VERSION', *'\([0-9.][0-9.]*\)'.*/\1/p" "$PLUGIN_DIR/swiss-volley-connector.php")"
if [ -z "$VERSION" ]; then
	echo "Fehler: SVC_VERSION konnte nicht aus $PLUGIN_DIR/swiss-volley-connector.php gelesen werden." >&2
	exit 1
fi
```

- [ ] **Step 4: Entwickler-Doku aus dem ZIP ausschliessen**

Die `zip`-Zeile ersetzen durch:

```bash
( cd "$ROOT" && zip -rq "$ZIP" swiss-volley-connector \
	-x '*.DS_Store' \
	-x 'swiss-volley-connector/README.md' \
	-x 'swiss-volley-connector/docs/*' )
```

- [ ] **Step 5: Build laufen lassen und ZIP-Inhalt prüfen**

Run:
```bash
bash bin/build.sh
unzip -l dist/swiss-volley-connector-0.1.7.zip | grep -cE 'README\.md|docs/'
```
Expected: Build endet mit Exit 0 und der Meldung `Fertig: …/swiss-volley-connector-0.1.7.zip`; der `grep -c` gibt `0` aus.

- [ ] **Step 6: Prüfen, dass der Guard greift**

Run:
```bash
sed -E 's/^Stable tag: 0\.1\.7/Stable tag: 0.9.9/' swiss-volley-connector/readme.txt > /tmp/r.txt
cp swiss-volley-connector/readme.txt /tmp/readme-original.txt
cp /tmp/r.txt swiss-volley-connector/readme.txt
bash bin/build.sh; echo "EXIT=$?"
cp /tmp/readme-original.txt swiss-volley-connector/readme.txt
git diff --stat swiss-volley-connector/readme.txt
```
Expected: `EXIT=1`, Tabelle mit `Stable tag … 0.9.9`, Abbruch **vor** dem Syntaxcheck. `git diff` danach ohne Ausgabe.

- [ ] **Step 7: Commit**

```bash
git add bin/build.sh
git commit -m "feat(build): gate the build on version consistency

The build now starts by asking bin/version.sh whether all four version
sources agree and whether readme.txt still matches CHANGELOG.md, so a
mismatch stops the run in a second instead of after the full test suite.
The version is taken from that check, which removes the second, separate
extraction that used to live in this script.

The ZIP no longer carries README.md and docs/ from inside the plugin
directory: those are developer documentation and have no business on a
production web server. readme.txt remains as the user-facing document.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Workflows

**Files:**
- Modify: `.github/workflows/release.yml:1-30`
- Modify: `.github/workflows/ci.yml` (neuer Job am Ende)

**Interfaces:**
- Consumes: `bash bin/version.sh --expect`, `bash bin/build.sh`, `bash bin/release-notes.sh`, `bash tests/test-version-tools.sh`
- Produces: Ein Tag `vX.Y.Z` erzeugt einen Release mit ZIP und Changelog-Text; CI prüft die Versions-Werkzeuge bei jedem Push

- [ ] **Step 1: release.yml neu schreiben**

```yaml
name: Release

# Prüft den Tag gegen die Versionsangaben in den Dateien, baut das
# installierbare ZIP und veröffentlicht beides als GitHub-Release,
# sobald ein Tag der Form v0.1.7 gepusht wird.
on:
  push:
    tags: [ 'v*' ]

permissions:
  contents: write

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: PHP einrichten
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      # Läuft bewusst vor dem Build: Ein falsch gesetzter Tag soll in
      # Sekunden scheitern, nicht nach der kompletten Testsuite.
      - name: Tag gegen die Versionsangaben prüfen
        run: |
          VERSION="$(bash bin/version.sh --expect "${GITHUB_REF_NAME#v}")"
          echo "VERSION=$VERSION" >> "$GITHUB_ENV"

      - name: Build (Lint, Tests, POT, ZIP)
        run: bash bin/build.sh

      - name: Release-Notes aus CHANGELOG.md
        run: bash bin/release-notes.sh "$VERSION" > release-notes.md

      - name: Release veröffentlichen
        uses: softprops/action-gh-release@v2
        with:
          files: dist/swiss-volley-connector-*.zip
          body_path: release-notes.md
          prerelease: ${{ startsWith(env.VERSION, '0.') }}
```

- [ ] **Step 2: ci.yml um einen eigenen Job ergänzen**

Am Ende von `.github/workflows/ci.yml` anfügen (gleiche Einrückungsebene wie `test:`):

```yaml
  # Eigener Job statt Schritt in der PHP-Matrix: Die Shell-Tests hängen
  # nicht von der PHP-Version ab und sollen nicht dreimal identisch laufen.
  version-tools:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Versionskonsistenz
        run: bash bin/version.sh

      - name: readme.txt gegen CHANGELOG.md prüfen
        run: bash bin/sync-readme-changelog.sh --check

      - name: Tests der Versions-Werkzeuge
        run: bash tests/test-version-tools.sh
```

- [ ] **Step 3: YAML-Syntax prüfen**

Run:
```bash
python3 -c "import yaml,sys; [yaml.safe_load(open(f)) for f in ['.github/workflows/ci.yml','.github/workflows/release.yml']]; print('YAML OK')"
```
Expected: `YAML OK`

- [ ] **Step 4: Die Workflow-Schritte lokal nachstellen**

Run:
```bash
bash bin/version.sh --expect 0.1.7
bash bin/build.sh > /dev/null && echo "build ok"
bash bin/release-notes.sh 0.1.7 > /tmp/release-notes.md && cat /tmp/release-notes.md
bash tests/test-version-tools.sh | tail -1
```
Expected: `0.1.7`; `build ok`; der Changelog-Text zu 0.1.7; `22 bestanden, 0 fehlgeschlagen`.

- [ ] **Step 5: Prüfen, dass ein falscher Tag scheitert**

Run: `bash bin/version.sh --expect 0.9.9; echo "EXIT=$?"`
Expected: Tabelle mit `Erwartet (Tag) … 0.9.9`, `EXIT=1`.

- [ ] **Step 6: Commit und Push**

```bash
git add .github/workflows/release.yml .github/workflows/ci.yml
git commit -m "ci: verify the tag before building a release

The release job now asks bin/version.sh whether the pushed tag matches the
version in the files, and does so before the build, so a mistyped tag fails
in seconds rather than after the full suite. The release body comes from
CHANGELOG.md instead of a generated commit list, and 0.x versions are
published as pre-releases to match the plugin's beta status.

CI gets a separate job for the shell tooling. It is separate from the PHP
matrix because the shell tests do not depend on the PHP version and should
not run three times over.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
git push origin main
```

- [ ] **Step 7: CI-Ergebnis abwarten**

Run: `gh run list --limit 2`
Expected: beide Jobs (`test`, `version-tools`) grün. Bei Rot: `gh run view --log-failed` und die Ursache beheben, bevor Task 7 beginnt.

---

### Task 7: Der Slash-Command /release

**Files:**
- Create: `.claude/commands/release.md`

**Interfaces:**
- Consumes: alle vier Skripte aus Task 1–4, `bin/build.sh` aus Task 5
- Produces: `/release` als projektlokaler Command

- [ ] **Step 1: Command schreiben**

Datei `.claude/commands/release.md`:

````markdown
---
description: Version anheben, Changelog pflegen und einen Release veröffentlichen
allowed-tools: Bash, Read, Edit, AskUserQuestion, TodoWrite
---

Führe den Release-Ablauf für dieses Plugin durch. Die gesamte Prüflogik liegt in
den Skripten unter `bin/` — rufe sie auf, statt ihre Arbeit nachzubauen.

## 1. Vorprüfung

Führe aus und werte aus:

```bash
git status --porcelain
git rev-parse --abbrev-ref HEAD
git fetch --tags --quiet && git tag --list
bash bin/version.sh
```

Brich mit einer Begründung ab (keine Rückfrage), wenn:

- das Arbeitsverzeichnis nicht sauber ist
- der Branch nicht `main` ist
- `bin/version.sh` einen Fehler meldet

Melde die aktuelle Version und den letzten vorhandenen Tag.

## 2. Frage: Was soll releast werden?

Stelle mit AskUserQuestion genau eine Frage mit diesen Optionen, jeweils mit der
konkret resultierenden Nummer im Label:

- `patch` — X.Y.(Z+1)
- `minor` — X.(Y+1).0
- `major` — (X+1).0.0
- `kein Bump` — die aktuelle Version releasen (nur sinnvoll, wenn für sie noch
  kein Tag existiert)

Bei einer expliziten Wunschversion nimmt der Nutzer «Other».

Ist für die Zielversion bereits ein Tag vorhanden, brich ab.

## 3. Changelog (entfällt bei «kein Bump»)

Lies die Commits seit dem letzten Tag:

```bash
git log --oneline "$(git describe --tags --abbrev=0 2>/dev/null || echo HEAD)"..HEAD
```

Schlage daraus Changelog-Einträge in ganzen Sätzen vor — beschreibe, was der
Nutzer merkt, nicht welche Datei sich geändert hat. Ordne jeden Eintrag einer
Kategorie zu: `Neu`, `Geändert`, `Behoben`, `Entfernt` oder `Sicherheit`.

Zeige den Vorschlag und lass ihn bestätigen oder korrigieren, bevor du schreibst.

## 4. Schreiben (entfällt bei «kein Bump»)

```bash
bash bin/bump-version.sh <ZIELVERSION>
```

Ersetze danach in `CHANGELOG.md` die Zeile `- TODO: Änderungen beschreiben`
durch die bestätigten Einträge, mit `### <Kategorie>`-Überschriften darüber.
Dann:

```bash
bash bin/sync-readme-changelog.sh
```

## 5. Prüfen

```bash
bash bin/build.sh
```

Bricht der Build ab, melde die Ursache und beende den Ablauf. Lass die
geänderten Dateien im Arbeitsverzeichnis liegen, damit der Nutzer korrigieren
kann — mache nichts rückgängig.

## 6. Rückfrage: committen und taggen

Zeige `git diff --stat` und den erzeugten Changelog-Abschnitt. Frage, ob
committet und lokal getaggt werden soll. Bei Zustimmung:

```bash
git add -A
git commit -m "chore(release): <ZIELVERSION>"
git tag "v<ZIELVERSION>"
```

Bei «kein Bump» entfällt der Commit; setze nur den Tag.

## 7. Rückfrage: pushen

Frage ausdrücklich und benenne die Folge: **Der Push des Tags löst den
öffentlichen Release aus.** Bei Zustimmung:

```bash
git push origin main
git push origin "v<ZIELVERSION>"
```

## 8. Abschluss

Beobachte den Workflow und melde das Ergebnis:

```bash
gh run list --limit 3
gh release view "v<ZIELVERSION>" --json url,assets,isPrerelease
```

Melde die Release-URL, den Namen des angehängten ZIPs und ob es als
Pre-Release markiert ist. Ist der Workflow rot, hole mit
`gh run view --log-failed` die Ursache und berichte sie — veröffentliche
nichts nach.
````

- [ ] **Step 2: Frontmatter prüfen**

Run:
```bash
head -5 .claude/commands/release.md
python3 -c "
import sys
text = open('.claude/commands/release.md', encoding='utf-8').read()
assert text.startswith('---\n'), 'Frontmatter fehlt'
ende = text.index('\n---\n', 4)
print('Frontmatter OK')
print(text[4:ende])
"
```
Expected: `Frontmatter OK` und die beiden Felder `description` und `allowed-tools`.

- [ ] **Step 3: Commit**

```bash
git add .claude/commands/release.md
git commit -m "feat: add the /release slash command

Drives the release from one place: it asks what to release, drafts changelog
entries from the commits since the last tag, fills CHANGELOG.md, runs the
build as a gate, and asks separately before committing and before pushing.

The command deliberately holds no checking logic of its own. Everything it
verifies, it verifies by calling the same bin/ scripts CI runs, so the two
paths cannot disagree.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: README aktualisieren und v0.1.7 veröffentlichen

Diese Aufgabe endet mit einer öffentlich sichtbaren Aktion. Der Nutzer hat ihr in der Planungsphase ausdrücklich zugestimmt.

**Files:**
- Modify: `README.md:33-46`

**Interfaces:**
- Consumes: alles aus Task 1–7
- Produces: ein veröffentlichter GitHub-Release `v0.1.7` mit ZIP-Download

- [ ] **Step 1: Den Abschnitt «Release-Workflow» in README.md ersetzen**

```markdown
## Release-Workflow

Der bequeme Weg ist der Claude-Command `/release`: Er fragt nach Bump-Art und
Changelog-Einträgen, schreibt alle Stellen, baut, committet, taggt und fragt vor
dem Push noch einmal nach.

Von Hand geht es genauso:

```bash
bin/bump-version.sh 0.2.0          # hebt alle vier Versionsstellen an
$EDITOR CHANGELOG.md               # Platzhalter durch die Änderungen ersetzen
bin/sync-readme-changelog.sh       # readme.txt neu erzeugen
bin/build.sh                       # Konsistenz, Lint, Tests, POT, ZIP
git commit -am "chore(release): 0.2.0"
git tag v0.2.0
git push && git push --tags
```

GitHub Actions prüft den Tag gegen die Dateien, baut das ZIP und veröffentlicht
es mit dem Changelog-Abschnitt als Release-Beschreibung. Versionen unter 1.0.0
werden als Pre-Release markiert.

### Wo die Version steht

An vier Stellen, die `bin/version.sh` gegeneinander prüft:

| Stelle | Datei |
| --- | --- |
| `Version:` im Plugin-Header | `swiss-volley-connector/swiss-volley-connector.php` |
| `SVC_VERSION` | dieselbe Datei |
| `Stable tag:` | `swiss-volley-connector/readme.txt` |
| oberste `## [X.Y.Z]` | `CHANGELOG.md` |

Weicht eine ab, brechen `bin/build.sh` und der Release-Workflow ab.

### Changelog

`CHANGELOG.md` im Repo-Root ist die gepflegte Quelle. Die Sektion
`== Changelog ==` in `swiss-volley-connector/readme.txt` wird daraus erzeugt und
enthält die vollständige Historie — dort nichts von Hand ändern, sondern
`bin/sync-readme-changelog.sh` laufen lassen. `bin/build.sh` prüft das mit
`--check`.
```

Ergänze ausserdem in der Dateiübersicht weiter unten die Zeile:

```markdown
* `CHANGELOG.md` – gepflegte Changelog-Historie (Quelle für `readme.txt`)
```

- [ ] **Step 2: Commit und Push**

```bash
git add README.md
git commit -m "docs: describe the release and version workflow

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
git push origin main
```

- [ ] **Step 3: Letzter Zustandscheck vor dem Tag**

Run:
```bash
git status --short
bash bin/version.sh
bash bin/sync-readme-changelog.sh --check
bash bin/release-notes.sh 0.1.7
gh run list --limit 2
```
Expected: sauberes Arbeitsverzeichnis, `0.1.7`, Synchronmeldung, der Changelog-Text zu 0.1.7, CI grün.

- [ ] **Step 4: Tag setzen und pushen**

```bash
git tag v0.1.7
git push origin v0.1.7
```

- [ ] **Step 5: Workflow beobachten**

Run:
```bash
gh run watch "$(gh run list --workflow=release.yml --limit 1 --json databaseId -q '.[0].databaseId')"
```
Expected: alle Schritte grün. Bei Rot: `gh run view --log-failed`, Ursache beheben, Tag löschen (`git tag -d v0.1.7 && git push --delete origin v0.1.7`) und erneut setzen.

- [ ] **Step 6: Den veröffentlichten Release prüfen**

Run:
```bash
gh release view v0.1.7 --json url,isPrerelease,body,assets \
  -q '"URL:        \(.url)\nPrerelease: \(.isPrerelease)\nAssets:     \(.assets[].name)\n---\n\(.body)"'
```
Expected: `isPrerelease: true`, genau ein Asset `swiss-volley-connector-0.1.7.zip`, als Body der Changelog-Abschnitt zu 0.1.7.

- [ ] **Step 7: Den Download echt herunterladen und öffnen**

Der eigentliche Beweis: Was am Release hängt, muss ein installierbares Plugin sein.

```bash
cd "$(mktemp -d)"
gh release download v0.1.7 --repo ThomasEnioKohler/swiss-volley-wordpress-connector
unzip -q swiss-volley-connector-0.1.7.zip
test -f swiss-volley-connector/swiss-volley-connector.php && echo "Hauptdatei vorhanden"
grep -c "0.1.7" swiss-volley-connector/swiss-volley-connector.php
test ! -d swiss-volley-connector/docs && echo "docs/ korrekt ausgeschlossen"
test ! -f swiss-volley-connector/README.md && echo "README.md korrekt ausgeschlossen"
```
Expected: `Hauptdatei vorhanden`, `2` (Header und Konstante), beide Ausschluss-Meldungen.

- [ ] **Step 8: Ergebnis melden**

Dem Nutzer berichten: Release-URL, Pre-Release-Status, Asset-Name und -Grösse, sowie das Ergebnis des Download-Tests aus Step 7.

---

## Selbstreview

**Spec-Abdeckung**

| Spec-Anforderung | Task |
| --- | --- |
| CHANGELOG.md kanonisch, Keep-a-Changelog | 1 |
| readme.txt-Sektion generiert, volle Historie | 1 |
| Migration wortgleich, Round-Trip beweisbar | 1, Steps 6–7 |
| Historische Einträge ohne Datum | 1, Step 4 |
| `version.sh` mit vier Quellen und `--expect` | 2 |
| `bump-version.sh` mit Format-, Gleichstand- und Rückwärtsprüfung | 3 |
| `sync-readme-changelog.sh` mit `--check` | 1 |
| `release-notes.sh` mit Platzhalter-Erkennung | 4 |
| build.sh Schritt 0 und ZIP-Ausschlüsse | 5 |
| Tag-Prüfung vor dem Build, `prerelease` bei 0.x | 6 |
| Eigener CI-Job für die Shell-Werkzeuge | 6 |
| `/release` mit Rückfragen vor Commit und Push | 7 |
| Alle in der Spec gelisteten Testfälle | 1–4 |
| Erste Anwendung: v0.1.7 als «kein Bump» | 8 |

**Namenskonsistenz geprüft:** `bin/version.sh`, `bin/bump-version.sh`, `bin/sync-readme-changelog.sh`, `bin/release-notes.sh`, `tests/test-version-tools.sh` heissen in allen Tasks, Tests, Workflows, im Command und im README gleich. Der Platzhalter lautet überall wörtlich `- TODO: Änderungen beschreiben`. Die Testhilfen `pass`, `fail`, `fixture`, `assert_exit`, `assert_stdout` und `verbiege` werden in Task 1 bzw. 2 definiert, bevor spätere Tasks sie benutzen.

**Erwartete Testzahlen:** Task 1 → 3, Task 2 → 10, Task 3 → 17, Task 4 → 22.

**Vorab erprobt.** Vier Codeteile dieses Plans wurden vor der Freigabe gegen die echten Projektdateien geprüft, weil ein Fehler in ihnen den ganzen Plan tragen würde:

| Geprüft | Ergebnis |
| --- | --- |
| `genau_eine` (Task 2) | ein-, zwei- und leerzeilige Eingabe korrekt unterschieden |
| `version_groesser` (Task 3) | 7 Paare korrekt, inklusive `0.1.10` > `0.1.9` und `1.0.0` > `0.9.9` |
| `ALT_RE`-Maskierung (Task 3) | ` * Version: 0x1x7` wird nicht mehr getroffen, `0.1.7` schon |
| Konverter (Task 1, Step 4) plus Generator (Step 5) | Round-Trip byteweise identisch zur heutigen `readme.txt`, gleiche MD5, 33 Zeilen |

Der letzte Punkt ist der wichtige: Die Migration ist damit nicht bloss geplant, sondern bereits an den echten Daten bewiesen.
