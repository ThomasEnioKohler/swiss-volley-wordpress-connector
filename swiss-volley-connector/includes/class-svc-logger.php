<?php
/**
 * Debug-Logger.
 *
 * Protokolliert API-Aufrufe (Endpunkt, HTTP-Status, Zeitpunkt, Fehlermeldung)
 * in einem Ringpuffer (Option), sofern der Debug-Modus aktiv ist.
 *
 * Es werden NIEMALS API-Keys, Tokens, Passwörter oder andere Secrets
 * protokolliert. Der Logger erhält solche Werte gar nicht erst.
 *
 * @package SwissVolleyConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SVC_Logger
 */
class SVC_Logger {

	const OPTION_KEY  = 'svc_log';
	const MAX_ENTRIES = 100;

	/**
	 * Prüft, ob der Debug-Modus aktiv ist.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$settings = get_option( 'svc_settings', array() );
		return ! empty( $settings['debug'] );
	}

	/**
	 * Log-Eintrag schreiben (nur im Debug-Modus).
	 *
	 * @param string $endpoint    API-Pfad ohne Query-Secrets, z. B. "/indoor/games".
	 * @param mixed  $status_code HTTP-Statuscode oder '-' bei Netzwerkfehler.
	 * @param string $message     Kurze Meldung (ohne sensible Daten).
	 * @param string $level       'info' oder 'error'.
	 */
	public static function log( string $endpoint, $status_code, string $message = '', string $level = 'info' ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$entries = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}

		$entries[] = array(
			'time'     => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'endpoint' => $endpoint,
			'status'   => is_scalar( $status_code ) ? (string) $status_code : '-',
			'message'  => $message,
			'level'    => ( 'error' === $level ) ? 'error' : 'info',
		);

		// Ringpuffer begrenzen.
		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, - self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $entries, false );
	}

	/**
	 * Alle Einträge lesen (neueste zuerst).
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function entries(): array {
		$entries = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $entries ) ) {
			return array();
		}
		return array_reverse( $entries );
	}

	/**
	 * Log leeren.
	 */
	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
