<?php
/**
 * Speed & Optimize: real Google PageSpeed Insights scores for mobile and desktop, plus
 * plain-language advice on what to fix. Read only: Swift Dash never changes the site.
 *
 * Tests run through the Swift Dash API with our Google key, so users never need one.
 * Free: homepage only. Other pages of the same site need Swift Dash Pro (the
 * swiftdash_speed_fetch filter). Addresses outside this site are always refused.
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SWIFTDASH_API' ) ) {
	define( 'SWIFTDASH_API', 'https://swdashapi.swiftoutbox.com/v1' );
}

/**
 * Test strategies.
 *
 * @return string[]
 */
function swiftdash_speed_strategies() {
	return array( 'mobile', 'desktop' );
}

/**
 * Stored homepage result for one strategy, or null when never tested.
 *
 * @param string $strategy mobile or desktop.
 * @return array|null
 */
function swiftdash_speed_get( $strategy ) {
	$result = get_option( 'swiftdash_speed_' . $strategy, null );
	return is_array( $result ) ? $result : null;
}

/**
 * Stored results for the last custom page tested with Pro: { url, mobile, desktop }, or null.
 *
 * @return array|null
 */
function swiftdash_speed_get_custom() {
	$custom = get_option( 'swiftdash_speed_custom', null );
	return is_array( $custom ) && ! empty( $custom['url'] ) ? $custom : null;
}

/**
 * Homepage address Google tests.
 *
 * @return string
 */
function swiftdash_speed_url() {
	/**
	 * Filters the homepage address that speed tests measure. Must stay on this site's homepage.
	 *
	 * @param string $url Default home_url( '/' ).
	 */
	return (string) apply_filters( 'swiftdash_speed_url', home_url( '/' ) );
}

/**
 * Whether Swift Dash Pro can test any page of this site (Pro hooks swiftdash_speed_fetch while licensed).
 *
 * @return bool
 */
function swiftdash_speed_pro_active() {
	return (bool) has_filter( 'swiftdash_speed_fetch' );
}

/**
 * Lower case host without a leading www.
 *
 * @param string $url Address.
 * @return string
 */
function swiftdash_speed_host( $url ) {
	return preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
}

/**
 * Whether an address is this site's homepage (same host and path as home_url(), no query).
 *
 * @param string $url Address.
 * @return bool
 */
function swiftdash_speed_is_home( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	return swiftdash_speed_host( $url ) === swiftdash_speed_host( home_url() )
		&& '' === (string) wp_parse_url( $url, PHP_URL_QUERY )
		&& untrailingslashit( $path ) === untrailingslashit( $home );
}

/**
 * Whether Google can reach an address (local development hosts cannot be tested).
 *
 * @param string $url Address.
 * @return bool
 */
function swiftdash_speed_is_public( $url ) {
	$host = strtolower( trim( (string) wp_parse_url( $url, PHP_URL_HOST ), '[]' ) );
	if ( '' === $host || in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) || false === strpos( $host, '.' ) ) {
		return false;
	}
	if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return (bool) filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}
	return ! preg_match( '/\.(local|test|localhost|internal|lan)$/', $host );
}

/**
 * Run one PageSpeed Insights test. Boundary checks happen here, before any HTTP call.
 *
 * @param string $url      Address on this site.
 * @param string $strategy mobile or desktop.
 * @return array|WP_Error Trimmed PSI response (lighthouseResult, loadingExperience, originLoadingExperience).
 */
function swiftdash_speed_fetch( $url, $strategy ) {
	$url = esc_url_raw( $url, array( 'http', 'https' ) );
	if ( '' === $url || swiftdash_speed_host( $url ) !== swiftdash_speed_host( home_url() ) ) {
		return new WP_Error( 'outside_site', __( 'Only pages of this website can be tested.', 'swift-dashboard-analytics' ), array( 'status' => 403 ) );
	}
	$is_home = swiftdash_speed_is_home( $url );
	if ( ! $is_home && ! swiftdash_speed_pro_active() ) {
		return new WP_Error( 'pro_required', __( 'Testing other pages needs Swift Dash Pro.', 'swift-dashboard-analytics' ), array( 'status' => 403 ) );
	}
	if ( ! swiftdash_speed_is_public( $url ) ) {
		return new WP_Error( 'not_public', __( 'Google cannot test a local site. Speed tests work once the site is online at a public address.', 'swift-dashboard-analytics' ), array( 'status' => 400 ) );
	}

	if ( swiftdash_speed_pro_active() ) {
		/**
		 * Runs a speed test through Swift Dash Pro (any page of this site).
		 *
		 * @param array|WP_Error|null $result   Null to fall back to the free homepage test.
		 * @param string              $url      Address on this site.
		 * @param string              $strategy mobile or desktop.
		 */
		$result = apply_filters( 'swiftdash_speed_fetch', null, $url, $strategy );
		if ( null !== $result ) {
			return $result;
		}
		if ( ! $is_home ) {
			return new WP_Error( 'pro_required', __( 'Testing other pages needs an active Swift Dash Pro license.', 'swift-dashboard-analytics' ), array( 'status' => 403 ) );
		}
	}

	$response = wp_remote_post(
		untrailingslashit( SWIFTDASH_API ) . '/speed/free',
		array(
			'timeout' => 90,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'site_url' => home_url(),
					'url'      => $url,
					'strategy' => $strategy,
				)
			),
		)
	);
	return swiftdash_speed_api_result( $response );
}

/**
 * Turn a Swift Dash API response into the PSI array or a WP_Error with a plain message.
 *
 * @param array|WP_Error $response wp_remote_* response.
 * @return array|WP_Error
 */
function swiftdash_speed_api_result( $response ) {
	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'test_failed',
			__( 'The speed test service could not be reached. Please try again in a minute.', 'swift-dashboard-analytics' ),
			array(
				'status'    => 502,
				'reference' => 'SD-API-NETWORK',
			)
		);
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 === $code && is_array( $body ) ) {
		return $body;
	}

	$error = is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
	$slug  = isset( $error['code'] ) ? sanitize_key( $error['code'] ) : '';
	$wait  = isset( $error['retry_after'] ) ? max( 1, (int) $error['retry_after'] ) : 0;
	$texts = array(
		'outside_site' => __( 'Only pages of this website can be tested.', 'swift-dashboard-analytics' ),
		'pro_required' => __( 'Testing other pages needs Swift Dash Pro.', 'swift-dashboard-analytics' ),
		'not_public'   => __( 'Google cannot test a local site. Speed tests work once the site is online at a public address.', 'swift-dashboard-analytics' ),
		'rate_limited' => sprintf(
			/* translators: %d: minutes to wait. */
			_n( 'You can run 10 tests per 10 minutes. Please try again in %d minute.', 'You can run 10 tests per 10 minutes. Please try again in %d minutes.', (int) max( 1, ceil( $wait / 60 ) ), 'swift-dashboard-analytics' ),
			(int) max( 1, ceil( $wait / 60 ) )
		),
	);
	return new WP_Error(
		isset( $texts[ $slug ] ) ? $slug : 'test_failed',
		isset( $texts[ $slug ] ) ? $texts[ $slug ] : __( 'Google could not finish the test. Please try again in a minute.', 'swift-dashboard-analytics' ),
		array(
			'status'    => $code ? $code : 502,
			'reference' => 'SD-API-' . ( $code ? $code : 'NETWORK' ),
		)
	);
}

/**
 * Register REST routes.
 */
function swiftdash_speed_routes() {
	$permission = function () {
		return current_user_can( swiftdash_capability() );
	};

	register_rest_route(
		'swiftdash/v1',
		'/speed',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => $permission,
			'callback'            => function () {
				return rest_ensure_response(
					array(
						'mobile'  => swiftdash_speed_get( 'mobile' ),
						'desktop' => swiftdash_speed_get( 'desktop' ),
						'custom'  => swiftdash_speed_get_custom(),
					)
				);
			},
		)
	);

	register_rest_route(
		'swiftdash/v1',
		'/speed/run',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => $permission,
			'callback'            => 'swiftdash_speed_rest_run',
			'args'                => array(
				'strategy' => array(
					'type'     => 'string',
					'enum'     => swiftdash_speed_strategies(),
					'required' => true,
				),
				'url'      => array(
					'type'    => 'string',
					'default' => '',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'swiftdash_speed_routes' );

/**
 * POST /speed/run: test the homepage (or, with Pro, another page of this site) and store the result.
 * Response: the stored result plus 'scope' (home or custom).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function swiftdash_speed_rest_run( $request ) {
	$strategy = $request['strategy'];
	$url      = '' === trim( (string) $request['url'] ) ? swiftdash_speed_url() : trim( (string) $request['url'] );
	$psi      = swiftdash_speed_fetch( $url, $strategy );
	if ( is_wp_error( $psi ) ) {
		return $psi;
	}

	$url    = esc_url_raw( $url );
	$result = is_array( $psi ) ? swiftdash_speed_parse( $psi, $url ) : null;
	if ( ! $result ) {
		return new WP_Error(
			'test_failed',
			__( 'Google could not finish the test. Please try again in a minute.', 'swift-dashboard-analytics' ),
			array(
				'status'    => 502,
				'reference' => 'SD-PSI-EMPTY',
			)
		);
	}

	if ( swiftdash_speed_is_home( $url ) ) {
		update_option( 'swiftdash_speed_' . $strategy, $result, false );
		$result['scope'] = 'home';
		return rest_ensure_response( $result );
	}

	$custom = swiftdash_speed_get_custom();
	if ( ! $custom || $custom['url'] !== $url ) {
		$custom = array(
			'url'     => $url,
			'mobile'  => null,
			'desktop' => null,
		);
	}
	$custom[ $strategy ] = $result;
	update_option( 'swiftdash_speed_custom', $custom, false );
	$result['scope'] = 'custom';
	return rest_ensure_response( $result );
}

/**
 * Turn a PageSpeed Insights v5 response into the stored result.
 *
 * @param array  $body Decoded PSI response.
 * @param string $url  Tested address.
 * @return array|null Null when the response holds no performance score.
 */
function swiftdash_speed_parse( $body, $url ) {
	$lr = isset( $body['lighthouseResult'] ) ? $body['lighthouseResult'] : array();
	if ( ! isset( $lr['categories']['performance']['score'] ) ) {
		return null;
	}
	$audits = isset( $lr['audits'] ) ? $lr['audits'] : array();

	$scores = array();
	foreach ( array(
		'performance'    => 'performance',
		'accessibility'  => 'accessibility',
		'best_practices' => 'best-practices',
		'seo'            => 'seo',
	) as $key => $category ) {
		$scores[ $key ] = isset( $lr['categories'][ $category ]['score'] ) ? (int) round( $lr['categories'][ $category ]['score'] * 100 ) : null;
	}

	$lab    = array();
	$status = array();
	foreach ( array(
		'lcp_ms' => 'largest-contentful-paint',
		'cls'    => 'cumulative-layout-shift',
		'tbt_ms' => 'total-blocking-time',
		'fcp_ms' => 'first-contentful-paint',
		'si_ms'  => 'speed-index',
	) as $key => $id ) {
		$audit          = isset( $audits[ $id ] ) ? $audits[ $id ] : array();
		$value          = isset( $audit['numericValue'] ) ? (float) $audit['numericValue'] : null;
		$lab[ $key ]    = null === $value ? null : ( 'cls' === $key ? round( $value, 3 ) : (int) round( $value ) );
		$status[ $key ] = isset( $audit['score'] ) ? swiftdash_speed_band( $audit['score'] * 100 ) : null;
	}

	return array(
		'tested_at'  => time(),
		'url'        => esc_url_raw( $url ),
		'scores'     => $scores,
		'lab'        => $lab,
		'lab_status' => $status,
		'field'      => swiftdash_speed_parse_field( isset( $body['loadingExperience'] ) ? $body['loadingExperience'] : array() ),
		'fixes'      => swiftdash_speed_parse_fixes( $lr ),
	);
}

/**
 * Score band: good (90-100), needs_work (50-89), poor (0-49).
 *
 * @param float $score 0-100.
 * @return string
 */
function swiftdash_speed_band( $score ) {
	$score = round( $score );
	return $score >= 90 ? 'good' : ( $score >= 50 ? 'needs_work' : 'poor' );
}

/**
 * Chrome UX Report (real-user) data, or null when Google has none for this page or site.
 *
 * @param array $experience PSI loadingExperience.
 * @return array|null
 */
function swiftdash_speed_parse_field( $experience ) {
	$metrics = isset( $experience['metrics'] ) ? $experience['metrics'] : array();
	$map     = array(
		'lcp' => 'LARGEST_CONTENTFUL_PAINT_MS',
		'inp' => 'INTERACTION_TO_NEXT_PAINT',
		'cls' => 'CUMULATIVE_LAYOUT_SHIFT_SCORE',
	);
	$bands   = array(
		'FAST'    => 'good',
		'AVERAGE' => 'needs_work',
		'SLOW'    => 'poor',
	);
	$field   = array(
		'lcp_ms'     => null,
		'inp_ms'     => null,
		'cls'        => null,
		'categories' => array(),
		'origin'     => ! empty( $experience['origin_fallback'] ),
	);
	$found   = false;
	foreach ( $map as $key => $metric ) {
		if ( ! isset( $metrics[ $metric ]['percentile'] ) ) {
			$field['categories'][ $key ] = null;
			continue;
		}
		$found = true;
		$value = $metrics[ $metric ]['percentile'];
		$field[ 'cls' === $key ? 'cls' : $key . '_ms' ] = 'cls' === $key ? round( $value / 100, 2 ) : (int) $value;
		$category                                       = isset( $metrics[ $metric ]['category'] ) ? $metrics[ $metric ]['category'] : '';
		$field['categories'][ $key ]                    = isset( $bands[ $category ] ) ? $bands[ $category ] : null;
	}
	return $found ? $field : null;
}

/**
 * Failing performance audits, ranked by estimated savings.
 *
 * @param array $lr PSI lighthouseResult.
 * @return array[]
 */
function swiftdash_speed_parse_fixes( $lr ) {
	$refs  = isset( $lr['categories']['performance']['auditRefs'] ) ? $lr['categories']['performance']['auditRefs'] : array();
	$fixes = array();
	foreach ( $refs as $ref ) {
		$id    = isset( $ref['id'] ) ? $ref['id'] : '';
		$audit = isset( $lr['audits'][ $id ] ) ? $lr['audits'][ $id ] : null;
		$mode  = isset( $audit['scoreDisplayMode'] ) ? $audit['scoreDisplayMode'] : '';
		if ( ! $audit || ( isset( $ref['group'] ) && 'metrics' === $ref['group'] )
			|| in_array( $mode, array( 'informative', 'notApplicable', 'manual', 'error' ), true )
			|| ! isset( $audit['score'] ) || $audit['score'] >= 0.9 ) {
			continue;
		}

		$savings = isset( $audit['details']['overallSavingsMs'] ) ? (float) $audit['details']['overallSavingsMs'] : 0;
		foreach ( array( 'LCP', 'FCP', 'TBT', 'INP' ) as $metric ) {
			if ( isset( $audit['metricSavings'][ $metric ] ) ) {
				$savings = max( $savings, (float) $audit['metricSavings'][ $metric ] );
			}
		}
		$savings = $savings > 0 ? (int) round( $savings ) : null;

		$fixes[] = array(
			'id'          => sanitize_key( $id ),
			'title'       => swiftdash_speed_plain( isset( $audit['title'] ) ? $audit['title'] : $id ),
			'explanation' => swiftdash_speed_plain( isset( $audit['description'] ) ? $audit['description'] : '' ),
			'evidence'    => isset( $audit['displayValue'] ) ? sanitize_text_field( $audit['displayValue'] ) : '',
			'savings_ms'  => $savings,
			'severity'    => $savings > 1000 ? 'high' : ( $savings > 300 ? 'medium' : 'low' ),
			'advice'      => swiftdash_speed_advice( $id ),
			'score'       => (float) $audit['score'],
		);
	}

	usort(
		$fixes,
		function ( $a, $b ) {
			return array( (int) $b['savings_ms'], $a['score'] ) <=> array( (int) $a['savings_ms'], $b['score'] );
		}
	);
	return array_map(
		function ( $fix ) {
			unset( $fix['score'] );
			return $fix;
		},
		$fixes
	);
}

/**
 * Lighthouse markdown to plain text: links become their text, "Learn more" links go.
 *
 * @param string $text Markdown text.
 * @return string
 */
function swiftdash_speed_plain( $text ) {
	$text = preg_replace( '/\s*\[Learn[^\]]*\]\([^)]*\)\.?/i', '', (string) $text );
	$text = preg_replace( '/\[([^\]]+)\]\([^)]*\)/', '$1', $text );
	return trim( sanitize_text_field( str_replace( '`', '', $text ) ) );
}

/**
 * Plain-language advice for a Lighthouse audit. Says what kind of tool helps, never names products.
 *
 * @param string $id Audit id.
 * @return string
 */
function swiftdash_speed_advice( $id ) {
	$images = __( 'Compress your images and serve them as WebP or AVIF at the size they are shown on screen. An image optimization plugin can do this for new and existing uploads.', 'swift-dashboard-analytics' );
	$block  = __( 'Let the page show content before every script and style has loaded: defer non-essential JavaScript and load only the CSS the top of the page needs first. Most caching and performance plugins have settings for this. Check your pages after each change.', 'swift-dashboard-analytics' );
	$server = __( 'Your server is slow to send the first part of the page. Page caching usually helps the most. If it stays slow, ask your host about server-level caching or a faster hosting plan.', 'swift-dashboard-analytics' );
	$cache  = __( 'Tell browsers to keep your images, CSS and JavaScript for a long time so repeat visits load faster. A caching plugin or your host can set long cache times for static files.', 'swift-dashboard-analytics' );
	$lcp    = __( 'Make the biggest item at the top of the page load first: do not lazy load it, compress it, and avoid sliders or large background videos there.', 'swift-dashboard-analytics' );
	$cls    = __( 'Stop content from jumping while the page loads: give images, videos and embeds a width and height, and do not insert banners or notices above content after it appears.', 'swift-dashboard-analytics' );
	$js     = __( 'The browser spends a long time running JavaScript. Remove plugins you do not need, turn off unused plugin features, and delay chat widgets, trackers and similar scripts until the visitor interacts.', 'swift-dashboard-analytics' );

	$advice = array(
		'render-blocking-resources'        => $block,
		'render-blocking-insight'          => $block,
		'unused-javascript'                => __( 'Some scripts load on pages that never use them. Turn off plugin features you do not use, remove plugins you no longer need, or use a plugin that can unload scripts on pages where they are not needed.', 'swift-dashboard-analytics' ),
		'unused-css-rules'                 => __( 'Some styles load on pages that never use them. Remove unused plugins and page builder add-ons, or use a performance plugin that can remove unused CSS. Check your pages after the change.', 'swift-dashboard-analytics' ),
		'modern-image-formats'             => $images,
		'uses-optimized-images'            => $images,
		'uses-responsive-images'           => $images,
		'image-delivery-insight'           => $images,
		'offscreen-images'                 => __( 'Load images further down the page only when the visitor scrolls to them (lazy loading). WordPress does this by default; check that your theme or page builder has not turned it off.', 'swift-dashboard-analytics' ),
		'efficient-animated-content'       => __( 'Replace large animated GIFs with short MP4 or WebM videos, which are much smaller.', 'swift-dashboard-analytics' ),
		'server-response-time'             => $server,
		'document-latency-insight'         => $server,
		'uses-text-compression'            => __( 'Turn on GZIP or Brotli compression for text files. Many hosts can enable it for you, and most caching plugins have a setting for it.', 'swift-dashboard-analytics' ),
		'uses-long-cache-ttl'              => $cache,
		'cache-insight'                    => $cache,
		'unminified-css'                   => __( 'Minify your CSS files to remove spaces and comments. Most caching and performance plugins can do this.', 'swift-dashboard-analytics' ),
		'unminified-javascript'            => __( 'Minify your JavaScript files to remove spaces and comments. Most caching and performance plugins can do this.', 'swift-dashboard-analytics' ),
		'largest-contentful-paint-element' => $lcp,
		'lcp-breakdown-insight'            => $lcp,
		'lcp-lazy-loaded'                  => __( 'The main image at the top of the page is lazy loaded, so it appears late. Turn off lazy loading for that one image and mark it as high priority in your theme or page builder.', 'swift-dashboard-analytics' ),
		'prioritize-lcp-image'             => __( 'Help the browser find the main image at the top of the page sooner: do not lazy load it, preload it or mark it as high priority, and avoid setting it only as a CSS background.', 'swift-dashboard-analytics' ),
		'lcp-discovery-insight'            => __( 'Help the browser find the main image at the top of the page sooner: do not lazy load it, preload it or mark it as high priority, and avoid setting it only as a CSS background.', 'swift-dashboard-analytics' ),
		'total-byte-weight'                => __( 'The page downloads a lot of data. Compress images, remove scripts and fonts you do not need, and show embedded videos as a preview image that loads the player only on click.', 'swift-dashboard-analytics' ),
		'dom-size'                         => __( 'The page is built from a very large number of HTML elements. Simplify the layout, remove nested containers you do not need in your page builder, and split very long pages or lists.', 'swift-dashboard-analytics' ),
		'dom-size-insight'                 => __( 'The page is built from a very large number of HTML elements. Simplify the layout, remove nested containers you do not need in your page builder, and split very long pages or lists.', 'swift-dashboard-analytics' ),
		'third-party-summary'              => __( 'Code from other services (chat, ads, trackers, embeds) slows the page. Remove the ones you do not need and delay the rest until the visitor interacts.', 'swift-dashboard-analytics' ),
		'third-parties-insight'            => __( 'Code from other services (chat, ads, trackers, embeds) slows the page. Remove the ones you do not need and delay the rest until the visitor interacts.', 'swift-dashboard-analytics' ),
		'font-display'                     => __( 'Make text visible while web fonts load by using font-display: swap. Host fonts on your own site and use fewer font families and weights. Many themes and performance plugins have a setting for this.', 'swift-dashboard-analytics' ),
		'font-display-insight'             => __( 'Make text visible while web fonts load by using font-display: swap. Host fonts on your own site and use fewer font families and weights. Many themes and performance plugins have a setting for this.', 'swift-dashboard-analytics' ),
		'bootup-time'                      => $js,
		'mainthread-work-breakdown'        => $js,
		'forced-reflow-insight'            => $js,
		'redirects'                        => __( 'The page address redirects before it loads. Link straight to the final address, including https and the correct www or non-www form.', 'swift-dashboard-analytics' ),
		'layout-shifts'                    => $cls,
		'cls-culprits-insight'             => $cls,
		'unsized-images'                   => $cls,
		'non-composited-animations'        => $cls,
		'legacy-javascript'                => __( 'Some scripts include old code for outdated browsers. Keep your theme and plugins up to date; if it remains, ask the theme or plugin author.', 'swift-dashboard-analytics' ),
		'legacy-javascript-insight'        => __( 'Some scripts include old code for outdated browsers. Keep your theme and plugins up to date; if it remains, ask the theme or plugin author.', 'swift-dashboard-analytics' ),
		'duplicated-javascript'            => __( 'The same code library loads more than once, often from two plugins. Remove one of the overlapping plugins or ask their authors.', 'swift-dashboard-analytics' ),
		'duplicated-javascript-insight'    => __( 'The same code library loads more than once, often from two plugins. Remove one of the overlapping plugins or ask their authors.', 'swift-dashboard-analytics' ),
		'network-dependency-tree-insight'  => __( 'Important files are loaded one after another instead of together. Load fewer files at the top of the page and preload the fonts and images the first screen needs.', 'swift-dashboard-analytics' ),
		'critical-request-chains'          => __( 'Important files are loaded one after another instead of together. Load fewer files at the top of the page and preload the fonts and images the first screen needs.', 'swift-dashboard-analytics' ),
		'viewport-insight'                 => __( 'The page is missing a mobile viewport setting. Your theme normally adds it; ask the theme author if it is missing.', 'swift-dashboard-analytics' ),
	);

	return isset( $advice[ $id ] ) ? $advice[ $id ] : __( 'Open the full report on PageSpeed Insights for the details of this item. Your developer or the support team of your theme or plugins can usually help.', 'swift-dashboard-analytics' );
}

/**
 * Speed & Optimize screen. JavaScript fills the cards from the stored results.
 *
 * @param array $screen Screen definition.
 */
function swiftdash_screen_speed( $screen ) {
	swiftdash_render_page_header(
		$screen['title'],
		$screen['description'],
		function () {
			?>
			<p class="sd-speed-last" data-sd-speed-last></p>
			<button type="button" class="sd-btn sd-btn--primary" data-sd-speed-run>
				<?php echo swiftdash_icon( 'arrow-clockwise' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
				<?php esc_html_e( 'Run test', 'swift-dashboard-analytics' ); ?>
			</button>
			<?php
		}
	);

	$skeleton = '<span class="sd-skeleton sd-skeleton--title"></span><span class="sd-skeleton sd-skeleton--block"></span>';
	?>
	<p class="sd-speed-progress" role="status" aria-live="polite" data-sd-speed-progress hidden></p>
	<div class="sd-speed" data-sd-speed-screen>
		<div data-sd-speed-alert></div>
		<section class="sd-card sd-speed-pagecard" aria-label="<?php esc_attr_e( 'Test another page', 'swift-dashboard-analytics' ); ?>">
			<div data-sd-speed-page-slot></div>
			<div data-sd-speed-view></div>
		</section>
		<div class="sd-grid">
			<?php
			foreach ( array(
				'mobile'  => __( 'Mobile', 'swift-dashboard-analytics' ),
				'desktop' => __( 'Desktop', 'swift-dashboard-analytics' ),
			) as $strategy => $label ) :
				?>
				<section class="sd-card sd-span-6" aria-labelledby="sd-speed-<?php echo esc_attr( $strategy ); ?>-title">
					<header class="sd-card__head">
						<h2 class="sd-card__title" id="sd-speed-<?php echo esc_attr( $strategy ); ?>-title"><?php echo esc_html( $label ); ?></h2>
					</header>
					<div class="sd-card__body" data-sd-speed-scores="<?php echo esc_attr( $strategy ); ?>"><?php echo wp_kses_post( $skeleton ); ?></div>
				</section>
			<?php endforeach; ?>
		</div>
		<div class="sd-grid">
			<section class="sd-card sd-span-12" aria-labelledby="sd-speed-cwv-title">
				<header class="sd-card__head">
					<h2 class="sd-card__title" id="sd-speed-cwv-title"><?php esc_html_e( 'Core Web Vitals', 'swift-dashboard-analytics' ); ?></h2>
				</header>
				<div class="sd-card__body" data-sd-speed-vitals><?php echo wp_kses_post( $skeleton ); ?></div>
			</section>
		</div>
		<div class="sd-grid">
			<section class="sd-card sd-span-12" aria-labelledby="sd-speed-fixes-title">
				<header class="sd-card__head">
					<h2 class="sd-card__title" id="sd-speed-fixes-title"><?php esc_html_e( 'Potential speed improvement areas', 'swift-dashboard-analytics' ); ?></h2>
					<div class="sd-card__actions sd-speed-toggle" role="group" aria-label="<?php esc_attr_e( 'Device', 'swift-dashboard-analytics' ); ?>">
						<button type="button" class="sd-btn sd-btn--sm sd-btn--secondary" data-sd-speed-device="mobile" aria-pressed="true"><?php esc_html_e( 'Mobile', 'swift-dashboard-analytics' ); ?></button>
						<button type="button" class="sd-btn sd-btn--sm sd-btn--secondary" data-sd-speed-device="desktop" aria-pressed="false"><?php esc_html_e( 'Desktop', 'swift-dashboard-analytics' ); ?></button>
					</div>
				</header>
				<div class="sd-card__body" data-sd-speed-fixes><?php echo wp_kses_post( $skeleton ); ?></div>
			</section>
		</div>
		<div class="sd-grid">
			<?php
			if ( has_action( 'swiftdash_speed_pro' ) ) {
				/**
				 * Prints the scheduled tests and score history card (Swift Dash Pro).
				 */
				do_action( 'swiftdash_speed_pro' );
			} else {
				swiftdash_render_widget(
					array(
						'id'          => 'speed-history',
						'title'       => __( 'Scheduled tests and score history', 'swift-dashboard-analytics' ),
						'description' => __( 'Run tests automatically every week, follow your scores on a history chart, get an alert when a score drops, and test any page of your site.', 'swift-dashboard-analytics' ),
						'span'        => 12,
						'tier'        => 'pro',
						'placeholder' => 'chart',
						'render'      => null,
						'state'       => '',
					)
				);
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Dashboard widgets: speed scores and top improvement areas (filled by speed.js).
 */
function swiftdash_speed_widgets() {
	swiftdash_register_widget(
		'speed-scores',
		array(
			'title'       => __( 'Speed scores', 'swift-dashboard-analytics' ),
			'description' => __( 'Your latest Google PageSpeed performance scores for mobile and desktop.', 'swift-dashboard-analytics' ),
			'area'        => 'performance',
			'order'       => 10,
			'span'        => 4,
			'render'      => function () {
				echo '<div data-sd-speed-widget="scores"><span class="sd-skeleton sd-skeleton--block"></span></div>';
			},
		)
	);
	swiftdash_register_widget(
		'speed-fixes',
		array(
			'title'       => __( 'Potential speed improvement areas', 'swift-dashboard-analytics' ),
			'description' => __( 'The three changes that would help your mobile speed score the most.', 'swift-dashboard-analytics' ),
			'area'        => 'performance',
			'order'       => 20,
			'span'        => 4,
			'render'      => function () {
				echo '<div data-sd-speed-widget="fixes"><span class="sd-skeleton sd-skeleton--block"></span></div>';
			},
		)
	);
}
add_action( 'swiftdash_register_widgets', 'swiftdash_speed_widgets' );

/**
 * Enqueue speed assets on the Speed & Optimize screen and the dashboard.
 *
 * @param string $screen_key Current screen key.
 */
function swiftdash_speed_assets( $screen_key ) {
	if ( ! in_array( $screen_key, array( 'speed', 'dashboard' ), true ) ) {
		return;
	}
	wp_enqueue_style( 'swiftdash-speed', SWIFTDASH_URL . 'assets/css/speed.css', array( 'swiftdash-app' ), SWIFTDASH_VERSION );
	wp_enqueue_script( 'swiftdash-speed', SWIFTDASH_URL . 'assets/js/speed.js', array( 'swiftdash-app', 'wp-api-fetch', 'wp-i18n' ), SWIFTDASH_VERSION, true );
	wp_set_script_translations( 'swiftdash-speed', 'swift-dashboard-analytics' );
	wp_add_inline_script(
		'swiftdash-speed',
		'window.swiftdashSpeed = ' . wp_json_encode(
			array(
				'results'    => array(
					'mobile'  => swiftdash_speed_get( 'mobile' ),
					'desktop' => swiftdash_speed_get( 'desktop' ),
				),
				'custom'     => swiftdash_speed_get_custom(),
				'home'       => swiftdash_speed_url(),
				'isPro'      => swiftdash_speed_pro_active(),
				'upgradeUrl' => add_query_arg(
					array(
						'utm_source' => 'plugin',
						'utm_medium' => 'speed-page-test',
					),
					SWIFTDASH_PRICING_URL
				),
				'screenUrl'  => admin_url( 'admin.php?page=swift-dash-speed' ),
			)
		) . ';',
		'before'
	);
}
add_action( 'swiftdash_enqueue_assets', 'swiftdash_speed_assets' );
