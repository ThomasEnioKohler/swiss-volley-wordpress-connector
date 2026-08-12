<?php
/**
 * Deinstallation: entfernt sämtliche plugin-eigenen Optionen und Transients,
 * einschliesslich des API-Keys.
 *
 * Wird nur beim vollständigen Löschen des Plugins ausgeführt –
 * beim blossen Deaktivieren bleiben alle Einstellungen erhalten.
 *
 * @package SwissVolleyConnector
 */

// Nur ausführen, wenn WordPress die Deinstallation angestossen hat.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Optionen (inkl. API-Key in svc_settings).
delete_option( 'svc_settings' );
delete_option( 'svc_teams' );
delete_option( 'svc_log' );

// Stale Copies der Cache-Schicht.
delete_option( 'svc_stale_games' );
delete_option( 'svc_stale_rankings' );

// Transients.
delete_transient( 'svc_cache_games' );
delete_transient( 'svc_cache_rankings' );

// Multisite: pro Site aufräumen.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );

		delete_option( 'svc_settings' );
		delete_option( 'svc_teams' );
		delete_option( 'svc_log' );
		delete_option( 'svc_stale_games' );
		delete_option( 'svc_stale_rankings' );
		delete_transient( 'svc_cache_games' );
		delete_transient( 'svc_cache_rankings' );

		restore_current_blog();
	}
}
