<?php
/**
 * Widget registry and card rendering. Add-ons register their own widgets on swiftdash_register_widgets.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registry storage.
 *
 * @return array
 */
function &swiftdash_widget_registry() {
	static $widgets = array();
	return $widgets;
}

/**
 * Register (or replace) a widget.
 *
 * @param string $id   Unique widget id, [a-z0-9_-].
 * @param array  $args {
 *     Widget settings.
 *     @type string        $title       Card heading.
 *     @type string        $description One sentence: what the widget shows (tooltip and customize panel).
 *     @type string        $screen      Screen key: dashboard, analytics, speed, links.
 *     @type string        $area        Area within the screen.
 *     @type int           $order       Sort order inside the area.
 *     @type int           $span        Grid columns out of 12.
 *     @type callable      $render      Prints the card body. Widgets without one are not shown.
 *     @type callable|null $actions     Prints extra header content (status pill, links), right aligned.
 *     @type array|null    $kpi         KPI card style { icon, tone } for widgets not listed in swiftdash_kpi_card().
 *     @type string        $replaced_by Id of a widget that shows the same data; when it is registered on the
 *                                      same screen, this one is left out (one card per report).
 * }
 */
function swiftdash_register_widget( $id, $args ) {
	$registry              = &swiftdash_widget_registry();
	$registry[ $id ]       = wp_parse_args(
		$args,
		array(
			'id'          => $id,
			'title'       => '',
			'description' => '',
			'screen'      => 'dashboard',
			'area'        => 'main',
			'order'       => 10,
			'span'        => 12,
			'render'      => null,
			'actions'     => null,
			'kpi'         => null,
			'replaced_by' => '',
		)
	);
	$registry[ $id ]['id'] = $id;
}

/**
 * Widgets for one screen area, sorted.
 *
 * @param string      $screen Screen key.
 * @param string|null $area   Area, or null for all areas.
 * @return array
 */
function swiftdash_get_widgets( $screen, $area = null ) {
	$registry = swiftdash_widget_registry();
	$widgets  = array_filter(
		$registry,
		function ( $w ) use ( $screen, $area, $registry ) {
			$other = $w['replaced_by'] && isset( $registry[ $w['replaced_by'] ] ) ? $registry[ $w['replaced_by'] ] : null;
			if ( $other && $other['screen'] === $screen && is_callable( $other['render'] ) ) {
				return false;
			}
			return $w['screen'] === $screen && ( null === $area || $w['area'] === $area ) && is_callable( $w['render'] );
		}
	);
	uasort(
		$widgets,
		function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		}
	);
	return $widgets;
}

/**
 * KPI card style of a widget: its own 'kpi' args, else the built-in visitor KPIs
 * (the "an-" analytics copies share them).
 *
 * @param array $widget Widget args.
 * @return array|null { icon, tone } or null when the widget is not a KPI card.
 */
function swiftdash_kpi_card( $widget ) {
	if ( is_array( $widget['kpi'] ) && isset( $widget['kpi']['icon'], $widget['kpi']['tone'] ) ) {
		return $widget['kpi'];
	}
	$cards = array(
		'visitors'   => array( 'users-fill', 'blue' ),
		'page-views' => array( 'chart-bar-fill', 'purple' ),
		'sessions'   => array( 'clock-fill', 'green' ),
	);
	$key   = preg_replace( '/^an-/', '', $widget['id'] );
	return isset( $cards[ $key ] ) ? array_combine( array( 'icon', 'tone' ), $cards[ $key ] ) : null;
}

/**
 * Saved card order per screen for the current user.
 *
 * @return array Card ids per screen key, e.g. { dashboard: string[] }.
 */
function swiftdash_layout() {
	$saved  = get_user_meta( get_current_user_id(), 'swiftdash_layout', true );
	$layout = array( 'dashboard' => array() );
	foreach ( is_array( $saved ) ? $saved : array() as $screen => $ids ) {
		if ( is_array( $ids ) ) {
			$layout[ sanitize_key( $screen ) ] = array_values( array_filter( array_map( 'sanitize_key', $ids ) ) );
		}
	}
	return $layout;
}

/**
 * Register the per-user card order meta (saved through POST /wp/v2/users/me).
 */
function swiftdash_register_layout_meta() {
	$ids = array(
		'type'  => 'array',
		'items' => array(
			'type'    => 'string',
			'pattern' => '^[a-z0-9_-]+$',
		),
	);
	register_meta(
		'user',
		'swiftdash_layout',
		array(
			'type'          => 'object',
			'single'        => true,
			'default'       => array(
				'dashboard' => array(),
			),
			'auth_callback' => function ( $allowed, $meta_key, $user_id ) {
				return current_user_can( 'edit_user', $user_id );
			},
			'show_in_rest'  => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'dashboard' => $ids,
					),
					'additionalProperties' => $ids,
				),
			),
		)
	);
}
add_action( 'init', 'swiftdash_register_layout_meta' );

/**
 * Print every widget of a screen in one sortable 12-column grid (user order on top of the
 * default order: area by area, then widget order).
 *
 * @param string $screen_key Screen key, e.g. dashboard.
 */
function swiftdash_render_screen_widgets( $screen_key ) {
	$widgets = swiftdash_get_screen_widgets( $screen_key );
	if ( ! $widgets ) {
		return;
	}
	$defaults = wp_list_pluck( $widgets, 'id' );
	$layout   = swiftdash_layout();
	$saved    = isset( $layout[ $screen_key ] ) ? array_flip( $layout[ $screen_key ] ) : array();
	$count    = count( $saved );
	$rank     = array();
	foreach ( $defaults as $i => $id ) {
		// Saved cards keep their saved position; cards added later follow them in default order.
		$rank[ $id ] = isset( $saved[ $id ] ) ? $saved[ $id ] : $count + $i;
	}
	usort(
		$widgets,
		function ( $a, $b ) use ( $rank ) {
			return $rank[ $a['id'] ] <=> $rank[ $b['id'] ];
		}
	);

	$hidden = swiftdash_prefs()['hidden'];
	// The dashboard's promo slot sits after the second full row of visible cards.
	$promo = 'dashboard' === $screen_key;
	$spans = 0;
	printf(
		'<div class="sd-grid sd-grid--sortable" data-sd-sortable="%1$s" data-sd-default="%2$s">',
		esc_attr( $screen_key ),
		esc_attr( implode( ',', $defaults ) )
	);
	foreach ( $widgets as $widget ) {
		$is_hidden = in_array( $widget['id'], $hidden, true );
		swiftdash_render_widget( $widget, $is_hidden, true );
		$spans += $is_hidden ? 0 : absint( $widget['span'] );
		if ( $promo && $spans >= 24 && 0 === $spans % 12 ) {
			swiftdash_render_upgrade( $screen_key );
			$promo = false;
		}
	}
	if ( $promo ) {
		swiftdash_render_upgrade( $screen_key );
	}
	echo '</div>';
	echo '<p class="screen-reader-text" id="sd-drag-help">' . esc_html__( 'Drag to move this card, or press the up and down arrow keys.', 'swiftwebdev-dashboard-analytics' ) . '</p>';
	echo '<div class="screen-reader-text" aria-live="polite" data-sd-sort-live></div>';
}

/**
 * Card order assets on screens with a sortable widget grid.
 *
 * @param string $screen_key Current screen key.
 */
function swiftdash_enqueue_sortable( $screen_key ) {
	/**
	 * Filters the screen keys that print a sortable widget grid.
	 *
	 * @param string[] $screens Default array( 'dashboard' ).
	 */
	if ( ! in_array( $screen_key, (array) apply_filters( 'swiftdash_sortable_screens', array( 'dashboard' ) ), true ) ) {
		return;
	}
	wp_enqueue_script( 'swiftdash-sortable-lib', SWIFTDASH_URL . 'assets/vendor/sortable/Sortable.min.js', array(), '1.15.7', true );
	wp_enqueue_script( 'swiftdash-widgets-sortable', SWIFTDASH_URL . 'assets/js/widgets-sortable.js', array( 'swiftdash-app', 'wp-api-fetch', 'wp-i18n', 'swiftdash-sortable-lib' ), SWIFTDASH_VERSION, true );
	wp_set_script_translations( 'swiftdash-widgets-sortable', 'swiftwebdev-dashboard-analytics' );
	wp_add_inline_script(
		'swiftdash-widgets-sortable',
		'window.swiftdashLayout = ' . wp_json_encode( swiftdash_layout() ) . ';',
		'before'
	);
}
add_action( 'swiftdash_enqueue_assets', 'swiftdash_enqueue_sortable' );

/**
 * Print one widget card.
 *
 * @param array $widget   Widget args.
 * @param bool  $hidden   Hidden by the user's customize panel.
 * @param bool  $sortable Print a drag handle in the header.
 */
function swiftdash_render_widget( $widget, $hidden = false, $sortable = false ) {
	$widget = wp_parse_args(
		$widget,
		array(
			'description' => '',
			'span'        => 12,
			'actions'     => null,
			'kpi'         => null,
		)
	);
	$id     = 'sd-w-' . $widget['id'];
	$kpi    = swiftdash_kpi_card( $widget );
	$class  = 'sd-card sd-widget sd-span-' . absint( $widget['span'] ) . ( $kpi ? ' sd-widget--kpi' : '' );

	printf(
		'<section class="%1$s" id="%2$s" data-sd-widget="%3$s" aria-labelledby="%2$s-title"%4$s>',
		esc_attr( $class ),
		esc_attr( $id ),
		esc_attr( $widget['id'] ),
		$hidden ? ' hidden' : ''
	);
	if ( $kpi ) {
		printf( '<span class="sd-kpi__icon sd-kpi__icon--%s">%s</span>', esc_attr( $kpi['tone'] ), wp_kses( swiftdash_icon( $kpi['icon'] ), swiftdash_icon_kses() ) );
	}
	echo '<header class="sd-card__head">';
	printf( '<h2 class="sd-card__title" id="%s-title">%s</h2>', esc_attr( $id ), esc_html( $widget['title'] ) );
	if ( $kpi && $widget['description'] ) {
		printf(
			'<button type="button" class="sd-tip" aria-label="%1$s" aria-describedby="%2$s-tip">%3$s<span class="sd-tip__text" role="tooltip" id="%2$s-tip">%4$s</span></button>',
			/* translators: %s: metric name. */
			esc_attr( sprintf( __( 'About %s', 'swiftwebdev-dashboard-analytics' ), $widget['title'] ) ),
			esc_attr( $id ),
			wp_kses( swiftdash_icon( 'info' ), swiftdash_icon_kses() ),
			esc_html( $widget['description'] )
		);
	}
	if ( is_callable( $widget['actions'] ) ) {
		echo '<div class="sd-card__actions">';
		call_user_func( $widget['actions'], $widget );
		echo '</div>';
	}
	if ( $sortable ) {
		printf(
			'<button type="button" class="sd-drag" data-sd-drag aria-label="%1$s" aria-describedby="sd-drag-help">%2$s</button>',
			/* translators: %s: card title. */
			esc_attr( sprintf( __( 'Move %s', 'swiftwebdev-dashboard-analytics' ), $widget['title'] ) ),
			wp_kses( swiftdash_icon( 'dots-six-vertical' ), swiftdash_icon_kses() )
		);
	}
	echo '</header>';
	echo '<div class="sd-card__body">';
	call_user_func( $widget['render'], $widget );
	echo '</div>';
	echo '</section>';
}

/**
 * Whether the add-on notice should show for the current user.
 *
 * @return bool
 */
function swiftdash_show_upgrade() {
	/**
	 * Filters whether the add-on notice is shown. An installed add-on can return false.
	 *
	 * @param bool $show Default true.
	 */
	$show = (bool) apply_filters( 'swiftdash_show_upgrade', true );
	return $show && swiftdash_prefs()['upgrade_snooze'] < time();
}

/**
 * Whether the review request is due for the current user: 14 days after install, administrators
 * only, not answered or snoozed. Shown for free and PRO alike.
 *
 * @return bool
 */
function swiftdash_review_due() {
	$installed = (int) get_option( 'swiftdash_installed', 0 );
	return $installed && time() >= $installed + 14 * DAY_IN_SECONDS
		&& current_user_can( 'manage_options' )
		&& swiftdash_prefs()['review_snooze'] < time();
}

/**
 * Review request in the promo slot. "Leave a review" and "I already did" hide it for good,
 * "Maybe later" for 30 days (app.js, per user in swiftdash_prefs).
 */
function swiftdash_render_review() {
	$url = 'https://wordpress.org/support/plugin/swiftwebdev-dashboard-analytics/reviews/#new-post';
	?>
	<aside class="sd-promo sd-promo--single" aria-labelledby="sd-review-title" data-sd-review>
		<button type="button" class="sd-icon-btn sd-promo__close" data-sd-review-done aria-label="<?php esc_attr_e( 'Close and do not ask again', 'swiftwebdev-dashboard-analytics' ); ?>">
			<?php echo wp_kses( swiftdash_icon( 'x' ), swiftdash_icon_kses() ); ?>
		</button>
		<div class="sd-promo__intro">
			<span class="sd-promo__eyebrow"><?php echo wp_kses( swiftdash_icon( 'heart-fill' ), swiftdash_icon_kses() ); ?><?php esc_html_e( 'Quick favor', 'swiftwebdev-dashboard-analytics' ); ?></span>
			<h2 class="sd-promo__title" id="sd-review-title"><?php esc_html_e( 'Enjoying SwiftWebDev Dashboard?', 'swiftwebdev-dashboard-analytics' ); ?></h2>
			<p class="sd-promo__desc"><?php esc_html_e( 'An honest review on WordPress.org takes a minute and helps other site owners decide if it is right for them.', 'swiftwebdev-dashboard-analytics' ); ?></p>
			<div class="sd-promo__actions">
				<a class="sd-btn sd-btn--light" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" data-sd-review-done>
					<?php esc_html_e( 'Leave a review', 'swiftwebdev-dashboard-analytics' ); ?>
					<?php echo wp_kses( swiftdash_icon( 'arrow-square-out' ), swiftdash_icon_kses() ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'swiftwebdev-dashboard-analytics' ); ?></span>
				</a>
				<button type="button" class="sd-promo__later" data-sd-review-later><?php esc_html_e( 'Maybe later', 'swiftwebdev-dashboard-analytics' ); ?></button>
				<button type="button" class="sd-promo__later" data-sd-review-done><?php esc_html_e( 'I already did', 'swiftwebdev-dashboard-analytics' ); ?></button>
			</div>
		</div>
	</aside>
	<?php
}

/**
 * Banner in the middle of the dashboard (after the second row of cards) about the separate PRO add-on: what it adds, one link
 * to learn more. Dismissible for 30 days; never shown elsewhere. While the review request is
 * due it takes this slot instead (one promo at a time).
 *
 * @param string $screen_key Screen the banner is on, for the link's campaign tag.
 */
function swiftdash_render_upgrade( $screen_key = 'dashboard' ) {
	if ( 'dashboard' === $screen_key && swiftdash_review_due() ) {
		swiftdash_render_review();
		return;
	}
	if ( ! swiftdash_show_upgrade() ) {
		return;
	}
	$url      = add_query_arg(
		array(
			'utm_source' => 'plugin',
			'utm_medium' => sanitize_key( $screen_key ) . '-banner',
		),
		SWIFTDASH_ADDON_URL
	);
	$features = array(
		__( 'Real-time visitors', 'swiftwebdev-dashboard-analytics' ),
		__( 'Goals and conversion rates', 'swiftwebdev-dashboard-analytics' ),
		__( 'UTM campaign reports', 'swiftwebdev-dashboard-analytics' ),
		__( 'Traffic-spike email alerts', 'swiftwebdev-dashboard-analytics' ),
		__( 'Countries, browsers and new visitors', 'swiftwebdev-dashboard-analytics' ),
		__( 'Form and click tracking', 'swiftwebdev-dashboard-analytics' ),
		__( 'Session recordings with private fields hidden', 'swiftwebdev-dashboard-analytics' ),
		__( 'Custom date ranges and CSV export', 'swiftwebdev-dashboard-analytics' ),
		__( 'Weekly speed tests with email alerts', 'swiftwebdev-dashboard-analytics' ),
	);
	$title_id = 'sd-promo-title-' . sanitize_key( $screen_key );
	?>
	<aside class="sd-promo" aria-labelledby="<?php echo esc_attr( $title_id ); ?>" data-sd-upgrade>
		<button type="button" class="sd-icon-btn sd-promo__close" data-sd-dismiss-upgrade aria-label="<?php esc_attr_e( 'Hide for 30 days', 'swiftwebdev-dashboard-analytics' ); ?>">
			<?php echo wp_kses( swiftdash_icon( 'x' ), swiftdash_icon_kses() ); ?>
		</button>
		<div class="sd-promo__intro">
			<span class="sd-promo__eyebrow"><?php echo wp_kses( swiftdash_icon( 'puzzle-piece-fill' ), swiftdash_icon_kses() ); ?><?php esc_html_e( 'Optional add-on', 'swiftwebdev-dashboard-analytics' ); ?></span>
			<h2 class="sd-promo__title" id="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'See the full story behind your visitors', 'swiftwebdev-dashboard-analytics' ); ?></h2>
			<p class="sd-promo__desc"><?php esc_html_e( 'SwiftWebDev Dashboard PRO is a separate plugin that adds detailed reports to this dashboard. Everything you use today stays free.', 'swiftwebdev-dashboard-analytics' ); ?></p>
			<div class="sd-promo__actions">
				<a class="sd-btn sd-btn--light" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Explore PRO', 'swiftwebdev-dashboard-analytics' ); ?>
					<?php echo wp_kses( swiftdash_icon( 'arrow-square-out' ), swiftdash_icon_kses() ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'swiftwebdev-dashboard-analytics' ); ?></span>
				</a>
				<button type="button" class="sd-promo__later" data-sd-dismiss-upgrade><?php esc_html_e( 'Maybe later', 'swiftwebdev-dashboard-analytics' ); ?></button>
			</div>
		</div>
		<ul class="sd-promo__list" aria-label="<?php esc_attr_e( 'What PRO adds', 'swiftwebdev-dashboard-analytics' ); ?>">
			<?php foreach ( $features as $feature ) : ?>
				<li><?php echo wp_kses( swiftdash_icon( 'check-circle-fill' ), swiftdash_icon_kses() ); ?><span><?php echo esc_html( $feature ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	</aside>
	<?php
}
