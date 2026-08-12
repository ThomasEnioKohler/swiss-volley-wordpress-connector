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
 * @package SwissVolleyConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SVC_Blocks
 */
class SVC_Blocks {

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
			'svc-blocks',
			SVC_PLUGIN_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			SVC_VERSION,
			true
		);

		// Teamliste für das Dropdown im Editor.
		$teams = array();
		foreach ( SVC_Teams::all() as $tid => $team ) {
			$label = SVC_Teams::display_name( (int) $tid );
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
		wp_localize_script( 'svc-blocks', 'svcBlocksData', array( 'teams' => $teams ) );

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
				'title'           => __( 'Swiss Volley – Spiele', 'swiss-volley-connector' ),
				'editor_script'   => 'svc-blocks',
				'attributes'      => $team_attributes,
				'render_callback' => static function ( $attributes ) {
					return SVC_Shortcodes::games(
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
				'title'           => __( 'Swiss Volley – Resultate', 'swiss-volley-connector' ),
				'editor_script'   => 'svc-blocks',
				'attributes'      => $team_attributes,
				'render_callback' => static function ( $attributes ) {
					return SVC_Shortcodes::results(
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
				'title'           => __( 'Swiss Volley – Rangliste', 'swiss-volley-connector' ),
				'editor_script'   => 'svc-blocks',
				'attributes'      => array(
					'team' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => static function ( $attributes ) {
					return SVC_Shortcodes::ranking(
						array( 'team' => (string) ( $attributes['team'] ?? '' ) )
					);
				},
			)
		);

		register_block_type(
			'swiss-volley/team',
			array(
				'api_version'     => 3,
				'title'           => __( 'Swiss Volley – Team', 'swiss-volley-connector' ),
				'editor_script'   => 'svc-blocks',
				'attributes'      => $team_attributes,
				'render_callback' => static function ( $attributes ) {
					return SVC_Shortcodes::team(
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
