# Shortcodes

Der Parameter `team` akzeptiert überall die **Swiss-Volley-Team-ID** oder den im Backend vergebenen **Alias** (Swiss Volley → Teams). Aliase machen Shortcodes robust gegen ID-Änderungen und lesbar, z. B. `herren-1`.

Team- und Liganamen stammen aus den Swiss-Volley-Daten. Sind unter **Swiss Volley → Teams** ein «Eigener Teamname» oder eine «Eigene Liga-Bezeichnung» hinterlegt, werden stattdessen diese verwendet – in allen Anzeigen inkl. Rangliste. Gegnernamen bleiben unverändert; Cup-Spiele behalten den offiziellen Wettbewerbsnamen. Ist zusätzlich ein «Team-Link» hinterlegt, wird der Teamname in Spiellisten und Rangliste zur Teamseite verlinkt (CSS-Klasse `svc-team-link`).

Alle Ausgaben sind responsive: auf dem Desktop tabellenartige Zeilen (Datum · Zeit · Heimteam · Auswärtsteam · Resultat), auf Mobilgeräten übersichtliche Karten.

---

## [swissvolley_games] – kommende Spiele

```
[swissvolley_games team="12345"]
[swissvolley_games team="herren-1" limit="10"]
[swissvolley_games team="herren-1" scope="all"]
```

| Attribut | Standard   | Beschreibung                                             |
| -------- | ---------- | -------------------------------------------------------- |
| `team`   | –          | Team-ID oder Alias (erforderlich)                        |
| `limit`  | `5`        | Maximale Anzahl Spiele (`0` = alle, max. 100)            |
| `scope`  | `upcoming` | `upcoming` (kommend), `played` (gespielt), `all` (beide) |
| `league` | (Einstellung) | Liga-Anzeige: `heading` (Überschrift), `meta` (Detailzeile), `none` (ausblenden). Ohne Angabe gilt die Einstellung «Liga-Darstellung in Spiellisten». |
| `group_by` | `none` | Spiele gruppieren: `league` (nach Liga, Synonym `liga`) oder `team` (nach eigenem Team). Jede Gruppe erhält eine Überschrift. Mit aktivem Umschalter dient der Wert als Startansicht. |
| `switcher` | (Einstellung) | `1` zeigt Besuchern den Umschalter «Chronologisch / Nach Liga / Nach Team», `0` blendet ihn aus; ohne Angabe gilt die Einstellung «Interaktive Gruppierung». |

Sortierung: kommende Spiele aufsteigend nach Datum/Uhrzeit.

## [swissvolley_results] – Resultate

```
[swissvolley_results team="12345" limit="10"]
[swissvolley_results team="damen-1"]
```

| Attribut | Standard | Beschreibung                                  |
| -------- | -------- | --------------------------------------------- |
| `team`   | –        | Team-ID oder Alias (erforderlich)             |
| `limit`  | `5`      | Maximale Anzahl Spiele (`0` = alle, max. 100) |

Sortierung: absteigend nach Datum/Uhrzeit (neuestes Resultat zuoberst). Satzresultate werden angezeigt, sofern die API sie liefert, z. B. `25:19 · 22:25 · 25:21 · 25:18`.

## [swissvolley_ranking] – Rangliste

```
[swissvolley_ranking team="12345"]
[swissvolley_ranking team="herren-1"]
```

| Attribut | Standard | Beschreibung                      |
| -------- | -------- | ---------------------------------- |
| `team`   | –        | Team-ID oder Alias (erforderlich) |

Zeigt die offizielle Swiss-Volley-Rangliste der Gruppe(n) des Teams – Reihenfolge unverändert von Swiss Volley, keine eigene Berechnung. Die eigene Zeile wird mit `svc-own-team` gekennzeichnet (wenn Hervorhebung aktiv).

## [swissvolley_team] – kombinierte Teamansicht

```
[swissvolley_team team="herren-1"]
[swissvolley_team team="12345" limit="3"]
```

Zeigt nacheinander: **1. Nächste Spiele**, **2. Letzte Resultate**, **3. Rangliste**.

| Attribut | Standard | Beschreibung                                    |
| -------- | -------- | ------------------------------------------------ |
| `team`   | –        | Team-ID oder Alias (erforderlich)               |
| `limit`  | `5`      | Anzahl Spiele je Abschnitt (Rangliste komplett) |

## [swissvolley_club_games] – nächste Spiele des ganzen Vereins

```
[swissvolley_club_games limit="10"]
[swissvolley_club_games limit="20" group_by="league"]
[swissvolley_club_games limit="20" group_by="team"]
```

Führt die chronologisch nächsten Spiele **aller im Backend ausgewählten Teams** zusammen (Swiss Volley → Teams → «In vereinsweiten Ansichten»). Ideal für die Startseite. Vereinsinterne Derbys erscheinen nur einmal.

| Attribut | Standard | Beschreibung             |
| -------- | -------- | ------------------------- |
| `limit`  | `10`      | Maximale Anzahl Spiele    |
| `league` | (Einstellung) | Liga-Anzeige: `heading`, `meta`, `none`; ohne Angabe gilt die globale Einstellung |
| `group_by` | `none` | `league` (nach Liga gruppieren) oder `team` (nach eigenem Team gruppieren); mit Umschalter = Startansicht |
| `switcher` | (Einstellung) | `1`/`0`: Besucher-Umschalter anzeigen/ausblenden; ohne Angabe gilt die globale Einstellung |

## [swissvolley_club_results] – letzte Resultate des ganzen Vereins

```
[swissvolley_club_results limit="10"]
```

| Attribut | Standard | Beschreibung          |
| -------- | -------- | ---------------------- |
| `limit`  | `10`      | Maximale Anzahl Spiele |
| `league` | (Einstellung) | Liga-Anzeige: `heading`, `meta`, `none`; ohne Angabe gilt die globale Einstellung |

---

## Gutenberg-Blöcke

Zusätzlich zu den Shortcodes (die immer funktionieren) stehen vier Blöcke bereit – jeweils mit Dropdown der konfigurierten Teams:

* **Swiss Volley – Spiele**
* **Swiss Volley – Resultate**
* **Swiss Volley – Rangliste**
* **Swiss Volley – Team**

## CSS-Klassen (Auswahl)

`svc-games`, `svc-game`, `svc-game-upcoming`, `svc-game-played`, `svc-when`, `svc-date`, `svc-time`, `svc-matchup`, `svc-team`, `svc-team-home`, `svc-team-away`, `svc-vs`, `svc-own-team`, `svc-result`, `svc-result-sets`, `svc-result-detail`, `svc-meta`, `svc-location`, `svc-league`, `svc-game-league`, `svc-game-group`, `svc-group-heading`, `svc-switcher`, `svc-switch`, `svc-ranking`, `svc-ranking-row`, `svc-heading`, `svc-empty`, `svc-notice`.

Eigene Anpassungen: Feld «Eigenes CSS» unter Swiss Volley → Einstellungen. Das Plugin verwendet keine `!important`-Regeln, Theme-CSS greift daher problemlos.

Datum-/Zeitformat lassen sich per Filter anpassen:

```php
add_filter( 'svc_date_format', fn() => 'j. F Y' );
add_filter( 'svc_time_format', fn() => 'H:i' );
```

Das HTML-Tag der Liga-Überschrift (Standard `h3`, CSS-Klasse `svc-game-league`) kann angepasst werden:

```php
add_filter( 'svc_game_league_heading_tag', fn() => 'h4' );
add_filter( 'svc_group_heading_tag', fn() => 'h4' ); // Überschrift gruppierter Listen
```
