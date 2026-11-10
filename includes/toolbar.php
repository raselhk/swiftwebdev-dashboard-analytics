<?php
/**
 * Toolbar (admin bar) items for permitted users: live visitors with a mini dashboard on hover,
 * and the navigation search. Shown in wp-admin and on the front end; each can be switched off.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a toolbar item shows for the current request.
 *
 * Search shows in wp-admin only: on the site, WordPress already puts its own search icon in the toolbar.
 *
 * @param string $item live|search.
 * @return bool
 */
function swiftdash_toolbar_on( $item ) {
	if ( 'search' === $item && ! is_admin() ) {
		return false;
	}
	return is_admin_bar_showing() && current_user_can( swiftdash_capability() ) && (bool) swiftdash_setting( 'toolbar_' . $item );
}

/**
 * Distinct visitors with a hit in the last 5 minutes (uncached).
 *
 * @return int
 */
function swiftdash_live_online() {
	global $wpdb;
	return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT COUNT(DISTINCT visitor) FROM %i WHERE created > %s', swiftdash_table( 'hits' ), gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS ) )
	);
}

/**
 * The live count, cached 10 seconds.
 *
 * @return array { online: int, time: int (when it was counted) }.
 */
function swiftdash_live_count() {
	$cached = get_transient( 'swiftdash_live_count' );
	if ( is_array( $cached ) && isset( $cached['online'], $cached['time'] ) ) {
		return $cached;
	}
	$count = array(
		'online' => swiftdash_live_online(),
		'time'   => time(),
	);
	set_transient( 'swiftdash_live_count', $count, 10 );
	return $count;
}

/**
 * Everything the live panel shows, cached 10 seconds.
 *
 * @return array {
 *     @type int     $online   Visitors in the last 5 minutes.
 *     @type int     $time     When this was computed (Unix time).
 *     @type int[]   $minutes  Distinct visitors per minute, last 30 minutes, oldest first.
 *     @type int|null $last_ago Seconds since the latest hit, null when none in 48 hours.
 *     @type array   $today    visitors, views, visitors_change, views_change ({ tone, text }) vs yesterday at the same time.
 *     @type array   $ai       visitors (AI assistant visits today), bots (AI bot visits today).
 *     @type array[] $pages    Top 3 pages today: title, path, count.
 *     @type array[] $sources  Top sources today: label, count, brand ({ color, path, letter } or null), direct (bool).
 * }
 */
function swiftdash_live_full() {
	$cached = get_transient( 'swiftdash_live_full' );
	if ( is_array( $cached ) && isset( $cached['time'] ) ) {
		return $cached;
	}
	global $wpdb;
	$hits  = swiftdash_table( 'hits' );
	$now   = time();
	$fmt   = 'Y-m-d H:i:s';
	$today = new DateTimeImmutable( 'today', wp_timezone() );

	$count = array(
		'online' => swiftdash_live_online(),
		'time'   => $now,
	);
	set_transient( 'swiftdash_live_count', $count, 10 );

	$minutes = array_fill( 0, 30, 0 );
	$rows    = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT TIMESTAMPDIFF(MINUTE, created, %s) AS ago, COUNT(DISTINCT visitor) AS n FROM %i WHERE created > %s GROUP BY ago', gmdate( $fmt, $now ), $hits, gmdate( $fmt, $now - 30 * MINUTE_IN_SECONDS ) ),
		ARRAY_A
	);
	foreach ( (array) $rows as $row ) {
		$ago = (int) $row['ago'];
		if ( $ago >= 0 && $ago < 30 ) {
			$minutes[ 29 - $ago ] = (int) $row['n'];
		}
	}
	$last = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(created) FROM %i', $hits ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$day       = swiftdash_live_day( $today->format( 'Y-m-d' ) );
	$yesterday = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT COUNT(DISTINCT visitor) AS visitors, COUNT(*) AS views FROM %i WHERE created >= %s AND created < %s', $hits, swiftdash_day_bounds( $today->modify( '-1 day' )->format( 'Y-m-d' ) )[0], gmdate( $fmt, $now - DAY_IN_SECONDS ) ),
		ARRAY_A
	);
	$change    = function ( $current, $previous ) {
		if ( $previous <= 0 ) {
			return array(
				'tone' => 'flat',
				'text' => '',
			);
		}
		$c = swiftdash_kpi_change( $current, $previous );
		return array(
			'tone' => $c['tone'],
			'text' => ( 'good' === $c['tone'] ? '+' : ( 'bad' === $c['tone'] ? "\u{2212}" : '' ) ) . ( 'flat' === $c['tone'] ? '0%' : $c['text'] ),
		);
	};

	$pages = array();
	foreach ( swiftdash_get_top( 'page', 1, 3 ) as $top ) {
		$title   = swiftdash_path_title( $top['item'] );
		$pages[] = array(
			'title' => '' !== $title ? $title : $top['item'],
			'path'  => $top['item'],
			'count' => $top['count'],
		);
	}

	// Sources: referrers merged by brand (google.com + google.co.uk), plus Direct = sessions without one.
	$brands  = swiftdash_brands();
	$sources = array();
	$sum     = 0;
	foreach ( swiftdash_get_top( 'referrer', 1, 100 ) as $top ) {
		$key  = swiftdash_referrer_brand( $top['item'] );
		$id   = '' !== $key ? $key : $top['item'];
		$sum += $top['count'];
		if ( ! isset( $sources[ $id ] ) ) {
			$sources[ $id ] = array(
				'label'  => '' !== $key ? $brands[ $key ][0] : $top['item'],
				'count'  => 0,
				'brand'  => '' !== $key ? array(
					'color'  => '#' . $brands[ $key ][1],
					'path'   => $brands[ $key ][2],
					'letter' => mb_substr( $brands[ $key ][0], 0, 1 ),
				) : null,
				'direct' => false,
			);
		}
		$sources[ $id ]['count'] += $top['count'];
	}
	$direct = $day['sessions'] - $sum;
	if ( $direct > 0 ) {
		$sources['direct'] = array(
			'label'  => __( 'Direct', 'swiftwebdev-dashboard-analytics' ),
			'count'  => $direct,
			'brand'  => null,
			'direct' => true,
		);
	}
	usort(
		$sources,
		function ( $a, $b ) {
			return $b['count'] - $a['count'];
		}
	);

	$payload = array(
		'online'   => $count['online'],
		'time'     => $now,
		'minutes'  => $minutes,
		'last_ago' => $last ? max( 0, $now - strtotime( $last . ' UTC' ) ) : null,
		'today'    => array(
			'visitors'        => $day['visitors'],
			'views'           => $day['views'],
			'visitors_change' => $change( $day['visitors'], (int) $yesterday['visitors'] ),
			'views_change'    => $change( $day['views'], (int) $yesterday['views'] ),
		),
		'ai'       => array(
			'visitors' => swiftdash_dims_period( 'ai', 1 )['total'],
			'bots'     => swiftdash_dims_period( 'aibot', 1 )['total'],
		),
		'pages'    => $pages,
		'sources'  => array_slice( $sources, 0, 5 ),
	);

	/**
	 * Filters the live panel data (GET swiftdash/v1/live?full=1) before it is cached for 10 seconds.
	 * Add-ons can add keys here and draw them on the swiftdash:live event of the toolbar panel.
	 *
	 * @param array $payload See swiftdash_live_full().
	 */
	$payload = (array) apply_filters( 'swiftdash_live_payload', $payload );
	set_transient( 'swiftdash_live_full', $payload, 10 );
	return $payload;
}

/**
 * Register GET swiftdash/v1/live and GET swiftdash/v1/search/commands.
 */
function swiftdash_toolbar_routes() {
	$permission = function () {
		return current_user_can( swiftdash_capability() );
	};
	register_rest_route(
		'swiftdash/v1',
		'/live',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => $permission,
			'callback'            => 'swiftdash_live_rest',
			'args'                => array(
				'full' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);
	register_rest_route(
		'swiftdash/v1',
		'/search/commands',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => $permission,
			'callback'            => 'swiftdash_search_commands_rest',
		)
	);
}
add_action( 'rest_api_init', 'swiftdash_toolbar_routes' );

/**
 * GET /live: the count only, or with full=1 everything the panel shows. age = seconds since counted.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function swiftdash_live_rest( $request ) {
	$data        = $request['full'] ? swiftdash_live_full() : swiftdash_live_count();
	$data['age'] = max( 0, time() - (int) $data['time'] );
	return rest_ensure_response( $data );
}

/**
 * GET /search/commands: the toolbar search command list (the admin menu comes from the copy saved on
 * the user's last admin page view, because REST requests have no admin menu) and the user's prefs.
 *
 * @return WP_REST_Response
 */
function swiftdash_search_commands_rest() {
	$menu = get_user_meta( get_current_user_id(), 'swiftdash_menu', true );
	return rest_ensure_response(
		array(
			'commands' => swiftdash_search_commands( is_array( $menu ) ? $menu : array() ),
			'prefs'    => swiftdash_prefs(),
		)
	);
}

/**
 * Save the user's admin menu destinations for the toolbar search, only when they changed.
 */
function swiftdash_toolbar_save_menu() {
	if ( ! swiftdash_toolbar_on( 'search' ) ) {
		return;
	}
	$user = get_current_user_id();
	$menu = swiftdash_destinations();
	if ( get_user_meta( $user, 'swiftdash_menu', true ) !== $menu ) {
		update_user_meta( $user, 'swiftdash_menu', $menu );
	}
}
add_action( 'adminmenu', 'swiftdash_toolbar_save_menu' );

/**
 * Add the toolbar items.
 *
 * @param WP_Admin_Bar $bar Toolbar.
 */
function swiftdash_toolbar_live_node( $bar ) {
	if ( ! swiftdash_toolbar_on( 'live' ) ) {
		return;
	}
	$count  = swiftdash_live_count();
	$online = (int) $count['online'];
	$bar->add_node(
		array(
			'id'    => 'swiftdash-live',
			'title' => '',
			'meta'  => array(
				'class' => 'sdtb-item',
				'html'  => swiftdash_toolbar_live_html( $online ),
			),
		)
	);
}
add_action( 'admin_bar_menu', 'swiftdash_toolbar_live_node', 95 );

/**
 * Search icon, placed just before "Howdy".
 *
 * @param WP_Admin_Bar $bar Toolbar.
 */
function swiftdash_toolbar_search_node( $bar ) {
	if ( ! swiftdash_toolbar_on( 'search' ) ) {
		return;
	}
	$bar->add_node(
		array(
			'id'     => 'swiftdash-search',
			'parent' => 'top-secondary',
			'title'  => '',
			'meta'   => array(
				'class' => 'sdtb-item',
				'html'  => swiftdash_toolbar_search_html(),
			),
		)
	);
}
add_action( 'admin_bar_menu', 'swiftdash_toolbar_search_node', 3 );

/**
 * Magnifier icon.
 *
 * @return string
 */
function swiftdash_toolbar_magnifier() {
	return '<svg class="sdtb-svg" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';
}

/**
 * Live item markup: the button (count rendered here, so it does not jump) and the empty panel.
 *
 * @param int $online Visitors online.
 * @return string
 */
function swiftdash_toolbar_live_html( $online ) {
	ob_start();
	?>
	<button type="button" class="ab-item sdtb-live" aria-expanded="false" aria-controls="sdtb-live-panel">
		<span class="sdtb-dot<?php echo $online ? '' : ' is-quiet'; ?>" aria-hidden="true"></span>
		<span class="sdtb-live__n" data-n="<?php echo esc_attr( (string) $online ); ?>"><?php echo esc_html( number_format_i18n( $online ) ); ?></span><span class="sdtb-live__word"> <?php esc_html_e( 'online', 'swiftwebdev-dashboard-analytics' ); ?></span><span class="sdtb-sr"><?php esc_html_e( ', show live visitor stats', 'swiftwebdev-dashboard-analytics' ); ?></span>
	</button>
	<div class="sdtb-backdrop" hidden></div>
	<div class="sdtb-panel" id="sdtb-live-panel" role="dialog" aria-label="<?php esc_attr_e( 'Live visitors', 'swiftwebdev-dashboard-analytics' ); ?>" hidden>
		<span class="sdtb-panel__grip" aria-hidden="true"></span>
		<button type="button" class="sdtb-panel__close" aria-label="<?php esc_attr_e( 'Close', 'swiftwebdev-dashboard-analytics' ); ?>"><svg class="sdtb-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
		<div class="sdtb-panel__body"></div>
		<div class="sdtb-panel__foot">
			<a class="sdtb-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=swift-dash' ) ); ?>"><?php esc_html_e( 'Open dashboard', 'swiftwebdev-dashboard-analytics' ); ?><svg class="sdtb-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
			<a class="sdtb-link" href="<?php echo esc_url( admin_url( 'admin.php?page=swift-dash-settings#sd-toolbar-live' ) ); ?>"><?php esc_html_e( 'Toolbar settings', 'swiftwebdev-dashboard-analytics' ); ?></a>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Search item markup: the icon button and the field it expands into.
 *
 * @return string
 */
function swiftdash_toolbar_search_html() {
	$label = __( 'Search posts, pages, users and screens', 'swiftwebdev-dashboard-analytics' );
	ob_start();
	?>
	<button type="button" class="ab-item sdtb-search__btn" aria-expanded="false" aria-controls="sdtb-search" aria-label="<?php echo esc_attr( $label ); ?>"><?php echo swiftdash_toolbar_magnifier(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></button>
	<div class="sdtb-search" id="sdtb-search" role="search" hidden>
		<label class="sdtb-sr" for="sdtb-search-input"><?php echo esc_html( $label ); ?></label>
		<?php echo swiftdash_toolbar_magnifier(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
		<input type="search" id="sdtb-search-input" class="sdtb-search__input" placeholder="<?php esc_attr_e( 'Search posts, pages, users, tools...', 'swiftwebdev-dashboard-analytics' ); ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="sdtb-search-results" aria-autocomplete="list">
		<kbd class="sdtb-search__esc" aria-hidden="true">Esc</kbd>
		<div class="sdtb-search__panel" hidden>
			<ul class="sdtb-search__results" id="sdtb-search-results" role="listbox" aria-label="<?php esc_attr_e( 'Search results', 'swiftwebdev-dashboard-analytics' ); ?>"></ul>
			<p class="sdtb-search__foot" aria-hidden="true">
				<span><kbd>&uarr;&darr;</kbd> <?php esc_html_e( 'to move', 'swiftwebdev-dashboard-analytics' ); ?></span>
				<span><kbd>Enter</kbd> <?php esc_html_e( 'to open', 'swiftwebdev-dashboard-analytics' ); ?></span>
				<span><kbd>Esc</kbd> <?php esc_html_e( 'to close', 'swiftwebdev-dashboard-analytics' ); ?></span>
			</p>
		</div>
		<p class="sdtb-sr" aria-live="polite" data-sd-search-status></p>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Toolbar CSS/JS, only when an item renders.
 */
function swiftdash_toolbar_assets() {
	$live   = swiftdash_toolbar_on( 'live' );
	$search = swiftdash_toolbar_on( 'search' );
	if ( ! $live && ! $search ) {
		return;
	}
	wp_enqueue_style( 'swiftdash-toolbar', SWIFTDASH_URL . 'assets/css/toolbar.css', array( 'admin-bar' ), SWIFTDASH_VERSION );
	$deps = array( 'wp-api-fetch', 'wp-i18n' );
	if ( $search ) {
		$deps[] = 'swiftdash-search';
	}
	wp_enqueue_script( 'swiftdash-toolbar', SWIFTDASH_URL . 'assets/js/toolbar.js', $deps, SWIFTDASH_VERSION, true );
	wp_set_script_translations( 'swiftdash-toolbar', 'swiftwebdev-dashboard-analytics' );
	wp_add_inline_script(
		'swiftdash-toolbar',
		'window.swiftdashToolbar = ' . wp_json_encode(
			array(
				'live'     => $live,
				'search'   => $search,
				'adminUrl' => admin_url(),
				'iconUrl'  => SWIFTDASH_URL . 'assets/icons.svg',
			)
		) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'swiftdash_toolbar_assets' );
add_action( 'wp_enqueue_scripts', 'swiftdash_toolbar_assets' );
