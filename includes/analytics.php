<?php
/**
 * Visitor widgets on the dashboard: KPI cards, visitors over time, busiest days and engagement.
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
			'title' => __( 'Visitors', 'swiftwebdev-dashboard-analytics' ),
			'tip'   => __( 'Unique people who visited your site in this period, counted without cookies.', 'swiftwebdev-dashboard-analytics' ),
			'tone'  => '',
		),
		'page-views' => array(
			'key'   => 'views',
			'title' => __( 'Page Views', 'swiftwebdev-dashboard-analytics' ),
			'tip'   => __( 'Total pages viewed, including repeat views of the same page.', 'swiftwebdev-dashboard-analytics' ),
			'tone'  => 'purple',
		),
		'sessions'   => array(
			'key'   => 'sessions',
			'title' => __( 'Sessions', 'swiftwebdev-dashboard-analytics' ),
			'tip'   => __( 'Separate visits to your site. One person can have several sessions.', 'swiftwebdev-dashboard-analytics' ),
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
 * Periods the dashboard visitor widgets offer, in days => label. Daily totals are kept long
 * enough to compare the longest period with the one before it (see swiftdash_rollup()).
 *
 * @return string[]
 */
function swiftdash_analytics_ranges() {
	return array(
		7  => __( 'Last 7 days', 'swiftwebdev-dashboard-analytics' ),
		30 => __( 'Last 30 days', 'swiftwebdev-dashboard-analytics' ),
		90 => __( 'Last 90 days', 'swiftwebdev-dashboard-analytics' ),
	);
}

/**
 * Selected period in days.
 *
 * @return int
 */
function swiftdash_analytics_range() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter.
	$range = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : 30;
	return array_key_exists( $range, swiftdash_analytics_ranges() ) ? $range : 30;
}

/**
 * Period picker for the dashboard header. Changing it reloads the page (app.js).
 */
function swiftdash_render_range_picker() {
	$range = swiftdash_analytics_range();
	?>
	<form class="sd-daterange" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" data-sd-range-form>
		<input type="hidden" name="page" value="swift-dash">
		<label class="screen-reader-text" for="sd-range"><?php esc_html_e( 'Period for visitor stats', 'swiftwebdev-dashboard-analytics' ); ?></label>
		<select class="sd-select" id="sd-range" name="range">
			<?php foreach ( swiftdash_analytics_ranges() as $days => $label ) : ?>
				<option value="<?php echo esc_attr( $days ); ?>" <?php selected( $range, $days ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<noscript><button type="submit" class="sd-btn sd-btn--secondary"><?php esc_html_e( 'Apply', 'swiftwebdev-dashboard-analytics' ); ?></button></noscript>
	</form>
	<?php
}

/**
 * Register the six visitor widgets on the dashboard: three KPI cards, visitors over time,
 * busiest days and engagement. All follow the selected period.
 */
function swiftdash_analytics_register_widgets() {
	$range = swiftdash_analytics_range();
	$order = 10;
	foreach ( swiftdash_kpi_metrics() as $id => $metric ) {
		swiftdash_register_widget(
			$id,
			array(
				'title'       => $metric['title'],
				'description' => $metric['tip'],
				'area'        => 'kpis',
				'order'       => $order,
				'span'        => 4,
				'render'      => 'swiftdash_render_kpi_widget',
				'metric'      => $id,
				'days'        => $range,
			)
		);
		$order += 10;
	}

	$reports = array(
		'visitor-trend'      => array( __( 'Visitors over time', 'swiftwebdev-dashboard-analytics' ), __( 'Visitors and page views for each day of the selected period.', 'swiftwebdev-dashboard-analytics' ), 'overview', 10, 8, 'swiftdash_render_trend_widget' ),
		'visitor-weekdays'   => array( __( 'Busiest days', 'swiftwebdev-dashboard-analytics' ), __( 'Average visitors on each day of the week.', 'swiftwebdev-dashboard-analytics' ), 'overview', 20, 4, 'swiftdash_render_weekdays_widget' ),
		'visitor-engagement' => array( __( 'Engagement', 'swiftwebdev-dashboard-analytics' ), __( 'How much people read and how often they come back.', 'swiftwebdev-dashboard-analytics' ), 'overview', 30, 12, 'swiftdash_render_engagement_widget' ),
	);
	foreach ( $reports as $id => $r ) {
		swiftdash_register_widget(
			$id,
			array(
				'title'       => $r[0],
				'description' => $r[1],
				'area'        => $r[2],
				'order'       => $r[3],
				'span'        => $r[4],
				'render'      => $r[5],
				'days'        => $range,
			)
		);
	}
}
add_action( 'swiftdash_register_widgets', 'swiftdash_analytics_register_widgets' );

/**
 * Register the visitor widget styles (add-ons can enqueue the handle on their own screens)
 * and load them on the dashboard.
 *
 * @param string $screen_key Current screen key.
 */
function swiftdash_analytics_assets( $screen_key ) {
	wp_register_style( 'swiftdash-analytics', SWIFTDASH_URL . 'assets/css/analytics.css', array( 'swiftdash-app' ), SWIFTDASH_VERSION );
	if ( 'dashboard' === $screen_key ) {
		wp_enqueue_style( 'swiftdash-analytics' );
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
			'text' => $current > 0 ? __( 'New', 'swiftwebdev-dashboard-analytics' ) : __( 'No change', 'swiftwebdev-dashboard-analytics' ),
			'sr'   => '',
		);
	}
	$pct = (int) round( ( $current - $previous ) / $previous * 100 );
	if ( 0 === $pct ) {
		return array(
			'tone' => 'flat',
			'icon' => '',
			'text' => __( 'No change', 'swiftwebdev-dashboard-analytics' ),
			'sr'   => '',
		);
	}
	return array(
		'tone' => $pct > 0 ? 'good' : 'bad',
		'icon' => $pct > 0 ? 'arrow-up' : 'arrow-down',
		/* translators: %s: percentage number. */
		'text' => sprintf( __( '%s%%', 'swiftwebdev-dashboard-analytics' ), number_format_i18n( abs( $pct ) ) ),
		'sr'   => $pct > 0 ? __( 'Up', 'swiftwebdev-dashboard-analytics' ) : __( 'Down', 'swiftwebdev-dashboard-analytics' ),
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
 * KPI card body.
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
			<p class="sd-an-kpi__empty"><?php esc_html_e( 'No visits recorded yet', 'swiftwebdev-dashboard-analytics' ); ?></p>
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
								__( 'Daily trend over the last %1$s days: lowest %2$s, highest %3$s.', 'swiftwebdev-dashboard-analytics' ),
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
						echo wp_kses( swiftdash_icon( $change['icon'] ), swiftdash_icon_kses() );
						echo '<span class="screen-reader-text">' . esc_html( $change['sr'] ) . ' </span>';
					}
					echo esc_html( $change['text'] );
					?>
				</span>
				<?php
				/* translators: %s: number of days. */
				echo esc_html( sprintf( _n( 'vs previous %s day', 'vs previous %s days', $days, 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $days ) ) );
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
		? __( 'Stats start with the next visit. Logged-in users are not counted.', 'swiftwebdev-dashboard-analytics' )
		: __( 'Stats start with the next visit.', 'swiftwebdev-dashboard-analytics' );
}

/**
 * Days of the period that count toward averages: on or after the first recorded day.
 *
 * @param array $stats See swiftdash_get_stats().
 * @return array[] Daily rows.
 */
function swiftdash_analytics_active_days( $stats ) {
	$since = (string) $stats['since'];
	return array_values(
		array_filter(
			$stats['daily'],
			function ( $row ) use ( $since ) {
				return $row['date'] >= $since;
			}
		)
	);
}

/**
 * Empty state shared by the visitor report widgets.
 */
function swiftdash_render_report_empty() {
	echo '<p class="sd-an-kpi__empty">' . esc_html__( 'No visits recorded yet', 'swiftwebdev-dashboard-analytics' ) . '</p>';
	echo '<p class="sd-field__help">' . esc_html( swiftdash_analytics_empty_help() ) . '</p>';
}

/**
 * Visitors over time: a bar per day with page views as a line.
 *
 * @param array $widget Widget args (days).
 */
function swiftdash_render_trend_widget( $widget ) {
	$stats = swiftdash_analytics_stats( (int) $widget['days'] );
	if ( empty( $stats['has_data'] ) ) {
		swiftdash_render_report_empty();
		return;
	}
	$buckets = $stats['daily'];
	$n       = count( $buckets );
	$max     = max( 1, max( wp_list_pluck( $buckets, 'views' ) ), max( wp_list_pluck( $buckets, 'visitors' ) ) );
	$height  = 160;
	$points  = array();
	$format  = get_option( 'date_format' );
	$label   = sprintf(
		/* translators: 1: total visitors, 2: total page views. */
		__( 'Visitors over time. %1$s visitors and %2$s page views in this period.', 'swiftwebdev-dashboard-analytics' ),
		number_format_i18n( $stats['totals']['visitors'] ),
		number_format_i18n( $stats['totals']['views'] )
	);
	?>
	<div class="sd-an-trend">
		<ul class="sd-an-legend" aria-hidden="true">
			<li class="sd-an-legend__visitors"><?php esc_html_e( 'Visitors', 'swiftwebdev-dashboard-analytics' ); ?></li>
			<li class="sd-an-legend__views"><?php esc_html_e( 'Page views', 'swiftwebdev-dashboard-analytics' ); ?></li>
			<li class="sd-an-legend__unit"><?php esc_html_e( 'Per day', 'swiftwebdev-dashboard-analytics' ); ?></li>
		</ul>
		<div class="sd-an-trend__plot">
			<span class="sd-an-trend__max" aria-hidden="true"><?php echo esc_html( number_format_i18n( $max ) ); ?></span>
			<svg class="sd-an-trend__svg" viewBox="0 0 <?php echo (int) ( $n * 10 ); ?> <?php echo (int) $height; ?>" preserveAspectRatio="none" role="img" aria-label="<?php echo esc_attr( $label ); ?>">
				<?php
				foreach ( $buckets as $i => $b ) {
					$h        = $b['visitors'] / $max * ( $height - 4 );
					$when     = date_i18n( $format, strtotime( $b['date'] ) );
					$points[] = ( $i * 10 + 5 ) . ',' . round( $height - $b['views'] / $max * ( $height - 4 ), 2 );
					printf(
						'<rect class="sd-an-trend__bar" x="%1$s" y="%2$s" width="7" height="%3$s" rx="1.5"><title>%4$s</title></rect>',
						esc_attr( $i * 10 + 1.5 ),
						esc_attr( round( $height - max( $h, 0.5 ), 2 ) ),
						esc_attr( round( max( $h, 0.5 ), 2 ) ),
						esc_html(
							sprintf(
								/* translators: 1: date, 2: visitors, 3: page views. */
								__( '%1$s: %2$s visitors, %3$s page views', 'swiftwebdev-dashboard-analytics' ),
								$when,
								number_format_i18n( $b['visitors'] ),
								number_format_i18n( $b['views'] )
							)
						)
					);
				}
				?>
				<polyline class="sd-an-trend__line" points="<?php echo esc_attr( implode( ' ', $points ) ); ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
			</svg>
		</div>
		<div class="sd-an-trend__axis" aria-hidden="true">
			<span><?php echo esc_html( date_i18n( $format, strtotime( $buckets[0]['date'] ) ) ); ?></span>
			<span><?php echo esc_html( date_i18n( $format, strtotime( $buckets[ (int) floor( $n / 2 ) ]['date'] ) ) ); ?></span>
			<span><?php esc_html_e( 'Today', 'swiftwebdev-dashboard-analytics' ); ?></span>
		</div>
	</div>
	<?php
}

/**
 * Busiest days: average visitors per weekday over the recorded days of the period.
 *
 * @param array $widget Widget args (days).
 */
function swiftdash_render_weekdays_widget( $widget ) {
	global $wp_locale;
	$stats = swiftdash_analytics_stats( (int) $widget['days'] );
	$days  = swiftdash_analytics_active_days( $stats );
	if ( empty( $stats['has_data'] ) || ! $days ) {
		swiftdash_render_report_empty();
		return;
	}
	$sum   = array_fill( 0, 7, 0 );
	$count = array_fill( 0, 7, 0 );
	foreach ( $days as $row ) {
		$w          = (int) gmdate( 'w', strtotime( $row['date'] . ' 12:00:00 UTC' ) );
		$sum[ $w ] += $row['visitors'];
		++$count[ $w ];
	}
	$avg   = array();
	$start = (int) get_option( 'start_of_week', 1 );
	for ( $k = 0; $k < 7; $k++ ) {
		$w         = ( $start + $k ) % 7;
		$avg[ $w ] = $count[ $w ] ? $sum[ $w ] / $count[ $w ] : 0;
	}
	$max  = max( $avg );
	$best = array_search( $max, $avg, true );
	if ( $max > 0 ) {
		/* translators: %s: weekday name. */
		echo '<p class="sd-an-weekdays__lead">' . esc_html( sprintf( __( '%s is usually your busiest day.', 'swiftwebdev-dashboard-analytics' ), $wp_locale->get_weekday( $best ) ) ) . '</p>';
	}
	echo '<ul class="sd-an-weekdays">';
	foreach ( $avg as $w => $value ) {
		printf(
			'<li%1$s><span class="sd-an-weekdays__day">%2$s</span><span class="sd-an-weekdays__track" aria-hidden="true"><span class="sd-an-weekdays__fill" style="width: %3$s%%"></span></span><span class="sd-an-weekdays__value">%4$s</span></li>',
			$max > 0 && $w === $best ? ' class="is-best"' : '',
			esc_html( $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $w ) ) ),
			esc_attr( $max > 0 ? round( $value / $max * 100, 1 ) : 0 ),
			/* translators: %s: average number of visitors. */
			esc_html( sprintf( __( '%s avg', 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $value, $value < 10 ? 1 : 0 ) ) )
		);
	}
	echo '</ul>';
	/* translators: %s: number of days. */
	echo '<p class="sd-field__help">' . esc_html( sprintf( _n( 'Based on %s day of visits.', 'Based on %s days of visits.', count( $days ), 'swiftwebdev-dashboard-analytics' ), number_format_i18n( count( $days ) ) ) ) . '</p>';
}

/**
 * Engagement: pages per visit, visits per visitor, average daily visitors and the best day.
 *
 * @param array $widget Widget args (days).
 */
function swiftdash_render_engagement_widget( $widget ) {
	$stats = swiftdash_analytics_stats( (int) $widget['days'] );
	$days  = swiftdash_analytics_active_days( $stats );
	$t     = $stats['totals'];
	if ( empty( $stats['has_data'] ) || ! $days || ! $t['views'] ) {
		swiftdash_render_report_empty();
		return;
	}
	$best  = array_reduce(
		$days,
		function ( $carry, $row ) {
			return null === $carry || $row['visitors'] > $carry['visitors'] ? $row : $carry;
		}
	);
	$daily = $t['visitors'] / count( $days );
	$items = array(
		array( 'file-text-fill', 'purple', __( 'Pages per visit', 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $t['sessions'] ? $t['views'] / $t['sessions'] : 0, 1 ), __( 'Pages viewed in an average visit', 'swiftwebdev-dashboard-analytics' ) ),
		array( 'arrows-clockwise-fill', 'green', __( 'Visits per visitor', 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $t['visitors'] ? $t['sessions'] / $t['visitors'] : 0, 1 ), __( 'Visits by the same person in a day', 'swiftwebdev-dashboard-analytics' ) ),
		array( 'users-fill', 'blue', __( 'Visitors per day', 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $daily, $daily < 10 ? 1 : 0 ), __( 'Average over the days with data', 'swiftwebdev-dashboard-analytics' ) ),
		array(
			'trend-up',
			'orange',
			__( 'Best day', 'swiftwebdev-dashboard-analytics' ),
			date_i18n( _x( 'M j', 'best day date format', 'swiftwebdev-dashboard-analytics' ), strtotime( $best['date'] ) ),
			/* translators: %s: number of visitors. */
			sprintf( _n( '%s visitor', '%s visitors', $best['visitors'], 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $best['visitors'] ) ),
		),
	);
	echo '<ul class="sd-an-engage">';
	foreach ( $items as $item ) {
		printf(
			'<li><span class="sd-an-engage__icon sd-kpi__icon--%1$s">%2$s</span><span class="sd-an-engage__text"><span class="sd-an-engage__label">%3$s</span><span class="sd-an-engage__value">%4$s</span><span class="sd-an-engage__hint">%5$s</span></span></li>',
			esc_attr( $item[1] ),
			wp_kses( swiftdash_icon( $item[0] ), swiftdash_icon_kses() ),
			esc_html( $item[2] ),
			esc_html( $item[3] ),
			esc_html( $item[4] )
		);
	}
	echo '</ul>';
}
