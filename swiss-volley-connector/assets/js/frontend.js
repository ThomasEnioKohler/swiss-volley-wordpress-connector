/**
 * Swiss Volley Connector – Frontend-Skript.
 *
 * Interaktive Gruppierung der Spiellisten (Chronologisch / Nach Liga /
 * Nach Team). Vanilla JS, ohne Abhängigkeiten; arbeitet rein clientseitig
 * mit den bereits gerenderten Karten (keine zusätzlichen API-Aufrufe).
 * Ohne JavaScript bleibt die chronologische Liste sichtbar.
 */
( function () {
	'use strict';

	/**
	 * Einen umschaltbaren Bereich initialisieren.
	 *
	 * @param {HTMLElement} wrap Container .svc-switchable.
	 */
	function init( wrap ) {
		var list = wrap.querySelector( '.svc-games' );
		if ( ! list ) {
			return;
		}

		// Ursprüngliche (chronologische) Reihenfolge merken.
		var cards = Array.prototype.slice.call( list.querySelectorAll( '.svc-game' ) );
		if ( ! cards.length ) {
			return;
		}

		var buttons = wrap.querySelectorAll( '.svc-switch' );

		/**
		 * Liste neu aufbauen.
		 *
		 * @param {string} mode 'none' | 'league' | 'team'.
		 */
		function apply( mode ) {
			// Bestehende Gruppen entfernen, Ausgangsliste wiederherstellen.
			var oldGroups = wrap.querySelector( '.svc-game-groups' );
			if ( oldGroups ) {
				oldGroups.parentNode.removeChild( oldGroups );
			}

			wrap.classList.remove( 'svc-grouped-league', 'svc-grouped-team' );

			if ( 'none' === mode ) {
				cards.forEach( function ( card ) {
					list.appendChild( card );
				} );
				list.hidden = false;
				setActive( mode );
				return;
			}

			var attr = 'league' === mode ? 'data-svc-league' : 'data-svc-team';
			var order = [];
			var map = {};

			cards.forEach( function ( card ) {
				var label = card.getAttribute( attr ) || '';
				if ( ! Object.prototype.hasOwnProperty.call( map, label ) ) {
					map[ label ] = [];
					order.push( label );
				}
				map[ label ].push( card );
			} );

			var groups = document.createElement( 'div' );
			groups.className = 'svc-game-groups';

			order.forEach( function ( label ) {
				var section = document.createElement( 'section' );
				section.className = 'svc-game-group';

				if ( label ) {
					var heading = document.createElement( 'h3' );
					heading.className = 'svc-group-heading';
					heading.textContent = label;
					section.appendChild( heading );
				}

				var inner = document.createElement( 'div' );
				inner.className = 'svc-games';
				map[ label ].forEach( function ( card ) {
					inner.appendChild( card );
				} );
				section.appendChild( inner );
				groups.appendChild( section );
			} );

			list.hidden = true;
			wrap.appendChild( groups );
			wrap.classList.add( 'league' === mode ? 'svc-grouped-league' : 'svc-grouped-team' );
			setActive( mode );
		}

		/**
		 * Aktiven Button markieren.
		 *
		 * @param {string} mode Aktiver Modus.
		 */
		function setActive( mode ) {
			Array.prototype.forEach.call( buttons, function ( button ) {
				var active = button.getAttribute( 'data-svc-group' ) === mode;
				button.classList.toggle( 'svc-active', active );
				button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			} );
		}

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				apply( button.getAttribute( 'data-svc-group' ) || 'none' );
			} );
		} );

		apply( wrap.getAttribute( 'data-svc-initial' ) || 'none' );
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '.svc-switchable' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
