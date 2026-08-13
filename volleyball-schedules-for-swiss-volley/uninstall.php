<?php
/**
 * Deinstallation: entfernt sämtliche plugin-eigenen Optionen und Transients,
 * einschliesslich des API-Keys.
 *
 * Wird nur beim vollständigen Löschen des Plugins ausgeführt –
 * beim blossen Deaktivieren bleiben alle Einstellungen erhalten.
 *
 * @package VolleyballSchedulesForSwissVolley
 */

// Nur ausführen, wenn WordPress die Deinstallation angestossen hat.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Optionen (inkl. API-Key in vssv_settings).
delete_option( 'vssv_settings' );
delete_option( 'vssv_teams' );
delete_option( 'vssv_log' );

// Stale Copies der Cache-Schicht.
delete_option( 'vssv_stale_games' );
delete_option( 'vssv_stale_rankings' );

// Transients.
delete_transient( 'vssv_cache_games' );
delete_transient( 'vssv_cache_rankings' );

// Multisite: pro Site aufräumen.
if ( is_multisite() ) {
	$vssv_site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $vssv_site_ids as $vssv_site_id ) {
		switch_to_blog( (int) $vssv_site_id );

		delete_option( 'vssv_settings' );
		delete_option( 'vssv_teams' );
		delete_option( 'vssv_log' );
		delete_option( 'vssv_stale_games' );
		delete_option( 'vssv_stale_rankings' );
		delete_transient( 'vssv_cache_games' );
		delete_transient( 'vssv_cache_rankings' );

		restore_current_blog();
	}
}
