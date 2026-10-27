<?php
/**
 * Left menu: Swift Dash items inside the native WordPress admin menu, plus its restyle
 * (brand block, upgrade card, admin-menu.css) on every admin page for permitted users.
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current user gets the Swift Dash menu, restyle and redirect.
 *
 * @return bool
 */
function swiftdash_menu_enabled() {
	return is_admin() && current_user_can( swiftdash_capability() );
}

/**
 * Analytics tabs shown as submenu items: key => label.
 *
 * @return string[]
 */
function swiftdash_menu_analytics_tabs() {
	if ( function_exists( 'swiftdash_analytics_tabs' ) ) {
		return wp_list_pluck( swiftdash_analytics_tabs(), 'label' );
	}
	return array(
		'overview' => __( 'Overview', 'swift-dashboard-analytics' ),
		'realtime' => __( 'Real-time', 'swift-dashboard-analytics' ),
		'audience' => __( 'Audience', 'swift-dashboard-analytics' ),
		'behavior' => __( 'Behavior', 'swift-dashboard-analytics' ),
		'pages'    => __( 'Pages', 'swift-dashboard-analytics' ),
		'forms'    => __( 'Forms', 'swift-dashboard-analytics' ),
		'clicks'   => __( 'Clicks', 'swift-dashboard-analytics' ),
		'sessions' => __( 'Session recordings', 'swift-dashboard-analytics' ),
		'bots'     => __( 'Bots', 'swift-dashboard-analytics' ),
		'events'   => __( 'Events', 'swift-dashboard-analytics' ),
	);
}

/**
 * Register Dashboard + Analytics in the left menu and every other screen as a hidden page.
 * Dashboard has no submenu (Updates lives in the Swift Dash header); core Dashboard
 * subpages stay reachable by URL because their submenu entries are left in place.
 */
function swiftdash_admin_menu() {
	$cap     = swiftdash_capability();
	$screens = swiftdash_screens();
	$render  = 'swiftdash_render_screen';

	// Only hide the core Dashboard item when the admin chose to open Swift Dash after login.
	if ( swiftdash_menu_enabled() && swiftdash_setting( 'open_after_login' ) ) {
		remove_menu_page( 'index.php' );
	}

	$dash = $screens['swift-dash'];
	swiftdash_screen_hooks( add_menu_page( $dash['title'], $dash['menu'], $cap, 'swift-dash', $render, 'dashicons-admin-home', 2 ) );

	$analytics = $screens['swift-dash-analytics'];
	swiftdash_screen_hooks( add_menu_page( $analytics['title'], $analytics['menu'], $cap, 'swift-dash-analytics', $render, 'dashicons-chart-area', 3 ) );
	$first = true;
	foreach ( swiftdash_menu_analytics_tabs() as $tab => $label ) {
		if ( $first ) {
			swiftdash_screen_hooks( add_submenu_page( 'swift-dash-analytics', $analytics['title'], $label, $cap, 'swift-dash-analytics', $render ) );
			$first = false;
			continue;
		}
		// No callback: WordPress links the slug as a plain URL.
		add_submenu_page( 'swift-dash-analytics', $label, $label, $cap, 'admin.php?page=swift-dash-analytics&tab=' . $tab );
	}

	foreach ( $screens as $slug => $screen ) {
		if ( 'swift-dash' !== $slug && 'swift-dash-analytics' !== $slug ) {
			swiftdash_screen_hooks( add_submenu_page( '', $screen['title'], $screen['menu'], $cap, $slug, $render ) );
		}
	}
}
add_action( 'admin_menu', 'swiftdash_admin_menu' );

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
 * Pages that belong to the core Dashboard menu highlight Swift Dash instead.
 *
 * @param string $parent_file Parent menu slug.
 * @return string
 */
function swiftdash_menu_parent_file( $parent_file ) {
	return 'index.php' === $parent_file && swiftdash_menu_enabled() ? 'swift-dash' : $parent_file;
}
add_filter( 'parent_file', 'swiftdash_menu_parent_file' );

/**
 * Highlight the current Analytics tab as the current submenu item.
 *
 * @param string|null $submenu_file Current submenu slug.
 * @return string|null
 */
function swiftdash_menu_submenu_file( $submenu_file ) {
	global $plugin_page;
	if ( 'swift-dash-analytics' !== $plugin_page ) {
		return $submenu_file;
	}
	$tabs = swiftdash_menu_analytics_tabs();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	if ( isset( $tabs[ $tab ] ) && array_key_first( $tabs ) !== $tab ) {
		return 'admin.php?page=swift-dash-analytics&tab=' . $tab;
	}
	return $submenu_file;
}
add_filter( 'submenu_file', 'swiftdash_menu_submenu_file' );

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
 * Brand block (placed first with CSS) and, on Swift Dash screens, the upgrade card.
 */
function swiftdash_menu_extras() {
	if ( ! swiftdash_menu_enabled() ) {
		return;
	}
	$brand = swiftdash_brand();
	$pro   = function_exists( 'swiftdash_pro_license_state' ) && 'active' === swiftdash_pro_license_state();
	?>
	<li class="sd-menu-brand">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=swift-dash' ) ); ?>">
			<img class="sd-menu-brand__logo" src="<?php echo esc_url( $brand['logo'] ); ?>" alt="" width="28" height="28">
			<span class="sd-menu-brand__name"><?php echo esc_html( $brand['name'] ); ?></span>
			<?php if ( $pro ) : ?>
				<span class="sd-menu-brand__pro"><?php esc_html_e( 'PRO', 'swift-dashboard-analytics' ); ?></span>
			<?php endif; ?>
		</a>
	</li>
	<?php
	if ( swiftdash_is_screen() && swiftdash_show_upgrade() ) {
		echo '<li class="swiftdash sd-menu-upgrade">';
		swiftdash_render_upgrade( 'sidebar' );
		echo '</li>';
	}
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
 * capability) plus Swift Dash screens that are not in the left menu.
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
		$add( __( 'Updates', 'swift-dashboard-analytics' ), admin_url( 'update-core.php' ) );
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
