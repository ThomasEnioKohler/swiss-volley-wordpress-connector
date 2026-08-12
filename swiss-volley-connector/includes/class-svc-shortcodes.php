<?php
/**
 * Shortcodes.
 *
 * [swissvolley_games team="ID|alias" limit="5" scope="upcoming|played|all"]
 * [swissvolley_results team="ID|alias" limit="5"]
 * [swissvolley_ranking team="ID|alias"]
 * [swissvolley_team team="ID|alias" limit="5"]
 * [swissvolley_club_games limit="10"]
 * [swissvolley_club_results limit="10"]
 *
 * @package SwissVolleyConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SVC_Shortcodes
 */
class SVC_Shortcodes {

	/**
	 * Shortcodes registrieren.
	 */
	public static function register(): void {
		add_shortcode( 'swissvolley_games', array( __CLASS__, 'games' ) );
		add_shortcode( 'swissvolley_results', array( __CLASS__, 'results' ) );
		add_shortcode( 'swissvolley_ranking', array( __CLASS__, 'ranking' ) );
		add_shortcode( 'swissvolley_team', array( __CLASS__, 'team' ) );
		add_shortcode( 'swissvolley_club_games', array( __CLASS__, 'club_games' ) );
		add_shortcode( 'swissvolley_club_results', array( __CLASS__, 'club_results' ) );
	}

	/**
	 * Team-Parameter auflösen; bei Fehler Meldung zurückgeben.
	 *
	 * @param array $atts Shortcode-Attribute.
	 * @return int|string Team-ID oder HTML-Fehlermeldung.
	 */
	private static function resolve_team( array $atts ) {
		$identifier = isset( $atts['team'] ) ? (string) $atts['team'] : '';
		$team_id    = SVC_Teams::resolve( $identifier );

		if ( 0 === $team_id ) {
			return '<p class="svc-notice svc-notice-error">'
				. esc_html__( 'Swiss Volley Connector: Das angegebene Team wurde nicht gefunden. Bitte Team-ID oder Alias prüfen.', 'swiss-volley-connector' )
				. '</p>';
		}
		return $team_id;
	}

	/**
	 * Limit-Attribut absichern.
	 *
	 * @param mixed $value Rohwert.
	 * @param int   $default Standard.
	 * @return int
	 */
	/**
	 * 'league'-Attribut validieren.
	 *
	 * @param mixed  $value   Attributwert.
	 * @param string $default Standard ('meta' oder 'heading').
	 * @return string 'meta' | 'heading' | 'none'
	 */
	private static function league_display( $value ): string {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		if ( in_array( $value, array( 'meta', 'heading', 'none' ), true ) ) {
			return $value;
		}

		// Globaler Standard aus den Einstellungen (Swiss Volley → Einstellungen).
		$settings = wp_parse_args( get_option( 'svc_settings', array() ), SVC_Plugin::default_settings() );
		return in_array( $settings['league_display'], array( 'meta', 'heading' ), true ) ? $settings['league_display'] : 'heading';
	}

	/**
	 * 'group_by'-Attribut validieren.
	 *
	 * @param mixed $value Attributwert.
	 * @return string 'league' | 'team' | 'none'
	 */
	private static function group_by( $value ): string {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		if ( 'liga' === $value ) {
			$value = 'league';
		}
		return in_array( $value, array( 'league', 'team' ), true ) ? $value : 'none';
	}

	/**
	 * Renderer-Optionen aus den Attributen bauen.
	 *
	 * @param array $atts Shortcode-Attribute (mit 'league' und 'group_by').
	 * @return array
	 */
	private static function render_opts( array $atts ): array {
		$raw = isset( $atts['league'] ) && is_string( $atts['league'] ) ? strtolower( trim( $atts['league'] ) ) : '';
		return array(
			'league'          => self::league_display( $atts['league'] ?? '' ),
			'league_explicit' => in_array( $raw, array( 'meta', 'heading', 'none' ), true ),
			'group_by'        => self::group_by( $atts['group_by'] ?? '' ),
			'switcher'        => self::switcher_enabled( $atts['switcher'] ?? '' ),
		);
	}

	/**
	 * Interaktiven Gruppierungs-Umschalter aktivieren?
	 *
	 * Attribut switcher="1|0" übersteuert die Einstellung
	 * «Interaktive Gruppierung»; ohne Angabe gilt die Einstellung.
	 *
	 * @param mixed $value Attributwert.
	 * @return bool
	 */
	private static function switcher_enabled( $value ): bool {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		if ( in_array( $value, array( '1', 'true', 'yes', 'ja', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( '0', 'false', 'no', 'nein', 'off' ), true ) ) {
			return false;
		}

		$settings = wp_parse_args( get_option( 'svc_settings', array() ), SVC_Plugin::default_settings() );
		return ! empty( $settings['group_switcher'] );
	}

	private static function limit( $value, int $default = 5 ): int {
		$limit = (int) $value;
		if ( $limit < 0 ) {
			$limit = 0;
		}
		return min( $limit, 100 );
	}

	/**
	 * [swissvolley_games]
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function games( $atts ): string {
		SVC_Plugin::mark_assets_needed();
		$atts = shortcode_atts(
			array(
				'team'     => '',
				'limit'    => 5,
				'scope'    => 'upcoming',
				'league'   => '',
				'group_by' => '',
				'switcher' => '',
			),
			$atts,
			'swissvolley_games'
		);

		$team_id = self::resolve_team( $atts );
		if ( is_string( $team_id ) ) {
			return $team_id;
		}

		$scope = in_array( $atts['scope'], array( 'upcoming', 'played', 'all' ), true ) ? $atts['scope'] : 'upcoming';
		$games = SVC_Data::games_for_team( $team_id, $scope, self::limit( $atts['limit'] ) );

		if ( is_wp_error( $games ) ) {
			return SVC_Renderer::render_error( $games );
		}

		$empty = ( 'upcoming' === $scope )
			? __( 'Zurzeit sind keine kommenden Spiele angesetzt.', 'swiss-volley-connector' )
			: __( 'Zurzeit sind keine Spiele vorhanden.', 'swiss-volley-connector' );

		return SVC_Renderer::render_games( $games, $empty, self::render_opts( $atts ) );
	}

	/**
	 * [swissvolley_results]
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function results( $atts ): string {
		SVC_Plugin::mark_assets_needed();
		$atts = shortcode_atts(
			array(
				'team'     => '',
				'limit'    => 5,
				'league'   => '',
				'group_by' => '',
				'switcher' => '',
			),
			$atts,
			'swissvolley_results'
		);

		$team_id = self::resolve_team( $atts );
		if ( is_string( $team_id ) ) {
			return $team_id;
		}

		$games = SVC_Data::games_for_team( $team_id, 'played', self::limit( $atts['limit'] ) );

		if ( is_wp_error( $games ) ) {
			return SVC_Renderer::render_error( $games );
		}

		return SVC_Renderer::render_games( $games, __( 'Zurzeit liegen noch keine Resultate vor.', 'swiss-volley-connector' ), self::render_opts( $atts ) );
	}

	/**
	 * [swissvolley_ranking]
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function ranking( $atts ): string {
		SVC_Plugin::mark_assets_needed();
		$atts = shortcode_atts(
			array( 'team' => '' ),
			$atts,
			'swissvolley_ranking'
		);

		$team_id = self::resolve_team( $atts );
		if ( is_string( $team_id ) ) {
			return $team_id;
		}

		$groups = SVC_Data::ranking_for_team( $team_id );
		if ( is_wp_error( $groups ) ) {
			return SVC_Renderer::render_error( $groups );
		}

		return SVC_Renderer::render_ranking( $groups, $team_id );
	}

	/**
	 * [swissvolley_team] – kombinierte Ansicht.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function team( $atts ): string {
		SVC_Plugin::mark_assets_needed();
		$atts = shortcode_atts(
			array(
				'team'  => '',
				'limit' => 5,
			),
			$atts,
			'swissvolley_team'
		);

		$team_id = self::resolve_team( $atts );
		if ( is_string( $team_id ) ) {
			return $team_id;
		}

		$limit = self::limit( $atts['limit'] );

		$upcoming = SVC_Data::games_for_team( $team_id, 'upcoming', $limit );
		$played   = SVC_Data::games_for_team( $team_id, 'played', $limit );
		$ranking  = SVC_Data::ranking_for_team( $team_id );

		$html = '<div class="svc-team-view">';

		$html .= SVC_Renderer::render_heading( __( 'Nächste Spiele', 'swiss-volley-connector' ) );
		$html .= is_wp_error( $upcoming )
			? SVC_Renderer::render_error( $upcoming )
			: SVC_Renderer::render_games( $upcoming, __( 'Zurzeit sind keine kommenden Spiele angesetzt.', 'swiss-volley-connector' ) );

		$html .= SVC_Renderer::render_heading( __( 'Letzte Resultate', 'swiss-volley-connector' ) );
		$html .= is_wp_error( $played )
			? SVC_Renderer::render_error( $played )
			: SVC_Renderer::render_games( $played, __( 'Zurzeit liegen noch keine Resultate vor.', 'swiss-volley-connector' ) );

		$html .= SVC_Renderer::render_heading( __( 'Rangliste', 'swiss-volley-connector' ) );
		$html .= is_wp_error( $ranking )
			? SVC_Renderer::render_error( $ranking )
			: SVC_Renderer::render_ranking( $ranking, $team_id );

		$html .= '</div>';
		return $html;
	}

	/**
	 * [swissvolley_club_games] – nächste Spiele aller ausgewählten Teams.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function club_games( $atts ): string {
		SVC_Plugin::mark_assets_needed();
		$atts = shortcode_atts(
			array(
				'limit'    => 10,
				'league'   => '',
				'group_by' => '',
				'switcher' => '',
			),
			$atts,
			'swissvolley_club_games'
		);

		$games = SVC_Data::games_for_club( 'upcoming', self::limit( $atts['limit'], 10 ) );
		if ( is_wp_error( $games ) ) {
			return SVC_Renderer::render_error( $games );
		}

		return SVC_Renderer::render_games( $games, __( 'Zurzeit sind keine kommenden Spiele angesetzt.', 'swiss-volley-connector' ), self::render_opts( $atts ) );
	}

	/**
	 * [swissvolley_club_results] – letzte Resultate aller ausgewählten Teams.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function club_results( $atts ): string {
		SVC_Plugin::mark_assets_needed();
		$atts = shortcode_atts(
			array(
				'limit'    => 10,
				'league'   => '',
				'group_by' => '',
				'switcher' => '',
			),
			$atts,
			'swissvolley_club_results'
		);

		$games = SVC_Data::games_for_club( 'played', self::limit( $atts['limit'], 10 ) );
		if ( is_wp_error( $games ) ) {
			return SVC_Renderer::render_error( $games );
		}

		return SVC_Renderer::render_games( $games, __( 'Zurzeit liegen noch keine Resultate vor.', 'swiss-volley-connector' ), self::render_opts( $atts ) );
	}
}
