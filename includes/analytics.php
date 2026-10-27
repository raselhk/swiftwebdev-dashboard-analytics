<?php
/**
 * Analytics screen and the free visitor KPI widgets (dashboard + analytics).
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

/**
 * Free KPI metrics: stats key, label, tooltip, accent modifier (icons: swiftdash_kpi_card()).
 *
 * @return array[]
 */
function swiftdash_kpi_metrics() {
	return array(
		'visitors'   => array(
			'key'   => 'visitors',
			'title' => __( 'Visitors', 'swift-dashboard-analytics' ),
			'tip'   => __( 'Unique people who visited your site in this period, counted without cookies.', 'swift-dashboard-analytics' ),
			'tone'  => '',
		),
		'page-views' => array(
			'key'   => 'views',
			'title' => __( 'Page Views', 'swift-dashboard-analytics' ),
			'tip'   => __( 'Total pages viewed, including repeat views of the same page.', 'swift-dashboard-analytics' ),
			'tone'  => 'purple',
		),
		'sessions'   => array(
			'key'   => 'sessions',
			'title' => __( 'Sessions', 'swift-dashboard-analytics' ),
			'tip'   => __( 'Separate visits to your site. One person can have several sessions.', 'swift-dashboard-analytics' ),
			'tone'  => 'green',
		),
	);
}

/**
 * Stats for a period, fetched once per request.
 *
 * @param int $days Days in the period.
 * @return array See swiftdash_get_stats().
 */
function swiftdash_analytics_stats( $days ) {
	static $cache = array();
	if ( ! isset( $cache[ $days ] ) ) {
		$cache[ $days ] = swiftdash_get_stats( $days );
	}
	return $cache[ $days ];
}

/**
 * Selected analytics range in days (free: 7 or 30).
 *
 * @return int
 */
function swiftdash_analytics_range() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter.
	$range = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : 30;
	return 7 === $range ? 7 : 30;
}

/**
 * Selected analytics tab key.
 *
 * @return string
 */
function swiftdash_analytics_tab() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
	return array_key_exists( $tab, swiftdash_analytics_tabs() ) ? $tab : 'overview';
}

/**
 * Analytics sub-navigation. Every tab but Overview is filled by the Pro add-on.
 *
 * @return array[] Keyed by tab: label, pro, items (what the tab contains).
 */
function swiftdash_analytics_tabs() {
	$tabs = array(
		'overview'   => array(
			'label' => __( 'Overview', 'swift-dashboard-analytics' ),
			'pro'   => false,
			'items' => array(),
		),
		'realtime'   => array(
			'label' => __( 'Real-time', 'swift-dashboard-analytics' ),
			'pro'   => true,
			'items' => array(
				__( 'How many people are on your site right now', 'swift-dashboard-analytics' ),
				__( 'Which pages they are viewing', 'swift-dashboard-analytics' ),
				__( 'A live feed of the latest visits', 'swift-dashboard-analytics' ),
			),
		),
		'audience'   => array(
			'label' => __( 'Audience', 'swift-dashboard-analytics' ),
			'pro'   => true,
			'items' => array(
				__( 'New and returning visitors', 'swift-dashboard-analytics' ),
				__( 'Visitors by country', 'swift-dashboard-analytics' ),
				__( 'Devices and browsers people use', 'swift-dashboard-analytics' ),
			),
		),
		'behavior'   => array(
			'label' => __( 'Behavior', 'swift-dashboard-analytics' ),
			'pro'   => true,
			'items' => array(
				__( 'How long visits last', 'swift-dashboard-analytics' ),
				__( 'How many pages people view per visit', 'swift-dashboard-analytics' ),
				__( 'What people search for on your site', 'swift-dashboard-analytics' ),
			),
		),
		'pages'      => array(
			'label' => __( 'Pages', 'swift-dashboard-analytics' ),
			'pro'   => true,
			'items' => array(
				__( 'Every page ranked by views', 'swift-dashboard-analytics' ),
				__( 'Unique visitors for each page', 'swift-dashboard-analytics' ),
				__( 'Change compared with the previous period', 'swift-dashboard-analytics' ),
			),
		),
		'forms'      => array(
			'label' => __( 'Forms', 'swift-dashboard-analytics' ),
			'pro'   => true,
			'items' => array(
				__( 'Successful form submissions over time', 'swift-dashboard-analytics' ),
				__( 'Submissions for each form', 'swift-dashboard-analytics' ),
				__( 'Conversion rate for each form', 'swift-dashboard-analytics' ),
			),
		),
		'clicks'     => array(
			'label' => __( 'Clicks', 'swift-dashboard-analytics' ),
			'pro'   => true,
			'items' => array(
				__( 'Clicks on links and buttons', 'swift-dashboard-analytics' ),
				__( 'Outbound, download, email and phone clicks', 'swift-dashboard-analytics' ),
				__( 'Your most clicked links', 'swift-dashboard-analytics' ),
			),
		),
		'recordings' => array(
			'label' => __( 'Session recordings', 'swift-dashboard-analytics' ),
			'pro'   => true,
			'items' => array(
				__( 'Replays of real visits, with private fields hidden', 'swift-dashboard-analytics' ),
				__( 'Pages visited and time spent in each visit', 'swift-dashboard-analytics' ),
				__( 'Search and filter recorded visits', 'swift-dashboard-analytics' ),
			),
		),
		'bots'       => array(
			'label' => __( 'Bots', 'swift-dashboard-analytics' ),
			'pro'   => true,
			'items' => array(
				__( 'Suspected bot and crawler visits', 'swift-dashboard-analytics' ),
				__( 'Spam related activity', 'swift-dashboard-analytics' ),
				__( 'Human compared with automated traffic', 'swift-dashboard-analytics' ),
			),
		),
		'events'     => array(
			'label' => __( 'Events', 'swift-dashboard-analytics' ),
			'pro'   => true,
			'items' => array(
				__( 'Custom events you choose to track', 'swift-dashboard-analytics' ),
				__( 'Goals and conversion funnels', 'swift-dashboard-analytics' ),
				__( 'Event trends over time', 'swift-dashboard-analytics' ),
			),
		),
	);

	/**
	 * Filters the analytics tabs. The Pro add-on sets 'pro' => false on the tabs it unlocks.
	 *
	 * @param array $tabs Tabs keyed by tab key.
	 */
	return apply_filters( 'swiftdash_analytics_tabs', $tabs );
}

/**
 * Register the free KPI widgets and the analytics locked Pro slots.
 */
function swiftdash_analytics_register_widgets() {
	$range = swiftdash_analytics_range();
	$order = 10;
	foreach ( swiftdash_kpi_metrics() as $id => $metric ) {
		$base = array(
			'title'       => $metric['title'],
			'description' => $metric['tip'],
			'area'        => 'kpis',
			'order'       => $order,
			'span'        => 3,
			'tier'        => 'free',
			'render'      => 'swiftdash_render_kpi_widget',
			'metric'      => $id,
		);
		swiftdash_register_widget( $id, $base + array( 'days' => 30 ) );
		swiftdash_register_widget(
			'an-' . $id,
			array(
				'screen' => 'analytics',
				'days'   => $range,
			) + $base
		);
		$order += 10;
	}

	$locked = array(
		'an-new-visitors' => array( __( 'New Visitors', 'swift-dashboard-analytics' ), __( 'See how many visitors came to your site for the first time.', 'swift-dashboard-analytics' ), 'kpis', 40, 3, 'kpi' ),
		'an-trend'        => array( __( 'Visitors & Page Views', 'swift-dashboard-analytics' ), __( 'Track visitors, page views and sessions day by day.', 'swift-dashboard-analytics' ), 'overview', 10, 8, 'chart' ),
		'an-sources'      => array( __( 'Traffic Sources', 'swift-dashboard-analytics' ), __( 'See whether visitors come from search, social, other sites or direct.', 'swift-dashboard-analytics' ), 'overview', 20, 4, 'donut' ),
		'an-top-pages'    => array( __( 'Top Pages', 'swift-dashboard-analytics' ), __( 'Find the pages people visit most.', 'swift-dashboard-analytics' ), 'details', 10, 4, 'table' ),
		'an-referrers'    => array( __( 'Top Referrers', 'swift-dashboard-analytics' ), __( 'See which other websites send you visitors.', 'swift-dashboard-analytics' ), 'details', 20, 4, 'table' ),
		'an-devices'      => array( __( 'Device Types', 'swift-dashboard-analytics' ), __( 'Compare visits from desktop, mobile and tablet.', 'swift-dashboard-analytics' ), 'details', 30, 4, 'donut' ),
		'an-countries'    => array( __( 'Visitors by Country', 'swift-dashboard-analytics' ), __( 'See which countries your visitors come from.', 'swift-dashboard-analytics' ), 'details', 40, 6, 'map' ),
		'an-forms'        => array( __( 'Form Submissions', 'swift-dashboard-analytics' ), __( 'Count successful form submissions for each form.', 'swift-dashboard-analytics' ), 'details', 50, 6, 'kpi' ),
		'an-clicks'       => array( __( 'Clicks Count', 'swift-dashboard-analytics' ), __( 'Count clicks on links, buttons, downloads and phone numbers.', 'swift-dashboard-analytics' ), 'details', 60, 6, 'kpi' ),
		'an-conversion'   => array( __( 'Conversion Rate', 'swift-dashboard-analytics' ), __( 'See what share of visitors complete a form or goal.', 'swift-dashboard-analytics' ), 'details', 70, 6, 'kpi' ),
	);
	foreach ( $locked as $id => $w ) {
		swiftdash_register_widget(
			$id,
			array(
				'title'       => $w[0],
				'description' => $w[1],
				'screen'      => 'analytics',
				'area'        => $w[2],
				'order'       => $w[3],
				'span'        => $w[4],
				'tier'        => 'pro',
				'placeholder' => $w[5],
			)
		);
	}
}
add_action( 'swiftdash_register_widgets', 'swiftdash_analytics_register_widgets' );

/**
 * Analytics widget areas.
 *
 * @param array $areas Areas per screen.
 * @return array
 */
function swiftdash_analytics_areas( $areas ) {
	$areas['analytics'] = array( 'kpis', 'overview', 'details' );
	return $areas;
}
add_filter( 'swiftdash_screen_areas', 'swiftdash_analytics_areas' );

/**
 * Enqueue analytics CSS/JS on the dashboard and analytics screens.
 *
 * @param string $screen_key Current screen key.
 */
function swiftdash_analytics_assets( $screen_key ) {
	if ( ! in_array( $screen_key, array( 'dashboard', 'analytics' ), true ) ) {
		return;
	}
	wp_enqueue_style( 'swiftdash-analytics', SWIFTDASH_URL . 'assets/css/analytics.css', array( 'swiftdash-app' ), SWIFTDASH_VERSION );
	if ( 'analytics' === $screen_key ) {
		wp_enqueue_script( 'swiftdash-analytics', SWIFTDASH_URL . 'assets/js/analytics.js', array( 'swiftdash-app' ), SWIFTDASH_VERSION, true );
	}
}
add_action( 'swiftdash_enqueue_assets', 'swiftdash_analytics_assets' );

/**
 * Change versus the previous period, for metrics where up is good.
 *
 * @param int $current  Current total.
 * @param int $previous Previous total.
 * @return array tone (good, bad, flat), icon ('' for none), text, sr (screen-reader prefix).
 */
function swiftdash_kpi_change( $current, $previous ) {
	if ( $previous <= 0 ) {
		return array(
			'tone' => 'flat',
			'icon' => '',
			'text' => $current > 0 ? __( 'New', 'swift-dashboard-analytics' ) : __( 'No change', 'swift-dashboard-analytics' ),
			'sr'   => '',
		);
	}
	$pct = (int) round( ( $current - $previous ) / $previous * 100 );
	if ( 0 === $pct ) {
		return array(
			'tone' => 'flat',
			'icon' => '',
			'text' => __( 'No change', 'swift-dashboard-analytics' ),
			'sr'   => '',
		);
	}
	return array(
		'tone' => $pct > 0 ? 'good' : 'bad',
		'icon' => $pct > 0 ? 'arrow-up' : 'arrow-down',
		/* translators: %s: percentage number. */
		'text' => sprintf( __( '%s%%', 'swift-dashboard-analytics' ), number_format_i18n( abs( $pct ) ) ),
		'sr'   => $pct > 0 ? __( 'Up', 'swift-dashboard-analytics' ) : __( 'Down', 'swift-dashboard-analytics' ),
	);
}

/**
 * Inline SVG sparkline points for a series.
 *
 * @param int[] $values Values, oldest first.
 * @return string Points attribute for a 100x32 viewBox.
 */
function swiftdash_sparkline_points( $values ) {
	$max    = max( 1, max( $values ) );
	$step   = 100 / max( 1, count( $values ) - 1 );
	$points = array();
	foreach ( array_values( $values ) as $i => $v ) {
		$points[] = round( $i * $step, 2 ) . ',' . round( 30 - ( $v / $max ) * 28, 2 );
	}
	return implode( ' ', $points );
}

/**
 * KPI card body, shared by the dashboard and analytics widgets.
 *
 * @param array $widget Widget args (metric, days).
 */
function swiftdash_render_kpi_widget( $widget ) {
	$metrics = swiftdash_kpi_metrics();
	$metric  = $metrics[ $widget['metric'] ];
	$days    = (int) $widget['days'];
	$stats   = swiftdash_analytics_stats( $days );
	$key     = $metric['key'];
	?>
	<div class="sd-kpi sd-an-kpi sd-an-kpi--<?php echo esc_attr( $metric['tone'] ? $metric['tone'] : 'blue' ); ?>">
		<?php if ( empty( $stats['has_data'] ) ) : ?>
			<p class="sd-an-kpi__empty"><?php esc_html_e( 'No visits recorded yet', 'swift-dashboard-analytics' ); ?></p>
			<p class="sd-field__help"><?php echo esc_html( swiftdash_analytics_empty_help() ); ?></p>
		<?php else : ?>
			<?php
			$current = (int) $stats['totals'][ $key ];
			$change  = swiftdash_kpi_change( $current, (int) $stats['previous'][ $key ] );
			$series  = array_map( 'intval', wp_list_pluck( $stats['daily'], $key ) );
			?>
			<div class="sd-an-kpi__row">
				<span class="sd-kpi__value"><?php echo esc_html( number_format_i18n( $current ) ); ?></span>
				<?php if ( count( $series ) > 1 ) : ?>
					<svg class="sd-an-spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true" focusable="false">
						<polyline points="<?php echo esc_attr( swiftdash_sparkline_points( $series ) ); ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
					</svg>
					<span class="screen-reader-text">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: number of days, 2: lowest daily value, 3: highest daily value. */
								__( 'Daily trend over the last %1$s days: lowest %2$s, highest %3$s.', 'swift-dashboard-analytics' ),
								number_format_i18n( count( $series ) ),
								number_format_i18n( min( $series ) ),
								number_format_i18n( max( $series ) )
							)
						);
						?>
					</span>
				<?php endif; ?>
			</div>
			<span class="sd-kpi__meta">
				<span class="sd-trend sd-trend--<?php echo esc_attr( $change['tone'] ); ?>">
					<?php
					if ( $change['icon'] ) {
						echo swiftdash_icon( $change['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
						echo '<span class="screen-reader-text">' . esc_html( $change['sr'] ) . ' </span>';
					}
					echo esc_html( $change['text'] );
					?>
				</span>
				<?php
				/* translators: %s: number of days. */
				echo esc_html( sprintf( _n( 'vs previous %s day', 'vs previous %s days', $days, 'swift-dashboard-analytics' ), number_format_i18n( $days ) ) );
				?>
			</span>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Help text shown while no visits are recorded.
 *
 * @return string
 */
function swiftdash_analytics_empty_help() {
	return swiftdash_setting( 'exclude_admins' )
		? __( 'Stats start with the next visit. Logged-in users are not counted.', 'swift-dashboard-analytics' )
		: __( 'Stats start with the next visit.', 'swift-dashboard-analytics' );
}

/**
 * Analytics screen.
 *
 * @param array $screen Screen definition.
 */
function swiftdash_screen_analytics( $screen ) {
	$tab   = swiftdash_analytics_tab();
	$tabs  = swiftdash_analytics_tabs();
	$range = swiftdash_analytics_range();

	swiftdash_render_page_header(
		$screen['title'],
		$screen['description'],
		function () use ( $tab, $range ) {
			if ( has_action( 'swiftdash_analytics_header_actions' ) ) {
				/**
				 * Replaces the analytics header actions (date range, export). Used by the Pro add-on.
				 *
				 * @param string $tab Current tab key.
				 */
				do_action( 'swiftdash_analytics_header_actions', $tab );
				return;
			}
			?>
			<form class="sd-daterange" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" data-sd-range-form>
				<input type="hidden" name="page" value="swift-dash-analytics">
				<?php if ( 'overview' !== $tab ) : ?>
					<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php endif; ?>
				<label class="screen-reader-text" for="sd-an-range"><?php esc_html_e( 'Date range', 'swift-dashboard-analytics' ); ?></label>
				<select class="sd-select" id="sd-an-range" name="range">
					<option disabled><?php esc_html_e( 'Last 24 hours (Pro)', 'swift-dashboard-analytics' ); ?></option>
					<option value="7" <?php selected( $range, 7 ); ?>><?php esc_html_e( 'Last 7 days', 'swift-dashboard-analytics' ); ?></option>
					<option value="30" <?php selected( $range, 30 ); ?>><?php esc_html_e( 'Last 30 days', 'swift-dashboard-analytics' ); ?></option>
					<option disabled><?php esc_html_e( 'Last 90 days (Pro)', 'swift-dashboard-analytics' ); ?></option>
					<option disabled><?php esc_html_e( 'Custom range (Pro)', 'swift-dashboard-analytics' ); ?></option>
				</select>
				<noscript><button type="submit" class="sd-btn sd-btn--secondary"><?php esc_html_e( 'Apply', 'swift-dashboard-analytics' ); ?></button></noscript>
			</form>
			<span class="sd-an-export">
				<button type="button" class="sd-btn sd-btn--secondary" aria-disabled="true" aria-describedby="sd-an-export-tip">
					<?php echo swiftdash_icon( 'download-simple' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
					<?php esc_html_e( 'Export report', 'swift-dashboard-analytics' ); ?>
					<span class="sd-badge sd-badge--pro"><?php esc_html_e( 'Pro', 'swift-dashboard-analytics' ); ?></span>
				</button>
				<span class="sd-tip__text" role="tooltip" id="sd-an-export-tip"><?php esc_html_e( 'Download your reports as a file with Swift Dash Pro.', 'swift-dashboard-analytics' ); ?></span>
			</span>
			<?php
		}
	);
	?>
	<nav class="sd-an-tabs" aria-label="<?php esc_attr_e( 'Analytics reports', 'swift-dashboard-analytics' ); ?>">
		<ul class="sd-an-tabs__list">
			<?php foreach ( $tabs as $key => $item ) : ?>
				<?php
				$url = admin_url( 'admin.php?page=swift-dash-analytics' );
				if ( 'overview' !== $key ) {
					$url = add_query_arg( 'tab', $key, $url );
				}
				if ( 30 !== $range ) {
					$url = add_query_arg( 'range', $range, $url );
				}
				?>
				<li>
					<a class="sd-an-tabs__link" href="<?php echo esc_url( $url ); ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>>
						<?php
						echo esc_html( $item['label'] );
						if ( $item['pro'] ) {
							echo swiftdash_icon( 'lock-simple' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
							echo '<span class="screen-reader-text"> ' . esc_html__( '(Pro)', 'swift-dashboard-analytics' ) . '</span>';
						}
						?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
	if ( 'overview' === $tab ) {
		echo '<div class="sd-an-toolbar">';
		swiftdash_render_analytics_note();
		printf(
			'<button type="button" class="sd-btn sd-btn--tertiary sd-btn--sm" data-sd-modal-open="sd-customize">%s%s</button>',
			swiftdash_icon( 'eye-slash' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
			esc_html__( 'Customize', 'swift-dashboard-analytics' )
		);
		echo '</div>';
		swiftdash_render_screen_widgets( 'analytics' );
		swiftdash_render_customize_panel( 'analytics' );
	} elseif ( has_action( 'swiftdash_analytics_tab_' . $tab ) ) {
		/**
		 * Prints the body of an analytics tab (the Pro add-on fills these).
		 *
		 * @param int $range Selected range in days.
		 */
		do_action( 'swiftdash_analytics_tab_' . $tab, $range );
	} else {
		swiftdash_render_analytics_locked_tab( $tab, $tabs[ $tab ] );
	}
}

/**
 * One-line note on how visits are counted.
 */
function swiftdash_render_analytics_note() {
	echo '<p class="sd-an-note">' . swiftdash_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
	esc_html_e( 'Counted without cookies on your own site.', 'swift-dashboard-analytics' );
	if ( swiftdash_setting( 'exclude_admins' ) ) {
		echo ' ' . esc_html__( 'Logged-in users are not counted.', 'swift-dashboard-analytics' );
	}
	echo '</p>';
}

/**
 * Locked panel for a Pro analytics tab.
 *
 * @param string $key Tab key.
 * @param array  $tab Tab definition.
 */
function swiftdash_render_analytics_locked_tab( $key, $tab ) {
	$url = add_query_arg(
		array(
			'utm_source'  => 'plugin',
			'utm_medium'  => 'locked-tab',
			'utm_content' => $key,
		),
		SWIFTDASH_PRICING_URL
	);
	?>
	<section class="sd-card sd-an-locked" aria-labelledby="sd-an-locked-title">
		<header class="sd-card__head">
			<h2 class="sd-card__title" id="sd-an-locked-title"><?php echo esc_html( $tab['label'] ); ?></h2>
			<span class="sd-badge sd-badge--pro"><?php esc_html_e( 'Pro', 'swift-dashboard-analytics' ); ?></span>
		</header>
		<div class="sd-card__body sd-an-locked__body">
			<div class="sd-an-locked__preview" aria-hidden="true">
				<div class="sd-ph sd-ph--chart"></div>
			</div>
			<div class="sd-an-locked__info">
				<span class="sd-locked__icon"><?php echo swiftdash_icon( 'lock-simple' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?></span>
				<p><?php esc_html_e( 'This report is part of Swift Dash Pro. It shows:', 'swift-dashboard-analytics' ); ?></p>
				<ul class="sd-an-locked__list">
					<?php foreach ( $tab['items'] as $item ) : ?>
						<li><?php echo swiftdash_icon( 'check-circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
				<a class="sd-btn sd-btn--primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Unlock with Pro', 'swift-dashboard-analytics' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'swift-dashboard-analytics' ); ?></span>
				</a>
			</div>
		</div>
	</section>
	<?php
}
