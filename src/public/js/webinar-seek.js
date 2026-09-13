/**
 * Universal webinar controls: seek, timeline clicks, active chapter
 * highlight + view/click counters (AJAX, rate limited server-side).
 */
( function () {
	'use strict';

	var front = window.webinarBlockFront || {};

	function post( action, webinarId, extra ) {
		if ( ! front.ajaxUrl ) {
			return Promise.resolve( null );
		}
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', front.nonce || '' );
		body.append( 'webinar_id', webinarId );
		if ( extra ) {
			Object.keys( extra ).forEach( function ( key ) {
				body.append( key, extra[ key ] );
			} );
		}
		return fetch( front.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) { return response.json(); } )
			.catch( function () { return null; } );
	}

	// Seek: find the video inside own widget.
	window.seekWebinarVideo = function ( seconds, el ) {
		var root = null;
		if ( el && el.closest ) {
			root = el.closest( '.webinar-widget' );
		}

		var video = root ? root.querySelector( 'video' ) : null;
		if ( ! video ) {
			video = document.querySelector( 'video' );
		}
		if ( ! video ) {
			return;
		}

		video.currentTime = seconds;
		video.play();

		// Scroll to the video ONLY if it is not visible, aligned to top.
		var r = video.getBoundingClientRect();
		var isVisible = false;
		if ( r.bottom > 0 ) {
			if ( r.top < window.innerHeight ) {
				isVisible = true;
			}
		}
		if ( ! isVisible ) {
			video.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	};

	// Active chapter highlight — WeakSet for reliable init.
	var initedVideos = new WeakSet();

	function initWidget( widget ) {
		var video = widget.querySelector( 'video' );
		var items = [].slice.call( widget.querySelectorAll( '.webinar-timeline-item' ) );
		var webinarId = widget.getAttribute( 'data-webinar-id' ) || '0';

		// Views counter: once per session, on the first real play.
		if ( video && ! video.dataset.viewTracked ) {
			video.dataset.viewTracked = '1';
			video.addEventListener( 'play', function onPlay() {
				video.removeEventListener( 'play', onPlay );
				var key = 'webinar_viewed_' + webinarId;
				var counted = false;
				try {
					counted = '1' === window.sessionStorage.getItem( key );
				} catch ( error ) {
					counted = false;
				}
				if ( counted ) {
					return;
				}
				try {
					window.sessionStorage.setItem( key, '1' );
				} catch ( error ) {
					// Session storage unavailable — server rate limit still applies.
				}
				post( 'webinar_track_view', webinarId ).then( function ( json ) {
					if ( json && json.success && json.data && json.data.count ) {
						var counter = widget.querySelector( '.webinar-views-count' );
						if ( counter ) {
							var fmt = window.WebinarBlock && window.WebinarBlock.formatViews;
							counter.textContent = fmt ? fmt( json.data.count ) : String( json.data.count );
						}
					}
				} );
			} );
		}


		// Detailed content time links. Delegated to the widget because the
		// slider clones the blocks (clones carry no direct listeners).
		widget.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( '.webinar-detail-time-link' );
			if ( ! link || ! widget.contains( link ) ) {
				return;
			}
			e.preventDefault();
			var t = +link.getAttribute( 'data-time' ) || 0;
			window.seekWebinarVideo( t, link );
			post( 'webinar_track_click', webinarId, { index: link.getAttribute( 'data-index' ) || 0 } );
		} );


		if ( ! video || ! items.length ) {
			return;
		}

		// Whole item clickable (by data-time); links seek too.
		items.forEach( function ( li ) {
			li.style.cursor = 'pointer';
			li.addEventListener( 'click', function ( e ) {
				var link = e.target.closest( 'a' );
				if ( link ) {
					e.preventDefault();
				}
				var t = +li.getAttribute( 'data-time' ) || 0;
				window.seekWebinarVideo( t, li );
				post( 'webinar_track_click', webinarId, { index: li.getAttribute( 'data-index' ) || 0 } );
			} );
		} );

		if ( initedVideos.has( video ) ) {
			return;
		}
		initedVideos.add( video );

		// Read times from data-time attributes (more reliable than parsing onclick).
		var times = items.map( function ( li ) {
			return +li.getAttribute( 'data-time' ) || 0;
		} );

		function mark() {
			var t = video.currentTime, idx = 0;
			for ( var i = 0; i < times.length; i++ ) {
				if ( times[ i ] <= t ) {
					idx = i;
				}
			}
			items.forEach( function ( li, i ) {
				li.classList.toggle( 'webinar-timeline-active', i === idx );
			} );
		}

		video.addEventListener( 'timeupdate', mark );
		video.addEventListener( 'seeked', mark );
		mark(); // Highlight the first chapter right away.
	}

	function initAll() {
		var widgets = document.querySelectorAll( '.webinar-widget' );
		[].forEach.call( widgets, initWidget );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();