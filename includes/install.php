<?php
/**
 * Database tables. Feature files add their CREATE TABLE statements through the
 * swiftdash_schema filter; tables are created or updated on activation and whenever
 * SWIFTDASH_DB_VERSION changes (dbDelta is safe to re-run).
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

define( 'SWIFTDASH_DB_VERSION', '3' );

/**
 * Create or update all SwiftWebDev Dashboard tables.
 */
function swiftdash_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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
