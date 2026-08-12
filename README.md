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

1. Version an drei Stellen anheben: Plugin-Header und `SVC_VERSION` in `swiss-volley-connector/swiss-volley-connector.php`, `Stable tag` in `swiss-volley-connector/readme.txt` (plus Changelog-Eintrag).
2. Committen, dann Tag pushen:

```bash
git commit -am "Version 0.2.0"
git tag v0.2.0
git push && git push --tags
```

GitHub Actions baut daraufhin das ZIP und veröffentlicht es automatisch als Release-Anhang.

## Dokumentation

* `swiss-volley-connector/readme.txt` – Installation, Konfiguration, FAQ, Changelog
* `swiss-volley-connector/docs/API.md` – verwendete Swiss-Volley-Endpunkte, Mapping, Einschränkungen
* `swiss-volley-connector/docs/SHORTCODES.md` – alle Shortcodes und Attribute

## Lizenz

GPL-2.0-or-later.
