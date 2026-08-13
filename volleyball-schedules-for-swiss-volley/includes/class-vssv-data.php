<?php
/**
 * Daten-Schicht: verbindet API, Cache und Normalisierung.
 *
 * Rohdaten der Swiss-Volley-API werden hier in eine interne, stabile
 * Struktur überführt (Data Mapper). Das Frontend arbeitet ausschliesslich
 * mit dieser Struktur und ist damit weitgehend unabhängig vom genauen
 * Swiss-Volley-JSON-Format.
 *
 * Normalisiertes Spiel:
 * [
 *   'id'            => int,
 *   'timestamp'     => int|null,   // Unix-Timestamp (Spielbeginn)
 *   'date'          => string,     // 'Y-m-d' (Rohwert der API, Lokalzeit CH)
 *   'time'          => string,     // 'H:i'
 *   'home_team'     => string,
 *   'away_team'     => string,
 *   'home_team_id'  => int,
 *   'away_team_id'  => int,
 *   'home_club_id'  => string,
 *   'away_club_id'  => string,
 *   'home_sets'     => int|null,
 *   'away_sets'     => int|null,
 *   'set_results'   => array<int,array{home:int,away:int}>,
 *   'venue'         => string,
 *   'venue_city'    => string,
 *   'league'        => string,     // Liga/Wettbewerb (inkl. Gruppe, wo sinnvoll)
 *   'season'        => string,     // z. B. '2026/27'
 *   'season_year'   => int|null,   // Startjahr, z. B. 2026
 *   'status'        => string,     // 'played' | 'upcoming'
 *   'gender'        => string,
 *   'league_id'     => int,
 *   'group_id'      => int,
 *   'phase_id'      => int,
 *   'is_cup'        => bool,
 * ]
 *
 * @package VolleyballSchedulesForSwissVolley
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VSSV_Data
 */
class VSSV_Data {

	/**
	 * Zwischenspeicher pro Request.
	 *
	 * @var array<string,mixed>
	 */
	private static array $runtime = array();

	/**
	 * True, wenn im aktuellen Request auf Stale-Daten zurückgegriffen wurde.
	 *
	 * @var bool
	 */
	private static bool $served_stale = false;

	/**
	 * Cache-Dauer in Sekunden gemäss Einstellungen.
	 *
	 * @return int
	 */
	private static function cache_ttl(): int {
		$settings = get_option( 'vssv_settings', array() );
		$minutes  = (int) ( $settings['cache_minutes'] ?? 30 );
		$allowed  = array( 5, 15, 30, 60, 180, 360, 720, 1440 );
		if ( ! in_array( $minutes, $allowed, true ) ) {
			$minutes = 30;
		}
		return $minutes * MINUTE_IN_SECONDS;
	}

	/**
	 * Wurden in diesem Request veraltete (Stale-)Daten ausgeliefert?
	 *
	 * @return bool
	 */
	public static function served_stale(): bool {
		return self::$served_stale;
	}

	/**
	 * Rohdaten laden: Cache → API → Stale-Fallback.
	 *
	 * @param string $key 'games' oder 'rankings'.
	 * @return array|WP_Error
	 */
	private static function fetch( string $key ) {
		if ( isset( self::$runtime[ $key ] ) ) {
			return self::$runtime[ $key ];
		}

		$cached = VSSV_Cache::get( $key );
		if ( false !== $cached && is_array( $cached ) ) {
			self::$runtime[ $key ] = $cached;
			return $cached;
		}

		$api  = new VSSV_API();
		$data = ( 'rankings' === $key ) ? $api->get_rankings() : $api->get_games();

		if ( ! is_wp_error( $data ) ) {
			VSSV_Cache::set( $key, $data, self::cache_ttl() );
			self::$runtime[ $key ] = $data;
			return $data;
		}

		// API nicht erreichbar: zuletzt erfolgreich geladene Daten verwenden.
		$stale = VSSV_Cache::get_stale( $key );
		if ( null !== $stale && is_array( $stale['data'] ) ) {
			self::$served_stale    = true;
			self::$runtime[ $key ] = $stale['data'];
			return $stale['data'];
		}

		self::$runtime[ $key ] = $data; // WP_Error merken, um Mehrfachaufrufe zu vermeiden.
		return $data;
	}

	/**
	 * Alle Spiele als Rohdaten.
	 *
	 * @return array|WP_Error
	 */
	public static function raw_games() {
		return self::fetch( 'games' );
	}

	/**
	 * Alle Ranglisten als Rohdaten.
	 *
	 * @return array|WP_Error
	 */
	public static function raw_rankings() {
		return self::fetch( 'rankings' );
	}

	/* ---------------------------------------------------------------------
	 * Normalisierung
	 * ------------------------------------------------------------------- */

	/**
	 * Einzelnes Roh-Spiel in die interne Struktur überführen.
	 *
	 * Besonderheiten der API, die hier abgefangen werden:
	 *  - setResults / resultSummary sind leere Arrays [] statt Objekten,
	 *    solange kein Resultat vorliegt.
	 *  - playDate ist "YYYY-MM-DD HH:MM:SS" in Schweizer Lokalzeit.
	 *
	 * @param array $g Rohdaten eines Spiels.
	 * @return array Normalisiertes Spiel.
	 */
	public static function normalize_game( array $g ): array {
		$home = isset( $g['teams']['home'] ) && is_array( $g['teams']['home'] ) ? $g['teams']['home'] : array();
		$away = isset( $g['teams']['away'] ) && is_array( $g['teams']['away'] ) ? $g['teams']['away'] : array();
		$hall = isset( $g['hall'] ) && is_array( $g['hall'] ) ? $g['hall'] : array();

		// Datum/Zeit: API liefert Schweizer Lokalzeit ohne Zeitzonenangabe.
		$play_date = isset( $g['playDate'] ) ? (string) $g['playDate'] : '';
		$date      = '';
		$time      = '';
		$timestamp = null;
		if ( '' !== $play_date ) {
			$parts = explode( ' ', $play_date );
			$date  = $parts[0] ?? '';
			$time  = isset( $parts[1] ) ? substr( $parts[1], 0, 5 ) : '';
			try {
				$dt        = new DateTimeImmutable( $play_date, new DateTimeZone( 'Europe/Zurich' ) );
				$timestamp = $dt->getTimestamp();
			} catch ( Exception $e ) {
				$timestamp = null;
			}
		}

		// Satzresultate: Map "1".."5" => {home, away} oder leeres Array.
		$set_results = array();
		if ( isset( $g['setResults'] ) && is_array( $g['setResults'] ) ) {
			foreach ( $g['setResults'] as $set ) {
				if ( is_array( $set ) && isset( $set['home'], $set['away'] ) ) {
					$set_results[] = array(
						'home' => (int) $set['home'],
						'away' => (int) $set['away'],
					);
				}
			}
		}

		// Resultat-Zusammenfassung: {} mit Werten oder leeres Array.
		$home_sets = null;
		$away_sets = null;
		$winner    = '';
		if ( isset( $g['resultSummary'] ) && is_array( $g['resultSummary'] ) && isset( $g['resultSummary']['winner'] ) ) {
			$home_sets = (int) ( $g['resultSummary']['wonSetsHomeTeam'] ?? 0 );
			$away_sets = (int) ( $g['resultSummary']['wonSetsAwayTeam'] ?? 0 );
			$winner    = (string) $g['resultSummary']['winner'];
		}

		$played = ( '' !== $winner ) || ( null !== $home_sets && ( $home_sets + $away_sets ) > 0 );

		// Liga-/Wettbewerbsbezeichnung: Bei Meisterschaftsspielen trägt meist
		// die Gruppe den vollständigen Namen ("Herren 2. Liga"), bei
		// Cup-Spielen die Liga ("Mobiliar Volley Cup"). Kombination analog
		// offizieller Darstellung.
		$league_caption = isset( $g['league']['caption'] ) ? (string) $g['league']['caption'] : '';
		$group_caption  = isset( $g['group']['caption'] ) ? (string) $g['group']['caption'] : '';
		$phase_caption  = isset( $g['phase']['caption'] ) ? (string) $g['phase']['caption'] : '';
		$is_cup         = (bool) preg_match( '/cup|pokal|coupe|coppa/i', $league_caption );

		$league_label = $group_caption ? $group_caption : ( $phase_caption ? $phase_caption : $league_caption );
		if ( $is_cup && $league_caption && false === stripos( $league_label, 'cup' ) ) {
			$league_label = $group_caption ? $league_caption . ' – ' . $group_caption : $league_caption;
		}

		// Saison: league.season ist das Startjahr (z. B. 2026 = Saison 2026/27).
		$season_year = null;
		if ( isset( $g['league']['season'] ) && is_numeric( $g['league']['season'] ) ) {
			$season_year = (int) $g['league']['season'];
		} elseif ( '' !== $date ) {
			$y           = (int) substr( $date, 0, 4 );
			$m           = (int) substr( $date, 5, 2 );
			$season_year = ( $m < 8 ) ? $y - 1 : $y; // Saisonstart im Spätsommer.
		}
		$season = ( null !== $season_year )
			? $season_year . '/' . substr( (string) ( $season_year + 1 ), -2 )
			: '';

		// Spielhalle inkl. Ort.
		$venue      = isset( $hall['caption'] ) ? (string) $hall['caption'] : '';
		$venue_city = isset( $hall['city'] ) ? (string) $hall['city'] : '';

		return array(
			'id'           => (int) ( $g['gameId'] ?? 0 ),
			'timestamp'    => $timestamp,
			'date'         => $date,
			'time'         => $time,
			'home_team'    => (string) ( $home['caption'] ?? '' ),
			'away_team'    => (string) ( $away['caption'] ?? '' ),
			'home_team_id' => (int) ( $home['teamId'] ?? 0 ),
			'away_team_id' => (int) ( $away['teamId'] ?? 0 ),
			'home_club_id' => (string) ( $home['clubId'] ?? '' ),
			'away_club_id' => (string) ( $away['clubId'] ?? '' ),
			'home_sets'    => $played ? $home_sets : null,
			'away_sets'    => $played ? $away_sets : null,
			'set_results'  => $set_results,
			'venue'        => $venue,
			'venue_city'   => $venue_city,
			'league'       => $league_label,
			'season'       => $season,
			'season_year'  => $season_year,
			'status'       => $played ? 'played' : 'upcoming',
			'gender'       => (string) ( $g['gender'] ?? '' ),
			'league_id'    => (int) ( $g['league']['leagueId'] ?? 0 ),
			'group_id'     => (int) ( $g['group']['groupId'] ?? 0 ),
			'phase_id'     => (int) ( $g['phase']['phaseId'] ?? 0 ),
			'is_cup'       => $is_cup,
		);
	}

	/**
	 * Alle Spiele normalisiert, optional nach Saison gefiltert.
	 *
	 * @return array|WP_Error
	 */
	public static function games() {
		$raw = self::raw_games();
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		if ( isset( self::$runtime['games_normalized'] ) ) {
			return self::$runtime['games_normalized'];
		}

		$settings    = get_option( 'vssv_settings', array() );
		$season_year = isset( $settings['season_year'] ) && '' !== (string) $settings['season_year']
			? (int) $settings['season_year']
			: null;

		$games = array();
		foreach ( $raw as $g ) {
			if ( ! is_array( $g ) ) {
				continue;
			}
			$n = self::normalize_game( $g );
			if ( null !== $season_year && null !== $n['season_year'] && $n['season_year'] !== $season_year ) {
				continue;
			}
			$games[] = $n;
		}

		self::$runtime['games_normalized'] = $games;
		return $games;
	}

	/* ---------------------------------------------------------------------
	 * Ableitungen: Verein, Teams, Saisons
	 * ------------------------------------------------------------------- */

	/**
	 * Eigenen Verein aus den Spieldaten erkennen.
	 *
	 * Der API-Key ist club-gebunden: In jedem gelieferten Spiel ist mindestens
	 * ein eigenes Team beteiligt. Der clubId-Wert mit der höchsten Abdeckung
	 * über alle Spiele ist der eigene Verein.
	 *
	 * @param array $raw_games Rohdaten des /indoor/games-Endpunkts.
	 * @return array{id:string,name:string}|null
	 */
	public static function detect_own_club( array $raw_games ): ?array {
		$counts = array();
		$names  = array();

		foreach ( $raw_games as $g ) {
			if ( ! is_array( $g ) || ! isset( $g['teams'] ) ) {
				continue;
			}
			$sides = array();
			foreach ( array( 'home', 'away' ) as $side ) {
				$t = $g['teams'][ $side ] ?? null;
				if ( is_array( $t ) && '' !== (string) ( $t['clubId'] ?? '' ) ) {
					$cid           = (string) $t['clubId'];
					$sides[ $cid ] = true;
					if ( ! isset( $names[ $cid ] ) && '' !== (string) ( $t['clubCaption'] ?? '' ) ) {
						$names[ $cid ] = (string) $t['clubCaption'];
					}
				}
			}
			foreach ( array_keys( $sides ) as $cid ) {
				$counts[ $cid ] = ( $counts[ $cid ] ?? 0 ) + 1;
			}
		}

		if ( empty( $counts ) ) {
			return null;
		}

		arsort( $counts );
		$club_id = (string) array_key_first( $counts );

		return array(
			'id'   => $club_id,
			'name' => $names[ $club_id ] ?? $club_id,
		);
	}

	/**
	 * Liste aller in den Daten vorkommenden Vereine (für Admin-Auswahl),
	 * sortiert nach Häufigkeit (eigener Verein zuoberst).
	 *
	 * @param array $raw_games Rohdaten.
	 * @return array<int,array{id:string,name:string,count:int}>
	 */
	public static function list_clubs( array $raw_games ): array {
		$counts = array();
		$names  = array();

		foreach ( $raw_games as $g ) {
			if ( ! is_array( $g ) || ! isset( $g['teams'] ) ) {
				continue;
			}
			foreach ( array( 'home', 'away' ) as $side ) {
				$t = $g['teams'][ $side ] ?? null;
				if ( ! is_array( $t ) ) {
					continue;
				}
				$cid = (string) ( $t['clubId'] ?? '' );
				if ( '' === $cid ) {
					continue;
				}
				$counts[ $cid ] = ( $counts[ $cid ] ?? 0 ) + 1;
				if ( ! isset( $names[ $cid ] ) && '' !== (string) ( $t['clubCaption'] ?? '' ) ) {
					$names[ $cid ] = (string) $t['clubCaption'];
				}
			}
		}

		arsort( $counts );

		$clubs = array();
		foreach ( $counts as $cid => $count ) {
			$clubs[] = array(
				'id'    => (string) $cid,
				'name'  => $names[ $cid ] ?? (string) $cid,
				'count' => (int) $count,
			);
		}
		return $clubs;
	}

	/**
	 * Teams eines Vereins aus den Spieldaten ableiten (nicht hart codiert).
	 *
	 * @param array  $raw_games Rohdaten.
	 * @param string $club_id   Club-ID.
	 * @return array<int,array<string,mixed>> Team-ID => Teamdaten.
	 */
	public static function derive_teams( array $raw_games, string $club_id ): array {
		$teams = array();

		foreach ( $raw_games as $g ) {
			if ( ! is_array( $g ) || ! isset( $g['teams'] ) ) {
				continue;
			}
			$n = self::normalize_game( $g );

			foreach ( array( 'home', 'away' ) as $side ) {
				$t = $g['teams'][ $side ] ?? null;
				if ( ! is_array( $t ) || (string) ( $t['clubId'] ?? '' ) !== $club_id ) {
					continue;
				}
				$tid = (int) ( $t['teamId'] ?? 0 );
				if ( 0 === $tid ) {
					continue;
				}

				if ( ! isset( $teams[ $tid ] ) ) {
					$teams[ $tid ] = array(
						'team_id'     => $tid,
						'caption'     => (string) ( $t['caption'] ?? '' ),
						'league'      => '',
						'season'      => '',
						'season_year' => null,
						'league_id'   => 0,
						'group_ids'   => array(),
						'phase_ids'   => array(),
					);
				}

				// Liga/Gruppen nur aus Meisterschaftsspielen übernehmen,
				// damit Cup-Wettbewerbe die Liga-Zuordnung nicht überschreiben.
				if ( ! $n['is_cup'] ) {
					if ( '' === $teams[ $tid ]['league'] && '' !== $n['league'] ) {
						$teams[ $tid ]['league']    = $n['league'];
						$teams[ $tid ]['league_id'] = $n['league_id'];
					}
					if ( $n['group_id'] && ! in_array( $n['group_id'], $teams[ $tid ]['group_ids'], true ) ) {
						$teams[ $tid ]['group_ids'][] = $n['group_id'];
					}
					if ( $n['phase_id'] && ! in_array( $n['phase_id'], $teams[ $tid ]['phase_ids'], true ) ) {
						$teams[ $tid ]['phase_ids'][] = $n['phase_id'];
					}
				}

				if ( null !== $n['season_year'] && ( null === $teams[ $tid ]['season_year'] || $n['season_year'] > $teams[ $tid ]['season_year'] ) ) {
					$teams[ $tid ]['season_year'] = $n['season_year'];
					$teams[ $tid ]['season']      = $n['season'];
				}
			}
		}

		uasort(
			$teams,
			static function ( $a, $b ) {
				return strnatcasecmp( (string) $a['caption'], (string) $b['caption'] );
			}
		);

		return $teams;
	}

	/**
	 * In den Daten verfügbare Saisons (Startjahre) auflisten.
	 *
	 * @param array $raw_games Rohdaten.
	 * @return array<int,string> Startjahr => Label '2026/27'.
	 */
	public static function list_seasons( array $raw_games ): array {
		$seasons = array();
		foreach ( $raw_games as $g ) {
			if ( ! is_array( $g ) ) {
				continue;
			}
			$n = self::normalize_game( $g );
			if ( null !== $n['season_year'] ) {
				$seasons[ $n['season_year'] ] = $n['season'];
			}
		}
		krsort( $seasons );
		return $seasons;
	}

	/* ---------------------------------------------------------------------
	 * Abfragen für Shortcodes/Blöcke
	 * ------------------------------------------------------------------- */

	/**
	 * Spiele eines Teams.
	 *
	 * @param int    $team_id Team-ID.
	 * @param string $scope   'upcoming' | 'played' | 'all'.
	 * @param int    $limit   Maximale Anzahl (0 = alle).
	 * @return array|WP_Error
	 */
	public static function games_for_team( int $team_id, string $scope = 'upcoming', int $limit = 0 ) {
		$games = self::games();
		if ( is_wp_error( $games ) ) {
			return $games;
		}

		$now      = time();
		$filtered = array();

		foreach ( $games as $g ) {
			if ( $g['home_team_id'] !== $team_id && $g['away_team_id'] !== $team_id ) {
				continue;
			}
			$is_played   = ( 'played' === $g['status'] );
			$is_upcoming = ! $is_played && ( null === $g['timestamp'] || $g['timestamp'] >= $now - 6 * HOUR_IN_SECONDS );

			if ( 'upcoming' === $scope && ! $is_upcoming ) {
				continue;
			}
			if ( 'played' === $scope && ! $is_played ) {
				continue;
			}
			$filtered[] = $g;
		}

		self::sort_games( $filtered, 'played' === $scope ? 'desc' : 'asc' );

		if ( $limit > 0 ) {
			$filtered = array_slice( $filtered, 0, $limit );
		}
		return self::apply_league_labels( $filtered );
	}

	/**
	 * Vereinsweite Spiele über alle aktivierten Teams.
	 *
	 * @param string $scope 'upcoming' | 'played'.
	 * @param int    $limit Maximale Anzahl.
	 * @return array|WP_Error
	 */
	public static function games_for_club( string $scope = 'upcoming', int $limit = 10 ) {
		$games = self::games();
		if ( is_wp_error( $games ) ) {
			return $games;
		}

		$team_ids = VSSV_Teams::enabled_team_ids();
		$now      = time();
		$filtered = array();
		$seen     = array();

		foreach ( $games as $g ) {
			if ( ! in_array( $g['home_team_id'], $team_ids, true ) && ! in_array( $g['away_team_id'], $team_ids, true ) ) {
				continue;
			}
			if ( isset( $seen[ $g['id'] ] ) ) {
				continue; // Vereinsderby nur einmal aufführen.
			}
			$seen[ $g['id'] ] = true;

			$is_played = ( 'played' === $g['status'] );
			if ( 'upcoming' === $scope && ( $is_played || ( null !== $g['timestamp'] && $g['timestamp'] < $now - 6 * HOUR_IN_SECONDS ) ) ) {
				continue;
			}
			if ( 'played' === $scope && ! $is_played ) {
				continue;
			}
			$filtered[] = $g;
		}

		self::sort_games( $filtered, 'played' === $scope ? 'desc' : 'asc' );

		if ( $limit > 0 ) {
			$filtered = array_slice( $filtered, 0, $limit );
		}
		return self::apply_league_labels( $filtered );
	}

	/**
	 * Wendet die im Backend hinterlegten eigenen Bezeichnungen an:
	 *
	 * - Eigener Teamname: ersetzt den API-Namen des eigenen Teams
	 *   (Heim wie Auswärts; Gegnernamen bleiben unverändert).
	 * - Eigene Liga-Bezeichnung: ersetzt den Swiss-Volley-Kurzcode – nur
	 *   bei Meisterschaftsspielen (Cup-Namen wie «Mobiliar Volley Cup»
	 *   bleiben unverändert). Bei Vereinsderbys gewinnt das Heimteam.
	 *
	 * Leere Felder lassen den jeweiligen API-Wert unangetastet.
	 *
	 * @param array<int,array<string,mixed>> $games Normalisierte Spiele.
	 * @return array<int,array<string,mixed>>
	 */
	private static function apply_league_labels( array $games ): array {
		foreach ( $games as &$g ) {
			$home_name = VSSV_Teams::name_label( (int) $g['home_team_id'] );
			if ( '' !== $home_name ) {
				$g['home_team'] = $home_name;
			}
			$away_name = VSSV_Teams::name_label( (int) $g['away_team_id'] );
			if ( '' !== $away_name ) {
				$g['away_team'] = $away_name;
			}

			if ( ! empty( $g['is_cup'] ) ) {
				continue;
			}

			$label = VSSV_Teams::league_label( (int) $g['home_team_id'] );
			if ( '' === $label ) {
				$label = VSSV_Teams::league_label( (int) $g['away_team_id'] );
			}
			if ( '' !== $label ) {
				$g['league'] = $label;
			}
		}
		unset( $g );

		return $games;
	}

	/**
	 * Rangliste(n) eines Teams gemäss offizieller Swiss-Volley-Reihenfolge.
	 *
	 * Die Zuordnung erfolgt über die Gruppen-IDs der Meisterschaftsspiele
	 * des Teams. Es findet keine eigene Berechnung statt.
	 *
	 * @param int $team_id Team-ID.
	 * @return array|WP_Error Array von Gruppen: {league_id, group_id, rows: [...]}.
	 */
	public static function ranking_for_team( int $team_id ) {
		$rankings = self::raw_rankings();
		if ( is_wp_error( $rankings ) ) {
			return $rankings;
		}

		// Gruppen des Teams aus dessen Spielen ermitteln (ohne Cup).
		$games = self::games();
		if ( is_wp_error( $games ) ) {
			return $games;
		}

		$group_ids = array();
		foreach ( $games as $g ) {
			if ( $g['is_cup'] || ! $g['group_id'] ) {
				continue;
			}
			if ( $g['home_team_id'] === $team_id || $g['away_team_id'] === $team_id ) {
				$group_ids[ $g['group_id'] ] = true;
			}
		}

		$result = array();
		foreach ( $rankings as $group ) {
			if ( ! is_array( $group ) || ! isset( $group['groupId'], $group['ranking'] ) ) {
				continue;
			}
			if ( ! isset( $group_ids[ (int) $group['groupId'] ] ) ) {
				continue;
			}
			// Enthält die Gruppe das Team überhaupt? (Sicherheitsnetz bei Phasenwechseln.)
			$rows = self::normalize_ranking_rows( (array) $group['ranking'] );
			if ( empty( $rows ) ) {
				continue;
			}
			$result[] = array(
				'league_id' => (int) ( $group['leagueId'] ?? 0 ),
				'phase_id'  => (int) ( $group['phaseId'] ?? 0 ),
				'group_id'  => (int) $group['groupId'],
				'rows'      => $rows,
			);
		}

		return $result;
	}

	/**
	 * Ranglisten-Zeilen normalisieren (Reihenfolge der API beibehalten).
	 *
	 * @param array $rows Rohzeilen.
	 * @return array<int,array<string,int|string|bool>>
	 */
	private static function normalize_ranking_rows( array $rows ): array {
		$out = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) || ! isset( $r['teamCaption'] ) ) {
				continue;
			}

			// Eigener Teamname (falls hinterlegt) statt API-Name.
			$tid  = (int) ( $r['teamId'] ?? 0 );
			$name = $tid ? VSSV_Teams::name_label( $tid ) : '';

			$out[] = array(
				'rank'      => (int) ( $r['rank'] ?? 0 ),
				'team_id'   => $tid,
				'team'      => '' !== $name ? $name : (string) $r['teamCaption'],
				'games'     => (int) ( $r['games'] ?? 0 ),
				'points'    => (int) ( $r['points'] ?? 0 ),
				'wins'      => (int) ( $r['wins'] ?? 0 ),
				'defeats'   => (int) ( $r['defeats'] ?? 0 ),
				'sets_won'  => (int) ( $r['setsWon'] ?? 0 ),
				'sets_lost' => (int) ( $r['setsLost'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Spiele sortieren.
	 *
	 * @param array  $games     Referenz auf Spieleliste.
	 * @param string $direction 'asc' | 'desc'.
	 */
	private static function sort_games( array &$games, string $direction = 'asc' ): void {
		usort(
			$games,
			static function ( $a, $b ) use ( $direction ) {
				$ta = $a['timestamp'] ?? PHP_INT_MAX;
				$tb = $b['timestamp'] ?? PHP_INT_MAX;
				return ( 'desc' === $direction ) ? $tb <=> $ta : $ta <=> $tb;
			}
		);
	}
}
