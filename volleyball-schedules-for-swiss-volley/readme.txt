=== Volleyball Schedules for Swiss Volley ===
Contributors: volleypizol
Tags: volleyball, sports, schedule, results, standings
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show volleyball schedules, results and official standings from the Swiss Volley API on your club website.

== Description ==

This plugin connects WordPress to the official Swiss Volley API (Volley Manager) and displays:

* Upcoming games with date, time, home team, away team and venue
* Results including set scores
* Official standings, taken from Swiss Volley rather than calculated
* Club-wide overviews across all your teams

When a fixture moves or a result is entered at Swiss Volley, the change appears on your site automatically. No manual upkeep.

The API is called server-side only and the responses are cached in WordPress transients. Your API key never appears in the front end, in JavaScript or in logs, and visitors never send requests to Swiss Volley.

This plugin is an independent project. It is not affiliated with, operated by, or endorsed by Swiss Volley.

== Installation ==

1. Install the plugin through **Plugins > Add New** and activate it.
2. In Volley Manager (https://volleymanager.volleyball.ch), go to **Administration > Club > Webservice/API** and create an API key.
3. In WordPress, go to **Swiss Volley > Settings**, enter the API key and save.
4. Click **Test API connection**. Your club is detected automatically.
5. On the **Teams** tab, click **Load club and teams now**. All teams are fetched from Swiss Volley.
6. Optionally give each team an alias (for example `men-1`) and choose which teams appear in club-wide views.
7. Add shortcodes or blocks to your team pages.

== Shortcodes ==

* `[swissvolley_games team="TEAM_ID" limit="5" scope="upcoming"]` - upcoming games (scope: upcoming, played, all)
* `[swissvolley_results team="TEAM_ID" limit="5"]` - results
* `[swissvolley_ranking team="TEAM_ID"]` - standings
* `[swissvolley_team team="TEAM_ID" limit="5"]` - combined view of next games, latest results and standings
* `[swissvolley_club_games limit="10"]` - next games across all selected teams
* `[swissvolley_club_results limit="10"]` - latest results across all selected teams

The `team` attribute accepts the Swiss Volley team ID or the alias you assigned, for example `[swissvolley_team team="men-1"]`.

Full reference: https://github.com/ThomasEnioKohler/swiss-volley-wordpress-connector/blob/main/volleyball-schedules-for-swiss-volley/docs/SHORTCODES.md

== External services ==

This plugin relies on the official Swiss Volley API to display game data.

* Service: Swiss Volley API (Volley Manager), https://api.volleyball.ch
* What is sent: your club's API key, plus the team and league identifiers required for the requested view.
* When: whenever a page containing one of this plugin's shortcodes or blocks is rendered and the cached data has expired (default 30 minutes, configurable between 5 minutes and 24 hours).
* By whom: your web server. Requests are made server-side through the WordPress HTTP API. Visitors never contact Swiss Volley, so no visitor IP addresses or other personal data are transmitted.
* API documentation: https://swissvolley.docs.apiary.io/#reference/indoor
* Swiss Volley terms of use and privacy policy: https://www.volleyball.ch/de/impressum and https://www.volleyball.ch/de/datenschutzerklaerung

The plugin uses no tracking services, no CDNs, no advertising, and sends no telemetry.

== Frequently Asked Questions ==

= Where do I get an API key? =
In Volley Manager, under Administration > Club > Webservice/API. The key is bound to your club.

= Why can I not choose an arbitrary club? =
The Swiss Volley API only returns data for the club that owns the API key, so the club is detected from the data itself.

= How often is the data refreshed? =
According to the configured cache duration (default 30 minutes, adjustable from 5 minutes to 24 hours). Use "Clear cache now" to refresh immediately.

= What happens when Swiss Volley is unreachable? =
The site keeps working. If data was loaded successfully before, the last known state is shown with a notice; otherwise a neutral message appears. Visitors never see technical error messages.

= Can I change the styling? =
Yes. Every element carries a distinct class (`vssv-games`, `vssv-game`, `vssv-team-view`, `vssv-result`, `vssv-ranking`, `vssv-own-team`, `vssv-date`, `vssv-league` and others). Small adjustments can be made in the "Custom CSS" field. The plugin uses no !important rules.

= Which languages are available? =
English, plus German for Switzerland (de_CH) and Germany (de_DE). Further translations are welcome at translate.wordpress.org.

== Screenshots ==

1. Upcoming games for a team, with date, venue and competition
2. Official standings, with your own team highlighted
3. Settings screen with the API key and automatic club detection
4. Teams screen with aliases, custom team names and custom league names

== Requirements ==

* WordPress 6.2 or newer
* PHP 8.1 or newer
* Outbound HTTPS connections to api.volleyball.ch
* A Swiss Volley API key from Volley Manager

== Changelog ==

= 1.0.1 =
* Removed the Custom CSS setting. Styling belongs in the theme or a child theme, and the plugin no longer stores or injects stylesheet text entered in the admin.
* Translations are no longer bundled with the plugin. German for Switzerland and Germany now comes from translate.wordpress.org as a language pack, which is how the plugin directory distributes translations.
* Corrected the links to Swiss Volley's legal notice and privacy policy in the external services section; the previous URLs no longer resolved.

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
