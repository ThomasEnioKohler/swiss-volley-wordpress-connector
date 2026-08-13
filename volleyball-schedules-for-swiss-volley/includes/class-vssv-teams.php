<?php
/**
 * Teamkonfiguration: Speicherung der aus Swiss Volley geladenen Teams,
 * Aliase und Auswahl für vereinsweite Ansichten.
 *
 * Struktur der Option 'vssv_teams' (Team-ID => Daten):
 * [
 *   12345 => [
 *     'team_id'   => 12345,
 *     'caption'   => 'Volley Pizol Herren 1',
 *     'league'    => 'Herren 2. Liga',
 *     'season'    => '2026/27',
 *     'league_id' => 678,
 *     'group_ids' => [ 910 ],
 *     'alias'        => 'herren-1',
 *     'in_club'      => true,  // in vereinsweiten Ansichten berücksichtigen
 *     'league_label' => '',    // eigene Liga-Bezeichnung; leer = API-Wert verwenden
 *     'name_label'   => '',    // eigener Teamname; leer = API-Wert verwenden
 *     'page_url'     => '',    // Link zur Teamseite; leer = kein Link
 *   ],
 * ]
 *
 * @package VolleyballSchedulesForSwissVolley
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VSSV_Teams
 */
class VSSV_Teams {

	const OPTION_KEY = 'vssv_teams';

	/**
	 * Alle konfigurierten Teams.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		$teams = get_option( self::OPTION_KEY, array() );
		return is_array( $teams ) ? $teams : array();
	}

	/**
	 * Teams speichern.
	 *
	 * @param array<int,array<string,mixed>> $teams Teams.
	 */
	public static function save( array $teams ): void {
		update_option( self::OPTION_KEY, $teams, false );
	}

	/**
	 * Aus der API abgeleitete Teams mit bestehender Konfiguration
	 * zusammenführen (Aliase und Auswahl bleiben erhalten).
	 *
	 * @param array<int,array<string,mixed>> $derived Von VSSV_Data::derive_teams gelieferte Teams.
	 * @return array<int,array<string,mixed>>
	 */
	public static function merge_derived( array $derived ): array {
		$existing = self::all();
		$merged   = array();

		foreach ( $derived as $tid => $team ) {
			$prev           = $existing[ $tid ] ?? array();
			$merged[ $tid ] = array(
				'team_id'   => (int) $tid,
				'caption'   => (string) ( $team['caption'] ?? '' ),
				'league'    => (string) ( $team['league'] ?? '' ),
				'season'    => (string) ( $team['season'] ?? '' ),
				'league_id' => (int) ( $team['league_id'] ?? 0 ),
				'group_ids' => array_map( 'intval', (array) ( $team['group_ids'] ?? array() ) ),
				'alias'     => (string) ( $prev['alias'] ?? '' ),
				'in_club'   => array_key_exists( 'in_club', $prev ) ? (bool) $prev['in_club'] : true,

				// Eigene, sprechende Bezeichnungen (bleiben beim Neuladen erhalten).
				'league_label' => (string) ( $prev['league_label'] ?? '' ),
				'name_label'   => (string) ( $prev['name_label'] ?? '' ),
				'page_url'     => (string) ( $prev['page_url'] ?? '' ),
			);
		}

		self::save( $merged );
		return $merged;
	}

	/**
	 * Team-IDs für vereinsweite Ansichten.
	 *
	 * @return int[]
	 */
	public static function enabled_team_ids(): array {
		$ids = array();
		foreach ( self::all() as $tid => $team ) {
			if ( ! empty( $team['in_club'] ) ) {
				$ids[] = (int) $tid;
			}
		}
		return $ids;
	}

	/**
	 * Alle bekannten Team-IDs (zur Erkennung eigener Teams im Frontend).
	 *
	 * @return int[]
	 */
	public static function known_team_ids(): array {
		return array_map( 'intval', array_keys( self::all() ) );
	}

	/**
	 * Team anhand von ID oder Alias auflösen.
	 *
	 * @param string $identifier Zahl (Team-ID) oder Alias wie 'herren-1'.
	 * @return int 0, wenn nicht auflösbar.
	 */
	public static function resolve( string $identifier ): int {
		$identifier = trim( $identifier );
		if ( '' === $identifier ) {
			return 0;
		}

		if ( ctype_digit( $identifier ) ) {
			return (int) $identifier;
		}

		$needle = sanitize_title( $identifier );
		foreach ( self::all() as $tid => $team ) {
			if ( isset( $team['alias'] ) && sanitize_title( (string) $team['alias'] ) === $needle ) {
				return (int) $tid;
			}
		}
		return 0;
	}

	/**
	 * Anzeigename eines Teams (für Blöcke/Fehlermeldungen).
	 *
	 * @param int $team_id Team-ID.
	 * @return string
	 */
	public static function caption( int $team_id ): string {
		$teams = self::all();
		return isset( $teams[ $team_id ]['caption'] ) ? (string) $teams[ $team_id ]['caption'] : (string) $team_id;
	}

	/**
	 * Eigene Liga-Bezeichnung eines Teams ('' = keine hinterlegt).
	 *
	 * @param int $team_id Team-ID.
	 * @return string
	 */
	public static function league_label( int $team_id ): string {
		$teams = self::all();
		return isset( $teams[ $team_id ]['league_label'] ) ? trim( (string) $teams[ $team_id ]['league_label'] ) : '';
	}

	/**
	 * Eigener Teamname eines Teams ('' = keiner hinterlegt).
	 *
	 * @param int $team_id Team-ID.
	 * @return string
	 */
	public static function name_label( int $team_id ): string {
		$teams = self::all();
		return isset( $teams[ $team_id ]['name_label'] ) ? trim( (string) $teams[ $team_id ]['name_label'] ) : '';
	}

	/**
	 * Anzeigename für alle Ausgaben: eigener Teamname, sonst API-Name.
	 *
	 * @param int $team_id Team-ID.
	 * @return string
	 */
	public static function display_name( int $team_id ): string {
		$label = self::name_label( $team_id );
		return '' !== $label ? $label : self::caption( $team_id );
	}

	/**
	 * Link zur Teamseite ('' = keiner hinterlegt).
	 *
	 * @param int $team_id Team-ID.
	 * @return string
	 */
	public static function page_url( int $team_id ): string {
		$teams = self::all();
		return isset( $teams[ $team_id ]['page_url'] ) ? trim( (string) $teams[ $team_id ]['page_url'] ) : '';
	}
}
