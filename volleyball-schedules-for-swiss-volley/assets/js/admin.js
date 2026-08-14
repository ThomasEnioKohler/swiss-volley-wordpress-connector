/**
 * Volleyball Schedules for Swiss Volley – Admin-Skript.
 *
 * Vanilla JS, keine externen Abhängigkeiten. Kommuniziert über admin-ajax.php
 * (Nonce-geschützt). Es werden keine Secrets an den Browser übertragen.
 */
( function () {
	'use strict';

	if ( typeof window.vssvAdmin === 'undefined' ) {
		return;
	}

	var cfg = window.vssvAdmin;

	/**
	 * AJAX-POST an admin-ajax.php.
	 *
	 * @param {string} action WP-AJAX-Action.
	 * @return {Promise<Object>} Antwort-JSON.
	 */
	function post( action ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', cfg.nonce );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * Button mit Ergebnisanzeige verdrahten.
	 *
	 * @param {string} buttonId  ID des Buttons.
	 * @param {string} resultId  ID des Ergebnis-Spans.
	 * @param {string} action    WP-AJAX-Action.
	 * @param {string} busyLabel Text während der Ausführung.
	 */
	function wire( buttonId, resultId, action, busyLabel ) {
		var button = document.getElementById( buttonId );
		var result = document.getElementById( resultId );
		if ( ! button || ! result ) {
			return;
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			result.className = 'vssv-inline-result';
			result.textContent = busyLabel;

			post( action )
				.then( function ( json ) {
					var ok = !! ( json && json.success );
					var data = ( json && json.data ) || {};
					result.className = 'vssv-inline-result ' + ( ok ? 'vssv-ok' : 'vssv-fail' );
					result.textContent = data.message || ( ok ? 'OK' : cfg.i18n.error );

					if ( ok && data.reload ) {
						window.setTimeout( function () {
							window.location.reload();
						}, 1500 );
					}
				} )
				.catch( function () {
					result.className = 'vssv-inline-result vssv-fail';
					result.textContent = cfg.i18n.error;
				} )
				.finally( function () {
					button.disabled = false;
				} );
		} );
	}

	wire( 'vssv-test-connection', 'vssv-test-result', 'vssv_test_connection', cfg.i18n.testing );
	wire( 'vssv-load-teams', 'vssv-load-result', 'vssv_load_teams', cfg.i18n.loading );
	wire( 'vssv-clear-cache', 'vssv-cache-result', 'vssv_clear_cache', cfg.i18n.clearing );
	wire( 'vssv-clear-log', 'vssv-log-result', 'vssv_clear_log', cfg.i18n.clearing );
} )();
