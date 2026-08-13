# Swiss Volley Connector

WordPress-Plugin zur automatischen Darstellung von Spielplänen, Resultaten und Ranglisten aus der offiziellen Swiss-Volley-API (Volley Manager). Entwickelt für [www.volleypizol.org](https://www.volleypizol.org).

## Architektur

Die Darstellung ist strikt von den HTTP-Aufrufen entkoppelt:

```
SVC_API        HTTP-Client (wp_remote_get, Authorization-Header, Timeout, Fehlerbehandlung)
SVC_Cache      Transients + Stale Copy (Fallback bei API-Ausfall)
SVC_Data       Data Mapper: Rohdaten → interne, stabile Struktur; Abfragen; Ableitung
               von Verein/Teams/Saisons aus den Spieldaten
SVC_Teams      Persistenz der Teamkonfiguration, Aliase, Auswahl für Club-Ansichten
SVC_Renderer   HTML-Ausgabe (kontextabhängig escaped)
SVC_Shortcodes 6 Shortcodes
SVC_Blocks     4 dynamische Gutenberg-Blöcke (serverseitig gerendert)
SVC_Admin      Backend: Einstellungen, Teams, Log; AJAX (Nonce + Capability)
SVC_Logger     Debug-Ringpuffer (nie Secrets)
```

Datenfluss: `Shortcode/Block → SVC_Data → SVC_Cache → SVC_API → api.volleyball.ch`.
Ändert Swiss Volley das JSON-Format, muss nur `SVC_Data::normalize_game()` angepasst werden.

## Verzeichnisstruktur

```
swiss-volley-connector/
├── swiss-volley-connector.php   Bootstrap, Konstanten, Aktivierung/Deaktivierung
├── readme.txt                   WordPress-Readme (Installation, FAQ, Changelog)
├── README.md                    Diese Datei
├── uninstall.php                Vollständige Bereinigung beim Löschen
├── docs/
│   ├── API.md                   Verwendete Swiss-Volley-Endpunkte, Mapping, Einschränkungen
│   └── SHORTCODES.md            Alle Shortcodes mit Beispielen
├── includes/
│   ├── class-svc-plugin.php
│   ├── class-svc-api.php
│   ├── class-svc-cache.php
│   ├── class-svc-data.php
│   ├── class-svc-teams.php
│   ├── class-svc-admin.php
│   ├── class-svc-shortcodes.php
│   ├── class-svc-blocks.php
│   ├── class-svc-renderer.php
│   └── class-svc-logger.php
├── assets/
│   ├── css/frontend.css         Theme-neutrale Standardgestaltung (keine !important)
│   ├── css/admin.css
│   └── js/admin.js              Vanilla JS (Verbindungstest, Teams laden, Cache leeren)
│   └── js/blocks.js             Gutenberg-Blöcke ohne Build-Pipeline
└── languages/
    └── swiss-volley-connector.pot
```

## Entwicklung

* PHP ≥ 8.1, WordPress ≥ 6.2, keine externen Dependencies, keine CDNs, keine Telemetrie.
* Quellsprache der Strings ist Deutsch (Schweizer Schreibweise), Textdomain `swiss-volley-connector`.
* Optionen: `svc_settings`, `svc_teams`, `svc_log`, `svc_stale_*`; Transients: `svc_cache_*`.
* Filter: `svc_date_format`, `svc_time_format`.
* Deaktivieren behält alle Einstellungen; Löschen entfernt via `uninstall.php` sämtliche Optionen und Transients inkl. API-Key.

## Sicherheit

* API-Key nur serverseitig; nie im HTML, JS, REST oder Log.
* Nonces und `current_user_can( 'manage_options' )` für alle Admin-Aktionen.
* Alle Eingaben werden sanitisiert, alle Ausgaben kontextabhängig escaped – auch API-Daten gelten als nicht vertrauenswürdig.
* Kein direkter Dateizugriff (`ABSPATH`-Guard in jeder PHP-Datei), kein direktes cURL, kein eigenes SQL.

## Lizenz

GPL-2.0-or-later.
