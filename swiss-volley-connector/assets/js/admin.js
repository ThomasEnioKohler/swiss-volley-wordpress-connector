/**
 * Swiss Volley Connector – Admin-Skript.
 *
 * Vanilla JS, keine externen Abhängigkeiten. Kommuniziert über admin-ajax.php
 * (Nonce-geschützt). Es werden keine Secrets an den Browser übertragen.
 */
( function () {
	'use strict';

	if ( typeof window.svcAdmin === 'undefined' ) {
		return;
	}

	var cfg = window.svcAdmin;

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
			result.className = 'svc-inline-result';
			result.textContent = busyLabel;

			post( action )
				.then( function ( json ) {
					var ok = !! ( json && json.success );
					var data = ( json && json.data ) || {};
					result.className = 'svc-inline-result ' + ( ok ? 'svc-ok' : 'svc-fail' );
					result.textContent = data.message || ( ok ? 'OK' : cfg.i18n.error );

					if ( ok && data.reload ) {
						window.setTimeout( function () {
							window.location.reload();
						}, 1500 );
					}
				} )
				.catch( function () {
					result.className = 'svc-inline-result svc-fail';
					result.textContent = cfg.i18n.error;
				} )
				.finally( function () {
					button.disabled = false;
				} );
		} );
	}

	wire( 'svc-test-connection', 'svc-test-result', 'svc_test_connection', cfg.i18n.testing );
	wire( 'svc-load-teams', 'svc-load-result', 'svc_load_teams', cfg.i18n.loading );
	wire( 'svc-clear-cache', 'svc-cache-result', 'svc_clear_cache', cfg.i18n.clearing );
	wire( 'svc-clear-log', 'svc-log-result', 'svc_clear_log', cfg.i18n.clearing );
} )();
