<?php
/**
 * Zentrale Plugin-Klasse: Verdrahtung von Shortcodes, Blöcken, Assets, Admin.
 *
 * @package SwissVolleyConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SVC_Plugin
 */
class SVC_Plugin {

	/**
	 * Singleton-Instanz.
	 *
	 * @var SVC_Plugin|null
	 */
	private static ?SVC_Plugin $instance = null;

	/**
	 * Wird true, sobald eine Ausgabe des Plugins auf der Seite vorkommt.
	 *
	 * @var bool
	 */
	private static bool $assets_needed = false;

	/**
	 * Instanz beziehen.
	 *
	 * @return SVC_Plugin
	 */
	public static function instance(): SVC_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: Hooks registrieren.
	 */
	private function __construct() {
		load_plugin_textdomain( 'swiss-volley-connector', false, dirname( plugin_basename( SVC_PLUGIN_FILE ) ) . '/languages' );

		SVC_Shortcodes::register();
		SVC_Blocks::register();

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_styles' ) );

		if ( is_admin() && class_exists( 'SVC_Admin' ) ) {
			SVC_Admin::register();
		}
	}

	/**
	 * Standardeinstellungen.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_settings(): array {
		return array(
			'api_key'       => '',
			'api_base_url'  => SVC_API::DEFAULT_BASE_URL,
			'club_id'       => '',
			'club_name'     => '',
			'season_year'   => '', // leer = alle von der API gelieferten Saisons.
			'cache_minutes' => 30,
			'highlight_own' => 1,

			// Liga-Darstellung in Spiellisten: 'heading' (Überschrift) oder 'meta' (Detailzeile).
			'league_display' => 'heading',

			// Interaktiver Gruppierungs-Umschalter für Besucher (0/1).
			'group_switcher' => 0,
			'debug'         => 0,
			'custom_css'    => '',
		);
	}

	/**
	 * Frontend-Assets registrieren; eingebunden werden sie nur bei Bedarf.
	 */
	public static function register_frontend_assets(): void {
		wp_register_style(
			'svc-frontend',
			SVC_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SVC_VERSION
		);

		wp_register_script(
			'svc-frontend',
			SVC_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			SVC_VERSION,
			true
		);
	}

	/**
	 * Frontend-Skript für die interaktive Gruppierung laden
	 * (nur wenn tatsächlich ein Umschalter ausgegeben wird).
	 */
	public static function mark_switcher_needed(): void {
		self::mark_assets_needed();
		if ( ! is_admin() ) {
			wp_enqueue_script( 'svc-frontend' );
		}
	}

	/**
	 * Frontend-CSS im Block-Editor bereitstellen (für Block-Vorschau).
	 */
	public static function enqueue_editor_styles(): void {
		wp_enqueue_style(
			'svc-frontend',
			SVC_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SVC_VERSION
		);
	}

	/**
	 * Von Shortcodes/Blöcken aufgerufen: Assets dieser Seite einbinden.
	 * Funktioniert auch bei später Ausgabe (Widgets), da Styles im Footer
	 * nachgeladen werden können.
	 */
	public static function mark_assets_needed(): void {
		if ( self::$assets_needed ) {
			return;
		}
		self::$assets_needed = true;

		if ( ! is_admin() ) {
			wp_enqueue_style( 'svc-frontend' );

			// Eigenes CSS des Administrators anhängen (sanitisiert).
			$settings   = get_option( 'svc_settings', array() );
			$custom_css = isset( $settings['custom_css'] ) ? trim( (string) $settings['custom_css'] ) : '';
			if ( '' !== $custom_css ) {
				wp_add_inline_style( 'svc-frontend', wp_strip_all_tags( $custom_css ) );
			}
		}
	}
}
