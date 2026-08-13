=== Volleyball Schedules for Swiss Volley ===
Contributors: volleypizol
Tags: volleyball, swiss volley, spielplan, resultate, rangliste
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zeigt Spielpläne, Resultate und Ranglisten automatisch aus der offiziellen Swiss-Volley-API an. Entwickelt für www.volleypizol.org.

== Description ==

Volleyball Schedules for Swiss Volley verbindet WordPress mit der offiziellen Swiss-Volley-API (Volley Manager) und stellt automatisch dar:

* Kommende Spiele (Datum, Uhrzeit, Heimteam, Auswärtsteam, Spielhalle)
* Resultate inkl. Satzresultaten
* Offizielle Ranglisten (keine eigene Berechnung)
* Vereinsweite Übersichten für die Startseite

Ändert sich ein Spieltermin oder wird ein Resultat bei Swiss Volley eingetragen, erscheint die Änderung automatisch – ohne manuelle Pflege. Die API wird gecacht (WordPress Transients) und ausschliesslich serverseitig aufgerufen: Der API-Key erscheint nie im Frontend, in JavaScript oder in Logs; Besucher senden keine Requests an Swiss Volley (Datenschutz).

**Hinweis zur Verteilung:** Das Plugin ist nicht im WordPress-Plugin-Verzeichnis gelistet. Installation und Aktualisierung erfolgen über das ZIP aus dem jeweiligen Release.

== Installation ==

1. ZIP-Datei des gewünschten Release herunterladen: `volleyball-schedules-for-swiss-volley-<version>.zip`
2. In WordPress: **Plugins → Plugin hinzufügen → Plugin hochladen**
3. ZIP auswählen, installieren und aktivieren.

== Konfiguration ==

1. Im Volley Manager (https://volleymanager.volleyball.ch) unter **Administration → Club → Webservice/API** einen API-Key erzeugen.
2. In WordPress unter **Swiss Volley → Einstellungen** den API-Key eintragen und speichern.
3. **API-Verbindung testen** klicken – der Verein (z. B. Volley Pizol) wird automatisch erkannt.
4. Im Tab **Teams** auf **Verein und Teams jetzt laden** klicken. Alle Mannschaften werden automatisch aus Swiss Volley geladen (nichts ist hart codiert).
5. Optional pro Team einen Alias vergeben (z. B. `herren-1`) und wählen, welche Teams in vereinsweiten Ansichten erscheinen.
6. Shortcodes oder Gutenberg-Blöcke auf den Teamseiten einfügen.

== Shortcodes ==

* `[swissvolley_games team="TEAM_ID" limit="5" scope="upcoming"]` – kommende Spiele (scope: upcoming, played, all)
* `[swissvolley_results team="TEAM_ID" limit="5"]` – Resultate
* `[swissvolley_ranking team="TEAM_ID"]` – Rangliste
* `[swissvolley_team team="TEAM_ID" limit="5"]` – kombinierte Ansicht (nächste Spiele, letzte Resultate, Rangliste)
* `[swissvolley_club_games limit="10"]` – nächste Spiele aller ausgewählten Teams
* `[swissvolley_club_results limit="10"]` – letzte Resultate aller ausgewählten Teams

Der Parameter `team` akzeptiert die Swiss-Volley-Team-ID oder den im Backend vergebenen Alias, z. B. `[swissvolley_team team="herren-1"]`.

Vollständige Referenz: `docs/SHORTCODES.md`.

== Frequently Asked Questions ==

= Woher bekomme ich den API-Key? =
Im Volley Manager unter Administration → Club → Webservice/API. Der Key ist club-gebunden.

= Warum kann ich keinen beliebigen Verein wählen? =
Die Swiss-Volley-API liefert ausschliesslich die Daten des Vereins, dem der API-Key gehört. Der Verein wird deshalb automatisch aus den Daten erkannt. Details: `docs/API.md`.

= Wie oft aktualisieren sich die Daten? =
Gemäss eingestellter Cache-Dauer (Standard 30 Minuten, einstellbar 5 Minuten bis 24 Stunden). Über «Cache jetzt leeren» kann sofort aktualisiert werden.

= Was passiert, wenn Swiss Volley nicht erreichbar ist? =
Die Webseite läuft normal weiter. Wenn vorhanden, werden die zuletzt erfolgreich geladenen Daten mit einem Hinweis angezeigt; andernfalls erscheint eine neutrale Meldung. Besucher sehen nie technische Fehlermeldungen.

= Kann ich das Design anpassen? =
Ja. Alle Elemente tragen eindeutige Klassen (`vssv-games`, `vssv-game`, `vssv-team`, `vssv-result`, `vssv-ranking`, `vssv-own-team`, `vssv-date`, `vssv-location` …). Kleinere Anpassungen gelingen direkt im Feld «Eigenes CSS». Das Plugin verwendet keine !important-Regeln.

== Datenschutz ==

Alle API-Aufrufe erfolgen serverseitig über die WordPress-HTTP-API. Besucher der Webseite senden keine Requests an Swiss Volley; es werden keine Besucher-IP-Adressen oder andere personenbezogene Daten an Swiss Volley übermittelt. Das Plugin verwendet keine externen Tracking-Dienste, keine CDNs, keine Werbung und sendet keine Telemetrie.

== Swiss-Volley-API-Hinweis ==

Dieses Plugin nutzt die offizielle Swiss-Volley-API (https://api.volleyball.ch, Dokumentation: https://swissvolley.docs.apiary.io/#reference/indoor). Es ist ein unabhängiges Projekt und wird von Swiss Volley weder betrieben noch unterstützt. Verwendete Endpunkte und bekannte Einschränkungen: `docs/API.md`.

== Anforderungen ==

* WordPress 6.2 oder neuer
* PHP 8.1 oder neuer
* Ausgehende HTTPS-Verbindungen zu api.volleyball.ch
* Ein Swiss-Volley-API-Key (Volley Manager)

== Changelog ==

= 1.0.0 =
* Initial release.
* Shows upcoming games, results and official standings from the Swiss Volley API.
* Automatic club and team detection; nothing is hard-coded.
* Six shortcodes and four block editor blocks with team selection.
* Team aliases, custom team names, custom league labels and per-team links.
* Optional grouping of game lists by league or team, with an optional visitor-facing switcher.
* Caching through WordPress transients, including a fallback to the last known data when the API is unreachable.
* Server-side API access only: the API key never reaches the front end, and visitors send no requests to Swiss Volley.
* Optional debug mode with an API log that never contains secrets.
