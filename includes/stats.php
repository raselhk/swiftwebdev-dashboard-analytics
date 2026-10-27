<?php
/**
 * Visitor stats read API (contract shared by dashboard + analytics widgets).
 * Data comes from the tables written by tracker.php.
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

/**
 * Visitor stats for the last $days full days including today.
 *
 * Period visitors are the sum of each day's unique visitors (no cross-day identity exists).
 *
 * @param int $days Number of days (1-30 in the free plugin).
 * @return array {
 *     @type bool     $has_data True once any visit was recorded.
 *     @type string   $since    First recorded day (Y-m-d) or ''.
 *     @type array    $totals   visitors, views, sessions for the period.
 *     @type array    $previous Same keys for the equal-length period before it.
 *     @type array[]  $daily    One row per day, oldest first: date (Y-m-d), visitors, views, sessions. Zero-filled.
 * }
 */
function swiftdash_get_stats( $days = 30 ) {
	global $wpdb;
	$days  = max( 1, min( 30, (int) $days ) );
	$zero  = array(
		'visitors' => 0,
		'views'    => 0,
		'sessions' => 0,
	);
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	$tkey  = $today->format( 'Y-m-d' );
	$first = $today->modify( '-' . ( 2 * $days - 1 ) . ' days' )->format( 'Y-m-d' );
	$hits  = swiftdash_table( 'hits' );
	$table = swiftdash_table( 'daily' );

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->prepare( 'SELECT day, visitors, views, sessions FROM %i WHERE day >= %s AND day < %s', $table, $first, $tkey ),
		ARRAY_A
	);
	$map = array();
	foreach ( (array) $rows as $row ) {
		$map[ $row['day'] ] = array(
			'visitors' => (int) $row['visitors'],
			'views'    => (int) $row['views'],
			'sessions' => (int) $row['sessions'],
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
