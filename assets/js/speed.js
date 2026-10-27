/**
 * Speed & Optimize screen and dashboard speed widgets. Shows real Google PageSpeed results only.
 * Free: homepage. Other pages of this site: Swift Dash PRO. Other sites: never.
 */
( function () {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const sd = window.swiftdash;
	const data = window.swiftdashSpeed || {};
	const results = Object.assign( { mobile: null, desktop: null }, data.results );
	let custom = data.custom || null;
	const app = document.querySelector( '.sd-app' );
	if ( ! app || ! sd ) {
		return;
	}

	const DEVICES = {
		mobile: __( 'Mobile', 'swift-dashboard-analytics' ),
		desktop: __( 'Desktop', 'swift-dashboard-analytics' ),
	};
	const BANDS = {
		good: { label: __( 'Good', 'swift-dashboard-analytics' ), tone: 'success', icon: 'check-circle' },
		needs_work: { label: __( 'Needs work', 'swift-dashboard-analytics' ), tone: 'warning', icon: 'warning' },
		poor: { label: __( 'Poor', 'swift-dashboard-analytics' ), tone: 'danger', icon: 'warning-circle' },
	};
	const SEVERITY = {
		high: { label: __( 'High impact', 'swift-dashboard-analytics' ), tone: 'danger' },
		medium: { label: __( 'Medium impact', 'swift-dashboard-analytics' ), tone: 'warning' },
		low: { label: __( 'Low impact', 'swift-dashboard-analytics' ), tone: 'neutral' },
	};

	/* ---------- Helpers ---------- */

	function esc( text ) {
		const div = document.createElement( 'div' );
		div.textContent = null === text || undefined === text ? '' : String( text );
		return div.innerHTML;
	}

	function band( score ) {
		return score >= 90 ? 'good' : ( score >= 50 ? 'needs_work' : 'poor' );
	}

	function status( key ) {
		const b = BANDS[ key ];
		return b ? '<span class="sd-status sd-status--' + b.tone + '">' + sd.icon( b.icon ) + esc( b.label ) + '</span>' : '';
	}

	function seconds( ms ) {
		return null === ms || undefined === ms ? null : sprintf(
			/* translators: %s: duration in seconds. */
			__( '%s s', 'swift-dashboard-analytics' ),
			( ms / 1000 ).toLocaleString( undefined, { maximumFractionDigits: 1, minimumFractionDigits: 1 } )
		);
	}

	function millis( ms ) {
		return null === ms || undefined === ms ? null : sprintf(
			/* translators: %s: duration in milliseconds. */
			__( '%s ms', 'swift-dashboard-analytics' ),
			Math.round( ms ).toLocaleString()
		);
	}

	function ago( unix ) {
		const diff = Math.round( unix - Date.now() / 1000 );
		const rtf = new Intl.RelativeTimeFormat( document.documentElement.lang || undefined, { numeric: 'auto' } );
		const steps = [ [ 60, 'second' ], [ 3600, 'minute' ], [ 86400, 'hour' ], [ 604800, 'day' ], [ Infinity, 'week' ] ];
		let unit = 1;
		for ( const [ limit, name ] of steps ) {
			if ( Math.abs( diff ) < limit ) {
				return rtf.format( Math.round( diff / unit ), name );
			}
			unit = limit;
		}
		return '';
	}

	function lastTested( set ) {
		const times = [ set.mobile, set.desktop ].filter( Boolean ).map( ( r ) => r.tested_at );
		return times.length ? sprintf(
			/* translators: %s: relative time, e.g. "5 minutes ago". */
			__( 'Last tested %s', 'swift-dashboard-analytics' ),
			ago( Math.max( ...times ) )
		) : __( 'Not tested yet', 'swift-dashboard-analytics' );
	}

	/** Ring gauge: SVG arc with the score centred, label and status text below. */
	function gauge( score, label, size ) {
		const tested = null !== score && undefined !== score;
		const key = tested ? band( score ) : null;
		const tone = tested ? BANDS[ key ].tone : 'empty';
		const aria = tested
			? sprintf( /* translators: 1: score name, 2: score 0-100, 3: rating. */ __( '%1$s: %2$d out of 100, %3$s', 'swift-dashboard-analytics' ), label, score, BANDS[ key ].label )
			: sprintf( /* translators: %s: score name. */ __( '%s: not tested', 'swift-dashboard-analytics' ), label );
		return '<div class="sd-gauge' + ( size ? ' sd-gauge--' + size : '' ) + '">' +
			'<div class="sd-gauge__ring is-' + tone + '" role="img" aria-label="' + esc( aria ) + '">' +
			'<svg viewBox="0 0 100 100" aria-hidden="true" focusable="false"><circle class="sd-gauge__track" cx="50" cy="50" r="44" pathLength="100"/>' +
			( tested ? '<circle class="sd-gauge__arc" cx="50" cy="50" r="44" pathLength="100" stroke-dasharray="' + Number( score ) + ' 100"/>' : '' ) + '</svg>' +
			'<span class="sd-gauge__value" aria-hidden="true">' + ( tested ? Number( score ) : '-' ) + '</span></div>' +
			'<p class="sd-gauge__label">' + esc( label ) + '</p>' +
			( tested ? '<p class="sd-gauge__status is-' + tone + '">' + esc( BANDS[ key ].label ) + '</p>' : '<p class="sd-gauge__status">' + esc( __( 'Not tested', 'swift-dashboard-analytics' ) ) + '</p>' ) +
			'</div>';
	}

	function empty( iconName, title, text, extra ) {
		return '<div class="sd-empty">' +
			'<span class="sd-empty__icon">' + sd.icon( iconName ) + '</span>' +
			'<p class="sd-empty__title">' + esc( title ) + '</p>' +
			'<p class="sd-empty__text">' + esc( text ) + '</p>' + ( extra || '' ) + '</div>';
	}

	function notTested( device ) {
		return empty(
			'gauge',
			__( 'No performance test has been run', 'swift-dashboard-analytics' ),
			sprintf(
				/* translators: %s: device name, Mobile or Desktop. */
				__( 'Select Run test to measure your %s scores with Google PageSpeed Insights.', 'swift-dashboard-analytics' ),
				DEVICES[ device ].toLowerCase()
			)
		);
	}

	function fixItem( fix, compact ) {
		const sev = SEVERITY[ fix.severity ] || SEVERITY.low;
		let html = '<li class="sd-fix">' +
			'<div class="sd-fix__head"><span class="sd-status sd-status--' + sev.tone + '">' + esc( sev.label ) + '</span>' +
			'<h3 class="sd-fix__title">' + esc( fix.title ) + '</h3></div>';
		if ( compact ) {
			return html + ( fix.evidence ? '<p class="sd-fix__evidence">' + esc( fix.evidence ) + '</p>' : '' ) + '</li>';
		}
		if ( fix.explanation ) {
			html += '<p class="sd-fix__text">' + esc( fix.explanation ) + '</p>';
		}
		const facts = [];
		if ( fix.evidence ) {
			facts.push( '<span><strong>' + esc( __( 'Measured:', 'swift-dashboard-analytics' ) ) + '</strong> ' + esc( fix.evidence ) + '</span>' );
		}
		if ( fix.savings_ms ) {
			facts.push( '<span><strong>' + esc( __( 'Estimated savings:', 'swift-dashboard-analytics' ) ) + '</strong> ' + esc( fix.savings_ms >= 1000 ? seconds( fix.savings_ms ) : millis( fix.savings_ms ) ) + '</span>' );
		}
		if ( facts.length ) {
			html += '<p class="sd-fix__evidence">' + facts.join( '' ) + '</p>';
		}
		return html + '<div class="sd-fix__advice">' + sd.icon( 'wrench' ) + '<p><strong>' + esc( __( 'What to do', 'swift-dashboard-analytics' ) ) + '</strong> ' + esc( fix.advice ) + '</p></div></li>';
	}

	/* ---------- Page boundary (the server checks again) ---------- */

	const hostOf = ( u ) => u.hostname.toLowerCase().replace( /^www\./, '' );
	const trimSlash = ( p ) => p.replace( /\/+$/, '' );
	const home = new URL( data.home || window.location.origin + '/' );
	const isHome = ( u ) => hostOf( u ) === hostOf( home ) && ! u.search && trimSlash( u.pathname ) === trimSlash( home.pathname );
	const shortUrl = ( url ) => {
		try {
			const u = new URL( url );
			return trimSlash( u.pathname ) + u.search || '/';
		} catch ( e ) {
			return url;
		}
	};

	/** Inline notice under the page field. tone: error | pro | info. */
	function notice( box, tone, text, withUpgrade ) {
		box.innerHTML = '<p class="sd-speed-notice sd-speed-notice--' + tone + '"' + ( 'error' === tone ? ' role="alert"' : '' ) + '>' +
			sd.icon( 'pro' === tone ? 'crown' : 'warning-circle' ) + '<span>' + esc( text ) +
			( withUpgrade && data.upgradeUrl ? ' <a href="' + esc( data.upgradeUrl ) + '" target="_blank" rel="noopener noreferrer">' + esc( __( 'Upgrade', 'swift-dashboard-analytics' ) ) +
				'<span class="screen-reader-text"> ' + esc( __( '(opens in a new tab)', 'swift-dashboard-analytics' ) ) + '</span></a>' : '' ) +
			'</span></p>';
	}

	function pageForm( id, compact ) {
		return '<form class="sd-speed-page' + ( compact ? ' sd-speed-page--compact' : '' ) + '" data-sd-speed-page novalidate>' +
			'<label class="sd-speed-page__label" for="' + id + '">' + esc( __( 'Test another page', 'swift-dashboard-analytics' ) ) +
			( data.isPro ? '' : ' <span class="sd-badge sd-badge--pro">' + esc( __( 'Pro', 'swift-dashboard-analytics' ) ) + '</span>' ) + '</label>' +
			'<div class="sd-speed-page__row">' +
			'<input class="sd-input" type="url" id="' + id + '" inputmode="url" autocomplete="url" spellcheck="false" value="' + esc( home.href ) + '" aria-describedby="' + id + '-notice">' +
			'<button type="submit" class="sd-btn sd-btn--secondary">' + esc( __( 'Test', 'swift-dashboard-analytics' ) ) + '</button></div>' +
			'<div id="' + id + '-notice" data-sd-speed-page-notice></div></form>';
	}

	/**
	 * Validate the page field. Returns the URL to test, 'home', or null after showing a notice.
	 *
	 * @param {HTMLFormElement} form Page form.
	 */
	function checkPage( form ) {
		const input = form.querySelector( 'input' );
		const box = form.querySelector( '[data-sd-speed-page-notice]' );
		input.removeAttribute( 'aria-invalid' );
		box.innerHTML = '';
		let u = null;
		try {
			u = new URL( input.value.trim() );
		} catch ( e ) {}
		if ( ! u || ! [ 'http:', 'https:' ].includes( u.protocol ) ) {
			input.setAttribute( 'aria-invalid', 'true' );
			notice( box, 'error', __( 'Enter a full page address, for example https://example.com/about/.', 'swift-dashboard-analytics' ) );
			return null;
		}
		if ( hostOf( u ) !== hostOf( home ) ) {
			input.setAttribute( 'aria-invalid', 'true' );
			notice( box, 'error', __( 'Only pages of this website can be tested.', 'swift-dashboard-analytics' ) );
			return null;
		}
		if ( isHome( u ) ) {
			return 'home';
		}
		if ( ! data.isPro ) {
			notice( box, 'pro', __( 'Testing other pages needs Swift Dash PRO.', 'swift-dashboard-analytics' ), true );
			return null;
		}
		return u.href;
	}

	/**
	 * Test mobile then desktop. Stores each result as it arrives.
	 *
	 * @param {string}   url        Address, '' for the homepage.
	 * @param {Function} onProgress Called with ( strategy ) before each test and ( null ) after each result.
	 * @return {Promise<string>} Resolves 'home' or 'custom'; rejects with { err, strategy }.
	 */
	async function runTests( url, onProgress ) {
		let scope = 'home';
		for ( const strategy of [ 'mobile', 'desktop' ] ) {
			onProgress( strategy );
			let res;
			try {
				res = await apiFetch( { path: '/swiftdash/v1/speed/run', method: 'POST', data: { strategy, url } } );
			} catch ( err ) {
				throw { err, strategy }; // eslint-disable-line no-throw-literal
			}
			scope = res.scope;
			if ( 'custom' === scope ) {
				if ( ! custom || custom.url !== res.url ) {
					custom = { url: res.url, mobile: null, desktop: null };
				}
				custom[ strategy ] = res;
			} else {
				results[ strategy ] = res;
			}
			onProgress( null, scope );
		}
		return scope;
	}

	const errorText = ( err ) => ( err && err.message ) || __( 'Google could not finish the test. Please try again in a minute.', 'swift-dashboard-analytics' );

	/* ---------- Speed & Optimize screen ---------- */

	const screen = app.querySelector( '[data-sd-speed-screen]' );
	let device = 'mobile';
	let view = 'home';
	const shown = () => ( 'custom' === view && custom ? custom : results );
	const reportUrl = () => 'https://pagespeed.web.dev/analysis?url=' + encodeURIComponent( 'custom' === view && custom ? custom.url : home.href );

	function renderScores( strategy ) {
		const el = screen.querySelector( '[data-sd-speed-scores="' + strategy + '"]' );
		const r = shown()[ strategy ];
		if ( ! r ) {
			el.innerHTML = notTested( strategy );
			return;
		}
		el.innerHTML = '<div class="sd-gauges">' +
			gauge( r.scores.performance, __( 'Performance', 'swift-dashboard-analytics' ) ) +
			gauge( r.scores.accessibility, __( 'Accessibility', 'swift-dashboard-analytics' ) ) +
			gauge( r.scores.best_practices, __( 'Best practices', 'swift-dashboard-analytics' ) ) +
			gauge( r.scores.seo, __( 'SEO', 'swift-dashboard-analytics' ) ) +
			'</div><p class="sd-speed-method">' + sd.icon( 'info' ) + esc( __( 'Lab test by Google Lighthouse', 'swift-dashboard-analytics' ) ) + ' <span>' +
			esc( new Date( r.tested_at * 1000 ).toLocaleString() ) + '</span></p>';
	}

	function vitalCell( value, statusKey ) {
		return null === value || undefined === value
			? '<td>' + esc( __( 'Not measured', 'swift-dashboard-analytics' ) ) + '</td>'
			: '<td><span class="sd-vital">' + esc( value ) + status( statusKey ) + '</span></td>';
	}

	function renderVitals() {
		const el = screen.querySelector( '[data-sd-speed-vitals]' );
		const set = shown();
		if ( ! set.mobile && ! set.desktop ) {
			el.innerHTML = empty( 'chart-line-up', __( 'No measurements yet', 'swift-dashboard-analytics' ), __( 'Core Web Vitals appear here after your first speed test.', 'swift-dashboard-analytics' ) );
			return;
		}
		const head = '<thead><tr><th scope="col">' + esc( __( 'Metric', 'swift-dashboard-analytics' ) ) + '</th><th scope="col">' + esc( DEVICES.mobile ) + '</th><th scope="col">' + esc( DEVICES.desktop ) + '</th></tr></thead>';
		const labRows = [
			[ 'lcp_ms', __( 'Largest Contentful Paint', 'swift-dashboard-analytics' ), seconds ],
			[ 'cls', __( 'Cumulative Layout Shift', 'swift-dashboard-analytics' ), ( v ) => ( null === v || undefined === v ? null : v.toFixed( 3 ) ) ],
			[ 'tbt_ms', __( 'Total Blocking Time', 'swift-dashboard-analytics' ), millis ],
			[ 'fcp_ms', __( 'First Contentful Paint', 'swift-dashboard-analytics' ), seconds ],
			[ 'si_ms', __( 'Speed Index', 'swift-dashboard-analytics' ), seconds ],
		];
		let lab = '';
		labRows.forEach( ( [ key, label, fmt ] ) => {
			lab += '<tr><th scope="row">' + esc( label ) + '</th>';
			[ 'mobile', 'desktop' ].forEach( ( s ) => {
				const r = set[ s ];
				lab += r ? vitalCell( fmt( r.lab[ key ] ), r.lab_status && r.lab_status[ key ] ) : '<td>' + esc( __( 'Not tested', 'swift-dashboard-analytics' ) ) + '</td>';
			} );
			lab += '</tr>';
		} );

		const fieldRows = [
			[ 'lcp', 'lcp_ms', __( 'Largest Contentful Paint', 'swift-dashboard-analytics' ), seconds ],
			[ 'inp', 'inp_ms', __( 'Interaction to Next Paint', 'swift-dashboard-analytics' ), millis ],
			[ 'cls', 'cls', __( 'Cumulative Layout Shift', 'swift-dashboard-analytics' ), ( v ) => ( null === v || undefined === v ? null : v.toFixed( 2 ) ) ],
		];
		const hasField = [ 'mobile', 'desktop' ].some( ( s ) => set[ s ] && set[ s ].field );
		let field;
		if ( hasField ) {
			let rows = '';
			fieldRows.forEach( ( [ cat, key, label, fmt ] ) => {
				rows += '<tr><th scope="row">' + esc( label ) + '</th>';
				[ 'mobile', 'desktop' ].forEach( ( s ) => {
					const f = set[ s ] && set[ s ].field;
					rows += f ? vitalCell( fmt( f[ key ] ), f.categories && f.categories[ cat ] ) : '<td>' + esc( __( 'Not enough data', 'swift-dashboard-analytics' ) ) + '</td>';
				} );
				rows += '</tr>';
			} );
			const origin = [ 'mobile', 'desktop' ].some( ( s ) => set[ s ] && set[ s ].field && set[ s ].field.origin );
			field = '<div class="sd-table-wrap"><table class="sd-table">' + head + '<tbody>' + rows + '</tbody></table></div>' +
				'<p class="sd-speed-note">' + esc( origin
				? __( 'Visitors to your whole site over the last 28 days. This page alone does not have enough visits yet.', 'swift-dashboard-analytics' )
				: __( 'Visitors to this page over the last 28 days, 75th percentile.', 'swift-dashboard-analytics' ) ) + '</p>';
		} else {
			field = '<div class="sd-speed-nodata">' + sd.icon( 'users' ) + '<div><p class="sd-empty__title">' + esc( __( 'Not enough real-user data yet', 'swift-dashboard-analytics' ) ) + '</p><p class="sd-speed-note">' +
				esc( __( 'Google only publishes real-user results for sites with enough Chrome visitors. Until then, use the lab results.', 'swift-dashboard-analytics' ) ) + '</p></div></div>';
		}

		el.innerHTML = '<div class="sd-vitals">' +
			'<div class="sd-vitals__block"><h3 class="sd-vitals__title">' + esc( __( 'Lab test', 'swift-dashboard-analytics' ) ) + '</h3><p class="sd-speed-note">' + esc( __( 'One simulated visit by Google Lighthouse.', 'swift-dashboard-analytics' ) ) + '</p>' +
			'<div class="sd-table-wrap"><table class="sd-table">' + head + '<tbody>' + lab + '</tbody></table></div></div>' +
			'<div class="sd-vitals__block"><h3 class="sd-vitals__title">' + esc( __( 'Real-user data from the Chrome UX Report', 'swift-dashboard-analytics' ) ) + '</h3>' + field + '</div></div>';
	}

	function renderFixes() {
		const el = screen.querySelector( '[data-sd-speed-fixes]' );
		screen.querySelectorAll( '[data-sd-speed-device]' ).forEach( ( btn ) => btn.setAttribute( 'aria-pressed', String( btn.dataset.sdSpeedDevice === device ) ) );
		const r = shown()[ device ];
		if ( ! r ) {
			el.innerHTML = notTested( device );
			return;
		}
		const report = '<a class="sd-btn sd-btn--tertiary sd-btn--sm" href="' + esc( reportUrl() ) + '" target="_blank" rel="noopener noreferrer">' +
			esc( __( 'Open the full Google report', 'swift-dashboard-analytics' ) ) + sd.icon( 'arrow-square-out' ) + '<span class="screen-reader-text"> ' + esc( __( '(opens in a new tab)', 'swift-dashboard-analytics' ) ) + '</span></a>';
		if ( ! r.fixes.length ) {
			el.innerHTML = empty( 'check-circle', __( 'Nothing important to fix', 'swift-dashboard-analytics' ), __( 'Google found no failing speed checks in the last test.', 'swift-dashboard-analytics' ), report );
			return;
		}
		el.innerHTML = '<p class="sd-speed-note">' + esc( sprintf(
			/* translators: 1: number of items, 2: device name. */
			__( '%1$d items found in the last %2$s test, biggest impact first. Swift Dash only reports; it never changes your site.', 'swift-dashboard-analytics' ),
			r.fixes.length,
			DEVICES[ device ].toLowerCase()
		) ) + '</p><ol class="sd-fixes">' + r.fixes.map( ( f ) => fixItem( f ) ).join( '' ) + '</ol>' + '<div class="sd-speed-foot">' + report + '</div>';
	}

	function renderView() {
		const el = screen.querySelector( '[data-sd-speed-view]' );
		if ( ! custom ) {
			el.innerHTML = '';
			return;
		}
		const btn = ( key, label ) => '<button type="button" class="sd-btn sd-btn--sm sd-btn--secondary" data-sd-speed-show="' + key + '" aria-pressed="' + String( view === key ) + '">' + esc( label ) + '</button>';
		el.innerHTML = '<div class="sd-speed-view"><span class="sd-speed-view__label">' + esc( __( 'Showing results for', 'swift-dashboard-analytics' ) ) + '</span>' +
			'<div class="sd-speed-toggle" role="group" aria-label="' + esc( __( 'Tested page', 'swift-dashboard-analytics' ) ) + '">' +
			btn( 'home', __( 'Homepage', 'swift-dashboard-analytics' ) ) + btn( 'custom', shortUrl( custom.url ) ) + '</div></div>';
	}

	function renderScreen() {
		renderView();
		renderScores( 'mobile' );
		renderScores( 'desktop' );
		renderVitals();
		renderFixes();
		app.querySelector( '[data-sd-speed-last]' ).textContent = lastTested( results );
	}

	if ( screen ) {
		const runBtn = app.querySelector( '[data-sd-speed-run]' );
		const progress = app.querySelector( '[data-sd-speed-progress]' );
		const alertBox = app.querySelector( '[data-sd-speed-alert]' );
		const slot = screen.querySelector( '[data-sd-speed-page-slot]' );
		slot.innerHTML = pageForm( 'sd-speed-page-url' );
		const form = slot.querySelector( 'form' );
		const formBtn = form.querySelector( 'button' );
		let lastUrl = '';

		screen.querySelectorAll( '[data-sd-speed-device]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				device = btn.dataset.sdSpeedDevice;
				renderFixes();
			} );
		} );
		screen.querySelector( '[data-sd-speed-view]' ).addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( '[data-sd-speed-show]' );
			if ( btn ) {
				view = btn.dataset.sdSpeedShow;
				renderScreen();
			}
		} );

		function showError( err, strategy ) {
			const retryable = ! err || ! [ 'not_public', 'outside_site', 'pro_required' ].includes( err.code );
			const ref = err && err.data && err.data.reference;
			alertBox.innerHTML = '<div class="sd-card sd-speed-alert"><div class="sd-error" role="alert">' +
				'<span class="sd-error__icon">' + sd.icon( 'warning-circle' ) + '</span>' +
				'<p class="sd-error__title">' + esc( sprintf( /* translators: %s: device name. */ __( 'The %s test did not finish', 'swift-dashboard-analytics' ), DEVICES[ strategy ].toLowerCase() ) ) + '</p>' +
				'<p class="sd-error__text">' + esc( errorText( err ) ) + '</p>' +
				( retryable ? '<button type="button" class="sd-btn sd-btn--secondary sd-btn--sm" data-sd-speed-retry>' + esc( __( 'Try again', 'swift-dashboard-analytics' ) ) + '</button>' : '' ) +
				( ref ? '<p class="sd-error__ref">' + esc( sprintf( /* translators: %s: error reference code. */ __( 'Reference: %s', 'swift-dashboard-analytics' ), ref ) ) + '</p>' : '' ) +
				'</div></div>';
		}

		async function run( url ) {
			lastUrl = url;
			const label = url ? shortUrl( url ) : __( 'homepage', 'swift-dashboard-analytics' );
			[ runBtn, formBtn ].forEach( ( b ) => {
				b.disabled = true;
				b.classList.add( 'is-busy' );
			} );
			alertBox.innerHTML = '';
			progress.hidden = false;
			try {
				const scope = await runTests( url, ( strategy, done ) => {
					if ( strategy ) {
						progress.textContent = sprintf(
							/* translators: 1: device name, 2: page path or "homepage". */
							__( 'Testing %1$s for %2$s, this takes up to 30 seconds', 'swift-dashboard-analytics' ),
							DEVICES[ strategy ].toLowerCase(),
							label
						);
					} else {
						view = done;
						renderScreen();
					}
				} );
				view = scope;
				renderScreen();
				sd.toast( __( 'Speed test finished. Your scores are up to date.', 'swift-dashboard-analytics' ), 'success' );
			} catch ( e ) {
				showError( e.err, e.strategy );
				sd.toast( errorText( e.err ), 'error' );
			} finally {
				progress.hidden = true;
				[ runBtn, formBtn ].forEach( ( b ) => {
					b.disabled = false;
					b.classList.remove( 'is-busy' );
				} );
			}
		}

		runBtn.addEventListener( 'click', () => run( '' ) );
		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			const target = checkPage( form );
			if ( target && ! formBtn.disabled ) {
				run( 'home' === target ? '' : target );
			}
		} );
		alertBox.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( '[data-sd-speed-retry]' ) && ! runBtn.disabled ) {
				run( lastUrl );
			}
		} );

		if ( custom ) {
			form.querySelector( 'input' ).value = custom.url;
		}
		renderScreen();

		// Arrived from the dashboard widget with a page to test (Pro).
		const asked = new URLSearchParams( window.location.search ).get( 'sd_test_url' );
		if ( asked ) {
			form.querySelector( 'input' ).value = asked;
			window.history.replaceState( null, '', window.location.pathname + '?page=' + encodeURIComponent( new URLSearchParams( window.location.search ).get( 'page' ) || '' ) );
			form.requestSubmit();
		}
	}

	/* ---------- Dashboard widgets ---------- */

	const link = ( label, primary ) => '<a class="sd-btn ' + ( primary ? 'sd-btn--primary' : 'sd-btn--tertiary' ) + ' sd-btn--sm" href="' + esc( data.screenUrl ) + '">' + esc( label ) + '</a>';
	const scoresWidget = app.querySelector( '[data-sd-speed-widget="scores"]' );
	const fixesWidget = app.querySelector( '[data-sd-speed-widget="fixes"]' );

	function renderFixesWidget() {
		if ( ! fixesWidget ) {
			return;
		}
		if ( ! results.mobile ) {
			fixesWidget.innerHTML = empty( 'gauge', __( 'No performance test has been run', 'swift-dashboard-analytics' ), __( 'Run a speed test to see where your site can get faster.', 'swift-dashboard-analytics' ) );
		} else if ( ! results.mobile.fixes.length ) {
			fixesWidget.innerHTML = empty( 'check-circle', __( 'Nothing important to fix', 'swift-dashboard-analytics' ), __( 'Google found no failing speed checks on mobile.', 'swift-dashboard-analytics' ), link( __( 'Open Speed & Optimize', 'swift-dashboard-analytics' ) ) );
		} else {
			fixesWidget.innerHTML = '<ol class="sd-fixes sd-fixes--compact">' + results.mobile.fixes.slice( 0, 3 ).map( ( f ) => fixItem( f, true ) ).join( '' ) + '</ol>' +
				'<div class="sd-speed-foot"><span class="sd-speed-note">' + esc( __( 'Mobile, biggest impact first', 'swift-dashboard-analytics' ) ) + '</span>' + link( __( 'Open Speed & Optimize', 'swift-dashboard-analytics' ) ) + '</div>';
		}
	}

	if ( scoresWidget ) {
		scoresWidget.innerHTML = '<div class="sd-speed-widget">' +
			'<div class="sd-gauges sd-gauges--two" data-sd-speed-rings></div>' +
			'<div class="sd-speed-widget__bar"><span class="sd-speed-note" data-sd-speed-widget-last></span>' +
			'<button type="button" class="sd-btn sd-btn--primary sd-btn--sm" data-sd-speed-widget-run>' + sd.icon( 'arrow-clockwise' ) + esc( __( 'Run test', 'swift-dashboard-analytics' ) ) + '</button></div>' +
			'<p class="sd-speed-note sd-speed-widget__progress" role="status" aria-live="polite" data-sd-speed-widget-progress></p>' +
			'<div data-sd-speed-widget-alert></div>' +
			pageForm( 'sd-speed-widget-url', true ) + '</div>';

		const rings = scoresWidget.querySelector( '[data-sd-speed-rings]' );
		const last = scoresWidget.querySelector( '[data-sd-speed-widget-last]' );
		const wProgress = scoresWidget.querySelector( '[data-sd-speed-widget-progress]' );
		const wAlert = scoresWidget.querySelector( '[data-sd-speed-widget-alert]' );
		const wRun = scoresWidget.querySelector( '[data-sd-speed-widget-run]' );
		const wForm = scoresWidget.querySelector( 'form' );
		const wFormBtn = wForm.querySelector( 'button' );

		const renderRings = () => {
			rings.innerHTML = gauge( results.mobile ? results.mobile.scores.performance : null, DEVICES.mobile, 'ring' ) +
				gauge( results.desktop ? results.desktop.scores.performance : null, DEVICES.desktop, 'ring' );
			last.textContent = lastTested( results );
		};

		async function widgetRun() {
			[ wRun, wFormBtn ].forEach( ( b ) => {
				b.disabled = true;
				b.classList.add( 'is-busy' );
			} );
			wAlert.innerHTML = '';
			try {
				await runTests( '', ( strategy ) => {
					wProgress.textContent = strategy ? sprintf( /* translators: %s: device name. */ __( 'Testing %s, this takes up to 30 seconds', 'swift-dashboard-analytics' ), DEVICES[ strategy ].toLowerCase() ) : '';
					renderRings();
				} );
				renderFixesWidget();
				sd.toast( __( 'Speed test finished. Your scores are up to date.', 'swift-dashboard-analytics' ), 'success' );
			} catch ( e ) {
				notice( wAlert, 'error', errorText( e.err ) );
				sd.toast( errorText( e.err ), 'error' );
			} finally {
				wProgress.textContent = '';
				[ wRun, wFormBtn ].forEach( ( b ) => {
					b.disabled = false;
					b.classList.remove( 'is-busy' );
				} );
			}
		}

		wRun.addEventListener( 'click', widgetRun );
		wForm.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			const target = checkPage( wForm );
			if ( 'home' === target ) {
				widgetRun();
			} else if ( target ) {
				window.location.href = data.screenUrl + '&sd_test_url=' + encodeURIComponent( target );
			}
		} );
		renderRings();
	}
	renderFixesWidget();
}() );
