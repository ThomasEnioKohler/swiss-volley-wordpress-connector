<?php
/**
 * Test-Harness: WordPress-Mocks + Szenarien aus Anforderung 34.
 * Läuft mit error_reporting(E_ALL); jede Warning/Notice schlägt fehl.
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
date_default_timezone_set( 'Europe/Zurich' );

define( 'ABSPATH', '/tmp/' );
define( 'VSSV_VERSION', '0.1.0' );
define( 'VSSV_PLUGIN_DIR', dirname( __DIR__ ) . '/volleyball-schedules-for-swiss-volley/' );
define( 'VSSV_PLUGIN_FILE', VSSV_PLUGIN_DIR . 'volleyball-schedules-for-swiss-volley.php' );
define( 'VSSV_PLUGIN_URL', 'https://example.test/wp-content/plugins/volleyball-schedules-for-swiss-volley/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

/* ------------------------- WP-Mocks ------------------------- */

$GLOBALS['wp_options']    = array();
$GLOBALS['wp_transients'] = array(); // key => [value, expires]
$GLOBALS['http_mock']     = null;    // callable(url, args) => response|WP_Error
$GLOBALS['http_calls']    = 0;

// Anforderung 3: add_action muss aufgezeichnet werden, damit Tests die
// tatsächlich verdrahteten Hooks prüfen und bei Bedarf auslösen können.
$GLOBALS['wp_actions']           = array(); // hook => [callback, ...]
$GLOBALS['registered_blocks']    = array(); // Blockname => Args
$GLOBALS['script_translations'] = array(); // Handle => [domain, path]

class WP_Error {
	private $code; private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['wp_options'] ) ? $GLOBALS['wp_options'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['wp_options'][ $k ] = $v; return true; }
function add_option( $k, $v ) { if ( ! isset( $GLOBALS['wp_options'][ $k ] ) ) { $GLOBALS['wp_options'][ $k ] = $v; } return true; }
function delete_option( $k ) { unset( $GLOBALS['wp_options'][ $k ] ); return true; }

function get_transient( $k ) {
	if ( ! isset( $GLOBALS['wp_transients'][ $k ] ) ) { return false; }
	list( $v, $exp ) = $GLOBALS['wp_transients'][ $k ];
	if ( $exp && $exp < time() ) { unset( $GLOBALS['wp_transients'][ $k ] ); return false; }
	return $v;
}
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['wp_transients'][ $k ] = array( $v, $ttl ? time() + $ttl : 0 ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['wp_transients'][ $k ] ); return true; }

function __( $s, $d = null ) { return $s; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html_x( $s, $c, $d = null ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s, $d = null ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function wp_register_script( $h, $src, $deps = array(), $v = false, $footer = false ) {}
function wp_enqueue_script( $h ) {}
function wp_set_script_translations( $h, $d = null, $p = null ) { $GLOBALS['script_translations'][ $h ] = array( $d, $p ); }
function wp_localize_script( $h, $name, $data ) {}
function wp_register_style( $h, $src, $deps = array(), $v = false ) {}
function wp_enqueue_style( $h ) {}
function wp_add_inline_style( $h, $css ) {}
function esc_url_raw( $u, $p = null ) { $u = trim( (string) $u ); if ( '' === $u ) { return ''; } if ( str_starts_with( $u, '/' ) ) { return $u; } return filter_var( $u, FILTER_VALIDATE_URL ) ? $u : ''; }
function esc_url( $u ) { return htmlspecialchars( esc_url_raw( $u ), ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { $s = strtolower( trim( (string) $s ) ); $s = preg_replace( '/[^a-z0-9]+/', '-', $s ); return trim( $s, '-' ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function shortcode_atts( $defaults, $atts, $tag = '' ) { $atts = (array) $atts; $out = array(); foreach ( $defaults as $k => $v ) { $out[ $k ] = array_key_exists( $k, $atts ) ? $atts[ $k ] : $v; } return $out; }
function add_shortcode( $tag, $cb ) {}
// Anforderung 3: zeichnet die Callbacks pro Hook auf, statt sie zu verwerfen –
// bisherige Szenarien riefen nie ::register()-Methoden auf und lösen daher
// nie einen dieser Hooks aus; das neue Testszenario tut das gezielt.
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['wp_actions'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) {}
// Real definiert (statt gänzlich zu fehlen): function_exists( 'register_block_type' )
// in VSSV_Blocks::register_blocks() soll wie unter echtem Gutenberg true liefern.
function register_block_type( $name, $args = array() ) { $GLOBALS['registered_blocks'][ $name ] = $args; return true; }
function apply_filters( $h, $v ) { return $v; }
function wp_date( $format, $ts ) { return date( $format, $ts ); } // Site-TZ = Europe/Zurich im Test.
function current_user_can( $c ) { return false; }
function is_admin() { return true; } // verhindert Enqueue im Test.
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function add_query_arg( $args, $url ) { return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args ); }
function load_plugin_textdomain( $a, $b = false, $c = '' ) { return true; }
function plugin_basename( $f ) { return basename( $f ); }

function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['http_calls']++;
	$mock = $GLOBALS['http_mock'];
	return $mock ? $mock( $url, $args ) : new WP_Error( 'no_mock', 'no mock' );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }

/* ------------------------- Plugin-Klassen laden ------------------------- */

require VSSV_PLUGIN_DIR . 'includes/class-vssv-logger.php';
require VSSV_PLUGIN_DIR . 'includes/class-vssv-cache.php';
require VSSV_PLUGIN_DIR . 'includes/class-vssv-api.php';
require VSSV_PLUGIN_DIR . 'includes/class-vssv-teams.php';
require VSSV_PLUGIN_DIR . 'includes/class-vssv-data.php';
require VSSV_PLUGIN_DIR . 'includes/class-vssv-renderer.php';
require VSSV_PLUGIN_DIR . 'includes/class-vssv-blocks.php';
require VSSV_PLUGIN_DIR . 'includes/class-vssv-plugin.php';
require VSSV_PLUGIN_DIR . 'includes/class-vssv-shortcodes.php';

/* ------------------------- Testdaten ------------------------- */

function fixture_games(): array {
	return array(
		// Kommendes Spiel Herren 1 (heim), Zukunft.
		array(
			'gameId' => 1, 'playDate' => date( 'Y-m-d', time() + 5 * 86400 ) . ' 18:00:00', 'gender' => 'm', 'status' => 2,
			'teams' => array(
				'home' => array( 'teamId' => 101, 'caption' => 'Volley Pizol Herren 1', 'clubId' => 'VP1', 'clubCaption' => 'Volley Pizol' ),
				'away' => array( 'teamId' => 900, 'caption' => 'Volley Näfels', 'clubId' => 'VN', 'clubCaption' => 'Volley Näfels' ),
			),
			'league' => array( 'leagueId' => 10, 'leagueCategoryId' => 7, 'caption' => '2L', 'season' => 2026 ),
			'phase'  => array( 'phaseId' => 1, 'caption' => 'Hauptrunde' ),
			'group'  => array( 'groupId' => 500, 'caption' => 'Herren 2. Liga' ),
			'hall'   => array( 'hallId' => 1, 'caption' => 'Sporthalle Riet', 'city' => 'Sargans' ),
			'referees' => array(), 'setResults' => array(), 'resultSummary' => array(),
		),
		// Gespieltes Spiel Herren 1: 3:1 mit Satzresultaten.
		array(
			'gameId' => 2, 'playDate' => date( 'Y-m-d', time() - 7 * 86400 ) . ' 18:00:00', 'gender' => 'm', 'status' => 2,
			'teams' => array(
				'home' => array( 'teamId' => 101, 'caption' => 'Volley Pizol Herren 1', 'clubId' => 'VP1', 'clubCaption' => 'Volley Pizol' ),
				'away' => array( 'teamId' => 901, 'caption' => 'TSV Jona', 'clubId' => 'TJ', 'clubCaption' => 'TSV Jona' ),
			),
			'league' => array( 'leagueId' => 10, 'leagueCategoryId' => 7, 'caption' => '2L', 'season' => 2026 ),
			'phase'  => array( 'phaseId' => 1, 'caption' => 'Hauptrunde' ),
			'group'  => array( 'groupId' => 500, 'caption' => 'Herren 2. Liga' ),
			'hall'   => array( 'hallId' => 1, 'caption' => 'Sporthalle Riet', 'city' => 'Sargans' ),
			'referees' => array(),
			'setResults' => array( '1' => array( 'home' => '25', 'away' => '19' ), '2' => array( 'home' => '22', 'away' => '25' ), '3' => array( 'home' => '25', 'away' => '21' ), '4' => array( 'home' => '25', 'away' => '18' ) ),
			'resultSummary' => array( 'wonSetsHomeTeam' => 3, 'wonSetsAwayTeam' => 1, 'winner' => 'home' ),
		),
		// Gespieltes Spiel Damen 1: 3:0 (auswärts).
		array(
			'gameId' => 3, 'playDate' => date( 'Y-m-d', time() - 3 * 86400 ) . ' 20:00:00', 'gender' => 'f', 'status' => 2,
			'teams' => array(
				'home' => array( 'teamId' => 902, 'caption' => 'VBC Sargans', 'clubId' => 'VS', 'clubCaption' => 'VBC Sargans' ),
				'away' => array( 'teamId' => 201, 'caption' => 'Volley Pizol Damen 1', 'clubId' => 'VP1', 'clubCaption' => 'Volley Pizol' ),
			),
			'league' => array( 'leagueId' => 11, 'leagueCategoryId' => 7, 'caption' => '3L', 'season' => 2026 ),
			'phase'  => array( 'phaseId' => 2, 'caption' => 'Hauptrunde' ),
			'group'  => array( 'groupId' => 600, 'caption' => 'Damen 3. Liga' ),
			'hall'   => array( 'hallId' => 2, 'caption' => 'Turnhalle Dorf', 'city' => 'Sargans' ),
			'referees' => array(), 'setResults' => array( '1' => array( 'home' => '20', 'away' => '25' ), '2' => array( 'home' => '18', 'away' => '25' ), '3' => array( 'home' => '23', 'away' => '25' ) ),
			'resultSummary' => array( 'wonSetsHomeTeam' => 0, 'wonSetsAwayTeam' => 3, 'winner' => 'away' ),
		),
		// Gespieltes Spiel Herren 1: 3:2 (knapper Sieg auswärts).
		array(
			'gameId' => 4, 'playDate' => date( 'Y-m-d', time() - 1 * 86400 ) . ' 19:30:00', 'gender' => 'm', 'status' => 2,
			'teams' => array(
				'home' => array( 'teamId' => 903, 'caption' => 'Volley Toggenburg', 'clubId' => 'VT', 'clubCaption' => 'Volley Toggenburg' ),
				'away' => array( 'teamId' => 101, 'caption' => 'Volley Pizol Herren 1', 'clubId' => 'VP1', 'clubCaption' => 'Volley Pizol' ),
			),
			'league' => array( 'leagueId' => 10, 'leagueCategoryId' => 7, 'caption' => '2L', 'season' => 2026 ),
			'phase'  => array( 'phaseId' => 1, 'caption' => 'Hauptrunde' ),
			'group'  => array( 'groupId' => 500, 'caption' => 'Herren 2. Liga' ),
			'hall'   => array( 'hallId' => 3, 'caption' => 'Sporthalle Rietwis', 'city' => 'Wattwil' ),
			'referees' => array(), 'setResults' => array( '1' => array( 'home' => '25', 'away' => '22' ), '2' => array( 'home' => '20', 'away' => '25' ), '3' => array( 'home' => '25', 'away' => '23' ), '4' => array( 'home' => '19', 'away' => '25' ), '5' => array( 'home' => '12', 'away' => '15' ) ),
			'resultSummary' => array( 'wonSetsHomeTeam' => 2, 'wonSetsAwayTeam' => 3, 'winner' => 'away' ),
		),
		// Cup-Spiel Herren 1 (kommend) – includeCup.
		array(
			'gameId' => 5, 'playDate' => date( 'Y-m-d', time() + 12 * 86400 ) . ' 17:00:00', 'gender' => 'm', 'status' => 2,
			'teams' => array(
				'home' => array( 'teamId' => 101, 'caption' => 'Volley Pizol Herren 1', 'clubId' => 'VP1', 'clubCaption' => 'Volley Pizol' ),
				'away' => array( 'teamId' => 904, 'caption' => 'STV St. Gallen', 'clubId' => 'SG', 'clubCaption' => 'STV St. Gallen' ),
			),
			'league' => array( 'leagueId' => 99, 'leagueCategoryId' => 4, 'caption' => 'Mobiliar Volley Cup', 'season' => 2026 ),
			'phase'  => array( 'phaseId' => 9, 'caption' => 'Runde 2' ),
			'group'  => array( 'groupId' => 990, 'caption' => 'Runde 2, Spiel 7' ),
			'hall'   => array( 'hallId' => 1, 'caption' => 'Sporthalle Riet', 'city' => 'Sargans' ),
			'referees' => array(), 'setResults' => array(), 'resultSummary' => array(),
		),
		// Damen 2: Team ganz ohne verwertbare Spiele gibt es nicht via API –
		// "Team ohne Spiele" wird unten als unbekannte Team-ID getestet.
	);
}

function fixture_rankings(): array {
	return array(
		array(
			'leagueId' => 10, 'phaseId' => 1, 'groupId' => 500,
			'ranking' => array(
				array( 'rank' => 1, 'teamId' => 101, 'teamCaption' => 'Volley Pizol Herren 1', 'games' => 3, 'points' => 7, 'wins' => 3, 'defeats' => 0, 'setsWon' => 9, 'setsLost' => 3, 'ballsWon' => 300, 'ballsLost' => 250, 'isTeam' => true ),
				array( 'rank' => 2, 'teamId' => 901, 'teamCaption' => 'TSV Jona', 'games' => 3, 'points' => 5, 'wins' => 2, 'defeats' => 1, 'setsWon' => 7, 'setsLost' => 5, 'ballsWon' => 280, 'ballsLost' => 260, 'isTeam' => true ),
			),
		),
		array(
			'leagueId' => 11, 'phaseId' => 2, 'groupId' => 600,
			'ranking' => array(
				array( 'rank' => 1, 'teamId' => 201, 'teamCaption' => 'Volley Pizol Damen 1', 'games' => 2, 'points' => 6, 'wins' => 2, 'defeats' => 0, 'setsWon' => 6, 'setsLost' => 0, 'ballsWon' => 150, 'ballsLost' => 110, 'isTeam' => true ),
			),
		),
	);
}

/* ------------------------- Mini-Assert ------------------------- */

$failures = 0;
function check( string $name, bool $cond, string $info = '' ): void {
	global $failures;
	if ( $cond ) {
		echo "PASS  $name\n";
	} else {
		$failures++;
		echo "FAIL  $name" . ( $info ? " – $info" : '' ) . "\n";
	}
}

function reset_state( array $settings = array() ): void {
	$GLOBALS['wp_options']           = array();
	$GLOBALS['wp_transients']        = array();
	$GLOBALS['http_calls']           = 0;
	$GLOBALS['wp_actions']           = array();
	$GLOBALS['registered_blocks']    = array();
	$GLOBALS['script_translations'] = array();
	$GLOBALS['wp_options']['vssv_settings'] = array_merge( VSSV_Plugin::default_settings(), array( 'api_key' => 'TESTKEY' ), $settings );
	// Runtime-Cache von VSSV_Data zurücksetzen.
	$ref  = new ReflectionClass( 'VSSV_Data' );
	$prop = $ref->getProperty( 'runtime' );
	$prop->setAccessible( true );
	$prop->setValue( null, array() );
	$prop2 = $ref->getProperty( 'served_stale' );
	$prop2->setAccessible( true );
	$prop2->setValue( null, false );
}

function mock_ok(): void {
	$GLOBALS['http_mock'] = function ( $url, $args ) {
		if ( ( $args['headers']['Authorization'] ?? '' ) !== 'TESTKEY' ) {
			return array( 'code' => 401, 'body' => '' );
		}
		if ( str_contains( $url, '/indoor/ranking' ) ) {
			return array( 'code' => 200, 'body' => json_encode( fixture_rankings() ) );
		}
		if ( str_contains( $url, '/indoor/games' ) ) {
			return array( 'code' => 200, 'body' => json_encode( fixture_games() ) );
		}
		return array( 'code' => 404, 'body' => '' );
	};
}

/* ------------------------- Szenarien (Anforderung 34) ------------------------- */

echo "== Volleyball Schedules for Swiss Volley – Testszenarien ==\n\n";

// 1. API-Key korrekt.
reset_state(); mock_ok();
$api = new VSSV_API();
$r   = $api->test_connection();
check( '01 API-Key korrekt: Verbindungstest OK', ! is_wp_error( $r ) && $r['ok'] && 5 === $r['game_count'] );
check( '04 Volley Pizol gefunden (Club-Erkennung)', ! is_wp_error( $r ) && 'VP1' === ( $r['club_id'] ?? '' ) && 'Volley Pizol' === ( $r['club_name'] ?? '' ) );

// 2. API-Key falsch.
reset_state( array( 'api_key' => 'WRONG' ) );
$GLOBALS['http_mock'] = fn( $url, $args ) => array( 'code' => 401, 'body' => '' );
$r = ( new VSSV_API() )->test_connection();
check( '02 API-Key falsch: verständliche Fehlermeldung, kein Key im Text', is_wp_error( $r ) && str_contains( $r->get_error_message(), 'Authentication' ) && ! str_contains( $r->get_error_message(), 'WRONG' ) );

// 3. API nicht erreichbar (ohne Stale) → sauberer Fehler.
reset_state();
$GLOBALS['http_mock'] = fn() => new WP_Error( 'timeout', 'cURL timeout' );
$games = VSSV_Data::games_for_team( 101, 'upcoming', 5 );
check( '03a API nicht erreichbar ohne Fallback: WP_Error statt Absturz', is_wp_error( $games ) );
$html = VSSV_Renderer::render_error( $games );
check( '03b Besucher-Fehlermeldung ohne technische Details', str_contains( $html, 'could not be loaded' ) && ! str_contains( $html, 'cURL' ) );

// 3c. API nicht erreichbar MIT Stale-Daten → letzter Stand + Hinweis.
reset_state(); mock_ok();
VSSV_Data::raw_games(); // füllt Cache + Stale.
reset_state();         // Runtime + Transients weg …
$GLOBALS['wp_options']['vssv_stale_games'] = array( 'saved' => time() - 3600, 'data' => fixture_games() ); // … Stale bleibt.
$GLOBALS['http_mock'] = fn() => new WP_Error( 'down', 'down' );
$games = VSSV_Data::games_for_team( 101, 'upcoming', 5 );
check( '03c Stale-Fallback liefert zuletzt bekannte Daten', is_array( $games ) && count( $games ) === 2 && VSSV_Data::served_stale() );
$html = VSSV_Renderer::render_games( $games );
check( '03d Hinweis «konnten momentan nicht aktualisiert werden»', str_contains( $html, 'could not be updated' ) );

// 5. Club nicht gefunden (leere API-Antwort).
check( '05 Club nicht gefunden bei leeren Daten', null === VSSV_Data::detect_own_club( array() ) );

// 6. Team gefunden: Ableitung der Teams.
reset_state(); mock_ok();
$raw   = VSSV_Data::raw_games();
$teams = VSSV_Data::derive_teams( $raw, 'VP1' );
check( '06 Teams automatisch abgeleitet (Herren 1 + Damen 1)', isset( $teams[101], $teams[201] ) && 2 === count( $teams ) );
check( '06b Liga aus Meisterschaft, nicht Cup', 'Herren 2. Liga' === $teams[101]['league'] );
check( '06c Saison erkannt', '2026/27' === $teams[101]['season'] );

// Teams speichern für weitere Tests (inkl. Alias).
VSSV_Teams::merge_derived( $teams );
$stored = VSSV_Teams::all();
$stored[101]['alias'] = 'herren-1';
VSSV_Teams::save( $stored );

// 7. Team ohne Spiele (unbekannte/inaktive Team-ID).
$empty = VSSV_Data::games_for_team( 99999, 'upcoming', 5 );
check( '07 Team ohne Spiele: leere Liste, keine Fehler', is_array( $empty ) && 0 === count( $empty ) );
$html = VSSV_Renderer::render_games( $empty );
check( '07b Leermeldung wird angezeigt', str_contains( $html, 'vssv-empty' ) );

// 8. Kommende Spiele vorhanden + aufsteigende Sortierung + Cup enthalten.
$up = VSSV_Data::games_for_team( 101, 'upcoming', 10 );
check( '08 Kommende Spiele (inkl. Cup) aufsteigend sortiert', 2 === count( $up ) && 1 === $up[0]['id'] && 5 === $up[1]['id'] );

// 9. Resultate vorhanden + absteigende Sortierung.
$res = VSSV_Data::games_for_team( 101, 'played', 10 );
check( '09 Resultate absteigend sortiert (neuestes zuerst)', 2 === count( $res ) && 4 === $res[0]['id'] && 2 === $res[1]['id'] );

// 10. Rangliste vorhanden, offizielle Reihenfolge, richtige Gruppe.
$rank = VSSV_Data::ranking_for_team( 101 );
check( '10 Rangliste der richtigen Gruppe, Reihenfolge unverändert', is_array( $rank ) && 1 === count( $rank ) && 500 === $rank[0]['group_id'] && 'Volley Pizol Herren 1' === $rank[0]['rows'][0]['team'] );

// 11. Resultat 3:0.
$d1 = VSSV_Data::games_for_team( 201, 'played', 5 );
check( '11 Resultat 3:0 korrekt normalisiert', 1 === count( $d1 ) && 0 === $d1[0]['home_sets'] && 3 === $d1[0]['away_sets'] && 3 === count( $d1[0]['set_results'] ) );

// 12. Resultat 3:2 inkl. 5 Satzresultaten im HTML.
$html = VSSV_Renderer::render_games( array( $res[0] ) );
check( '12 Resultat 3:2 mit Satzresultaten gerendert', str_contains( $html, '2 : 3' ) && str_contains( $html, '12:15' ) && substr_count( $html, ':' ) >= 5 );

// 13. Spiel verschoben: geänderter playDate erscheint automatisch.
$moved             = fixture_games();
$moved[0]['playDate'] = date( 'Y-m-d', time() + 9 * 86400 ) . ' 20:30:00';
reset_state();
$GLOBALS['http_mock'] = function ( $url, $args ) use ( $moved ) {
	if ( str_contains( $url, 'ranking' ) ) { return array( 'code' => 200, 'body' => json_encode( fixture_rankings() ) ); }
	return array( 'code' => 200, 'body' => json_encode( $moved ) );
};
$up2 = VSSV_Data::games_for_team( 101, 'upcoming', 10 );
check( '13 Verschobenes Spiel: neuer Termin automatisch übernommen', '20:30' === $up2[0]['time'] || '20:30' === $up2[1]['time'] );

// 14./15./16. Cache-Verhalten.
reset_state(); mock_ok();
VSSV_Data::raw_games();
$calls_after_first = $GLOBALS['http_calls'];
// Runtime leeren, Transient behalten → kein neuer HTTP-Call.
$ref = new ReflectionClass( 'VSSV_Data' ); $p = $ref->getProperty( 'runtime' ); $p->setAccessible( true ); $p->setValue( null, array() );
VSSV_Data::raw_games();
check( '14 Cache vorhanden: kein zweiter API-Aufruf', $GLOBALS['http_calls'] === $calls_after_first );
// Cache abgelaufen simulieren.
$GLOBALS['wp_transients'] = array();
$p->setValue( null, array() );
VSSV_Data::raw_games();
check( '15 Cache abgelaufen: API wird erneut abgefragt', $GLOBALS['http_calls'] === $calls_after_first + 1 );
// Manuell geleert.
VSSV_Cache::clear_all();
$p->setValue( null, array() );
VSSV_Data::raw_games();
check( '16 Cache manuell geleert: frische Daten geladen', $GLOBALS['http_calls'] === $calls_after_first + 2 );

// Shortcodes end-to-end (Alias + Club-Ansichten).
reset_state(); mock_ok();
VSSV_Teams::merge_derived( VSSV_Data::derive_teams( VSSV_Data::raw_games(), 'VP1' ) );
$stored = VSSV_Teams::all(); $stored[101]['alias'] = 'herren-1'; VSSV_Teams::save( $stored );
$GLOBALS['wp_options']['vssv_settings']['club_id'] = 'VP1';

$html = VSSV_Shortcodes::team( array( 'team' => 'herren-1', 'limit' => 5 ) );
check( 'S1 Alias-Auflösung + kombinierte Teamansicht', str_contains( $html, 'vssv-team-view' ) && str_contains( $html, 'Upcoming games' ) && str_contains( $html, 'vssv-ranking' ) );
check( 'S2 Eigenes Team mit vssv-own-team hervorgehoben', str_contains( $html, 'vssv-own-team' ) );

$html = VSSV_Shortcodes::club_games( array( 'limit' => 10 ) );
check( 'S3 Vereinsweite kommende Spiele über alle Teams', substr_count( $html, 'vssv-game ' ) === 2 || substr_count( $html, '<article' ) === 2 );

$html = VSSV_Shortcodes::club_results( array( 'limit' => 10 ) );
check( 'S4 Vereinsweite Resultate (3 Spiele, absteigend)', 3 === substr_count( $html, '<article' ) && str_contains( $html, 'vssv-result-sets' ) );

$html = VSSV_Shortcodes::games( array( 'team' => 'gibts-nicht' ) );
check( 'S5 Unbekanntes Team: verständliche Meldung', str_contains( $html, 'not found' ) );

// Team-Filter für Club-Ansichten.
$stored = VSSV_Teams::all(); $stored[201]['in_club'] = false; VSSV_Teams::save( $stored );
$html = VSSV_Shortcodes::club_results( array( 'limit' => 10 ) );
check( 'S6 Team-Filter: abgewähltes Team erscheint nicht', 2 === substr_count( $html, '<article' ) );

// XSS: API-Daten werden escaped.
reset_state();
$evil = fixture_games();
$evil[0]['teams']['home']['caption'] = '<script>alert(1)</script>';
$GLOBALS['http_mock'] = function ( $url ) use ( $evil ) {
	if ( str_contains( $url, 'ranking' ) ) { return array( 'code' => 200, 'body' => json_encode( fixture_rankings() ) ); }
	return array( 'code' => 200, 'body' => json_encode( $evil ) );
};
$up3  = VSSV_Data::games_for_team( 101, 'upcoming', 5 );
$html = VSSV_Renderer::render_games( $up3 );
check( 'S7 API-Daten werden escaped (kein <script> im Output)', ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ) );

// Eigene Liga-Bezeichnung.
reset_state(); mock_ok();
VSSV_Teams::merge_derived( VSSV_Data::derive_teams( VSSV_Data::raw_games(), 'VP1' ) );
$GLOBALS['wp_options']['vssv_settings']['club_id'] = 'VP1';

// L1: ohne Bezeichnung bleibt der API-Wert.
$up = VSSV_Data::games_for_team( 101, 'upcoming', 10 );
check( 'L1 Ohne eigene Bezeichnung: API-Wert bleibt', 'Herren 2. Liga' === $up[0]['league'] );

// L2: mit Bezeichnung wird sie in Team- und Club-Ansichten verwendet.
$stored = VSSV_Teams::all();
$stored[101]['league_label'] = 'Herren 2. Liga Region GL/GR/SG';
VSSV_Teams::save( $stored );
$up  = VSSV_Data::games_for_team( 101, 'upcoming', 10 );
$cg  = VSSV_Data::games_for_club( 'upcoming', 10 );
$c101 = array_values( array_filter( $cg, fn( $g ) => 101 === $g['home_team_id'] || 101 === $g['away_team_id'] ) );
check( 'L2 Eigene Bezeichnung in Teamansicht', 'Herren 2. Liga Region GL/GR/SG' === $up[0]['league'] );
check( 'L2b Eigene Bezeichnung in Club-Ansicht', 'Herren 2. Liga Region GL/GR/SG' === $c101[0]['league'] );

// L3: Cup-Spiele behalten den Wettbewerbsnamen.
$cup = array_values( array_filter( $up, fn( $g ) => ! empty( $g['is_cup'] ) ) );
check( 'L3 Cup-Spiel behält Cup-Namen', 1 === count( $cup ) && str_contains( $cup[0]['league'], 'Mobiliar Volley Cup' ) );

// L4: anderes Team (ohne Bezeichnung) unverändert; merge_derived erhält Bezeichnung.
$d = VSSV_Data::games_for_team( 201, 'played', 5 );
check( 'L4 Anderes Team unverändert', 'Damen 3. Liga' === $d[0]['league'] );
VSSV_Teams::merge_derived( VSSV_Data::derive_teams( VSSV_Data::raw_games(), 'VP1' ) );
check( 'L5 Neuladen der Teams erhält eigene Bezeichnung', 'Herren 2. Liga Region GL/GR/SG' === VSSV_Teams::league_label( 101 ) );

// L6: Renderer gibt die Bezeichnung escaped aus.
$html = VSSV_Renderer::render_games( VSSV_Data::games_for_team( 101, 'upcoming', 1 ) );
check( 'L6 Bezeichnung im Frontend-HTML', str_contains( $html, 'Herren 2. Liga Region GL/GR/SG' ) );

// Eigener Teamname.
$stored = VSSV_Teams::all();
$stored[101]['name_label'] = 'Herren 1';
$stored[201]['name_label'] = 'Damen 1';
VSSV_Teams::save( $stored );

// N1: Heimspiel → Heimname ersetzt, Gegner unverändert.
$up = VSSV_Data::games_for_team( 101, 'upcoming', 10 );
check( 'N1 Eigener Teamname (heim), Gegner unverändert', 'Herren 1' === $up[0]['home_team'] && 'Volley Näfels' === $up[0]['away_team'] );

// N2: Auswärtsspiel → Auswärtsname ersetzt.
$res = VSSV_Data::games_for_team( 101, 'played', 10 );
check( 'N2 Eigener Teamname (auswärts)', 'Herren 1' === $res[0]['away_team'] && 'Volley Toggenburg' === $res[0]['home_team'] );

// N3: Rangliste zeigt eigenen Namen, Gegner unverändert.
$rank = VSSV_Data::ranking_for_team( 101 );
check( 'N3 Rangliste mit eigenem Teamnamen', 'Herren 1' === $rank[0]['rows'][0]['team'] && 'TSV Jona' === $rank[0]['rows'][1]['team'] );

// N4: Club-Ansicht verwendet eigene Namen beider Teams.
$cg = VSSV_Data::games_for_club( 'played', 10 );
$names = array_merge( array_column( $cg, 'home_team' ), array_column( $cg, 'away_team' ) );
check( 'N4 Club-Ansicht mit eigenen Teamnamen', in_array( 'Herren 1', $names, true ) && in_array( 'Damen 1', $names, true ) );

// N5: Leeres Feld → API-Name bleibt (Team 201 zurücksetzen).
$stored = VSSV_Teams::all();
$stored[201]['name_label'] = '';
VSSV_Teams::save( $stored );
$d = VSSV_Data::games_for_team( 201, 'played', 5 );
check( 'N5 Leeres Feld: API-Name bleibt', 'Volley Pizol Damen 1' === $d[0]['away_team'] );

// N6: Neuladen erhält den eigenen Teamnamen; Anzeige inkl. vssv-own-team im HTML.
VSSV_Teams::merge_derived( VSSV_Data::derive_teams( VSSV_Data::raw_games(), 'VP1' ) );
check( 'N6 Neuladen erhält eigenen Teamnamen', 'Herren 1' === VSSV_Teams::name_label( 101 ) );
$html = VSSV_Renderer::render_games( VSSV_Data::games_for_team( 101, 'upcoming', 1 ) );
check( 'N7 Eigener Name im HTML mit vssv-own-team', str_contains( $html, 'Herren 1' ) && str_contains( $html, 'vssv-own-team' ) );

// Liga als Überschrift.
$html = VSSV_Shortcodes::club_games( array( 'limit' => 10 ) );
check( 'G1 Club-Ansicht: Liga als Überschrift (h3)', str_contains( $html, '<h3 class="vssv-game-league">' ) );
check( 'G2 Club-Ansicht: Liga nicht doppelt in Meta-Zeile', ! str_contains( $html, 'vssv-league"' ) );
check( 'G3 Eigene Liga-Bezeichnung in der Überschrift', str_contains( $html, 'Herren 2. Liga Region GL/GR/SG</h3>' ) );

$GLOBALS['wp_options']['vssv_settings']['league_display'] = 'meta';
$html = VSSV_Shortcodes::games( array( 'team' => '101' ) );
check( 'G4 Teamansicht folgt globaler Einstellung (meta)', str_contains( $html, 'vssv-league"' ) && ! str_contains( $html, 'vssv-game-league' ) );
$GLOBALS['wp_options']['vssv_settings']['league_display'] = 'heading';

$html = VSSV_Shortcodes::games( array( 'team' => '101', 'league' => 'heading' ) );
check( 'G5 league="heading" auch für Team-Shortcodes', str_contains( $html, 'vssv-game-league' ) );

$html = VSSV_Shortcodes::club_results( array( 'limit' => 10, 'league' => 'none' ) );
check( 'G6 league="none" blendet Liga aus', ! str_contains( $html, 'vssv-game-league' ) && ! str_contains( $html, 'vssv-league"' ) );

// G7: Cup-Spiel zeigt den Wettbewerbsnamen als Überschrift.
$html = VSSV_Shortcodes::club_games( array( 'limit' => 10 ) );
check( 'G7 Cup-Name als Überschrift', str_contains( $html, 'Mobiliar Volley Cup' ) );

// Team-Link.
$stored = VSSV_Teams::all();
$stored[101]['page_url'] = 'https://www.volleypizol.org/herren-1/';
VSSV_Teams::save( $stored );

// U1: Heimspiel → Heimteam verlinkt, Gegner nicht.
$html = VSSV_Renderer::render_games( VSSV_Data::games_for_team( 101, 'upcoming', 10 ) );
check( 'U1 Heimteam verlinkt', str_contains( $html, '<a class="vssv-team vssv-team-home vssv-own-team vssv-team-link" href="https://www.volleypizol.org/herren-1/">' ) );
check( 'U2 Gegner nicht verlinkt', str_contains( $html, '<span class="vssv-team vssv-team-away">Volley N' ) );

// U3: Auswärtsspiel → Auswärtsteam verlinkt.
$html = VSSV_Renderer::render_games( VSSV_Data::games_for_team( 101, 'played', 10 ) );
check( 'U3 Auswärtsteam verlinkt', str_contains( $html, 'vssv-team-away vssv-own-team vssv-team-link' ) );

// U4: Rangliste → eigene Zeile verlinkt, Gegner nicht.
$html = VSSV_Renderer::render_ranking( VSSV_Data::ranking_for_team( 101 ), 101 );
check( 'U4 Rangliste: eigenes Team verlinkt', str_contains( $html, '<a class="vssv-team-link" href="https://www.volleypizol.org/herren-1/">' ) && ! str_contains( $html, 'Jona</a>' ) );

// U5: Ohne Link bleibt alles wie bisher (Team 201).
$html = VSSV_Renderer::render_games( VSSV_Data::games_for_team( 201, 'played', 5 ) );
check( 'U5 Ohne Link: Span statt Anker', ! str_contains( $html, 'vssv-team-link' ) );

// U6: Neuladen der Teams erhält den Link.
VSSV_Teams::merge_derived( VSSV_Data::derive_teams( VSSV_Data::raw_games(), 'VP1' ) );
check( 'U6 Neuladen erhält Team-Link', 'https://www.volleypizol.org/herren-1/' === VSSV_Teams::page_url( 101 ) );

// U7: URL wird escaped ausgegeben.
$stored = VSSV_Teams::all();
$stored[101]['page_url'] = '/teams/herren-1/?a=1&b=2';
VSSV_Teams::save( $stored );
$html = VSSV_Renderer::render_games( VSSV_Data::games_for_team( 101, 'upcoming', 1 ) );
check( 'U7 Relative URL, escaped (&amp;)', str_contains( $html, 'href="/teams/herren-1/?a=1&amp;b=2"' ) );

// U8: Club-Ansicht ebenfalls verlinkt.
$html = VSSV_Shortcodes::club_games( array( 'limit' => 10 ) );
check( 'U8 Club-Ansicht mit Team-Link', str_contains( $html, 'vssv-team-link' ) );

// Globale Liga-Darstellung.
// K1: Standard 'heading' gilt jetzt auch für Team-Shortcodes.
$html = VSSV_Shortcodes::games( array( 'team' => '101' ) );
check( 'K1 Global heading: Team-Liste mit Überschrift', str_contains( $html, 'vssv-game-league' ) );

// K2: Global 'meta' → Club-Liste klein in der Detailzeile.
$GLOBALS['wp_options']['vssv_settings']['league_display'] = 'meta';
$html = VSSV_Shortcodes::club_games( array( 'limit' => 10 ) );
check( 'K2 Global meta: Club-Liste in Detailzeile', str_contains( $html, 'vssv-league"' ) && ! str_contains( $html, 'vssv-game-league' ) );

// K3: Shortcode-Attribut übersteuert die Einstellung.
$html = VSSV_Shortcodes::club_games( array( 'limit' => 10, 'league' => 'heading' ) );
check( 'K3 Attribut übersteuert Einstellung', str_contains( $html, 'vssv-game-league' ) );

// K4: Ungültiger Einstellungswert fällt auf heading zurück.
$GLOBALS['wp_options']['vssv_settings']['league_display'] = 'quatsch';
$html = VSSV_Shortcodes::games( array( 'team' => '101' ) );
check( 'K4 Ungültiger Wert: Fallback heading', str_contains( $html, 'vssv-game-league' ) );
$GLOBALS['wp_options']['vssv_settings']['league_display'] = 'heading';

// Gruppierung.
// P1: Nach Liga gruppiert – Gruppen-Überschriften, Liga pro Karte ausgeblendet.
$html = VSSV_Shortcodes::club_games( array( 'limit' => 10, 'group_by' => 'league' ) );
check( 'P1 Gruppierung nach Liga: Sections + Überschriften', substr_count( $html, '<section class="vssv-game-group">' ) === 2 && str_contains( $html, '<h3 class="vssv-group-heading">' ) );
check( 'P2 Liga pro Karte ausgeblendet (nicht doppelt)', ! str_contains( $html, 'vssv-game-league' ) && ! str_contains( $html, 'vssv-league"' ) );
check( 'P3 Gruppenreihenfolge = erstes Spiel (Meisterschaft vor Cup)', strpos( $html, 'Region GL/GR/SG' ) < strpos( $html, 'Mobiliar Volley Cup' ) );

// P4: league="heading" explizit → bleibt trotz Liga-Gruppierung sichtbar.
$html = VSSV_Shortcodes::club_games( array( 'limit' => 10, 'group_by' => 'league', 'league' => 'heading' ) );
check( 'P4 Explizites league="heading" bleibt in Gruppen erhalten', str_contains( $html, 'vssv-game-league' ) );

// P5: Nach Team gruppiert – Überschrift = eigener Teamname, Liga weiterhin da.
$html = VSSV_Shortcodes::club_results( array( 'limit' => 10, 'group_by' => 'team' ) );
check( 'P5 Gruppierung nach Team mit eigenem Teamnamen', str_contains( $html, '<h3 class="vssv-group-heading">Herren 1</h3>' ) && str_contains( $html, 'vssv-game-league' ) );
check( 'P6 Zwei Teamgruppen (Herren 1, Damen 1)', 2 === substr_count( $html, 'vssv-group-heading' ) );

// P7: 'liga' als deutsches Synonym.
$html = VSSV_Shortcodes::club_games( array( 'limit' => 10, 'group_by' => 'liga' ) );
check( 'P7 group_by="liga" wird akzeptiert', str_contains( $html, 'vssv-group-heading' ) );

// P8: Ohne group_by unverändert (keine Sections).
$html = VSSV_Shortcodes::club_games( array( 'limit' => 10 ) );
check( 'P8 Ohne group_by keine Gruppen', ! str_contains( $html, 'vssv-game-group' ) );

// P9: Auch für Team-Shortcodes (Meisterschaft/Cup getrennt).
$html = VSSV_Shortcodes::games( array( 'team' => '101', 'group_by' => 'league' ) );
check( 'P9 Team-Shortcode nach Liga gruppiert (Cup separat)', 2 === substr_count( $html, 'vssv-group-heading' ) );

// Interaktiver Umschalter.
// W1: Einstellung aus (Standard) → kein Umschalter.
$html = VSSV_Shortcodes::club_results( array( 'limit' => 10 ) );
check( 'W1 Einstellung aus: kein Umschalter', ! str_contains( $html, 'vssv-switcher' ) );

// W2: Einstellung ein → Umschalter mit allen drei Optionen (Resultate: 2 Ligen, 2 Teams).
$GLOBALS['wp_options']['vssv_settings']['group_switcher'] = 1;
$html = VSSV_Shortcodes::club_results( array( 'limit' => 10 ) );
check( 'W2 Umschalter mit Chronologisch/Liga/Team', str_contains( $html, 'data-vssv-group="none"' ) && str_contains( $html, 'data-vssv-group="league"' ) && str_contains( $html, 'data-vssv-group="team"' ) );
check( 'W3 Karten tragen Daten-Attribute', str_contains( $html, 'data-vssv-league="' ) && str_contains( $html, 'data-vssv-team="Herren 1"' ) );
check( 'W4 Startansicht chronologisch', str_contains( $html, 'data-vssv-initial="none"' ) );

// W5: group_by als Startansicht.
$html = VSSV_Shortcodes::club_results( array( 'limit' => 10, 'group_by' => 'team' ) );
check( 'W5 group_by wird zur Startansicht', str_contains( $html, 'data-vssv-initial="team"' ) && ! str_contains( $html, 'vssv-game-group' ) );

// W6: Attribut switcher="0" übersteuert die Einstellung (serverseitige Gruppierung greift wieder).
$html = VSSV_Shortcodes::club_results( array( 'limit' => 10, 'group_by' => 'team', 'switcher' => '0' ) );
check( 'W6 switcher="0" übersteuert Einstellung', ! str_contains( $html, 'vssv-switcher' ) && str_contains( $html, 'vssv-game-group' ) );

// W7: Einstellung aus, Attribut switcher="1" schaltet ein.
$GLOBALS['wp_options']['vssv_settings']['group_switcher'] = 0;
$html = VSSV_Shortcodes::club_results( array( 'limit' => 10, 'switcher' => '1' ) );
check( 'W7 switcher="1" übersteuert Einstellung', str_contains( $html, 'vssv-switcher' ) );

// W8: Nur eine Dimension → nur sinnvolle Buttons (Team-Liste: 1 Team, 2 Ligen → kein Team-Button).
$html = VSSV_Shortcodes::games( array( 'team' => '101', 'scope' => 'all', 'switcher' => '1' ) );
check( 'W8 Nur-Liga-Umschalter bei einzelnem Team', str_contains( $html, 'data-vssv-group="league"' ) && ! str_contains( $html, 'data-vssv-group="team"' ) );

// W9: Nichts zu gruppieren (eine Liga, ein Team) → gar kein Umschalter.
$html = VSSV_Shortcodes::results( array( 'team' => '201', 'switcher' => '1' ) );
check( 'W9 Keine Dimension: kein Umschalter', ! str_contains( $html, 'vssv-switcher' ) );
$GLOBALS['wp_options']['vssv_settings']['group_switcher'] = 0;

// Hook-Verdrahtung (Anforderung 3): wp_set_script_translations wird für den
// Block-Editor-Handle 'vssv-blocks' aufgerufen. Das war bislang eine
// unverdrahtete Annahme: VSSV_Blocks wurde in diesem Harness nie geladen,
// wodurch wp_set_script_translations nie feuern konnte.
// load_plugin_textdomain() entfaellt: seit WP 4.6 laedt Core die
// Uebersetzungen von org-gehosteten Plugins automatisch.
reset_state();
VSSV_Plugin::instance();

$init_hooks = $GLOBALS['wp_actions']['init'] ?? array();

foreach ( $init_hooks as $cb ) {
	call_user_func( $cb );
}

check(
	'I2 wp_set_script_translations für vssv-blocks beim init-Aufruf ausgelöst',
	isset( $GLOBALS['script_translations']['vssv-blocks'] )
		&& 'volleyball-schedules-for-swiss-volley' === $GLOBALS['script_translations']['vssv-blocks'][0]
);
check(
	'I3 Blöcke tatsächlich registriert (register_block_type über init ausgelöst)',
	isset( $GLOBALS['registered_blocks']['swiss-volley/games'] )
);

echo "\n" . ( $failures ? "$failures TEST(S) FEHLGESCHLAGEN" : 'ALLE TESTS BESTANDEN' ) . "\n";
exit( $failures ? 1 : 0 );
