<?php
/**
 * Database tables. Feature files add their CREATE TABLE statements through the
 * swiftdash_schema filter; tables are created or updated on activation and whenever
 * SWIFTDASH_DB_VERSION changes (dbDelta is safe to re-run).
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

define( 'SWIFTDASH_DB_VERSION', '4' );

/**
 * Create or update all SwiftWebDev Dashboard tables.
 */
function swiftdash_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$fresh = false === get_option( 'swiftdash_db_version' ); // Never installed before (not an update or reactivation).

	/**
	 * Filters the CREATE TABLE statements (dbDelta format, one per array item).
	 *
	 * @param string[] $schema          Statements.
	 * @param string   $prefix          Table prefix including "swiftdash_", e.g. wp_swiftdash_.
	 * @param string   $charset_collate Charset clause to append.
	 */
	$schema = apply_filters( 'swiftdash_schema', array(), $wpdb->prefix . 'swiftdash_', $wpdb->get_charset_collate() );
	foreach ( $schema as $sql ) {
		dbDelta( $sql );
	}

	// Version 3 stopped storing the page path of each visit (dbDelta never drops columns).
	$hits = $wpdb->prefix . 'swiftdash_hits';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $hits, 'path' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN path', $hits ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
	update_option( 'swiftdash_db_version', SWIFTDASH_DB_VERSION, false );

	// Install date (review notice timing). Sites updating from 1.0.0 use their oldest daily total.
	if ( ! get_option( 'swiftdash_installed' ) ) {
		$oldest = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(day) FROM %i', $wpdb->prefix . 'swiftdash_daily' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$since  = $oldest ? strtotime( $oldest . ' 00:00:00' ) : false;
		update_option( 'swiftdash_installed', $since ? $since : time(), false );
		if ( $fresh ) {
			// Brand-new install: onboarding.php sends the activating admin to the welcome screen once.
			update_option( 'swiftdash_first_run', 1, false );
		}
	}

	/**
	 * Fires after tables are installed (schedule cron events here).
	 */
	do_action( 'swiftdash_installed' );
}

/**
 * Run the installer when the stored DB version is out of date.
 */
function swiftdash_maybe_install() {
	if ( get_option( 'swiftdash_db_version' ) !== SWIFTDASH_DB_VERSION ) {
		swiftdash_install();
	}
}
add_action( 'plugins_loaded', 'swiftdash_maybe_install', 20 );
register_activation_hook( SWIFTDASH_FILE, 'swiftdash_install' );
