# Volleyball Schedules for Swiss Volley

WordPress-Plugin zur automatischen Darstellung von Spielplänen, Resultaten und Ranglisten aus der offiziellen Swiss-Volley-API (Volley Manager). Entwickelt für [www.volleypizol.org](https://www.volleypizol.org).

## Architektur

Die Darstellung ist strikt von den HTTP-Aufrufen entkoppelt:

```
VSSV_API        HTTP-Client (wp_remote_get, Authorization-Header, Timeout, Fehlerbehandlung)
VSSV_Cache      Transients + Stale Copy (Fallback bei API-Ausfall)
VSSV_Data       Data Mapper: Rohdaten → interne, stabile Struktur; Abfragen; Ableitung
               von Verein/Teams/Saisons aus den Spieldaten
VSSV_Teams      Persistenz der Teamkonfiguration, Aliase, Auswahl für Club-Ansichten
VSSV_Renderer   HTML-Ausgabe (kontextabhängig escaped)
VSSV_Shortcodes 6 Shortcodes
VSSV_Blocks     4 dynamische Gutenberg-Blöcke (serverseitig gerendert)
VSSV_Admin      Backend: Einstellungen, Teams, Log; AJAX (Nonce + Capability)
VSSV_Logger     Debug-Ringpuffer (nie Secrets)
```

Datenfluss: `Shortcode/Block → VSSV_Data → VSSV_Cache → VSSV_API → api.volleyball.ch`.
Ändert Swiss Volley das JSON-Format, muss nur `VSSV_Data::normalize_game()` angepasst werden.

## Verzeichnisstruktur

```
volleyball-schedules-for-swiss-volley/
├── volleyball-schedules-for-swiss-volley.php   Bootstrap, Konstanten, Aktivierung/Deaktivierung
├── readme.txt                   WordPress-Readme (Installation, FAQ, Changelog)
├── README.md                    Diese Datei
├── uninstall.php                Vollständige Bereinigung beim Löschen
├── docs/
│   ├── API.md                   Verwendete Swiss-Volley-Endpunkte, Mapping, Einschränkungen
│   └── SHORTCODES.md            Alle Shortcodes mit Beispielen
├── includes/
│   ├── class-vssv-plugin.php
│   ├── class-vssv-api.php
│   ├── class-vssv-cache.php
│   ├── class-vssv-data.php
│   ├── class-vssv-teams.php
│   ├── class-vssv-admin.php
│   ├── class-vssv-shortcodes.php
│   ├── class-vssv-blocks.php
│   ├── class-vssv-renderer.php
│   └── class-vssv-logger.php
├── assets/
│   ├── css/frontend.css         Theme-neutrale Standardgestaltung (keine !important)
│   ├── css/admin.css
│   └── js/admin.js              Vanilla JS (Verbindungstest, Teams laden, Cache leeren)
│   └── js/blocks.js             Gutenberg-Blöcke ohne Build-Pipeline
└── languages/
    └── volleyball-schedules-for-swiss-volley.pot
```

## Entwicklung

* PHP ≥ 8.1, WordPress ≥ 6.2, keine externen Dependencies, keine CDNs, keine Telemetrie.
* Quellsprache der Strings ist Deutsch (Schweizer Schreibweise), Textdomain `volleyball-schedules-for-swiss-volley`.
* Optionen: `vssv_settings`, `vssv_teams`, `vssv_log`, `vssv_stale_*`; Transients: `vssv_cache_*`.
* Filter: `vssv_date_format`, `vssv_time_format`.
* Deaktivieren behält alle Einstellungen; Löschen entfernt via `uninstall.php` sämtliche Optionen und Transients inkl. API-Key.

## Sicherheit

* API-Key nur serverseitig; nie im HTML, JS, REST oder Log.
* Nonces und `current_user_can( 'manage_options' )` für alle Admin-Aktionen.
* Alle Eingaben werden sanitisiert, alle Ausgaben kontextabhängig escaped – auch API-Daten gelten als nicht vertrauenswürdig.
* Kein direkter Dateizugriff (`ABSPATH`-Guard in jeder PHP-Datei), kein direktes cURL, kein eigenes SQL.

## Lizenz

GPL-2.0-or-later.
