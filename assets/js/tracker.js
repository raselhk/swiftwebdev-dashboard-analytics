/* SwiftWebDev Dashboard visitor counter: no cookies, no storage, one request per page load. */
( function () {
	var cfg = window.swiftdashTracker;
	if ( ! cfg || ! cfg.endpoint || navigator.webdriver ) {
		return;
	}
	var body = JSON.stringify( { p: location.pathname } );
	try {
		if ( navigator.sendBeacon && navigator.sendBeacon( cfg.endpoint, body ) ) {
			return;
		}
	} catch ( e ) {}
	try {
		fetch( cfg.endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } } );
	} catch ( e ) {}
}() );
