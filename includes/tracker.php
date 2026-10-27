<?php
/**
 * Cookieless visitor counter: front-end beacon, REST endpoint, hourly rollup.
 *
 * Nothing identifying is stored. A visitor is sha256( daily salt + IP + user agent ); the
 * salt rotates every site-local day, so hashes cannot be linked across days.
 *
 * @package SwiftDash
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
  path varchar(255) NOT NULL,
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

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( swiftdash_is_bot( $ua ) ) {
		return $done;
	}
	foreach ( array( 'HTTP_SEC_PURPOSE', 'HTTP_PURPOSE' ) as $header ) {
		if ( isset( $_SERVER[ $header ] ) && false !== stripos( (string) wp_unslash( $_SERVER[ $header ] ), 'prefetch' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return $done;
		}
	}

	$body = $request->get_body();
	$data = strlen( $body ) < 2048 ? json_decode( $body, true ) : null;
	$path = is_array( $data ) && isset( $data['p'] ) && is_string( $data['p'] ) ? $data['p'] : '';
	$path = (string) preg_split( '/[?#]/', $path, 2 )[0];
	if ( '' === $path || '/' !== $path[0] || strlen( $path ) > 255 || preg_match( '/[\x00-\x1f\x7f]/', $path ) ) {
		return new WP_REST_Response( null, 400 );
	}

	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
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
			'path'        => $path,
			'new_session' => $recent ? 0 : 1,
			'created'     => gmdate( 'Y-m-d H:i:s' ),
		),
		array( '%s', '%s', '%d', '%s' )
	);
	return $done;
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
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', $daily, $today->modify( '-60 days' )->format( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
		'Swift Dash',
		wp_kses_post(
			'<p>' . __( 'Swift Dash counts visitors, page views and sessions without cookies and without storing IP addresses or user agents. Each visit is reduced to a one-way hash of the visitor IP address and browser, mixed with a secret salt that changes every day, so a visitor cannot be recognised from one day to the next.', 'swift-dashboard-analytics' ) . '</p><p>' . __( 'Raw page-view rows are kept for 48 hours; daily totals are kept for 60 days. All data stays in the database of this site and is not sent to any third party.', 'swift-dashboard-analytics' ) . '</p>'
		)
	);
}
add_action( 'admin_init', 'swiftdash_privacy_content' );
