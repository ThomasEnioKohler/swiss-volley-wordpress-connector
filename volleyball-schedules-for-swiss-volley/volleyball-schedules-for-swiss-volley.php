<?php
/**
 * Plugin Name:       Volleyball Schedules for Swiss Volley
 * Plugin URI:        https://www.volleypizol.org
 * Description:       Displays match schedules, results and standings automatically from the official Swiss Volley API (Volley Manager).
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            Volley Pizol
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       volleyball-schedules-for-swiss-volley
 * Domain Path:       /languages
 *
 * @package VolleyballSchedulesForSwissVolley
 */

// Kein direkter Aufruf dieser Datei.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VSSV_VERSION', '1.0.0' );
define( 'VSSV_PLUGIN_FILE', __FILE__ );
define( 'VSSV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VSSV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Kernklassen laden.
require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-logger.php';
require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-cache.php';
require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-api.php';
require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-teams.php';
require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-data.php';
require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-renderer.php';
require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-shortcodes.php';
require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-blocks.php';
require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-plugin.php';

if ( is_admin() ) {
	require_once VSSV_PLUGIN_DIR . 'includes/class-vssv-admin.php';
}

/**
 * Zentraler Zugriff auf die Plugin-Instanz.
 *
 * @return VSSV_Plugin
 */
function vssv_plugin(): VSSV_Plugin {
	return VSSV_Plugin::instance();
}

// Plugin starten.
add_action( 'plugins_loaded', array( 'VSSV_Plugin', 'instance' ) );

/**
 * Standardwerte bei Aktivierung setzen (bestehende Einstellungen bleiben erhalten).
 */
function vssv_activate(): void {
	$defaults = VSSV_Plugin::default_settings();
	$existing = get_option( 'vssv_settings', array() );
	if ( ! is_array( $existing ) ) {
		$existing = array();
	}
	update_option( 'vssv_settings', array_merge( $defaults, $existing ) );

	if ( false === get_option( 'vssv_teams', false ) ) {
		add_option( 'vssv_teams', array() );
	}
}
register_activation_hook( __FILE__, 'vssv_activate' );

/**
 * Bei Deaktivierung nur flüchtige Daten (Transients) entfernen.
 * Einstellungen und Teamkonfiguration bleiben bewusst erhalten.
 */
function vssv_deactivate(): void {
	VSSV_Cache::clear_all();
}
register_deactivation_hook( __FILE__, 'vssv_deactivate' );
