/**
 * Webinar tabs: switching + sliding indicator + swipe.
 * Swipe logic is kept verbatim from the debugged original.
 * Scoped per widget so multiple webinars never interfere.
 */
( function () {
	'use strict';

	var MOBILE_MAX = 768; // swipe works only below this width
	var SWIPE_THRESHOLD = 0.25; // switch threshold: 25% of the tabbar width

	function initTabs( widget ) {
		var tabButtons = [].slice.call( widget.querySelectorAll( '.webinar-tabbar .webinar-tab-btn' ) );
		var tabContents = [].slice.call( widget.querySelectorAll( '.webinar-tab-content' ) );

		if ( 0 === tabButtons.length ) {
			return;
		}

		tabButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var targetTab = this.getAttribute( 'data-tab' );

				// Deactivate buttons in THIS webinar only.
				tabButtons.forEach( function ( b ) {
					b.classList.remove( 'active' );
				} );
				this.classList.add( 'active' );

				tabContents.forEach( function ( content ) {
					content.classList.remove( 'active' );
					if ( content.getAttribute( 'data-tab' ) === targetTab ) {
						content.classList.add( 'active' );
					}
				} );
			} );
		} );
	}

	function initTabSwipe( root ) {
		var tabs = root.querySelector( '.webinar-tabbar' );
		if ( ! tabs ) {
			return;
		}
		var buttons = [].slice.call( tabs.querySelectorAll( '.webinar-tab-btn' ) );
		if ( buttons.length < 2 ) {
			return;
		}

		// Sliding indicator.
		var indicator = document.createElement( 'span' );
		indicator.className = 'webinar-tab-indicator';
		tabs.appendChild( indicator );

		function activeIndex() {
			var i;
			for ( i = 0; i < buttons.length; i++ ) {
				if ( buttons[ i ].classList.contains( 'active' ) ) {
					return i;
				}
			}
			return 0;
		}

		function placeIndicator( animate ) {
			var btn = buttons[ activeIndex() ];
			if ( ! btn ) {
				return;
			}
			if ( animate ) {
				indicator.style.transition = 'left 0.3s ease, width 0.3s ease';
			} else {
				indicator.style.transition = 'none';
			}
			indicator.style.left = btn.offsetLeft + 'px';
			indicator.style.width = btn.offsetWidth + 'px';
			if ( ! animate ) {
				void indicator.offsetWidth;
			}
		}

		placeIndicator( false );
		window.addEventListener( 'resize', function () { placeIndicator( false ); } );
		window.addEventListener( 'load', function () { placeIndicator( false ); } );

		var k;
		for ( k = 0; k < buttons.length; k++ ) {
			buttons[ k ].addEventListener( 'click', function () { placeIndicator( true ); } );
		}

		// ===== SWIPE (debugged original logic, verbatim) =====
		var tracking = false;
		var horizontal = false;
		var directMode = false; // swipe started on the tabbar itself
		var startX = 0, startY = 0, lastX = 0;
		var fromLeft = 0, fromWidth = 0;
		var targetLeft = 0, targetWidth = 0;
		var targetIndex = -1;

		function tabsVisible() {
			var r = tabs.getBoundingClientRect();
			return r.bottom > 0 && r.top < window.innerHeight;
		}

		root.addEventListener( 'touchstart', function ( e ) {
			tracking = false;
			horizontal = false;
			directMode = false;
			targetIndex = -1;
			if ( window.innerWidth > MOBILE_MAX ) {
				return;
			}
			if ( ! tabsVisible() ) {
				return;
			}
			var t = e.target;
			if ( t.closest ) {
				// Custom player: the controls overlay must never start a tab
				// swipe (it has its own tap/drag interactions), while the plain
				// video area swipes tabs like any other content.
				if ( t.closest( '.webinar-custom-controls' ) ) {
					return;
				}
				var playerWrap = t.closest( '.webinar-player-wrapper' );
				var customPlayer = playerWrap && 'custom' === playerWrap.getAttribute( 'data-player-type' );
				if ( ! customPlayer && t.closest( 'video' ) ) {
					return; // Native player: keep the historical exclusion.
				}
				if ( t.closest( '.webinar-slider-viewport' ) ) {
					return;
				}
				if ( t.closest( '.webinar-slider-nav' ) ) {
					return;
				}
				if ( t.closest( '.webinar-header' ) ) {
					return;
				}
			}
			// Where the swipe started: on the tabs or on the content.
			if ( t.closest ) {
				if ( t.closest( '.webinar-tabbar' ) ) {
					directMode = true;
				}
			}

			tracking = true;
			startX = e.touches[ 0 ].clientX;
			startY = e.touches[ 0 ].clientY;
			lastX = startX;
		}, { passive: true } );

		root.addEventListener( 'touchmove', function ( e ) {
			if ( ! tracking ) {
				return;
			}
			var x = e.touches[ 0 ].clientX;
			var y = e.touches[ 0 ].clientY;
			var diffX = x - startX;
			var diffY = y - startY;

			if ( ! horizontal ) {
				var adx = Math.abs( diffX );
				var ady = Math.abs( diffY );
				if ( adx > ady ) {
					if ( adx > 10 ) {
						var idx = activeIndex();
						var next;
						if ( directMode ) {
							// Dragging the tab strip: indicator follows the finger.
							if ( diffX < 0 ) {
								next = idx - 1;
							} else {
								next = idx + 1;
							}
						} else {
							// Flipping the content: standard carousel.
							if ( diffX < 0 ) {
								next = idx + 1;
							} else {
								next = idx - 1;
							}
						}
						if ( next < 0 ) {
							tracking = false;
							return;
						}
						if ( next >= buttons.length ) {
							tracking = false;
							return;
						}
						horizontal = true;
						targetIndex = next;
						fromLeft = buttons[ idx ].offsetLeft;
						fromWidth = buttons[ idx ].offsetWidth;
						targetLeft = buttons[ next ].offsetLeft;
						targetWidth = buttons[ next ].offsetWidth;
						indicator.style.transition = 'none';
					}
				} else {
					if ( ady > 10 ) {
						tracking = false;
						return;
					}
				}
			}

			if ( ! horizontal ) {
				return;
			}

			lastX = x;
			var w = tabs.offsetWidth;
			if ( w < 1 ) {
				w = 1;
			}
			var p = Math.abs( diffX ) / w;
			if ( p > 1 ) {
				p = 1;
			}

			indicator.style.left = ( fromLeft + ( targetLeft - fromLeft ) * p ) + 'px';
			indicator.style.width = ( fromWidth + ( targetWidth - fromWidth ) * p ) + 'px';
		}, { passive: true } );

		root.addEventListener( 'touchend', function () {
			if ( ! tracking ) {
				return;
			}
			tracking = false;
			if ( ! horizontal ) {
				return;
			}
			horizontal = false;

			var diffX = lastX - startX;
			var w = tabs.offsetWidth;
			if ( w < 1 ) {
				w = 1;
			}
			var p = Math.abs( diffX ) / w;

			if ( p > SWIPE_THRESHOLD ) {
				if ( targetIndex >= 0 ) {
					buttons[ targetIndex ].click();
				}
			} else {
				placeIndicator( true );
			}
			targetIndex = -1;
		}, { passive: true } );

		function abortSwipe() {
			if ( ! tracking ) {
				return;
			}
			tracking = false;
			if ( horizontal ) {
				horizontal = false;
				placeIndicator( true );
			}
		}

		root.addEventListener( 'touchcancel', abortSwipe );
		root.addEventListener( 'pointercancel', abortSwipe );
	}

	function initAll() {
		var widgets = document.querySelectorAll( '.webinar-widget' );
		[].forEach.call( widgets, function ( widget ) {
			initTabs( widget );
			var root = widget.querySelector( '.webinar-tabs' );
			if ( root ) {
				initTabSwipe( root );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();