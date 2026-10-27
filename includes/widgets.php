<?php
/**
 * Widget registry and card rendering.
 *
 * Free registers its own widgets plus locked slots for Pro widget ids. The Pro add-on
 * registers the same ids later (priority 20 on swiftdash_register_widgets) to replace them.
 *
 * @package SwiftDash
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
 *     @type string        $description One sentence: what the widget shows (used by locked cards and the customize panel).
 *     @type string        $screen      Screen key: dashboard, analytics, speed, links.
 *     @type string        $area        Area within the screen.
 *     @type int           $order       Sort order inside the area.
 *     @type int           $span        Grid columns out of 12.
 *     @type string        $tier        'free' or 'pro'.
 *     @type string        $placeholder Locked preview shape: kpi, chart, table, donut, map.
 *     @type callable|null $render      Prints the card body. Null on a pro widget = locked slot.
 *     @type string        $state       '' or 'expired' (set by the Pro add-on).
 *     @type callable|null $actions     Prints extra header content (status pill, links), right aligned.
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
			'tier'        => 'free',
			'placeholder' => 'chart',
			'render'      => null,
			'state'       => '',
			'actions'     => null,
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
	$widgets = array_filter(
		swiftdash_widget_registry(),
		function ( $w ) use ( $screen, $area ) {
			return $w['screen'] === $screen && ( null === $area || $w['area'] === $area );
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
 * Print a widget area as a 12-column grid.
 *
 * @param string $screen Screen key.
 * @param string $area   Area key.
 */
function swiftdash_render_area( $screen, $area ) {
	$widgets = swiftdash_get_widgets( $screen, $area );
	if ( ! $widgets ) {
		return;
	}
	$hidden = swiftdash_prefs()['hidden'];
	echo '<div class="sd-grid" data-sd-area="' . esc_attr( $area ) . '">';
	foreach ( $widgets as $widget ) {
		swiftdash_render_widget( $widget, in_array( $widget['id'], $hidden, true ) );
	}
	echo '</div>';
}

/**
 * KPI card widgets: icon and tone per widget id (the "an-" analytics copies share them).
 *
 * @param string $id Widget id.
 * @return array|null { icon, tone } or null when the widget is not a KPI card.
 */
function swiftdash_kpi_card( $id ) {
	$cards = array(
		'visitors'     => array( 'users-fill', 'blue' ),
		'page-views'   => array( 'chart-bar-fill', 'purple' ),
		'sessions'     => array( 'clock-fill', 'green' ),
		'new-visitors' => array( 'user-plus-fill', 'orange' ),
	);
	$key   = preg_replace( '/^an-/', '', $id );
	return isset( $cards[ $key ] ) ? array_combine( array( 'icon', 'tone' ), $cards[ $key ] ) : null;
}

/**
 * Saved card order per screen for the current user.
 *
 * @return array { dashboard: string[], analytics: string[] }
 */
function swiftdash_layout() {
	$saved  = get_user_meta( get_current_user_id(), 'swiftdash_layout', true );
	$layout = array();
	foreach ( array( 'dashboard', 'analytics' ) as $screen ) {
		$ids               = is_array( $saved ) && isset( $saved[ $screen ] ) && is_array( $saved[ $screen ] ) ? $saved[ $screen ] : array();
		$layout[ $screen ] = array_values( array_filter( array_map( 'sanitize_key', $ids ) ) );
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
				'analytics' => array(),
			),
			'auth_callback' => function ( $allowed, $meta_key, $user_id ) {
				return current_user_can( 'edit_user', $user_id );
			},
			'show_in_rest'  => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'dashboard' => $ids,
						'analytics' => $ids,
					),
					'additionalProperties' => false,
				),
			),
		)
	);
}
add_action( 'init', 'swiftdash_register_layout_meta' );

/**
 * Widgets of a screen in default order: area order, then widget order, with locked Pro cards
 * moved to the end (the New Visitors KPI stays in the KPI row).
 *
 * @param string $screen_key Screen key.
 * @return array[]
 */
function swiftdash_default_widget_order( $screen_key ) {
	$open   = array();
	$locked = array();
	foreach ( swiftdash_get_screen_widgets( $screen_key ) as $widget ) {
		if ( swiftdash_widget_is_locked( $widget ) && ! swiftdash_kpi_card( $widget['id'] ) ) {
			$locked[] = $widget;
		} else {
			$open[] = $widget;
		}
	}
	return array_merge( $open, $locked );
}

/**
 * Print every widget of a screen in one sortable 12-column grid (user order on top of the default).
 *
 * @param string $screen_key Screen key: dashboard or analytics.
 */
function swiftdash_render_screen_widgets( $screen_key ) {
	$widgets = swiftdash_default_widget_order( $screen_key );
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
	printf(
		'<div class="sd-grid sd-grid--sortable" data-sd-sortable="%1$s" data-sd-default="%2$s">',
		esc_attr( $screen_key ),
		esc_attr( implode( ',', $defaults ) )
	);
	foreach ( $widgets as $widget ) {
		swiftdash_render_widget( $widget, in_array( $widget['id'], $hidden, true ), true );
	}
	echo '</div>';
	echo '<p class="screen-reader-text" id="sd-drag-help">' . esc_html__( 'Drag to move this card, or press the up and down arrow keys.', 'swift-dashboard-analytics' ) . '</p>';
	echo '<div class="screen-reader-text" aria-live="polite" data-sd-sort-live></div>';
}

/**
 * Card order assets on the dashboard and analytics screens.
 *
 * @param string $screen_key Current screen key.
 */
function swiftdash_enqueue_sortable( $screen_key ) {
	if ( ! in_array( $screen_key, array( 'dashboard', 'analytics' ), true ) ) {
		return;
	}
	wp_enqueue_script( 'swiftdash-sortable-lib', SWIFTDASH_URL . 'assets/vendor/sortable/Sortable.min.js', array(), SWIFTDASH_VERSION, true );
	wp_enqueue_script( 'swiftdash-widgets-sortable', SWIFTDASH_URL . 'assets/js/widgets-sortable.js', array( 'swiftdash-app', 'wp-api-fetch', 'wp-i18n', 'swiftdash-sortable-lib' ), SWIFTDASH_VERSION, true );
	wp_set_script_translations( 'swiftdash-widgets-sortable', 'swift-dashboard-analytics' );
	wp_add_inline_script(
		'swiftdash-widgets-sortable',
		'window.swiftdashLayout = ' . wp_json_encode( swiftdash_layout() ) . ';',
		'before'
	);
}
add_action( 'swiftdash_enqueue_assets', 'swiftdash_enqueue_sortable' );

/**
 * Whether a widget renders as a locked (or expired) slot.
 *
 * @param array $widget Widget args.
 * @return bool
 */
function swiftdash_widget_is_locked( $widget ) {
	return 'pro' === $widget['tier'] && ( ! is_callable( $widget['render'] ) || 'expired' === $widget['state'] );
}

/**
 * Print one widget card.
 *
 * @param array $widget   Widget args.
 * @param bool  $hidden   Hidden by the user's customize panel.
 * @param bool  $sortable Print a drag handle in the header.
 */
function swiftdash_render_widget( $widget, $hidden = false, $sortable = false ) {
	$id     = 'sd-w-' . $widget['id'];
	$locked = swiftdash_widget_is_locked( $widget );
	$kpi    = swiftdash_kpi_card( $widget['id'] );
	$class  = 'sd-card sd-widget sd-span-' . absint( $widget['span'] ) . ( $locked ? ' sd-locked' : '' ) . ( $kpi ? ' sd-widget--kpi' : '' );

	printf(
		'<section class="%1$s" id="%2$s" data-sd-widget="%3$s" aria-labelledby="%2$s-title"%4$s>',
		esc_attr( $class ),
		esc_attr( $id ),
		esc_attr( $widget['id'] ),
		$hidden ? ' hidden' : ''
	);
	if ( $kpi ) {
		printf( '<span class="sd-kpi__icon sd-kpi__icon--%s">%s</span>', esc_attr( $kpi['tone'] ), swiftdash_icon( $kpi['icon'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- swiftdash_icon() returns escaped markup.
	}
	echo '<header class="sd-card__head">';
	printf( '<h2 class="sd-card__title" id="%s-title">%s</h2>', esc_attr( $id ), esc_html( $widget['title'] ) );
	if ( $kpi && $widget['description'] ) {
		printf(
			'<button type="button" class="sd-tip" aria-label="%1$s" aria-describedby="%2$s-tip">%3$s<span class="sd-tip__text" role="tooltip" id="%2$s-tip">%4$s</span></button>',
			/* translators: %s: metric name. */
			esc_attr( sprintf( __( 'About %s', 'swift-dashboard-analytics' ), $widget['title'] ) ),
			esc_attr( $id ),
			swiftdash_icon( 'info' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
			esc_html( $widget['description'] )
		);
	}
	if ( 'pro' === $widget['tier'] ) {
		echo '<span class="sd-badge sd-badge--pro">' . esc_html__( 'Pro', 'swift-dashboard-analytics' ) . '</span>';
	} elseif ( isset( $widget['actions'] ) && is_callable( $widget['actions'] ) ) {
		echo '<div class="sd-card__actions">';
		call_user_func( $widget['actions'], $widget );
		echo '</div>';
	}
	if ( $sortable ) {
		printf(
			'<button type="button" class="sd-drag" data-sd-drag aria-label="%1$s" aria-describedby="sd-drag-help">%2$s</button>',
			/* translators: %s: card title. */
			esc_attr( sprintf( __( 'Move %s', 'swift-dashboard-analytics' ), $widget['title'] ) ),
			swiftdash_icon( 'dots-six-vertical' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
		);
	}
	echo '</header>';

	if ( $locked ) {
		swiftdash_render_locked_body( $widget );
	} else {
		echo '<div class="sd-card__body">';
		call_user_func( $widget['render'], $widget );
		echo '</div>';
	}
	echo '</section>';
}

/**
 * Locked or expired slot body: blurred placeholder shape (never real or sample figures) + overlay.
 *
 * @param array $widget Widget args.
 */
function swiftdash_render_locked_body( $widget ) {
	$expired = 'expired' === $widget['state'];
	$url     = $expired
		? SWIFTDASH_ACCOUNT_URL
		: add_query_arg(
			array(
				'utm_source'  => 'plugin',
				'utm_medium'  => 'locked-widget',
				'utm_content' => $widget['id'],
			),
			SWIFTDASH_PRICING_URL
		);

	echo '<div class="sd-card__body sd-locked__body">';
	printf( '<div class="sd-ph sd-ph--%s" aria-hidden="true"></div>', esc_attr( sanitize_key( $widget['placeholder'] ) ) );
	echo '<div class="sd-locked__overlay">';
	echo '<span class="sd-locked__icon">' . swiftdash_icon( $expired ? 'warning' : 'lock-simple' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- swiftdash_icon() returns escaped markup.
	if ( $expired ) {
		echo '<p class="sd-locked__text"><strong>' . esc_html__( 'License expired. Please renew.', 'swift-dashboard-analytics' ) . '</strong></p>';
	} elseif ( $widget['description'] ) {
		echo '<p class="sd-locked__text">' . esc_html( $widget['description'] ) . '</p>';
	}
	printf(
		'<a class="sd-btn sd-btn--primary sd-btn--sm" href="%s" target="_blank" rel="noopener noreferrer">%s<span class="screen-reader-text"> %s</span></a>',
		esc_url( $url ),
		$expired ? esc_html__( 'Renew license', 'swift-dashboard-analytics' ) : esc_html__( 'Unlock with Pro', 'swift-dashboard-analytics' ),
		esc_html__( '(opens in a new tab)', 'swift-dashboard-analytics' )
	);
	echo '</div></div>';
}

/**
 * Whether upgrade prompts should show for the current user.
 *
 * @return bool
 */
function swiftdash_show_upgrade() {
	/**
	 * Filters whether upgrade prompts are shown. The Pro add-on returns false when licensed.
	 *
	 * @param bool $show Default true.
	 */
	$show = (bool) apply_filters( 'swiftdash_show_upgrade', true );
	return $show && swiftdash_prefs()['upgrade_snooze'] < time();
}

/**
 * Upgrade prompt, dismissible for 30 days.
 *
 * @param string $variant 'banner' (end of dashboard) or 'sidebar'.
 */
function swiftdash_render_upgrade( $variant = 'banner' ) {
	if ( ! swiftdash_show_upgrade() ) {
		return;
	}
	$url = add_query_arg(
		array(
			'utm_source' => 'plugin',
			'utm_medium' => 'upgrade-' . $variant,
		),
		SWIFTDASH_PRICING_URL
	);
	?>
	<aside class="sd-upgrade sd-upgrade--<?php echo esc_attr( $variant ); ?>" aria-label="<?php esc_attr_e( 'Swift Dash Pro', 'swift-dashboard-analytics' ); ?>" data-sd-upgrade>
		<button type="button" class="sd-icon-btn sd-upgrade__close" data-sd-dismiss-upgrade aria-label="<?php esc_attr_e( 'Hide for 30 days', 'swift-dashboard-analytics' ); ?>">
			<?php echo swiftdash_icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
		</button>
		<span class="sd-upgrade__icon"><?php echo swiftdash_icon( 'crown' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?></span>
		<div class="sd-upgrade__text">
			<p class="sd-upgrade__title"><?php esc_html_e( 'Upgrade to Pro', 'swift-dashboard-analytics' ); ?></p>
			<p class="sd-upgrade__desc">
				<?php
				'banner' === $variant
					? esc_html_e( 'Get full visitor analytics, real-time data, form and click tracking, session recordings and scheduled speed tests.', 'swift-dashboard-analytics' )
					: esc_html_e( 'Unlock full analytics and scheduled speed tests.', 'swift-dashboard-analytics' );
				?>
			</p>
		</div>
		<a class="sd-btn <?php echo 'banner' === $variant ? 'sd-btn--primary' : 'sd-btn--light'; ?>" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'See Pro plans', 'swift-dashboard-analytics' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'swift-dashboard-analytics' ); ?></span>
		</a>
	</aside>
	<?php
}
