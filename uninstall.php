<?php
/**
 * Remove everything Swift Dash stored when the plugin is deleted.
 *
 * @package SwiftDash
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete Swift Dash data for the current site.
 */
function swiftdash_uninstall_site() {
	global $wpdb;

	foreach ( array( 'hits', 'daily', 'activity' ) as $table ) {
		$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $wpdb->prefix . 'swiftdash_' . $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- table names cannot be placeholders; prefix is trusted.
	}

	foreach ( array( 'swiftdash_settings', 'swiftdash_db_version', 'swiftdash_salt', 'swiftdash_speed_mobile', 'swiftdash_speed_desktop', 'swiftdash_speed_custom' ) as $option ) {
		delete_option( $option );
	}

	wp_clear_scheduled_hook( 'swiftdash_rollup' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $swiftdash_site_id ) {
		switch_to_blog( $swiftdash_site_id );
		swiftdash_uninstall_site();
		restore_current_blog();
	}
} else {
	swiftdash_uninstall_site();
}

// User meta is network-wide.
delete_metadata( 'user', 0, 'swiftdash_prefs', '', true );
delete_metadata( 'user', 0, 'swiftdash_links', '', true );
delete_metadata( 'user', 0, 'swiftdash_layout', '', true );
