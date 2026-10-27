<?php
/**
 * Free dashboard widgets in the bottom row (area "site"): Updates & Health, Recent Activity.
 *
 * Everything reads cached WordPress data only; nothing here triggers a remote request.
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the widgets.
 */
function swiftdash_register_site_widgets() {
	swiftdash_register_widget(
		'updates-health',
		array(
			'title'       => __( 'Updates & Health', 'swift-dashboard-analytics' ),
			'description' => __( 'See pending WordPress, plugin and theme updates, your PHP version and Site Health results.', 'swift-dashboard-analytics' ),
			'screen'      => 'dashboard',
			'area'        => 'site',
			'order'       => 10,
			'span'        => 6,
			'tier'        => 'free',
			'render'      => 'swiftdash_render_updates_health',
			'actions'     => 'swiftdash_render_health_status',
		)
	);
	swiftdash_register_widget(
		'recent-activity',
		array(
			'title'       => __( 'Recent Activity', 'swift-dashboard-analytics' ),
			'description' => __( 'See the latest posts, new users, orders and plugin changes on your site.', 'swift-dashboard-analytics' ),
			'screen'      => 'dashboard',
			'area'        => 'site',
			'order'       => 20,
			'span'        => 6,
			'tier'        => 'free',
			'render'      => 'swiftdash_render_recent_activity',
		)
	);
}
add_action( 'swiftdash_register_widgets', 'swiftdash_register_site_widgets' );

/**
 * Dashboard widget styles.
 *
 * @param string $screen_key Current screen key.
 */
function swiftdash_enqueue_dashboard_widgets( $screen_key ) {
	if ( 'dashboard' === $screen_key ) {
		wp_enqueue_style( 'swiftdash-dashboard', SWIFTDASH_URL . 'assets/css/dashboard.css', array( 'swiftdash-app' ), SWIFTDASH_VERSION );
	}
}
add_action( 'swiftdash_enqueue_assets', 'swiftdash_enqueue_dashboard_widgets' );

/**
 * Count pending updates in a cached update transient.
 *
 * @param string $name Transient name: update_plugins or update_themes.
 * @return int|null Null when WordPress has not checked yet.
 */
function swiftdash_pending_updates( $name ) {
	$data = get_site_transient( $name );
	if ( ! is_object( $data ) || ! isset( $data->last_checked ) ) {
		return null;
	}
	return empty( $data->response ) ? 0 : count( (array) $data->response );
}

/**
 * Build the Updates & Health rows.
 *
 * @return array[] Each: icon, tone (icon tile colour), name, detail, status (success|warning|danger|info|neutral), label, action (url, text, sr) or null.
 */
function swiftdash_health_rows() {
	$rows      = array();
	$not_check = __( 'Not checked yet', 'swift-dashboard-analytics' );

	// WordPress core.
	$version = get_bloginfo( 'version' );
	$core    = get_site_transient( 'update_core' );
	$newest  = '';
	if ( is_object( $core ) && ! empty( $core->updates ) ) {
		foreach ( $core->updates as $offer ) {
			if ( isset( $offer->response, $offer->current ) && 'upgrade' === $offer->response ) {
				$newest = $offer->current;
				break;
			}
		}
	}
	$row = array(
		'icon'   => 'arrow-clockwise-fill',
		'tone'   => 'blue',
		'name'   => __( 'WordPress', 'swift-dashboard-analytics' ),
		/* translators: %s: WordPress version number. */
		'detail' => sprintf( __( 'Version %s', 'swift-dashboard-analytics' ), $version ),
		'status' => 'success',
		'label'  => __( 'Up to date', 'swift-dashboard-analytics' ),
		'action' => null,
	);
	if ( ! is_object( $core ) ) {
		$row['status'] = 'neutral';
		$row['label']  = $not_check;
	} elseif ( $newest ) {
		$row['status'] = 'warning';
		$row['label']  = __( 'Update available', 'swift-dashboard-analytics' );
		/* translators: 1: installed version, 2: available version. */
		$row['detail'] = sprintf( __( 'Version %1$s, %2$s available', 'swift-dashboard-analytics' ), $version, $newest );
		if ( current_user_can( 'update_core' ) ) {
			$row['action'] = array( admin_url( 'update-core.php' ), __( 'Update', 'swift-dashboard-analytics' ), __( 'WordPress', 'swift-dashboard-analytics' ) );
		}
	}
	$rows[] = $row;

	// Plugins and themes.
	$kinds = array(
		'plugins' => array( 'puzzle-piece-fill', 'blue', __( 'Plugins', 'swift-dashboard-analytics' ), 'update_plugins', 'plugins.php?plugin_status=upgrade' ),
		'themes'  => array( 'paint-brush-fill', 'purple', __( 'Themes', 'swift-dashboard-analytics' ), 'update_themes', 'themes.php' ),
	);
	foreach ( $kinds as $kind ) {
		list( $icon, $tone, $name, $transient, $url ) = $kind;

		$count = swiftdash_pending_updates( $transient );
		$row   = array(
			'icon'   => $icon,
			'tone'   => $tone,
			'name'   => $name,
			'detail' => __( 'No updates available', 'swift-dashboard-analytics' ),
			'status' => 'success',
			'label'  => __( 'Up to date', 'swift-dashboard-analytics' ),
			'action' => null,
		);
		if ( null === $count ) {
			$row['detail'] = __( 'WordPress has not checked for updates yet', 'swift-dashboard-analytics' );
			$row['status'] = 'neutral';
			$row['label']  = $not_check;
		} elseif ( $count > 0 ) {
			/* translators: %s: number of updates. */
			$row['detail'] = sprintf( _n( '%s update available', '%s updates available', $count, 'swift-dashboard-analytics' ), number_format_i18n( $count ) );
			$row['status'] = 'warning';
			$row['label']  = __( 'Update available', 'swift-dashboard-analytics' );
			if ( current_user_can( $transient ) ) {
				$row['action'] = array( admin_url( $url ), __( 'Update', 'swift-dashboard-analytics' ), $name );
			}
		}
		$rows[] = $row;
	}

	// PHP. wp_check_php_version() calls the WordPress.org API when its cache is cold, so
	// only call it when the cached answer exists.
	$php = false;
	if ( function_exists( 'wp_check_php_version' ) && false !== get_site_transient( 'php_check_' . md5( PHP_VERSION ) ) ) {
		$php = wp_check_php_version();
	}
	$row = array(
		'icon'   => 'code-fill',
		'tone'   => 'blue',
		'name'   => __( 'PHP version', 'swift-dashboard-analytics' ),
		'detail' => PHP_VERSION,
		'status' => 'neutral',
		'label'  => __( 'Version check unavailable', 'swift-dashboard-analytics' ),
		'action' => null,
	);
	if ( is_array( $php ) ) {
		if ( empty( $php['is_acceptable'] ) ) {
			$row['status'] = 'danger';
			$row['label']  = __( 'Update required', 'swift-dashboard-analytics' );
		} elseif ( empty( $php['is_secure'] ) ) {
			$row['status'] = 'warning';
			$row['label']  = __( 'Update recommended', 'swift-dashboard-analytics' );
		} else {
			$row['status'] = 'success';
			$row['label']  = __( 'Supported', 'swift-dashboard-analytics' );
		}
		if ( ! empty( $php['recommended_version'] ) && version_compare( PHP_VERSION, $php['recommended_version'], '<' ) ) {
			/* translators: 1: current PHP version, 2: recommended PHP version. */
			$row['detail'] = sprintf( __( '%1$s, %2$s or newer recommended', 'swift-dashboard-analytics' ), PHP_VERSION, $php['recommended_version'] );
		}
	}
	$rows[] = $row;

	// Site Health (counts cached by WordPress after the Site Health screen runs its tests).
	$health = json_decode( (string) get_transient( 'health-check-site-status-result' ), true );
	$row    = array(
		'icon'   => 'heart-fill',
		'tone'   => 'red',
		'name'   => __( 'Site Health', 'swift-dashboard-analytics' ),
		'detail' => __( 'Open Site Health once to run the checks', 'swift-dashboard-analytics' ),
		'status' => 'neutral',
		'label'  => $not_check,
		'action' => null,
	);
	if ( is_array( $health ) && isset( $health['good'], $health['recommended'], $health['critical'] ) ) {
		$critical      = (int) $health['critical'];
		$recommended   = (int) $health['recommended'];
		$row['detail'] = sprintf(
			/* translators: 1: passed tests, 2: recommended improvements, 3: critical issues. */
			__( '%1$s passed, %2$s recommended, %3$s critical', 'swift-dashboard-analytics' ),
			number_format_i18n( (int) $health['good'] ),
			number_format_i18n( $recommended ),
			number_format_i18n( $critical )
		);
		if ( $critical > 0 ) {
			$row['status'] = 'danger';
			$row['label']  = __( 'Critical issues', 'swift-dashboard-analytics' );
		} elseif ( $recommended > 0 ) {
			$row['status'] = 'warning';
			$row['label']  = __( 'Should be improved', 'swift-dashboard-analytics' );
		} else {
			$row['status'] = 'success';
			$row['label']  = __( 'Good', 'swift-dashboard-analytics' );
		}
	}
	if ( current_user_can( 'view_site_health_checks' ) ) {
		$row['action'] = array(
			admin_url( 'site-health.php' ),
			'neutral' === $row['status'] ? __( 'Run checks', 'swift-dashboard-analytics' ) : __( 'View details', 'swift-dashboard-analytics' ),
			__( 'Site Health', 'swift-dashboard-analytics' ),
			'tertiary',
		);
	}
	$rows[] = $row;

	return $rows;
}

/**
 * Status pill markup (icon + text, never colour alone).
 *
 * @param string $status Status key.
 * @param string $label  Text.
 * @return string Escaped markup.
 */
function swiftdash_status_pill( $status, $label ) {
	$icons = array(
		'success' => 'check-circle',
		'warning' => 'warning',
		'danger'  => 'warning-circle',
		'info'    => 'info',
		'neutral' => 'info',
	);
	return sprintf(
		'<span class="sd-status sd-status--%1$s">%2$s%3$s</span>',
		esc_attr( $status ),
		swiftdash_icon( $icons[ $status ] ),
		esc_html( $label )
	);
}

/**
 * Updates & Health header: overall status pill.
 */
function swiftdash_render_health_status() {
	$attention = array_filter(
		swiftdash_health_rows(),
		function ( $r ) {
			return in_array( $r['status'], array( 'warning', 'danger' ), true );
		}
	);
	$pill      = $attention
		? swiftdash_status_pill( 'warning', __( 'Needs attention', 'swift-dashboard-analytics' ) )
		: swiftdash_status_pill( 'success', __( 'All good', 'swift-dashboard-analytics' ) );
	echo $pill; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- swiftdash_status_pill() returns escaped markup.
}

/**
 * Updates & Health body.
 */
function swiftdash_render_updates_health() {
	$rows = swiftdash_health_rows();
	echo '<ul class="sd-dw-list">';
	foreach ( $rows as $row ) {
		echo '<li class="sd-dw-row">';
		printf( '<span class="sd-dw-chip sd-dw-chip--%s">%s</span>', esc_attr( $row['tone'] ), swiftdash_icon( $row['icon'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
		printf(
			'<span class="sd-dw-row__text"><span class="sd-dw-row__name">%s</span><span class="sd-dw-row__detail">%s</span></span>',
			esc_html( $row['name'] ),
			esc_html( $row['detail'] )
		);
		echo '<span class="sd-dw-row__end">';
		if ( $row['action'] && 'tertiary' !== ( isset( $row['action'][3] ) ? $row['action'][3] : '' ) ) {
			// Status text is in the detail line; the button carries the action.
			printf(
				'<a class="sd-btn sd-btn--secondary sd-btn--sm" href="%s">%s<span class="screen-reader-text"> %s</span></a>',
				esc_url( $row['action'][0] ),
				esc_html( $row['action'][1] ),
				esc_html( $row['action'][2] )
			);
		} else {
			echo swiftdash_status_pill( $row['status'], $row['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
			if ( $row['action'] ) {
				printf(
					'<a class="sd-btn sd-btn--neutral sd-btn--sm" href="%s">%s<span class="screen-reader-text"> %s</span></a>',
					esc_url( $row['action'][0] ),
					esc_html( $row['action'][1] ),
					esc_html( $row['action'][2] )
				);
			}
		}
		echo '</span></li>';
	}
	echo '</ul>';
}

/**
 * Recent Activity body.
 */
function swiftdash_render_recent_activity() {
	$rows = swiftdash_get_activity( 6 );
	if ( ! $rows ) {
		swiftdash_render_empty_state(
			'clock',
			__( 'No activity recorded yet', 'swift-dashboard-analytics' ),
			__( 'New posts, users and updates will appear here. Swift Dash records activity from the moment it was activated.', 'swift-dashboard-analytics' )
		);
		return;
	}
	$types = array(
		'publish'    => array( 'file-text-fill', 'blue' ),
		'user'       => array( 'user-plus-fill', 'blue' ),
		'plugin'     => array( 'puzzle-piece-fill', 'orange' ),
		'plugin-off' => array( 'plug-fill', 'neutral' ),
		'theme'      => array( 'paint-brush-fill', 'purple' ),
		'core'       => array( 'arrow-clockwise-fill', 'green' ),
		'order'      => array( 'shopping-cart-fill', 'orange' ),
	);
	echo '<ul class="sd-dw-list">';
	foreach ( $rows as $row ) {
		$type = isset( $types[ $row->type ] ) ? $types[ $row->type ] : array( 'bell-fill', 'blue' );
		$time = strtotime( $row->created . ' UTC' );
		echo '<li class="sd-dw-row">';
		printf( '<span class="sd-dw-chip sd-dw-chip--%s">%s</span>', esc_attr( $type[1] ), swiftdash_icon( $type[0] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
		printf(
			'<span class="sd-dw-row__text"><span class="sd-dw-row__name">%s</span><span class="sd-dw-row__detail">%s</span></span>',
			esc_html( $row->title ),
			esc_html( $row->detail )
		);
		printf(
			'<time class="sd-dw-row__time" datetime="%s" title="%s">%s</time>',
			esc_attr( gmdate( 'c', $time ) ),
			esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) ),
			/* translators: %s: human readable time difference, e.g. "2 hours". */
			esc_html( sprintf( __( '%s ago', 'swift-dashboard-analytics' ), human_time_diff( $time ) ) )
		);
		echo '</li>';
	}
	echo '</ul>';
}
