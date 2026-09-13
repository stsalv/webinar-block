/**
 * Smooth open/close for "Detailed content" — final debugged version.
 */
( function () {
	'use strict';

	var DURATION = 450; // production value (was 1000 for tests).

	window.setDetailsAnimated = function ( d, shouldOpen ) {
		if ( ! d ) {
			return;
		}
		if ( d._animating ) {
			return;
		}
		var body = d.querySelector( '.webinar-details-body' );
		if ( ! body ) {
			return;
		}

		var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( reduce ) {
			d.open = shouldOpen;
			return;
		}

		d._animating = true;

		var fontsReady = Promise.resolve();
		if ( document.fonts ) {
			if ( document.fonts.ready ) {
				fontsReady = document.fonts.ready;
			}
		}

		function twoFrames() {
			return new Promise( function ( resolve ) {
				requestAnimationFrame( function () {
					requestAnimationFrame( function () { resolve(); } );
				} );
			} );
		}

		function num( v ) {
			var n = parseFloat( v );
			if ( isNaN( n ) ) {
				return 0;
			}
			return n;
		}

		function cleanup() {
			body.style.height = '';
			body.style.opacity = '';
			body.style.overflow = '';
			body.style.transition = '';
			body.style.paddingTop = '';
			body.style.paddingBottom = '';
			d._animating = false;
		}

		function startTransition() {
			var ease = 'cubic-bezier(0.4, 0, 0.2, 1)';
			body.style.transition =
				'height ' + DURATION + 'ms ' + ease + ', ' +
				'padding-top ' + DURATION + 'ms ' + ease + ', ' +
				'padding-bottom ' + DURATION + 'ms ' + ease + ', ' +
				'opacity ' + DURATION + 'ms ease';
		}

		if ( shouldOpen ) {
			// ===== OPEN =====
			d.open = true;

			// Collapse INSTANTLY in the same frame: the page never stretches,
			// the scrollbar does not jump.
			body.style.overflow = 'hidden';
			body.style.opacity = '0';
			body.style.height = '0px';
			body.style.paddingTop = '0px';
			body.style.paddingBottom = '0px';

			Promise.all( [ fontsReady, twoFrames() ] ).then( function () {
				// Temporarily restore the natural state for an exact measure.
				// Everything is synchronous in one task — no intermediate frame paints.
				body.style.height = '';
				body.style.paddingTop = '';
				body.style.paddingBottom = '';

				var cs = window.getComputedStyle( body );
				var padT = num( cs.paddingTop );
				var padB = num( cs.paddingBottom );
				var totalH = body.getBoundingClientRect().height;
				var contentH = totalH - padT - padB;
				if ( contentH < 0 ) {
					contentH = 0;
				}

				// Collapse back and fix the start.
				body.style.height = '0px';
				body.style.paddingTop = '0px';
				body.style.paddingBottom = '0px';
				void body.offsetHeight;

				// Animate height and padding together.
				startTransition();
				body.style.height = contentH + 'px';
				body.style.paddingTop = padT + 'px';
				body.style.paddingBottom = padB + 'px';
				body.style.opacity = '1';

				setTimeout( cleanup, DURATION + 50 );
			} );
		} else {
			// ===== CLOSE =====
			d.classList.add( 'webinar-details-closing' );

			var cs2 = window.getComputedStyle( body );
			var padT2 = num( cs2.paddingTop );
			var padB2 = num( cs2.paddingBottom );
			var totalH2 = body.getBoundingClientRect().height;
			var contentH2 = totalH2 - padT2 - padB2;
			if ( contentH2 < 0 ) {
				contentH2 = 0;
			}

			// Fix the starting point.
			body.style.overflow = 'hidden';
			body.style.height = contentH2 + 'px';
			body.style.paddingTop = padT2 + 'px';
			body.style.paddingBottom = padB2 + 'px';
			body.style.opacity = '1';
			void body.offsetHeight;

			// Animate EVERYTHING to zero — no tail left at the end.
			startTransition();
			body.style.height = '0px';
			body.style.paddingTop = '0px';
			body.style.paddingBottom = '0px';
			body.style.opacity = '0';

			setTimeout( function () {
				d.open = false;
				d.classList.remove( 'webinar-details-closing' );
				cleanup();
			}, DURATION + 50 );
		}
	};

	function initAll() {
		var wraps = document.querySelectorAll( '.webinar-details-wrap' );
		[].forEach.call( wraps, function ( d ) {
			var summary = d.querySelector( 'summary' );
			if ( summary && ! summary.dataset.animatedBound ) {
				summary.dataset.animatedBound = '1';
				summary.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					if ( d._animating ) {
						return;
					}
					window.setDetailsAnimated( d, ! d.open );
				} );
			}

			// Bottom "collapse" button (plugin markup has no inline onclick).
			var closeBtn = d.querySelector( '.webinar-details-close' );
			if ( closeBtn && ! closeBtn.dataset.animatedBound ) {
				closeBtn.dataset.animatedBound = '1';
				closeBtn.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					window.closeDetailed( closeBtn );
				} );
			}
		} );
	}

	// Collapse button handler (also callable externally).
	window.closeDetailed = function ( btn ) {
		var d = null;
		if ( btn && btn.closest ) {
			d = btn.closest( 'details' );
		}
		if ( ! d ) {
			return;
		}
		if ( d._animating ) {
			return;
		}
		window.setDetailsAnimated( d, false );
		setTimeout( function () {
			d.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}, DURATION + 80 );
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();