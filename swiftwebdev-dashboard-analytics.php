<?php
/**
 * Plugin Name:       SwiftWebDev Dashboard & Analytics
 * Plugin URI:        https://swiftwebdev.com/swiftwebdev-dashboard-docs/
 * Description:       Admin dashboard and cookieless analytics for WordPress: visitor stats, Google PageSpeed scores, site health and a modern admin menu.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Swift Web Dev
 * Author URI:        https://swiftwebdev.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       swiftwebdev-dashboard-analytics
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

define( 'SWIFTDASH_VERSION', '1.0.0' );
define( 'SWIFTDASH_FILE', __FILE__ );
define( 'SWIFTDASH_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWIFTDASH_URL', plugin_dir_url( __FILE__ ) );
define( 'SWIFTDASH_ADDON_URL', 'https://swiftwebdev.com/swiftwebdev-dashboard-pro/' );
define( 'SWIFTDASH_DOCS_URL', 'https://swiftwebdev.com/swiftwebdev-dashboard-docs/' );

require_once SWIFTDASH_DIR . 'includes/install.php';
require_once SWIFTDASH_DIR . 'includes/settings.php';
require_once SWIFTDASH_DIR . 'includes/stats.php';
require_once SWIFTDASH_DIR . 'includes/tracker.php';
require_once SWIFTDASH_DIR . 'includes/activity.php';
require_once SWIFTDASH_DIR . 'includes/widgets.php';
require_once SWIFTDASH_DIR . 'includes/shell.php';
require_once SWIFTDASH_DIR . 'includes/screens.php';
require_once SWIFTDASH_DIR . 'includes/admin-menu.php';
require_once SWIFTDASH_DIR . 'includes/analytics.php';
require_once SWIFTDASH_DIR . 'includes/dashboard-widgets.php';
require_once SWIFTDASH_DIR . 'includes/speed.php';
require_once SWIFTDASH_DIR . 'includes/quick-links.php';

if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( SWIFTDASH_DIR . 'includes/dev-components.php' ) ) {
	require_once SWIFTDASH_DIR . 'includes/dev-components.php';
}
