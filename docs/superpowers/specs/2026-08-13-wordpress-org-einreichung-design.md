# Einreichung im WordPress.org-Plugin-Verzeichnis – Design

Datum: 2026-08-13

## Ziel

Das Plugin wird im offiziellen WordPress.org-Plugin-Verzeichnis veröffentlicht und
anschliessend von dort aus aktualisiert. Die bisherige Verteilung über ZIP-Dateien aus
GitHub-Releases entfällt als primärer Weg.

## Ausgangslage

* Version 1.0.0, funktional vollständig, keine produktiven Installationen (bisher nur Tests).
* Code ohne die üblichen Ablehnungsgründe: keine `eval`, `base64_*`, `curl_*` oder
  `file_get_contents`; API-Zugriffe über die WordPress-HTTP-API; Nonce- und
  Capability-Prüfungen vorhanden; Prefix `SVC_`/`svc_` durchgängig.
* Release-Pipeline vorhanden: Tag-Push → Versionskonsistenzprüfung → Lint → Tests → POT → ZIP
  → GitHub-Release.

## Entscheidungen

### E1 – Umbenennung wegen Trademark (Guideline 17)

Guideline 17 verbietet Marken oder fremde Projektnamen als ersten Begriff des Plugin-Slugs
ohne Nachweis von Eigentum oder offizieller Vertretung. Der bisherige Slug
`swiss-volley-connector` beginnt mit der Marke «Swiss Volley»; eine Genehmigung von
Swiss Volley liegt nicht vor. Die Guideline nennt als zulässiges Muster ausdrücklich die
Form «Eigener Name **for** Marke».

| | alt | neu |
|---|---|---|
| Plugin Name | Swiss Volley Connector | Volleyball Schedules for Swiss Volley |
| Slug / Ordner | `swiss-volley-connector` | `volleyball-schedules-for-swiss-volley` |
| Hauptdatei | `swiss-volley-connector.php` | `volleyball-schedules-for-swiss-volley.php` |
| Text Domain | `swiss-volley-connector` | `volleyball-schedules-for-swiss-volley` |
| Klassen / Funktionen | `SVC_`, `svc_` | `VSSV_`, `vssv_` |
| Options / Transients | `svc_settings`, `svc_teams`, `svc_log`, `svc_stale_*`, `svc_cache_*` | `vssv_*` |
| CSS-Klassen | `svc-*` | `vssv-*` |
| Filter-Hooks | `svc_group_heading_tag`, `svc_game_league_heading_tag` | `vssv_*` |

**Nicht umbenannt:** Shortcode-Namen (`swissvolley_games`, `swissvolley_results`,
`swissvolley_ranking`, `swissvolley_team`, `swissvolley_club_games`,
`swissvolley_club_results`). Die Trademark-Regel betrifft ausschliesslich den Slug; die
Namen sind distinkt genug, um Kollisionen zu vermeiden, und ein Rename brächte keinen
Gewinn. Die Nennung von «Swiss Volley» im Fliesstext, in Beschreibungen und in
Shortcode-Namen ist als beschreibende Nutzung zulässig.

Da keine Installationen existieren, entfällt jede Migrationslogik. Das umbenannte Plugin
startet mit leeren Optionen; API-Key und Teams werden neu eingetragen.

### E2 – Englisch als Quellsprache

WordPress.org behandelt Quellstrings immer als `en_US`; translate.wordpress.org übersetzt
aus dem Englischen. Deutsche Quellstrings würden bedeuten: deutsche Oberfläche für alle
Nutzer weltweit und ein praktisch unbrauchbares Übersetzungssystem.

* Rund 134 `__()`/`esc_html__()`/`esc_attr__()`-Strings in PHP sowie die `wp.i18n`-Strings
  in `assets/js/blocks.js` werden auf Englisch umgestellt.
* `de_DE` und `de_CH` werden als `.po`/`.mo` mitgeliefert und decken beide sämtliche
  Strings ab. Der `de_CH`-Katalog übernimmt die heutigen deutschen Texte unverändert
  (Schweizer Schreibweise, `ss` statt `ß`), damit sich für den bestehenden Anwendungsfall
  nichts ändert. Der `de_DE`-Katalog ist derselbe Text mit `ß` an den betroffenen Stellen.
* `readme.txt` wird vollständig englisch, einschliesslich Changelog.
* `bin/make-pot.py` wird auf die neue Text-Domain angepasst, die POT neu generiert.

### E3 – Version bleibt 1.0.0

Es existieren keine Installationen und keine öffentliche Verbreitung. Das
Verzeichnis-Listing startet deshalb bei 1.0.0. Der CHANGELOG wird auf einen einzigen
1.0.0-Eintrag «Initial release» zusammengefasst: Die Einträge 0.1.0–0.1.7 beschreiben
Entwicklungsschritte einer nie veröffentlichten Testphase und wären für Nutzer des
Verzeichnisses irreführend.

Konsequenz für die Toolchain: `bin/version.sh` prüft die Übereinstimmung von
Plugin-Header, Versionskonstante, `Stable tag` und erstem CHANGELOG-Abschnitt. Diese
Prüfung bleibt unverändert gültig.

### E4 – Deploy über GitHub Action

`.github/workflows/release.yml` wird um einen Schritt mit
`10up/action-wordpress-plugin-deploy@stable` erweitert. Der Schritt läuft nach dem
GitHub-Release und spiegelt das Build-Verzeichnis nach SVN `trunk/` und `tags/<version>/`
sowie `.wordpress-org/` nach SVN `assets/`.

* Secrets: `SVN_USERNAME`, `SVN_PASSWORD`.
* Der Schritt wird erst nach der Freigabe durch das Review-Team aktiviert — vorher
  existiert kein SVN-Repository.
* `bin/build.sh` muss zusätzlich zum ZIP ein sauberes Build-Verzeichnis erzeugen, das die
  Action als `BUILD_DIR` verwendet. Enthalten ist ausschliesslich der Plugin-Ordner ohne
  `bin/`, `tests/`, `dist/` und Entwickler-Dateien.

## Arbeitspakete

### A1 – Rename

Mechanische, aber breite Umbenennung gemäss E1 über den Plugin-Ordner und die gesamte
Toolchain. Betroffen sind neben dem Plugin-Code:

* `bin/build.sh` (Pfade, ZIP-Name, Prüfung auf `SVC_VERSION`)
* `bin/version.sh` (Pfade, Regex auf `SVC_VERSION`)
* `bin/sync-readme-changelog.sh`, `bin/release-notes.sh`, `bin/bump-version.sh`
* `bin/make-pot.py` (Text-Domain, Header)
* `tests/harness.php`, `tests/test-build.sh`, `tests/test-version-tools.sh`
* `.github/workflows/release.yml`, `.github/workflows/ci.yml`
* `README.md`, `CHANGELOG.md`, `docs/`

Verifikation: `bin/build.sh` läuft grün durch, und eine Suche nach `svc`/`SVC`
(case-insensitive) über das Repository liefert nur noch bewusst belassene Treffer.

### A2 – i18n auf Englisch

Umsetzung von E2. Verifikation: Die generierte POT enthält ausschliesslich englische
`msgid`-Einträge; die `de_CH`-Übersetzung deckt alle Strings ab; die Oberfläche zeigt bei
`de_CH` die heutigen deutschen Texte.

### A3 – readme.txt für das Verzeichnis

Neufassung auf Englisch mit den vom Verzeichnis erwarteten Feldern:

* `Contributors:` gültiger WordPress.org-Username.
* `Tags:` maximal fünf, englisch: `volleyball, sports, schedule, results, standings`.
* `Requires at least: 6.2`, `Tested up to: 7.0` (aktuelle stabile WordPress-Version ist
  7.0.4; der bisherige Wert 6.8 würde im Verzeichnis als veraltet markiert),
  `Requires PHP: 8.1`, `Stable tag: 1.0.0`.
* Kurzbeschreibung höchstens 150 Zeichen.
* Der Hinweis «nicht im WordPress-Plugin-Verzeichnis gelistet» entfällt.
* Neuer Pflichtabschnitt `== External services ==`: Nennung von `api.volleyball.ch`,
  welche Daten zu welchem Zeitpunkt übertragen werden, ausdrücklicher Hinweis, dass keine
  Besucherdaten übermittelt werden, sowie Links auf Nutzungsbedingungen und
  Datenschutzerklärung von Swiss Volley. Ein fehlender Abschnitt ist einer der häufigsten
  Ablehnungsgründe bei Plugins mit externen Diensten.
* Disclaimer «not affiliated with or endorsed by Swiss Volley» in die Description.
* Abschnitt `== Screenshots ==` mit einer Zeile pro Screenshot.
* Verweise auf `docs/SHORTCODES.md` und `docs/API.md` werden durch absolute GitHub-URLs
  ersetzt: `bin/build.sh` schliesst `docs/` aus dem ZIP aus, die relativen Verweise wären
  für Nutzer des Verzeichnisses tot.

### A4 – Compliance-Prüfung

* Das offizielle Plugin-Check-Plugin (PCP) lokal gegen das Build ausführen; es bildet die
  automatisierte Prüfung bei der Einreichung ab. Alle Errors müssen behoben sein, Warnings
  bewertet.
* PHPCS mit `WordPress-Coding-Standards` in `ci.yml` aufnehmen.
* Die Array-Inputs in `class-svc-admin.php` — nach A1 `class-vssv-admin.php` — (`alias`, `in_club`, `league_label`,
  `name_label`, `page_url`) daraufhin prüfen, dass jeder Wert einzeln sanitisiert wird;
  die vorhandenen `phpcs:ignore`-Kommentare müssen inhaltlich zutreffen.

### A5 – Verzeichnis-Assets

Neues Verzeichnis `.wordpress-org/` im Repository:

* `icon-128x128.png`, `icon-256x256.png`
* `banner-772x250.png`, `banner-1544x500.png`
* `screenshot-1.png` … `screenshot-n.png`

Alle Grafiken selbst gestaltet, ohne Logos oder Bildmarken von Swiss Volley.

### A6 – Deploy-Schritt

Umsetzung von E4, zunächst deaktiviert bzw. per Bedingung übersprungen, bis der
SVN-Zugang vorliegt.

## Ablauf der Einreichung

1. WordPress.org-Account anlegen bzw. bestätigen; der Name in `Contributors:` muss diesem
   Account entsprechen.
2. Build-ZIP (< 10 MB) auf `https://wordpress.org/plugins/developers/add/` hochladen.
3. Der automatisierte Plugin Check läuft sofort; Fehler blockieren die Einreichung.
4. Manuelles Review durch das Plugin-Team. Rückfragen kommen per E-Mail von
   `plugins@wordpress.org` und müssen beantwortet werden. Die Wartezeit beträgt
   erfahrungsgemäss mehrere Wochen.
5. Nach der Freigabe wird ein SVN-Repository bereitgestellt. Das Plugin wird erst durch
   den ersten SVN-Commit öffentlich sichtbar.
6. `trunk/`, `tags/1.0.0/` und `assets/` befüllen — ab hier über den Deploy-Schritt aus E4.
7. Jedes weitere Update erfolgt über einen Tag-Push.

## Risiken

* **Ablehnung trotz Umbenennung.** Das Review-Team entscheidet im Einzelfall über
  Markennutzung. Fällt die Entscheidung negativ aus, muss «Swiss Volley» auch aus dem
  Namen verschwinden (etwa «Volleyball Schedules for Swiss Clubs»); die Beschreibung darf
  den Dienst weiterhin nennen.
* **Abhängigkeit von einer fremden API.** Ändert Swiss Volley die API oder deren
  Nutzungsbedingungen, betrifft das ein öffentlich gelistetes Plugin mit Nutzern ausserhalb
  des eigenen Vereins.
* **Breite des Renames.** Die Umbenennung berührt Plugin-Code, Toolchain, Tests und CI
  gleichzeitig. Absicherung ist die bestehende Testsuite plus ein vollständiger
  `bin/build.sh`-Durchlauf.
