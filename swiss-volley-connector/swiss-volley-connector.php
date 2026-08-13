<?php
/**
 * Plugin Name:       Swiss Volley Connector
 * Plugin URI:        https://www.volleypizol.org
 * Description:       Zeigt Spielpläne, Resultate und Ranglisten automatisch aus der offiziellen Swiss-Volley-API (Volley Manager) an.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            Volley Pizol
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       swiss-volley-connector
 * Domain Path:       /languages
 *
 * @package SwissVolleyConnector
 */

// Kein direkter Aufruf dieser Datei.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SVC_VERSION', '1.0.0' );
define( 'SVC_PLUGIN_FILE', __FILE__ );
define( 'SVC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SVC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Kernklassen laden.
require_once SVC_PLUGIN_DIR . 'includes/class-svc-logger.php';
require_once SVC_PLUGIN_DIR . 'includes/class-svc-cache.php';
require_once SVC_PLUGIN_DIR . 'includes/class-svc-api.php';
require_once SVC_PLUGIN_DIR . 'includes/class-svc-teams.php';
require_once SVC_PLUGIN_DIR . 'includes/class-svc-data.php';
require_once SVC_PLUGIN_DIR . 'includes/class-svc-renderer.php';
require_once SVC_PLUGIN_DIR . 'includes/class-svc-shortcodes.php';
require_once SVC_PLUGIN_DIR . 'includes/class-svc-blocks.php';
require_once SVC_PLUGIN_DIR . 'includes/class-svc-plugin.php';

if ( is_admin() ) {
	require_once SVC_PLUGIN_DIR . 'includes/class-svc-admin.php';
}

/**
 * Zentraler Zugriff auf die Plugin-Instanz.
 *
 * @return SVC_Plugin
 */
function svc_plugin(): SVC_Plugin {
	return SVC_Plugin::instance();
}

// Plugin starten.
add_action( 'plugins_loaded', array( 'SVC_Plugin', 'instance' ) );

/**
 * Standardwerte bei Aktivierung setzen (bestehende Einstellungen bleiben erhalten).
 */
function svc_activate(): void {
	$defaults = SVC_Plugin::default_settings();
	$existing = get_option( 'svc_settings', array() );
	if ( ! is_array( $existing ) ) {
		$existing = array();
	}
	update_option( 'svc_settings', array_merge( $defaults, $existing ) );

	if ( false === get_option( 'svc_teams', false ) ) {
		add_option( 'svc_teams', array() );
	}
}
register_activation_hook( __FILE__, 'svc_activate' );

/**
 * Bei Deaktivierung nur flüchtige Daten (Transients) entfernen.
 * Einstellungen und Teamkonfiguration bleiben bewusst erhalten.
 */
function svc_deactivate(): void {
	SVC_Cache::clear_all();
}
register_deactivation_hook( __FILE__, 'svc_deactivate' );
