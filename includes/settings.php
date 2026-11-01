<?php
/**
 * Site settings (core /wp/v2/settings) and per-user preferences (core /wp/v2/users/me meta).
 *
 * Both ride on core REST endpoints, so SwiftWebDev Dashboard needs no custom routes for them.
 *
 * @package SwiftDash
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
			'type'          => 'object',
			'single'        => true,
			'default'       => array(
				'hidden'         => array(),
				'upgrade_snooze' => 0,
			),
			'auth_callback' => function ( $allowed, $meta_key, $user_id ) {
				return current_user_can( 'edit_user', $user_id );
			},
			'show_in_rest'  => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'hidden'         => array(
							'type'  => 'array',
							'items' => array(
								'type'    => 'string',
								'pattern' => '^[a-z0-9_-]+$',
							),
						),
						'upgrade_snooze' => array(
							'type'    => 'integer',
							'minimum' => 0,
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
 * Current user's preferences, always with every key present.
 *
 * @return array
 */
function swiftdash_prefs() {
	$prefs = get_user_meta( get_current_user_id(), 'swiftdash_prefs', true );
	$prefs = is_array( $prefs ) ? $prefs : array();
	return array(
		'hidden'         => isset( $prefs['hidden'] ) && is_array( $prefs['hidden'] ) ? array_values( array_map( 'sanitize_key', $prefs['hidden'] ) ) : array(),
		'upgrade_snooze' => isset( $prefs['upgrade_snooze'] ) ? absint( $prefs['upgrade_snooze'] ) : 0,
	);
}
