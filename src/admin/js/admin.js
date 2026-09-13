/**
 * Webinar Block — admin scripts.
 *
 * Phase 2A: media library integration for webinar meta boxes
 * and MP4/HLS visibility toggle.
 */
( function () {
	'use strict';

	const i18n = window.webinarBlockAdmin || {};
	const PREVIEW_DATA = i18n.previewData || {};
	const I18N = i18n.i18n || {};

	const FRAME_TITLES = {
		video: i18n.chooseVideo || 'Choose video',
		poster: i18n.choosePoster || 'Choose poster',
		manifest: i18n.chooseManifest || 'Choose manifest',
		file: i18n.chooseFile || 'Choose file',
		icon: i18n.chooseIcon || 'Choose icon',
	};

	const FRAME_LIBRARIES = {
		video: { type: 'video' },
		poster: { type: 'image' },
		manifest: {}, // any file type (.m3u8)
		file: {},
		icon: { type: 'image' },
	};


	/* ------------------------------------------------------------------
	 * Repeaters (timeline / takeaways / materials)
	 * ---------------------------------------------------------------- */

	const TEMPLATE_IDS = {
		timeline: 'webinar-tpl-timeline',
		takeaways: 'webinar-tpl-takeaway',
		materials: 'webinar-tpl-material',
	};

	document.addEventListener( 'click', ( event ) => {
		const addBtn = event.target.closest( '.webinar-repeater-add' );
		if ( addBtn ) {
			addRepeaterRow( addBtn );
			return;
		}

		const removeBtn = event.target.closest( '.webinar-repeater-remove' );
		if ( removeBtn ) {
			if ( ! window.confirm( i18n.confirmRemove || 'Remove this row?' ) ) {
				return;
			}
			const row = removeBtn.closest( '.webinar-repeater-row' );
			if ( row ) {
				row.remove();
			}
		}
	} );

	function makeRow( repeaterKey ) {
		const template = document.getElementById( TEMPLATE_IDS[ repeaterKey ] );
		if ( ! template ) {
			return null;
		}
		const index = Date.now() + Math.floor( Math.random() * 1000 );
		const wrapper = document.createElement( 'div' );
		wrapper.innerHTML = template.innerHTML.split( '__INDEX__' ).join( index );
		return wrapper.firstElementChild;
	}

	function addRepeaterRow( button ) {
		const repeater = button.closest( '.webinar-repeater' );
		if ( ! repeater ) {
			return;
		}
		const rows = repeater.querySelector( '.webinar-repeater-rows' );
		const row = makeRow( repeater.dataset.repeater );
		if ( rows && row ) {
			rows.appendChild( row );
		}
	}



	/* ------------------------------------------------------------------
	 * Block-level import / export (timeline / takeaways)
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'click', ( event ) => {
		const importBtn = event.target.closest( '.webinar-block-import-btn' );
		if ( importBtn ) {
			const actions = importBtn.closest( '.webinar-repeater-actions' );
			if ( actions ) {
				actions.querySelector( '.webinar-block-import-file' ).click();
			}
			return;
		}

		const exportBtn = event.target.closest( '.webinar-block-export' );
		if ( exportBtn ) {
			const repeater = exportBtn.closest( '.webinar-repeater' );
			if ( ! repeater ) {
				return;
			}
			const block = exportBtn.dataset.block;
			if ( 'timeline' === block ) {
				downloadJson( 'webinar-timeline.json', collectTimeline( repeater ) );
			} else if ( 'takeaways' === block ) {
				downloadJson( 'webinar-takeaways.json', {
					key_takeaway: ( document.getElementById( '_webinar_key_takeaway' ) || {} ).value || '',
					takeaways: collectTakeaways( repeater ),
				} );
			}
		}
	} );

	/* ------------------------------------------------------------------
	 * Copy shortcode from the list table
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'click', ( event ) => {
		const copyBtn = event.target.closest( '.webinar-copy-btn' );
		if ( ! copyBtn ) {
			return;
		}
		const wrap = copyBtn.closest( '.webinar-shortcode-wrap' );
		const input = wrap ? wrap.querySelector( 'input' ) : null;
		if ( ! input ) {
			return;
		}
		input.select();
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( input.value );
		}
	} );


	document.addEventListener( 'change', ( event ) => {
		if ( ! event.target.classList.contains( 'webinar-block-import-file' ) ) {
			return;
		}

		const file = event.target.files[ 0 ];
		const actions = event.target.closest( '.webinar-repeater-actions' );
		const repeater = actions ? actions.closest( '.webinar-repeater' ) : null;
		if ( ! file || ! repeater ) {
			return;
		}

		const reader = new FileReader();
		reader.onload = async () => {
			let data = null;
			try {
				data = JSON.parse( String( reader.result || '' ) );
			} catch ( error ) {
				data = null;
			}
			if ( ! data ) {
				return;
			}

			const mode = await askImportMode();
			if ( 'cancel' === mode ) {
				return;
			}

			const rowsBox = repeater.querySelector( '.webinar-repeater-rows' );
			if ( 'replace' === mode && rowsBox ) {
				rowsBox.innerHTML = '';
			}

			if ( 'timeline' === repeater.dataset.repeater ) {
				const rows = extractTimelineRows( data );
				if ( ! rows ) {
					window.alert( i18n.importFormatError || 'File format does not match this block.' );
					return;
				}

				if ( 'replace' === mode ) {
					const rowsBox = repeater.querySelector( '.webinar-repeater-rows' );
					if ( rowsBox ) {
						rowsBox.innerHTML = '';
					}
				}
				fillTimeline( repeater, rows );

			} else if ( 'takeaways' === repeater.dataset.repeater ) {
				const extracted = extractTakeaways( data );
				if ( ! extracted ) {
					window.alert( i18n.importFormatError || 'File format does not match this block.' );
					return;
				}

				// Rows: append or replace.
				if ( 'replace' === mode ) {
					const rowsBox = repeater.querySelector( '.webinar-repeater-rows' );
					if ( rowsBox ) {
						rowsBox.innerHTML = '';
					}
				}
				if ( extracted.list.length ) {
					fillTakeaways( repeater, extracted.list );
				}

				// Key takeaway logic.
				const keyInput = document.getElementById( '_webinar_key_takeaway' );
				if ( keyInput ) {
					const currentKey = keyInput.value.trim();
					const importedKey = extracted.key;

					if ( 'replace' === mode ) {
						// Replace mode: always use imported key (even if empty).
						keyInput.value = importedKey;
					} else {
						// Append mode: conditional update.
						if ( ! currentKey && importedKey ) {
							// Empty field + imported key → fill it.
							keyInput.value = importedKey;
						} else if ( currentKey && importedKey ) {
							// Both filled → ask user.
							const shouldUpdate = await askYesNo(
								i18n.updateKeyQuestion || 'Replace the key takeaway?'
							);
							if ( shouldUpdate ) {
								keyInput.value = importedKey;
							}
						}
						// If currentKey filled but no importedKey → do nothing.
					}
				}
			}
		};
		reader.readAsText( file );
		event.target.value = '';
	} );

	/**
	 * Small modal asking whether to append or replace rows on import.
	 * Uses the .postbox class so admin color schemes (incl. night mode)
	 * style it like any other meta box.
	 *
	 * @return {Promise<string>} 'append' | 'replace' | 'cancel'
	 */
	function askImportMode() {
		return new Promise( ( resolve ) => {
			const overlay = document.createElement( 'div' );
			overlay.className = 'webinar-import-modal';

			const box = document.createElement( 'div' );
			box.className = 'postbox webinar-import-modal-box';

			const text = document.createElement( 'p' );
			text.textContent = i18n.importModeQuestion || 'What should happen to the current rows?';

			const row = document.createElement( 'p' );
			row.className = 'webinar-import-modal-actions';
			[
				[ 'append', i18n.importModeAppend || 'Append to current rows', 'button button-primary' ],
				[ 'replace', i18n.importModeReplace || 'Replace current rows', 'button' ],
				[ 'cancel', i18n.importModeCancel || 'Cancel', 'button' ],
			].forEach( ( [ mode, label, cls ] ) => {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = cls;
				btn.dataset.mode = mode;
				btn.textContent = label;
				row.appendChild( btn );
			} );

			box.appendChild( text );
			box.appendChild( row );
			overlay.appendChild( box );
			document.body.appendChild( overlay );

			overlay.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( 'button[data-mode]' );
				if ( ! btn ) {
					return;
				}
				overlay.remove();
				resolve( btn.dataset.mode );
			} );
		} );
	}

	/**
	 * Yes/No confirmation modal (same look as the import mode modal).
	 *
	 * @param {string} question Question text.
	 * @return {Promise<boolean>} Resolves true on "Yes".
	 */
	function askYesNo( question ) {
		return new Promise( ( resolve ) => {
			const overlay = document.createElement( 'div' );
			overlay.className = 'webinar-import-modal';

			const box = document.createElement( 'div' );
			box.className = 'postbox webinar-import-modal-box';

			const text = document.createElement( 'p' );
			text.textContent = question;

			const row = document.createElement( 'p' );
			row.className = 'webinar-import-modal-actions';
			[
				[ 'yes', i18n.yesLabel || 'Yes', 'button button-primary' ],
				[ 'no', i18n.noLabel || 'No', 'button' ],
			].forEach( ( [ mode, label, cls ] ) => {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = cls;
				btn.dataset.mode = mode;
				btn.textContent = label;
				row.appendChild( btn );
			} );

			box.appendChild( text );
			box.appendChild( row );
			overlay.appendChild( box );
			document.body.appendChild( overlay );

			overlay.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( 'button[data-mode]' );
				if ( ! btn ) {
					return;
				}
				overlay.remove();
				resolve( 'yes' === btn.dataset.mode );
			} );
		} );
	}

	/**
	 * Extract valid timeline rows from an imported payload.
	 * Accepts a bare array or an object with a "timeline" key.
	 * A row counts only when BOTH time and title are non-empty.
	 *
	 * @param {*} payload Parsed JSON.
	 * @return {Array|null} Clean rows, or null when nothing usable.
	 */
	function extractTimelineRows( payload ) {
		const raw = Array.isArray( payload )
			? payload
			: ( payload && Array.isArray( payload.timeline ) ? payload.timeline : null );
		if ( ! raw ) {
			return null;
		}
		const rows = raw.filter( ( r ) => r && 'object' === typeof r
			&& String( r.time || '' ).trim() !== ''
			&& String( r.title || '' ).trim() !== '' );
		return rows.length ? rows : null;
	}

	/**
	 * Extract takeaways from an imported payload.
	 * Valid when at least one of key_takeaway / takeaways is present.
	 *
	 * @param {*} payload Parsed JSON.
	 * @return {Object|null} { list: string[], key: string } or null.
	 */
	function extractTakeaways( payload ) {
		// Only plain strings/numbers are valid takeaway texts. Objects.
		// (e.g. a timeline rows array) must NOT stringify to "[object Object]".
		const cleanList = ( arr ) => arr
			.filter( ( t ) => 'string' === typeof t || 'number' === typeof t )
			.map( ( t ) => String( t ).trim() )
			.filter( ( t ) => '' !== t );

		if ( Array.isArray( payload ) ) {
			const list = cleanList( payload );
			return list.length ? { list, key: '' } : null;
		}
		if ( ! payload || 'object' !== typeof payload ) {
			return null;
		}
		const list = Array.isArray( payload.takeaways ) ? cleanList( payload.takeaways ) : [];
		const keyRaw = payload.key_takeaway;
		const key = ( 'string' === typeof keyRaw || 'number' === typeof keyRaw )
			? String( keyRaw ).trim()
			: '';
		if ( ! list.length && ! key ) {
			return null;
		}
		return { list, key };
	}

	/* Helpers: collect / fill / download. */

	function collectTimeline( repeater ) {
		const list = [];
		repeater.querySelectorAll( '.webinar-repeater-row' ).forEach( ( row ) => {
			const get = ( suffix ) => {
				const el = row.querySelector( `[name$="[${ suffix }]"]` );
				return el ? el.value : '';
			};
			const time = get( 'time' ).trim();
			const title = get( 'title' ).trim();
			if ( ! time && ! title ) {
				return;
			}
			list.push( {
				time,
				title,
				short_desc: get( 'short_desc' ),
				long_desc: get( 'long_desc' ),
				clicks: parseInt( get( 'clicks' ), 10 ) || 0,
			} );
		} );
		return list;
	}

	function fillTimeline( repeater, list ) {
		const rows = repeater.querySelector( '.webinar-repeater-rows' );
		if ( ! rows ) {
			return;
		}
		list.forEach( ( item ) => {
			const row = makeRow( 'timeline' );
			if ( ! row ) {
				return;
			}
			setRowValue( row, 'time', item.time || '' );
			setRowValue( row, 'title', item.title || '' );
			setRowValue( row, 'short_desc', item.short_desc || '' );
			setRowValue( row, 'long_desc', item.long_desc || '' );
			setRowValue( row, 'clicks', item.clicks || 0 );
			rows.appendChild( row );
		} );
	}

	function collectTakeaways( repeater ) {
		const list = [];
		repeater.querySelectorAll( 'textarea[name^="_webinar_takeaways"]' ).forEach( ( ta ) => {
			const value = ta.value.trim();
			if ( value ) {
				list.push( value );
			}
		} );
		return list;
	}

	function fillTakeaways( repeater, list ) {
		const rows = repeater.querySelector( '.webinar-repeater-rows' );
		if ( ! rows ) {
			return;
		}
		list.forEach( ( text ) => {
			const row = makeRow( 'takeaways' );
			if ( ! row ) {
				return;
			}
			const ta = row.querySelector( 'textarea[name^="_webinar_takeaways"]' );
			if ( ta ) {
				ta.value = String( text );
			}
			rows.appendChild( row );
		} );
	}

	function setRowValue( row, suffix, value ) {
		const el = row.querySelector( `[name$="[${ suffix }]"]` );
		if ( el ) {
			el.value = value;
		}
	}

	function downloadJson( filename, data ) {
		const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
		const url = URL.createObjectURL( blob );
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = filename;
		document.body.appendChild( a );
		a.click();
		a.remove();
		URL.revokeObjectURL( url );
	}

	/* ------------------------------------------------------------------
	 * Media library pickers
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'click', ( event ) => {
		const selectBtn = event.target.closest( '.webinar-media-select' );
		if ( selectBtn ) {
			openMediaFrame( selectBtn );
			return;
		}

		const clearBtn = event.target.closest( '.webinar-media-clear' );
		if ( clearBtn ) {
			clearMediaField( clearBtn );
		}
	} );

	function openMediaFrame( button ) {
		const kind = button.dataset.kind || 'file';

		const frame = wp.media( {
			title: FRAME_TITLES[ kind ],
			library: FRAME_LIBRARIES[ kind ],
			multiple: false,
			button: { text: FRAME_TITLES[ kind ] },
		} );

		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			setMediaField( button.dataset.target, attachment, kind );
		} );

		frame.open();
	}


	function setMediaField( inputId, attachment, kind ) {
		const input = document.getElementById( inputId );
		const preview = document.querySelector(
			`.webinar-media-preview[data-target="${ inputId }"]`
		);

		if ( input ) {
			input.value = attachment.id;
		}
		renderPreview( preview, attachment, kind );
	}

	function renderPreview( previewEl, attachment, kind ) {
		if ( ! previewEl ) {
			return;
		}

		previewEl.innerHTML = '';

		if ( 'icon' === kind ) {
			const img = document.createElement( 'img' );
			img.src = attachment.url;
			img.alt = '';
			img.className = 'webinar-preview-icon';
			previewEl.appendChild( img );
			return;
		}

		if ( 'poster' === kind ) {
			const img = document.createElement( 'img' );
			img.src = attachment.url;
			img.alt = '';
			img.className = 'webinar-preview-image';
			previewEl.appendChild( img );
			return;
		}

		if ( 'video' === kind ) {
			const video = document.createElement( 'video' );
			video.src = attachment.url;
			video.className = 'webinar-preview-video';
			video.controls = true;
			video.muted = true;
			video.preload = 'metadata';
			previewEl.appendChild( video );
			return;
		}

		const name = document.createElement( 'span' );
		name.className = 'description';
		name.textContent = attachment.filename || attachment.url;
		previewEl.appendChild( name );
	}

	function clearMediaField( button ) {
		const inputId = button.dataset.target;
		const input = document.getElementById( inputId );
		const preview = document.querySelector(
			`.webinar-media-preview[data-target="${ inputId }"]`
		);

		if ( input ) {
			input.value = '';
		}
		if ( preview ) {
			if ( 'icon' === preview.dataset.kind ) {
				renderDefaultIcon( preview );
			} else {
				preview.innerHTML = '';
			}
		}
	}

	function renderDefaultIcon( previewEl ) {
		previewEl.innerHTML = '';

		const wrap = document.createElement( 'div' );
		wrap.className = 'webinar-preview-default';

		const box = document.createElement( 'span' );
		box.className = 'webinar-preview-icon';
		box.innerHTML = i18n.defaultIcon || '';

		const label = document.createElement( 'span' );
		label.className = 'description';
		label.textContent = i18n.defaultLabel || '(default)';

		wrap.appendChild( box );
		wrap.appendChild( label );
		previewEl.appendChild( wrap );
	}

	/* ------------------------------------------------------------------
	 * Icon size radios: live preview resize
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'change', ( event ) => {
		const radio = event.target;
		if ( 'radio' !== radio.type || ! radio.closest( '.webinar-icon-size' ) ) {
			return;
		}
		const box = radio.closest( '.webinar-icon-size' );
		const target = box ? box.dataset.previewTarget : '';
		const preview = target
			? document.querySelector( `.webinar-media-preview[data-target="${ target }"]` )
			: null;
		if ( preview ) {
			preview.classList.toggle( 'webinar-preview--large', 'large' === radio.value );
		}
	} );

	/* ------------------------------------------------------------------
	 * MP4 / HLS toggle
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'change', ( event ) => {
		if ( 'radio' !== event.target.type || '_webinar_video_type' !== event.target.name ) {
			return;
		}

		document.querySelectorAll( '.webinar-admin-row[data-video-type]' ).forEach( ( row ) => {
			row.style.display = row.dataset.videoType === event.target.value ? '' : 'none';
		} );
	} );

	/* ------------------------------------------------------------------
	 * Player type toggle: the share checkbox belongs to the custom
	 * player only, so it hides when native controls are selected.
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'change', ( event ) => {
		if ( 'radio' !== event.target.type || '_webinar_player_type' !== event.target.name ) {
			return;
		}

		document.querySelectorAll( '.webinar-admin-row[data-player-type]' ).forEach( ( row ) => {
			row.style.display = row.dataset.playerType === event.target.value ? '' : 'none';
		} );
	} );


	/* ------------------------------------------------------------------
	 * HLS signing presets
	 * ---------------------------------------------------------------- */

	const HLS_PRESETS = {
		nginx: {
			hash: '%expires%%path% %key%',
			sign: '?md5=%md5%&expires=%expires%',
		},
		cloudfront: {
			hash: '%path%?Expires=%expires%&Key-Pair-Id=%key%',
			sign: '?Expires=%expires%&Signature=%md5%&Key-Pair-Id=%key%',
		},
		fastly: {
			hash: '%key%:expires=%expires%:path=%path%',
			sign: '?hdnts=expires=%expires%~hmac=%md5%',
		},
		custom: {
			hash: '',
			sign: '',
		},
	};

	document.addEventListener( 'change', ( event ) => {
		if ( '_webinar_video_hls_preset' !== event.target.name ) {
			return;
		}
		const preset = HLS_PRESETS[ event.target.value ];
		if ( ! preset ) {
			return;
		}
		const hashInput = document.getElementById( '_webinar_video_hls_hash_template' );
		const signInput = document.getElementById( '_webinar_video_hls_sign_template' );
		if ( hashInput ) {
			hashInput.value = preset.hash;
		}
		if ( signInput ) {
			signInput.value = preset.sign;
		}
		if ( 'custom' === event.target.value && hashInput ) {
			hashInput.focus();
		}
	} );

	/* Required signing templates when HLS is selected. */
	document.addEventListener( 'submit', ( event ) => {
		const form = event.target;
		if ( ! form || 'post' !== form.id ) {
			return;
		}
		const hlsRadio = form.querySelector( 'input[name="_webinar_video_type"][value="hls"]' );
		if ( ! hlsRadio || ! hlsRadio.checked ) {
			return;
		}
		const hashInput = document.getElementById( '_webinar_video_hls_hash_template' );
		const signInput = document.getElementById( '_webinar_video_hls_sign_template' );
		if ( hashInput && signInput && ( '' === hashInput.value.trim() || '' === signInput.value.trim() ) ) {
			event.preventDefault();
			window.alert( i18n.hlsTemplatesRequired || 'Hash template and signature template are required for HLS.' );
			hashInput.focus();
		}
	}, true );



	/* ------------------------------------------------------------------
	 * Theme
	 * ---------------------------------------------------------------- */

	const PRESET_COLORS = window.webinarBlockAdmin?.presetColors || {};

	document.addEventListener( 'change', ( event ) => {
		if ( ! event.target.classList.contains( 'webinar-theme-radio' ) ) {
			return;
		}
		
		const theme = event.target.value;
		const colorPickers = document.querySelectorAll( '.webinar-color-picker' );
		const importBtn = document.querySelector( '.webinar-theme-import-btn' );
		
		const isCustom = 'custom' === theme;
		
		// Enable/disable pickers and import button based on theme
		colorPickers.forEach( ( picker ) => {
			picker.disabled = ! isCustom;
		} );
		
		if ( importBtn ) {
			importBtn.disabled = ! isCustom;
		}
		
		// Apply preset colors immediately
		if ( PRESET_COLORS[ theme ] ) {
			Object.keys( PRESET_COLORS[ theme ] ).forEach( ( key ) => {
				const input = document.querySelector(
					`input[name="_webinar_theme_custom[${ key }]"]`
				);
				const valueEl = input ? input.nextElementSibling : null;
				
				if ( input ) {
					// Only update if theme changed (not custom)
					if ( ! isCustom ) {
						input.value = PRESET_COLORS[ theme ][ key ];
					}
					if ( valueEl ) {
						valueEl.textContent = input.value.toUpperCase();
					}
				}
			} );
		}
	} );

	// Update hex value on color change
	document.addEventListener( 'input', ( event ) => {
		if ( event.target.classList.contains( 'webinar-color-picker' ) ) {
			const valueEl = event.target.nextElementSibling;
			if ( valueEl ) {
				valueEl.textContent = event.target.value.toUpperCase();
			}
		}
	} );


	/* ------------------------------------------------------------------
	 * Import buttons: trigger hidden file inputs
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'click', ( event ) => {
		const layoutBtn = event.target.closest( '.webinar-layout-import-btn' );
		if ( layoutBtn ) {
			const field = layoutBtn.closest( '.webinar-layout-field' );
			if ( field ) {
				field.querySelector( '.webinar-layout-import-file' ).click();
			}
			return;
		}

		const themeBtn = event.target.closest( '.webinar-theme-import-btn' );
		if ( themeBtn ) {
			document.querySelector( '.webinar-theme-import-file' ).click();
			return;
		}

		const settingsBtn = event.target.closest( '.webinar-settings-import-btn' );
		if ( settingsBtn ) {
			document.querySelector( '.webinar-settings-import-file' ).click();
		}
	} );


	/**
	 * Current webinar post ID.
	 *
	 * On the edit screen it comes from PHP; on "Add New" the page has no
	 * ?post= param, but WordPress already created an auto-draft whose ID
	 * sits in the hidden #post_ID input (or in the block editor store).
	 */
	function currentPostId() {
		if ( window.webinarBlockAdmin.postId ) {
			return window.webinarBlockAdmin.postId;
		}
		const hidden = document.getElementById( 'post_ID' );
		if ( hidden ) {
			return parseInt( hidden.value, 10 ) || 0;
		}
		if ( window.wp && window.wp.data && window.wp.data.select ) {
			const editor = window.wp.data.select( 'core/editor' );
			if ( editor && editor.getCurrentPostId ) {
				return editor.getCurrentPostId() || 0;
			}
		}
		return 0;
	}


	/* ------------------------------------------------------------------
	 * Import file handlers
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'change', ( event ) => {

		// Theme JSON import: fill palette + switch theme to custom.
		if ( event.target.classList.contains( 'webinar-theme-import-file' ) ) {
			const file = event.target.files[ 0 ];
			if ( ! file ) {
				return;
			}
			const reader = new FileReader();
			reader.onload = () => {
				let data = null;
				try {
					data = JSON.parse( String( reader.result || '' ) );
				} catch ( error ) {
					data = null;
				}
				if ( ! data || ! data.colors ) {
					return;
				}

				// Switch to 'custom' theme radio button
				const customRadio = document.querySelector( '.webinar-theme-radio[value="custom"]' );
				if ( customRadio ) {
					customRadio.checked = true;
					// Trigger change event to update UI (enable inputs and import button)
					customRadio.dispatchEvent( new Event( 'change' ) );
				}

				Object.keys( data.colors ).forEach( ( key ) => {
					const input = document.querySelector(
						`input[name="_webinar_theme_custom[${ key }]"]`
					);
					if ( input && /^#[0-9a-fA-F]{6}$/.test( data.colors[ key ] ) ) {
						input.value = data.colors[ key ];
						// Update hex value display
						const valueEl = input.nextElementSibling;
						if ( valueEl && valueEl.classList.contains( 'webinar-color-value' ) ) {
							valueEl.textContent = data.colors[ key ].toUpperCase();
						}
					}
				} );
			};
			reader.readAsText( file );
			event.target.value = '';
			return;
		}

		// Webinar settings JSON import: apply server-side, then reload.
		if ( event.target.classList.contains( 'webinar-settings-import-file' ) ) {
			const file = event.target.files[ 0 ];
			const status = document.querySelector( '.webinar-settings-import-status' );
			if ( ! file ) {
				return;
			}
			const reader = new FileReader();
			reader.onload = () => {
				const body = new FormData();
				body.append( 'action', 'webinar_import_settings' );
				body.append( 'nonce', window.webinarBlockAdmin.nonce || '' );
				body.append( 'post_id', currentPostId() );
				body.append( 'payload', String( reader.result || '' ) );

				fetch( window.ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					body,
				} )
					.then( ( response ) => response.text() )
					.then( ( text ) => {
						let json = null;
						try {
							json = JSON.parse( text );
						} catch ( error ) {
							json = null;
						}

						if ( json && json.success ) {
							if ( status ) {
								status.textContent = window.webinarBlockAdmin.imported || 'Imported. Reloading…';
							}
							// Server either filled an existing webinar or created a
							// fresh draft; open its edit screen.
							const pid = json.data && json.data.post_id ? json.data.post_id : currentPostId();
							window.location.href =
								( window.webinarBlockAdmin.postEditBase || 'post.php' ) +
								'?post=' + pid + '&action=edit';
							return;
						}

						// Diagnostics: show the raw server answer.
						const detail = json && json.data ? JSON.stringify( json.data ) : text.slice( 0, 200 );
						if ( status ) {
							status.textContent = ( window.webinarBlockAdmin.importError || 'Import failed.' ) + ' [' + detail + ']';
						}
						console.error( 'Webinar import raw response:', text );
					} )
					.catch( ( error ) => {
						if ( status ) {
							status.textContent = ( window.webinarBlockAdmin.importError || 'Import failed.' ) + ' [' + error.message + ']';
						}
						console.error( error );
					} );
			};
			reader.readAsText( file );
			event.target.value = '';
		}
	} );


	/* ------------------------------------------------------------------
	 * Layout Schemes: tabs, custom CSS toggle, validation, import/export
	 * ---------------------------------------------------------------- */

	const LAYOUT_PRESETS = window.webinarBlockAdmin?.layoutPresets || {};
	const THEME_COLORS = window.webinarBlockAdmin?.presetColors || {};

	function getDefaultColors() {
		return {
			primary: '#014993',
			primary_hover: '#003a75',
			secondary: '#3a7bc8',
			accent: '#002d5c',
			bg_main: '#ffffff',
			bg_tile: '#f9fafb',
			bg_content: '#ffffff',
			bg_button: '#014993',
			border: '#e5e7eb',
			text_primary: '#1e3a5f',
			text_secondary: '#4b5563',
			text_on_primary: '#ffffff',
			text_muted: '#667085',
			text_inverse: '#ffffff',
			tab_active: '#002d5c',
			timeline_active: '#c7e2fc',
			eye_counter: '#667085',
			badge_bg: '#014993',
		};
	}

	function getCurrentThemeColors() {
		// 1. Try to get from color pickers first (for Custom theme live updates)
		const customColors = {};
		const colorInputs = document.querySelectorAll( '.webinar-color-picker' );
		colorInputs.forEach( ( input ) => {
			const name = input.name ? input.name.replace( '_webinar_theme_custom[', '' ).replace( ']', '' ) : '';
			if ( name && input.value ) {
				customColors[ name ] = input.value;
			}
		} );

		// If we have custom colors, merge them with defaults
		if ( Object.keys( customColors ).length > 0 ) {
			return { ...getDefaultColors(), ...customColors };
		}

		// 2. Otherwise, get from theme radio buttons (Preset themes)
		const themeRadio = document.querySelector( '.webinar-theme-radio:checked' );
		const theme = themeRadio ? themeRadio.value : 'standard';
		const presetColors = THEME_COLORS[ theme ] || THEME_COLORS.standard || {};

		// 3. Merge with defaults to ensure all variables are always present
		return { ...getDefaultColors(), ...presetColors };
	}

	function renderSchemePreview( block, scheme, customCss ) {
		const previewContainer = document.querySelector( `.webinar-scheme-preview[data-block="${ block }"]` );
		if ( ! previewContainer ) {
			return;
		}

		previewContainer.innerHTML = '';

		if ( ! scheme ) {
			previewContainer.innerHTML = `<div class="webinar-scheme-preview-placeholder">${ I18N.previewNotAvailable || 'Preview not available' }</div>`;
			return;
		}

		let html = '';
		const data = PREVIEW_DATA[ block ] || [];

		if ( 'timeline' === block ) {
			html = '<ul class="webinar-timeline-list">';
			data.forEach( ( item, i ) => {
				html += `<li class="webinar-timeline-item${ 0 === i ? ' webinar-timeline-active' : '' }" data-time="0">
					<a href="#" class="webinar-timeline-link">
						<span class="webinar-timeline-time">${ item.time }</span>
						<span class="webinar-timeline-title">${ item.title }</span>
					</a>
					<p class="webinar-timeline-desc">${ item.short_desc }</p>
				</li>`;
			} );
			html += '</ul>';
		} else if ( 'takeaways' === block ) {
			html = '<ul class="webinar-takeaways-grid">';
			data.forEach( ( text ) => {
				html += `<li>${ text }</li>`;
			} );
			html += '</ul>';
		} else if ( 'materials' === block ) {
			html = '<div class="webinar-materials-list">';
			data.forEach( ( item ) => {
				html += `<div class="webinar-material-item">
					<p class="webinar-material-desc">${ item.description }</p>
					<div class="webinar-materials">
						<a href="#">${ item.button_text }</a>
					</div>
				</div>`;
			} );
			html += '</div>';
		} else if ( 'details' === block ) {
			html = '<details class="webinar-details-wrap" open><summary class="webinar-details-summary"><span>' + ( I18N.previewDetailedContent || 'Detailed content' ) + '</span></summary><div class="webinar-details-body"><div class="webinar-details-columns">';
			data.forEach( ( item ) => {
				html += `<div class="webinar-detail-block" data-time="0">
					<h4><a href="#" class="webinar-detail-time-link"><span class="webinar-detail-time">${ item.time }</span></a> ${ item.title }</h4>
					<p>${ item.long_desc }</p>
				</div>`;
			} );
			html += '</div></div></details>';
		}

		const wrapper = document.createElement( 'div' );
		wrapper.className = 'webinar-widget webinar-scheme-preview-widget';
		wrapper.innerHTML = html;

		// Apply theme colors as CSS variables
		const colors = getCurrentThemeColors();
		const colorVars = [
			'--webinar-primary', '--webinar-primary-hover', '--webinar-secondary', '--webinar-accent',
			'--webinar-bg-main', '--webinar-bg-tile', '--webinar-bg-content', '--webinar-bg-button', '--webinar-border',
			'--webinar-text-primary', '--webinar-text-secondary', '--webinar-text-on-primary', '--webinar-text-muted', '--webinar-text-inverse',
			'--webinar-tab-active', '--webinar-timeline-active', '--webinar-eye-counter', '--webinar-badge-bg'
		];
		
		colorVars.forEach( ( varName ) => {
			const colorKey = varName.replace( '--webinar-', '' ).replace( /-/g, '_' );
			if ( colors[ colorKey ] ) {
				wrapper.style.setProperty( varName, colors[ colorKey ] );
			}
		} );

		if ( customCss ) {
			const style = document.createElement( 'style' );
			style.textContent = customCss;
			wrapper.appendChild( style );
		}

		previewContainer.appendChild( wrapper );
	}

	function updatePreviewForPanel( panel ) {
		const block = panel.dataset.block;
		const radio = panel.querySelector( '.webinar-scheme-radio:checked' );
		const scheme = radio ? radio.value : 'classic';
		const textarea = panel.querySelector( '.webinar-scheme-css' );
		
		// For custom scheme, use textarea CSS; for presets, use LAYOUT_PRESETS
		let css = '';
		if ( 'custom' === scheme && textarea ) {
			css = textarea.value;
		} else if ( LAYOUT_PRESETS[ block ] && LAYOUT_PRESETS[ block ][ scheme ] ) {
			css = LAYOUT_PRESETS[ block ][ scheme ];
		}
		
		renderSchemePreview( block, scheme, css );
	}

	// Initialize previews on page load
	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '.webinar-scheme-panel.active' ).forEach( ( panel ) => {
			updatePreviewForPanel( panel );
		} );
	} );


	// Prevent link navigation in preview
	document.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( '.webinar-scheme-preview-widget a' );
		if ( link ) {
			event.preventDefault();
			event.stopPropagation();
		}
	}, true );


	// Update preview when color theme changes
	document.addEventListener( 'change', ( event ) => {
		if ( ! event.target.classList.contains( 'webinar-theme-radio' ) ) {
			return;
		}

		// Update all active previews
		setTimeout( () => {
			document.querySelectorAll( '.webinar-scheme-panel.active' ).forEach( ( panel ) => {
				updatePreviewForPanel( panel );
			} );
		}, 100 );
	} );

	// Update preview when color pickers change (live update)
	document.addEventListener( 'input', ( event ) => {
		if ( ! event.target.classList.contains( 'webinar-color-picker' ) ) {
			return;
		}

		document.querySelectorAll( '.webinar-scheme-panel.active' ).forEach( ( panel ) => {
			updatePreviewForPanel( panel );
		} );
	} );


	// Tab switching
	document.addEventListener( 'click', ( event ) => {
		const tab = event.target.closest( '.webinar-scheme-tab' );
		if ( ! tab ) {
			return;
		}

		const block = tab.dataset.block;
		const wrapper = tab.closest( '.webinar-schemes-wrapper' );

		// Deactivate all tabs and panels
		wrapper.querySelectorAll( '.webinar-scheme-tab' ).forEach( ( t ) => t.classList.remove( 'active' ) );
		wrapper.querySelectorAll( '.webinar-scheme-panel' ).forEach( ( p ) => p.classList.remove( 'active' ) );

		// Activate selected
		tab.classList.add( 'active' );
		const panel = wrapper.querySelector( `.webinar-scheme-panel[data-block="${ block }"]` );
		if ( panel ) {
			panel.classList.add( 'active' );
			updatePreviewForPanel( panel );
		}
	} );

	// Scheme radio change: toggle custom CSS visibility and update preview
	document.addEventListener( 'change', ( event ) => {
		if ( ! event.target.classList.contains( 'webinar-scheme-radio' ) ) {
			return;
		}

		const panel = event.target.closest( '.webinar-scheme-panel' );
		if ( ! panel ) {
			return;
		}

		const scheme = event.target.value;
		const block = panel.dataset.block;
		const customBox = panel.querySelector( '.webinar-scheme-custom' );
		const applyBtn = panel.querySelector( '.webinar-scheme-apply' );
		const importBtn = panel.querySelector( '.webinar-scheme-import-btn' );
		const textarea = panel.querySelector( '.webinar-scheme-css' );
		const radios = panel.querySelectorAll( '.webinar-scheme-radio' ); // ← ДОБАВЛЕНО

		if ( customBox ) {
			customBox.style.display = 'custom' === scheme ? '' : 'none';
		}
		if ( applyBtn ) {
			applyBtn.disabled = 'custom' !== scheme;
		}
		if ( importBtn ) {
			importBtn.disabled = 'custom' !== scheme;
		}

		// When switching to custom, fill textarea with preset CSS
		if ( 'custom' === scheme && textarea && LAYOUT_PRESETS[ block ] ) {
			// Find the last non-custom scheme that was selected
			let lastPreset = 'classic';
			radios.forEach( ( radio ) => {
				if ( radio !== event.target && radio.dataset.lastSelected ) {
					lastPreset = radio.value;
				}
			} );

			// If we have a preset for this scheme, use it
			if ( LAYOUT_PRESETS[ block ][ lastPreset ] ) {
				textarea.value = LAYOUT_PRESETS[ block ][ lastPreset ];
			}
		}

		// Mark this radio as last selected
		event.target.dataset.lastSelected = 'true';
		radios.forEach( ( radio ) => {
			if ( radio !== event.target ) {
				delete radio.dataset.lastSelected;
			}
		} );

		// Update preview
		updatePreviewForPanel( panel );
	} );

	// Apply custom CSS button
	document.addEventListener( 'click', ( event ) => {
		const applyBtn = event.target.closest( '.webinar-scheme-apply' );
		if ( ! applyBtn ) {
			return;
		}

		const panel = applyBtn.closest( '.webinar-scheme-panel' );
		if ( ! panel ) {
			return;
		}

		const block = applyBtn.dataset.block;
		const textarea = panel.querySelector( '.webinar-scheme-css' );
		const statusEl = panel.querySelector( '.webinar-scheme-css-status' );

		if ( ! textarea || ! statusEl ) {
			return;
		}

		const css = textarea.value.trim();

		// Validate CSS
		const validation = validateCustomCss( css );
		if ( ! validation.valid ) {
			statusEl.textContent = validation.message;
			statusEl.className = 'webinar-scheme-css-status error';
			return;
		}

		// Update preview
		renderSchemePreview( block, 'custom', css );
		statusEl.textContent = I18N.previewUpdated || 'Preview updated';
		statusEl.className = 'webinar-scheme-css-status success';

		setTimeout( () => {
			statusEl.textContent = '';
		}, 3000 );
	} );

	// CSS validation
	function validateCustomCss( css ) {
		if ( ! css ) {
			return { valid: true, message: '' };
		}

		// 1) Dangerous constructs.
		const dangerous = [
			/@import\s/i,
			/expression\s*\(/i,
			/javascript\s*:/i,
			/url\s*\(\s*['"]?https?:\/\//i,
		];
		for ( const pattern of dangerous ) {
			if ( pattern.test( css ) ) {
				return { valid: false, message: ( I18N.cssDangerousPattern || 'Dangerous pattern detected' ) + ': ' + pattern.source };
			}
		}

		// 2) Brace balance.
		const open = ( css.match( /\{/g ) || [] ).length;
		const close = ( css.match( /\}/g ) || [] ).length;
		if ( open !== close ) {
			return { valid: false, message: I18N.cssSyntaxError || 'Syntax error: mismatched braces' };
		}

		// 3) Per-selector checks.
		const knownTags = [ 'a', 'b', 'blockquote', 'br', 'button', 'code', 'details', 'div', 'em', 'figure', 'figcaption', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'small', 'span', 'strong', 'sub', 'summary', 'sup', 'svg', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'ul', 'video' ];
		const selRe = /(^|[{}])\s*([^{}]+?)\s*\{/g;
		let m;
		while ( ( m = selRe.exec( css ) ) !== null ) {
			const selectorText = m[ 2 ].trim();
			if ( ! selectorText || '@' === selectorText.charAt( 0 ) ) {
				continue; // skip @media / @keyframes headers
			}
			for ( let sel of selectorText.split( ',' ) ) {
				sel = sel.trim();
				if ( ! sel ) {
					continue;
				}

				// 3a) Unknown tags (garbage before a class, typos, etc.)
				const tags = sel.match( /(^|[\s>+,(])([a-zA-Zа-яА-ЯёЁ][a-zA-Z0-9а-яА-ЯёЁ-]*)/g ) || [];
				for ( const raw of tags ) {
					const tag = raw.trim().toLowerCase();
					if ( tag && -1 === knownTags.indexOf( tag ) ) {
						return { valid: false, message: ( I18N.cssUnknownTag || 'Unknown element in selector' ) + ': "' + tag + '" → ' + sel };
					}
				}

				// 3b) Pure CSS syntax probe (strip pseudo-classes/elements first)
				const probe = sel.replace( /::?[a-zA-Z-]+(\([^)]*\))?/g, '' ).trim();
				if ( probe ) {
					try {
						document.querySelector( probe );
					} catch ( e ) {
						return { valid: false, message: ( I18N.cssBadSelector || 'Invalid selector' ) + ': ' + sel };
					}
				}
			}
		}

		return { valid: true, message: '' };
	}

	// Import scheme button
	document.addEventListener( 'click', ( event ) => {
		const importBtn = event.target.closest( '.webinar-scheme-import-btn' );
		if ( ! importBtn ) {
			return;
		}

		const panel = importBtn.closest( '.webinar-scheme-panel' );
		if ( ! panel ) {
			return;
		}

		const fileInput = panel.querySelector( '.webinar-scheme-import-file' );
		if ( fileInput ) {
			fileInput.click();
		}
	} );

	// Import scheme file handler
	document.addEventListener( 'change', ( event ) => {
		if ( ! event.target.classList.contains( 'webinar-scheme-import-file' ) ) {
			return;
		}

		const file = event.target.files[ 0 ];
		const panel = event.target.closest( '.webinar-scheme-panel' );
		if ( ! file || ! panel ) {
			return;
		}

		const reader = new FileReader();
		reader.onload = () => {
			let data = null;
			try {
				data = JSON.parse( String( reader.result || '' ) );
			} catch ( error ) {
				data = null;
			}

			if ( ! data || ! data.block || ! data.css ) {
				return;
			}

			// Switch to custom scheme
			const customRadio = panel.querySelector( '.webinar-scheme-radio[value="custom"]' );
			if ( customRadio ) {
				customRadio.checked = true;
				customRadio.dispatchEvent( new Event( 'change' ) );
			}

			// Fill CSS
			const textarea = panel.querySelector( '.webinar-scheme-css' );
			if ( textarea ) {
				textarea.value = data.css;
			}

			// Update preview
			const block = panel.dataset.block;
			renderSchemePreview( block, 'custom', data.css );
		};
		reader.readAsText( file );
		event.target.value = '';
	} );


} )();