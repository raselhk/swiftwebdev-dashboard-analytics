<?php
/**
 * Activity log: records site events (from activation onward) into {prefix}swiftdash_activity.
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

define( 'SWIFTDASH_ACTIVITY_KEEP', 200 );

/**
 * Activity table name.
 *
 * @return string
 */
function swiftdash_activity_table() {
	global $wpdb;
	return $wpdb->prefix . 'swiftdash_activity';
}

/**
 * Add the activity table to the install schema.
 *
 * @param string[] $schema  Statements.
 * @param string   $prefix  Table prefix including "swiftdash_".
 * @param string   $collate Charset clause.
 * @return string[]
 */
function swiftdash_activity_schema( $schema, $prefix, $collate ) {
	$schema[] = "CREATE TABLE {$prefix}activity (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created datetime NOT NULL,
  type varchar(32) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(255) NOT NULL DEFAULT '',
  detail varchar(255) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY created (created)
) $collate;";
	return $schema;
}
add_filter( 'swiftdash_schema', 'swiftdash_activity_schema', 10, 3 );

/**
 * Record one event and prune to the newest SWIFTDASH_ACTIVITY_KEEP rows.
 *
 * @param string $type      Event type key.
 * @param string $title     Short headline.
 * @param string $detail    Detail line.
 * @param int    $object_id Related object id.
 */
function swiftdash_log_activity( $type, $title, $detail = '', $object_id = 0 ) {
	global $wpdb;
	$table = swiftdash_activity_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own log table, write only.
	$ok = $wpdb->insert(
		$table,
		array(
			'created'   => gmdate( 'Y-m-d H:i:s' ),
			'type'      => substr( sanitize_key( $type ), 0, 32 ),
			'object_id' => absint( $object_id ),
			'title'     => mb_substr( wp_strip_all_tags( $title ), 0, 255 ),
			'detail'    => mb_substr( wp_strip_all_tags( $detail ), 0, 255 ),
			'user_id'   => get_current_user_id(),
		),
		array( '%s', '%s', '%d', '%s', '%s', '%d' )
	);
	if ( ! $ok ) {
		return;
	}

	// Prune: ids are sequential, so everything below the newest KEEP ids goes.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own log table.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id <= %d', $table, $wpdb->insert_id - SWIFTDASH_ACTIVITY_KEEP ) );
	wp_cache_delete( 'recent', 'swiftdash_activity' );
}

/**
 * Newest activity rows.
 *
 * @param int $limit Row count.
 * @return object[]
 */
function swiftdash_get_activity( $limit = 6 ) {
	global $wpdb;
	$rows = wp_cache_get( 'recent', 'swiftdash_activity' );
	if ( false === $rows ) {
		$table = swiftdash_activity_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own log table, cached above.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, 20 ) );
		wp_cache_set( 'recent', $rows, 'swiftdash_activity' );
	}
	return array_slice( $rows, 0, $limit );
}

/**
 * Post or page published.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post.
 */
function swiftdash_activity_on_publish( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status || wp_is_post_revision( $post ) ) {
		return;
	}
	$type = get_post_type_object( $post->post_type );
	if ( ! $type || ! $type->public || 'attachment' === $post->post_type ) {
		return;
	}
	if ( 'page' === $post->post_type ) {
		$title = __( 'Page published', 'swiftwebdev-dashboard-analytics' );
	} elseif ( 'post' === $post->post_type ) {
		$title = __( 'Post published', 'swiftwebdev-dashboard-analytics' );
	} else {
		/* translators: %s: post type singular name. */
		$title = sprintf( __( '%s published', 'swiftwebdev-dashboard-analytics' ), $type->labels->singular_name );
	}
	$name = get_the_title( $post );
	swiftdash_log_activity( 'publish', $title, '' !== $name ? $name : __( '(no title)', 'swiftwebdev-dashboard-analytics' ), $post->ID );
}
add_action( 'transition_post_status', 'swiftdash_activity_on_publish', 10, 3 );

/**
 * New user registered (display name only, never the email).
 *
 * @param int $user_id User id.
 */
function swiftdash_activity_on_user( $user_id ) {
	$user = get_userdata( $user_id );
	swiftdash_log_activity( 'user', __( 'New user registered', 'swiftwebdev-dashboard-analytics' ), $user ? $user->display_name : '', $user_id );
}
add_action( 'user_register', 'swiftdash_activity_on_user' );

/**
 * Plugin, theme or core updated.
 *
 * @param WP_Upgrader $upgrader Upgrader.
 * @param array       $extra    Hook extra data.
 */
function swiftdash_activity_on_upgrade( $upgrader, $extra ) {
	if ( empty( $extra['action'] ) || 'update' !== $extra['action'] || empty( $extra['type'] ) ) {
		return;
	}
	if ( 'core' === $extra['type'] ) {
		// The global $wp_version is still the old one in this request, so read the new file.
		$version = preg_match( "/wp_version = '([^']+)'/", (string) file_get_contents( ABSPATH . WPINC . '/version.php' ), $m ) ? $m[1] : '';
		swiftdash_log_activity( 'core', __( 'WordPress updated', 'swiftwebdev-dashboard-analytics' ), $version ? 'WordPress ' . $version : '' );
		return;
	}
	if ( 'plugin' === $extra['type'] ) {
		$items = isset( $extra['plugins'] ) ? (array) $extra['plugins'] : ( isset( $extra['plugin'] ) ? array( $extra['plugin'] ) : array() );
		foreach ( $items as $file ) {
			swiftdash_log_activity( 'plugin', __( 'Plugin updated', 'swiftwebdev-dashboard-analytics' ), swiftdash_activity_plugin_name( $file, true ) );
		}
		return;
	}
	if ( 'theme' === $extra['type'] ) {
		$items = isset( $extra['themes'] ) ? (array) $extra['themes'] : ( isset( $extra['theme'] ) ? array( $extra['theme'] ) : array() );
		foreach ( $items as $slug ) {
			$theme = wp_get_theme( $slug );
			$name  = $theme->exists() ? $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) : $slug;
			swiftdash_log_activity( 'theme', __( 'Theme updated', 'swiftwebdev-dashboard-analytics' ), $name );
		}
	}
}
add_action( 'upgrader_process_complete', 'swiftdash_activity_on_upgrade', 10, 2 );

/**
 * Plugin name from its main file.
 *
 * @param string $file         Plugin basename.
 * @param bool   $with_version Append the version.
 * @return string
 */
function swiftdash_activity_plugin_name( $file, $with_version = false ) {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$path = WP_PLUGIN_DIR . '/' . $file;
	$data = file_exists( $path ) ? get_plugin_data( $path, false, false ) : array();
	$name = ! empty( $data['Name'] ) ? $data['Name'] : $file;
	return $with_version && ! empty( $data['Version'] ) ? $name . ' ' . $data['Version'] : $name;
}

/**
 * Plugin activated.
 *
 * @param string $file Plugin basename.
 */
function swiftdash_activity_on_activate( $file ) {
	swiftdash_log_activity( 'plugin', __( 'Plugin activated', 'swiftwebdev-dashboard-analytics' ), swiftdash_activity_plugin_name( $file ) );
}
add_action( 'activated_plugin', 'swiftdash_activity_on_activate' );

/**
 * Plugin deactivated.
 *
 * @param string $file Plugin basename.
 */
function swiftdash_activity_on_deactivate( $file ) {
	swiftdash_log_activity( 'plugin-off', __( 'Plugin deactivated', 'swiftwebdev-dashboard-analytics' ), swiftdash_activity_plugin_name( $file ) );
}
add_action( 'deactivated_plugin', 'swiftdash_activity_on_deactivate' );

/**
 * WooCommerce order received.
 *
 * @param int $order_id Order id.
 */
function swiftdash_activity_on_order( $order_id ) {
	$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
	if ( ! $order ) {
		return;
	}
	$total = html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
	swiftdash_log_activity( 'order', __( 'New order received', 'swiftwebdev-dashboard-analytics' ), '#' . $order->get_order_number() . ', ' . $total, $order_id );
}
add_action( 'woocommerce_new_order', 'swiftdash_activity_on_order' );
