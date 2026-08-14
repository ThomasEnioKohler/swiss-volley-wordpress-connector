# Volleyball Schedules for Swiss Volley – Entwicklungs-Repository

WordPress-Plugin für [www.volleypizol.org](https://www.volleypizol.org): zeigt Spielpläne, Resultate und Ranglisten automatisch aus der offiziellen Swiss-Volley-API. Dieses Repository enthält den Quellcode, die Testsuite und die Build-Pipeline.

## Struktur

```
volleyball-schedules-for-swiss-volley/   Plugin-Quellcode (wird ins ZIP gepackt, ohne README.md, docs/ und *.po)
CHANGELOG.md              Gepflegte Changelog-Historie (Quelle für readme.txt)
dist/                     Build-Ausgabe (nicht versioniert)
phpcs.xml.dist            WordPress Coding Standards (inkl. Präfix-Prüfung VSSV_/vssv_)
.wordpress-org/           Verzeichnis-Assets (Icon, Banner) fürs SVN-Deploy nach wordpress.org
assets-src/               Bearbeitbare Quellen (SVG) der .wordpress-org-Assets

tests/harness.php             Testsuite mit WordPress-Mocks (kein WP nötig)
tests/test-i18n.sh            Prüft Quellsprache (ASCII), Text Domain und Katalog-Vollständigkeit
tests/test-version-tools.sh   Tests der Versions-Werkzeuge (arbeiten auf Kopien)
tests/test-build.sh           Tests der Build-Pipeline

bin/build.sh                  Build: Versionsprüfung → Lint → Tests → .pot → i18n-Gate → .mo → JSON → Build-Verzeichnis → ZIP → ZIP-Prüfung
bin/version.sh                Vergleicht die vier Versionsquellen; --expect prüft gegen einen Tag
bin/bump-version.sh           Hebt die Version an und legt den Changelog-Abschnitt an
bin/sync-readme-changelog.sh  Erzeugt die Changelog-Sektion in readme.txt; --check prüft nur
bin/release-notes.sh          Schneidet den Release-Body aus CHANGELOG.md
bin/make-pot.py               Generator für die Übersetzungsvorlage
bin/make-blocks-json.py       Generator für die JSON-Sprachkataloge des Block-Editors (Handle vssv-blocks)

.claude/commands/release.md    Slash-Command /release: führt durch den Release-Ablauf
.github/workflows/ci.yml       CI: Lint + Tests auf PHP 8.1/8.2/8.3, Versions-Werkzeuge, Build, Coding Standards
.github/workflows/release.yml  Release: prüft den Tag, baut das ZIP, veröffentlicht es, deployt nach SVN
```

## Voraussetzungen

macOS/Linux mit `php` (≥ 8.1), `python3`, `zip` und `gettext` (`msgfmt`, `msgcmp`). Auf dem Mac: `brew install php gettext` (`gettext` danach ggf. mit `brew link --force gettext` in den `PATH` hängen).

## Bauen und testen

```bash
bin/build.sh
```

Führt Versionsprüfung, Syntaxcheck, Testsuite, POT-Generierung, die i18n-Prüfung (ASCII-Quellstrings, Text Domain, Katalog-Vollständigkeit gegen `msgcmp`), `.mo`- und JSON-Sprachkataloge sowie den Bau von Build-Verzeichnis und ZIP aus — und legt das installierbare Plugin unter `dist/volleyball-schedules-for-swiss-volley-<version>.zip` ab. Nur die Tests:

```bash
php -d error_reporting=E_ALL tests/harness.php
```

Nur die i18n-Prüfung:

```bash
bash tests/test-i18n.sh
```

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
| `Version:` im Plugin-Header | `volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php` |
| `VSSV_VERSION` | dieselbe Datei |
| `Stable tag:` | `volleyball-schedules-for-swiss-volley/readme.txt` |
| oberste `## [X.Y.Z]` | `CHANGELOG.md` |

Weicht eine ab, brechen `bin/build.sh` und der Release-Workflow ab.

### Changelog

`CHANGELOG.md` im Repo-Root ist die gepflegte Quelle. Die Sektion
`== Changelog ==` in `volleyball-schedules-for-swiss-volley/readme.txt` wird daraus erzeugt und
enthält die vollständige Historie — dort nichts von Hand ändern, sondern
`bin/sync-readme-changelog.sh` laufen lassen. `bin/build.sh` prüft das mit
`--check`.

## Dokumentation

* `volleyball-schedules-for-swiss-volley/readme.txt` – Installation, Konfiguration, FAQ, Changelog
* `CHANGELOG.md` – gepflegte Changelog-Historie (Quelle für `readme.txt`)
* `volleyball-schedules-for-swiss-volley/docs/API.md` – verwendete Swiss-Volley-Endpunkte, Mapping, Einschränkungen
* `volleyball-schedules-for-swiss-volley/docs/SHORTCODES.md` – alle Shortcodes und Attribute

## Umstieg auf volleypizol.org

Die Umbenennung von `swiss-volley-connector` auf `volleyball-schedules-for-swiss-volley`
(Präfixe `svc_`/`svc-` → `VSSV_`/`vssv_`/`vssv-`) ist ein Schnitt, keine Migration:
Options-Keys, CSS-Klassen und Filter-Namen haben sich geändert, und es gibt
bewusst keine automatische Übernahme der alten Daten — auf volleypizol.org läuft
noch die alte Version, und da es keine weiteren Installationen gibt, wurde eine
Migration bewusst nicht gebaut. Beim Umstieg dieser Site von Hand vorgehen:

1. Das neue Plugin installieren und aktivieren (parallel zum alten möglich, es
   verwendet eigene Options-Keys `vssv_settings`/`vssv_teams`).
2. Unter **Swiss Volley → Settings** den API-Key neu eintragen (der alte Key
   wird nicht übernommen).
3. Auf dem **Teams**-Tab **Load club and teams now** ausführen, um Verein und
   Teams neu zu laden.
4. Pro Team wieder eintragen: Alias, eigener Teamname, eigene Liga-Bezeichnung
   und Team-Link (frühere Angaben unter `svc_teams` werden nicht übernommen).
5. Eigenes CSS (Custom-CSS-Feld, Theme oder Child-Theme) von `.svc-*` auf
   `.vssv-*` umstellen (z. B. `.svc-own-team` → `.vssv-own-team`).
6. Erst danach das alte Plugin (`swiss-volley-connector`) löschen. **Nicht
   vorher**: Das Löschen führt dessen `uninstall.php` aus und entfernt die
   alten `svc_*`-Optionen und -Transients — das soll erst passieren, wenn
   Schritt 1–5 bestätigt abgeschlossen sind.

## Lizenz

GPL-2.0-or-later.
