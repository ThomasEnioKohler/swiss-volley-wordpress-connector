<?php
/**
 * Cache-Schicht auf Basis der WordPress Transients API.
 *
 * Zusätzlich wird pro Schlüssel eine "Stale Copy" in einer Option abgelegt.
 * Ist die Swiss-Volley-API temporär nicht erreichbar, können so die zuletzt
 * erfolgreich geladenen Daten weiterhin angezeigt werden.
 *
 * @package VolleyballSchedulesForSwissVolley
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VSSV_Cache
 */
class VSSV_Cache {

	const TRANSIENT_PREFIX = 'vssv_cache_';
	const STALE_PREFIX     = 'vssv_stale_';

	/**
	 * Bekannte Cache-Schlüssel (für vollständiges Leeren).
	 *
	 * @var string[]
	 */
	private static array $known_keys = array( 'games', 'rankings' );

	/**
	 * Frischen Cache-Wert lesen.
	 *
	 * @param string $key Cache-Schlüssel.
	 * @return mixed|false false, wenn nicht vorhanden/abgelaufen.
	 */
	public static function get( string $key ) {
		return get_transient( self::TRANSIENT_PREFIX . $key );
	}

	/**
	 * Cache-Wert schreiben (Transient + Stale Copy).
	 *
	 * @param string $key   Cache-Schlüssel.
	 * @param mixed  $value Wert (array).
	 * @param int    $ttl   Lebensdauer in Sekunden.
	 */
	public static function set( string $key, $value, int $ttl ): void {
		set_transient( self::TRANSIENT_PREFIX . $key, $value, max( 60, $ttl ) );

		// Stale Copy für Ausfall-Fallback, ohne Autoload.
		update_option(
			self::STALE_PREFIX . $key,
			array(
				'saved' => time(),
				'data'  => $value,
			),
			false
		);
	}

	/**
	 * Zuletzt erfolgreich geladene Daten lesen (Fallback bei API-Ausfall).
	 *
	 * @param string $key Cache-Schlüssel.
	 * @return array{saved:int,data:mixed}|null
	 */
	public static function get_stale( string $key ): ?array {
		$stale = get_option( self::STALE_PREFIX . $key, null );
		if ( is_array( $stale ) && array_key_exists( 'data', $stale ) ) {
			return $stale;
		}
		return null;
	}

	/**
	 * Einzelnen Cache-Eintrag löschen (Transient, Stale Copy bleibt).
	 *
	 * @param string $key Cache-Schlüssel.
	 */
	public static function delete( string $key ): void {
		delete_transient( self::TRANSIENT_PREFIX . $key );
	}

	/**
	 * Alle Plugin-Transients löschen ("Cache jetzt leeren").
	 * Stale Copies bleiben als Notfall-Fallback erhalten.
	 */
	public static function clear_all(): void {
		foreach ( self::$known_keys as $key ) {
			delete_transient( self::TRANSIENT_PREFIX . $key );
		}
	}

	/**
	 * Alles inkl. Stale Copies entfernen (für die Deinstallation).
	 */
	public static function purge_everything(): void {
		foreach ( self::$known_keys as $key ) {
			delete_transient( self::TRANSIENT_PREFIX . $key );
			delete_option( self::STALE_PREFIX . $key );
		}
	}
}
