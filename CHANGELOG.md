# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung an [Semantic Versioning](https://semver.org/lang/de/).

Diese Datei ist die gepflegte Quelle. Die Sektion `== Changelog ==` in
`swiss-volley-connector/readme.txt` wird daraus erzeugt — dort nichts von Hand
ändern, sondern `bin/sync-readme-changelog.sh` laufen lassen.

## [1.0.0] - 2026-08-13

### Geändert
- Erste stabile Version. Das Plugin ist nicht mehr als «Private Beta» gekennzeichnet: Der Hinweis im Kopf der Einstellungsseite entfällt, ebenso die Beta-Vermerke in der Plugin-Beschreibung und im Readme. Funktional ändert sich nichts gegenüber 0.1.7.

## [0.1.7]

### Neu
- Interaktive Gruppierung für Besucher. Über den Spiellisten erscheint ein Umschalter «Chronologisch / Nach Liga / Nach Team», mit dem Besucher die Ansicht direkt auf der Seite wechseln – ohne Neuladen und ohne zusätzliche API-Aufrufe. Aktivierbar global unter Swiss Volley → Einstellungen («Interaktive Gruppierung», standardmässig aus) oder pro Shortcode mit switcher="1" bzw. switcher="0". Der Umschalter zeigt nur sinnvolle Optionen (z. B. keinen Team-Button, wenn die Liste nur ein Team enthält) und erscheint gar nicht, wenn es nichts zu gruppieren gibt. Ein per group_by gesetzter Wert dient als Startansicht. Ohne JavaScript bleibt die chronologische Liste sichtbar.

## [0.1.6]

### Neu
- Gruppierung von Spiellisten über das Attribut group_by="league" (Synonym: "liga") oder group_by="team" – für alle Spiel-Shortcodes inkl. der vereinsweiten Ansichten. Jede Gruppe erhält eine Überschrift (Liga-Bezeichnung bzw. eigener Teamname); die Reihenfolge der Gruppen folgt dem jeweils ersten Spiel, innerhalb der Gruppen bleibt die chronologische Sortierung erhalten. Bei Gruppierung nach Liga wird die Liga pro Spiel automatisch ausgeblendet (nicht doppelt); mit league="heading" oder league="meta" lässt sie sich bewusst zusätzlich anzeigen. Das Überschriften-Tag (Standard h3, Klasse svc-group-heading) ist per Filter svc_group_heading_tag anpassbar.

## [0.1.5]

### Neu
- Einstellung «Liga-Darstellung in Spiellisten» (Swiss Volley → Einstellungen): «Als Überschrift über dem Spiel (gross)» oder «Klein in der Detailzeile». Gilt als Standard für alle Spiellisten (Team- und Vereinsansichten) und ist pro Shortcode weiterhin mit league="heading|meta|none" übersteuerbar.

## [0.1.4]

### Neu
- Spalte «Team-Link» pro Team (Swiss Volley → Teams). Ist eine URL hinterlegt (z. B. die Teamseite), wird der Teamname in allen Anzeigen verlinkt – in Spiellisten (heim wie auswärts) und in der Rangliste. Gegnerteams bleiben unverlinkt. Der Link bleibt beim Neuladen der Teams erhalten; leeres Feld = kein Link (wie bisher).

## [0.1.3]

### Neu
- Liga als Überschrift über jedem Spiel. In den vereinsweiten Ansichten ([swissvolley_club_games], [swissvolley_club_results]) ist das jetzt Standard; die Liga erscheint dort nicht mehr doppelt in der Meta-Zeile. Über das neue Attribut league="meta|heading|none" lässt sich die Darstellung bei allen Spiel-Shortcodes steuern; Team-Shortcodes zeigen die Liga standardmässig weiterhin in der Meta-Zeile. Das Überschriften-Tag (Standard h3) ist per Filter svc_game_league_heading_tag anpassbar.

## [0.1.2]

### Neu
- Spalte «Eigener Teamname» pro Team (Swiss Volley → Teams). Wenn ausgefüllt, ersetzt er in allen Anzeigen (Spiele, Resultate, Rangliste, Block-Auswahl) den Swiss-Volley-Namen, z. B. «Volley Pizol Herren 1» → «Herren 1»; wenn leer, wird weiterhin der Wert von Swiss Volley angezeigt. Gegnernamen bleiben unverändert. Der Name bleibt beim Neuladen der Teams erhalten.

## [0.1.1]

### Neu
- Spalte «Eigene Liga-Bezeichnung» pro Team (Swiss Volley → Teams). Wenn ausgefüllt, ersetzt sie in allen Anzeigen den Swiss-Volley-Kurzcode (z. B. «H2L» → «Herren 2. Liga»); wenn leer, wird weiterhin der Wert von Swiss Volley angezeigt. Cup-Spiele behalten den offiziellen Wettbewerbsnamen. Die Bezeichnung bleibt beim Neuladen der Teams erhalten.

## [0.1.0]

- Erste Version (Private Beta)
- Anbindung an die offizielle Swiss-Volley-API (/indoor/games, /indoor/ranking)
- Automatische Erkennung von Verein und Teams
- Shortcodes für Spiele, Resultate, Rangliste, Teamansicht und vereinsweite Übersichten
- Team-Aliase für sprechende Shortcodes
- Vier Gutenberg-Blöcke mit Team-Auswahl
- Caching über WordPress Transients inkl. Ausfall-Fallback auf letzte bekannte Daten
- Optionaler Debug-Modus mit API-Log (ohne Secrets)
- Deutschsprachige Oberfläche (Schweizer Schreibweise), vollständig übersetzbar
