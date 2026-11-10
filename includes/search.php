<?php
/**
 * Header search: commands (Quick Links + admin menu) shown instantly, and a REST route
 * that finds content in every status the user can edit, plus users.
 *
 * @package SwiftWebDev_Dashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Search commands for the current user: Quick Links first (they carry a description,
 * icon and tone), then our screens, then every other admin menu destination.
 *
 * Items with suggest = true are offered as quick actions before the user types.
 *
 * @param array[]|null $destinations Admin menu destinations; null reads the current admin menu.
 * @return array[] Each: label, hint, icon, tone, url, suggest.
 */
function swiftdash_search_commands( $destinations = null ) {
	$suggest = array( 'add-post', 'add-page', 'upload-media', 'add-product', 'swift-dash-speed' );
	$out     = array();
	$add     = function ( $label, $hint, $icon, $tone, $url, $key = '' ) use ( &$out, $suggest ) {
		if ( '' !== $label && ! isset( $out[ $url ] ) ) {
			$out[ $url ] = array(
				'label'   => $label,
				'hint'    => $hint,
				'icon'    => $icon,
				'tone'    => $tone,
				'url'     => $url,
				'suggest' => in_array( $key, $suggest, true ),
			);
		}
	};

	foreach ( swiftdash_quick_links() as $link ) {
		$add( $link['label'], $link['description'], $link['icon'], $link['tone'], $link['url'], $link['id'] );
	}
	foreach ( swiftdash_screens() as $slug => $screen ) {
		$add( $screen['menu'], $screen['description'], $screen['icon'], 'blue', admin_url( 'admin.php?page=' . $slug ), $slug );
	}
	foreach ( null === $destinations ? swiftdash_destinations() : $destinations as $dest ) {
		$add( $dest['label'], '', $dest['icon'], 'neutral', $dest['url'] );
	}
	return array_values( $out );
}

/**
 * Register the search script shared by the dashboard header and the toolbar.
 */
function swiftdash_register_search_script() {
	wp_register_script( 'swiftdash-search', SWIFTDASH_URL . 'assets/js/search.js', array( 'wp-api-fetch', 'wp-i18n' ), SWIFTDASH_VERSION, true );
	wp_set_script_translations( 'swiftdash-search', 'swiftwebdev-dashboard-analytics' );
}
add_action( 'init', 'swiftdash_register_search_script' );

/**
 * Icon for a top-level admin menu slug. Unknown menus (other plugins) get a puzzle piece.
 *
 * @param string $slug Menu slug.
 * @return string Icon name in assets/icons.svg.
 */
function swiftdash_menu_icon( $slug ) {
	$icons = array(
		'index.php'                  => 'house',
		'edit.php'                   => 'note-fill',
		'upload.php'                 => 'images-fill',
		'edit.php?post_type=page'    => 'files-fill',
		'edit-comments.php'          => 'chat-circle-text-fill',
		'themes.php'                 => 'paint-brush-fill',
		'plugins.php'                => 'plug-fill',
		'users.php'                  => 'users-fill',
		'profile.php'                => 'user-circle-fill',
		'tools.php'                  => 'wrench-fill',
		'options-general.php'        => 'gear-six-fill',
		'woocommerce'                => 'shopping-cart-fill',
		'edit.php?post_type=product' => 'tag-fill',
		'swift-dash'                 => 'chart-bar-fill',
	);
	return isset( $icons[ $slug ] ) ? $icons[ $slug ] : 'puzzle-piece-fill';
}

/**
 * Icon for a post type in search results.
 *
 * @param string $type Post type.
 * @return string
 */
function swiftdash_post_type_icon( $type ) {
	$icons = array(
		'post'       => 'note-fill',
		'page'       => 'file-text-fill',
		'attachment' => 'image-fill',
		'product'    => 'tag-fill',
		'wp_block'   => 'shapes-fill',
	);
	return isset( $icons[ $type ] ) ? $icons[ $type ] : 'files-fill';
}

/**
 * Register GET swiftdash/v1/search.
 */
function swiftdash_search_routes() {
	register_rest_route(
		'swiftdash/v1',
		'/search',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => function () {
				return current_user_can( swiftdash_capability() );
			},
			'callback'            => 'swiftdash_search_rest',
			'args'                => array(
				'q' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'swiftdash_search_routes' );

/**
 * GET /search?q=: up to 8 content items in any status but trash (drafts included) that the
 * user can edit, and up to 3 users when the user may list them.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function swiftdash_search_rest( $request ) {
	$q   = trim( (string) $request['q'] );
	$out = array(
		'content' => array(),
		'users'   => array(),
	);
	if ( mb_strlen( $q ) < 2 ) {
		return rest_ensure_response( $out );
	}

	$types = get_post_types( array( 'show_ui' => true ), 'objects' );
	unset( $types['wp_navigation'], $types['wp_template'], $types['wp_template_part'], $types['wp_global_styles'], $types['shop_order'], $types['shop_order_placehold'] );

	$query = new WP_Query(
		array(
			's'                      => $q,
			'post_type'              => array_keys( $types ),
			'post_status'            => 'any', // Every status except trash and auto-draft; includes inherit for media.
			'posts_per_page'         => 8,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $query->posts as $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			continue;
		}
		$status = in_array( $post->post_status, array( 'publish', 'inherit' ), true ) ? '' : get_post_status_object( $post->post_status );
		$item   = array(
			'label'  => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'swiftwebdev-dashboard-analytics' ),
			'hint'   => $types[ $post->post_type ]->labels->singular_name,
			'status' => $status ? $status->label : '',
			'icon'   => swiftdash_post_type_icon( $post->post_type ),
			'url'    => (string) get_edit_post_link( $post->ID, 'raw' ),
		);
		if ( wp_attachment_is_image( $post ) ) {
			$item['thumb'] = (string) wp_get_attachment_image_url( $post->ID, 'thumbnail' );
		}
		$out['content'][] = $item;
	}

	if ( current_user_can( 'list_users' ) ) {
		$users = get_users(
			array(
				'search'         => '*' . $q . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
				'number'         => 3,
				'fields'         => array( 'ID', 'display_name', 'user_email' ),
			)
		);
		foreach ( $users as $user ) {
			if ( current_user_can( 'edit_user', $user->ID ) ) {
				$out['users'][] = array(
					'label' => $user->display_name,
					'hint'  => $user->user_email,
					'icon'  => 'user-circle-fill',
					'url'   => get_edit_user_link( $user->ID ),
				);
			}
		}
	}

	return rest_ensure_response( $out );
}
