/**
 * Block editor UI for the Sheet Music Library and Sheet Music Last Updated blocks.
 * Plain JS (no build step); both blocks are rendered on the server.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var BaseControl = wp.components.BaseControl;
	var CheckboxControl = wp.components.CheckboxControl;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var Disabled = wp.components.Disabled;
	var ServerSideRender = wp.serverSideRender;
	var useSelect = wp.data.useSelect;

	var data = window.osmBlockData || { seasons: [], pieces: [] };

	function parseSeasons( value ) {
		return ( value || '' ).split( ',' ).map( function ( s ) {
			return s.trim();
		} ).filter( Boolean );
	}

	// Checkbox list of seasons, stored as a comma-separated slug string.
	function SeasonChecklist( props ) {
		var selected = parseSeasons( props.value );

		return el( BaseControl, { label: props.label, help: props.help, __nextHasNoMarginBottom: true },
			data.seasons.length ? data.seasons.map( function ( season ) {
				return el( CheckboxControl, {
					key: season.slug,
					label: season.name,
					checked: selected.indexOf( season.slug ) !== -1,
					__nextHasNoMarginBottom: true,
					onChange: function ( checked ) {
						var next = selected.filter( function ( s ) {
							return s !== season.slug;
						} );
						if ( checked ) next.push( season.slug );
						props.onChange( next.join( ',' ) );
					},
				} );
			} ) : el( 'p', null, 'No seasons have been created yet.' )
		);
	}

	registerBlockType( 'sheet-music-librarian/library', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var pieceOptions = [ { label: '— Show a collection —', value: '0' } ].concat(
				data.pieces.map( function ( piece ) {
					return { label: piece.title || '(no title) #' + piece.id, value: String( piece.id ) };
				} )
			);

			return el( 'div', useBlockProps(),
				el( InspectorControls, null,
					el( PanelBody, { title: 'Collection' },
						el( SelectControl, {
							label: 'Single piece',
							value: String( attributes.pieceId || 0 ),
							options: pieceOptions,
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { pieceId: parseInt( value, 10 ) || 0 } );
							},
						} ),
						attributes.pieceId ? null : el( SeasonChecklist, {
							label: 'Seasons',
							help: 'Leave all unchecked to show the entire library.',
							value: attributes.season,
							onChange: function ( value ) {
								setAttributes( { season: value } );
							},
						} ),
						! attributes.pieceId && parseSeasons( attributes.season ).length === 1 ? el( SelectControl, {
							label: 'Order',
							value: attributes.orderby,
							options: [
								{ label: 'Program order (set on the season)', value: 'program' },
								{ label: 'Alphabetical', value: 'title' },
							],
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { orderby: value } );
							},
						} ) : null
					)
				),
				el( Disabled, null,
					el( ServerSideRender, {
						block: 'sheet-music-librarian/library',
						attributes: attributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'sheet-music-librarian/last-updated', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var postId = useSelect( function ( select ) {
				var editor = select( 'core/editor' );
				return editor ? editor.getCurrentPostId() : 0;
			}, [] );

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Settings' },
						el( TextControl, {
							label: 'Label',
							value: attributes.label,
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { label: value } );
							},
						} ),
						el( ToggleControl, {
							label: 'Match the collections on this page',
							help: attributes.matchPage
								? 'Uses the Sheet Music Library blocks and shortcodes on this page (as last saved). Falls back to the entire library if there are none.'
								: 'Choose which seasons to track below.',
							checked: attributes.matchPage,
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { matchPage: value } );
							},
						} ),
						attributes.matchPage ? null : el( SeasonChecklist, {
							label: 'Seasons',
							help: 'Leave all unchecked to track the entire library.',
							value: attributes.season,
							onChange: function ( value ) {
								setAttributes( { season: value } );
							},
						} )
					)
				),
				el( 'div', useBlockProps(),
					el( ServerSideRender, {
						block: 'sheet-music-librarian/last-updated',
						attributes: attributes,
						urlQueryArgs: postId ? { post_id: postId } : {},
						EmptyResponsePlaceholder: function () {
							return el( 'p', null, attributes.label + ' (no sheet music yet)' );
						},
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
