/* SwiftWebDev Dashboard visitor counter: no cookies, no storage. Sends the page load, then a human confirmation. */
( function () {
	var cfg = window.swiftdashTracker;
	if ( ! cfg || ! cfg.endpoint || navigator.webdriver ) {
		return;
	}
	// Real input only: script-made events are not trusted, and wheel (not scroll) because pages and crawlers scroll by script.
	var evs = [ 'pointermove', 'mousemove', 'wheel', 'keydown', 'click', 'touchstart' ];
	var id = '', human = false, secs = 0, queue = [], waiting = [], base = { p: location.pathname }, timer, sw, a, i;
	try {
		a = new Uint8Array( 8 );
		crypto.getRandomValues( a );
		for ( i = 0; i < 8; i++ ) {
			id += ( '0' + a[ i ].toString( 16 ) ).slice( -2 );
		}
		base.i = id;
		var ref = document.referrer ? new URL( document.referrer ).hostname : '';
		if ( ref && ref !== location.hostname ) {
			base.r = ref;
		}
		var q = new URLSearchParams( location.search ), keys = { u: 'utm_source', m: 'utm_medium', c: 'utm_campaign' };
		for ( i in keys ) {
			if ( q.get( keys[ i ] ) ) {
				base[ i ] = q.get( keys[ i ] ).toLowerCase().slice( 0, 40 );
			}
		}
		base.t = document.title.slice( 0, 200 );
	} catch ( e ) {}
	if ( ! base.i ) {
		return;
	}

	function post( data ) {
		var body = JSON.stringify( data );
		try {
			if ( navigator.sendBeacon && navigator.sendBeacon( cfg.endpoint, body ) ) {
				return;
			}
		} catch ( e ) {}
		try {
			fetch( cfg.endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } } );
		} catch ( e ) {}
	}

	function confirm( ev ) {
		if ( human || ( ev && ev.isTrusted === false ) ) {
			return;
		}
		human = true;
		clearInterval( timer );
		for ( i = 0; i < evs.length; i++ ) {
			removeEventListener( evs[ i ], confirm, true );
		}
		var data = JSON.parse( JSON.stringify( base ) );
		data.h = 1;
		if ( queue.length ) {
			data.e = queue;
			queue = [];
		}
		post( data );
		sw.human = true;
		while ( waiting.length ) {
			try {
				waiting.shift()();
			} catch ( e ) {}
		}
		try {
			document.dispatchEvent( new CustomEvent( 'swiftdash:human' ) );
		} catch ( e ) {}
	}

	sw = window.swiftdash = window.swiftdash || {};
	sw.hitId = id;
	sw.human = false;
	sw.onHuman = function ( cb ) {
		if ( typeof cb !== 'function' ) {
			return;
		}
		if ( human ) {
			cb();
		} else {
			waiting.push( cb );
		}
	};
	sw.send = function ( events ) {
		if ( ! events || ! events.length ) {
			return;
		}
		if ( human ) {
			post( { i: id, e: events.slice( 0, 50 ) } );
		} else {
			queue = queue.concat( events ).slice( -50 );
		}
	};

	post( { i: id, p: base.p, r: base.r, u: base.u, m: base.m, c: base.c, t: base.t } );
	for ( i = 0; i < evs.length; i++ ) {
		addEventListener( evs[ i ], confirm, { capture: true, passive: true } );
	}
	// Page visible for 10 seconds in total also counts (reading without touching anything).
	timer = setInterval( function () {
		if ( document.visibilityState === 'visible' && ++secs >= 10 ) {
			confirm();
		}
	}, 1000 );
}() );
