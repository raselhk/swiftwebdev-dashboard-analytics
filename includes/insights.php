<?php
/**
 * Insights: top pages, referrers and devices on the dashboard (AI cards: ai-search.php), a summary widget
 * on the WordPress dashboard and a Views column on the Posts and Pages lists.
 * Data comes from the daily totals written by swiftdash_rest_hit() (swiftdash_get_top()).
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the insight widgets in their own area below the 1.0 cards. Top pages steps aside
 * when an add-on shows its own Top Pages card. All follow the selected period.
 */
function swiftdash_insights_register_widgets() {
	$range   = swiftdash_analytics_range();
	$widgets = array(
		'insight-pages'     => array( __( 'Top pages', 'swiftwebdev-dashboard-analytics' ), __( 'The pages people view most.', 'swiftwebdev-dashboard-analytics' ), 'swiftdash_render_top_pages_widget' ),
		'insight-referrers' => array( __( 'Referrers', 'swiftwebdev-dashboard-analytics' ), __( 'Other websites that send you visitors.', 'swiftwebdev-dashboard-analytics' ), 'swiftdash_render_referrers_widget' ),
		'insight-devices'   => array( __( 'Devices', 'swiftwebdev-dashboard-analytics' ), __( 'Visits from phones, tablets and computers.', 'swiftwebdev-dashboard-analytics' ), 'swiftdash_render_devices_widget' ),
	);
	$order   = 10;
	foreach ( $widgets as $id => $w ) {
		swiftdash_register_widget(
			$id,
			array(
				'title'       => $w[0],
				'description' => $w[1],
				'area'        => 'insights',
				'order'       => $order,
				'span'        => 6,
				'render'      => $w[2],
				'days'        => $range,
				'replaced_by' => 'insight-pages' === $id ? 'top-pages' : '',
			)
		);
		$order += 10;
	}
}
add_action( 'swiftdash_register_widgets', 'swiftdash_insights_register_widgets' );

/**
 * Insight widget styles on the dashboard.
 *
 * @param string $screen_key Current screen key.
 */
function swiftdash_insights_assets( $screen_key ) {
	if ( 'dashboard' === $screen_key ) {
		wp_enqueue_style( 'swiftdash-insights', SWIFTDASH_URL . 'assets/css/insights.css', array( 'swiftdash-app' ), SWIFTDASH_VERSION );
	}
}
add_action( 'swiftdash_enqueue_assets', 'swiftdash_insights_assets' );

/**
 * Empty state: no visits at all yet, or none of this kind in the period.
 *
 * @param int    $days Period in days.
 * @param string $help Help line for the period case.
 */
function swiftdash_insights_empty( $days, $help ) {
	$stats = swiftdash_analytics_stats( $days );
	if ( empty( $stats['has_data'] ) ) {
		swiftdash_render_report_empty();
		return;
	}
	echo '<p class="sd-an-kpi__empty">' . esc_html__( 'Nothing recorded in this period yet', 'swiftwebdev-dashboard-analytics' ) . '</p>';
	echo '<p class="sd-field__help">' . esc_html( $help ) . '</p>';
}

/**
 * Ranked list with a bar per row, proportional to the largest count.
 *
 * @param array[] $rows Each: label, meta ('' for none), icon (safe SVG markup or ''), count, value (display text),
 *                      optional brand (brand key for swiftdash_render_brand_tile(), '' for unknown).
 */
function swiftdash_insights_list( $rows ) {
	$max = max( 1, max( wp_list_pluck( $rows, 'count' ) ) );
	echo '<ol class="sd-in-list">';
	foreach ( $rows as $row ) {
		$brand = isset( $row['brand'] );
		echo '<li class="sd-in-row' . ( $row['icon'] || $brand ? ' sd-in-row--icon' : '' ) . '">';
		if ( $brand ) {
			swiftdash_render_brand_tile( $row['brand'] );
		} elseif ( $row['icon'] ) {
			echo '<span class="sd-in-row__icon">' . wp_kses( $row['icon'], swiftdash_insights_svg_kses() ) . '</span>';
		}
		echo '<span class="sd-in-row__text"><span class="sd-in-row__label">' . esc_html( $row['label'] ) . '</span>';
		if ( '' !== $row['meta'] ) {
			echo '<span class="sd-in-row__meta">' . esc_html( $row['meta'] ) . '</span>';
		}
		echo '</span>';
		echo '<span class="sd-in-row__value">' . esc_html( $row['value'] ) . '</span>';
		printf( '<span class="sd-in-row__track" aria-hidden="true"><span class="sd-in-row__fill" style="width: %s%%"></span></span>', esc_attr( round( $row['count'] / $max * 100, 1 ) ) );
		echo '</li>';
	}
	echo '</ol>';
}

/**
 * Allowed tags for the device icons (the sprite has none).
 *
 * @return array[]
 */
function swiftdash_insights_svg_kses() {
	return array(
		'svg'  => array_fill_keys( array( 'class', 'aria-hidden', 'focusable', 'viewbox', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin' ), true ),
		'rect' => array_fill_keys( array( 'x', 'y', 'width', 'height', 'rx' ), true ),
		'path' => array( 'd' => true ),
	);
}

/**
 * Line icon for a device type.
 *
 * @param string $device mobile|tablet|desktop.
 * @return string SVG markup.
 */
function swiftdash_device_icon( $device ) {
	$shapes = array(
		'mobile'  => '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
		'tablet'  => '<rect x="4" y="2.5" width="16" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
		'desktop' => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M8.5 21h7M12 17v4"/>',
	);
	return '<svg class="sd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $shapes[ $device ] . '</svg>';
}

/**
 * Formatted count with a unit, e.g. "12 views".
 *
 * @param int  $count Count.
 * @param bool $views Page views (true) or visits (false).
 * @return string
 */
function swiftdash_insights_count( $count, $views ) {
	return $views
		/* translators: %s: number of page views. */
		? sprintf( _n( '%s view', '%s views', $count, 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $count ) )
		/* translators: %s: number of visits. */
		: sprintf( _n( '%s visit', '%s visits', $count, 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $count ) );
}

/**
 * Post title for a site path, or "Home page" for the front page; '' when none.
 *
 * @param string $path URL path.
 * @return string
 */
function swiftdash_path_title( $path ) {
	$id = url_to_postid( preg_replace( '#^(https?://[^/]+).*$#', '$1', home_url() ) . $path );
	if ( $id ) {
		return wp_strip_all_tags( get_the_title( $id ) );
	}
	return wp_make_link_relative( home_url( '/' ) ) === $path ? __( 'Home page', 'swiftwebdev-dashboard-analytics' ) : '';
}

/**
 * Top pages: path and post title (looked up for the shown rows only).
 *
 * @param array $widget Widget args (days).
 */
function swiftdash_render_top_pages_widget( $widget ) {
	$days = (int) $widget['days'];
	$top  = swiftdash_get_top( 'page', $days, 8 );
	if ( ! $top ) {
		swiftdash_insights_empty( $days, __( 'Page totals are collected from the next visit on.', 'swiftwebdev-dashboard-analytics' ) );
		return;
	}
	$rows = array();
	foreach ( $top as $t ) {
		$title  = swiftdash_path_title( $t['item'] );
		$rows[] = array(
			'label' => '' !== $title ? $title : $t['item'],
			'meta'  => '' !== $title ? $t['item'] : '',
			'icon'  => '',
			'count' => $t['count'],
			'value' => swiftdash_insights_count( $t['count'], true ),
		);
	}
	swiftdash_insights_list( $rows );
}

/**
 * Referrers: other websites visitors came from, once per visit.
 *
 * @param array $widget Widget args (days).
 */
function swiftdash_render_referrers_widget( $widget ) {
	$days = (int) $widget['days'];
	$top  = swiftdash_get_top( 'referrer', $days, 8 );
	if ( ! $top ) {
		swiftdash_insights_empty( $days, __( 'Websites that link to you show up here when someone clicks through. Direct visits are not listed.', 'swiftwebdev-dashboard-analytics' ) );
		return;
	}
	$rows   = array();
	$brands = swiftdash_brands();
	foreach ( $top as $t ) {
		$brand  = swiftdash_referrer_brand( $t['item'] );
		$rows[] = array(
			'label' => $brand ? $brands[ $brand ][0] : $t['item'],
			'meta'  => $brand ? $t['item'] : '',
			'icon'  => '',
			'brand' => $brand,
			'count' => $t['count'],
			'value' => swiftdash_insights_count( $t['count'], false ),
		);
	}
	swiftdash_insights_list( $rows );
}

/**
 * Devices: share of visits from phones, tablets and computers.
 *
 * @param array $widget Widget args (days).
 */
function swiftdash_render_devices_widget( $widget ) {
	$days   = (int) $widget['days'];
	$counts = wp_list_pluck( swiftdash_get_top( 'device', $days, 3 ), 'count', 'item' );
	$total  = array_sum( $counts );
	if ( ! $total ) {
		swiftdash_insights_empty( $days, __( 'Device totals are collected from the next visit on.', 'swiftwebdev-dashboard-analytics' ) );
		return;
	}
	$labels = array(
		'desktop' => __( 'Desktop', 'swiftwebdev-dashboard-analytics' ),
		'mobile'  => __( 'Mobile', 'swiftwebdev-dashboard-analytics' ),
		'tablet'  => __( 'Tablet', 'swiftwebdev-dashboard-analytics' ),
	);
	$rows   = array();
	foreach ( $labels as $device => $label ) {
		$count  = isset( $counts[ $device ] ) ? (int) $counts[ $device ] : 0;
		$rows[] = array(
			'label' => $label,
			'meta'  => swiftdash_insights_count( $count, false ),
			'icon'  => swiftdash_device_icon( $device ),
			'count' => $count,
			/* translators: %s: percentage number. */
			'value' => sprintf( __( '%s%%', 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $count / $total * 100 ) ),
		);
	}
	usort(
		$rows,
		function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		}
	);
	swiftdash_insights_list( $rows );
}

/**
 * Summary widget on the WordPress dashboard.
 */
function swiftdash_wp_dashboard_setup() {
	if ( current_user_can( swiftdash_capability() ) ) {
		wp_add_dashboard_widget( 'swiftdash_summary', __( 'SwiftWebDev Dashboard', 'swiftwebdev-dashboard-analytics' ), 'swiftdash_render_wp_dashboard_widget' );
	}
}
add_action( 'wp_dashboard_setup', 'swiftdash_wp_dashboard_setup' );

/**
 * Visitors today, 7 and 30 days, top 3 pages of the last 7 days.
 */
function swiftdash_render_wp_dashboard_widget() {
	$daily  = swiftdash_get_stats( 30 )['daily'];
	$counts = array(
		__( 'Today', 'swiftwebdev-dashboard-analytics' ) => end( $daily )['visitors'],
		__( 'Last 7 days', 'swiftwebdev-dashboard-analytics' ) => array_sum( wp_list_pluck( array_slice( $daily, -7 ), 'visitors' ) ),
		__( 'Last 30 days', 'swiftwebdev-dashboard-analytics' ) => array_sum( wp_list_pluck( $daily, 'visitors' ) ),
	);
	$top    = swiftdash_get_top( 'page', 7, 3 );
	?>
	<div class="activity-block">
		<h3><?php esc_html_e( 'Visitors', 'swiftwebdev-dashboard-analytics' ); ?></h3>
		<ul>
			<?php foreach ( $counts as $label => $count ) : ?>
				<li><?php echo esc_html( $label ); ?>: <strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<div class="activity-block">
		<h3><?php esc_html_e( 'Top pages, last 7 days', 'swiftwebdev-dashboard-analytics' ); ?></h3>
		<?php if ( $top ) : ?>
			<ul>
				<?php foreach ( $top as $t ) : ?>
					<?php $title = swiftdash_path_title( $t['item'] ); ?>
					<li><?php echo esc_html( '' !== $title ? $title : $t['item'] ); ?>: <strong><?php echo esc_html( swiftdash_insights_count( $t['count'], true ) ); ?></strong></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'No page views recorded yet.', 'swiftwebdev-dashboard-analytics' ); ?></p>
		<?php endif; ?>
	</div>
	<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=swift-dash' ) ); ?>"><?php esc_html_e( 'Open SwiftWebDev Dashboard', 'swiftwebdev-dashboard-analytics' ); ?></a></p>
	<?php
}

/**
 * Page views per path for the last 30 days, fetched once per request.
 *
 * @return int[] Lowercased path => views.
 */
function swiftdash_views_map() {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		// ponytail: loads every path of the last 30 days; cap is the LIMIT, per-screen IN() query if sites outgrow it.
		foreach ( swiftdash_get_top( 'page', 30, 20000 ) as $t ) {
			$key         = mb_strtolower( $t['item'] );
			$map[ $key ] = ( isset( $map[ $key ] ) ? $map[ $key ] : 0 ) + $t['count'];
		}
	}
	return $map;
}

/**
 * Add the Views column to Posts and Pages.
 *
 * @param string[] $columns Columns.
 * @return string[]
 */
function swiftdash_views_column( $columns ) {
	if ( current_user_can( swiftdash_capability() ) ) {
		$columns['swiftdash_views'] = '<span title="' . esc_attr__( 'Page views in the last 30 days', 'swiftwebdev-dashboard-analytics' ) . '">' . esc_html__( 'Views', 'swiftwebdev-dashboard-analytics' ) . '</span>';
	}
	return $columns;
}
add_filter( 'manage_post_posts_columns', 'swiftdash_views_column' );
add_filter( 'manage_page_posts_columns', 'swiftdash_views_column' );

/**
 * Print the Views cell.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function swiftdash_views_cell( $column, $post_id ) {
	if ( 'swiftdash_views' !== $column ) {
		return;
	}
	$views = 0;
	$path  = wp_make_link_relative( (string) get_permalink( $post_id ) );
	// Drafts and plain permalinks (?p=123) have no path of their own.
	if ( 'publish' === get_post_status( $post_id ) && false === strpbrk( $path, '?#' ) ) {
		$map   = swiftdash_views_map();
		$key   = mb_strtolower( rawurldecode( $path ) );
		$views = isset( $map[ $key ] ) ? $map[ $key ] : 0;
	}
	if ( $views ) {
		echo esc_html( number_format_i18n( $views ) );
	} else {
		echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'No views', 'swiftwebdev-dashboard-analytics' ) . '</span>';
	}
}
add_action( 'manage_post_posts_custom_column', 'swiftdash_views_cell', 10, 2 );
add_action( 'manage_page_posts_custom_column', 'swiftdash_views_cell', 10, 2 );

/**
 * Narrow Views column on the list screens.
 */
function swiftdash_views_column_style() {
	wp_add_inline_style( 'list-tables', '.fixed .column-swiftdash_views{width:5.5em}' );
}
add_action( 'load-edit.php', 'swiftdash_views_column_style' );
