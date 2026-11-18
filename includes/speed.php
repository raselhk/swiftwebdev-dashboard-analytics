<?php
/**
 * Speed & Optimize: real Google PageSpeed Insights scores for mobile and desktop, plus
 * plain-language advice on what to fix. Read only: SwiftWebDev Dashboard never changes the site.
 *
 * Tests run through the SwiftWebDev speed test service with our Google key, so users never
 * need one. Any page of this site can be tested; addresses outside this site are refused.
 *
 * @package SwiftWebDevDashboard
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
 * Stored results for the last other page of this site that was tested: { url, mobile, desktop }, or null.
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
		return new WP_Error( 'outside_site', __( 'Only pages of this website can be tested.', 'swiftwebdev-dashboard-analytics' ), array( 'status' => 403 ) );
	}
	if ( ! swiftdash_speed_is_public( $url ) ) {
		return new WP_Error( 'not_public', __( 'Google cannot test a local site. Speed tests work once the site is online at a public address.', 'swiftwebdev-dashboard-analytics' ), array( 'status' => 400 ) );
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
 * Turn a speed test service response into the PSI array or a WP_Error with a plain message.
 *
 * @param array|WP_Error $response wp_remote_* response.
 * @return array|WP_Error
 */
function swiftdash_speed_api_result( $response ) {
	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'test_failed',
			__( 'The speed test service could not be reached. Please try again in a minute.', 'swiftwebdev-dashboard-analytics' ),
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
		'outside_site' => __( 'Only pages of this website can be tested.', 'swiftwebdev-dashboard-analytics' ),
		'not_public'   => __( 'Google cannot test a local site. Speed tests work once the site is online at a public address.', 'swiftwebdev-dashboard-analytics' ),
		'rate_limited' => sprintf(
			/* translators: %d: minutes to wait. */
			_n( 'You can run 10 tests per 10 minutes. Please try again in %d minute.', 'You can run 10 tests per 10 minutes. Please try again in %d minutes.', (int) max( 1, ceil( $wait / 60 ) ), 'swiftwebdev-dashboard-analytics' ),
			(int) max( 1, ceil( $wait / 60 ) )
		),
	);
	return new WP_Error(
		isset( $texts[ $slug ] ) ? $slug : 'test_failed',
		isset( $texts[ $slug ] ) ? $texts[ $slug ] : __( 'Google could not finish the test. Please try again in a minute.', 'swiftwebdev-dashboard-analytics' ),
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
 * POST /speed/run: test the homepage or another page of this site and store the result.
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
			__( 'Google could not finish the test. Please try again in a minute.', 'swiftwebdev-dashboard-analytics' ),
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
	$fixes  = array();
	foreach ( array(
		'performance'    => 'performance',
		'accessibility'  => 'accessibility',
		'best_practices' => 'best-practices',
		'seo'            => 'seo',
	) as $key => $category ) {
		$scores[ $key ] = isset( $lr['categories'][ $category ]['score'] ) ? (int) round( $lr['categories'][ $category ]['score'] * 100 ) : null;
		$fixes[ $key ]  = 'performance' === $key ? swiftdash_speed_parse_fixes( $lr ) : swiftdash_speed_parse_checks( $lr, $category );
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

	// 'fixes' stays the performance list (results saved by 1.2.0 hold only that); 'fixes_by_category' adds the rest.
	return array(
		'tested_at'         => time(),
		'url'               => esc_url_raw( $url ),
		'scores'            => $scores,
		'lab'               => $lab,
		'lab_status'        => $status,
		'field'             => swiftdash_speed_parse_field( isset( $body['loadingExperience'] ) ? $body['loadingExperience'] : array() ),
		'fixes'             => $fixes['performance'],
		'fixes_by_category' => $fixes,
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
 * Failing accessibility, best practices or SEO audits, biggest score impact first.
 *
 * Impact is what the audit costs its category score: Lighthouse scores a category as the
 * weighted mean of its scored audits, so a failing audit costs weight * (1 - score) / total weight.
 *
 * @param array  $lr       PSI lighthouseResult.
 * @param string $category Lighthouse category id: accessibility, best-practices or seo.
 * @return array[]
 */
function swiftdash_speed_parse_checks( $lr, $category ) {
	$refs   = isset( $lr['categories'][ $category ]['auditRefs'] ) ? $lr['categories'][ $category ]['auditRefs'] : array();
	$scored = array();
	$total  = 0;
	foreach ( $refs as $ref ) {
		$audit = isset( $ref['id'], $lr['audits'][ $ref['id'] ] ) ? $lr['audits'][ $ref['id'] ] : null;
		$mode  = isset( $audit['scoreDisplayMode'] ) ? $audit['scoreDisplayMode'] : '';
		if ( isset( $audit['score'] ) && ! in_array( $mode, array( 'informative', 'notApplicable', 'manual', 'error' ), true ) ) {
			$scored[] = array( $ref, $audit );
			$total   += isset( $ref['weight'] ) ? (float) $ref['weight'] : 0;
		}
	}

	$fixes = array();
	foreach ( $scored as list( $ref, $audit ) ) {
		// Hidden audits are experimental; PageSpeed Insights does not show them either.
		if ( $audit['score'] >= 0.9 || ( isset( $ref['group'] ) && 'hidden' === $ref['group'] ) ) {
			continue;
		}
		$points  = $total > 0 && isset( $ref['weight'] ) ? (int) round( $ref['weight'] * ( 1 - $audit['score'] ) / $total * 100 ) : 0;
		$fixes[] = array(
			'id'          => sanitize_key( $ref['id'] ),
			'title'       => swiftdash_speed_plain( isset( $audit['title'] ) ? $audit['title'] : $ref['id'] ),
			'explanation' => swiftdash_speed_plain( isset( $audit['description'] ) ? $audit['description'] : '' ),
			'evidence'    => isset( $audit['displayValue'] ) ? sanitize_text_field( $audit['displayValue'] ) : '',
			'savings_ms'  => null,
			'points'      => $points > 0 ? $points : null,
			'severity'    => $points >= 8 ? 'high' : ( $points >= 4 ? 'medium' : 'low' ),
			'advice'      => swiftdash_speed_check_advice( $ref['id'] ),
		);
	}

	// Biggest impact first; equal impact keeps the Lighthouse order (usort is not stable on PHP 7).
	if ( $fixes ) {
		array_multisort( array_map( 'intval', array_column( $fixes, 'points' ) ), SORT_DESC, array_keys( $fixes ), SORT_ASC, $fixes );
	}
	return $fixes;
}

/**
 * Plain-language advice for an accessibility, best practices or SEO audit. Says where to look in
 * WordPress and what kind of tool helps, never names products. Empty for audits without advice.
 *
 * @param string $id Audit id.
 * @return string
 */
function swiftdash_speed_check_advice( $id ) {
	$alt     = __( 'Add a short description (alt text) to each image that carries meaning: open the image in the Media Library or select it in the editor and fill in "Alternative Text". Leave it empty for purely decorative images.', 'swiftwebdev-dashboard-analytics' );
	$names   = __( 'Give every button and link a name that says what it does. For icon-only buttons and image links, add visible text or a label in your theme, page builder or menu settings (often called "aria-label" or "accessible name").', 'swiftwebdev-dashboard-analytics' );
	$labels  = __( 'Every form field needs a visible label that says what to enter. Edit the form in your form plugin and add a label to each field; placeholder text alone is not enough.', 'swiftwebdev-dashboard-analytics' );
	$lang    = __( 'The page does not say which language it is written in. Set the site language under Settings > General. If it is set and this still shows, your theme is missing the language attribute; ask the theme author.', 'swiftwebdev-dashboard-analytics' );
	$lists   = __( 'A list or menu is built in a way screen readers cannot follow. This usually comes from the theme, a menu or a page builder widget; update it, or ask its author to fix the list markup.', 'swiftwebdev-dashboard-analytics' );
	$tap     = __( 'Some buttons or links are too small or too close together to tap on a phone. Make them at least 24 by 24 pixels (48 is better) and add space between them in your theme or page builder settings.', 'swiftwebdev-dashboard-analytics' );
	$title   = __( 'Give the page a clear title. Set the post or page title in the editor, and the site title under Settings > General. An SEO plugin lets you write a separate title for search results.', 'swiftwebdev-dashboard-analytics' );
	$view    = __( 'Visitors must be able to zoom on phones, and the page needs a mobile viewport setting. Your theme controls this; update the theme, or ask its author to allow zooming.', 'swiftwebdev-dashboard-analytics' );
	$aria    = __( 'Some hidden accessibility code (ARIA) on the page is wrong, which confuses screen readers. It normally comes from the theme or a plugin: update them, and if it remains, report it to the author with the full Google report.', 'swiftwebdev-dashboard-analytics' );
	$tables  = __( 'A table is missing proper header cells, so screen readers cannot tell which heading belongs to which value. In the editor, turn on the header row for the table, and use tables for data only, not for layout.', 'swiftwebdev-dashboard-analytics' );
	$image   = __( 'Some images are shown stretched or at a lower quality than the screen needs. Upload images in the right proportions and at least as large as they are displayed, and avoid forcing a width and height that do not match the image.', 'swiftwebdev-dashboard-analytics' );
	$prompt  = __( 'The page asks for a browser permission as soon as it loads, which visitors usually refuse. Ask only after the visitor clicks something; look for this setting in the plugin that shows the prompt.', 'swiftwebdev-dashboard-analytics' );
	$blocked = __( 'Search engines are being told not to show this page. Check that "Discourage search engines from indexing this site" is off under Settings > Reading, and that your SEO plugin has not set this page to "noindex".', 'swiftwebdev-dashboard-analytics' );

	if ( 0 === strpos( $id, 'aria-' ) ) {
		return $aria;
	}

	$advice = array(
		// Accessibility.
		'image-alt'                    => $alt,
		'input-image-alt'              => $alt,
		'object-alt'                   => $alt,
		'button-name'                  => $names,
		'link-name'                    => $names,
		'label'                        => $labels,
		'select-name'                  => $labels,
		'form-field-multiple-labels'   => $labels,
		'label-content-name-mismatch'  => __( 'A control shows one text but announces another to screen readers. Make the hidden label start with the same words as the visible text.', 'swiftwebdev-dashboard-analytics' ),
		'color-contrast'               => __( 'Some text is too faint against its background. Use darker text or a lighter background in your theme colors or page builder (aim for a contrast of at least 4.5 to 1 for normal text), and check text placed on images.', 'swiftwebdev-dashboard-analytics' ),
		'link-in-text-block'           => __( 'Links inside text are told apart by color only. Underline links in your content, or give them a clearly different style, in your theme settings.', 'swiftwebdev-dashboard-analytics' ),
		'html-has-lang'                => $lang,
		'html-lang-valid'              => $lang,
		'valid-lang'                   => $lang,
		'list'                         => $lists,
		'listitem'                     => $lists,
		'definition-list'              => $lists,
		'dlitem'                       => $lists,
		'target-size'                  => $tap,
		'tap-targets'                  => $tap,
		'heading-order'                => __( 'Headings skip a level, for example from Heading 2 straight to Heading 4. Use one Heading 1 for the page title, then Heading 2 for sections and Heading 3 inside them. Pick heading levels for structure, not for text size.', 'swiftwebdev-dashboard-analytics' ),
		'document-title'               => $title,
		'meta-viewport'                => $view,
		'viewport'                     => $view,
		'bypass'                       => __( 'Keyboard users cannot skip past the menu to the content. Most themes include a "Skip to content" link; if yours does not, ask the theme author to add one.', 'swiftwebdev-dashboard-analytics' ),
		'skip-link'                    => __( 'The "Skip to content" link does not lead anywhere. This is a theme issue; update the theme or report it to its author.', 'swiftwebdev-dashboard-analytics' ),
		'landmark-one-main'            => __( 'The page has no marked main content area, which screen reader users rely on to jump to the content. This is set by the theme; ask the theme author to add a main landmark.', 'swiftwebdev-dashboard-analytics' ),
		'frame-title'                  => __( 'An embedded frame (such as a video, map or form) has no title. Add a title in the embed block or code so screen readers can say what it contains.', 'swiftwebdev-dashboard-analytics' ),
		'video-caption'                => __( 'A video has no captions. Add a captions file to the video in the editor, or turn on captions with your video service.', 'swiftwebdev-dashboard-analytics' ),
		'tabindex'                     => __( 'Some elements force their own keyboard order, so the Tab key jumps around the page. Remove custom tab order values from your theme or page builder elements.', 'swiftwebdev-dashboard-analytics' ),
		'duplicate-id-aria'            => __( 'Two elements on the page share the same ID, often because the same form, widget or template part appears twice. Remove the duplicate or give each one its own ID.', 'swiftwebdev-dashboard-analytics' ),
		'meta-refresh'                 => __( 'The page reloads or redirects by itself after a set time, which disorients visitors. Remove the automatic refresh and use a normal redirect instead.', 'swiftwebdev-dashboard-analytics' ),
		'td-headers-attr'              => $tables,
		'th-has-data-cells'            => $tables,
		'td-has-header'                => $tables,
		'table-fake-caption'           => $tables,
		'identical-links-same-purpose' => __( 'Several links have the same text but go to different places. Make each link text say where it leads, for example the post title instead of "Read more".', 'swiftwebdev-dashboard-analytics' ),

		// Best practices.
		'is-on-https'                  => __( 'Some parts of the page load without HTTPS. Install an SSL certificate (most hosts offer one for free), set both addresses under Settings > General to https, and update old http links to images and files in your content.', 'swiftwebdev-dashboard-analytics' ),
		'redirects-http'               => __( 'The http address does not send visitors to https. Turn on "Force HTTPS" at your host, or ask them to redirect all traffic to https.', 'swiftwebdev-dashboard-analytics' ),
		'errors-in-console'            => __( 'The page reports errors while it loads, usually a script that fails or a file that is missing. Update your theme and plugins. If the errors remain, switch plugins off one at a time on a test copy of the site to find the cause.', 'swiftwebdev-dashboard-analytics' ),
		'deprecations'                 => __( 'The page uses browser features that are being retired and may stop working. Update your theme and plugins; if it remains, tell the author of the script named in the full Google report.', 'swiftwebdev-dashboard-analytics' ),
		'third-party-cookies'          => __( 'Other services on the page (ads, embeds, trackers) set cookies that browsers are starting to block. Remove the services you do not need, and use privacy-friendly embed options where they exist.', 'swiftwebdev-dashboard-analytics' ),
		'inspector-issues'             => __( 'The browser flagged problems on the page, such as blocked files or cookie warnings. Update your theme and plugins, and check the full Google report to see which file is involved.', 'swiftwebdev-dashboard-analytics' ),
		'image-aspect-ratio'           => $image,
		'image-size-responsive'        => $image,
		'geolocation-on-start'         => $prompt,
		'notification-on-start'        => $prompt,
		'paste-preventing-inputs'      => __( 'A form field blocks pasting, which breaks password managers. Remove the script or plugin setting that prevents pasting.', 'swiftwebdev-dashboard-analytics' ),
		'doctype'                      => __( 'The page does not start with the standard HTML5 doctype, or something is printed before it. This comes from the theme or from a plugin that prints output too early; ask its author.', 'swiftwebdev-dashboard-analytics' ),
		'charset'                      => __( 'The page does not declare its character set early enough, so special characters can show wrongly. Your theme normally sets this at the top of the page; ask the theme author.', 'swiftwebdev-dashboard-analytics' ),
		'font-size'                    => __( 'Some text is too small to read on a phone. Set body text to at least 16 pixels and avoid text smaller than 12 pixels in your theme or page builder typography settings.', 'swiftwebdev-dashboard-analytics' ),

		// SEO.
		'meta-description'             => __( 'The page has no meta description, the short summary shown under the title in search results. WordPress does not add one by itself: use an SEO plugin and write one or two sentences for each important page.', 'swiftwebdev-dashboard-analytics' ),
		'is-crawlable'                 => $blocked,
		'http-status-code'             => __( 'The page returned an error code instead of loading normally, so search engines will not index it. Check that the page is published and public, and look at your redirects, security and caching plugins.', 'swiftwebdev-dashboard-analytics' ),
		'link-text'                    => __( 'Some links use vague text such as "click here" or "read more". Rewrite them to say where they lead, for example "see our pricing".', 'swiftwebdev-dashboard-analytics' ),
		'crawlable-anchors'            => __( 'Some links have no real address, so search engines cannot follow them. Give each link a proper URL in the editor, and use a button instead of a link for actions that do not open a page.', 'swiftwebdev-dashboard-analytics' ),
		'robots-txt'                   => __( 'Your robots.txt file contains errors, so search engines may misread it. Open yoursite.com/robots.txt to see it. If an SEO plugin manages the file, fix the rules there; otherwise edit or remove the file on your server.', 'swiftwebdev-dashboard-analytics' ),
		'canonical'                    => __( 'The page points search engines to a wrong or conflicting main address (canonical URL). Check the canonical setting for this page in your SEO plugin, and make sure only one plugin adds it.', 'swiftwebdev-dashboard-analytics' ),
		'hreflang'                     => __( 'The language links for translated versions of this page are not valid. Check the language codes and addresses in your translation plugin.', 'swiftwebdev-dashboard-analytics' ),
		'structured-data'              => __( 'Check your structured data with a testing tool for rich results. An SEO plugin can add the right data for articles, products and businesses.', 'swiftwebdev-dashboard-analytics' ),
	);

	return isset( $advice[ $id ] ) ? $advice[ $id ] : '';
}

/**
 * Lighthouse markdown to plain text: links become their text, "Learn more" links go.
 * Code spans keep their angle brackets (`<html>` element), so the result is plain text to escape on output.
 *
 * @param string $text Markdown text.
 * @return string
 */
function swiftdash_speed_plain( $text ) {
	$text = preg_replace( '/\s*\[Learn[^\]]*\]\([^)]*\)\.?/i', '', (string) $text );
	// Also "Learn more about [topic](url) and more." sentences, which read as a dead end without the link.
	$text = preg_replace( '/\s*Learn more[^.\]]*\[[^\]]*\]\([^)]*\)[^.]*\.?/i', '', $text );
	$text = preg_replace( '/\[([^\]]+)\]\([^)]*\)/', '$1', $text );
	// sanitize_text_field() strips anything that looks like a tag, which would eat `<html>` from "`<html>` element ...".
	$text = preg_replace_callback(
		'/`([^`]*)`/',
		function ( $m ) {
			return str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $m[1] );
		},
		$text
	);
	return trim( str_replace( array( '&lt;', '&gt;', '`' ), array( '<', '>', '' ), sanitize_text_field( $text ) ) );
}

/**
 * Plain-language advice for a Lighthouse audit. Says what kind of tool helps, never names products.
 *
 * @param string $id Audit id.
 * @return string
 */
function swiftdash_speed_advice( $id ) {
	$images = __( 'Compress your images and serve them as WebP or AVIF at the size they are shown on screen. An image optimization plugin can do this for new and existing uploads.', 'swiftwebdev-dashboard-analytics' );
	$block  = __( 'Let the page show content before every script and style has loaded: defer non-essential JavaScript and load only the CSS the top of the page needs first. Most caching and performance plugins have settings for this. Check your pages after each change.', 'swiftwebdev-dashboard-analytics' );
	$server = __( 'Your server is slow to send the first part of the page. Page caching usually helps the most. If it stays slow, ask your host about server-level caching or a faster hosting plan.', 'swiftwebdev-dashboard-analytics' );
	$cache  = __( 'Tell browsers to keep your images, CSS and JavaScript for a long time so repeat visits load faster. A caching plugin or your host can set long cache times for static files.', 'swiftwebdev-dashboard-analytics' );
	$lcp    = __( 'Make the biggest item at the top of the page load first: do not lazy load it, compress it, and avoid sliders or large background videos there.', 'swiftwebdev-dashboard-analytics' );
	$cls    = __( 'Stop content from jumping while the page loads: give images, videos and embeds a width and height, and do not insert banners or notices above content after it appears.', 'swiftwebdev-dashboard-analytics' );
	$js     = __( 'The browser spends a long time running JavaScript. Remove plugins you do not need, turn off unused plugin features, and delay chat widgets, trackers and similar scripts until the visitor interacts.', 'swiftwebdev-dashboard-analytics' );

	$advice = array(
		'render-blocking-resources'        => $block,
		'render-blocking-insight'          => $block,
		'unused-javascript'                => __( 'Some scripts load on pages that never use them. Turn off plugin features you do not use, remove plugins you no longer need, or use a plugin that can unload scripts on pages where they are not needed.', 'swiftwebdev-dashboard-analytics' ),
		'unused-css-rules'                 => __( 'Some styles load on pages that never use them. Remove unused plugins and page builder add-ons, or use a performance plugin that can remove unused CSS. Check your pages after the change.', 'swiftwebdev-dashboard-analytics' ),
		'modern-image-formats'             => $images,
		'uses-optimized-images'            => $images,
		'uses-responsive-images'           => $images,
		'image-delivery-insight'           => $images,
		'offscreen-images'                 => __( 'Load images further down the page only when the visitor scrolls to them (lazy loading). WordPress does this by default; check that your theme or page builder has not turned it off.', 'swiftwebdev-dashboard-analytics' ),
		'efficient-animated-content'       => __( 'Replace large animated GIFs with short MP4 or WebM videos, which are much smaller.', 'swiftwebdev-dashboard-analytics' ),
		'server-response-time'             => $server,
		'document-latency-insight'         => $server,
		'uses-text-compression'            => __( 'Turn on GZIP or Brotli compression for text files. Many hosts can enable it for you, and most caching plugins have a setting for it.', 'swiftwebdev-dashboard-analytics' ),
		'uses-long-cache-ttl'              => $cache,
		'cache-insight'                    => $cache,
		'unminified-css'                   => __( 'Minify your CSS files to remove spaces and comments. Most caching and performance plugins can do this.', 'swiftwebdev-dashboard-analytics' ),
		'unminified-javascript'            => __( 'Minify your JavaScript files to remove spaces and comments. Most caching and performance plugins can do this.', 'swiftwebdev-dashboard-analytics' ),
		'largest-contentful-paint-element' => $lcp,
		'lcp-breakdown-insight'            => $lcp,
		'lcp-lazy-loaded'                  => __( 'The main image at the top of the page is lazy loaded, so it appears late. Turn off lazy loading for that one image and mark it as high priority in your theme or page builder.', 'swiftwebdev-dashboard-analytics' ),
		'prioritize-lcp-image'             => __( 'Help the browser find the main image at the top of the page sooner: do not lazy load it, preload it or mark it as high priority, and avoid setting it only as a CSS background.', 'swiftwebdev-dashboard-analytics' ),
		'lcp-discovery-insight'            => __( 'Help the browser find the main image at the top of the page sooner: do not lazy load it, preload it or mark it as high priority, and avoid setting it only as a CSS background.', 'swiftwebdev-dashboard-analytics' ),
		'total-byte-weight'                => __( 'The page downloads a lot of data. Compress images, remove scripts and fonts you do not need, and show embedded videos as a preview image that loads the player only on click.', 'swiftwebdev-dashboard-analytics' ),
		'dom-size'                         => __( 'The page is built from a very large number of HTML elements. Simplify the layout, remove nested containers you do not need in your page builder, and split very long pages or lists.', 'swiftwebdev-dashboard-analytics' ),
		'dom-size-insight'                 => __( 'The page is built from a very large number of HTML elements. Simplify the layout, remove nested containers you do not need in your page builder, and split very long pages or lists.', 'swiftwebdev-dashboard-analytics' ),
		'third-party-summary'              => __( 'Code from other services (chat, ads, trackers, embeds) slows the page. Remove the ones you do not need and delay the rest until the visitor interacts.', 'swiftwebdev-dashboard-analytics' ),
		'third-parties-insight'            => __( 'Code from other services (chat, ads, trackers, embeds) slows the page. Remove the ones you do not need and delay the rest until the visitor interacts.', 'swiftwebdev-dashboard-analytics' ),
		'font-display'                     => __( 'Make text visible while web fonts load by using font-display: swap. Host fonts on your own site and use fewer font families and weights. Many themes and performance plugins have a setting for this.', 'swiftwebdev-dashboard-analytics' ),
		'font-display-insight'             => __( 'Make text visible while web fonts load by using font-display: swap. Host fonts on your own site and use fewer font families and weights. Many themes and performance plugins have a setting for this.', 'swiftwebdev-dashboard-analytics' ),
		'bootup-time'                      => $js,
		'mainthread-work-breakdown'        => $js,
		'forced-reflow-insight'            => $js,
		'redirects'                        => __( 'The page address redirects before it loads. Link straight to the final address, including https and the correct www or non-www form.', 'swiftwebdev-dashboard-analytics' ),
		'layout-shifts'                    => $cls,
		'cls-culprits-insight'             => $cls,
		'unsized-images'                   => $cls,
		'non-composited-animations'        => $cls,
		'legacy-javascript'                => __( 'Some scripts include old code for outdated browsers. Keep your theme and plugins up to date; if it remains, ask the theme or plugin author.', 'swiftwebdev-dashboard-analytics' ),
		'legacy-javascript-insight'        => __( 'Some scripts include old code for outdated browsers. Keep your theme and plugins up to date; if it remains, ask the theme or plugin author.', 'swiftwebdev-dashboard-analytics' ),
		'duplicated-javascript'            => __( 'The same code library loads more than once, often from two plugins. Remove one of the overlapping plugins or ask their authors.', 'swiftwebdev-dashboard-analytics' ),
		'duplicated-javascript-insight'    => __( 'The same code library loads more than once, often from two plugins. Remove one of the overlapping plugins or ask their authors.', 'swiftwebdev-dashboard-analytics' ),
		'network-dependency-tree-insight'  => __( 'Important files are loaded one after another instead of together. Load fewer files at the top of the page and preload the fonts and images the first screen needs.', 'swiftwebdev-dashboard-analytics' ),
		'critical-request-chains'          => __( 'Important files are loaded one after another instead of together. Load fewer files at the top of the page and preload the fonts and images the first screen needs.', 'swiftwebdev-dashboard-analytics' ),
		'viewport-insight'                 => __( 'The page is missing a mobile viewport setting. Your theme normally adds it; ask the theme author if it is missing.', 'swiftwebdev-dashboard-analytics' ),
	);

	return isset( $advice[ $id ] ) ? $advice[ $id ] : __( 'Open the full report on PageSpeed Insights for the details of this item. Your developer or the support team of your theme or plugins can usually help.', 'swiftwebdev-dashboard-analytics' );
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
				<?php echo wp_kses( swiftdash_icon( 'arrow-clockwise' ), swiftdash_icon_kses() ); ?>
				<?php esc_html_e( 'Run test', 'swiftwebdev-dashboard-analytics' ); ?>
			</button>
			<?php
		}
	);

	$skeleton = '<span class="sd-skeleton sd-skeleton--title"></span><span class="sd-skeleton sd-skeleton--block"></span>';
	?>
	<p class="sd-speed-progress" role="status" aria-live="polite" data-sd-speed-progress hidden></p>
	<div class="sd-speed" data-sd-speed-screen>
		<div data-sd-speed-alert></div>
		<section class="sd-card sd-speed-pagecard" aria-label="<?php esc_attr_e( 'Test another page', 'swiftwebdev-dashboard-analytics' ); ?>">
			<div data-sd-speed-page-slot></div>
			<div data-sd-speed-view></div>
		</section>
		<div class="sd-grid">
			<?php
			foreach ( array(
				'mobile'  => __( 'Mobile', 'swiftwebdev-dashboard-analytics' ),
				'desktop' => __( 'Desktop', 'swiftwebdev-dashboard-analytics' ),
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
					<h2 class="sd-card__title" id="sd-speed-cwv-title"><?php esc_html_e( 'Core Web Vitals', 'swiftwebdev-dashboard-analytics' ); ?></h2>
				</header>
				<div class="sd-card__body" data-sd-speed-vitals><?php echo wp_kses_post( $skeleton ); ?></div>
			</section>
		</div>
		<div class="sd-grid">
			<section class="sd-card sd-span-12" aria-labelledby="sd-speed-fixes-title">
				<header class="sd-card__head">
					<h2 class="sd-card__title" id="sd-speed-fixes-title"><?php esc_html_e( 'Improvement areas', 'swiftwebdev-dashboard-analytics' ); ?></h2>
					<div class="sd-card__actions sd-speed-toggle" role="group" aria-label="<?php esc_attr_e( 'Device', 'swiftwebdev-dashboard-analytics' ); ?>">
						<button type="button" class="sd-btn sd-btn--sm sd-btn--secondary" data-sd-speed-device="mobile" aria-pressed="true"><?php esc_html_e( 'Mobile', 'swiftwebdev-dashboard-analytics' ); ?></button>
						<button type="button" class="sd-btn sd-btn--sm sd-btn--secondary" data-sd-speed-device="desktop" aria-pressed="false"><?php esc_html_e( 'Desktop', 'swiftwebdev-dashboard-analytics' ); ?></button>
					</div>
				</header>
				<div class="sd-card__body" data-sd-speed-fixes><?php echo wp_kses_post( $skeleton ); ?></div>
			</section>
		</div>
		<div class="sd-grid">
			<?php
			/**
			 * Prints extra cards at the end of the Speed & Optimize screen, for add-ons.
			 */
			do_action( 'swiftdash_speed_cards' );
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
			'title'       => __( 'Speed scores', 'swiftwebdev-dashboard-analytics' ),
			'description' => __( 'Your latest Google PageSpeed performance scores for mobile and desktop.', 'swiftwebdev-dashboard-analytics' ),
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
			'title'       => __( 'Improvement areas', 'swiftwebdev-dashboard-analytics' ),
			'description' => __( 'The three changes that would help your mobile speed score the most.', 'swiftwebdev-dashboard-analytics' ),
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
	wp_set_script_translations( 'swiftdash-speed', 'swiftwebdev-dashboard-analytics' );
	wp_add_inline_script(
		'swiftdash-speed',
		'window.swiftdashSpeed = ' . wp_json_encode(
			array(
				'results'   => array(
					'mobile'  => swiftdash_speed_get( 'mobile' ),
					'desktop' => swiftdash_speed_get( 'desktop' ),
				),
				'custom'    => swiftdash_speed_get_custom(),
				'home'      => swiftdash_speed_url(),
				'screenUrl' => admin_url( 'admin.php?page=swift-dash-speed' ),
			),
			JSON_HEX_TAG // Audit titles keep code like <html> as text; never let it close the script tag.
		) . ';',
		'before'
	);
}
add_action( 'swiftdash_enqueue_assets', 'swiftdash_speed_assets' );
