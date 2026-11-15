<?php
/**
 * Search, SEO, social and uptime crawlers: counted on the server by user agent, next to the AI
 * bots (detection in swiftdash_ai_bot_detect()), as one daily total per crawler in the
 * swiftdash_dims table (kind "crawler"). They are never counted as visitors.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crawlers counted on the Bots & automated visits card. AI bots counted by swiftdash_ai_bots()
 * are not listed here: they are matched first and counted as AI crawlers.
 *
 * @return array[] key => { label: name as shown, token: user agent substring (any case) }.
 */
function swiftdash_crawlers() {
	$bots = array(
		'googlebot'           => array( 'Googlebot', 'Googlebot' ),
		'bingbot'             => array( 'Bingbot', 'bingbot' ),
		'yandexbot'           => array( 'YandexBot', 'YandexBot' ),
		'baiduspider'         => array( 'Baiduspider', 'Baiduspider' ),
		'duckduckbot'         => array( 'DuckDuckBot', 'DuckDuckBot' ),
		'seznambot'           => array( 'SeznamBot', 'SeznamBot' ),
		'petalbot'            => array( 'PetalBot', 'PetalBot' ),
		'ahrefsbot'           => array( 'AhrefsBot', 'AhrefsBot' ),
		'semrushbot'          => array( 'SemrushBot', 'SemrushBot' ),
		'mj12bot'             => array( 'MJ12bot', 'MJ12bot' ),
		'dotbot'              => array( 'DotBot', 'DotBot' ),
		'facebookexternalhit' => array( 'Facebook', 'facebookexternalhit' ),
		'twitterbot'          => array( 'Twitterbot', 'Twitterbot' ),
		'linkedinbot'         => array( 'LinkedInBot', 'LinkedInBot' ),
		'pinterestbot'        => array( 'Pinterestbot', 'Pinterestbot' ),
		'uptimerobot'         => array( 'UptimeRobot', 'UptimeRobot' ),
		'pingdom'             => array( 'Pingdom', 'Pingdom' ),
		'statuscake'          => array( 'StatusCake', 'StatusCake' ),
		'site24x7'            => array( 'Site24x7', 'Site24x7' ),
		'betteruptime'        => array( 'Better Stack', 'Better Uptime Bot' ),
		'jetmon'              => array( 'Jetpack Monitor', 'jetmon' ),
	);
	foreach ( $bots as $key => $bot ) {
		$bots[ $key ] = array_combine( array( 'label', 'token' ), $bot );
	}
	/**
	 * Filters the search, SEO, social and uptime crawlers counted on the server.
	 *
	 * @param array[] $bots key => { label, token }.
	 */
	return (array) apply_filters( 'swiftdash_crawlers', $bots );
}

/**
 * Add one request to today's total for a crawler.
 *
 * @param string $key Crawler key.
 */
function swiftdash_crawler_record( $key ) {
	global $wpdb;
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'INSERT INTO %i (day, kind, item, count) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE count = count + 1', swiftdash_table( 'dims' ), wp_date( 'Y-m-d' ), 'crawler', $key )
	);
}
