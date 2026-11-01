<?php
/**
 * Left menu: one SwiftWebDev Dashboard item in the native
 * WordPress admin menu, plus its restyle (brand block, admin-menu.css) on every admin page
 * for permitted users.
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current user gets the SwiftWebDev Dashboard menu, restyle and redirect.
 *
 * @return bool
 */
function swiftdash_menu_enabled() {
	return is_admin() && current_user_can( swiftdash_capability() );
}

/**
 * Register the SwiftWebDev Dashboard menu at the default position (after the core items).
 * Screens with location 'left' become its submenu items (WordPress drops the submenu while
 * the dashboard is the only one); every other screen is a hidden page.
 */
function swiftdash_admin_menu() {
	$cap     = swiftdash_capability();
	$screens = swiftdash_screens();
	$render  = 'swiftdash_render_screen';

	swiftdash_screen_hooks( add_menu_page( __( 'SwiftWebDev Dashboard', 'swiftwebdev-dashboard-analytics' ), __( 'SwiftWebDev', 'swiftwebdev-dashboard-analytics' ), $cap, 'swift-dash', $render, 'dashicons-chart-area' ) );
	foreach ( $screens as $slug => $screen ) {
		$parent = 'left' === swiftdash_screen_location( $screen ) ? 'swift-dash' : '';
		swiftdash_screen_hooks( add_submenu_page( $parent, $screen['title'], $screen['menu'], $cap, $slug, $render ) );
	}
}
add_action( 'admin_menu', 'swiftdash_admin_menu' );

/**
 * Keep the SwiftWebDev menu item highlighted on every plugin screen, including the ones
 * reached from the user menu.
 *
 * @param string $parent_file Parent menu slug.
 * @return string
 */
function swiftdash_menu_parent_file( $parent_file ) {
	return swiftdash_is_screen() ? 'swift-dash' : $parent_file;
}
add_filter( 'parent_file', 'swiftdash_menu_parent_file' );

/**
 * Hidden screens are registered without a parent, so WordPress would reset the highlighted
 * parent to none while it prints the menu. Unlist them once access checks are done
 * (admin_head runs right before the menu); the pages stay registered and reachable.
 */
function swiftdash_menu_unlist_hidden() {
	global $submenu;
	if ( ! swiftdash_is_screen() || empty( $submenu[''] ) ) {
		return;
	}
	$slugs = array_keys( swiftdash_screens() );
	foreach ( $submenu[''] as $i => $item ) {
		if ( in_array( $item[2], $slugs, true ) ) {
			unset( $submenu[''][ $i ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- removes our own hidden entries only.
		}
	}
}
add_action( 'admin_head', 'swiftdash_menu_unlist_hidden' );

/**
 * Browser tab title from the screen title. Hidden pages (no parent menu) otherwise get a null
 * $title: an empty tab title plus a strip_tags() deprecation in admin-header.php.
 */
function swiftdash_admin_page_title() {
	global $title, $plugin_page;
	$screens = swiftdash_screens();
	if ( empty( $title ) && $plugin_page && isset( $screens[ $plugin_page ] ) && swiftdash_is_screen() ) {
		$title = $screens[ $plugin_page ]['title']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress reads it for the document title.
	}
}
add_action( 'current_screen', 'swiftdash_admin_page_title' );

/**
 * Menu restyle on every admin page for permitted users.
 */
function swiftdash_menu_assets() {
	if ( swiftdash_menu_enabled() ) {
		// Tokens carry the bundled font the menu uses; their variables stay scoped to .swiftdash.
		wp_enqueue_style( 'swiftdash-tokens', SWIFTDASH_URL . 'assets/css/tokens.css', array(), SWIFTDASH_VERSION );
		wp_enqueue_style( 'swiftdash-admin-menu', SWIFTDASH_URL . 'assets/css/admin-menu.css', array( 'colors', 'swiftdash-tokens' ), SWIFTDASH_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'swiftdash_menu_assets' );

/**
 * Brand block: the site's logo and name, placed first with CSS.
 */
function swiftdash_menu_extras() {
	if ( ! swiftdash_menu_enabled() ) {
		return;
	}
	$brand = swiftdash_brand();
	?>
	<li class="sd-menu-brand">
		<a href="<?php echo esc_url( admin_url() ); ?>">
			<img class="sd-menu-brand__logo" src="<?php echo esc_url( $brand['logo'] ); ?>" alt="" width="28" height="28">
			<span class="sd-menu-brand__name"><?php echo esc_html( $brand['name'] ); ?></span>
		</a>
	</li>
	<?php
}
add_action( 'adminmenu', 'swiftdash_menu_extras' );

/**
 * Admin URL of a menu item, the way WordPress links it.
 * ponytail: legacy menu items that point at a plugin .php file are linked as-is; they still load through admin.php.
 *
 * @param string $slug   Menu slug.
 * @param string $parent_slug Parent slug ('admin.php' for top-level items).
 * @return string
 */
function swiftdash_menu_url( $slug, $parent_slug ) {
	if ( ! get_plugin_page_hook( $slug, $parent_slug ) ) {
		return admin_url( $slug );
	}
	$base = strtok( $parent_slug, '?' );
	return admin_url( 'admin.php' !== $base && file_exists( ABSPATH . 'wp-admin/' . $base ) ? add_query_arg( 'page', $slug, $parent_slug ) : 'admin.php?page=' . $slug );
}

/**
 * Header search destinations: everything in the admin menu (already filtered by
 * capability) plus SwiftWebDev Dashboard screens that are not in the left menu.
 *
 * @return array[] Each: label, url.
 */
function swiftdash_destinations() {
	global $menu, $submenu;

	$out   = array();
	$clean = function ( $label ) {
		// Drop counter bubbles ("Updates 3" becomes "Updates").
		return trim( preg_replace( '/\s*\d+$/', '', wp_strip_all_tags( (string) $label ) ) );
	};
	$add   = function ( $label, $url ) use ( &$out ) {
		if ( '' !== $label && ! isset( $out[ $url ] ) ) {
			$out[ $url ] = array(
				'label' => $label,
				'url'   => $url,
			);
		}
	};

	foreach ( (array) $menu as $item ) {
		$top = $clean( $item[0] );
		if ( '' === $top ) {
			continue;
		}
		if ( empty( $submenu[ $item[2] ] ) ) {
			$add( $top, swiftdash_menu_url( $item[2], 'admin.php' ) );
			continue;
		}
		foreach ( $submenu[ $item[2] ] as $sub ) {
			$label = $clean( $sub[0] );
			$add( $label === $top ? $label : $top . ': ' . $label, swiftdash_menu_url( $sub[2], $item[2] ) );
		}
	}

	foreach ( swiftdash_screens() as $slug => $screen ) {
		$add( $screen['menu'], admin_url( 'admin.php?page=' . $slug ) );
	}
	if ( swiftdash_can_update() ) {
		$add( __( 'Updates', 'swiftwebdev-dashboard-analytics' ), admin_url( 'update-core.php' ) );
	}
	return array_values( $out );
}

/**
 * Whether the user may open the WordPress Updates screen.
 *
 * @return bool
 */
function swiftdash_can_update() {
	return current_user_can( 'update_core' ) || current_user_can( 'update_plugins' );
}
