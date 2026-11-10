/* SwiftWebDev Dashboard visitor counter: no cookies, no storage, one request per page load. */
( function () {
	var cfg = window.swiftdashTracker;
	if ( ! cfg || ! cfg.endpoint || navigator.webdriver ) {
		return;
	}
	var data = { p: location.pathname };
	try {
		var ref = document.referrer ? new URL( document.referrer ).hostname : '';
		if ( ref && ref !== location.hostname ) {
			data.r = ref;
		}
		var utm = new URLSearchParams( location.search ).get( 'utm_source' );
		if ( utm ) {
			data.u = utm.toLowerCase().slice( 0, 40 );
		}
	} catch ( e ) {}
	var body = JSON.stringify( data );
	try {
		if ( navigator.sendBeacon && navigator.sendBeacon( cfg.endpoint, body ) ) {
			return;
		}
	} catch ( e ) {}
	try {
		fetch( cfg.endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } } );
	} catch ( e ) {}
}() );
