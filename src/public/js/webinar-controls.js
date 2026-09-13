/**
 * Webinar Block — custom branded player controls.
 *
 * Implements play/pause, progress bar (click + drag), volume,
 * playback speed, ±10s skip, fullscreen, and auto-hide controls.
 * Only activates for webinars with player_type="custom".
 *
 * @package Webinar_Block
 */
( function () {
	'use strict';

	const front = window.webinarBlockFront || {};
	const SPEED_OPTIONS = [ 1, 1.25, 1.5, 2 ];
	const initialized = new WeakSet();

	/**
	 * Compact view counter: 0-999 as is, then K/M/B with up to two
	 * decimals and trimmed trailing zeros (1 K, 1.5 K, 1.55 K, 10 K).
	 *
	 * @param {number} n View count.
	 * @return {string} Formatted count.
	 */
	function formatViews( n ) {
		n = Math.max( 0, Math.floor( Number( n ) || 0 ) );
		if ( n < 1000 ) {
			return String( n );
		}
		const units = [ [ 1e9, 'B' ], [ 1e6, 'M' ], [ 1e3, 'K' ] ];
		for ( let i = 0; i < units.length; i++ ) {
			if ( n >= units[ i ][ 0 ] ) {
				const text = ( Math.round( ( n / units[ i ][ 0 ] ) * 100 ) / 100 )
					.toFixed( 2 )
					.replace( /\.?0+$/, '' );
				return text + ' ' + units[ i ][ 1 ];
			}
		}
		return String( n );
	}

	/**
	 * Format seconds to mm:ss or h:mm:ss string.	 *
	 * @param {number} seconds Time in seconds.
	 * @return {string} Formatted time string.
	 */
	function formatTime( seconds ) {
		if ( ! isFinite( seconds ) || seconds < 0 ) {
			return '0:00';
		}
		const h = Math.floor( seconds / 3600 );
		const m = Math.floor( ( seconds % 3600 ) / 60 );
		const s = Math.floor( seconds % 60 );
		if ( h > 0 ) {
			return h + ':' + String( m ).padStart( 2, '0' ) + ':' + String( s ).padStart( 2, '0' );
		}
		return m + ':' + String( s ).padStart( 2, '0' );
	}

	/**
	 * Update aria-label for a button based on its current state.
	 *
	 * @param {HTMLButtonElement} button Button element.
	 * @param {string} action Action identifier.
	 * @param {boolean} state Current state (e.g., playing, muted).
	 */
	function updateAriaLabel( button, action, state ) {
		const labels = {
			playPause: {
				true: front.pause || 'Pause',
				false: front.play || 'Play',
			},
			volume: {
				true: front.unmute || 'Unmute',
				false: front.mute || 'Mute',
			},
			fullscreen: {
				true: front.exitFullscreen || 'Exit Fullscreen',
				false: front.fullscreen || 'Fullscreen',
			},
		};
		if ( labels[ action ] && labels[ action ][ state ] ) {
			button.setAttribute( 'aria-label', labels[ action ][ state ] );
		}
	}

	/**
	 * Initialize custom controls for a single video element.
	 *
	 * @param {HTMLVideoElement} video Video element.
	 * @param {HTMLElement} wrapper Player wrapper containing controls.
	 */
	function initPlayer( video, wrapper ) {
		if ( initialized.has( video ) ) {
			return;
		}
		initialized.add( video );

		const controls = wrapper.querySelector( '.webinar-custom-controls' );
		if ( ! controls ) {
			return;
		}

		// Cache control elements
		const playPauseBtn = controls.querySelector( '.webinar-btn-play-pause' );
		const skipBackBtn = controls.querySelector( '.webinar-btn-skip-back' );
		const skipForwardBtn = controls.querySelector( '.webinar-btn-skip-forward' );
		const volumeBtn = controls.querySelector( '.webinar-btn-volume' );
		const volumeSlider = controls.querySelector( '.webinar-volume-input' );
		const speedBtn = controls.querySelector( '.webinar-btn-speed' );
		const speedLabel = controls.querySelector( '.webinar-speed-label' );
		const speedMenu = controls.querySelector( '.webinar-speed-menu' );
		const fullscreenBtn = controls.querySelector( '.webinar-btn-fullscreen' );
		const timeCurrent = controls.querySelector( '.webinar-time-current' );
		const timeDuration = controls.querySelector( '.webinar-time-duration' );
		const progressBar = controls.querySelector( '.webinar-progress-bar' );
		const segmentsBox = controls.querySelector( '.webinar-progress-segments' );
		const progressThumb = controls.querySelector( '.webinar-progress-thumb' );
		let progressSegments = [];

		/**
		 * (Re)build chapter segments of the progress bar.
		 * Widths sum to exactly 100%, so time<->pixel mapping stays linear.
		 */
		function buildProgressSegments() {
			if ( ! segmentsBox ) {
				return;
			}
			segmentsBox.innerHTML = '';
			progressSegments = [];

			const duration = video.duration || 0;
			const starts = [];
			for ( let i = 0; i < previewChapters.length; i++ ) {
				const t = previewChapters[ i ].t;
				if ( duration && t >= duration ) {
					continue;
				}
				if ( ! starts.length || t > starts[ starts.length - 1 ] ) {
					starts.push( t );
				}
			}
			if ( ! starts.length || starts[ 0 ] > 0 ) {
				starts.unshift( 0 );
			}

			for ( let i = 0; i < starts.length; i++ ) {
				const start = starts[ i ];
				const end = i + 1 < starts.length ? starts[ i + 1 ] : ( duration || Infinity );
				const width = duration
					? ( ( Math.min( end, duration ) - start ) / duration ) * 100
					: 100;

				const el = document.createElement( 'div' );
				el.className = 'webinar-progress-segment';
				el.style.width = width + '%';

				const bufferedEl = document.createElement( 'div' );
				bufferedEl.className = 'webinar-progress-buffered';
				const playedEl = document.createElement( 'div' );
				playedEl.className = 'webinar-progress-played';
				el.appendChild( bufferedEl );
				el.appendChild( playedEl );
				segmentsBox.appendChild( el );

				progressSegments.push( { el: el, playedEl: playedEl, bufferedEl: bufferedEl, start: start, end: end } );
			}
			updateSegmentFills();
		}

		/**
		 * Update played/buffered fill inside every segment.
		 */
		function updateSegmentFills() {
			if ( ! progressSegments.length || ! video.duration ) {
				return;
			}
			const current = video.currentTime;
			let bufferedEnd = 0;
			if ( video.buffered.length ) {
				bufferedEnd = video.buffered.end( video.buffered.length - 1 );
			}
			for ( let i = 0; i < progressSegments.length; i++ ) {
				const seg = progressSegments[ i ];
				const span = ( isFinite( seg.end ) ? seg.end : video.duration ) - seg.start;
				if ( span <= 0 ) {
					continue;
				}
				const played = Math.max( 0, Math.min( 1, ( current - seg.start ) / span ) );
				const buffered = Math.max( 0, Math.min( 1, ( bufferedEnd - seg.start ) / span ) );
				seg.playedEl.style.width = ( played * 100 ) + '%';
				seg.bufferedEl.style.width = ( buffered * 100 ) + '%';
			}
		}

		// Buffering and error overlays sit inside the player wrapper.
		const spinner = wrapper.querySelector( '.webinar-spinner' );
		const errorOverlay = wrapper.querySelector( '.webinar-error-overlay' );
		const retryBtn = errorOverlay ? errorOverlay.querySelector( '.webinar-btn-retry' ) : null;
		const bigPlayBtn = wrapper.querySelector( '.webinar-big-play' );
		const playFlash = wrapper.querySelector( '.webinar-play-flash' );
		const pauseFlash = wrapper.querySelector( '.webinar-pause-flash' );

		let bufferingTimer = null;

		/**
		 * Show a brief animated play/pause icon overlay.
		 * YouTube-style: scale up, hold, fade out.
		 *
		 * @param {string} type 'play' or 'pause'
		 */
		function showClickFeedback( type ) {
			var target = ( 'play' === type ) ? playFlash : pauseFlash;
			var other = ( 'play' === type ) ? pauseFlash : playFlash;

			if ( ! target ) {
				return;
			}

			// Hide any currently running opposite flash
			if ( other ) {
				other.classList.remove( 'webinar-flash-active' );
				other.hidden = true;
			}

			// Restart animation even if already playing:
			// remove class, force reflow, add class back.
			target.classList.remove( 'webinar-flash-active' );
			target.hidden = false;
			// Force reflow so the browser sees the class removal before re-add.
			void target.offsetWidth;
			target.classList.add( 'webinar-flash-active' );
		}

		let clickTimer = null;
		const CLICK_DELAY = 250; // ms delay to distinguish click from double-click

		/**
		 * Toggle play/pause state.
		 */
		function togglePlayPause() {
			if ( video.paused || video.ended ) {
				video.play();
			} else {
				video.pause();
			}
		}

		/**
		 * Toggle fullscreen on the player wrapper.
		 * Falls back to video-element fullscreen on iOS Safari, where the
		 * Fullscreen API is unavailable for arbitrary elements. iOS state is
		 * tracked via webkitbeginfullscreen / webkitendfullscreen events.
		 */
		function toggleFullscreen() {
			if ( document.fullscreenElement || document.webkitFullscreenElement ) {
				if ( document.exitFullscreen ) {
					document.exitFullscreen();
				} else if ( document.webkitExitFullscreen ) {
					document.webkitExitFullscreen();
				}
			} else if ( iosFullscreen ) {
				// iOS: exit the video-element fullscreen session.
				if ( video.webkitExitFullscreen ) {
					video.webkitExitFullscreen();
				}
			} else if ( wrapper.requestFullscreen ) {
				wrapper.requestFullscreen();
			} else if ( wrapper.webkitRequestFullscreen ) {
				wrapper.webkitRequestFullscreen();
			} else if ( video.webkitEnterFullscreen ) {
				// iOS Safari: only the <video> element can go fullscreen.
				video.webkitEnterFullscreen();
			}
		}

		/**
		 * Show the buffering spinner after a short delay.
		 * Delay avoids flashing on very fast state transitions.
		 */
		function showSpinner() {
			if ( ! spinner || spinner.hidden === false ) {
				return;
			}
			clearTimeout( bufferingTimer );
			bufferingTimer = setTimeout( function () {
				if ( spinner ) {
					spinner.hidden = false;
				}
			}, 250 );
		}

		/**
		 * Hide the buffering spinner immediately.
		 */
		function hideSpinner() {
			clearTimeout( bufferingTimer );
			if ( spinner ) {
				spinner.hidden = true;
			}
		}

		/**
		 * Show the error overlay and force controls visible.
		 */
		function showError() {
			if ( errorOverlay ) {
				errorOverlay.hidden = false;
			}
			wrapper.classList.remove( 'webinar-controls-hidden' );
			wrapper.classList.add( 'webinar-paused' );
		}

		/**
		 * Hide the error overlay.
		 */
		function hideError() {
			if ( errorOverlay ) {
				errorOverlay.hidden = true;
			}
		}

		// Buffering events dispatched by webinar.js
		video.addEventListener( 'webinar:buffering', showSpinner );
		video.addEventListener( 'webinar:ready', function () {
			hideSpinner();
			hideError();
		} );
		video.addEventListener( 'webinar:error', function () {
			hideSpinner();
			showError();
		} );

		// Retry: reload HLS via startLoad or full reload, MP4 via load()
		if ( retryBtn ) {
			retryBtn.addEventListener( 'click', function () {
				hideError();
				showSpinner();
				try {
					if ( video.webinarHls && typeof video.webinarHls.startLoad === 'function' ) {
						video.webinarHls.stopLoad();
						video.webinarHls.startLoad();
					} else {
						// MP4 or native HLS: reset src to force reload.
						var src = video.currentSrc || video.src;
						if ( src ) {
							video.src = '';
							video.src = src;
							video.load();
						}
					}
				} catch ( e ) {
					console.error( 'Webinar retry failed:', e );
					showError();
				}
			} );
		}

		let hideControlsTimer = null;
		let isDraggingProgress = false;

		/**
		 * Show controls and reset hide timer.
		 */
		function showControls() {
			wrapper.classList.remove( 'webinar-controls-hidden' );
			resetHideTimer();
		}

		/**
		 * Hide controls after delay.
		 */
		function hideControls() {
			if ( ! video.paused ) {
				wrapper.classList.add( 'webinar-controls-hidden' );
			}
		}

		/**
		 * Reset the auto-hide timer.
		 */
		function resetHideTimer() {
			if ( hideControlsTimer ) {
				clearTimeout( hideControlsTimer );
			}
			hideControlsTimer = setTimeout( hideControls, 3000 );
		}

		// Play/Pause button
		if ( playPauseBtn ) {
			playPauseBtn.addEventListener( 'click', function () {
				if ( video.paused ) {
					video.play();
				} else {
					video.pause();
				}
			} );
		}

		/**
		 * Quick "pop" scale on the play/pause icon when the mode flips.
		 */
		function popPlayPause() {
			if ( ! playPauseBtn ) {
				return;
			}
			playPauseBtn.classList.remove( 'webinar-pop' );
			// Force reflow so the animation restarts on rapid toggles.
			void playPauseBtn.offsetWidth;
			playPauseBtn.classList.add( 'webinar-pop' );
		}

		// Sync play/pause icon with video state
		video.addEventListener( 'play', function () {
			if ( playPauseBtn ) {
				playPauseBtn.innerHTML = front.pause ? '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>' : '';
				updateAriaLabel( playPauseBtn, 'playPause', true );
				popPlayPause();
			}
			wrapper.classList.remove( 'webinar-paused' );
			wrapper.classList.add( 'webinar-has-played' ); // Hide big play button
			wrapper.classList.add( 'webinar-playing' );    // Center button yields
			resetHideTimer();
		} );

		video.addEventListener( 'pause', function () {
			if ( playPauseBtn ) {
				playPauseBtn.innerHTML = front.play ? '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>' : '';
				updateAriaLabel( playPauseBtn, 'playPause', false );
				popPlayPause();
			}
			wrapper.classList.add( 'webinar-paused' );
			wrapper.classList.remove( 'webinar-controls-hidden' );
			wrapper.classList.remove( 'webinar-playing' );
		} );

		// Big play button: play on click (single click only, no fullscreen)
		if ( bigPlayBtn ) {
			bigPlayBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation(); // Prevent wrapper click handler
				video.play();
				// Do not show click-flash here: the big play button itself is the feedback.
			} );
		}

		// Mobile center play/pause (visible only when paused, CSS-driven).
		// stopPropagation keeps it independent from the click-flash logic.
		const centerPlayPauseBtn = wrapper.querySelector( '.webinar-center-play-pause' );
		if ( centerPlayPauseBtn ) {
			centerPlayPauseBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				togglePlayPause();
			} );
		}

		/* --------------------------------------------------------------
		 * Picture-in-Picture: floating button on the right edge.
		 * Standard Web API where available; WebKit presentation mode on
		 * iOS Safari; button removed entirely when unsupported.
		 * -------------------------------------------------------------- */
		const pipBtn = wrapper.querySelector( '.webinar-btn-pip' );
		let lastPipClick = 0;
		if ( pipBtn ) {
			const hasWebkitPip = 'function' === typeof video.webkitSupportsPresentationMode
				&& video.webkitSupportsPresentationMode( 'picture-in-picture' );
			const hasStdPip = !! ( document.pictureInPictureEnabled && video.requestPictureInPicture );
			if ( ! hasStdPip && ! hasWebkitPip ) {
				pipBtn.remove();
			} else {
				pipBtn.hidden = false;

				// On narrow screens pin the button above the controls block.
				const syncPipPosition = function () {
					wrapper.style.setProperty( '--webinar-pip-bottom', ( controls.offsetHeight ) + 'px' );
				};
				syncPipPosition();
				window.addEventListener( 'resize', syncPipPosition );
				document.addEventListener( 'fullscreenchange', syncPipPosition );

				pipBtn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					e.preventDefault();
					lastPipClick = Date.now();
					if ( ! hasStdPip && hasWebkitPip ) {
						// iOS Safari path.
						const current = video.webkitPresentationMode || 'inline';
						video.webkitSetPresentationMode( 'picture-in-picture' === current ? 'inline' : 'picture-in-picture' );
						return;
					}
					if ( document.pictureInPictureElement === video ) {
						document.exitPictureInPicture().catch( function ( err ) {
							console.warn( 'PiP exit failed:', err );
						} );
					} else {
						// Chromium (Edge, Yandex, Android) enters PiP reliably
						// only for playing media — start playback first.
						if ( video.paused ) {
							video.play().catch( function () {} );
						}
						video.requestPictureInPicture().catch( function ( err ) {
							console.warn( 'PiP request failed:', err );
						} );
					}
				} );
				video.addEventListener( 'enterpictureinpicture', function () {
					pipBtn.classList.add( 'webinar-pip-active' );
				} );
				video.addEventListener( 'leavepictureinpicture', function () {
					pipBtn.classList.remove( 'webinar-pip-active' );
				} );
				if ( hasWebkitPip ) {
					video.addEventListener( 'webkitpresentationmodechanged', function () {
						pipBtn.classList.toggle( 'webinar-pip-active', 'picture-in-picture' === video.webkitPresentationMode );
					} );
				}
			}
		}

		// Clean up flash elements when their animation finishes
		function onFlashEnd( e ) {
			var el = e.target;
			if ( el && el.classList && el.classList.contains( 'webinar-flash-active' ) ) {
				el.classList.remove( 'webinar-flash-active' );
				el.hidden = true;
			}
		}
		if ( playFlash ) {
			playFlash.addEventListener( 'animationend', onFlashEnd );
		}
		if ( pauseFlash ) {
			pauseFlash.addEventListener( 'animationend', onFlashEnd );
		}

		// Click on video or wrapper: play/pause (single click) or fullscreen (double click)
		wrapper.addEventListener( 'click', function ( e ) {
			// Ignore clicks inside controls, progress bar, speed menu, or during drag
			if ( e.target.closest( '.webinar-btn' ) ||
				 e.target.closest( '.webinar-progress-bar' ) ||
				 e.target.closest( '.webinar-speed-menu' ) ||
				 e.target.closest( '.webinar-quality-menu' ) ||
				 e.target.closest( '.webinar-share-menu' ) ||
				 e.target.closest( '.webinar-share-toast' ) ||
				 e.target.closest( '.webinar-volume-slider' ) ||
				 e.target.closest( '.webinar-big-play' ) ||
				 e.target.closest( '.webinar-center-play-pause' ) ||
				 e.target.closest( '.webinar-spinner' ) ||
				 e.target.closest( '.webinar-error-overlay' ) ||
				 isDraggingProgress ||
				 ( Date.now() - lastPipClick < 700 ) ) {
				return;
			}

			// Distinguish single click from double click
			if ( clickTimer ) {
				clearTimeout( clickTimer );
				clickTimer = null;
				// Double click detected: toggle fullscreen
				toggleFullscreen();
			} else {
				clickTimer = setTimeout( function () {
					clickTimer = null;
					// Single click: toggle play/pause + show flash feedback
					var willPlay = video.paused || video.ended;
					togglePlayPause();
					showClickFeedback( willPlay ? 'play' : 'pause' );
				}, CLICK_DELAY );
			}
		} );

		// Skip buttons
		if ( skipBackBtn ) {
			skipBackBtn.addEventListener( 'click', function () {
				video.currentTime = Math.max( 0, video.currentTime - 10 );
			} );
		}

		if ( skipForwardBtn ) {
			skipForwardBtn.addEventListener( 'click', function () {
				video.currentTime = Math.min( video.duration || 0, video.currentTime + 10 );
			} );
		}

		// Keyboard: arrows behave identically on BOTH skip buttons —
		// Left = -10s, Right = +10s, regardless of which one is focused.
		[ skipBackBtn, skipForwardBtn ].forEach( function ( btn ) {
			if ( ! btn ) {
				return;
			}
			btn.addEventListener( 'keydown', function ( e ) {
				if ( 'ArrowLeft' !== e.key && 'ArrowRight' !== e.key ) {
					return;
				}
				e.preventDefault();
				if ( ! video.duration ) {
					return;
				}
				const delta = 'ArrowLeft' === e.key ? -10 : 10;
				video.currentTime = Math.max( 0, Math.min( video.duration, video.currentTime + delta ) );
			} );
		} );

		// Volume button (mute/unmute)
		if ( volumeBtn ) {
			volumeBtn.addEventListener( 'click', function () {
				video.muted = ! video.muted;
				if ( volumeSlider ) {
					volumeSlider.value = video.muted ? 0 : video.volume;
				}
			} );
		}

		// Sync volume icon with mute state
		video.addEventListener( 'volumechange', function () {
			if ( volumeBtn ) {
				const isMuted = video.muted || video.volume === 0;
				volumeBtn.innerHTML = isMuted
					? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>'
					: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>';
				updateAriaLabel( volumeBtn, 'volume', ! isMuted );
			}
		} );

		// Volume slider
		if ( volumeSlider ) {
			volumeSlider.addEventListener( 'input', function () {
				video.volume = parseFloat( this.value );
				video.muted = video.volume === 0;
			} );
		}

		// Speed button toggles menu
		if ( speedBtn && speedMenu ) {
			speedBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				speedMenu.classList.toggle( 'webinar-speed-menu-visible' );
				if ( qualityMenu ) {
					qualityMenu.classList.remove( 'webinar-quality-menu-visible' );
				}
				if ( shareMenu ) {
					shareMenu.classList.remove( 'webinar-share-menu-visible' );
				}
			} );
		}

		// Speed menu options (delegated)
		if ( speedMenu ) {
			speedMenu.addEventListener( 'click', function ( e ) {
				const option = e.target.closest( '.webinar-speed-option' );
				if ( ! option ) {
					return;
				}
				const speed = parseFloat( option.dataset.speed );
				if ( isFinite( speed ) ) {
					video.playbackRate = speed;
					speedMenu.classList.remove( 'webinar-speed-menu-visible' );
					// Update active state
					speedMenu.querySelectorAll( '.webinar-speed-option' ).forEach( function ( opt ) {
						opt.classList.toggle( 'webinar-speed-active', parseFloat( opt.dataset.speed ) === speed );
					} );
				}
			} );
		}

		// Update speed label + highlight the active speed option.
		function syncSpeedActive() {
			if ( ! speedMenu ) {
				return;
			}
			speedMenu.querySelectorAll( '.webinar-speed-option' ).forEach( function ( opt ) {
				opt.classList.toggle( 'webinar-speed-active', parseFloat( opt.dataset.speed ) === video.playbackRate );
			} );
		}

		video.addEventListener( 'ratechange', function () {
			if ( speedLabel ) {
				speedLabel.textContent = video.playbackRate + 'x';
			}
			syncSpeedActive();
		} );
		syncSpeedActive(); // Initial highlight (1x).

		/* --------------------------------------------------------------
		 * Quality selector (in-player menu, custom player only).
		 * Built dynamically when HLS exposes 2+ levels; the native player
		 * keeps its legacy <select> under the video (webinar.js).
		 * -------------------------------------------------------------- */
		let qualityHolder = null;
		let qualityBtn = null;
		let qualityMenu = null;
		let qualityLabel = null;

		const QUALITY_SVG = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="8" x2="6" y2="8"></line><circle cx="9" cy="8" r="3"></circle><line x1="12" y1="8" x2="21" y2="8"></line><line x1="3" y1="16" x2="12" y2="16"></line><circle cx="15" cy="16" r="3"></circle><line x1="18" y1="16" x2="21" y2="16"></line></svg>';

		function levelLabel( hls, index ) {
			if ( index < 0 || ! hls.levels[ index ] ) {
				return front.autoLabel || 'Auto';
			}
			const level = hls.levels[ index ];
			return level.height ? level.height + 'p' : Math.round( level.bitrate / 1000 ) + ' kbps';
		}

		function buildQualityMenu() {
			const hls = video.webinarHls;
			if ( ! hls || ! hls.levels || hls.levels.length < 2 || qualityHolder ) {
				return;
			}

			qualityHolder = document.createElement( 'span' );
			qualityHolder.className = 'webinar-quality-holder';

			qualityBtn = document.createElement( 'button' );
			qualityBtn.type = 'button';
			qualityBtn.className = 'webinar-btn webinar-btn-quality';
			qualityBtn.setAttribute( 'aria-label', front.quality || 'Quality' );
			qualityBtn.innerHTML = QUALITY_SVG;

			qualityLabel = document.createElement( 'span' );
			qualityLabel.className = 'webinar-quality-label';
			qualityLabel.textContent = front.autoLabel || 'Auto';
			qualityBtn.appendChild( qualityLabel );

			qualityMenu = document.createElement( 'div' );
			qualityMenu.className = 'webinar-quality-menu';
			qualityMenu.setAttribute( 'role', 'menu' );
			qualityMenu.setAttribute( 'aria-label', front.quality || 'Quality' );

			const autoOpt = document.createElement( 'button' );
			autoOpt.type = 'button';
			autoOpt.className = 'webinar-quality-option webinar-quality-active';
			autoOpt.setAttribute( 'role', 'menuitem' );
			autoOpt.dataset.level = '-1';
			autoOpt.textContent = front.autoLabel || 'Auto';
			qualityMenu.appendChild( autoOpt );

			hls.levels.forEach( function ( level, index ) {
				const opt = document.createElement( 'button' );
				opt.type = 'button';
				opt.className = 'webinar-quality-option';
				opt.setAttribute( 'role', 'menuitem' );
				opt.dataset.level = String( index );
				opt.textContent = level.height ? level.height + 'p' : Math.round( level.bitrate / 1000 ) + ' kbps';
				qualityMenu.appendChild( opt );
			} );

			qualityHolder.appendChild( qualityBtn );
			qualityHolder.appendChild( qualityMenu );

			// Insert right after the speed holder (button + its menu),
			// so the order stays: speed, quality, fullscreen.
			const speedHolder = speedBtn ? ( speedBtn.closest( '.webinar-speed-holder' ) || speedBtn ) : null;
			if ( speedHolder && speedHolder.parentNode ) {
				speedHolder.parentNode.insertBefore( qualityHolder, speedHolder.nextSibling );
			}

			qualityBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				qualityMenu.classList.toggle( 'webinar-quality-menu-visible' );
				if ( speedMenu ) {
					speedMenu.classList.remove( 'webinar-speed-menu-visible' );
				}
				if ( shareMenu ) {
					shareMenu.classList.remove( 'webinar-share-menu-visible' );
				}
			} );

			qualityMenu.addEventListener( 'click', function ( e ) {
				const option = e.target.closest( '.webinar-quality-option' );
				if ( ! option ) {
					return;
				}
				const level = parseInt( option.dataset.level, 10 );
				hls.currentLevel = level; // -1 re-enables ABR
				if ( qualityLabel ) {
					qualityLabel.textContent = levelLabel( hls, level );
				}
				qualityMenu.querySelectorAll( '.webinar-quality-option' ).forEach( function ( opt ) {
					opt.classList.toggle( 'webinar-quality-active', opt === option );
				} );
				qualityMenu.classList.remove( 'webinar-quality-menu-visible' );
			} );
		}

		// Sync label/highlight while ABR is in charge or level changes.
		video.addEventListener( 'webinar:levelswitched', function ( e ) {
			if ( ! qualityLabel || ! video.webinarHls ) {
				return;
			}
			const d = e.detail || {};
			qualityLabel.textContent = d.auto
				? ( front.autoLabel || 'Auto' )
				: levelLabel( video.webinarHls, d.level );
			if ( qualityMenu ) {
				qualityMenu.querySelectorAll( '.webinar-quality-option' ).forEach( function ( opt ) {
					const lvl = parseInt( opt.dataset.level, 10 );
					opt.classList.toggle( 'webinar-quality-active', d.auto ? -1 === lvl : lvl === d.level );
				} );
			}
		} );

		video.addEventListener( 'webinar:qualityready', buildQualityMenu );
		buildQualityMenu(); // In case the manifest parsed before init.

		// Fullscreen button (single source of truth: toggleFullscreen)
		if ( fullscreenBtn ) {
			fullscreenBtn.addEventListener( 'click', function () {
				toggleFullscreen();
			} );

			// Focus cycle: Tab from fullscreen loops back into the player —
			// PiP first; when PiP is absent/hidden, the first visible control
			// (skip-back on desktop, center play on mobile).
			wrapper.addEventListener( 'keydown', function ( e ) {
				if ( 'Tab' !== e.key || e.shiftKey || e.target !== fullscreenBtn ) {
					return;
				}
				const candidates = [ pipBtn, skipBackBtn, playPauseBtn, centerPlayPauseBtn ];
				for ( let i = 0; i < candidates.length; i++ ) {
					const el = candidates[ i ];
					if ( el && ! el.hidden && null !== el.offsetParent ) {
						e.preventDefault();
						el.focus();
						return;
					}
				}
			} );
		}

		// Sync fullscreen icon with state
		let iosFullscreen = false;

		function updateFullscreenUi( isFullscreen ) {
			if ( ! fullscreenBtn ) {
				return;
			}
			fullscreenBtn.innerHTML = isFullscreen
				? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path></svg>'
				: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>';
			updateAriaLabel( fullscreenBtn, 'fullscreen', isFullscreen );
		}

		function onFullscreenChange() {
			updateFullscreenUi( !!( document.fullscreenElement || document.webkitFullscreenElement ) );
		}

		document.addEventListener( 'fullscreenchange', onFullscreenChange );
		document.addEventListener( 'webkitfullscreenchange', onFullscreenChange );

		// iOS Safari: fullscreen lives on the video element, not the document.
		video.addEventListener( 'webkitbeginfullscreen', function () {
			iosFullscreen = true;
			updateFullscreenUi( true );
		} );
		video.addEventListener( 'webkitendfullscreen', function () {
			iosFullscreen = false;
			updateFullscreenUi( false );
		} );

		document.addEventListener( 'fullscreenchange', onFullscreenChange );
		document.addEventListener( 'webkitfullscreenchange', onFullscreenChange );

		// Progress bar: click to seek + drag (mouse + touch)
		if ( progressBar && progressThumb ) {
			function getProgressPercent( clientX ) {
				const rect = progressBar.getBoundingClientRect();
				return Math.max( 0, Math.min( 1, ( clientX - rect.left ) / rect.width ) );
			}

			function updateDragVisual( percent ) {
				if ( ! video.duration ) {
					return;
				}
				// Update thumb position instantly (not waiting for timeupdate).
				progressThumb.style.left = ( percent * 100 ) + '%';
				// Update played fills in segments.
				const t = percent * video.duration;
				for ( let i = 0; i < progressSegments.length; i++ ) {
					const seg = progressSegments[ i ];
					const span = ( isFinite( seg.end ) ? seg.end : video.duration ) - seg.start;
					if ( span <= 0 ) {
						continue;
					}
					const played = Math.max( 0, Math.min( 1, ( t - seg.start ) / span ) );
					seg.playedEl.style.width = ( played * 100 ) + '%';
				}
				// Show preview bubble at the drag position.
				if ( 'function' === typeof showPreviewBubble ) {
					const rect = progressBar.getBoundingClientRect();
					showPreviewBubble( { clientX: rect.left + percent * rect.width }, t );
				}
			}

			// Click to seek (if not part of a drag)
			progressBar.addEventListener( 'click', function ( e ) {
				if ( isDraggingProgress ) {
					return;
				}
				const percent = getProgressPercent( e.clientX );
				if ( percent >= 0 && percent <= 1 && video.duration ) {
					video.currentTime = percent * video.duration;
				}
			} );

			// Drag start (mouse)
			progressBar.addEventListener( 'mousedown', function ( e ) {
				e.preventDefault();
				isDraggingProgress = true;
				const percent = getProgressPercent( e.clientX );
				updateDragVisual( percent );
			} );

			// Drag move (mouse)
			document.addEventListener( 'mousemove', function ( e ) {
				if ( ! isDraggingProgress ) {
					return;
				}
				const percent = getProgressPercent( e.clientX );
				updateDragVisual( percent );
			} );

			// Drag end (mouse) — actual seek happens here
			document.addEventListener( 'mouseup', function ( e ) {
				if ( ! isDraggingProgress ) {
					return;
				}
				isDraggingProgress = false;
				const percent = getProgressPercent( e.clientX );
				if ( video.duration && percent >= 0 && percent <= 1 ) {
					video.currentTime = percent * video.duration;
				}
			} );

			// Drag start (touch)
			progressBar.addEventListener( 'touchstart', function ( e ) {
				if ( ! e.touches.length ) {
					return;
				}
				e.stopPropagation(); // Prevent wrapper tap handler
				isDraggingProgress = true;
				const percent = getProgressPercent( e.touches[ 0 ].clientX );
				updateDragVisual( percent );
			}, { passive: true } );

			// Drag move (touch)
			document.addEventListener( 'touchmove', function ( e ) {
				if ( ! isDraggingProgress || ! e.touches.length ) {
					return;
				}
				const percent = getProgressPercent( e.touches[ 0 ].clientX );
				updateDragVisual( percent );
			}, { passive: true } );

			// Drag end (touch) — actual seek happens here
			document.addEventListener( 'touchend', function ( e ) {
				if ( ! isDraggingProgress ) {
					return;
				}
				isDraggingProgress = false;
				if ( ! e.changedTouches.length || ! video.duration ) {
					return;
				}
				const percent = getProgressPercent( e.changedTouches[ 0 ].clientX );
				if ( percent >= 0 && percent <= 1 ) {
					video.currentTime = percent * video.duration;
				}
			} );

			// Drag cancel
			document.addEventListener( 'touchcancel', function () {
				isDraggingProgress = false;
			} );

			// Keyboard: Enter/Space = play/pause, ArrowLeft/ArrowRight = ±10s.
			progressBar.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key || 'Spacebar' === e.key ) {
					e.preventDefault(); // Keep the page from scrolling.
					togglePlayPause();
					return;
				}
				if ( 'ArrowLeft' === e.key || 'ArrowRight' === e.key ) {
					e.preventDefault();
					if ( ! video.duration ) {
						return;
					}
					const delta = 'ArrowLeft' === e.key ? -10 : 10;
					video.currentTime = Math.max( 0, Math.min( video.duration, video.currentTime + delta ) );
				}
			} );
		}

		/* --------------------------------------------------------------
		 * Hover preview bubble: time + chapter title + frame preview.
		 * The frame is drawn from a hidden secondary video (MP4) or a
		 * lightweight second hls.js instance (HLS), seeked with throttling.
		 * Created lazily on the first hover to avoid extra connections.
		 * -------------------------------------------------------------- */
		// The wrapper carries its own webinar id (rendered by PHP), so an
		// outer element reusing the .webinar-widget class (e.g. a Gutenberg
		// group block) can no longer break id resolution.
		const widgetRoot = wrapper.closest( '[data-webinar-id]' ) || wrapper;
		const webinarId = wrapper.getAttribute( 'data-webinar-id' )
			|| widgetRoot.getAttribute( 'data-webinar-id' ) || '0';

		const previewBubble = document.createElement( 'div' );
		previewBubble.className = 'webinar-preview-bubble';
		previewBubble.setAttribute( 'aria-hidden', 'true' );

		const previewFrame = document.createElement( 'div' );
		previewFrame.className = 'webinar-preview-frame';

		const previewMeta = document.createElement( 'div' );
		previewMeta.className = 'webinar-preview-meta';
		const previewTime = document.createElement( 'span' );
		previewTime.className = 'webinar-preview-time';
		const previewChapter = document.createElement( 'span' );
		previewChapter.className = 'webinar-preview-chapter';
		previewMeta.appendChild( previewTime );
		previewMeta.appendChild( previewChapter );

		previewBubble.appendChild( previewFrame );
		previewBubble.appendChild( previewMeta );
		wrapper.appendChild( previewBubble );

		/* --------------------------------------------------------------
		 * Sprite preview engine (WebVTT storyboards, YouTube-style).
		 * No second media stream: tiles are cut from pre-generated sheets.
		 * Thresholds are provisional and tuned in one place.
		 * -------------------------------------------------------------- */
		const PREVIEW_MIN_WIDTH = 500; // Below: no image preview, text only.
		const PREVIEW_STD_WIDTH = 1000; // Above: quality depends on connection.
		const previewStdUrl = wrapper.getAttribute( 'data-preview-std' ) || '';
		const previewSmallUrl = wrapper.getAttribute( 'data-preview-small' ) || '';
		const previewVttCache = {}; // quality -> cues
		const previewImgCache = {}; // sheet url -> Image
		let previewActiveQuality = null;
		let lastPreviewHoverTime = null;

		function previewConnectionFast() {
			const c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
			if ( ! c || ! c.effectiveType ) {
				return true; // No Network Information API: assume fast.
			}
			return '4g' === c.effectiveType;
		}

		function choosePreviewQuality() {
			const w = wrapper.getBoundingClientRect().width;
			if ( w < PREVIEW_MIN_WIDTH ) {
				return null; // No load at all on narrow screens.
			}
			if ( w < PREVIEW_STD_WIDTH ) {
				return previewSmallUrl ? 'small' : ( previewStdUrl ? 'std' : null );
			}
			if ( previewConnectionFast() ) {
				return previewStdUrl ? 'std' : ( previewSmallUrl ? 'small' : null );
			}
			return previewSmallUrl ? 'small' : ( previewStdUrl ? 'std' : null );
		}

		function parseVttTime( s ) {
			const parts = s.trim().split( ':' );
			let h = 0, m = 0, sec = 0;
			if ( 3 === parts.length ) {
				h = +parts[ 0 ]; m = +parts[ 1 ]; sec = parseFloat( parts[ 2 ] );
			} else if ( 2 === parts.length ) {
				m = +parts[ 0 ]; sec = parseFloat( parts[ 1 ] );
			} else {
				sec = parseFloat( parts[ 0 ] );
			}
			return h * 3600 + m * 60 + sec;
		}

		function parseVtt( text, baseUrl ) {
			const cues = [];
			const blocks = text.replace( /\r/g, '' ).split( '\n\n' );
			blocks.forEach( function ( block ) {
				const lines = block.split( '\n' );
				for ( let i = 0; i < lines.length - 1; i++ ) {
					if ( lines[ i ].indexOf( '-->' ) < 0 ) {
						continue;
					}
					const times = lines[ i ].split( '-->' );
					const payload = lines.slice( i + 1 ).join( '\n' ).trim();
					if ( ! payload ) {
						continue;
					}
					const frag = payload.match( /#xywh=(\d+),(\d+),(\d+),(\d+)/ );
					const rawUrl = payload.split( '#' )[ 0 ].trim();
					cues.push( {
						start: parseVttTime( times[ 0 ] ),
						end: parseVttTime( times[ 1 ] ),
						// Relative sprite paths resolve against the VTT location,
						// per the WebVTT spec.
						url: baseUrl ? new URL( rawUrl, baseUrl ).href : rawUrl,
						x: frag ? +frag[ 1 ] : 0,
						y: frag ? +frag[ 2 ] : 0,
						w: frag ? +frag[ 3 ] : 0,
						h: frag ? +frag[ 4 ] : 0,
					} );
					break;
				}
			} );
			return cues;
		}

		// Frame size always follows the CHOSEN quality's first tile, applied
		// on every decision (not only on first fetch) — no stale sizes.
		function applyPreviewSize( quality ) {
			const cues = previewVttCache[ quality ];
			if ( cues && cues.length && cues[ 0 ].w && cues[ 0 ].h ) {
				previewBubble.style.setProperty( '--webinar-pw', cues[ 0 ].w + 'px' );
				previewBubble.style.setProperty( '--webinar-ph', cues[ 0 ].h + 'px' );
			}
		}

		function loadPreviewVtt( quality ) {
			const url = 'std' === quality ? previewStdUrl : previewSmallUrl;
			if ( previewVttCache[ quality ] ) {
				return Promise.resolve( previewVttCache[ quality ] );
			}
			return fetch( url ).then( function ( r ) {
				return r.text();
			} ).then( function ( text ) {
				const cues = parseVtt( text, url );
				previewVttCache[ quality ] = cues;
				applyPreviewSize( quality );
				return cues;
			} );
		}

		function paintPreviewCue( cue ) {
			let img = previewImgCache[ cue.url ];
			if ( img && img.naturalWidth ) {
				applySheet( cue, img );
				return;
			}
			if ( ! img ) {
				img = new Image();
				previewImgCache[ cue.url ] = img;
				img.src = cue.url;
			}
			img.onload = function () {
				applySheet( cue, img );
			};
			img.onerror = function () {
				previewBubble.classList.remove( 'webinar-preview-has-frame' );
			};
		}

		function applySheet( cue, img ) {
			previewFrame.style.backgroundImage = 'url("' + cue.url + '")';
			previewFrame.style.backgroundSize = img.naturalWidth + 'px ' + img.naturalHeight + 'px';
			previewFrame.style.backgroundPosition = '-' + cue.x + 'px -' + cue.y + 'px';
			previewBubble.classList.add( 'webinar-preview-has-frame' );
		}

		function requestPreviewCue( t ) {
			lastPreviewHoverTime = t;
			const quality = choosePreviewQuality();
			if ( ! quality ) {
				previewBubble.classList.remove( 'webinar-preview-has-frame' );
				return;
			}
			if ( quality !== previewActiveQuality ) {
				previewActiveQuality = quality;
				previewBubble.classList.remove( 'webinar-preview-has-frame' );
			}
			// Re-apply the frame size for the chosen quality right away
			// (from cache), so std<->small switches resize the window.
			applyPreviewSize( quality );
			loadPreviewVtt( quality ).then( function ( cues ) {
				let cue = null;
				for ( let i = 0; i < cues.length; i++ ) {
					if ( t >= cues[ i ].start && t < cues[ i ].end ) {
						cue = cues[ i ];
						break;
					}
				}
				if ( ! cue && cues.length ) {
					cue = cues[ cues.length - 1 ];
				}
				if ( cue ) {
					paintPreviewCue( cue );
				}
			} ).catch( function () {
				// VTT unavailable: keep the text-only pill.
			} );
		}

		// Recalculate quality/size when the wrapper geometry changes while
		// the bubble is visible (window resize, phone rotation, fullscreen).
		let previewResizeTimer = null;
		function refreshPreviewIfVisible() {
			if ( ! previewBubble.classList.contains( 'webinar-preview-visible' ) ) {
				return;
			}
			const quality = choosePreviewQuality();
			if ( ! quality ) {
				previewBubble.classList.remove( 'webinar-preview-has-frame' );
				previewActiveQuality = null;
				return;
			}
			previewActiveQuality = quality;
			applyPreviewSize( quality );
			if ( ! previewVttCache[ quality ] ) {
				loadPreviewVtt( quality ).catch( function () {} );
			}
			if ( null !== lastPreviewHoverTime ) {
				requestPreviewCue( lastPreviewHoverTime );
			}
		}
		window.addEventListener( 'resize', function () {
			clearTimeout( previewResizeTimer );
			previewResizeTimer = setTimeout( refreshPreviewIfVisible, 150 );
		} );
		document.addEventListener( 'fullscreenchange', function () {
			clearTimeout( previewResizeTimer );
			previewResizeTimer = setTimeout( refreshPreviewIfVisible, 150 );
		} );

		// Chapters come from the JSON payload rendered by PHP inside the
		// player wrapper — fully independent of presentation markup/classes.
		const previewChapters = [];
		( function () {
			const dataEl = wrapper.querySelector( '.webinar-chapters-data' );
			if ( ! dataEl ) {
				return;
			}
			let list = null;
			try {
				list = JSON.parse( dataEl.textContent );
			} catch ( e ) {
				list = null;
			}
			if ( ! Array.isArray( list ) ) {
				return;
			}
			list.forEach( function ( item ) {
				if ( item && isFinite( +item.t ) ) {
					previewChapters.push( { t: +item.t, title: String( item.title || '' ) } );
				}
			} );
		} )();


		function showPreviewBubble( e, t ) {
			const wrapRect = wrapper.getBoundingClientRect();
			previewTime.textContent = formatTime( t );

			let chapter = '';
			for ( let i = 0; i < previewChapters.length; i++ ) {
				if ( previewChapters[ i ].t <= t ) {
					chapter = previewChapters[ i ].title;
				}
			}
			previewChapter.textContent = chapter;
			previewChapter.style.display = chapter ? '' : 'none';

			// Make it visible first so offsetWidth is measurable, then clamp
			// by the bubble's real half-width: at the edges the bubble
			// stops instead of overflowing the wrapper.
			previewBubble.classList.add( 'webinar-preview-visible' );
			const half = Math.max( 88, previewBubble.offsetWidth / 2 );
			const minX = half + 4;
			const maxX = wrapRect.width - half - 4;
			let x = e.clientX - wrapRect.left;
			if ( maxX >= minX ) {
				x = Math.max( minX, Math.min( maxX, x ) );
			} else {
				x = wrapRect.width / 2; // Narrow wrapper fallback.
			}
			previewBubble.style.left = x + 'px';

			// Grow the chapter segment under the cursor (YouTube-style).
			for ( let i = 0; i < progressSegments.length; i++ ) {
				const seg = progressSegments[ i ];
				const inSeg = t >= seg.start && ( ! isFinite( seg.end ) || t < seg.end );
				seg.el.classList.toggle( 'webinar-segment-hover', inSeg );
			}

			requestPreviewCue( t );
		}

		if ( progressBar ) {
			progressBar.addEventListener( 'mousemove', function ( e ) {
				if ( ! video.duration ) {
					return;
				}
				const rect = progressBar.getBoundingClientRect();
				const ratio = Math.max( 0, Math.min( 1, ( e.clientX - rect.left ) / rect.width ) );
				showPreviewBubble( e, ratio * video.duration );
			} );
			progressBar.addEventListener( 'mouseleave', function () {
				previewBubble.classList.remove( 'webinar-preview-visible' );
				for ( let i = 0; i < progressSegments.length; i++ ) {
					progressSegments[ i ].el.classList.remove( 'webinar-segment-hover' );
				}
			} );
		}

		/* --------------------------------------------------------------
		 * Share button: menu with "Link to video" / "Link to moment",
		 * clipboard copy + fading toast.
		 * -------------------------------------------------------------- */
		const shareHolder = wrapper.querySelector( '.webinar-share-holder' );
		const shareBtn = shareHolder ? shareHolder.querySelector( '.webinar-btn-share' ) : null;
		const shareMenu = shareHolder ? shareHolder.querySelector( '.webinar-share-menu' ) : null;
		const shareToast = shareHolder ? shareHolder.querySelector( '.webinar-share-toast' ) : null;
		let shareToastTimer = null;

		function copyText( text ) {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				return navigator.clipboard.writeText( text );
			}
			// Fallback for old browsers / non-secure contexts.
			return new Promise( function ( resolve, reject ) {
				const ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild( ta );
				ta.select();
				try {
					document.execCommand( 'copy' );
					resolve();
				} catch ( e ) {
					reject( e );
				}
				ta.remove();
			} );
		}

		if ( shareBtn && shareMenu ) {
			shareBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				shareMenu.classList.toggle( 'webinar-share-menu-visible' );
				if ( speedMenu ) {
					speedMenu.classList.remove( 'webinar-speed-menu-visible' );
				}
				if ( qualityMenu ) {
					qualityMenu.classList.remove( 'webinar-quality-menu-visible' );
				}
			} );

			shareMenu.addEventListener( 'click', function ( e ) {
				const option = e.target.closest( '.webinar-share-option' );
				if ( ! option ) {
					return;
				}
				const url = new URL( window.location.href );
				url.hash = '';
				let link = url.toString() + '#webinar-' + webinarId;
				if ( 'moment' === option.dataset.share ) {
					link += '?t=' + Math.floor( video.currentTime || 0 );
				}
				copyText( link ).then( function () {
					if ( shareToast ) {
						shareToast.hidden = false;
						clearTimeout( shareToastTimer );
						shareToastTimer = setTimeout( function () {
							shareToast.hidden = true;
						}, 2500 );
					}
				} );
				shareMenu.classList.remove( 'webinar-share-menu-visible' );
			} );
		}

		/* --------------------------------------------------------------
		 * Position memory (localStorage) + resume pill + #t= deep links.
		 * -------------------------------------------------------------- */
		const POS_KEY = 'webinar_pos_' + webinarId;
		// Debug: add ?webinar_debug=1 to the URL to trace save/restore.
		const DEBUG_POS = /[?&]webinar_debug=1/.test( window.location.search );

		let lastPosSave = 0;

		function savePosition() {
			if ( restoring || ! sessionActive ) {
				return; // Ignore restore-seek saves and inactive duplicates.
			}
			try {
				if ( ! video.duration ) {
					return;
				}
				const t = Math.floor( video.currentTime );
				if ( t < 5 || t > video.duration - 10 ) {
					// At the very start or essentially finished: forget the
					// saved position so the next load starts clean (otherwise
					// a stale mid-video value would survive a rewind to 0).
					window.localStorage.removeItem( POS_KEY );
					if ( DEBUG_POS ) {
						console.info( '[webinar] clear', POS_KEY );
					}
					return;
				}
				window.localStorage.setItem( POS_KEY, String( t ) );
				if ( DEBUG_POS ) {
					console.info( '[webinar] save', POS_KEY, t );
				}
			} catch ( e ) {
				// Storage unavailable (private mode) — silently skip.
			}
		}

		let pendingRestore = 0;
		// While the restore-seek is being applied (loadedmetadata + canplay retry),
		// ignore save triggers so we don't re-write the just-read position.
		let restoring = false;
		// Only the instance the user actually played/seeked in this session may
		// write the shared storage key (same webinar can be embedded twice).
		let sessionActive = false;

		video.addEventListener( 'loadedmetadata', function () {
			// 1) Deep links: "#webinar-<id>" (scroll only) or
			//    "#webinar-<id>?t=<sec>" (scroll + seek). Legacy "#t=<sec>"
			//    applies to the first custom player on the page.
			//    A deep link wins over the saved position.
			const hash = window.location.hash || '';
			const scoped = hash.match( /#webinar-(\d+)(?:[?&]t=(\d+))?/ );
			const legacy = hash.match( /#t=(\d+)/ );
			let seekTo = 0;
			if ( scoped && String( webinarId ) === scoped[ 1 ] ) {
				seekTo = parseInt( scoped[ 2 ] || '0', 10 ) || 0;
			} else if ( legacy && ! scoped && wrapper === document.querySelector( '.webinar-player-wrapper[data-player-type="custom"]' ) ) {
				seekTo = parseInt( legacy[ 1 ], 10 ) || 0;
			} else {
				// 2) Saved position: silently restore on reload, no UI.
				try {
					seekTo = parseInt( window.localStorage.getItem( POS_KEY ), 10 ) || 0;
				} catch ( e ) {
					seekTo = 0;
				}
			}
			let storedRaw = null;
			try {
				storedRaw = window.localStorage.getItem( POS_KEY );
			} catch ( e ) {
				// Ignore.
			}
			if ( seekTo && video.duration && seekTo <= video.duration - 15 ) {
				pendingRestore = seekTo;
				restoring = true;
				video.currentTime = seekTo;
			}
			if ( DEBUG_POS ) {
				console.info( '[webinar] restore', {
					id: webinarId,
					seekTo: seekTo,
					duration: video.duration,
					localStorageRaw: storedRaw,
				} );
			}
		} );

		// hls.js can snap an early seek back to 0 while it prepares the
		// first fragments; re-apply the position once playback is ready.
		video.addEventListener( 'canplay', function onCanPlay() {
			if ( ! pendingRestore ) {
				return;
			}
			if ( Math.abs( video.currentTime - pendingRestore ) > 2 ) {
				video.currentTime = pendingRestore;
			}
			if ( DEBUG_POS ) {
				console.info( '[webinar] restore re-applied on canplay', video.currentTime );
			}
			pendingRestore = 0;
			// Re-enable normal saves after a tiny grace: some engines fire
			// an extra 'seeked' right after canplay; let it pass.
			setTimeout( function () { restoring = false; }, 500 );
			video.removeEventListener( 'canplay', onCanPlay );
		} );

		video.addEventListener( 'timeupdate', function () {
			const now = Date.now();
			if ( now - lastPosSave > 3000 ) {
				lastPosSave = now;
				savePosition();
			}
		} );
		video.addEventListener( 'seeked', function () {
			if ( ! restoring ) {
				sessionActive = true; // User-initiated seek.
			}
			savePosition();
			// Also clear the restoring flag in case canplay never fired
			// (e.g. fast MP4 with no buffering).
			restoring = false;
		} );
		video.addEventListener( 'play', function () {
			sessionActive = true;
		} );
		video.addEventListener( 'pause', savePosition );
		window.addEventListener( 'pagehide', savePosition );
		document.addEventListener( 'visibilitychange', function () {
			if ( 'hidden' === document.visibilityState ) {
				savePosition();
			}
		} );

		video.addEventListener( 'ended', function () {
			try {
				window.localStorage.removeItem( POS_KEY );
			} catch ( e ) {
				// Ignore.
			}
		} );

		// Compound hashes (#webinar-5?t=9) are not native anchors, so scroll
		// to the target widget manually when the URL points at it.
		if ( 0 === ( window.location.hash || '' ).indexOf( '#webinar-' + webinarId ) ) {
			const scrollTarget = wrapper.closest( '.webinar-widget' ) || wrapper;
			scrollTarget.scrollIntoView( { block: 'start' } );
		}

		// Update progress bar on timeupdate
		video.addEventListener( 'timeupdate', function () {
			if ( ! progressThumb || ! video.duration ) {
				return;
			}
			const percent = ( video.currentTime / video.duration ) * 100;
			progressThumb.style.left = percent + '%';
			updateSegmentFills();
			if ( timeCurrent ) {
				timeCurrent.textContent = formatTime( video.currentTime );
			}
			if ( progressBar ) {
				progressBar.setAttribute( 'aria-valuenow', Math.round( percent ) );
			}
		} );

		// Update buffered progress (per segment)
		video.addEventListener( 'progress', function () {
			updateSegmentFills();
		} );

		// Set duration when metadata loads
		video.addEventListener( 'loadedmetadata', function () {
			if ( timeDuration ) {
				timeDuration.textContent = formatTime( video.duration );
			}
			if ( progressBar ) {
				progressBar.setAttribute( 'aria-valuemax', Math.round( video.duration ) );
			}
			buildProgressSegments();
		} );

		// Auto-hide controls on mouse activity
		wrapper.addEventListener( 'mousemove', showControls );
		wrapper.addEventListener( 'mouseleave', function () {
			if ( ! video.paused ) {
				hideControls();
			}
		} );
		wrapper.addEventListener( 'touchstart', showControls, { passive: true } );

		// Close speed / quality menus when clicking outside
		document.addEventListener( 'click', function ( e ) {
			if ( speedMenu && speedBtn && ! speedBtn.contains( e.target ) && ! speedMenu.contains( e.target ) ) {
				speedMenu.classList.remove( 'webinar-speed-menu-visible' );
			}
			if ( qualityMenu && qualityHolder && ! qualityHolder.contains( e.target ) ) {
				qualityMenu.classList.remove( 'webinar-quality-menu-visible' );
			}
			if ( shareMenu && shareHolder && ! shareHolder.contains( e.target ) ) {
				shareMenu.classList.remove( 'webinar-share-menu-visible' );
			}
		} );

		// Initial state
		wrapper.classList.add( 'webinar-paused' );

		// If metadata was already available (cached media), build now.
		if ( video.duration ) {
			buildProgressSegments();
		}

		// Touch detection via maxTouchPoints: unlike (hover)/(pointer) media
		// queries, this stays truthful in "desktop mode" mobile browsers
		// (Yandex, Huawei) and prevents sticky-hover slider flashes.
		const isTouchDevice = ( navigator.maxTouchPoints || 0 ) > 0 || 'ontouchstart' in window;
		if ( isTouchDevice ) {
			wrapper.classList.add( 'webinar-touch-device' );
			// Global flag: CSS mobile rules keyed off html.webinar-touch-device
			// stay truthful in "desktop mode" mobile browsers (Yandex, Huawei).
			document.documentElement.classList.add( 'webinar-touch-device' );
		}

		if ( volumeSlider ) {
			volumeSlider.value = video.volume;
		}
	}

	/**
	 * Initialize all custom players on the page.
	 */
	function initAll() {
		const wrappers = document.querySelectorAll( '.webinar-player-wrapper[data-player-type="custom"]' );
		wrappers.forEach( function ( wrapper ) {
			const video = wrapper.querySelector( 'video' );
			if ( video ) {
				initPlayer( video, wrapper );
			}
		} );
	}

	// Initialize on DOM ready
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}

	// Re-scan for dynamically inserted markup (Gutenberg ServerSideRender,
	// AJAX navigation). initPlayer is idempotent (WeakSet), so this is safe.
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

	// Expose for dynamic content (e.g., Gutenberg preview updates)
	window.WebinarBlock = window.WebinarBlock || {};
	window.WebinarBlock.initCustomControls = initAll;
	window.WebinarBlock.formatViews = formatViews;

} )();