<?php
/**
 * Optional "this site uses the plugin" ping. Off by default: nothing is sent until an administrator
 * turns on "Tell Swift Web Dev this site uses the plugin". When on, the site address and the plugin
 * version go to the Swift Web Dev API once a week. Turning it off, or deactivating the plugin,
 * asks the API to remove the site.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Send the ping (POST) or the removal (DELETE). Never blocks the page and never retries.
 *
 * @param string $method POST or DELETE.
 * @return void
 */
function swiftdash_usage_request( $method ) {
	wp_remote_request(
		untrailingslashit( SWIFTDASH_API ) . '/installs',
		array(
			'method'   => $method,
			'timeout'  => 5,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'site_url' => home_url( '/' ),
					'version'  => SWIFTDASH_VERSION,
					'product'  => 'swiftdash',
				)
			),
		)
	);
}

/**
 * Keep the weekly event in line with the setting. The first event is due at once, so the site
 * shows up soon after the switch is turned on.
 *
 * @return void
 */
function swiftdash_usage_sync() {
	$on   = swiftdash_setting( 'share_usage' );
	$next = wp_next_scheduled( 'swiftdash_usage_ping' );
	if ( $on && ! $next ) {
		wp_schedule_event( time(), 'weekly', 'swiftdash_usage_ping' );
	} elseif ( ! $on && $next ) {
		wp_clear_scheduled_hook( 'swiftdash_usage_ping' );
		swiftdash_usage_request( 'DELETE' );
	}
}
add_action( 'admin_init', 'swiftdash_usage_sync' );

/**
 * Weekly event: ping while the setting is on.
 *
 * @return void
 */
function swiftdash_usage_ping() {
	if ( swiftdash_setting( 'share_usage' ) ) {
		swiftdash_usage_request( 'POST' );
	} else {
		wp_clear_scheduled_hook( 'swiftdash_usage_ping' );
	}
}
add_action( 'swiftdash_usage_ping', 'swiftdash_usage_ping' );

/**
 * Plugin deactivated: stop the event and, if this site was listed, ask for its removal.
 *
 * @return void
 */
function swiftdash_usage_deactivate() {
	if ( wp_next_scheduled( 'swiftdash_usage_ping' ) ) {
		wp_clear_scheduled_hook( 'swiftdash_usage_ping' );
		swiftdash_usage_request( 'DELETE' );
	}
}
register_deactivation_hook( SWIFTDASH_DIR . 'swiftwebdev-dashboard-analytics.php', 'swiftdash_usage_deactivate' );
