<?php
/**
 * SwiftWebDev Dashboard screens: definitions, assets, screen bodies. The left menu lives in admin-menu.php.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Screen definitions keyed by admin page slug.
 *
 * Location: 'left' (WordPress left menu), 'user' (top-right user menu) or 'hidden' (URL only).
 * Older definitions with 'nav' => true are read as 'user', see swiftdash_screen_location().
 *
 * @return array[]
 */
function swiftdash_screens() {
	$screens = array(
		'swift-dash'          => array(
			'key'         => 'dashboard',
			'menu'        => __( 'Dashboard', 'swiftwebdev-dashboard-analytics' ),
			'title'       => __( 'Dashboard', 'swiftwebdev-dashboard-analytics' ),
			'description' => __( 'Here is what is happening on your website today.', 'swiftwebdev-dashboard-analytics' ),
			'icon'        => 'house',
			'location'    => 'left',
		),
		'swift-dash-speed'    => array(
			'key'         => 'speed',
			'menu'        => __( 'Speed & Optimize', 'swiftwebdev-dashboard-analytics' ),
			'title'       => __( 'Speed & Optimize', 'swiftwebdev-dashboard-analytics' ),
			'description' => __( 'See your mobile and desktop speed scores and what to fix to reach 90+.', 'swiftwebdev-dashboard-analytics' ),
			'icon'        => 'gauge',
			'location'    => 'user',
		),
		'swift-dash-links'    => array(
			'key'         => 'links',
			'menu'        => __( 'Quick Links', 'swiftwebdev-dashboard-analytics' ),
			'title'       => __( 'Quick Links', 'swiftwebdev-dashboard-analytics' ),
			'description' => __( 'Shortcuts to the WordPress tasks you use most.', 'swiftwebdev-dashboard-analytics' ),
			'icon'        => 'link-simple',
			'location'    => 'user',
		),
		'swift-dash-settings' => array(
			'key'         => 'settings',
			'menu'        => __( 'SwiftWebDev Dashboard settings', 'swiftwebdev-dashboard-analytics' ),
			'title'       => __( 'SwiftWebDev Dashboard Settings', 'swiftwebdev-dashboard-analytics' ),
			'description' => __( 'Choose how SwiftWebDev Dashboard behaves on this site.', 'swiftwebdev-dashboard-analytics' ),
			'icon'        => 'gear-six',
			'location'    => 'user',
		),
	);

	/**
	 * Filters the SwiftWebDev Dashboard screens.
	 *
	 * @param array $screens Screens keyed by admin page slug.
	 */
	return apply_filters( 'swiftdash_screens', $screens );
}

/**
 * Hook suffixes of SwiftWebDev Dashboard screens.
 *
 * @param string|null $add Hook suffix to record.
 * @return string[]
 */
function swiftdash_screen_hooks( $add = null ) {
	static $hooks = array();
	if ( $add ) {
		$hooks[] = $add;
	}
	return $hooks;
}

/**
 * Where a screen is linked from: 'left', 'user' or 'hidden'.
 *
 * @param array $screen Screen definition.
 * @return string
 */
function swiftdash_screen_location( $screen ) {
	if ( isset( $screen['location'] ) && in_array( $screen['location'], array( 'left', 'user', 'hidden' ), true ) ) {
		return $screen['location'];
	}
	return empty( $screen['nav'] ) ? 'hidden' : 'user';
}

/**
 * Whether the current admin request is a SwiftWebDev Dashboard screen.
 *
 * @return bool
 */
function swiftdash_is_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && in_array( $screen->id, swiftdash_screen_hooks(), true );
}

/**
 * Body class that switches off the WordPress admin chrome on SwiftWebDev Dashboard screens only.
 *
 * @param string $classes Space-separated classes.
 * @return string
 */
function swiftdash_admin_body_class( $classes ) {
	return swiftdash_is_screen() ? $classes . ' swiftdash-screen' : $classes;
}
add_filter( 'admin_body_class', 'swiftdash_admin_body_class' );

/**
 * Enqueue assets on SwiftWebDev Dashboard screens only.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function swiftdash_enqueue_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, swiftdash_screen_hooks(), true ) ) {
		return;
	}

	wp_enqueue_style( 'swiftdash-tokens', SWIFTDASH_URL . 'assets/css/tokens.css', array(), SWIFTDASH_VERSION );
	wp_enqueue_style( 'swiftdash-app', SWIFTDASH_URL . 'assets/css/app.css', array( 'swiftdash-tokens' ), SWIFTDASH_VERSION );

	wp_enqueue_script( 'swiftdash-app', SWIFTDASH_URL . 'assets/js/app.js', array( 'wp-api-fetch', 'wp-i18n', 'swiftdash-search' ), SWIFTDASH_VERSION, true );
	wp_set_script_translations( 'swiftdash-app', 'swiftwebdev-dashboard-analytics' );

	wp_add_inline_script(
		'swiftdash-app',
		'window.swiftdashData = ' . wp_json_encode(
			array(
				'prefs'    => swiftdash_prefs(),
				'commands' => swiftdash_search_commands(),
				'userId'   => get_current_user_id(),
				'adminUrl' => admin_url(),
			)
		) . ';',
		'before'
	);

	global $plugin_page;
	$screens = swiftdash_screens();
	if ( 'swift-dash-settings' === $plugin_page ) {
		wp_enqueue_media();
	}

	/**
	 * Fires after the core SwiftWebDev Dashboard assets are enqueued on a SwiftWebDev Dashboard screen.
	 * Feature files enqueue their own CSS/JS here (depend on the 'swiftdash-app' script and style).
	 *
	 * @param string $screen_key Current screen key (dashboard, analytics, speed, links, settings).
	 */
	do_action( 'swiftdash_enqueue_assets', isset( $screens[ $plugin_page ] ) ? $screens[ $plugin_page ]['key'] : '' );
}
add_action( 'admin_enqueue_scripts', 'swiftdash_enqueue_assets' );

/**
 * Optional: open SwiftWebDev Dashboard instead of the WordPress dashboard.
 */
function swiftdash_maybe_redirect_dashboard() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only opt-out flag on a GET link.
	$bypass = isset( $_GET['swiftdash'] ) && '0' === $_GET['swiftdash'];
	if ( ! $bypass && swiftdash_setting( 'open_after_login' ) && current_user_can( swiftdash_capability() ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=swift-dash' ) );
		exit;
	}
}
add_action( 'load-index.php', 'swiftdash_maybe_redirect_dashboard' );

/**
 * Register the widgets of every screen. Feature files and add-ons register or replace widgets here.
 */
function swiftdash_setup_widgets() {
	/**
	 * Fires before a screen renders. Register or replace widgets with swiftdash_register_widget().
	 */
	do_action( 'swiftdash_register_widgets' );
}

/**
 * Render any SwiftWebDev Dashboard screen inside the shared shell.
 */
function swiftdash_render_screen() {
	global $plugin_page;

	$screens = swiftdash_screens();
	if ( ! isset( $screens[ $plugin_page ] ) || ! current_user_can( swiftdash_capability() ) ) {
		return;
	}
	$screen = $screens[ $plugin_page ];
	swiftdash_setup_widgets();

	?>
	<div class="swiftdash sd-app">
		<a class="sd-skip" href="#sd-content"><?php esc_html_e( 'Skip to main content', 'swiftwebdev-dashboard-analytics' ); ?></a>
		<div class="sd-main">
			<?php swiftdash_render_header(); ?>
			<main class="sd-content" id="sd-content" tabindex="-1">
				<div class="wrap sd-wrap">
					<?php
					$body = 'swiftdash_screen_' . $screen['key'];
					if ( function_exists( $body ) ) {
						call_user_func( $body, $screen );
					} elseif ( ! has_action( 'swiftdash_screen_' . $screen['key'] ) ) {
						swiftdash_screen_pending( $screen );
					} else {
						/**
						 * Prints the body of a SwiftWebDev Dashboard screen added through the swiftdash_screens filter.
						 *
						 * @param array $screen Screen definition.
						 */
						do_action( 'swiftdash_screen_' . $screen['key'], $screen );
					}
					?>
				</div>
			</main>
		</div>
		<div class="sd-toasts" aria-live="polite" aria-atomic="false"></div>
		<?php swiftdash_render_confirm_dialog(); ?>
	</div>
	<?php
}

/**
 * Dashboard screen.
 *
 * @param array $screen Screen definition.
 */
function swiftdash_screen_dashboard( $screen ) {
	$user = wp_get_current_user();
	$name = $user->first_name ? $user->first_name : $user->display_name;

	swiftdash_render_page_header(
		/* translators: %s: user's first name. */
		sprintf( __( 'Welcome back, %s', 'swiftwebdev-dashboard-analytics' ), $name ),
		$screen['description'],
		function () {
			swiftdash_render_range_picker();
			?>
			<button type="button" class="sd-btn sd-btn--secondary" data-sd-modal-open="sd-customize">
				<?php echo wp_kses( swiftdash_icon( 'eye-slash' ), swiftdash_icon_kses() ); ?>
				<?php esc_html_e( 'Customize', 'swiftwebdev-dashboard-analytics' ); ?>
			</button>
			<a class="sd-btn sd-btn--secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View site', 'swiftwebdev-dashboard-analytics' ); ?>
				<?php echo wp_kses( swiftdash_icon( 'arrow-square-out' ), swiftdash_icon_kses() ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'swiftwebdev-dashboard-analytics' ); ?></span>
			</a>
			<?php
		}
	);

	swiftdash_render_screen_widgets( 'dashboard' );
	swiftdash_render_customize_panel( 'dashboard' );
}

/**
 * Widget areas of a screen, top to bottom.
 *
 * @param string $screen_key Screen key.
 * @return string[]
 */
function swiftdash_screen_areas( $screen_key ) {
	$areas = array(
		'dashboard' => array( 'kpis', 'overview', 'performance', 'site', 'insights', 'ai' ),
	);

	/**
	 * Filters the widget areas of each screen, top to bottom.
	 *
	 * @param array $areas Area keys per screen key.
	 */
	$areas = apply_filters( 'swiftdash_screen_areas', $areas );
	return isset( $areas[ $screen_key ] ) ? $areas[ $screen_key ] : array( 'main' );
}

/**
 * All widgets of a screen in display order (area by area).
 *
 * @param string $screen_key Screen key.
 * @return array[]
 */
function swiftdash_get_screen_widgets( $screen_key ) {
	$widgets = array();
	foreach ( swiftdash_screen_areas( $screen_key ) as $area ) {
		$widgets = array_merge( $widgets, array_values( swiftdash_get_widgets( $screen_key, $area ) ) );
	}
	return $widgets;
}

/**
 * Screens whose content arrives in a later build phase.
 *
 * @param array $screen Screen definition.
 */
function swiftdash_screen_pending( $screen ) {
	swiftdash_render_page_header( $screen['title'], $screen['description'] );
	swiftdash_render_empty_state(
		'clock',
		__( 'This screen is not ready yet', 'swiftwebdev-dashboard-analytics' ),
		__( 'It will be added in an upcoming version of SwiftWebDev Dashboard.', 'swiftwebdev-dashboard-analytics' )
	);
}

/**
 * Settings screen. Saves through core /wp/v2/settings.
 *
 * @param array $screen Screen definition.
 */
function swiftdash_screen_settings( $screen ) {
	$brand   = swiftdash_brand();
	$last    = (int) get_option( 'swiftdash_report_last', 0 );
	$weekly  = swiftdash_setting( 'weekly_report' );
	$help_id = $last ? 'sd-weekly-report-help sd-weekly-report-last' : 'sd-weekly-report-help';
	swiftdash_render_page_header( $screen['title'], $screen['description'] );
	$welcome = swiftdash_render_welcome();
	?>
	<form class="sd-card sd-form" data-sd-settings-form novalidate>
		<div class="sd-card__body sd-form__fields">
			<div class="sd-field sd-field--switch<?php echo $welcome ? ' sd-field--highlight' : ''; ?>">
				<input type="checkbox" class="sd-switch" id="sd-open-after-login" name="open_after_login" aria-describedby="sd-open-after-login-help" <?php checked( swiftdash_setting( 'open_after_login' ) ); ?>>
				<div>
					<label class="sd-field__label" for="sd-open-after-login"><?php esc_html_e( 'Open SwiftWebDev Dashboard after login', 'swiftwebdev-dashboard-analytics' ); ?></label>
					<p class="sd-field__help" id="sd-open-after-login-help"><?php esc_html_e( 'Shows SwiftWebDev Dashboard instead of the WordPress dashboard. The WordPress dashboard stays available from your user menu.', 'swiftwebdev-dashboard-analytics' ); ?></p>
				</div>
			</div>
			<div class="sd-field sd-field--switch">
				<input type="checkbox" class="sd-switch" id="sd-exclude-admins" name="exclude_admins" aria-describedby="sd-exclude-admins-help" <?php checked( swiftdash_setting( 'exclude_admins' ) ); ?>>
				<div>
					<label class="sd-field__label" for="sd-exclude-admins"><?php esc_html_e( 'Do not count logged-in users', 'swiftwebdev-dashboard-analytics' ); ?></label>
					<p class="sd-field__help" id="sd-exclude-admins-help"><?php esc_html_e( 'Visits by anyone logged in to WordPress (you and your team) are left out of visitor stats.', 'swiftwebdev-dashboard-analytics' ); ?></p>
				</div>
			</div>
			<div class="sd-field sd-field--switch">
				<input type="checkbox" class="sd-switch" id="sd-toolbar-live" name="toolbar_live" aria-describedby="sd-toolbar-live-help" <?php checked( swiftdash_setting( 'toolbar_live' ) ); ?>>
				<div>
					<label class="sd-field__label" for="sd-toolbar-live"><?php esc_html_e( 'Show live visitors in the toolbar', 'swiftwebdev-dashboard-analytics' ); ?></label>
					<p class="sd-field__help" id="sd-toolbar-live-help"><?php esc_html_e( 'Shows how many people are on your site right now in the top bar, with more of today\'s numbers when you point at it.', 'swiftwebdev-dashboard-analytics' ); ?></p>
				</div>
			</div>
			<div class="sd-field sd-field--switch">
				<input type="checkbox" class="sd-switch" id="sd-toolbar-search" name="toolbar_search" aria-describedby="sd-toolbar-search-help" <?php checked( swiftdash_setting( 'toolbar_search' ) ); ?>>
				<div>
					<label class="sd-field__label" for="sd-toolbar-search"><?php esc_html_e( 'Show search in the toolbar', 'swiftwebdev-dashboard-analytics' ); ?></label>
					<p class="sd-field__help" id="sd-toolbar-search-help"><?php esc_html_e( 'Adds a search icon next to your name in the top bar that finds posts, pages, media, users and admin screens from any admin page.', 'swiftwebdev-dashboard-analytics' ); ?></p>
				</div>
			</div>
			<div class="sd-field">
				<label class="sd-field__label" for="sd-brand-name"><?php esc_html_e( 'Menu name', 'swiftwebdev-dashboard-analytics' ); ?></label>
				<input type="text" class="sd-input" id="sd-brand-name" name="brand_name" maxlength="40" aria-describedby="sd-brand-name-help" placeholder="<?php echo esc_attr( wp_strip_all_tags( get_bloginfo( 'name' ) ) ); ?>" value="<?php echo esc_attr( swiftdash_setting( 'brand_name' ) ); ?>">
				<p class="sd-field__help" id="sd-brand-name-help"><?php esc_html_e( 'Shown next to the logo at the top of the left menu. Your site title is used by default.', 'swiftwebdev-dashboard-analytics' ); ?></p>
			</div>
			<div class="sd-field" data-sd-logo data-default="<?php echo esc_url( get_site_icon_url( 64 ) ? get_site_icon_url( 64 ) : SWIFTDASH_URL . 'assets/img/brand.svg' ); ?>">
				<span class="sd-field__label" id="sd-brand-logo-label"><?php esc_html_e( 'Menu logo', 'swiftwebdev-dashboard-analytics' ); ?></span>
				<div class="sd-logo-field">
					<img class="sd-logo-field__preview" src="<?php echo esc_url( $brand['logo'] ); ?>" alt="" width="40" height="40">
					<button type="button" class="sd-btn sd-btn--secondary" data-sd-logo-choose aria-describedby="sd-brand-logo-label"><?php esc_html_e( 'Choose image', 'swiftwebdev-dashboard-analytics' ); ?></button>
					<button type="button" class="sd-btn sd-btn--tertiary" data-sd-logo-reset<?php echo swiftdash_setting( 'brand_logo' ) ? '' : ' hidden'; ?>><?php esc_html_e( 'Use default', 'swiftwebdev-dashboard-analytics' ); ?></button>
				</div>
				<input type="hidden" name="brand_logo" value="<?php echo esc_attr( (string) swiftdash_setting( 'brand_logo' ) ); ?>">
				<p class="sd-field__help"><?php esc_html_e( 'A square image works best. It is shown at the top of the left menu on every admin page. Your site icon is used by default.', 'swiftwebdev-dashboard-analytics' ); ?></p>
			</div>
			<div class="sd-field sd-field--switch">
				<input type="checkbox" class="sd-switch" id="sd-weekly-report" name="weekly_report" aria-describedby="<?php echo esc_attr( $help_id ); ?>" <?php checked( $weekly ); ?>>
				<div>
					<label class="sd-field__label" for="sd-weekly-report"><?php esc_html_e( 'Email a weekly site report', 'swiftwebdev-dashboard-analytics' ); ?></label>
					<p class="sd-field__help" id="sd-weekly-report-help"><?php esc_html_e( 'Every Monday morning: visitors, top pages, AI assistant visits, speed scores, pending updates and Site Health, compared with the week before.', 'swiftwebdev-dashboard-analytics' ); ?></p>
					<?php if ( $last ) : ?>
						<p class="sd-field__help" id="sd-weekly-report-last">
							<?php
							/* translators: %s: date and time the last weekly report was sent. */
							echo esc_html( sprintf( __( 'Last report sent %s.', 'swiftwebdev-dashboard-analytics' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last ) ) );
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
			<div class="sd-field">
				<label class="sd-field__label" for="sd-report-email"><?php esc_html_e( 'Send the report to', 'swiftwebdev-dashboard-analytics' ); ?></label>
				<input type="email" class="sd-input" id="sd-report-email" name="report_email" maxlength="100" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" aria-describedby="sd-report-email-help" value="<?php echo esc_attr( swiftdash_setting( 'report_email' ) ); ?>">
				<p class="sd-field__help" id="sd-report-email-help">
					<?php
					/* translators: %s: site admin email address. */
					echo esc_html( sprintf( __( 'Leave empty to use the site admin email, %s. The test report goes to this address too.', 'swiftwebdev-dashboard-analytics' ), get_option( 'admin_email' ) ) );
					?>
				</p>
				<div>
					<button type="button" class="sd-btn sd-btn--secondary" data-sd-report-test><?php esc_html_e( 'Send a test report now', 'swiftwebdev-dashboard-analytics' ); ?></button>
				</div>
			</div>
			<p class="sd-form__error" role="alert" hidden></p>
		</div>
		<footer class="sd-card__foot">
			<button type="submit" class="sd-btn sd-btn--primary" disabled><?php esc_html_e( 'Save changes', 'swiftwebdev-dashboard-analytics' ); ?></button>
		</footer>
	</form>
	<?php
}

/**
 * Empty state component.
 *
 * @param string $icon  Icon name.
 * @param string $title Heading.
 * @param string $text  What happened and what to do next.
 */
function swiftdash_render_empty_state( $icon, $title, $text ) {
	?>
	<div class="sd-empty">
		<span class="sd-empty__icon"><?php echo wp_kses( swiftdash_icon( $icon ), swiftdash_icon_kses() ); ?></span>
		<p class="sd-empty__title"><?php echo esc_html( $title ); ?></p>
		<p class="sd-empty__text"><?php echo esc_html( $text ); ?></p>
	</div>
	<?php
}

/**
 * Widget settings panel: show or hide widgets on a screen (saved per user).
 *
 * @param string $screen_key Screen key.
 */
function swiftdash_render_customize_panel( $screen_key ) {
	$hidden = swiftdash_prefs()['hidden'];
	?>
	<dialog class="sd-dialog sd-dialog--drawer" id="sd-customize" aria-labelledby="sd-customize-title">
		<form method="dialog" class="sd-dialog__inner" data-sd-customize>
			<header class="sd-dialog__head">
				<h2 class="sd-dialog__title" id="sd-customize-title"><?php 'dashboard' === $screen_key ? esc_html_e( 'Customize dashboard', 'swiftwebdev-dashboard-analytics' ) : esc_html_e( 'Customize this screen', 'swiftwebdev-dashboard-analytics' ); ?></h2>
				<button type="submit" value="cancel" class="sd-icon-btn" aria-label="<?php esc_attr_e( 'Close', 'swiftwebdev-dashboard-analytics' ); ?>"><?php echo wp_kses( swiftdash_icon( 'x' ), swiftdash_icon_kses() ); ?></button>
			</header>
			<div class="sd-dialog__body">
				<p class="sd-field__help"><?php esc_html_e( 'Choose which widgets you see. This only changes your own view.', 'swiftwebdev-dashboard-analytics' ); ?></p>
				<ul class="sd-checklist">
					<?php foreach ( swiftdash_get_screen_widgets( $screen_key ) as $widget ) : ?>
						<li>
							<label class="sd-check">
								<input type="checkbox" value="<?php echo esc_attr( $widget['id'] ); ?>" <?php checked( ! in_array( $widget['id'], $hidden, true ) ); ?>>
								<span><?php echo esc_html( $widget['title'] ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<footer class="sd-dialog__foot">
				<button type="button" class="sd-btn sd-btn--tertiary" data-sd-customize-reset><?php esc_html_e( 'Show all', 'swiftwebdev-dashboard-analytics' ); ?></button>
				<button type="submit" value="save" class="sd-btn sd-btn--primary"><?php esc_html_e( 'Save', 'swiftwebdev-dashboard-analytics' ); ?></button>
			</footer>
		</form>
	</dialog>
	<?php
}

/**
 * Shared confirmation dialog, driven by swiftdash.confirm() in app.js.
 */
function swiftdash_render_confirm_dialog() {
	?>
	<dialog class="sd-dialog" id="sd-confirm" aria-labelledby="sd-confirm-title" aria-describedby="sd-confirm-text">
		<form method="dialog" class="sd-dialog__inner">
			<header class="sd-dialog__head">
				<h2 class="sd-dialog__title" id="sd-confirm-title"></h2>
			</header>
			<div class="sd-dialog__body"><p id="sd-confirm-text"></p></div>
			<footer class="sd-dialog__foot">
				<button type="submit" value="cancel" class="sd-btn sd-btn--secondary"><?php esc_html_e( 'Cancel', 'swiftwebdev-dashboard-analytics' ); ?></button>
				<button type="submit" value="confirm" class="sd-btn sd-btn--primary" data-sd-confirm-ok></button>
			</footer>
		</form>
	</dialog>
	<?php
}
