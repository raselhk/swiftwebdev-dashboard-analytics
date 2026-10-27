<?php
/**
 * Database tables. Feature files add their CREATE TABLE statements through the
 * swiftdash_schema filter; tables are created or updated on activation and whenever
 * SWIFTDASH_DB_VERSION changes (dbDelta is safe to re-run).
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

define( 'SWIFTDASH_DB_VERSION', '2' );

/**
 * Create or update all Swift Dash tables.
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
