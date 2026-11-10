<?php
/**
 * Site settings (core /wp/v2/settings) and per-user preferences (core /wp/v2/users/me meta).
 *
 * Both ride on core REST endpoints, so SwiftWebDev Dashboard needs no custom routes for them.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability needed to use SwiftWebDev Dashboard screens.
 *
 * @return string
 */
function swiftdash_capability() {
	/**
	 * Filters the capability required to open SwiftWebDev Dashboard screens.
	 *
	 * @param string $capability Default 'manage_options'.
	 */
	return (string) apply_filters( 'swiftdash_capability', 'manage_options' );
}

/**
 * Register the site option and the user preference meta.
 */
function swiftdash_register_settings() {
	register_setting(
		'swiftdash',
		'swiftdash_settings',
		array(
			'type'              => 'object',
			'default'           => array(
				'open_after_login' => false,
				'exclude_admins'   => true,
				'brand_name'       => '',
				'brand_logo'       => 0,
				'weekly_report'    => false,
				'report_email'     => '',
				'toolbar_live'     => true,
				'toolbar_search'   => true,
			),
			'sanitize_callback' => 'swiftdash_sanitize_settings',
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'open_after_login' => array( 'type' => 'boolean' ),
						'exclude_admins'   => array( 'type' => 'boolean' ),
						'brand_name'       => array(
							'type'      => 'string',
							'maxLength' => 40,
						),
						'brand_logo'       => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'weekly_report'    => array( 'type' => 'boolean' ),
						'report_email'     => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
						'toolbar_live'     => array( 'type' => 'boolean' ),
						'toolbar_search'   => array( 'type' => 'boolean' ),
					),
					'additionalProperties' => false,
				),
			),
		)
	);

	register_meta(
		'user',
		'swiftdash_prefs',
		array(
			'type'              => 'object',
			'single'            => true,
			'default'           => array(
				'hidden'          => array(),
				'upgrade_snooze'  => 0,
				'review_snooze'   => 0,
				'welcomed'        => false,
				'recent'          => array(),
				'pro_tips_hidden' => false,
			),
			'auth_callback'     => function ( $allowed, $meta_key, $user_id ) {
				return current_user_can( 'edit_user', $user_id );
			},
			'sanitize_callback' => 'swiftdash_sanitize_prefs',
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'hidden'          => array(
							'type'  => 'array',
							'items' => array(
								'type'    => 'string',
								'pattern' => '^[a-z0-9_-]+$',
							),
						),
						'upgrade_snooze'  => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'review_snooze'   => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'welcomed'        => array( 'type' => 'boolean' ),
						'pro_tips_hidden' => array( 'type' => 'boolean' ),
						'recent'          => array(
							'type'     => 'array',
							'maxItems' => 5,
							'items'    => array(
								'type'                 => 'object',
								'properties'           => array(
									'label' => array(
										'type'      => 'string',
										'maxLength' => 200,
									),
									'url'   => array(
										'type'   => 'string',
										'format' => 'uri',
									),
									'hint'  => array(
										'type'      => 'string',
										'maxLength' => 200,
									),
									'icon'  => array(
										'type'    => 'string',
										'pattern' => '^[a-z0-9_-]+$',
									),
									'tone'  => array(
										'type'    => 'string',
										'pattern' => '^[a-z0-9_-]+$',
									),
								),
								'required'             => array( 'label', 'url' ),
								'additionalProperties' => false,
							),
						),
					),
					'additionalProperties' => false,
				),
			),
		)
	);
}
add_action( 'init', 'swiftdash_register_settings' );

/**
 * Sanitize the settings option.
 *
 * @param mixed $value Raw value.
 * @return array
 */
function swiftdash_sanitize_settings( $value ) {
	$value = is_array( $value ) ? $value : array();
	return array(
		'open_after_login' => ! empty( $value['open_after_login'] ),
		'exclude_admins'   => ! isset( $value['exclude_admins'] ) || ! empty( $value['exclude_admins'] ),
		'brand_name'       => isset( $value['brand_name'] ) ? mb_substr( sanitize_text_field( (string) $value['brand_name'] ), 0, 40 ) : '',
		'brand_logo'       => isset( $value['brand_logo'] ) ? absint( $value['brand_logo'] ) : 0,
		'weekly_report'    => ! empty( $value['weekly_report'] ),
		'report_email'     => isset( $value['report_email'] ) ? sanitize_email( (string) $value['report_email'] ) : '',
		'toolbar_live'     => ! isset( $value['toolbar_live'] ) || ! empty( $value['toolbar_live'] ),
		'toolbar_search'   => ! isset( $value['toolbar_search'] ) || ! empty( $value['toolbar_search'] ),
	);
}

/**
 * Get one setting.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function swiftdash_setting( $key ) {
	$settings = swiftdash_sanitize_settings( get_option( 'swiftdash_settings', array() ) );
	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

/**
 * Sidebar brand: name and logo URL. Defaults to the site title and site icon.
 *
 * @return array { name, logo }
 */
function swiftdash_brand() {
	$name = swiftdash_setting( 'brand_name' );
	$logo = swiftdash_setting( 'brand_logo' ) ? wp_get_attachment_image_url( swiftdash_setting( 'brand_logo' ), 'thumbnail' ) : '';
	$logo = $logo ? $logo : get_site_icon_url( 64 );
	if ( '' === $name ) {
		$name = wp_strip_all_tags( get_bloginfo( 'name' ) );
	}
	return array(
		'name' => '' !== $name ? $name : __( 'Dashboard', 'swiftwebdev-dashboard-analytics' ),
		'logo' => $logo ? $logo : SWIFTDASH_URL . 'assets/img/brand.svg',
	);
}

/**
 * Clean a preferences array: every key present, recent searches limited to admin pages of this site.
 *
 * @param mixed $prefs Raw value.
 * @return array
 */
function swiftdash_sanitize_prefs( $prefs ) {
	$prefs  = is_array( $prefs ) ? $prefs : array();
	$recent = array();
	foreach ( isset( $prefs['recent'] ) && is_array( $prefs['recent'] ) ? $prefs['recent'] : array() as $item ) {
		$url = is_array( $item ) && isset( $item['url'], $item['label'] ) ? esc_url_raw( (string) $item['url'] ) : '';
		if ( '' === $url || 0 !== strpos( $url, admin_url() ) || count( $recent ) >= 5 ) {
			continue;
		}
		$icon     = isset( $item['icon'] ) ? sanitize_key( $item['icon'] ) : '';
		$tone     = isset( $item['tone'] ) ? sanitize_key( $item['tone'] ) : '';
		$recent[] = array(
			'label' => mb_substr( sanitize_text_field( (string) $item['label'] ), 0, 200 ),
			'url'   => $url,
			'hint'  => isset( $item['hint'] ) ? mb_substr( sanitize_text_field( (string) $item['hint'] ), 0, 200 ) : '',
			'icon'  => '' !== $icon ? $icon : 'arrow-square-out',
			'tone'  => '' !== $tone ? $tone : 'neutral',
		);
	}
	return array(
		'hidden'          => isset( $prefs['hidden'] ) && is_array( $prefs['hidden'] ) ? array_values( array_map( 'sanitize_key', $prefs['hidden'] ) ) : array(),
		'upgrade_snooze'  => isset( $prefs['upgrade_snooze'] ) ? absint( $prefs['upgrade_snooze'] ) : 0,
		'review_snooze'   => isset( $prefs['review_snooze'] ) ? absint( $prefs['review_snooze'] ) : 0,
		'welcomed'        => ! empty( $prefs['welcomed'] ),
		'recent'          => $recent,
		'pro_tips_hidden' => ! empty( $prefs['pro_tips_hidden'] ),
	);
}

/**
 * Current user's preferences, always with every key present.
 *
 * @return array
 */
function swiftdash_prefs() {
	return swiftdash_sanitize_prefs( get_user_meta( get_current_user_id(), 'swiftdash_prefs', true ) );
}
