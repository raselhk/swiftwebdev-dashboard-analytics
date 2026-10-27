<?php
/**
 * App shell: top header, user menu, page header, icons (guideline section 3).
 * The left menu is the native WordPress admin menu (admin-menu.php).
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG icon from the bundled sprite. Decorative: hidden from assistive tech.
 *
 * @param string $name  Icon name (file name in tools/icons without .svg).
 * @param string $extra_class Extra class.
 * @return string Escaped markup.
 */
function swiftdash_icon( $name, $extra_class = '' ) {
	return sprintf(
		'<svg class="sd-icon %1$s" aria-hidden="true" focusable="false"><use href="%2$s"></use></svg>',
		esc_attr( $extra_class ),
		esc_url( SWIFTDASH_URL . 'assets/icons.svg' ) . '#sd-i-' . esc_attr( sanitize_key( $name ) )
	);
}

/**
 * User menu rows: Swift Dash screens placed in the user menu, settings last.
 *
 * @return array[] Each: label, url, icon.
 */
function swiftdash_user_menu_items() {
	$items = array();
	$last  = array();
	foreach ( swiftdash_screens() as $slug => $screen ) {
		if ( 'user' !== swiftdash_screen_location( $screen ) ) {
			continue;
		}
		$row = array(
			'label' => $screen['menu'],
			'url'   => admin_url( 'admin.php?page=' . $slug ),
			'icon'  => isset( $screen['icon'] ) ? $screen['icon'] : 'squares-four-fill',
		);
		if ( 'swift-dash-settings' === $slug ) {
			$last[] = $row;
		} else {
			$items[] = $row;
		}
	}
	return array_merge( $items, $last );
}

/**
 * Pending updates as notification rows (real counts from core).
 *
 * @return array[] Each: text, url.
 */
function swiftdash_notifications() {
	$data = wp_get_update_data();
	$rows = array();
	$map  = array(
		/* translators: %d: number of plugin updates. */
		'plugins'      => array( _n_noop( '%d plugin update available', '%d plugin updates available', 'swift-dashboard-analytics' ), 'plugins.php?plugin_status=upgrade' ),
		/* translators: %d: number of theme updates. */
		'themes'       => array( _n_noop( '%d theme update available', '%d theme updates available', 'swift-dashboard-analytics' ), 'themes.php' ),
		/* translators: %d: number of WordPress updates. */
		'wordpress'    => array( _n_noop( '%d WordPress update available', '%d WordPress updates available', 'swift-dashboard-analytics' ), 'update-core.php' ),
		/* translators: %d: number of translation updates. */
		'translations' => array( _n_noop( '%d translation update available', '%d translation updates available', 'swift-dashboard-analytics' ), 'update-core.php' ),
	);
	foreach ( $map as $key => $row ) {
		$count = isset( $data['counts'][ $key ] ) ? (int) $data['counts'][ $key ] : 0;
		if ( $count > 0 ) {
			$rows[] = array(
				'text' => sprintf( translate_nooped_plural( $row[0], $count, 'swift-dashboard-analytics' ), $count ),
				'url'  => admin_url( $row[1] ),
			);
		}
	}
	return $rows;
}

/**
 * Print the top header.
 */
function swiftdash_render_header() {
	$user     = wp_get_current_user();
	$notes    = swiftdash_notifications();
	$initials = strtoupper( mb_substr( $user->display_name ? $user->display_name : $user->user_login, 0, 1 ) );
	?>
	<header class="sd-header">

		<div class="sd-search" role="search" data-sd-search>
			<label class="screen-reader-text" for="sd-search-input"><?php esc_html_e( 'Search content, tools and pages', 'swift-dashboard-analytics' ); ?></label>
			<?php echo swiftdash_icon( 'magnifying-glass', 'sd-search__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
			<input type="search" id="sd-search-input" class="sd-input sd-search__input" placeholder="<?php esc_attr_e( 'Search posts, pages, tools...', 'swift-dashboard-analytics' ); ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="sd-search-results" aria-autocomplete="list">
			<ul class="sd-menu sd-search__results" id="sd-search-results" role="listbox" hidden></ul>
		</div>

		<div class="sd-header__actions">
			<div class="sd-dropdown">
				<button type="button" class="sd-icon-btn" data-sd-dropdown aria-expanded="false" aria-controls="sd-notes" aria-label="<?php echo esc_attr( $notes ? __( 'Notifications, updates available', 'swift-dashboard-analytics' ) : __( 'Notifications', 'swift-dashboard-analytics' ) ); ?>">
					<?php echo swiftdash_icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
					<?php if ( $notes ) : ?>
						<span class="sd-icon-btn__dot" aria-hidden="true"></span>
					<?php endif; ?>
				</button>
				<div class="sd-menu sd-menu--right sd-notes" id="sd-notes" hidden>
					<p class="sd-menu__title"><?php esc_html_e( 'Notifications', 'swift-dashboard-analytics' ); ?></p>
					<?php if ( $notes ) : ?>
						<?php foreach ( $notes as $note ) : ?>
							<a class="sd-menu__item" href="<?php echo esc_url( $note['url'] ); ?>"><?php echo swiftdash_icon( 'arrow-clockwise' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?><?php echo esc_html( $note['text'] ); ?></a>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="sd-menu__empty"><?php esc_html_e( 'Everything is up to date.', 'swift-dashboard-analytics' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php
			global $plugin_page;
			$shortcuts = array(
				'swift-dash-speed' => array( __( 'Speed & Optimize', 'swift-dashboard-analytics' ), 'gauge' ),
				'swift-dash-links' => array( __( 'Quick Links', 'swift-dashboard-analytics' ), 'link-simple' ),
			);
			foreach ( $shortcuts as $slug => $shortcut ) :
				?>
				<a class="sd-icon-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" aria-label="<?php echo esc_attr( $shortcut[0] ); ?>" title="<?php echo esc_attr( $shortcut[0] ); ?>"<?php echo $plugin_page === $slug ? ' aria-current="page"' : ''; ?>>
					<?php echo swiftdash_icon( $shortcut[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
				</a>
			<?php endforeach; ?>

			<?php
			if ( swiftdash_can_update() ) :
				$updates = (int) wp_get_update_data()['counts']['total'];
				$label   = $updates
					/* translators: %d: number of available updates. */
					? sprintf( _n( 'Updates, %d available', 'Updates, %d available', $updates, 'swift-dashboard-analytics' ), $updates )
					: __( 'Updates', 'swift-dashboard-analytics' );
				?>
				<a class="sd-icon-btn sd-updates" href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>">
					<?php echo swiftdash_icon( 'arrow-clockwise' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
					<?php if ( $updates ) : ?>
						<span class="sd-updates__count" aria-hidden="true"><?php echo esc_html( number_format_i18n( $updates ) ); ?></span>
					<?php endif; ?>
				</a>
			<?php endif; ?>

			<a class="sd-icon-btn" href="<?php echo esc_url( SWIFTDASH_DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Help and documentation (opens in a new tab)', 'swift-dashboard-analytics' ); ?>">
				<?php echo swiftdash_icon( 'question' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
			</a>

			<div class="sd-dropdown">
				<button type="button" class="sd-user" data-sd-dropdown aria-expanded="false" aria-controls="sd-user-menu">
					<span class="sd-user__avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
					<span class="sd-user__text">
						<span class="sd-user__name"><?php echo esc_html( $user->display_name ); ?></span>
						<span class="sd-user__role"><?php echo esc_html( swiftdash_role_label( $user ) ); ?></span>
					</span>
					<?php echo swiftdash_icon( 'caret-down', 'sd-user__caret' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
				</button>
				<div class="sd-menu sd-menu--right" id="sd-user-menu" hidden>
					<?php foreach ( swiftdash_user_menu_items() as $item ) : ?>
						<a class="sd-menu__item" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo swiftdash_icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?><?php echo esc_html( $item['label'] ); ?></a>
					<?php endforeach; ?>
					<hr class="sd-menu__sep">
					<a class="sd-menu__item" href="<?php echo esc_url( get_edit_profile_url() ); ?>"><?php echo swiftdash_icon( 'user-circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?><?php esc_html_e( 'Your profile', 'swift-dashboard-analytics' ); ?></a>
					<a class="sd-menu__item" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo swiftdash_icon( 'arrow-square-out' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?><?php esc_html_e( 'View site', 'swift-dashboard-analytics' ); ?></a>
					<a class="sd-menu__item" href="<?php echo esc_url( add_query_arg( 'swiftdash', '0', admin_url( 'index.php' ) ) ); ?>"><?php echo swiftdash_icon( 'house' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?><?php esc_html_e( 'Classic WordPress dashboard', 'swift-dashboard-analytics' ); ?></a>
					<a class="sd-menu__item" href="<?php echo esc_url( wp_logout_url() ); ?>"><?php echo swiftdash_icon( 'sign-out' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?><?php esc_html_e( 'Log out', 'swift-dashboard-analytics' ); ?></a>
				</div>
			</div>
		</div>
	</header>
	<?php
}

/**
 * Display name of the user's first role.
 *
 * @param WP_User $user User.
 * @return string
 */
function swiftdash_role_label( $user ) {
	$roles = wp_roles()->get_names();
	$role  = $user->roles ? reset( $user->roles ) : '';
	return isset( $roles[ $role ] ) ? translate_user_role( $roles[ $role ] ) : '';
}

/**
 * Print the page header (guideline 3.3).
 *
 * @param string   $title       Page title.
 * @param string   $description One sentence.
 * @param callable $actions     Optional: prints the action buttons.
 */
function swiftdash_render_page_header( $title, $description, $actions = null ) {
	?>
	<div class="sd-page-head">
		<div class="sd-page-head__text">
			<h1 class="sd-page-head__title"><?php echo esc_html( $title ); ?></h1>
			<p class="sd-page-head__desc"><?php echo esc_html( $description ); ?></p>
		</div>
		<?php if ( is_callable( $actions ) ) : ?>
			<div class="sd-page-head__actions"><?php call_user_func( $actions ); ?></div>
		<?php endif; ?>
	</div>
	<hr class="wp-header-end">
	<?php
}
