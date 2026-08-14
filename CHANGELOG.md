# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung an [Semantic Versioning](https://semver.org/lang/de/).

Diese Datei ist die gepflegte Quelle. Die Sektion `== Changelog ==` in
`volleyball-schedules-for-swiss-volley/readme.txt` wird daraus erzeugt — dort nichts von Hand
ändern, sondern `bin/sync-readme-changelog.sh` laufen lassen.

## [1.0.0] - 2026-08-13

- Initial release.
- Shows upcoming games, results and official standings from the Swiss Volley API.
- Automatic club and team detection; nothing is hard-coded.
- Six shortcodes and four block editor blocks with team selection.
- Team aliases, custom team names, custom league labels and per-team links.
- Optional grouping of game lists by league or team, with an optional visitor-facing switcher.
- Caching through WordPress transients, including a fallback to the last known data when the API is unreachable.
- Server-side API access only: the API key never reaches the front end, and visitors send no requests to Swiss Volley.
- Optional debug mode with an API log that never contains secrets.
