<?php
/**
 * First run: send the activating admin to the welcome card on the settings screen once,
 * plus a Settings link on the Plugins screen.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Redirect once after the first activation. install.php sets swiftdash_first_run on a brand-new
 * install only, so updates and reactivations never get here. Bulk and network activation clear
 * the flag without redirecting.
 */
function swiftdash_first_run_redirect() {
	if ( ! get_option( 'swiftdash_first_run' ) || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! current_user_can( swiftdash_capability() ) ) {
		return;
	}
	delete_option( 'swiftdash_first_run' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of the core bulk activation flag.
	if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
		return;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=swift-dash-settings&welcome=1' ) );
	exit;
}
add_action( 'admin_init', 'swiftdash_first_run_redirect' );

/**
 * Settings link next to Deactivate on the Plugins screen.
 *
 * @param string[] $links Action links.
 * @return string[]
 */
function swiftdash_plugin_action_links( $links ) {
	array_unshift(
		$links,
		sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=swift-dash-settings' ) ), esc_html__( 'Settings', 'swiftwebdev-dashboard-analytics' ) )
	);
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( SWIFTDASH_FILE ), 'swiftdash_plugin_action_links' );

/**
 * Welcome card at the top of the settings screen, shown after the first-run redirect until the
 * user answers it. The buttons are wired in app.js (settings form block).
 *
 * @return bool Whether the card was printed.
 */
function swiftdash_render_welcome() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag from the first-run redirect.
	if ( empty( $_GET['welcome'] ) || swiftdash_prefs()['welcomed'] ) {
		return false;
	}
	?>
	<section class="sd-card sd-welcome" aria-labelledby="sd-welcome-title" data-sd-welcome data-dashboard="<?php echo esc_url( admin_url( 'admin.php?page=swift-dash' ) ); ?>">
		<div class="sd-welcome__text">
			<span class="sd-status sd-status--info"><?php esc_html_e( 'Welcome', 'swiftwebdev-dashboard-analytics' ); ?></span>
			<h2 class="sd-welcome__title" id="sd-welcome-title"><?php esc_html_e( 'Make SwiftWebDev Dashboard your home screen?', 'swiftwebdev-dashboard-analytics' ); ?></h2>
			<p class="sd-welcome__desc"><?php esc_html_e( 'See visitors, speed scores, updates and Site Health the moment you log in. You can switch back to the WordPress dashboard any time from your user menu.', 'swiftwebdev-dashboard-analytics' ); ?></p>
			<label class="sd-check">
				<input type="checkbox" data-sd-welcome-report checked>
				<span><?php esc_html_e( 'Email me a weekly site report', 'swiftwebdev-dashboard-analytics' ); ?></span>
			</label>
			<div class="sd-welcome__actions">
				<button type="button" class="sd-btn sd-btn--primary" data-sd-welcome-answer="yes"><?php esc_html_e( 'Yes, make it my default', 'swiftwebdev-dashboard-analytics' ); ?></button>
				<button type="button" class="sd-btn sd-btn--secondary" data-sd-welcome-answer="no"><?php esc_html_e( 'Keep the WordPress dashboard', 'swiftwebdev-dashboard-analytics' ); ?></button>
			</div>
		</div>
		<div class="sd-welcome__preview" aria-hidden="true">
			<span class="sd-welcome__nav"></span>
			<span class="sd-welcome__kpi"></span>
			<span class="sd-welcome__kpi"></span>
			<span class="sd-welcome__kpi"></span>
			<span class="sd-welcome__chart"><svg viewBox="0 0 120 40" preserveAspectRatio="none"><path d="M0 34 L15 28 L30 30 L45 18 L60 22 L75 12 L90 16 L105 6 L120 9"/></svg></span>
		</div>
	</section>
	<?php
	return true;
}
