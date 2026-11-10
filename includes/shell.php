<?php
/**
 * App shell: top header, user menu, page header, icons (guideline section 3).
 * The left menu is the native WordPress admin menu (admin-menu.php).
 *
 * @package SwiftWebDevDashboard
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
 * Allowed tags for wp_kses() when printing icons and status pills.
 *
 * @return array[]
 */
function swiftdash_icon_kses() {
	return array(
		'svg'  => array(
			'class'       => true,
			'aria-hidden' => true,
			'focusable'   => true,
		),
		'use'  => array( 'href' => true ),
		'span' => array( 'class' => true ),
	);
}

/**
 * User menu rows: SwiftWebDev Dashboard screens placed in the user menu, settings last.
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
		'plugins'      => array( _n_noop( '%d plugin update available', '%d plugin updates available', 'swiftwebdev-dashboard-analytics' ), 'plugins.php?plugin_status=upgrade' ),
		/* translators: %d: number of theme updates. */
		'themes'       => array( _n_noop( '%d theme update available', '%d theme updates available', 'swiftwebdev-dashboard-analytics' ), 'themes.php' ),
		/* translators: %d: number of WordPress updates. */
		'wordpress'    => array( _n_noop( '%d WordPress update available', '%d WordPress updates available', 'swiftwebdev-dashboard-analytics' ), 'update-core.php' ),
		/* translators: %d: number of translation updates. */
		'translations' => array( _n_noop( '%d translation update available', '%d translation updates available', 'swiftwebdev-dashboard-analytics' ), 'update-core.php' ),
	);
	foreach ( $map as $key => $row ) {
		$count = isset( $data['counts'][ $key ] ) ? (int) $data['counts'][ $key ] : 0;
		if ( $count > 0 ) {
			$rows[] = array(
				'text' => sprintf( translate_nooped_plural( $row[0], $count, 'swiftwebdev-dashboard-analytics' ), $count ),
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
			<label class="screen-reader-text" for="sd-search-input"><?php esc_html_e( 'Search content, tools and pages', 'swiftwebdev-dashboard-analytics' ); ?></label>
			<?php echo wp_kses( swiftdash_icon( 'magnifying-glass', 'sd-search__icon' ), swiftdash_icon_kses() ); ?>
			<input type="search" id="sd-search-input" class="sd-input sd-search__input" placeholder="<?php esc_attr_e( 'Search posts, pages, users, tools...', 'swiftwebdev-dashboard-analytics' ); ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="sd-search-results" aria-autocomplete="list">
			<div class="sd-menu sd-search__panel" hidden>
				<ul class="sd-search__results" id="sd-search-results" role="listbox" aria-label="<?php esc_attr_e( 'Search results', 'swiftwebdev-dashboard-analytics' ); ?>"></ul>
				<p class="sd-search__foot" aria-hidden="true">
					<span><kbd>&uarr;</kbd><kbd>&darr;</kbd> <?php esc_html_e( 'to move', 'swiftwebdev-dashboard-analytics' ); ?></span>
					<span><kbd>Enter</kbd> <?php esc_html_e( 'to open', 'swiftwebdev-dashboard-analytics' ); ?></span>
					<span><kbd>Esc</kbd> <?php esc_html_e( 'to close', 'swiftwebdev-dashboard-analytics' ); ?></span>
				</p>
			</div>
			<p class="screen-reader-text" aria-live="polite" data-sd-search-status></p>
		</div>

		<div class="sd-header__actions">
			<div class="sd-dropdown">
				<button type="button" class="sd-icon-btn" data-sd-dropdown aria-expanded="false" aria-controls="sd-notes" aria-label="<?php echo esc_attr( $notes ? __( 'Notifications, updates available', 'swiftwebdev-dashboard-analytics' ) : __( 'Notifications', 'swiftwebdev-dashboard-analytics' ) ); ?>">
					<?php echo wp_kses( swiftdash_icon( 'bell' ), swiftdash_icon_kses() ); ?>
					<?php if ( $notes ) : ?>
						<span class="sd-icon-btn__dot" aria-hidden="true"></span>
					<?php endif; ?>
				</button>
				<div class="sd-menu sd-menu--right sd-notes" id="sd-notes" hidden>
					<p class="sd-menu__title"><?php esc_html_e( 'Notifications', 'swiftwebdev-dashboard-analytics' ); ?></p>
					<?php if ( $notes ) : ?>
						<?php foreach ( $notes as $note ) : ?>
							<a class="sd-menu__item" href="<?php echo esc_url( $note['url'] ); ?>"><?php echo wp_kses( swiftdash_icon( 'arrow-clockwise' ), swiftdash_icon_kses() ); ?><?php echo esc_html( $note['text'] ); ?></a>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="sd-menu__empty"><?php esc_html_e( 'Everything is up to date.', 'swiftwebdev-dashboard-analytics' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php
			global $plugin_page;
			$shortcuts = array(
				'swift-dash-speed' => array( __( 'Speed & Optimize', 'swiftwebdev-dashboard-analytics' ), 'gauge' ),
				'swift-dash-links' => array( __( 'Quick Links', 'swiftwebdev-dashboard-analytics' ), 'link-simple' ),
			);
			foreach ( $shortcuts as $slug => $shortcut ) :
				?>
				<a class="sd-icon-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" aria-label="<?php echo esc_attr( $shortcut[0] ); ?>" title="<?php echo esc_attr( $shortcut[0] ); ?>"<?php echo $plugin_page === $slug ? ' aria-current="page"' : ''; ?>>
					<?php echo wp_kses( swiftdash_icon( $shortcut[1] ), swiftdash_icon_kses() ); ?>
				</a>
			<?php endforeach; ?>

			<?php
			if ( swiftdash_can_update() ) :
				$updates = (int) wp_get_update_data()['counts']['total'];
				$label   = $updates
					/* translators: %d: number of available updates. */
					? sprintf( _n( 'Updates, %d available', 'Updates, %d available', $updates, 'swiftwebdev-dashboard-analytics' ), $updates )
					: __( 'Updates', 'swiftwebdev-dashboard-analytics' );
				?>
				<a class="sd-icon-btn sd-updates" href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>">
					<?php echo wp_kses( swiftdash_icon( 'arrow-clockwise' ), swiftdash_icon_kses() ); ?>
					<?php if ( $updates ) : ?>
						<span class="sd-updates__count" aria-hidden="true"><?php echo esc_html( number_format_i18n( $updates ) ); ?></span>
					<?php endif; ?>
				</a>
			<?php endif; ?>

			<a class="sd-icon-btn" href="<?php echo esc_url( SWIFTDASH_DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Help and documentation (opens in a new tab)', 'swiftwebdev-dashboard-analytics' ); ?>">
				<?php echo wp_kses( swiftdash_icon( 'question' ), swiftdash_icon_kses() ); ?>
			</a>

			<div class="sd-dropdown">
				<button type="button" class="sd-user" data-sd-dropdown aria-expanded="false" aria-controls="sd-user-menu">
					<span class="sd-user__avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
					<span class="sd-user__text">
						<span class="sd-user__name"><?php echo esc_html( $user->display_name ); ?></span>
						<span class="sd-user__role"><?php echo esc_html( swiftdash_role_label( $user ) ); ?></span>
					</span>
					<?php echo wp_kses( swiftdash_icon( 'caret-down', 'sd-user__caret' ), swiftdash_icon_kses() ); ?>
				</button>
				<div class="sd-menu sd-menu--right" id="sd-user-menu" hidden>
					<?php foreach ( swiftdash_user_menu_items() as $item ) : ?>
						<a class="sd-menu__item" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo wp_kses( swiftdash_icon( $item['icon'] ), swiftdash_icon_kses() ); ?><?php echo esc_html( $item['label'] ); ?></a>
					<?php endforeach; ?>
					<hr class="sd-menu__sep">
					<a class="sd-menu__item" href="<?php echo esc_url( get_edit_profile_url() ); ?>"><?php echo wp_kses( swiftdash_icon( 'user-circle' ), swiftdash_icon_kses() ); ?><?php esc_html_e( 'Your profile', 'swiftwebdev-dashboard-analytics' ); ?></a>
					<a class="sd-menu__item" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo wp_kses( swiftdash_icon( 'arrow-square-out' ), swiftdash_icon_kses() ); ?><?php esc_html_e( 'View site', 'swiftwebdev-dashboard-analytics' ); ?></a>
					<a class="sd-menu__item" href="<?php echo esc_url( add_query_arg( 'swiftdash', '0', admin_url( 'index.php' ) ) ); ?>"><?php echo wp_kses( swiftdash_icon( 'house' ), swiftdash_icon_kses() ); ?><?php esc_html_e( 'Classic WordPress dashboard', 'swiftwebdev-dashboard-analytics' ); ?></a>
					<a class="sd-menu__item" href="<?php echo esc_url( wp_logout_url() ); ?>"><?php echo wp_kses( swiftdash_icon( 'sign-out' ), swiftdash_icon_kses() ); ?><?php esc_html_e( 'Log out', 'swiftwebdev-dashboard-analytics' ); ?></a>
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
