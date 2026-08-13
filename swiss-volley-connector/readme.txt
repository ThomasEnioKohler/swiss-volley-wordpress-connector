=== Swiss Volley Connector ===
Contributors: volleypizol
Tags: volleyball, swiss volley, spielplan, resultate, rangliste
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.7
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zeigt Spielpläne, Resultate und Ranglisten automatisch aus der offiziellen Swiss-Volley-API an. Entwickelt für www.volleypizol.org.

== Description ==

Swiss Volley Connector verbindet WordPress mit der offiziellen Swiss-Volley-API (Volley Manager) und stellt automatisch dar:

* Kommende Spiele (Datum, Uhrzeit, Heimteam, Auswärtsteam, Spielhalle)
* Resultate inkl. Satzresultaten
* Offizielle Ranglisten (keine eigene Berechnung)
* Vereinsweite Übersichten für die Startseite

Ändert sich ein Spieltermin oder wird ein Resultat bei Swiss Volley eingetragen, erscheint die Änderung automatisch – ohne manuelle Pflege. Die API wird gecacht (WordPress Transients) und ausschliesslich serverseitig aufgerufen: Der API-Key erscheint nie im Frontend, in JavaScript oder in Logs; Besucher senden keine Requests an Swiss Volley (Datenschutz).

**Hinweis zur Verteilung:** Das Plugin ist nicht im WordPress-Plugin-Verzeichnis gelistet. Installation und Aktualisierung erfolgen über das ZIP aus dem jeweiligen Release.

== Installation ==

1. ZIP-Datei herunterladen: `swiss-volley-connector-0.1.0.zip`
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
Ja. Alle Elemente tragen eindeutige Klassen (`svc-games`, `svc-game`, `svc-team`, `svc-result`, `svc-ranking`, `svc-own-team`, `svc-date`, `svc-location` …). Kleinere Anpassungen gelingen direkt im Feld «Eigenes CSS». Das Plugin verwendet keine !important-Regeln.

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

= 0.1.7 =
* Neu: Interaktive Gruppierung für Besucher. Über den Spiellisten erscheint ein Umschalter «Chronologisch / Nach Liga / Nach Team», mit dem Besucher die Ansicht direkt auf der Seite wechseln – ohne Neuladen und ohne zusätzliche API-Aufrufe. Aktivierbar global unter Swiss Volley → Einstellungen («Interaktive Gruppierung», standardmässig aus) oder pro Shortcode mit switcher="1" bzw. switcher="0". Der Umschalter zeigt nur sinnvolle Optionen (z. B. keinen Team-Button, wenn die Liste nur ein Team enthält) und erscheint gar nicht, wenn es nichts zu gruppieren gibt. Ein per group_by gesetzter Wert dient als Startansicht. Ohne JavaScript bleibt die chronologische Liste sichtbar.

= 0.1.6 =
* Neu: Gruppierung von Spiellisten über das Attribut group_by="league" (Synonym: "liga") oder group_by="team" – für alle Spiel-Shortcodes inkl. der vereinsweiten Ansichten. Jede Gruppe erhält eine Überschrift (Liga-Bezeichnung bzw. eigener Teamname); die Reihenfolge der Gruppen folgt dem jeweils ersten Spiel, innerhalb der Gruppen bleibt die chronologische Sortierung erhalten. Bei Gruppierung nach Liga wird die Liga pro Spiel automatisch ausgeblendet (nicht doppelt); mit league="heading" oder league="meta" lässt sie sich bewusst zusätzlich anzeigen. Das Überschriften-Tag (Standard h3, Klasse svc-group-heading) ist per Filter svc_group_heading_tag anpassbar.

= 0.1.5 =
* Neu: Einstellung «Liga-Darstellung in Spiellisten» (Swiss Volley → Einstellungen): «Als Überschrift über dem Spiel (gross)» oder «Klein in der Detailzeile». Gilt als Standard für alle Spiellisten (Team- und Vereinsansichten) und ist pro Shortcode weiterhin mit league="heading|meta|none" übersteuerbar.

= 0.1.4 =
* Neu: Spalte «Team-Link» pro Team (Swiss Volley → Teams). Ist eine URL hinterlegt (z. B. die Teamseite), wird der Teamname in allen Anzeigen verlinkt – in Spiellisten (heim wie auswärts) und in der Rangliste. Gegnerteams bleiben unverlinkt. Der Link bleibt beim Neuladen der Teams erhalten; leeres Feld = kein Link (wie bisher).

= 0.1.3 =
* Neu: Liga als Überschrift über jedem Spiel. In den vereinsweiten Ansichten ([swissvolley_club_games], [swissvolley_club_results]) ist das jetzt Standard; die Liga erscheint dort nicht mehr doppelt in der Meta-Zeile. Über das neue Attribut league="meta|heading|none" lässt sich die Darstellung bei allen Spiel-Shortcodes steuern; Team-Shortcodes zeigen die Liga standardmässig weiterhin in der Meta-Zeile. Das Überschriften-Tag (Standard h3) ist per Filter svc_game_league_heading_tag anpassbar.

= 0.1.2 =
* Neu: Spalte «Eigener Teamname» pro Team (Swiss Volley → Teams). Wenn ausgefüllt, ersetzt er in allen Anzeigen (Spiele, Resultate, Rangliste, Block-Auswahl) den Swiss-Volley-Namen, z. B. «Volley Pizol Herren 1» → «Herren 1»; wenn leer, wird weiterhin der Wert von Swiss Volley angezeigt. Gegnernamen bleiben unverändert. Der Name bleibt beim Neuladen der Teams erhalten.

= 0.1.1 =
* Neu: Spalte «Eigene Liga-Bezeichnung» pro Team (Swiss Volley → Teams). Wenn ausgefüllt, ersetzt sie in allen Anzeigen den Swiss-Volley-Kurzcode (z. B. «H2L» → «Herren 2. Liga»); wenn leer, wird weiterhin der Wert von Swiss Volley angezeigt. Cup-Spiele behalten den offiziellen Wettbewerbsnamen. Die Bezeichnung bleibt beim Neuladen der Teams erhalten.

= 0.1.0 =
* Erste Version (Private Beta)
* Anbindung an die offizielle Swiss-Volley-API (/indoor/games, /indoor/ranking)
* Automatische Erkennung von Verein und Teams
* Shortcodes für Spiele, Resultate, Rangliste, Teamansicht und vereinsweite Übersichten
* Team-Aliase für sprechende Shortcodes
* Vier Gutenberg-Blöcke mit Team-Auswahl
* Caching über WordPress Transients inkl. Ausfall-Fallback auf letzte bekannte Daten
* Optionaler Debug-Modus mit API-Log (ohne Secrets)
* Deutschsprachige Oberfläche (Schweizer Schreibweise), vollständig übersetzbar
