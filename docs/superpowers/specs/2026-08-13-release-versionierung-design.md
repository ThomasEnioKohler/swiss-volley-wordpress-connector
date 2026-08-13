# Release- und Versionshandling

Datum: 2026-08-13
Status: freigegeben

## Problem

Die Version steht an vier Stellen, alle von Hand gepflegt, keine davon prüft die
anderen:

| Stelle | Datei |
| --- | --- |
| `Version:` im Plugin-Header | `swiss-volley-connector/swiss-volley-connector.php` |
| `define( 'SVC_VERSION', … )` | dieselbe Datei |
| `Stable tag:` | `swiss-volley-connector/readme.txt` |
| Changelog-Abschnitt `= X.Y.Z =` | dieselbe Datei |

Dazu kommt der Git-Tag als fünfte, ebenfalls ungeprüfte Quelle. Weicht eine ab,
merkt es niemand: Der Release-Workflow baut, hängt ein ZIP an und veröffentlicht
es mit einer Versionsnummer, die nicht zum Inhalt passen muss.

`.github/workflows/release.yml` existiert bereits, wurde aber nie ausgeführt —
es gibt bisher keinen einzigen Tag im Repo.

## Ziel

Ein Tag `vX.Y.Z` erzeugt einen GitHub-Release mit dem installierbaren ZIP als
Download und den echten, nutzerlesbaren Changelog-Einträgen als Beschreibung.
Stimmt irgendetwas an der Version nicht, bricht der Vorgang ab, bevor etwas
veröffentlicht wird.

## Rollenteilung

Die Prüflogik liegt in Shell-Skripten unter `bin/`, nicht im Claude-Command.
GitHub Actions läuft ohne Claude — läge die Logik nur im Command, gäbe es zwei
Wahrheiten, und die in CI wäre die schwächere. Der Command `/release` ist der
menschliche Fahrer: er fragt, sammelt Changelog-Text und ruft dieselben Skripte
auf, die auch CI ausführt.

## Änderung am Changelog

`CHANGELOG.md` im Repo-Root wird die einzige gepflegte Changelog-Quelle
(Keep-a-Changelog-Format, volle Historie). Die Sektion `== Changelog ==` in
`readme.txt` wird daraus generiert und enthält die letzten drei Versionen plus
einen Link auf die vollständige Historie.

Doppelte Pflege entsteht dadurch nicht: `readme.txt` wird nicht mehr von Hand
editiert, sondern erzeugt und per `--check` gegen die Quelle verglichen.

### Abbildung zwischen den Formaten

Die Abbildung ist verlustfrei in beide Richtungen:

| `CHANGELOG.md` | `readme.txt` |
| --- | --- |
| `## [0.1.7]` | `= 0.1.7 =` |
| `### Neu` gefolgt von `- Text` | `* Neu: Text` |
| `- Text` direkt unter `## […]`, vor jedem `###` | `* Text` |

Aus einer Kategorie-Überschrift wird also ein Zeilenpräfix und umgekehrt. Zeilen
ohne Kategorie bleiben ohne Präfix.

### Migration der bestehenden Einträge

0.1.0 bis 0.1.7 wandern wortgleich; nur die Formatmarker ändern sich.
Bestandsaufnahme der Quelle:

* 0.1.1 bis 0.1.7: je eine Zeile mit Präfix `* Neu: …` → `### Neu` plus ein
  Aufzählungspunkt
* 0.1.0: neun Zeilen ohne Präfix → neun Aufzählungspunkte direkt unter
  `## [0.1.0]`, ohne Kategorie-Überschrift

Historische Einträge bekommen **kein Datum**. Das Repo enthält genau einen
Import-Commit; echte Release-Daten sind nicht rekonstruierbar, und erfundene
Daten wären schlechter als gar keine. Ab der ersten mit diesem Werkzeug
erzeugten Version steht `## [X.Y.Z] - YYYY-MM-DD`.

Die Migration ist überprüfbar: Nach dem Umbau muss der Generator die drei
obersten Abschnitte der `readme.txt` byteweise so wiederherstellen, wie sie
heute dort stehen. Weicht ein Zeichen ab, war die Migration nicht wortgleich.

`== Changelog ==` ist die letzte Sektion der `readme.txt`; der Generator ersetzt
alles von dieser Zeile bis zum Dateiende.

## Komponenten

### `bin/version.sh [--expect X.Y.Z]`

Liest die vier Versionsquellen — Plugin-Header, `SVC_VERSION`, `Stable tag:` und
die oberste `## [X.Y.Z]` in `CHANGELOG.md` — und vergleicht sie.

* Alle gleich: Version auf stdout, Exit 0
* Abweichung: Tabelle aller Fundstellen mit Datei und Wert auf stderr, Exit 1
* Eine Quelle liefert keinen oder mehr als einen Treffer: Exit 1 mit Angabe der
  betroffenen Quelle
* `--expect X.Y.Z`: zusätzlich Abgleich gegen den erwarteten Wert (im Workflow
  der Git-Tag ohne `v`)

Die Changelog-Sektion in `readme.txt` ist **keine** Quelle mehr, weil generiert.

Alle Extraktionen nutzen `sed -E` (funktioniert mit BSD- und GNU-sed).
`grep -P` wird nicht verwendet — macOS liefert BSD-grep ohne PCRE.

### `bin/sync-readme-changelog.sh [--check]`

Erzeugt die Sektion `== Changelog ==` in `readme.txt` aus `CHANGELOG.md`: die
obersten drei Versionen nach der Abbildungstabelle oben, danach eine Leerzeile
und die Zeile

```
Vollständige Historie: https://github.com/ThomasEnioKohler/swiss-volley-wordpress-connector/blob/main/CHANGELOG.md
```

Mit `--check` wird nichts geschrieben: Das Skript erzeugt die Sektion im
Speicher und vergleicht sie mit der Datei. Unterschied → Diff auf stderr,
Exit 1. So fallen Handedits an der generierten Sektion auf, statt still zu
überleben.

### `bin/bump-version.sh X.Y.Z`

1. Prüft das Format `MAJOR.MINOR.PATCH`
2. Prüft mit `bin/version.sh`, dass der Ist-Zustand konsistent ist — auf einem
   driftenden Stand wird nicht gebumpt
3. Lehnt Versionen ab, die nicht grösser als die aktuelle sind (Vergleich per
   `sort -V`)
4. Schreibt Plugin-Header, `SVC_VERSION` und `Stable tag:`
5. Fügt in `CHANGELOG.md` einen Abschnitt `## [X.Y.Z] - <heute>` mit einer
   Platzhalterzeile ein
6. Ruft `bin/sync-readme-changelog.sh`
7. Gibt ein Protokoll aus, welche Stelle von welchem auf welchen Wert wechselte

Das Skript committet und taggt nicht. Der Changelog-Text wird vom Command
eingesetzt, der die Platzhalterzeile ersetzt.

### `bin/release-notes.sh X.Y.Z`

Gibt den Inhalt des Abschnitts `## [X.Y.Z]` aus `CHANGELOG.md` auf stdout aus
(ohne die Überschrift selbst, bis zur nächsten `## [`-Zeile oder zum Dateiende),
im Markdown-Format für den Release-Body.

Fehlt der Abschnitt, ist er leer oder enthält er noch die Platzhalterzeile:
Exit 1. Ein Release ohne Notes soll nicht entstehen.

### `bin/build.sh` (geändert)

* Neuer Schritt 0 „Versionskonsistenz": `VERSION="$(bin/version.sh)"` plus
  `bin/sync-readme-changelog.sh --check`
* Die eigene Versions-Extraktion entfällt — sie existiert danach nur noch in
  `version.sh`
* Schrittzähler von `/4` auf `/5`
* Der ZIP-Schritt schliesst zusätzlich aus: `swiss-volley-connector/README.md`
  und `swiss-volley-connector/docs/*`

### `.github/workflows/release.yml` (geändert)

Reihenfolge:

1. Checkout, PHP 8.3
2. `bin/version.sh --expect "${GITHUB_REF_NAME#v}"` — Tag gegen Dateien
3. `bash bin/build.sh`
4. `bin/release-notes.sh "$VERSION" > release-notes.md`
5. `softprops/action-gh-release@v2` mit `body_path: release-notes.md`,
   `files: dist/swiss-volley-connector-*.zip`, `prerelease` wenn die Version mit
   `0.` beginnt

Der Tag-Abgleich läuft bewusst **vor** dem Build: Ein falsch gesetzter Tag
scheitert in Sekunden statt nach der kompletten Testsuite.
`generate_release_notes` entfällt — der Body kommt aus dem Changelog.

### `.github/workflows/ci.yml` (geändert)

Eigener Job neben der PHP-Matrix, der `tests/test-version-tools.sh` und
`bin/version.sh` ausführt. Eigener Job statt Matrix-Schritt, damit die
Shell-Tests nicht dreimal identisch laufen.

### `.claude/commands/release.md`

Projektlokaler Slash-Command `/release`.

Ablauf:

1. **Vorprüfung** — Arbeitsverzeichnis sauber? Branch `main`? Zieltag noch
   frei? `bin/version.sh` grün? Verletzung → Abbruch mit Begründung, keine
   Rückfrage
2. **Frage Bump-Art** — `patch` / `minor` / `major` / explizite Version / *kein
   Bump* (aktuellen Stand releasen). Die Auswahl zeigt jeweils die resultierende
   Nummer
3. **Frage Changelog** — bei Bump: Commits seit dem letzten Tag auslesen,
   daraus Einträge vorschlagen, Kategorie je Eintrag (Neu / Geändert / Behoben /
   Entfernt / Sicherheit); der Nutzer korrigiert und bestätigt. Bei *kein Bump*
   entfällt der Schritt, der Abschnitt existiert bereits
4. **Schreiben** — `bin/bump-version.sh`, Platzhalter durch die bestätigten
   Einträge ersetzen, `bin/sync-readme-changelog.sh`
5. **Prüfen** — `bash bin/build.sh`; rot → Abbruch, Änderungen bleiben zur
   Korrektur im Arbeitsverzeichnis liegen
6. **Rückfrage committen** — Diff zeigen, dann Commit und lokalen Tag setzen
7. **Rückfrage pushen** — ausdrücklich als der Schritt benannt, der den
   öffentlichen Release auslöst
8. **Abschluss** — Workflow-Lauf beobachten, Ergebnis und Release-URL melden

Bei *kein Bump* überspringt der Command die Schritte 3 und 4 und beginnt bei der
Prüfung.

## Tests

`tests/test-version-tools.sh`, reines Bash ohne neue Abhängigkeit, in CI
ausgeführt. Arbeitet ausschliesslich auf Kopien in einem `mktemp -d`; die echten
Projektdateien werden nie verändert.

Abgedeckte Fälle:

* `version.sh` bei Gleichstand: Exit 0, korrekte Ausgabe
* `version.sh` mit je einer der vier Quellen verbogen: Exit 1, die betroffene
  Quelle wird benannt (vier Einzelfälle)
* `version.sh --expect` mit passendem und mit abweichendem Wert
* `sync --check` auf synchronem Stand: Exit 0
* `sync --check` nach Handedit an `readme.txt`: Exit 1
* `sync` erzeugt aus der migrierten `CHANGELOG.md` die drei obersten Abschnitte
  byteweise so, wie sie vor der Migration in `readme.txt` standen
* `release-notes.sh` mit vorhandenem Abschnitt: korrekter Ausschnitt
* `release-notes.sh` mit fehlendem Abschnitt und mit Platzhalter: Exit 1
* `bump-version.sh` schreibt alle Stellen und legt den CHANGELOG-Abschnitt an
* `bump-version.sh` lehnt ungültiges Format, Gleichstand und Rückwärtssprung ab

Die bestehende PHP-Testsuite bleibt unverändert.

## Bewusst nicht enthalten

* Kein zusätzlicher technischer Changelog neben `CHANGELOG.md`
* Kein automatisches Committen oder Taggen durch die Shell-Skripte — das macht
  nur der Command, und nur nach Rückfrage
* Keine Bots, die Versionen selbsttätig anheben
* Kein Publishing ins WordPress-Plugin-Verzeichnis (das Plugin ist Private Beta)

## Erste Anwendung

Nach der Umsetzung wird `v0.1.7` getaggt und gepusht, damit ein echter Release
mit ZIP entsteht und die Pipeline bewiesen ist. Das ist der *kein Bump*-Pfad:
Version 0.1.7 steht bereits in allen Quellen, der Changelog-Abschnitt existiert.

Der Nutzer hat dieser Veröffentlichung ausdrücklich zugestimmt.

## Offene Punkte

Keine.
