/**
 * Sliders for takeaways and detailed blocks.
 * Drag/wrap logic kept verbatim from the debugged original.
 */
var front = window.webinarBlockFront || {};

document.addEventListener( 'DOMContentLoaded', function () {
	'use strict';

	var widgets = document.querySelectorAll( '.webinar-widget' );
	[].forEach.call( widgets, function ( widget ) {
		buildWebinarSlider( widget, '.webinar-takeaways-grid', 'li', 'takeaways' );
		buildWebinarSlider( widget, '.webinar-details-columns', '.webinar-detail-block', 'details' );
	} );
} );

function buildWebinarSlider( widget, rootSelector, itemSelector, variant ) {
	var root = widget.querySelector( rootSelector );

	if ( ! root ) {
		return;
	}
	if ( root.getAttribute( 'data-slider-built' ) ) {
		return;
	}
	root.setAttribute( 'data-slider-built', '1' );

	var items = root.querySelectorAll( itemSelector );
	var total = items.length;
	if ( total < 2 ) {
		return;
	}

	// ===== DOM structure =====
	var outer = document.createElement( 'div' );
	outer.className = 'webinar-slider-outer webinar-slider--' + variant;
	outer.style.display = 'none';

	var viewport = document.createElement( 'div' );
	viewport.className = 'webinar-slider-viewport';

	var grid = document.createElement( 'div' );
	grid.className = 'webinar-slider-grid';

	var i;
	for ( i = 0; i < total; i++ ) {
		var slide = document.createElement( 'div' );
		slide.className = 'webinar-slider-slide';
		slide.appendChild( items[ i ].cloneNode( true ) );
		grid.appendChild( slide );
	}

	viewport.appendChild( grid );
	outer.appendChild( viewport );

	var totalStr = String( total ).padStart( 2, '0' );
	var nav = document.createElement( 'div' );
	nav.className = 'webinar-slider-nav';
	var prevLabel = front.prevSlide || 'Previous slide';
	var nextLabel = front.nextSlide || 'Next slide';
	nav.innerHTML =
		'<button class="webinar-slider-prev" aria-label="' + prevLabel + '">' +
		'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button>' +
		'<div class="webinar-slider-dots"></div>' +
		'<button class="webinar-slider-next" aria-label="' + nextLabel + '">' +
		'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></button>';
	outer.appendChild( nav );

	var counter = document.createElement( 'div' );
	counter.className = 'webinar-slider-counter';
	counter.innerHTML = '<span class="webinar-counter-current">01</span><span class="webinar-counter-sep">/</span><span class="webinar-counter-total">' + totalStr + '</span>';
	outer.appendChild( counter );
	root.parentNode.insertBefore( outer, root.nextSibling );

	// ===== State =====
	var slides = [].slice.call( grid.children );
	var dotsBox = nav.querySelector( '.webinar-slider-dots' );
	var counterCurrent = counter.querySelector( '.webinar-counter-current' );
	var currentIndex = 0;
	var isLocked = false;
	var VpW = 0;

	function measure() {
		var w = viewport.offsetWidth;
		if ( w > 0 ) {
			VpW = w;
		}
	}

	// Dots.
	for ( i = 0; i < total; i++ ) {
		var dot = document.createElement( 'span' );
		dot.className = 'webinar-slider-dot';
		if ( i === 0 ) {
			dot.classList.add( 'active' );
		}

		( function ( idx, d ) {
			d.addEventListener( 'click', function () { goTo( idx ); } );
		} )( i, dot );
		dotsBox.appendChild( dot );
	}
	var dots = [].slice.call( dotsBox.children );

	function refreshUI( index ) {
		var k;
		for ( k = 0; k < total; k++ ) {
			slides[ k ].style.opacity = ( k === index ) ? '1' : '0.35';
			if ( k === index ) {
				dots[ k ].classList.add( 'active' );
			} else {
				dots[ k ].classList.remove( 'active' );
			}
		}
		counterCurrent.textContent = String( index + 1 ).padStart( 2, '0' );
	}

	function setTransform( px, animate ) {
		grid.style.transition = animate ? 'transform 0.4s ease' : 'none';
		grid.style.transform = 'translateX(' + px + 'px)';
	}

	function makeClone( target ) {
		var c = slides[ target ].cloneNode( true );
		c.className = 'webinar-slider-slide webinar-slider-clone';
		c.style.width = VpW + 'px';
		c.style.transition = 'none';
		c.style.opacity = '0.35';
		return c;
	}

	var activeClone = null;

	// Wrapped transition (buttons/dots across the edge).
	function wrapTransition( dir ) {
		measure();
		var target = ( dir === 'next' ) ? 0 : total - 1;
		var virtual = ( dir === 'next' ) ? total : -1;
		var clone = makeClone( target );
		viewport.appendChild( clone );
		clone.style.left = ( ( virtual - currentIndex ) * VpW ) + 'px';
		void clone.offsetWidth;

		isLocked = true;
		grid.style.transition = 'transform 0.3s ease';
		grid.style.transform = 'translateX(' + ( -virtual * VpW ) + 'px)';
		clone.style.transition = 'left 0.3s ease, opacity 0.3s ease';
		clone.style.left = '0px';
		clone.style.opacity = '1';
		slides[ currentIndex ].style.opacity = '0.35';
		slides[ target ].style.opacity = '1';

		var doneClone = clone;
		var snapTo = target;
		setTimeout( function () {
			currentIndex = snapTo;
			setTransform( -snapTo * VpW, false );
			refreshUI( currentIndex );
			requestAnimationFrame( function () {
				if ( doneClone ) {
					if ( doneClone.parentNode ) {
						doneClone.parentNode.removeChild( doneClone );
					}
				}
				isLocked = false;
			} );
		}, 320 );
	}

	function goTo( index ) {
		measure();
		if ( isLocked ) {
			return;
		}
		if ( index === currentIndex ) {
			return;
		}

		var isNextWrap = ( currentIndex === total - 1 ) * ( index === 0 );
		var isPrevWrap = ( currentIndex === 0 ) * ( index === total - 1 );

		if ( isNextWrap ) {
			wrapTransition( 'next' );
			return;
		}
		if ( isPrevWrap ) {
			wrapTransition( 'prev' );
			return;
		}

		isLocked = true;
		slides[ currentIndex ].style.opacity = '0.35';
		slides[ index ].style.opacity = '1';
		currentIndex = index;
		setTransform( -index * VpW, true );
		refreshUI( currentIndex );
		setTimeout( function () { isLocked = false; }, 450 );
	}

	nav.querySelector( '.webinar-slider-prev' ).addEventListener( 'click', function () {
		goTo( ( currentIndex - 1 + total ) % total );
	} );
	nav.querySelector( '.webinar-slider-next' ).addEventListener( 'click', function () {
		goTo( ( currentIndex + 1 ) % total );
	} );

	// ===== Dragging =====
	var isDragging = false;
	var fingerOnScreen = false;
	var startX = 0, startY = 0, currentX = 0;
	var horizontal = false;
	var preparedDir = null; // direction for which the incoming tile is prepared
	var isWrap = false, virtual = 0, target = 0, incoming = null;

	// Remove the current incoming tile (clone — from DOM, real — restore opacity).
	function cleanupIncoming() {
		if ( incoming ) {
			if ( isWrap ) {
				if ( activeClone ) {
					var dc = activeClone;
					activeClone = null;
					if ( dc.parentNode ) {
						dc.parentNode.removeChild( dc );
					}
				}
			} else {
				incoming.style.opacity = '0.35';
			}
		}
		incoming = null;
		isWrap = false;
		virtual = 0;
	}

	// Prepare the incoming tile for direction dir (with a clone at the edges).
	function prepare( dir ) {
		cleanupIncoming();
		if ( dir === 'next' ) {
			if ( currentIndex === total - 1 ) {
				isWrap = true;
				virtual = total;
				target = 0;
				var c1 = makeClone( 0 );
				activeClone = c1;
				viewport.appendChild( c1 );
				incoming = c1;
			} else {
				target = currentIndex + 1;
				incoming = slides[ target ];
			}
		} else {
			if ( currentIndex === 0 ) {
				isWrap = true;
				virtual = -1;
				target = total - 1;
				var c2 = makeClone( total - 1 );
				activeClone = c2;
				viewport.appendChild( c2 );
				incoming = c2;
			} else {
				target = currentIndex - 1;
				incoming = slides[ target ];
			}
		}
	}

	function startDrag( x, y ) {
		isDragging = true;
		startX = x;
		startY = y;
		currentX = x;
		horizontal = false;
		preparedDir = null;
		cleanupIncoming();
		measure();
		grid.style.transition = 'none';
	}

	viewport.addEventListener( 'touchstart', function ( e ) {
		fingerOnScreen = true;
		if ( isLocked ) {
			return;
		}
		startDrag( e.touches[ 0 ].clientX, e.touches[ 0 ].clientY );
	}, { passive: true } );

	viewport.addEventListener( 'touchmove', function ( e ) {
		var tx = e.touches[ 0 ].clientX;
		var ty = e.touches[ 0 ].clientY;

		// "On the fly" swipe pickup: finger on screen, lock has ended.
		if ( ! isDragging ) {
			if ( fingerOnScreen ) {
				if ( ! isLocked ) {
					startDrag( tx, ty );
				}
			}
		}
		if ( ! isDragging ) {
			return;
		}

		var diffX = tx - startX;
		var diffY = ty - startY;

		if ( ! horizontal ) {
			var adx = Math.abs( diffX );
			var ady = Math.abs( diffY );
			if ( adx > ady ) {
				if ( adx > 10 ) {
					horizontal = true;
				}
			} else {
				if ( ady > 10 ) {
					isDragging = false;
					return;
				}
			}
		}
		if ( ! horizontal ) {
			return;
		}

		currentX = tx;

		diffX = currentX - startX;

		// Direction with a 5px dead zone; on direction change
		// re-prepare the incoming tile (clone is drawn at the edges).
		var wantDir = null;
		if ( diffX < -5 ) {
			wantDir = 'next';
		} else {
			if ( diffX > 5 ) {
				wantDir = 'prev';
			}
		}
		if ( wantDir !== null ) {
			if ( preparedDir !== wantDir ) {
				prepare( wantDir );
				preparedDir = wantDir;
			}
		}
		if ( preparedDir === null ) {
			return;
		}

		var progress = Math.abs( diffX ) / VpW;
		if ( progress > 1 ) {
			progress = 1;
		}

		grid.style.transform = 'translateX(' + ( -currentIndex * VpW + diffX ) + 'px)';
		slides[ currentIndex ].style.opacity = String( Math.max( 0.35, 1 - progress ) );
		if ( incoming ) {
			incoming.style.opacity = String( Math.min( 1, 0.35 + progress ) );
		}
		if ( isWrap ) {
			if ( activeClone ) {
				activeClone.style.left = ( ( virtual - currentIndex ) * VpW + diffX ) + 'px';
			}
		}
	}, { passive: true } );

	viewport.addEventListener( 'touchend', function () {
		fingerOnScreen = false;
		if ( ! isDragging ) {
			return;
		}
		isDragging = false;
		if ( preparedDir === null ) {
			return;
		}

		var diffX = currentX - startX;
		var threshold = VpW * 0.25;

		if ( Math.abs( diffX ) > threshold ) {
			if ( isWrap ) {
				isLocked = true;
				grid.style.transition = 'transform 0.3s ease';
				grid.style.transform = 'translateX(' + ( -virtual * VpW ) + 'px)';
				if ( activeClone ) {
					activeClone.style.transition = 'left 0.3s ease, opacity 0.3s ease';
					activeClone.style.left = '0px';
					activeClone.style.opacity = '1';
				}
				slides[ currentIndex ].style.opacity = '0.35';
				slides[ target ].style.opacity = '1';
				var snapTo = target;
				var doneClone = activeClone;
				activeClone = null;
				setTimeout( function () {
					currentIndex = snapTo;
					setTransform( -snapTo * VpW, false );
					refreshUI( currentIndex );
					requestAnimationFrame( function () {
						if ( doneClone ) {
							if ( doneClone.parentNode ) {
								doneClone.parentNode.removeChild( doneClone );
							}
						}
						isLocked = false;
					} );
				}, 320 );
			} else {
				isLocked = true;
				slides[ currentIndex ].style.opacity = '0.35';
				slides[ target ].style.opacity = '1';
				currentIndex = target;
				setTransform( -target * VpW, true );
				refreshUI( currentIndex );
				setTimeout( function () { isLocked = false; }, 400 );
			}
		} else {
			// Snap back.
			grid.style.transition = 'transform 0.3s ease';
			grid.style.transform = 'translateX(' + ( -currentIndex * VpW ) + 'px)';
			slides[ currentIndex ].style.opacity = '1';
			if ( isWrap ) {
				if ( activeClone ) {
					var dyingClone = activeClone;
					activeClone = null;
					dyingClone.style.transition = 'left 0.3s ease, opacity 0.3s ease';
					dyingClone.style.left = ( ( virtual - currentIndex ) * VpW ) + 'px';
					dyingClone.style.opacity = '0.35';
					setTimeout( function () {
						if ( dyingClone ) {
							dyingClone.parentNode.removeChild( dyingClone );
						}
					}, 320 );
				}
			} else {
				if ( incoming ) {
					incoming.style.opacity = '0.35';
				}
			}
		}
		preparedDir = null;
		horizontal = false;
		isWrap = false;
		incoming = null;
	}, { passive: true } );

	function abortDrag() {
		fingerOnScreen = false;
		if ( ! isDragging ) {
			return;
		}
		isDragging = false;
		grid.style.transition = 'transform 0.3s ease';
		grid.style.transform = 'translateX(' + ( -currentIndex * VpW ) + 'px)';
		slides[ currentIndex ].style.opacity = '1';
		cleanupIncoming();
		preparedDir = null;
		horizontal = false;
		incoming = null;
	}

	viewport.addEventListener( 'touchcancel', abortDrag );
	viewport.addEventListener( 'pointercancel', abortDrag );

	// ===== Mode: slider or regular grid =====
	function isSingleColumn() {
		var prevDisplay = root.style.display;
		root.style.display = 'grid';
		var cols = window.getComputedStyle( root ).gridTemplateColumns;
		root.style.display = prevDisplay;
		if ( cols === 'none' ) {
			return true;
		}
		var parts = cols.trim().split( /\s+/ );
		return parts.length <= 1;
	}

	function applyMode() {
		if ( isSingleColumn() ) {
			outer.style.display = 'block';
			root.style.display = 'none';
			measure();
			setTransform( -currentIndex * VpW, false );
		} else {
			outer.style.display = 'none';
			root.style.display = '';
		}
	}

	var resizeTimer = null;
	window.addEventListener( 'resize', function () {
		clearTimeout( resizeTimer );
		resizeTimer = setTimeout( function () {
			measure();
			setTransform( -currentIndex * VpW, false );
			applyMode();
		}, 200 );
	} );

	refreshUI( 0 );
	setTransform( 0, false );
	applyMode();
}