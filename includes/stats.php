<?php
/**
 * Visitor stats read API (contract shared by dashboard + analytics widgets).
 * Data comes from the tables written by tracker.php.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Visitor stats for the last $days full days including today.
 *
 * Visitors are real humans only; automated loads are counted separately. PRO replaces the numbers
 * through the swiftdash_pre_get_stats filter, the local tables stay available in swiftdash_get_stats_local().
 *
 * Period visitors are the sum of each day's unique visitors (no cross-day identity exists).
 *
 * @param int $days Number of days (1-90).
 * @return array {
 *     @type bool     $has_data True once any visit was recorded.
 *     @type string   $since    First recorded day (Y-m-d) or ''.
 *     @type array    $totals   visitors, views, sessions, automated for the period.
 *     @type array    $previous Same keys for the equal-length period before it.
 *     @type array[]  $daily    One row per day, oldest first: date (Y-m-d), visitors, views, sessions, automated. Zero-filled.
 * }
 */
function swiftdash_get_stats( $days = 30 ) {
	/**
	 * Short-circuits swiftdash_get_stats() (PRO supplies its own numbers).
	 *
	 * @param array|null $pre  Stats array in the same shape, or null.
	 * @param int        $days Days requested.
	 */
	$pre = apply_filters( 'swiftdash_pre_get_stats', null, $days );
	if ( is_array( $pre ) ) {
		return $pre;
	}
	return swiftdash_get_stats_local( $days );
}

/**
 * Stats from the local tables (same shape as swiftdash_get_stats()).
 *
 * @param int $days Number of days (1-90).
 * @return array
 */
function swiftdash_get_stats_local( $days = 30 ) {
	global $wpdb;
	$days  = max( 1, min( 90, (int) $days ) );
	$zero  = array(
		'visitors'  => 0,
		'views'     => 0,
		'sessions'  => 0,
		'automated' => 0,
	);
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	$tkey  = $today->format( 'Y-m-d' );
	$first = $today->modify( '-' . ( 2 * $days - 1 ) . ' days' )->format( 'Y-m-d' );
	$hits  = swiftdash_table( 'hits' );
	$table = swiftdash_table( 'daily' );

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->prepare( 'SELECT day, visitors, views, sessions, automated FROM %i WHERE day >= %s AND day < %s', $table, $first, $tkey ),
		ARRAY_A
	);
	$map = array();
	foreach ( (array) $rows as $row ) {
		$map[ $row['day'] ] = array(
			'visitors'  => (int) $row['visitors'],
			'views'     => (int) $row['views'],
			'sessions'  => (int) $row['sessions'],
			'automated' => (int) $row['automated'],
		);
	}
	$map[ $tkey ] = swiftdash_live_day( $tkey );

	$daily    = array();
	$totals   = $zero;
	$previous = $zero;
	for ( $i = 2 * $days - 1; $i >= 0; $i-- ) {
		$date = $today->modify( "-{$i} days" )->format( 'Y-m-d' );
		$day  = isset( $map[ $date ] ) ? $map[ $date ] : $zero;
		foreach ( $zero as $key => $unused ) {
			if ( $i < $days ) {
				$totals[ $key ] += $day[ $key ];
			} else {
				$previous[ $key ] += $day[ $key ];
			}
		}
		if ( $i < $days ) {
			$daily[] = array( 'date' => $date ) + $day;
		}
	}

	$since = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(day) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$min   = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(created) FROM %i', $hits ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $min ) {
		$hit_day = ( new DateTimeImmutable( $min, new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' );
		$since   = '' === $since || $hit_day < $since ? $hit_day : $since;
	}

	return array(
		'has_data' => '' !== $since,
		'since'    => $since,
		'totals'   => $totals,
		'previous' => $previous,
		'daily'    => $daily,
	);
}

/**
 * Most counted items of one kind over the last $days days (including today unless $full_days).
 *
 * @param string $kind  page (URL path), referrer (host), device (mobile|tablet|desktop) or ai (source key).
 * @param int    $days  Number of days (1-90).
 * @param int    $limit     Maximum rows.
 * @param bool   $full_days End with yesterday, so the period is $days complete days.
 * @return array[] Each: item (string), count (int). Highest count first.
 */
function swiftdash_get_top( $kind, $days = 30, $limit = 10, $full_days = false ) {
	/**
	 * Short-circuits swiftdash_get_top() (PRO supplies its own numbers).
	 *
	 * @param array|null $pre       Rows (item, count), or null.
	 * @param string     $kind      Dimension kind.
	 * @param int        $days      Days requested.
	 * @param int        $limit     Maximum rows.
	 * @param bool       $full_days Complete days only.
	 */
	$pre = apply_filters( 'swiftdash_pre_get_top', null, $kind, $days, $limit, $full_days );
	if ( is_array( $pre ) ) {
		return $pre;
	}
	return swiftdash_get_top_local( $kind, $days, $limit, $full_days );
}

/**
 * Top items from the local tables (same arguments and shape as swiftdash_get_top()).
 *
 * @param string $kind      Dimension kind.
 * @param int    $days      Number of days (1-90).
 * @param int    $limit     Maximum rows.
 * @param bool   $full_days End with yesterday.
 * @return array[]
 */
function swiftdash_get_top_local( $kind, $days = 30, $limit = 10, $full_days = false ) {
	global $wpdb;
	$days  = max( 1, min( 90, (int) $days ) );
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	$end   = $full_days ? $today : $today->modify( '+1 day' ); // Exclusive.
	$since = $end->modify( '-' . $days . ' days' )->format( 'Y-m-d' );
	$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT item, SUM(count) AS total FROM %i WHERE day >= %s AND day < %s AND kind = %s GROUP BY item ORDER BY total DESC, item ASC LIMIT %d', swiftdash_table( 'dims' ), $since, $end->format( 'Y-m-d' ), (string) $kind, max( 1, (int) $limit ) ),
		ARRAY_A
	);
	return array_map(
		function ( $row ) {
			return array(
				'item'  => (string) $row['item'],
				'count' => (int) $row['total'],
			);
		},
		(array) $rows
	);
}
