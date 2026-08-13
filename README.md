# Swiss Volley Connector – Entwicklungs-Repository

WordPress-Plugin für [www.volleypizol.org](https://www.volleypizol.org): zeigt Spielpläne, Resultate und Ranglisten automatisch aus der offiziellen Swiss-Volley-API. Dieses Repository enthält den Quellcode, die Testsuite und die Build-Pipeline.

## Struktur

```
swiss-volley-connector/   Plugin-Quellcode (wird 1:1 ins ZIP gepackt)
tests/harness.php         Testsuite mit WordPress-Mocks (82 Checks, kein WP nötig)
bin/build.sh              Build-Pipeline: Lint → Tests → .pot → ZIP
bin/make-pot.py           Generator für die Übersetzungsvorlage
dist/                     Build-Ausgabe (nicht versioniert)
.github/workflows/ci.yml       CI: Lint + Tests auf PHP 8.1/8.2/8.3 bei jedem Push
.github/workflows/release.yml  Release: baut das ZIP bei einem Tag v* und hängt es an den GitHub-Release
```

## Voraussetzungen

macOS/Linux mit `php` (≥ 8.1), `python3` und `zip`. Auf dem Mac: `brew install php`.

## Bauen und testen

```bash
bin/build.sh
```

Führt Syntaxcheck, Testsuite und POT-Generierung aus und legt das installierbare Plugin unter `dist/swiss-volley-connector-<version>.zip` ab. Nur die Tests:

```bash
php -d error_reporting=E_ALL tests/harness.php
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

## Dokumentation

* `swiss-volley-connector/readme.txt` – Installation, Konfiguration, FAQ, Changelog
* `CHANGELOG.md` – gepflegte Changelog-Historie (Quelle für `readme.txt`)
* `swiss-volley-connector/docs/API.md` – verwendete Swiss-Volley-Endpunkte, Mapping, Einschränkungen
* `swiss-volley-connector/docs/SHORTCODES.md` – alle Shortcodes und Attribute

## Lizenz

GPL-2.0-or-later.
