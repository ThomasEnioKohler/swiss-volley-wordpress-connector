# Swiss-Volley-API – verwendete Endpunkte und Mapping

Stand der Analyse: August 2026.

## Quelle

* Offizielle Dokumentation: https://swissvolley.docs.apiary.io/#reference/indoor
* Basis-URL: `https://api.volleyball.ch`
* Verwaltung/Key: Volley Manager, https://volleymanager.volleyball.ch → **Administration → Club → Webservice/API**

Die frühere SOAP-Schnittstelle (`myvolley.volleyball.ch/SwissVolley.wsdl`) ist veraltet und wird von diesem Plugin **nicht** verwendet.

## Authentifizierung

Der API-Key ist **club-gebunden** und wird unverändert im `Authorization`-Header gesendet (ohne `Bearer`-Präfix):

```
GET /indoor/games?includeCup=1 HTTP/1.1
Host: api.volleyball.ch
Authorization: <API-KEY>
Accept: application/json
```

Fehlerhafte Keys führen zu HTTP 401/403; das Plugin meldet dies verständlich im Backend und gibt den Key nie aus.

## Verwendete Endpunkte

### GET /indoor/games?includeCup=1

Liefert **alle Spiele der Teams des Vereins** (vergangene und kommende) als JSON-Array.

Wichtig: Ohne `includeCup=1` blendet die API sämtliche Cup-Spiele (z. B. Mobiliar Volley Cup, regionale Cups) aus. Das Plugin setzt den Parameter deshalb immer.

Erwartete Struktur eines Spiels (relevante Felder):

```json
{
  "gameId": 123456,
  "playDate": "2026-10-17 18:00:00",
  "gender": "m",
  "status": 2,
  "teams": {
    "home": { "teamId": 6631, "caption": "Volley Pizol Herren 1", "clubId": "906295", "clubCaption": "Volley Pizol" },
    "away": { "teamId": 7000, "caption": "Volley Näfels", "clubId": "…", "clubCaption": "…" }
  },
  "league": { "leagueId": 1, "leagueCategoryId": 4, "caption": "2L", "season": 2026, "translations": { "d": "…", "shortD": "…", "f": "…", "shortF": "…" } },
  "phase":  { "phaseId": 1, "caption": "…", "translations": { } },
  "group":  { "groupId": 910, "caption": "Herren 2. Liga", "translations": { } },
  "hall":   { "hallId": 1, "caption": "Sporthalle Riet", "street": "…", "number": "…", "zip": "…", "city": "Sargans", "latitude": 47.0, "longitude": 9.4, "plusCode": "…" },
  "referees":      { "1": { "refereeId": 1, "lastName": "…", "firstName": "…" } },
  "setResults":    { "1": { "home": "25", "away": "19" }, "2": { "home": "22", "away": "25" } },
  "resultSummary": { "wonSetsHomeTeam": 3, "wonSetsAwayTeam": 1, "winner": "home" }
}
```

**Besonderheiten (im Plugin abgefangen):**

* `setResults`, `referees` und `resultSummary` sind **leere Arrays `[]` statt Objekten**, solange kein Wert vorliegt.
* `playDate` ist Schweizer Lokalzeit (`YYYY-MM-DD HH:MM:SS`) ohne Zeitzonenangabe. Das Plugin interpretiert sie als `Europe/Zurich` und gibt sie über `wp_date()` in der in WordPress konfigurierten Zeitzone aus (Sommer-/Winterzeit korrekt).
* `status` ist ein nicht offiziell dokumentierter Integer; er ist **2 sowohl für angesetzte als auch für gespielte Spiele** und ändert sich z. B. bei Verschiebungen. Das Plugin nutzt ihn deshalb **nicht** zur Unterscheidung gespielt/kommend, sondern `resultSummary` (Resultat vorhanden = gespielt).
* Bei Meisterschaftsspielen trägt meist `group.caption` den vollständigen Liganamen („Herren 2. Liga“), während `league.caption` ein Kurzcode ist („2L“). Bei Cup-Spielen ist es umgekehrt (`league.caption` = „Mobiliar Volley Cup“, `group.caption` = Runde). Das Plugin bildet die Anzeige entsprechend.
* `league.season` ist das Startjahr der Saison als Integer (2026 = Saison 2026/27).

### GET /indoor/ranking

Liefert die **offiziellen Ranglisten** aller Gruppen, in denen Vereinsteams spielen, als JSON-Array:

```json
[
  {
    "leagueId": 1,
    "phaseId": 1,
    "groupId": 910,
    "ranking": [
      {
        "rank": 1, "teamId": 6631, "teamCaption": "Volley Pizol Herren 1",
        "games": 10, "points": 25,
        "wins": 8, "winsClear": 6, "winsNarrow": 2,
        "defeats": 2, "defeatsClear": 1, "defeatsNarrow": 1,
        "setsWon": 26, "setsLost": 10,
        "ballsWon": 850, "ballsLost": 700,
        "isTeam": true
      }
    ]
  }
]
```

Das Plugin übernimmt die **Reihenfolge unverändert** (keine eigene Berechnung) und ordnet Gruppen einem Team über die `groupId`-Werte seiner Meisterschaftsspiele zu.

### Weitere dokumentierte Endpunkte (nicht verwendet)

* `GET /indoor/upcomingGames` und `GET /indoor/recentResults`: Convenience-Ausschnitte von `/indoor/games`. Das Plugin filtert stattdessen selbst aus `/indoor/games`, damit ein einziger gecachter Abruf alle Ansichten (pro Team und vereinsweit, kommend und gespielt) versorgt.

## Mapping API → interne Struktur

`SVC_Data::normalize_game()` überführt jedes Spiel in:

| Intern          | Quelle                                                        |
| --------------- | ------------------------------------------------------------- |
| `id`            | `gameId`                                                      |
| `timestamp`     | `playDate` als `Europe/Zurich` geparst                        |
| `date` / `time` | `playDate` (Rohwerte)                                         |
| `home_team` / `away_team` | `teams.home.caption` / `teams.away.caption`         |
| `home_team_id` / `away_team_id` | `teams.*.teamId`                              |
| `home_club_id` / `away_club_id` | `teams.*.clubId`                              |
| `home_sets` / `away_sets` | `resultSummary.wonSets*` (null, wenn kein Resultat) |
| `set_results`   | `setResults` (Map → Liste, Werte als int)                     |
| `venue` / `venue_city` | `hall.caption` / `hall.city`                           |
| `league`        | `group.caption`, Fallback `phase.caption` / `league.caption`; bei Cups mit Wettbewerbsname kombiniert |
| `season` / `season_year` | `league.season`, Fallback aus dem Spieldatum         |
| `status`        | abgeleitet: `played`, wenn `resultSummary` ein Resultat trägt, sonst `upcoming` |
| `is_cup`        | Heuristik über `league.caption` (cup/pokal/coupe/coppa)       |

## Erkennung von Verein und Teams

Die API kennt **keine Endpunkte zum Auflisten von Clubs, Teams oder Saisons** – der Key ist club-gebunden. Das Plugin leitet deshalb ab:

* **Eigener Verein:** In jedem gelieferten Spiel ist mindestens ein eigenes Team beteiligt. Die `clubId` mit der höchsten Abdeckung über alle Spiele ist der eigene Verein (`SVC_Data::detect_own_club()`).
* **Teams:** Alle Teams mit dieser `clubId` aus den Spieldaten (`SVC_Data::derive_teams()`), inkl. Liga, Saison und Gruppen-IDs. Nichts ist hart codiert.
* **Saisons:** Alle in den Daten vorkommenden `league.season`-Werte (`SVC_Data::list_seasons()`).

## Bekannte Einschränkungen der aktuellen API

1. **Keine Club-Auswahl:** Es können nur Daten des Vereins angezeigt werden, dem der API-Key gehört. Ein Dropdown „beliebigen Verein wählen“ ist technisch nicht möglich; das Backend zeigt den erkannten Verein zur Bestätigung an.
2. **Kein Saisonarchiv:** Die API liefert die aktuell im Volley Manager geführten Daten (laufende Saison, nach dem Rollover die neue). Historische Saisons sind nicht abrufbar. Der Saisonwechsel selbst erfordert **keine Codeänderung**: Neue Daten erscheinen automatisch; optional kann im Backend ein Saison-Startjahr als Filter gesetzt werden.
3. **Keine separate „Spielnummer“:** Die API liefert `gameId` als eindeutige Kennung; eine davon abweichende offizielle Spielnummer ist im Feed nicht enthalten und wird daher nicht angezeigt (nicht erfunden, vgl. Anforderung 27).
4. **`status` undokumentiert:** Die Bedeutung der Integer-Werte ist nicht offiziell dokumentiert; „Spiel verschoben“ zeigt sich zuverlässig als geänderter `playDate`, den das Plugin automatisch übernimmt. Ein explizites „verschoben“-Label wird mangels dokumentierter Semantik nicht angezeigt.
5. **Nur Indoor:** Dieses Plugin nutzt die Indoor-Referenz der API. Beach-Daten sind nicht Teil von Version 0.1.0.
6. **Abweichungen Doku ↔ Realität:** Die Apiary-Dokumentation nennt `includeCup` nicht prominent; in der Praxis werden Cup-Spiele ohne diesen Parameter unterdrückt (verifiziert an produktiven Integrationen). Ebenso sind die „leeres Array statt Objekt“-Fälle bei `setResults`/`resultSummary`/`referees` nicht dokumentiert, treten aber auf. Beides ist im Plugin berücksichtigt.
