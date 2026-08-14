<?php
/**
 * Gutenberg-Blöcke (dynamisch, serverseitig gerendert).
 *
 * Blöcke:
 *  - swiss-volley/games    Swiss Volley – Spiele
 *  - swiss-volley/results  Swiss Volley – Resultate
 *  - swiss-volley/ranking  Swiss Volley – Rangliste
 *  - swiss-volley/team     Swiss Volley – Team
 *
 * Die Blöcke sind bewusst schlank gehalten: ein gemeinsames Editor-Skript
 * (Vanilla JS, keine Build-Pipeline) mit Team-Dropdown; die Ausgabe erfolgt
 * über dieselben Render-Pfade wie die Shortcodes. Shortcodes funktionieren
 * unabhängig davon immer.
 *
 * @package VolleyballSchedulesForSwissVolley
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VSSV_Blocks
 */
class VSSV_Blocks {

	/**
	 * Blöcke und Editor-Assets registrieren.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Blocktypen registrieren.
	 */
	public static function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // Klassischer Editor ohne Gutenberg.
		}

		wp_register_script(
			'vssv-blocks',
			VSSV_PLUGIN_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			VSSV_VERSION,
			true
		);

		wp_set_script_translations(
			'vssv-blocks',
			'volleyball-schedules-for-swiss-volley',
			VSSV_PLUGIN_DIR . 'languages'
		);

		// Teamliste für das Dropdown im Editor.
		$teams = array();
		foreach ( VSSV_Teams::all() as $tid => $team ) {
			$label = VSSV_Teams::display_name( (int) $tid );
			if ( '' === $label ) {
				$label = (string) $tid;
			}
			if ( ! empty( $team['alias'] ) ) {
				$label .= ' (' . $team['alias'] . ')';
			}
			$teams[] = array(
				'value' => (string) $tid,
				'label' => $label,
			);
		}
		wp_localize_script( 'vssv-blocks', 'vssvBlocksData', array( 'teams' => $teams ) );

		$team_attributes = array(
			'team'  => array(
				'type'    => 'string',
				'default' => '',
			),
			'limit' => array(
				'type'    => 'number',
				'default' => 5,
			),
		);

		register_block_type(
			'swiss-volley/games',
			array(
				'api_version'     => 3,
				'title'           => __( 'Swiss Volley - Games', 'volleyball-schedules-for-swiss-volley' ),
				'editor_script'   => 'vssv-blocks',
				'attributes'      => $team_attributes,
				'render_callback' => static function ( $attributes ) {
					return VSSV_Shortcodes::games(
						array(
							'team'  => (string) ( $attributes['team'] ?? '' ),
							'limit' => (int) ( $attributes['limit'] ?? 5 ),
							'scope' => 'upcoming',
						)
					);
				},
			)
		);

		register_block_type(
			'swiss-volley/results',
			array(
				'api_version'     => 3,
				'title'           => __( 'Swiss Volley - Results', 'volleyball-schedules-for-swiss-volley' ),
				'editor_script'   => 'vssv-blocks',
				'attributes'      => $team_attributes,
				'render_callback' => static function ( $attributes ) {
					return VSSV_Shortcodes::results(
						array(
							'team'  => (string) ( $attributes['team'] ?? '' ),
							'limit' => (int) ( $attributes['limit'] ?? 5 ),
						)
					);
				},
			)
		);

		register_block_type(
			'swiss-volley/ranking',
			array(
				'api_version'     => 3,
				'title'           => __( 'Swiss Volley - Standings', 'volleyball-schedules-for-swiss-volley' ),
				'editor_script'   => 'vssv-blocks',
				'attributes'      => array(
					'team' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => static function ( $attributes ) {
					return VSSV_Shortcodes::ranking(
						array( 'team' => (string) ( $attributes['team'] ?? '' ) )
					);
				},
			)
		);

		register_block_type(
			'swiss-volley/team',
			array(
				'api_version'     => 3,
				'title'           => __( 'Swiss Volley - Team', 'volleyball-schedules-for-swiss-volley' ),
				'editor_script'   => 'vssv-blocks',
				'attributes'      => $team_attributes,
				'render_callback' => static function ( $attributes ) {
					return VSSV_Shortcodes::team(
						array(
							'team'  => (string) ( $attributes['team'] ?? '' ),
							'limit' => (int) ( $attributes['limit'] ?? 5 ),
						)
					);
				},
			)
		);
	}
}
