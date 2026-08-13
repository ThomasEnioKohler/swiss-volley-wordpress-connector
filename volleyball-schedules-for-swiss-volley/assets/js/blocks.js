/**
 * Volleyball Schedules for Swiss Volley – Gutenberg-Blöcke.
 *
 * Ohne Build-Pipeline (kein JSX): nutzt die globalen wp.*-Pakete.
 * Vier dynamische Blöcke mit Team-Dropdown; die Vorschau erfolgt
 * serverseitig über ServerSideRender – identisch zur Frontend-Ausgabe.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var Placeholder = wp.components.Placeholder;
	var ServerSideRender = wp.serverSideRender;

	var teams = ( window.vssvBlocksData && window.vssvBlocksData.teams ) || [];

	var teamOptions = [ { value: '', label: __( '-- Select team --', 'volleyball-schedules-for-swiss-volley' ) } ].concat( teams );

	/**
	 * Block registrieren.
	 *
	 * @param {string}  name     Blockname (Suffix).
	 * @param {string}  title    Anzeigename.
	 * @param {string}  icon     Dashicon.
	 * @param {boolean} hasLimit Limit-Regler anzeigen.
	 */
	function registerVssvBlock( name, title, icon, hasLimit ) {
		var attributes = {
			team: { type: 'string', default: '' }
		};
		if ( hasLimit ) {
			attributes.limit = { type: 'number', default: 5 };
		}

		registerBlockType( 'swiss-volley/' + name, {
			apiVersion: 3,
			title: title,
			icon: icon,
			category: 'widgets',
			attributes: attributes,

			edit: function ( props ) {
				var controls = [
					el( SelectControl, {
						key: 'team',
						label: __( 'Team', 'volleyball-schedules-for-swiss-volley' ),
						value: props.attributes.team,
						options: teamOptions,
						onChange: function ( value ) {
							props.setAttributes( { team: value } );
						}
					} )
				];

				if ( hasLimit ) {
					controls.push(
						el( RangeControl, {
							key: 'limit',
							label: __( 'Number of games', 'volleyball-schedules-for-swiss-volley' ),
							min: 1,
							max: 30,
							value: props.attributes.limit,
							onChange: function ( value ) {
								props.setAttributes( { limit: value } );
							}
						} )
					);
				}

				var preview;
				if ( ! props.attributes.team ) {
					preview = el( Placeholder, {
						icon: icon,
						label: title,
						instructions: teams.length
							? __( 'Please select a team in the block settings.', 'volleyball-schedules-for-swiss-volley' )
							: __( 'No teams configured yet. Load teams under Swiss Volley -> Teams first.', 'volleyball-schedules-for-swiss-volley' )
					} );
				} else {
					preview = el( ServerSideRender, {
						block: 'swiss-volley/' + name,
						attributes: props.attributes
					} );
				}

				return el(
					'div',
					wp.blockEditor.useBlockProps ? wp.blockEditor.useBlockProps() : {},
					el( InspectorControls, { key: 'inspector' },
						el( PanelBody, { title: __( 'Swiss Volley', 'volleyball-schedules-for-swiss-volley' ) }, controls )
					),
					preview
				);
			},

			// Dynamischer Block: Ausgabe erfolgt serverseitig.
			save: function () {
				return null;
			}
		} );
	}

	registerVssvBlock( 'games', __( 'Swiss Volley - Games', 'volleyball-schedules-for-swiss-volley' ), 'calendar-alt', true );
	registerVssvBlock( 'results', __( 'Swiss Volley - Results', 'volleyball-schedules-for-swiss-volley' ), 'editor-ol', true );
	registerVssvBlock( 'ranking', __( 'Swiss Volley - Standings', 'volleyball-schedules-for-swiss-volley' ), 'list-view', false );
	registerVssvBlock( 'team', __( 'Swiss Volley - Team', 'volleyball-schedules-for-swiss-volley' ), 'groups', true );
} )( window.wp );
