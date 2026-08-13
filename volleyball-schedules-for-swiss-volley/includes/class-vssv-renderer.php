<?php
/**
 * Renderer: erzeugt das Frontend-HTML.
 *
 * Alle Daten – auch jene der Swiss-Volley-API – gelten als nicht
 * vertrauenswürdig und werden kontextabhängig escaped.
 *
 * Datum/Uhrzeit werden über wp_date() in der in WordPress konfigurierten
 * Zeitzone ausgegeben (keine feste UTC+1/UTC+2-Logik).
 *
 * @package VolleyballSchedulesForSwissVolley
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VSSV_Renderer
 */
class VSSV_Renderer {

	/**
	 * Datumsformat (über Filter 'vssv_date_format' anpassbar).
	 *
	 * @return string
	 */
	private static function date_format(): string {
		/**
		 * Filter: Datumsformat der Spielausgabe.
		 *
		 * @param string $format PHP-Datumsformat.
		 */
		return (string) apply_filters( 'vssv_date_format', 'D, d.m.Y' );
	}

	/**
	 * Zeitformat (über Filter 'vssv_time_format' anpassbar,
	 * Standard aus den WordPress-Einstellungen).
	 *
	 * @return string
	 */
	private static function time_format(): string {
		$wp_format = (string) get_option( 'time_format', 'H:i' );
		/**
		 * Filter: Zeitformat der Spielausgabe.
		 *
		 * @param string $format PHP-Zeitformat.
		 */
		return (string) apply_filters( 'vssv_time_format', $wp_format ? $wp_format : 'H:i' );
	}

	/**
	 * Prüft, ob ein Team zum eigenen Verein gehört (für Hervorhebung).
	 *
	 * @param array  $game Normalisiertes Spiel.
	 * @param string $side 'home' | 'away'.
	 * @return bool
	 */
	/**
	 * Teamname im Spielkopf: als Link, wenn für das Team eine Seite
	 * hinterlegt ist (Swiss Volley → Teams → Team-Link), sonst als Span.
	 * Gegnerteams sind nie konfiguriert und bleiben damit unverlinkt.
	 *
	 * @param string $name    Anzeigename.
	 * @param int    $team_id Team-ID.
	 * @param string $classes CSS-Klassen.
	 * @return string
	 */
	private static function team_name_html( string $name, int $team_id, string $classes ): string {
		$url = $team_id ? VSSV_Teams::page_url( $team_id ) : '';
		if ( '' !== $url ) {
			return '<a class="' . esc_attr( $classes . ' vssv-team-link' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
		}
		return '<span class="' . esc_attr( $classes ) . '">' . esc_html( $name ) . '</span>';
	}

	/**
	 * Prüft, ob die angegebene Spielseite ein eigenes Team ist.
	 *
	 * @param array  $game Normalisiertes Spiel.
	 * @param string $side 'home' | 'away'.
	 * @return bool
	 */
	private static function is_own( array $game, string $side ): bool {
		$settings = get_option( 'vssv_settings', array() );
		if ( empty( $settings['highlight_own'] ) ) {
			return false;
		}
		$club_id = (string) ( $settings['club_id'] ?? '' );
		if ( '' === $club_id ) {
			return false;
		}
		return ( $game[ $side . '_club_id' ] ?? '' ) === $club_id;
	}

	/**
	 * Hinweis, wenn veraltete Daten ausgeliefert werden.
	 *
	 * @return string
	 */
	private static function stale_notice(): string {
		if ( ! VSSV_Data::served_stale() ) {
			return '';
		}
		return '<p class="vssv-notice vssv-notice-stale">'
			. esc_html__( 'The game data could not be updated at this time. Showing the last known state.', 'volleyball-schedules-for-swiss-volley' )
			. '</p>';
	}

	/**
	 * Fehlermeldung für Besucher (ohne technische Details).
	 * Administratoren sehen im Debug-Modus die konkrete Ursache.
	 *
	 * @param WP_Error $error Fehler.
	 * @return string
	 */
	public static function render_error( WP_Error $error ): string {
		$html = '<p class="vssv-notice vssv-notice-error">'
			. esc_html__( 'The game data could not be loaded at this time.', 'volleyball-schedules-for-swiss-volley' )
			. '</p>';

		if ( VSSV_Logger::enabled() && current_user_can( 'manage_options' ) ) {
			$html .= '<p class="vssv-notice vssv-notice-debug">'
				. esc_html__( 'Debug (visible to administrators only):', 'volleyball-schedules-for-swiss-volley' ) . ' '
				. esc_html( $error->get_error_message() )
				. '</p>';
		}
		return $html;
	}

	/**
	 * Spielliste rendern.
	 *
	 * @param array  $games         Normalisierte Spiele.
	 * @param string $empty_message Text bei leerer Liste.
	 * @param array  $opts          Optionen:
	 *                              'league' => 'meta'    Liga in der Meta-Zeile (Standard),
	 *                                          'heading' Liga als Überschrift über dem Spiel,
	 *                                          'none'    Liga nicht anzeigen.
	 * @return string
	 */
	public static function render_games( array $games, string $empty_message = '', array $opts = array() ): string {
		$league_display = isset( $opts['league'] ) && in_array( $opts['league'], array( 'meta', 'heading', 'none' ), true )
			? $opts['league']
			: 'meta';

		$group_by = isset( $opts['group_by'] ) && in_array( $opts['group_by'], array( 'league', 'team' ), true )
			? $opts['group_by']
			: 'none';

		// Interaktiver Umschalter für Besucher: flache Liste mit Daten-
		// Attributen ausgeben; das Frontend-Skript gruppiert clientseitig.
		if ( ! empty( $opts['switcher'] ) && ! empty( $games ) ) {
			$own_ids     = VSSV_Teams::known_team_ids();
			$league_dims = array();
			$team_dims   = array();
			foreach ( $games as $g ) {
				if ( '' !== (string) $g['league'] ) {
					$league_dims[ (string) $g['league'] ] = true;
				}
				$own_tid = in_array( (int) $g['home_team_id'], $own_ids, true )
					? (int) $g['home_team_id']
					: ( in_array( (int) $g['away_team_id'], $own_ids, true ) ? (int) $g['away_team_id'] : 0 );
				if ( $own_tid ) {
					$team_dims[ $own_tid ] = true;
				}
			}
			$has_league = count( $league_dims ) > 1;
			$has_team   = count( $team_dims ) > 1;

			// Nur sinnvoll, wenn es überhaupt etwas zu gruppieren gibt.
			if ( $has_league || $has_team ) {
				VSSV_Plugin::mark_switcher_needed();

				$initial = $group_by;
				if ( ( 'league' === $initial && ! $has_league ) || ( 'team' === $initial && ! $has_team ) ) {
					$initial = 'none';
				}

				$html  = self::stale_notice();
				$html .= '<div class="vssv-switchable" data-vssv-initial="' . esc_attr( $initial ) . '">';

				$html .= '<div class="vssv-switcher" role="group" aria-label="' . esc_attr__( 'Group games', 'volleyball-schedules-for-swiss-volley' ) . '">';
				$html .= '<button type="button" class="vssv-switch" data-vssv-group="none">' . esc_html__( 'Chronological', 'volleyball-schedules-for-swiss-volley' ) . '</button>';
				if ( $has_league ) {
					$html .= '<button type="button" class="vssv-switch" data-vssv-group="league">' . esc_html__( 'By league', 'volleyball-schedules-for-swiss-volley' ) . '</button>';
				}
				if ( $has_team ) {
					$html .= '<button type="button" class="vssv-switch" data-vssv-group="team">' . esc_html__( 'By team', 'volleyball-schedules-for-swiss-volley' ) . '</button>';
				}
				$html .= '</div>';

				$html .= self::render_games_body( $games, $empty_message, $league_display, true );
				$html .= '</div>';
				return $html;
			}
		}

		// Gruppierte Ausgabe: Gruppen in der Reihenfolge ihres ersten Spiels
		// (Sortierung der Spiele bleibt unverändert), je Gruppe eine Überschrift.
		if ( 'none' !== $group_by && ! empty( $games ) ) {
			$own_ids = VSSV_Teams::known_team_ids();
			$groups  = array();

			foreach ( $games as $g ) {
				if ( 'team' === $group_by ) {
					$own_tid = in_array( (int) $g['home_team_id'], $own_ids, true )
						? (int) $g['home_team_id']
						: ( in_array( (int) $g['away_team_id'], $own_ids, true ) ? (int) $g['away_team_id'] : 0 );
					$key     = 'team-' . $own_tid;
					$label   = $own_tid ? VSSV_Teams::display_name( $own_tid ) : __( 'More games', 'volleyball-schedules-for-swiss-volley' );
				} else {
					$label = (string) $g['league'];
					if ( '' === $label ) {
						$label = __( 'More games', 'volleyball-schedules-for-swiss-volley' );
					}
					$key = 'league-' . $label;
				}

				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'label' => $label,
						'games' => array(),
					);
				}
				$groups[ $key ]['games'][] = $g;
			}

			/**
			 * HTML-Tag für die Gruppen-Überschrift (Standard 'h3').
			 *
			 * @param string $tag Erlaubt: h1–h6, div, span.
			 */
			$group_tag = apply_filters( 'vssv_group_heading_tag', 'h3' );
			if ( ! in_array( $group_tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' ), true ) ) {
				$group_tag = 'h3';
			}

			// Bei Gruppierung nach Liga wäre die Liga-Überschrift pro Spiel
			// redundant; ohne ausdrücklichen Wunsch wird sie ausgeblendet.
			$inner_opts             = $opts;
			$inner_opts['group_by'] = 'none';
			if ( 'league' === $group_by && empty( $opts['league_explicit'] ) ) {
				$inner_opts['league'] = 'none';
			}

			$html = self::stale_notice() . '<div class="vssv-game-groups">';
			foreach ( $groups as $group ) {
				$html .= '<section class="vssv-game-group">';
				$html .= '<' . $group_tag . ' class="vssv-group-heading">' . esc_html( $group['label'] ) . '</' . $group_tag . '>';
				$html .= self::render_games_plain( $group['games'], $inner_opts );
				$html .= '</section>';
			}
			return $html . '</div>';
		}

		return self::stale_notice() . self::render_games_body( $games, $empty_message, $league_display );
	}

	/**
	 * Kartenliste ohne Stale-Hinweis (für Gruppen).
	 *
	 * @param array $games Spiele.
	 * @param array $opts  Optionen wie render_games (ohne group_by).
	 * @return string
	 */
	private static function render_games_plain( array $games, array $opts ): string {
		$league_display = isset( $opts['league'] ) && in_array( $opts['league'], array( 'meta', 'heading', 'none' ), true )
			? $opts['league']
			: 'meta';
		return self::render_games_body( $games, '', $league_display );
	}

	/**
	 * Eigentliche Kartenausgabe.
	 *
	 * @param array  $games          Spiele.
	 * @param string $empty_message  Text bei leerer Liste.
	 * @param string $league_display 'meta' | 'heading' | 'none'.
	 * @param bool   $data_attrs     Daten-Attribute für den clientseitigen Umschalter ausgeben.
	 * @return string
	 */
	private static function render_games_body( array $games, string $empty_message, string $league_display, bool $data_attrs = false ): string {

		/**
		 * HTML-Tag für die Liga-Überschrift pro Spiel (Standard 'h3').
		 *
		 * @param string $tag Erlaubt: h1–h6, div, span.
		 */
		$heading_tag = apply_filters( 'vssv_game_league_heading_tag', 'h3' );
		if ( ! in_array( $heading_tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' ), true ) ) {
			$heading_tag = 'h3';
		}

		$html = '';

		if ( empty( $games ) ) {
			$empty_message = $empty_message ? $empty_message : __( 'No games are currently available.', 'volleyball-schedules-for-swiss-volley' );
			return $html . '<p class="vssv-empty">' . esc_html( $empty_message ) . '</p>';
		}

		$html .= '<div class="vssv-games">';

		foreach ( $games as $g ) {
			$played = ( 'played' === $g['status'] );

			$home_classes = 'vssv-team vssv-team-home' . ( self::is_own( $g, 'home' ) ? ' vssv-own-team' : '' );
			$away_classes = 'vssv-team vssv-team-away' . ( self::is_own( $g, 'away' ) ? ' vssv-own-team' : '' );

			$date_label = '';
			$time_label = '';
			if ( null !== $g['timestamp'] ) {
				$date_label = wp_date( self::date_format(), $g['timestamp'] );
				$time_label = wp_date( self::time_format(), $g['timestamp'] );
			} elseif ( '' !== $g['date'] ) {
				$date_label = $g['date'];
				$time_label = $g['time'];
			}

			$venue = $g['venue'];
			if ( $venue && $g['venue_city'] && false === stripos( $venue, $g['venue_city'] ) ) {
				$venue .= ', ' . $g['venue_city'];
			}

			$attrs = '';
			if ( $data_attrs ) {
				$own_ids = VSSV_Teams::known_team_ids();
				$own_tid = in_array( (int) $g['home_team_id'], $own_ids, true )
					? (int) $g['home_team_id']
					: ( in_array( (int) $g['away_team_id'], $own_ids, true ) ? (int) $g['away_team_id'] : 0 );
				$attrs   = ' data-vssv-league="' . esc_attr( (string) $g['league'] ) . '"';
				$attrs  .= ' data-vssv-team="' . esc_attr( $own_tid ? VSSV_Teams::display_name( $own_tid ) : '' ) . '"';
			}

			$html .= '<article class="vssv-game vssv-game-' . esc_attr( $g['status'] ) . '"' . $attrs . '>';

			if ( 'heading' === $league_display && $g['league'] ) {
				$html .= '<' . $heading_tag . ' class="vssv-game-league">' . esc_html( $g['league'] ) . '</' . $heading_tag . '>';
			}

			$html .= '<div class="vssv-when">';
			$html .= '<span class="vssv-date">' . esc_html( $date_label ) . '</span>';
			if ( $time_label ) {
				$html .= '<span class="vssv-time">' . esc_html( $time_label ) . '</span>';
			}
			$html .= '</div>';

			$html .= '<div class="vssv-matchup">';
			$html .= self::team_name_html( $g['home_team'], (int) $g['home_team_id'], $home_classes );
			$html .= '<span class="vssv-vs" aria-hidden="true">' . esc_html_x( 'vs.', 'Home/away team separator', 'volleyball-schedules-for-swiss-volley' ) . '</span>';
			$html .= self::team_name_html( $g['away_team'], (int) $g['away_team_id'], $away_classes );
			$html .= '</div>';

			if ( $played && null !== $g['home_sets'] ) {
				$html .= '<div class="vssv-result">';
				$html .= '<span class="vssv-result-sets">' . esc_html( $g['home_sets'] . ' : ' . $g['away_sets'] ) . '</span>';
				if ( ! empty( $g['set_results'] ) ) {
					$sets = array();
					foreach ( $g['set_results'] as $set ) {
						$sets[] = $set['home'] . ':' . $set['away'];
					}
					$html .= '<span class="vssv-result-detail">' . esc_html( implode( ' · ', $sets ) ) . '</span>';
				}
				$html .= '</div>';
			} else {
				$html .= '<div class="vssv-result vssv-result-pending" aria-hidden="true">–</div>';
			}

			$html .= '<div class="vssv-meta">';
			if ( $venue ) {
				$html .= '<span class="vssv-location">' . esc_html( $venue ) . '</span>';
			}
			if ( 'meta' === $league_display && $g['league'] ) {
				$html .= '<span class="vssv-league">' . esc_html( $g['league'] ) . '</span>';
			}
			$html .= '</div>';

			$html .= '</article>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Rangliste(n) rendern.
	 *
	 * @param array $groups Gruppen aus VSSV_Data::ranking_for_team().
	 * @param int   $team_id Team-ID (für Hervorhebung der eigenen Zeile).
	 * @return string
	 */
	public static function render_ranking( array $groups, int $team_id = 0 ): string {
		$html = self::stale_notice();

		if ( empty( $groups ) ) {
			return $html . '<p class="vssv-empty">' . esc_html__( 'No standings are currently available.', 'volleyball-schedules-for-swiss-volley' ) . '</p>';
		}

		$settings  = get_option( 'vssv_settings', array() );
		$highlight = ! empty( $settings['highlight_own'] );
		$own_ids   = $highlight ? VSSV_Teams::known_team_ids() : array();

		foreach ( $groups as $group ) {
			$html .= '<div class="vssv-ranking-wrap">';
			$html .= '<table class="vssv-ranking">';
			$html .= '<thead><tr>';
			$html .= '<th scope="col" class="vssv-col-rank">' . esc_html_x( 'Rank', 'Standings column', 'volleyball-schedules-for-swiss-volley' ) . '</th>';
			$html .= '<th scope="col" class="vssv-col-team">' . esc_html__( 'Team', 'volleyball-schedules-for-swiss-volley' ) . '</th>';
			$html .= '<th scope="col" class="vssv-col-num">' . esc_html_x( 'P', 'Abbreviation for games played', 'volleyball-schedules-for-swiss-volley' ) . '</th>';
			$html .= '<th scope="col" class="vssv-col-num">' . esc_html_x( 'W', 'Abbreviation for wins', 'volleyball-schedules-for-swiss-volley' ) . '</th>';
			$html .= '<th scope="col" class="vssv-col-num">' . esc_html_x( 'L', 'Abbreviation for losses', 'volleyball-schedules-for-swiss-volley' ) . '</th>';
			$html .= '<th scope="col" class="vssv-col-num vssv-col-sets">' . esc_html_x( 'Sets', 'Standings column', 'volleyball-schedules-for-swiss-volley' ) . '</th>';
			$html .= '<th scope="col" class="vssv-col-num">' . esc_html_x( 'Pts', 'Abbreviation for points', 'volleyball-schedules-for-swiss-volley' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $group['rows'] as $row ) {
				$is_this_team = ( $team_id && $row['team_id'] === $team_id );
				$is_own_club  = $highlight && in_array( $row['team_id'], $own_ids, true );

				$classes = 'vssv-ranking-row';
				if ( $is_this_team || $is_own_club ) {
					$classes .= ' vssv-own-team';
				}

				$html   .= '<tr class="' . esc_attr( $classes ) . '">';
				$html   .= '<td class="vssv-col-rank">' . esc_html( (string) $row['rank'] ) . '</td>';
				$row_url = VSSV_Teams::page_url( (int) $row['team_id'] );
				if ( '' !== $row_url ) {
					$html .= '<td class="vssv-col-team"><a class="vssv-team-link" href="' . esc_url( $row_url ) . '">' . esc_html( $row['team'] ) . '</a></td>';
				} else {
					$html .= '<td class="vssv-col-team">' . esc_html( $row['team'] ) . '</td>';
				}
				$html .= '<td class="vssv-col-num">' . esc_html( (string) $row['games'] ) . '</td>';
				$html .= '<td class="vssv-col-num">' . esc_html( (string) $row['wins'] ) . '</td>';
				$html .= '<td class="vssv-col-num">' . esc_html( (string) $row['defeats'] ) . '</td>';
				$html .= '<td class="vssv-col-num vssv-col-sets">' . esc_html( $row['sets_won'] . ':' . $row['sets_lost'] ) . '</td>';
				$html .= '<td class="vssv-col-num vssv-col-points">' . esc_html( (string) $row['points'] ) . '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table>';
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Abschnittstitel.
	 *
	 * @param string $title Titel.
	 * @return string
	 */
	public static function render_heading( string $title ): string {
		return '<h3 class="vssv-heading">' . esc_html( $title ) . '</h3>';
	}
}
