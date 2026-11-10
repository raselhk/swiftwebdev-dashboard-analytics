/**
 * Toolbar items: live visitors (count + mini dashboard on hover, focus or tap) and the
 * navigation search (search.js). Data from GET swiftdash/v1/live, polled only while useful.
 */
( function () {
	'use strict';

	const { __, _n, sprintf } = wp.i18n;
	const cfg = window.swiftdashToolbar || {};
	const POLL = 15000; // Count refresh while the page is visible.
	const IDLE = 30 * 60000; // Stop polling after this long without user activity.
	const phone = window.matchMedia( '(max-width: 782px)' );

	let numberFormat;
	try {
		numberFormat = new Intl.NumberFormat( document.documentElement.lang || undefined );
	} catch ( e ) {
		numberFormat = new Intl.NumberFormat();
	}
	const fmt = ( n ) => numberFormat.format( n || 0 );

	function esc( text ) {
		const div = document.createElement( 'div' );
		div.textContent = String( text );
		return div.innerHTML;
	}

	function ready( fn ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	/* ---------- Live visitors ---------- */

	// Shown, text hidden, while the first load runs: same markup as the real panel, so no layout shift.
	const PLACEHOLDER = {
		online: 12,
		minutes: [ 2, 3, 2, 4, 3, 5, 3, 4, 6, 5, 4, 7, 6, 5, 8, 7, 6, 9, 8, 7, 10, 9, 11, 10, 12, 11, 13, 12, 15, 16 ],
		last_ago: 360,
		today: { visitors: 284, views: 611, visitors_change: { tone: 'flat', text: '+12%' }, views_change: { tone: 'flat', text: '+8%' } },
		ai: { visitors: 9, bots: 143 },
		pages: [ { title: 'Loading the top page', count: 86 }, { title: 'Loading the next page', count: 64 }, { title: 'Loading a page', count: 51 } ],
		sources: [ { label: 'Loading', count: 96 }, { label: 'Loading', count: 71 }, { label: 'Load', count: 14 } ],
	};

	const ARROW = '<svg class="sdtb-svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

	function agoText( seconds ) {
		const m = Math.round( seconds / 60 );
		if ( m < 60 ) {
			/* translators: %d: number of minutes. */
			return sprintf( _n( '%d minute ago', '%d minutes ago', Math.max( 1, m ), 'swiftwebdev-dashboard-analytics' ), Math.max( 1, m ) );
		}
		const h = Math.round( m / 60 );
		/* translators: %d: number of hours. */
		return sprintf( _n( '%d hour ago', '%d hours ago', h, 'swiftwebdev-dashboard-analytics' ), h );
	}

	function tile( long, short, value, sub, subShort, tone, hint ) {
		return '<div class="sdtb-tile">' +
			'<span class="sdtb-tile__label"><span class="sdtb-long">' + esc( long ) + '</span><span class="sdtb-short">' + esc( short ) + '</span></span>' +
			'<span class="sdtb-tile__n sdtb-t">' + esc( fmt( value ) ) + '</span>' +
			( null === sub ? '' : '<span class="sdtb-tile__sub sdtb-tone--' + esc( tone || 'flat' ) + '"' + ( sub ? ' title="' + esc( hint || sub ) + '"' : '' ) + '><span class="sdtb-long sdtb-t">' + ( sub ? esc( sub ) : '&nbsp;' ) + '</span><span class="sdtb-short sdtb-t">' + ( subShort ? esc( subShort ) : '&nbsp;' ) + '</span></span>' ) +
			'</div>';
	}

	function tiles( d, quiet ) {
		const vc = d.today.visitors_change || {};
		const pc = d.today.views_change || {};
		return '<div class="sdtb-tiles">' +
			tile(
				__( 'Visitors today', 'swiftwebdev-dashboard-analytics' ),
				__( 'Today', 'swiftwebdev-dashboard-analytics' ),
				d.today.visitors,
				quiet ? null : vc.text,
				vc.text,
				vc.tone,
				__( 'Compared with yesterday at the same time', 'swiftwebdev-dashboard-analytics' )
			) +
			tile( __( 'Page views', 'swiftwebdev-dashboard-analytics' ), __( 'Views', 'swiftwebdev-dashboard-analytics' ), d.today.views, quiet ? null : pc.text, pc.text, pc.tone, __( 'Compared with yesterday at the same time', 'swiftwebdev-dashboard-analytics' ) ) +
			tile(
				__( 'AI visitors', 'swiftwebdev-dashboard-analytics' ),
				__( 'AI', 'swiftwebdev-dashboard-analytics' ),
				d.ai.visitors,
				/* translators: %s: number of visits from AI bots today. */
				quiet ? null : sprintf( _n( '%s AI bot visit', '%s AI bot visits', d.ai.bots, 'swiftwebdev-dashboard-analytics' ), fmt( d.ai.bots ) ),
				_n( 'visitor', 'visitors', d.ai.visitors, 'swiftwebdev-dashboard-analytics' ),
				'flat'
			) +
			'</div>';
	}

	function bars( minutes ) {
		const max = Math.max( 1, ...minutes );
		const peak = Math.max( 0, ...minutes );
		/* translators: %d: highest number of visitors in one minute. */
		const label = __( 'Visitors per minute, last 30 minutes.', 'swiftwebdev-dashboard-analytics' ) + ' ' + sprintf( _n( 'Busiest minute: %d visitor.', 'Busiest minute: %d visitors.', peak, 'swiftwebdev-dashboard-analytics' ), peak );
		return '<div class="sdtb-bars" role="img" aria-label="' + esc( label ) + '">' +
			minutes.map( ( v, i ) => '<span class="sdtb-bar' + ( i >= 25 ? ' is-now' : '' ) + '" style="height:' + Math.max( 3, Math.round( v / max * 44 ) ) + 'px"></span>' ).join( '' ) +
			'</div>';
	}

	function brandTile( s ) {
		if ( s.direct ) {
			return '<span class="sdtb-chip__icon" aria-hidden="true">' + ARROW + '</span>';
		}
		if ( ! s.brand ) {
			return '<span class="sdtb-chip__icon" aria-hidden="true">' + esc( ( s.label || '?' ).charAt( 0 ).toUpperCase() ) + '</span>';
		}
		const mark = s.brand.path
			? '<svg class="sdtb-svg" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="' + esc( s.brand.path ) + '"/></svg>'
			: esc( s.brand.letter );
		return '<span class="sdtb-chip__icon is-brand" style="--sdtb-brand:' + esc( s.brand.color ) + '" aria-hidden="true">' + mark + '</span>';
	}

	function liveHtml( d ) {
		const pages = d.pages || [];
		const top = Math.max( 1, ...pages.map( ( p ) => p.count ) );
		const sources = d.sources || [];
		return '<div class="sdtb-sec sdtb-sec--head">' +
			'<div class="sdtb-row">' +
			'<span class="sdtb-pill"><span class="sdtb-dot" aria-hidden="true"></span><span class="sdtb-t">' + esc( __( 'Live now', 'swiftwebdev-dashboard-analytics' ) ) + '</span></span>' +
			'<span class="sdtb-ago sdtb-t" data-ago>' + esc( __( 'Updated just now', 'swiftwebdev-dashboard-analytics' ) ) + '</span>' +
			'</div>' +
			'<div class="sdtb-row sdtb-row--end">' +
			'<div class="sdtb-big"><span class="sdtb-big__n sdtb-t">' + esc( fmt( d.online ) ) + '</span>' +
			'<span class="sdtb-big__label sdtb-t">' + esc( _n( 'visitor in the last 5 minutes', 'visitors in the last 5 minutes', d.online, 'swiftwebdev-dashboard-analytics' ) ) + '</span></div>' +
			'<div class="sdtb-chart">' + bars( d.minutes || [] ) + '<span class="sdtb-chart__label sdtb-t">' + esc( __( 'Last 30 minutes', 'swiftwebdev-dashboard-analytics' ) ) + '</span></div>' +
			'</div>' +
			'</div>' +
			tiles( d, false ) +
			'<div class="sdtb-sec"><h2 class="sdtb-h">' + esc( __( 'Top pages today', 'swiftwebdev-dashboard-analytics' ) ) + '</h2><div class="sdtb-pages">' +
			( pages.length
				? pages.map( ( p ) => '<div class="sdtb-page"><div class="sdtb-page__row"><span class="sdtb-page__title sdtb-t"' + ( p.path ? ' title="' + esc( p.path ) + '"' : '' ) + '>' + esc( p.title ) + '</span>' +
					/* translators: %s: number of page views. */
					'<span class="sdtb-page__n sdtb-t">' + esc( sprintf( _n( '%s view', '%s views', p.count, 'swiftwebdev-dashboard-analytics' ), fmt( p.count ) ) ) + '</span></div>' +
					'<div class="sdtb-meter"><span style="width:' + Math.max( 2, Math.round( p.count / top * 100 ) ) + '%"></span></div></div>' ).join( '' )
				: '<p class="sdtb-empty">' + esc( __( 'No page views yet today.', 'swiftwebdev-dashboard-analytics' ) ) + '</p>' ) +
			'</div></div>' +
			'<div class="sdtb-sec sdtb-sec--sources"><h2 class="sdtb-h">' + esc( __( 'Where they came from', 'swiftwebdev-dashboard-analytics' ) ) + '</h2><div class="sdtb-sources">' +
			( sources.length
				? sources.map( ( s ) => '<span class="sdtb-chip">' + brandTile( s ) + '<span class="sdtb-t">' + esc( s.label ) + '</span> <span class="sdtb-chip__n sdtb-t">' + esc( fmt( s.count ) ) + '</span></span>' ).join( '' )
				: '<p class="sdtb-empty">' + esc( __( 'No visits yet today.', 'swiftwebdev-dashboard-analytics' ) ) + '</p>' ) +
			'</div></div>';
	}

	function quietHtml( d ) {
		const last = null === d.last_ago || undefined === d.last_ago
			? __( 'No visits in the last 2 days.', 'swiftwebdev-dashboard-analytics' )
			/* translators: %s: how long ago, e.g. 6 minutes ago. */
			: sprintf( __( 'The last visit was %s.', 'swiftwebdev-dashboard-analytics' ), agoText( d.last_ago ) );
		return '<div class="sdtb-sec sdtb-sec--head sdtb-sec--quiet">' +
			'<span class="sdtb-pill is-quiet"><span class="sdtb-dot is-quiet" aria-hidden="true"></span><span class="sdtb-t">' + esc( __( 'Quiet right now', 'swiftwebdev-dashboard-analytics' ) ) + '</span></span>' +
			'<p class="sdtb-quiet__title sdtb-t">' + esc( __( 'No one is on your site at the moment.', 'swiftwebdev-dashboard-analytics' ) ) + '</p>' +
			'<p class="sdtb-quiet__text"><span class="sdtb-t">' + esc( last + ' ' + __( 'The count updates by itself when someone arrives.', 'swiftwebdev-dashboard-analytics' ) ) + '</span></p>' +
			'</div>' +
			tiles( d, true );
	}

	function live() {
		const li = document.getElementById( 'wp-admin-bar-swiftdash-live' );
		if ( ! li ) {
			return;
		}
		const btn = li.querySelector( '.sdtb-live' );
		const panel = li.querySelector( '.sdtb-panel' );
		const body = panel.querySelector( '.sdtb-panel__body' );
		const backdrop = li.querySelector( '.sdtb-backdrop' );
		const countEl = btn.querySelector( '.sdtb-live__n' );
		const dot = btn.querySelector( '.sdtb-dot' );

		let online = parseInt( countEl.dataset.n, 10 ) || 0;
		let data = null; // Last full payload.
		let dataAt = 0; // Client time the payload was computed on the server.
		let countAt = Date.now();
		let open = false;
		let pinned = false;
		let quietFocus = false;
		let lastActive = Date.now();
		let idle = false;
		let inflight = null;
		let openTimer = 0;
		let closeTimer = 0;
		let pollTimer = 0;
		let tickTimer = 0;

		function setCount( n ) {
			online = n;
			countEl.textContent = fmt( n );
			dot.classList.toggle( 'is-quiet', ! n );
		}

		function tick() {
			const el = body.querySelector( '[data-ago]' );
			if ( ! el || ! data ) {
				return;
			}
			const s = Math.max( 0, Math.round( ( Date.now() - dataAt ) / 1000 ) );
			el.textContent = s < 5
				? __( 'Updated just now', 'swiftwebdev-dashboard-analytics' )
				/* translators: %d: number of seconds. */
				: ( s < 60 ? sprintf( _n( 'Updated %d second ago', 'Updated %d seconds ago', s, 'swiftwebdev-dashboard-analytics' ), s )
				/* translators: %d: number of minutes. */
					: sprintf( _n( 'Updated %d minute ago', 'Updated %d minutes ago', Math.floor( s / 60 ), 'swiftwebdev-dashboard-analytics' ), Math.floor( s / 60 ) ) );
		}

		function render() {
			const d = data || Object.assign( {}, PLACEHOLDER, { online } );
			const quiet = ! d.online;
			body.innerHTML = quiet ? quietHtml( d ) : liveHtml( d );
			body.classList.toggle( 'is-loading', ! data );
			body.setAttribute( 'aria-busy', data ? 'false' : 'true' );
			panel.classList.toggle( 'is-quiet', quiet );
			tick();
			if ( data ) {
				/**
				 * Add-ons draw extra rows from their swiftdash_live_payload keys here.
				 * detail: { data, panel, body }.
				 */
				document.dispatchEvent( new CustomEvent( 'swiftdash:live', { detail: { data, panel, body } } ) );
			}
		}

		// One request at a time: the full panel data while open, the count otherwise.
		function refresh() {
			if ( inflight ) {
				return inflight;
			}
			const full = open;
			inflight = wp.apiFetch( { path: '/swiftdash/v1/live' + ( full ? '?full=1' : '' ) } )
				.then( ( res ) => {
					countAt = Date.now();
					setCount( parseInt( res.online, 10 ) || 0 );
					if ( full ) {
						data = res;
						dataAt = Date.now() - ( res.age || 0 ) * 1000;
					}
					if ( open ) {
						render();
					}
				} )
				.catch( () => {} )
				.then( () => {
					inflight = null;
					// Opened while a count-only request ran: fetch the panel data now.
					if ( open && ! data ) {
						refresh();
					}
				} );
			return inflight;
		}

		function schedule() {
			clearTimeout( pollTimer );
			if ( document.hidden || idle ) {
				return;
			}
			pollTimer = setTimeout( () => {
				if ( Date.now() - lastActive > IDLE ) {
					idle = true;
					return;
				}
				refresh().then( schedule );
			}, POLL );
		}

		// Keep the panel inside the window (desktop).
		function place() {
			panel.style.removeProperty( '--sdtb-shift' );
			if ( phone.matches ) {
				return;
			}
			const r = panel.getBoundingClientRect();
			const over = r.right - ( document.documentElement.clientWidth - 8 );
			const under = 8 - r.left;
			const shift = over > 0 ? -over : ( under > 0 ? under : 0 );
			if ( shift ) {
				panel.style.setProperty( '--sdtb-shift', shift + 'px' );
			}
		}

		function show( how ) {
			clearTimeout( openTimer );
			clearTimeout( closeTimer );
			if ( open ) {
				pinned = pinned || 'click' === how;
				return;
			}
			open = true;
			pinned = 'click' === how;
			btn.setAttribute( 'aria-expanded', 'true' );
			li.classList.add( 'hover' );
			panel.hidden = false;
			backdrop.hidden = ! phone.matches;
			render();
			place();
			tickTimer = setInterval( tick, 1000 );
			if ( ! data || Date.now() - dataAt > POLL ) {
				refresh().then( schedule );
			}
			if ( phone.matches && 'click' === how ) {
				panel.querySelector( '.sdtb-panel__close' ).focus();
			}
		}

		function hide( focusButton ) {
			clearTimeout( openTimer );
			clearTimeout( closeTimer );
			if ( ! open ) {
				return;
			}
			open = pinned = false;
			clearInterval( tickTimer );
			btn.setAttribute( 'aria-expanded', 'false' );
			li.classList.remove( 'hover' );
			panel.hidden = true;
			backdrop.hidden = true;
			if ( focusButton ) {
				quietFocus = true;
				btn.focus();
				quietFocus = false;
			}
		}

		li.addEventListener( 'pointerenter', ( e ) => {
			if ( 'mouse' !== e.pointerType || phone.matches ) {
				return;
			}
			clearTimeout( closeTimer );
			if ( ! open ) {
				openTimer = setTimeout( () => show( 'hover' ), 120 );
			}
		} );
		li.addEventListener( 'pointerleave', ( e ) => {
			if ( 'mouse' !== e.pointerType || phone.matches ) {
				return;
			}
			clearTimeout( openTimer );
			if ( open && ! pinned ) {
				closeTimer = setTimeout( () => hide( false ), 300 );
			}
		} );
		btn.addEventListener( 'focus', () => {
			if ( ! quietFocus && btn.matches( ':focus-visible' ) && ! phone.matches ) {
				show( 'focus' );
			}
		} );
		btn.addEventListener( 'click', () => {
			if ( open && ( pinned || phone.matches ) ) {
				hide( false );
			} else {
				show( 'click' );
			}
		} );
		panel.querySelector( '.sdtb-panel__close' ).addEventListener( 'click', () => hide( true ) );
		li.addEventListener( 'focusout', ( e ) => {
			if ( e.relatedTarget && ! li.contains( e.relatedTarget ) ) {
				hide( false );
			}
		} );
		document.addEventListener( 'pointerdown', ( e ) => {
			if ( open && ( ! li.contains( e.target ) || backdrop === e.target ) ) {
				hide( false );
			}
		} );
		document.addEventListener( 'keydown', ( e ) => {
			if ( open && 'Escape' === e.key ) {
				hide( li.contains( document.activeElement ) );
			}
		} );
		phone.addEventListener( 'change', () => hide( false ) );

		// Polling: every 15 s while the page is visible, paused when hidden, stopped after 30 idle minutes.
		[ 'pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart', 'wheel' ].forEach( ( type ) => {
			window.addEventListener( type, () => {
				lastActive = Date.now();
				if ( idle ) {
					idle = false;
					refresh().then( schedule );
				}
			}, { passive: true, capture: true } );
		} );
		document.addEventListener( 'visibilitychange', () => {
			if ( document.hidden ) {
				clearTimeout( pollTimer );
			} else if ( Date.now() - countAt >= POLL ) {
				refresh().then( schedule );
			} else {
				schedule();
			}
		} );
		schedule();
	}

	/* ---------- Search ---------- */

	function search() {
		const li = document.getElementById( 'wp-admin-bar-swiftdash-search' );
		if ( ! li || ! window.swiftdashSearch ) {
			return;
		}
		const btn = li.querySelector( '.sdtb-search__btn' );
		const box = li.querySelector( '.sdtb-search' );
		const input = box.querySelector( 'input' );
		let prefs = null;

		function savePrefs( changes, extra ) {
			prefs = Object.assign( {}, prefs, changes );
			// On dashboard screens, save through the dashboard so both keep the same preferences.
			if ( window.swiftdash && window.swiftdash.savePrefs ) {
				return window.swiftdash.savePrefs( changes, extra );
			}
			return wp.apiFetch( Object.assign( { path: '/wp/v2/users/me', method: 'POST', data: { meta: { swiftdash_prefs: prefs } } }, extra ) );
		}

		const api = window.swiftdashSearch(
			{
				root: li,
				input,
				panel: box.querySelector( '.sdtb-search__panel' ),
				list: box.querySelector( '[role="listbox"]' ),
				status: box.querySelector( '[data-sd-search-status]' ),
			},
			{
				commands: () => wp.apiFetch( { path: '/swiftdash/v1/search/commands' } ).then( ( res ) => {
					prefs = res.prefs || {};
					return res.commands || [];
				} ),
				adminUrl: cfg.adminUrl,
				iconUrl: cfg.iconUrl,
				getRecent: () => ( prefs && prefs.recent ) || [],
				saveRecent: ( recent, extra ) => savePrefs( { recent }, extra ),
				onEscape: () => collapse( true ),
			}
		);

		function collapse( focusButton ) {
			if ( box.hidden ) {
				return;
			}
			api.hide();
			input.value = '';
			box.hidden = true;
			btn.hidden = false;
			btn.setAttribute( 'aria-expanded', 'false' );
			li.classList.remove( 'is-open' );
			if ( focusButton ) {
				btn.focus();
			}
		}

		btn.addEventListener( 'click', () => {
			btn.hidden = true;
			box.hidden = false;
			btn.setAttribute( 'aria-expanded', 'true' );
			li.classList.add( 'is-open' );
			input.focus();
		} );
		li.addEventListener( 'focusout', ( e ) => {
			if ( ! li.contains( e.relatedTarget ) ) {
				collapse( false );
			}
		} );
	}

	ready( () => {
		if ( cfg.live ) {
			live();
		}
		if ( cfg.search ) {
			search();
		}
	} );
}() );
