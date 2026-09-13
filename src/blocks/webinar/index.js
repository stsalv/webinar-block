/**
 * Webinar Block — Gutenberg editor integration.
 *
 * Dynamic block: the editor only stores the webinar ID;
 * the front-end markup is rendered server-side by Webinar_Render.
 * Written without JSX (plain wp.* globals) — no babel preset changes.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks ) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var Placeholder = wp.components.Placeholder;
	var SelectControl = wp.components.SelectControl;
	var PanelBody = wp.components.PanelBody;
	var Spinner = wp.components.Spinner;
	var createElement = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var useState = wp.element.useState;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	var NO_VALUE = '0';

	var L10n = window.webinarBlockEditorL10n
		|| ( window.parent && window.parent.webinarBlockEditorL10n )
		|| {};

	function t( key, fallback ) {
		return L10n[ key ] || fallback;
	}

	function loadOptions() {
		return wp.apiFetch( { path: '/wp/v2/webinars?per_page=100&status=publish' } )
			.then( function ( posts ) {
				var list = ( posts || [] ).map( function ( p ) {
					return { value: String( p.id ), label: p.title.rendered || ( '#' + p.id ) };
				} );
				list.sort( function ( a, b ) { return a.label.localeCompare( b.label ); } );
				list.unshift( { value: NO_VALUE, label: t( 'select', '— Select a webinar —' ) } );
				return list;
			} )
			.catch( function () {
				return [ { value: NO_VALUE, label: t( 'select', '— Select a webinar —' ) } ];
			} );
	}

	function useWebinarOptions() {
		var state = useState( null );
		var options = state[ 0 ];
		var setOptions = state[ 1 ];

		useEffect( function () {
			loadOptions().then( setOptions );
		}, [] );

		return options;
	}

	var icon = createElement(
		'svg',
		{ viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': 'true', focusable: 'false' },
		createElement( 'rect', { x: 1, y: 3, width: 12, height: 18, rx: 2, fill: 'none', stroke: 'currentColor', strokeWidth: 2 } ),
		createElement( 'path', { d: 'M5.5 9v6l5-3z', fill: 'currentColor' } ),
		createElement( 'path', { d: 'M17 8h6M17 12h6M17 16h6', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', fill: 'none' } )
	);

	registerBlockType( 'webinar-block/webinar', {
		apiVersion: 3,
		title: t( 'title', 'Webinar' ),
		icon: icon,
		category: 'media',
		supports: { html: false, multiple: true, align: [ 'wide', 'full' ] },
		attributes: {
			webinarId: { type: 'integer', default: 0 },
		},

		edit: function ( props ) {
			var options = useWebinarOptions();
			var blockProps = useBlockProps();
			var id = props.attributes.webinarId;
			var selected = null;

			if ( options && id ) {
				selected = options.filter( function ( o ) { return o.value === String( id ); } )[ 0 ] || null;
			}

			function onChange( value ) {
				props.setAttributes( { webinarId: parseInt( value, 10 ) || 0 } );
			}

			var select = createElement( SelectControl, {
				label: t( 'title', 'Webinar' ),
				value: String( id || 0 ),
				options: options || [ { value: NO_VALUE, label: '...' } ],
				onChange: onChange,
			} );

			var inspector = createElement(
				InspectorControls,
				{ key: 'inspector' },
				createElement( PanelBody, { title: t( 'settings', 'Webinar settings' ) }, select )
			);

			var body;
			if ( id ) {
				// Live preview of the real front-end markup.
				body = createElement( ServerSideRender, {
					block: 'webinar-block/webinar',
					attributes: props.attributes,
				} );
			} else {
				body = createElement(
					Placeholder,
					{
						icon: icon,
						label: t( 'title', 'Webinar' ),
						instructions: selected
							? t( 'selected', 'Selected:' ) + ' ' + selected.label
							: t( 'instructions', 'Choose a published webinar. The full block renders on the site.' ),
					},
					options ? select : createElement( Spinner, {} )
				);
			}

			return createElement( 'div', blockProps, inspector, body );
		},


		// Dynamic block: nothing saved to post_content except attributes.
		save: function () {
			return null;
		},
	} );
} )( window.wp );

