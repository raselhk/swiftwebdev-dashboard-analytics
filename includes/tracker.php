<?php
/**
 * Cookieless visitor counter: front-end beacon, REST endpoint, hourly rollup.
 *
 * Nothing identifying is stored. A visitor is sha256( daily salt + IP + user agent ); the
 * salt rotates every site-local day, so hashes cannot be linked across days.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Full table name.
 *
 * @param string $name hits|daily.
 * @return string
 */
function swiftdash_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'swiftdash_' . $name;
}

/**
 * Tables (dbDelta format).
 *
 * @param string[] $schema  Statements.
 * @param string   $prefix  Table prefix.
 * @param string   $collate Charset clause.
 * @return string[]
 */
function swiftdash_tracker_schema( $schema, $prefix, $collate ) {
	$schema[] = "CREATE TABLE {$prefix}hits (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  visitor char(64) NOT NULL,
  new_session tinyint(1) unsigned NOT NULL DEFAULT 0,
  created datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY visitor_created (visitor,created),
  KEY created (created)
) {$collate};";
	$schema[] = "CREATE TABLE {$prefix}daily (
  day date NOT NULL,
  visitors int(10) unsigned NOT NULL DEFAULT 0,
  views int(10) unsigned NOT NULL DEFAULT 0,
  sessions int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day)
) {$collate};";
	// Daily totals per page path, referrer host, device type (kept 90 days), AI source and AI bot (180 days).
	$schema[] = "CREATE TABLE {$prefix}dims (
  day date NOT NULL,
  kind varchar(10) NOT NULL,
  item varchar(191) NOT NULL,
  count int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,kind,item)
) {$collate};";
	return $schema;
}
add_filter( 'swiftdash_schema', 'swiftdash_tracker_schema', 10, 3 );

/**
 * Front-end script.
 */
function swiftdash_enqueue_tracker() {
	if ( is_preview() || is_feed() || is_embed() || is_customize_preview() ) {
		return;
	}
	if ( swiftdash_setting( 'exclude_admins' ) && is_user_logged_in() ) {
		return;
	}
	wp_enqueue_script(
		'swiftdash-tracker',
		SWIFTDASH_URL . 'assets/js/tracker.js',
		array(),
		SWIFTDASH_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_add_inline_script(
		'swiftdash-tracker',
		'window.swiftdashTracker=' . wp_json_encode( array( 'endpoint' => rest_url( 'swiftdash/v1/hit' ) ) ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'swiftdash_enqueue_tracker' );

/**
 * Register the beacon route.
 */
function swiftdash_register_tracker_route() {
	register_rest_route(
		'swiftdash/v1',
		'/hit',
		array(
			'methods'             => 'POST',
			'callback'            => 'swiftdash_rest_hit',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'swiftdash_register_tracker_route' );

/**
 * Is this user agent a crawler or tool?
 *
 * @param string $ua User agent.
 * @return bool
 */
function swiftdash_is_bot( $ua ) {
	if ( '' === $ua ) {
		return true;
	}
	return (bool) preg_match( '/bot|crawl|spider|slurp|headless|lighthouse|pingdom|curl|wget|python-requests|httpclient|libwww|monitor|uptime|facebookexternalhit|preview|scrapy|phantom|node-fetch|go-http/i', $ua );
}

/**
 * Today's salt, rotated when the site-local date changes.
 *
 * @return string
 */
function swiftdash_salt() {
	$today = wp_date( 'Y-m-d' );
	$salt  = get_option( 'swiftdash_salt' );
	if ( ! is_array( $salt ) || ! isset( $salt['date'], $salt['salt'] ) || $salt['date'] !== $today ) {
		$salt = array(
			'date' => $today,
			'salt' => wp_generate_password( 32, true, true ),
		);
		update_option( 'swiftdash_salt', $salt, false );
	}
	return $salt['salt'];
}

/**
 * Anonymous visitor hash. The IP and user agent are used here and never stored.
 *
 * @param string $ip IP address.
 * @param string $ua User agent.
 * @return string
 */
function swiftdash_visitor_hash( $ip, $ua ) {
	return hash( 'sha256', swiftdash_salt() . $ip . $ua );
}

/**
 * Record one page view.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function swiftdash_rest_hit( $request ) {
	global $wpdb;
	$done = new WP_REST_Response( null, 204 );

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	if ( swiftdash_is_bot( $ua ) ) {
		return $done;
	}
	foreach ( array( 'HTTP_SEC_PURPOSE', 'HTTP_PURPOSE' ) as $header ) {
		if ( isset( $_SERVER[ $header ] ) && false !== stripos( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ), 'prefetch' ) ) {
			return $done;
		}
	}

	$body = $request->get_body();
	$data = strlen( $body ) < 2048 ? json_decode( $body, true ) : null;
	// The page path is never stored with the visit, only in the daily per-page totals.
	$path = is_array( $data ) && isset( $data['p'] ) && is_string( $data['p'] ) ? $data['p'] : '';
	$path = rawurldecode( (string) preg_split( '/[?#]/', $path, 2 )[0] );
	if ( '' === $path || '/' !== $path[0] || strlen( $path ) > 255 || ! wp_check_invalid_utf8( $path ) || preg_match( '/[\x00-\x1f\x7f]/', $path ) ) {
		return new WP_REST_Response( null, 400 );
	}
	$ref = isset( $data['r'] ) && is_string( $data['r'] ) ? strtolower( $data['r'] ) : '';
	$ref = preg_replace( '/^www\./', '', $ref );
	if ( strlen( $ref ) > 100 || ! preg_match( '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $ref ) || preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === $ref ) {
		$ref = '';
	}
	$utm = isset( $data['u'] ) && is_string( $data['u'] ) && preg_match( '/^[a-z0-9][a-z0-9._ -]{0,39}$/', $data['u'] ) ? $data['u'] : '';

	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	/**
	 * Filters the client IP used for the (never stored) visitor hash, e.g. behind a proxy.
	 *
	 * @param string $ip REMOTE_ADDR.
	 */
	$ip      = (string) apply_filters( 'swiftdash_client_ip', $ip );
	$visitor = swiftdash_visitor_hash( $ip, $ua );
	$hits    = swiftdash_table( 'hits' );

	$recent = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->prepare( 'SELECT 1 FROM %i WHERE visitor = %s AND created > %s LIMIT 1', $hits, $visitor, gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS ) )
	);
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$hits,
		array(
			'visitor'     => $visitor,
			'new_session' => $recent ? 0 : 1,
			'created'     => gmdate( 'Y-m-d H:i:s' ),
		),
		array( '%s', '%d', '%s' )
	);

	// Daily totals: every view counts for its page; sources and devices count once per session.
	$dims = array( 'page' => mb_substr( $path, 0, 191 ) );
	if ( ! $recent ) {
		$dims['referrer'] = $ref;
		$dims['device']   = swiftdash_device_type( $ua );
		$dims['ai']       = swiftdash_ai_source( $ref, $utm );
	}
	$day = wp_date( 'Y-m-d' );
	foreach ( array_filter( $dims ) as $kind => $item ) {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'INSERT INTO %i (day, kind, item, count) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE count = count + 1', swiftdash_table( 'dims' ), $day, $kind, $item )
		);
	}
	return $done;
}

/**
 * Device type from a user agent. iPads that report a desktop browser count as desktop.
 *
 * @param string $ua User agent.
 * @return string mobile|tablet|desktop.
 */
function swiftdash_device_type( $ua ) {
	if ( preg_match( '/ipad|tablet|kindle|silk|playbook|android(?!.*mobi)/i', $ua ) ) {
		return 'tablet';
	}
	return preg_match( '/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua ) ? 'mobile' : 'desktop';
}

/**
 * AI assistants whose visits are counted: referrer hosts (subdomains match too) and utm_source values.
 *
 * @return array[] key => { label, hosts, utm }.
 */
function swiftdash_ai_sources() {
	$sources = array(
		'chatgpt'    => array( 'ChatGPT', array( 'chatgpt.com', 'chat.openai.com' ), array( 'chatgpt.com', 'chatgpt', 'openai' ) ),
		'perplexity' => array( 'Perplexity', array( 'perplexity.ai' ), array( 'perplexity', 'perplexity.ai' ) ),
		'gemini'     => array( 'Gemini', array( 'gemini.google.com', 'bard.google.com' ), array( 'gemini', 'gemini.google.com', 'bard' ) ),
		'claude'     => array( 'Claude', array( 'claude.ai' ), array( 'claude', 'claude.ai' ) ),
		'copilot'    => array( 'Copilot', array( 'copilot.microsoft.com', 'copilot.cloud.microsoft' ), array( 'copilot', 'copilot.microsoft.com' ) ),
		'deepseek'   => array( 'DeepSeek', array( 'chat.deepseek.com', 'deepseek.com' ), array( 'deepseek', 'deepseek.com' ) ),
		'meta'       => array( 'Meta AI', array( 'meta.ai' ), array( 'meta.ai' ) ),
		'grok'       => array( 'Grok', array( 'grok.com' ), array( 'grok', 'grok.com' ) ),
		'mistral'    => array( 'Mistral Le Chat', array( 'chat.mistral.ai' ), array( 'mistral', 'chat.mistral.ai' ) ),
		'you'        => array( 'You.com', array( 'you.com' ), array( 'you.com' ) ),
		'phind'      => array( 'Phind', array( 'phind.com' ), array( 'phind', 'phind.com' ) ),
	);
	foreach ( $sources as $key => $s ) {
		$sources[ $key ] = array_combine( array( 'label', 'hosts', 'utm' ), $s );
	}
	/**
	 * Filters the AI assistants counted on the AI traffic widget.
	 *
	 * @param array[] $sources key => { label, hosts, utm }.
	 */
	return (array) apply_filters( 'swiftdash_ai_sources', $sources );
}

/**
 * AI source key of a visit, from its referrer host (without www) or utm_source.
 *
 * @param string $host Referrer host or ''.
 * @param string $utm  Lowercased utm_source or ''.
 * @return string Source key or ''.
 */
function swiftdash_ai_source( $host, $utm ) {
	foreach ( swiftdash_ai_sources() as $key => $source ) {
		foreach ( (array) $source['hosts'] as $h ) {
			if ( '' !== $host && ( $host === $h || str_ends_with( $host, '.' . $h ) ) ) {
				return $key;
			}
		}
		if ( '' !== $utm && in_array( $utm, (array) $source['utm'], true ) ) {
			return $key;
		}
	}
	return '';
}

/**
 * UTC [start, end) of a site-local day.
 *
 * @param string $date Y-m-d.
 * @return string[]
 */
function swiftdash_day_bounds( $date ) {
	$utc   = new DateTimeZone( 'UTC' );
	$start = new DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
	$end   = $start->modify( '+1 day' );
	return array( $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
}

/**
 * Counts for one site-local day from the raw hits.
 *
 * @param string $date Y-m-d.
 * @return int[] visitors, views, sessions.
 */
function swiftdash_live_day( $date ) {
	global $wpdb;
	$hits   = swiftdash_table( 'hits' );
	$bounds = swiftdash_day_bounds( $date );
	$row    = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->prepare( 'SELECT COUNT(DISTINCT visitor) AS visitors, COUNT(*) AS views, COALESCE(SUM(new_session),0) AS sessions FROM %i WHERE created >= %s AND created < %s', $hits, $bounds[0], $bounds[1] ),
		ARRAY_A
	);
	return array(
		'visitors' => (int) $row['visitors'],
		'views'    => (int) $row['views'],
		'sessions' => (int) $row['sessions'],
	);
}

/**
 * Hourly: refresh today's and yesterday's daily rows, prune old data.
 */
function swiftdash_rollup() {
	global $wpdb;
	$hits  = swiftdash_table( 'hits' );
	$daily = swiftdash_table( 'daily' );
	$today = new DateTimeImmutable( 'today', wp_timezone() );

	foreach ( array( $today, $today->modify( '-1 day' ) ) as $day ) {
		$date   = $day->format( 'Y-m-d' );
		$counts = swiftdash_live_day( $date );
		if ( $counts['views'] > 0 ) {
			$wpdb->replace( $daily, array( 'day' => $date ) + $counts, array( '%s', '%d', '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$wpdb->delete( $daily, array( 'day' => $date ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created < %s', $hits, gmdate( 'Y-m-d H:i:s', time() - 48 * HOUR_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	// Daily totals: 90 days (the longest period on the dashboard) plus the 90 days before it,
	// which the "vs previous period" change on the KPI cards compares against. Nothing older is kept.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', $daily, $today->modify( '-180 days' )->format( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	// Page, referrer and device totals: 90 days, the longest period they are shown for. AI visitor
	// and AI bot totals: 180 days, for the "vs previous period" change of the longest period.
	$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE day < %s AND kind NOT IN ('ai', 'aibot')", swiftdash_table( 'dims' ), $today->modify( '-90 days' )->format( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', swiftdash_table( 'dims' ), $today->modify( '-180 days' )->format( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
add_action( 'swiftdash_rollup', 'swiftdash_rollup' );

/**
 * Make sure the hourly event exists.
 */
function swiftdash_schedule_rollup() {
	if ( ! wp_next_scheduled( 'swiftdash_rollup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'swiftdash_rollup' );
	}
}
add_action( 'swiftdash_installed', 'swiftdash_schedule_rollup' );
add_action( 'init', 'swiftdash_schedule_rollup' );
register_deactivation_hook(
	SWIFTDASH_FILE,
	function () {
		wp_clear_scheduled_hook( 'swiftdash_rollup' );
	}
);

/**
 * Privacy policy text.
 */
function swiftdash_privacy_content() {
	wp_add_privacy_policy_content(
		'SwiftWebDev Dashboard',
		wp_kses_post(
			'<p>' . __( 'SwiftWebDev Dashboard counts visitors, page views and sessions without cookies and without storing IP addresses or user agents. Each visit is reduced to a one-way hash of the visitor IP address and browser, mixed with a secret salt that changes every day, so a visitor cannot be recognized from one day to the next.', 'swiftwebdev-dashboard-analytics' ) . '</p><p>' . __( 'Raw page-view rows are kept for 48 hours; daily visitor totals are kept for 180 days. Daily totals per page, referring website and device type (mobile, tablet or desktop) are kept for 90 days; daily totals per AI assistant are kept for 180 days. They are counts only, are not linked to any visitor, and still no IP address or user agent is stored. All data stays in the database of this site and is not sent to any third party.', 'swiftwebdev-dashboard-analytics' ) . '</p><p>' . __( 'Requests from known AI crawlers (for example GPTBot or ClaudeBot) are recognized by the name in their user agent and added to a daily total per crawler, kept for 180 days. The user agent, IP address and requested address are not stored.', 'swiftwebdev-dashboard-analytics' ) . '</p>'
		)
	);
}
add_action( 'admin_init', 'swiftdash_privacy_content' );
