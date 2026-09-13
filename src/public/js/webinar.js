/**
 * Webinar Block — front-end bootstrap.
 *
 * Phase 4: HLS playback via hls.js with ABR and nginx secure_link
 * signed segments. MP4 plays natively. Tabs/sliders arrive in Phase 5.
 */
import Hls from 'hls.js';

( function () {
	'use strict';

	const front = window.webinarBlockFront || {};
	const signedCache = {};
	const signFailures = {}; // Circuit breaker: total 403/410 per URL.

	/**
	 * Bind native video state events that the custom controls consume.
	 * Works for both MP4 and native HLS (Safari). HLS.js has its own
	 * dispatching path inside initWidget.
	 *
	 * @param {HTMLVideoElement} video Video element.
	 */
	function bindStateEvents( video ) {
		video.addEventListener( 'waiting', function () {
			video.dispatchEvent( new CustomEvent( 'webinar:buffering' ) );
		} );
		video.addEventListener( 'playing', function () {
			video.dispatchEvent( new CustomEvent( 'webinar:ready' ) );
		} );
		video.addEventListener( 'canplay', function () {
			video.dispatchEvent( new CustomEvent( 'webinar:ready' ) );
		} );
		video.addEventListener( 'error', function () {
			video.dispatchEvent( new CustomEvent( 'webinar:error', { detail: { source: 'native' } } ) );
		} );
		// Initial buffering state if autoplay or user seeks before ready.
		if ( video.readyState < 3 && video.currentSrc ) {
			video.dispatchEvent( new CustomEvent( 'webinar:buffering' ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Signed URL helper
	 * ---------------------------------------------------------------- */

	function fetchSignedUrl( url, webinarId ) {
		if ( signedCache[ url ] ) {
			return Promise.resolve( signedCache[ url ] );
		}

		const body = new FormData();
		body.append( 'action', 'webinar_sign_segment' );
		body.append( 'nonce', front.nonce || '' );
		body.append( 'webinar_id', webinarId );
		body.append( 'url', url );

		return fetch( front.ajaxUrl || window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} )
			.then( ( response ) => response.json() )
			.then( ( json ) => {
				if ( json && json.success && json.data && json.data.url ) {
					signedCache[ url ] = json.data.url;
					return json.data.url;
				}
				throw new Error( 'webinar: sign failed' );
			} );
	}

	/**
	 * Build a loader class bound to one widget: every request whose URL
	 * starts with the segment base URL is signed first.
	 *
	 * Supports:
	 *  - `%t` in the URL → replaced with current timestamp (anti-cache).
	 *  - Retry on 403 / 410 (expired signature): drop cache, re-sign, retry
	 *    up to `maxRetries` times with `retryDelay` ms between attempts.
	 *
	 * @param {string} baseUrl    Segment base URL.
	 * @param {string} webinarId  Webinar post ID.
	 * @return {Function} Loader class for hls.js config.
	 */
	function makeSignedLoader( baseUrl, webinarId ) {
		const MAX_RETRIES = 10;
		const RETRY_DELAY = 500;
		const MAX_SIGN_FAILURES = 6;

		return class SignedLoader extends Hls.DefaultConfig.loader {
			constructor( config ) {
				super( config );
				this.webinarRetryCount = 0;
				this.webinarDestroyed = false;
			}

			destroy() {
				this.webinarDestroyed = true;
				super.destroy();
			}

			load( context, config, callbacks ) {
				const originalUrl = context.url;

				if ( baseUrl && 0 === originalUrl.indexOf( baseUrl ) ) {
					// Substitute %t with current timestamp (anti-cache for playlists).
					const processedUrl = originalUrl.replace( /%t/g, String( Math.floor( Date.now() / 1000 ) ) );
					context.url = processedUrl;

					const loader = this;
					const originalOnError = callbacks.onError;

					callbacks.onError = ( response, retryContext, details ) => {
						const code = response && response.code ? response.code : 0;
						// 403 = missing/invalid signature, 410 = expired signature
						// (nginx secure_link convention).
						if ( ( 403 === code || 410 === code ) && loader.webinarRetryCount < MAX_RETRIES ) {
							loader.webinarRetryCount += 1;
							// Circuit breaker: hls.js spawns a fresh loader per
							// request, so a per-loader counter cannot stop a global
							// retry storm (e.g. signing misconfiguration). Cap total
							// failures per URL and surface the error overlay instead.
							signFailures[ originalUrl ] = ( signFailures[ originalUrl ] || 0 ) + 1;
							if ( signFailures[ originalUrl ] > MAX_SIGN_FAILURES ) {
								if ( 'function' === typeof originalOnError ) {
									originalOnError( response, retryContext, details );
								}
								return;
							}
							delete signedCache[ originalUrl ];
							delete signedCache[ processedUrl ];
							setTimeout( () => {
								if ( loader.webinarDestroyed ) {
									return;
								}
								loader.load( context, config, callbacks );
							}, RETRY_DELAY );
							return;
						}
						loader.webinarRetryCount = 0;
						if ( 'function' === typeof originalOnError ) {
							originalOnError( response, retryContext, details );
						}
					};

					const originalOnSuccess = callbacks.onSuccess;
					callbacks.onSuccess = ( response, stats, contextArg, networkDetails ) => {
						loader.webinarRetryCount = 0;
						signFailures[ originalUrl ] = 0;
						if ( 'function' === typeof originalOnSuccess ) {
							originalOnSuccess( response, stats, contextArg, networkDetails );
						}
					};

					fetchSignedUrl( processedUrl, webinarId )
						.then( ( signed ) => {
							// hls.js may abort/destroy the loader while the signature
							// request is in flight (rapid seeks). A deferred
							// super.load() on a destroyed loader throws TypeError.
							if ( this.webinarDestroyed ) {
								return;
							}
							context.url = signed;
							super.load( context, config, callbacks );
						} )
						.catch( () => {
							if ( this.webinarDestroyed ) {
								return;
							}
							// Try unsigned rather than fail hard (e.g. dev envs).
							super.load( context, config, callbacks );
						} );
					return;
				}

				super.load( context, config, callbacks );
			}

		};
	}
	/* ------------------------------------------------------------------
	 * Player init
	 * ---------------------------------------------------------------- */

	function initWidget( widget ) {
		const video = widget.querySelector( 'video' );
		if ( ! video || video.dataset.webinarInit ) {
			return;
		}
		video.dataset.webinarInit = '1';

		// Always bind state events — both MP4 and HLS need buffering/error UX.
		bindStateEvents( video );

		if ( 'hls' !== video.dataset.videoType ) {
			return; // MP4: native playback, nothing to do.
		}

		const manifest = video.dataset.manifest;
		if ( ! manifest ) {
			return;
		}
		const baseUrl = video.dataset.baseUrl || '';
		// Prefer the widget's own id; fall back to the nearest holder —
		// the player wrapper carries data-webinar-id, so a Gutenberg group
		// reusing the .webinar-widget class cannot zero-out the id.
		const idHost = widget.dataset.webinarId
			? widget
			: ( video.closest( '[data-webinar-id]' ) || widget );
		const webinarId = idHost.getAttribute( 'data-webinar-id' ) || '0';

		if ( Hls.isSupported() ) {
			// Tight buffer caps: on a page with many videos, each player
			// must not flood the connection. The defaults (30–60s) kill
			// weak networks when 10–20 instances boot at once.
			const hls = new Hls( {
				loader: makeSignedLoader( baseUrl, webinarId ),
				backBufferLength: 10,
				maxBufferLength: 30,  // Keep 30s ahead
				maxMaxBufferLength: 60,   // Never exceed 60s 
				maxBufferSize: 30 * 1000 * 1000,
				// Prime with the cheapest level: one small fragment per video
				// on page load, then the network idles until interaction.
				startLevel: 0,
			} );

			// Expose the HLS instance for controls (quality selector, retry).
			video.webinarHls = hls;

			// Prime mode: manifest + ONE fragment (lowest level) load on page
			// load so native controls leave the spinner state and the saved
			// position can render its frame. Then the network idles until
			// the user plays or seeks.
			video.webinarHlsStopped = false;
			video.webinarHlsUserStarted = false;
			let primeStopDone = false;

			// Forward level switches so controls can sync the quality label
			// without importing hls.js.
			hls.on( Hls.Events.LEVEL_SWITCHED, function () {
				video.dispatchEvent( new CustomEvent( 'webinar:levelswitched', {
					detail: { auto: hls.autoLevelEnabled, level: hls.currentLevel },
				} ) );
			} );

			hls.on( Hls.Events.ERROR, ( event, data ) => {
				if ( data && data.fatal ) {
					console.error( 'Webinar HLS fatal error:', data );
					video.dispatchEvent( new CustomEvent( 'webinar:error', { detail: { source: 'hls', data: data } } ) );
					// For network / media errors, attempt automatic recovery once.
					switch ( data.type ) {
						case Hls.ErrorTypes.NETWORK_ERROR:
							hls.startLoad();
							break;
						case Hls.ErrorTypes.MEDIA_ERROR:
							hls.recoverMediaError();
							break;
					}
				}
			} );

			hls.on( Hls.Events.MANIFEST_PARSED, function () {
				buildQualitySelector( video, hls );
			} );

			// Prime stop: once the first fragment is buffered while the video
			// is still paused and the user hasn't started playback, idle the
			// network. This also covers the saved-position restore seek: its
			// fragment arrives, renders the freeze frame, then loading stops.
			hls.on( Hls.Events.FRAG_BUFFERED, function () {
				if ( primeStopDone || ! video.paused || video.webinarHlsUserStarted ) {
					return;
				}
				// Idle the network only once the frame at the CURRENT position
				// is buffered. On page load this waits for the saved-position
				// restore fragment, not just the zero-position prime fragment.
				let covered = false;
				for ( let i = 0; i < video.buffered.length; i++ ) {
					if ( video.currentTime >= video.buffered.start( i ) - 0.1 &&
						 video.currentTime <= video.buffered.end( i ) + 0.1 ) {
						covered = true;
						break;
					}
				}
				if ( covered ) {
					primeStopDone = true;
					hls.stopLoad();
					video.webinarHlsStopped = true;
				}
			} );

			// Play: resume loading from the current position.
			video.addEventListener( 'play', function () {
				video.webinarHlsUserStarted = true;
				if ( video.webinarHlsStopped ) {
					video.webinarHlsStopped = false;
					hls.startLoad( video.currentTime );
				}
			} );

			// Any seek (user drag, saved-position restore) resumes loading
			// from the new position so the freeze frame renders promptly.
			// The prime-stop arm re-arms so paused scrubs also load just
			// the fragment they need, then idle again.
			video.addEventListener( 'seeking', function () {
				primeStopDone = false;
				video.webinarHlsStopped = false;
				hls.startLoad( video.currentTime );
			} );


			hls.loadSource( manifest );
			hls.attachMedia( video );

		} else if ( video.canPlayType( 'application/vnd.apple.mpegurl' ) ) {
			// Safari: native HLS.
			video.src = manifest;
		}
	}


	/**
	 * Small quality selector under the player (Auto + levels).
	 * Auto = ABR in charge; manual pick pins the level until switched back.
	 *
	 * @param {HTMLVideoElement} video Video element.
	 * @param {Object} hls hls.js instance.
	 */
	function buildQualitySelector( video, hls ) {
		if ( ! hls.levels || hls.levels.length < 2 ) {
			return;
		}

		// Custom player builds its own in-player menu (webinar-controls.js).
		const playerWrapper = video.closest( '.webinar-player-wrapper' );
		if ( playerWrapper && 'custom' === playerWrapper.getAttribute( 'data-player-type' ) ) {
			video.dispatchEvent( new CustomEvent( 'webinar:qualityready' ) );
			return;
		}

		const col = video.closest( '.webinar-col-video' );
		const views = col ? col.querySelector( '.webinar-views' ) : null;
		if ( ! views || views.querySelector( '.webinar-quality' ) ) {
			return;
		}

		const wrap = document.createElement( 'span' );
		wrap.className = 'webinar-quality';

		const select = document.createElement( 'select' );

		const auto = document.createElement( 'option' );
		auto.value = '-1';
		auto.textContent = front.autoLabel || 'Auto';
		select.appendChild( auto );

		hls.levels.forEach( function ( level, index ) {
			const option = document.createElement( 'option' );
			option.value = String( index );
			option.textContent = level.height
				? level.height + 'p'
				: Math.round( level.bitrate / 1000 ) + ' kbps';
			select.appendChild( option );
		} );

		select.addEventListener( 'change', function () {
			// -1 re-enables ABR; >= 0 pins the chosen level.
			hls.currentLevel = parseInt( select.value, 10 );
		} );

		// While ABR is in charge, keep "Auto" selected.
		hls.on( Hls.Events.LEVEL_SWITCHED, function () {
			if ( hls.autoLevelEnabled ) {
				select.value = '-1';
			}
		} );

		wrap.appendChild( select );
		views.appendChild( wrap );
	}

	function initAll() {
		document.querySelectorAll( '.webinar-widget' ).forEach( initWidget );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}

	// Re-scan for dynamically inserted markup (Gutenberg ServerSideRender,
	// AJAX navigation). initWidget is idempotent (dataset flag), so this
	// is safe to run repeatedly.
	if ( 'undefined' !== typeof MutationObserver ) {
		let moPending = false;
		const observer = new MutationObserver( function () {
			if ( moPending ) {
				return;
			}
			moPending = true;
			setTimeout( function () {
				moPending = false;
				initAll();
			}, 200 );
		} );
		const startObserving = function () {
			if ( document.body ) {
				observer.observe( document.body, { childList: true, subtree: true } );
			}
		};
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', startObserving );
		} else {
			startObserving();
		}
	}

	window.WebinarBlock = window.WebinarBlock || {};
	window.WebinarBlock.initAll = initAll;
} )();