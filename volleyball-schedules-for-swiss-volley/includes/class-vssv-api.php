<?php
/**
 * HTTP-Client für die offizielle Swiss-Volley-API (Volley Manager).
 *
 * Dokumentation: https://swissvolley.docs.apiary.io/#reference/indoor
 * Basis-URL:     https://api.volleyball.ch
 *
 * Authentifizierung: Der club-gebundene API-Key wird unverändert im
 * "Authorization"-Header gesendet (kein "Bearer"-Präfix). Der Key wird im
 * Volley Manager unter Administration > Club > Webservice/API erzeugt.
 *
 * Alle Aufrufe erfolgen ausschliesslich serverseitig über die WordPress
 * HTTP-API (wp_remote_get). Der API-Key verlässt den Server nie Richtung
 * Browser und wird nie protokolliert.
 *
 * Verwendete, offiziell dokumentierte Endpunkte:
 *  - GET /indoor/games?includeCup=1  Alle Spiele der Vereinsteams (inkl. Cup)
 *  - GET /indoor/ranking             Ranglisten aller Gruppen mit Vereinsteams
 *
 * Die API kennt KEINE Endpunkte zum Auflisten von Clubs, Teams oder Saisons:
 * Der Key ist club-gebunden; Verein, Teams und Saisons werden aus den
 * Spieldaten abgeleitet (siehe VSSV_Data). Details: docs/API.md.
 *
 * @package VolleyballSchedulesForSwissVolley
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VSSV_API
 */
class VSSV_API {

	const DEFAULT_BASE_URL = 'https://api.volleyball.ch';
	const TIMEOUT          = 15;

	/**
	 * API-Basis-URL (ohne abschliessenden Slash).
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * Club-gebundener API-Key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key  API-Key; null = aus Einstellungen.
	 * @param string|null $base_url Basis-URL; null = aus Einstellungen.
	 */
	public function __construct( ?string $api_key = null, ?string $base_url = null ) {
		$settings = get_option( 'vssv_settings', array() );

		$this->api_key  = ( null !== $api_key ) ? $api_key : (string) ( $settings['api_key'] ?? '' );
		$base           = ( null !== $base_url ) ? $base_url : (string) ( $settings['api_base_url'] ?? self::DEFAULT_BASE_URL );
		$this->base_url = untrailingslashit( $base ? $base : self::DEFAULT_BASE_URL );
	}

	/**
	 * Prüft, ob ein API-Key hinterlegt ist.
	 *
	 * @return bool
	 */
	public function has_key(): bool {
		return '' !== trim( $this->api_key );
	}

	/**
	 * GET-Request gegen die API ausführen und JSON dekodieren.
	 *
	 * @param string               $path Pfad, z. B. '/indoor/games'.
	 * @param array<string,string> $args Query-Parameter.
	 * @return array|WP_Error Dekodiertes JSON-Array oder WP_Error.
	 */
	public function get( string $path, array $args = array() ) {
		if ( ! $this->has_key() ) {
			return new WP_Error(
				'vssv_no_key',
				__( 'Es ist kein Swiss-Volley-API-Key hinterlegt.', 'volleyball-schedules-for-swiss-volley' )
			);
		}

		$url = $this->base_url . '/' . ltrim( $path, '/' );
		if ( ! empty( $args ) ) {
			$url = add_query_arg( array_map( 'rawurlencode', $args ), $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 3,
				'headers'     => array(
					// Die Swiss-Volley-API erwartet den Key unverändert im Authorization-Header.
					'Authorization' => $this->api_key,
					'Accept'        => 'application/json',
				),
				'user-agent'  => 'volleyball-schedules-for-swiss-volley/' . VSSV_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			VSSV_Logger::log( $path, '-', $response->get_error_message(), 'error' );
			return new WP_Error(
				'vssv_http_error',
				__( 'Die Swiss-Volley-API ist momentan nicht erreichbar.', 'volleyball-schedules-for-swiss-volley' )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 401 === $code || 403 === $code ) {
			VSSV_Logger::log( $path, $code, 'Authentifizierung fehlgeschlagen.', 'error' );
			return new WP_Error(
				'vssv_auth_error',
				__( 'Authentifizierung fehlgeschlagen. Bitte den API-Key prüfen (Volley Manager: Administration → Club → Webservice/API).', 'volleyball-schedules-for-swiss-volley' )
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			VSSV_Logger::log( $path, $code, 'Unerwarteter HTTP-Status.', 'error' );
			return new WP_Error(
				'vssv_bad_status',
				sprintf(
					/* translators: %d: HTTP-Statuscode */
					__( 'Die Swiss-Volley-API hat einen unerwarteten Status geliefert (HTTP %d).', 'volleyball-schedules-for-swiss-volley' ),
					$code
				)
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			VSSV_Logger::log( $path, $code, 'Antwort war kein gültiges JSON-Array.', 'error' );
			return new WP_Error(
				'vssv_bad_json',
				__( 'Die Antwort der Swiss-Volley-API konnte nicht verarbeitet werden.', 'volleyball-schedules-for-swiss-volley' )
			);
		}

		VSSV_Logger::log( $path, $code, 'OK (' . count( $data ) . ' Einträge).' );

		return $data;
	}

	/**
	 * Alle Spiele der Vereinsteams laden (inkl. Cup-Spiele).
	 *
	 * Hinweis: Ohne includeCup=1 blendet die API sämtliche Cup-Spiele aus.
	 *
	 * @return array|WP_Error
	 */
	public function get_games() {
		return $this->get( '/indoor/games', array( 'includeCup' => '1' ) );
	}

	/**
	 * Ranglisten aller Gruppen laden, in denen Vereinsteams spielen.
	 *
	 * @return array|WP_Error
	 */
	public function get_rankings() {
		return $this->get( '/indoor/ranking' );
	}

	/**
	 * Verbindung testen: Erreichbarkeit + Authentifizierung.
	 *
	 * Gibt bei Erfolg eine kleine, unkritische Zusammenfassung zurück
	 * (Anzahl Spiele, erkannter Verein) – niemals den API-Key.
	 *
	 * @return array{ok:bool,message:string,club_id?:string,club_name?:string,game_count?:int}|WP_Error
	 */
	public function test_connection() {
		$games = $this->get_games();
		if ( is_wp_error( $games ) ) {
			return $games;
		}

		$result = array(
			'ok'         => true,
			'game_count' => count( $games ),
			'message'    => sprintf(
				/* translators: %d: Anzahl Spiele */
				__( 'Verbindung erfolgreich. Die API hat %d Spiele geliefert.', 'volleyball-schedules-for-swiss-volley' ),
				count( $games )
			),
		);

		$club = VSSV_Data::detect_own_club( $games );
		if ( $club ) {
			$result['club_id']   = $club['id'];
			$result['club_name'] = $club['name'];
		}

		return $result;
	}
}
