<?php
/**
 * Quick Links: customizable shortcuts to common WordPress tasks (screen + dashboard widget).
 *
 * Per-user layout lives in user meta swiftdash_links, saved through core /wp/v2/users/me.
 *
 * @package SwiftDash
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quick Links sections, in display order.
 *
 * @return string[] Section key => label.
 */
function swiftdash_links_sections() {
	return array(
		'content'  => __( 'Content', 'swift-dashboard-analytics' ),
		'design'   => __( 'Design', 'swift-dashboard-analytics' ),
		'people'   => __( 'Plugins and users', 'swift-dashboard-analytics' ),
		'tools'    => __( 'Tools', 'swift-dashboard-analytics' ),
		'settings' => __( 'Settings', 'swift-dashboard-analytics' ),
		'store'    => __( 'Store', 'swift-dashboard-analytics' ),
	);
}

/**
 * Shortcuts available to the current user, keyed by id, in default order.
 *
 * @return array[] Each: id, label, description, icon, url, capability, tone, section.
 */
function swiftdash_quick_links() {
	$block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	$pending     = (int) wp_count_comments()->moderated;
	$comments    = __( 'Review and reply to comments.', 'swift-dashboard-analytics' );
	if ( $pending ) {
		$comments = sprintf(
			/* translators: %s: number of comments waiting for review. */
			_n( 'Review and reply to comments. %s waiting for review.', 'Review and reply to comments. %s waiting for review.', $pending, 'swift-dashboard-analytics' ),
			number_format_i18n( $pending )
		);
	}

	$links = array(
		array( 'add-post', __( 'Add new post', 'swift-dashboard-analytics' ), __( 'Write and publish a new blog post.', 'swift-dashboard-analytics' ), 'plus-circle-fill', 'post-new.php', 'edit_posts', 'blue', 'content' ),
		array( 'all-posts', __( 'All posts', 'swift-dashboard-analytics' ), __( 'Edit, publish or remove your existing posts.', 'swift-dashboard-analytics' ), 'note-fill', 'edit.php', 'edit_posts', 'sky', 'content' ),
		array( 'categories', __( 'Categories', 'swift-dashboard-analytics' ), __( 'Group your posts by topic.', 'swift-dashboard-analytics' ), 'folder-fill', 'edit-tags.php?taxonomy=category', 'manage_categories', 'orange', 'content' ),
		array( 'tags', __( 'Tags', 'swift-dashboard-analytics' ), __( 'Label posts with keywords so readers find related content.', 'swift-dashboard-analytics' ), 'tag-fill', 'edit-tags.php?taxonomy=post_tag', 'manage_categories', 'green', 'content' ),
		array( 'add-page', __( 'Add new page', 'swift-dashboard-analytics' ), __( 'Create a new page such as About or Contact.', 'swift-dashboard-analytics' ), 'file-plus-fill', 'post-new.php?post_type=page', 'edit_pages', 'purple', 'content' ),
		array( 'manage-pages', __( 'All pages', 'swift-dashboard-analytics' ), __( 'Edit, publish or remove your existing pages.', 'swift-dashboard-analytics' ), 'files-fill', 'edit.php?post_type=page', 'edit_pages', 'purple', 'content' ),
		array( 'upload-media', __( 'Upload media', 'swift-dashboard-analytics' ), __( 'Add images, videos or documents to your media library.', 'swift-dashboard-analytics' ), 'cloud-arrow-up-fill', 'media-new.php', 'upload_files', 'green', 'content' ),
		array( 'media-library', __( 'Media library', 'swift-dashboard-analytics' ), __( 'Browse and manage every file you have uploaded.', 'swift-dashboard-analytics' ), 'images-fill', 'upload.php', 'upload_files', 'sky', 'content' ),
		array( 'comments', __( 'Comments', 'swift-dashboard-analytics' ), $comments, 'chat-circle-text-fill', 'edit-comments.php', 'moderate_comments', 'orange', 'content' ),
		array( 'themes', __( 'Themes', 'swift-dashboard-analytics' ), __( 'Choose or change the theme that styles your site.', 'swift-dashboard-analytics' ), 'palette-fill', 'themes.php', 'switch_themes', 'purple', 'design' ),
		array(
			'customize-site',
			$block_theme ? __( 'Site editor', 'swift-dashboard-analytics' ) : __( 'Customize site', 'swift-dashboard-analytics' ),
			__( 'Change how your site looks: colors, fonts, header and footer.', 'swift-dashboard-analytics' ),
			'paint-brush-fill',
			$block_theme ? 'site-editor.php' : 'customize.php',
			'edit_theme_options',
			'red',
			'design',
		),
	);
	if ( $block_theme ) {
		$links[] = array( 'patterns', __( 'Patterns', 'swift-dashboard-analytics' ), __( 'Reusable blocks and layouts for your pages and templates.', 'swift-dashboard-analytics' ), 'shapes-fill', 'site-editor.php?path=/patterns', 'edit_theme_options', 'blue', 'design' );
		$links[] = array( 'navigation', __( 'Navigation', 'swift-dashboard-analytics' ), __( 'Choose which links appear in your site navigation.', 'swift-dashboard-analytics' ), 'compass-fill', 'site-editor.php?path=/navigation', 'edit_theme_options', 'green', 'design' );
	} else {
		if ( current_theme_supports( 'widgets' ) ) {
			$links[] = array( 'widgets', __( 'Widgets', 'swift-dashboard-analytics' ), __( 'Add content blocks to your sidebars and footer.', 'swift-dashboard-analytics' ), 'squares-four-fill', 'widgets.php', 'edit_theme_options', 'blue', 'design' );
		}
		if ( current_theme_supports( 'menus' ) ) {
			$links[] = array( 'menus', __( 'Menus', 'swift-dashboard-analytics' ), __( 'Choose which links appear in your site navigation.', 'swift-dashboard-analytics' ), 'list-fill', 'nav-menus.php', 'edit_theme_options', 'green', 'design' );
		}
	}
	if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
		$links[] = array( 'theme-editor', __( 'Theme file editor', 'swift-dashboard-analytics' ), __( 'Edit theme code directly. For developers only.', 'swift-dashboard-analytics' ), 'code-fill', 'theme-editor.php', 'edit_themes', 'sky', 'design' );
	}
	array_push(
		$links,
		array( 'add-plugin', __( 'Add plugin', 'swift-dashboard-analytics' ), __( 'Find and install new plugins from WordPress.org.', 'swift-dashboard-analytics' ), 'puzzle-piece-fill', 'plugin-install.php', 'install_plugins', 'sky', 'people' ),
		array( 'manage-plugins', __( 'Installed plugins', 'swift-dashboard-analytics' ), __( 'Update, turn on or turn off the plugins you have.', 'swift-dashboard-analytics' ), 'plug-fill', 'plugins.php', 'activate_plugins', 'blue', 'people' ),
		array( 'add-user', __( 'Add user', 'swift-dashboard-analytics' ), __( 'Invite a new person to your site.', 'swift-dashboard-analytics' ), 'user-plus-fill', 'user-new.php', 'create_users', 'green', 'people' ),
		array( 'manage-users', __( 'Manage users', 'swift-dashboard-analytics' ), __( 'See everyone on your site and change what they can do.', 'swift-dashboard-analytics' ), 'users-fill', 'users.php', 'list_users', 'orange', 'people' ),
		array( 'profile', __( 'Your profile', 'swift-dashboard-analytics' ), __( 'Change your name, email address and password.', 'swift-dashboard-analytics' ), 'user-circle-fill', 'profile.php', 'read', 'purple', 'people' ),
		array( 'tools', __( 'Tools', 'swift-dashboard-analytics' ), __( 'Open the WordPress tools screen.', 'swift-dashboard-analytics' ), 'wrench-fill', 'tools.php', 'edit_posts', 'blue', 'tools' ),
		array( 'import', __( 'Import', 'swift-dashboard-analytics' ), __( 'Bring in posts and pages from another site.', 'swift-dashboard-analytics' ), 'download-simple-fill', 'import.php', 'import', 'green', 'tools' ),
		array( 'export-data', __( 'Export', 'swift-dashboard-analytics' ), __( 'Download your posts, pages and comments as a file you can import elsewhere.', 'swift-dashboard-analytics' ), 'export-fill', 'export.php', 'export', 'purple', 'tools' ),
		array( 'site-health', __( 'Site Health', 'swift-dashboard-analytics' ), __( 'Check your site for security and performance problems.', 'swift-dashboard-analytics' ), 'heartbeat-fill', 'site-health.php', 'view_site_health_checks', 'red', 'tools' ),
		array( 'system-info', __( 'System information', 'swift-dashboard-analytics' ), __( 'See your server, PHP and WordPress details, useful when asking for support.', 'swift-dashboard-analytics' ), 'info-fill', 'site-health.php?tab=debug', 'view_site_health_checks', 'sky', 'tools' ),
		array( 'updates', __( 'Updates', 'swift-dashboard-analytics' ), __( 'Update WordPress, plugins, themes and translations.', 'swift-dashboard-analytics' ), 'arrows-clockwise-fill', 'update-core.php', 'update_core', 'orange', 'tools' ),
		array( 'site-settings', __( 'General settings', 'swift-dashboard-analytics' ), __( 'Change your site title, tagline, time zone and other basics.', 'swift-dashboard-analytics' ), 'gear-six-fill', 'options-general.php', 'manage_options', 'blue', 'settings' ),
		array( 'writing', __( 'Writing', 'swift-dashboard-analytics' ), __( 'Default post category, format and post by email.', 'swift-dashboard-analytics' ), 'pen-nib-fill', 'options-writing.php', 'manage_options', 'purple', 'settings' ),
		array( 'reading', __( 'Reading', 'swift-dashboard-analytics' ), __( 'Your homepage, posts per page and search engine visibility.', 'swift-dashboard-analytics' ), 'book-open-fill', 'options-reading.php', 'manage_options', 'green', 'settings' ),
		array( 'discussion', __( 'Discussion', 'swift-dashboard-analytics' ), __( 'Decide who can comment and how comments are reviewed.', 'swift-dashboard-analytics' ), 'chats-circle-fill', 'options-discussion.php', 'manage_options', 'orange', 'settings' ),
		array( 'media-settings', __( 'Media settings', 'swift-dashboard-analytics' ), __( 'Image sizes and how uploads are organized.', 'swift-dashboard-analytics' ), 'image-fill', 'options-media.php', 'manage_options', 'sky', 'settings' ),
		array( 'permalinks', __( 'Permalinks', 'swift-dashboard-analytics' ), __( 'Choose how the web addresses of your posts look.', 'swift-dashboard-analytics' ), 'link-simple-fill', 'options-permalink.php', 'manage_options', 'red', 'settings' ),
		array( 'privacy', __( 'Privacy', 'swift-dashboard-analytics' ), __( 'Set your privacy policy page.', 'swift-dashboard-analytics' ), 'shield-check-fill', 'options-privacy.php', 'manage_privacy_options', 'green', 'settings' )
	);
	if ( class_exists( 'WooCommerce' ) ) {
		$hpos    = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$links[] = array( 'add-product', __( 'Add product', 'swift-dashboard-analytics' ), __( 'Add a new product to your store.', 'swift-dashboard-analytics' ), 'shopping-cart-fill', 'post-new.php?post_type=product', 'edit_products', 'green', 'store' );
		$links[] = array( 'orders', __( 'Orders', 'swift-dashboard-analytics' ), __( 'See and process your store orders.', 'swift-dashboard-analytics' ), 'receipt-fill', $hpos ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order', 'edit_shop_orders', 'orange', 'store' );
	}

	$keyed = array();
	foreach ( $links as $link ) {
		$link[4]           = admin_url( $link[4] );
		$keyed[ $link[0] ] = array_combine( array( 'id', 'label', 'description', 'icon', 'url', 'capability', 'tone', 'section' ), $link );
	}

	/**
	 * Filters the Quick Links shortcuts. Each item: id, label, description, icon, url, capability,
	 * tone (blue, sky, purple, green, orange, red) and section (content, design, people, tools, settings, store).
	 * Items the current user lacks the capability for are removed afterwards.
	 *
	 * @param array[] $links Shortcuts keyed by id, in default order.
	 */
	$keyed = apply_filters( 'swiftdash_quick_links', $keyed );

	$sections  = swiftdash_links_sections();
	$available = array();
	foreach ( (array) $keyed as $link ) {
		if ( empty( $link['id'] ) || ! preg_match( '/^[a-z0-9_-]+$/', $link['id'] ) || empty( $link['capability'] ) || ! current_user_can( $link['capability'] ) ) {
			continue;
		}
		$link = wp_parse_args(
			$link,
			array(
				'label'       => '',
				'description' => '',
				'icon'        => 'link-simple-fill',
				'url'         => '',
				'tone'        => 'blue',
				'section'     => 'tools',
			)
		);
		if ( ! isset( $sections[ $link['section'] ] ) ) {
			$link['section'] = 'tools';
		}
		$available[ $link['id'] ] = $link;
	}
	return $available;
}

/**
 * Default pinned shortcuts: the first 8 available of the recommended set.
 *
 * @param array[] $links Available shortcuts keyed by id.
 * @return string[]
 */
function swiftdash_links_default_pins( $links ) {
	$ids = array( 'add-post', 'add-page', 'upload-media', 'manage-users', 'customize-site', 'add-plugin', 'updates', 'site-health' );
	return array_slice( array_values( array_intersect( $ids, array_keys( $links ) ) ), 0, 8 );
}

/**
 * Register the per-user layout meta.
 */
function swiftdash_register_links_meta() {
	$ids = array(
		'type'     => 'array',
		'maxItems' => 100,
		'items'    => array(
			'type'    => 'string',
			'pattern' => '^[a-z0-9_-]+$',
		),
	);
	register_meta(
		'user',
		'swiftdash_links',
		array(
			'type'          => 'object',
			'single'        => true,
			'auth_callback' => function ( $allowed, $meta_key, $user_id ) {
				return current_user_can( 'edit_user', $user_id );
			},
			'show_in_rest'  => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'order'  => $ids,
						'hidden' => $ids,
						'pinned' => $ids,
					),
					'additionalProperties' => false,
				),
			),
		)
	);
}
add_action( 'init', 'swiftdash_register_links_meta' );

/**
 * Current user's layout, limited to the available shortcuts.
 *
 * Pinned is ordered as shown on the dashboard. Older saves kept the pinned order in `order`, so pinned
 * ids are sorted by their position there.
 *
 * @param array[] $links Available shortcuts keyed by id.
 * @return array { hidden: ids, pinned: ids in dashboard order }
 */
function swiftdash_links_state( $links ) {
	$ids   = array_keys( $links );
	$saved = get_user_meta( get_current_user_id(), 'swiftdash_links', true );
	if ( ! is_array( $saved ) ) {
		return array(
			'hidden' => array(),
			'pinned' => swiftdash_links_default_pins( $links ),
		);
	}
	$pick = function ( $key ) use ( $saved, $ids ) {
		return isset( $saved[ $key ] ) && is_array( $saved[ $key ] )
			? array_values( array_intersect( array_map( 'sanitize_key', $saved[ $key ] ), $ids ) )
			: array();
	};

	$hidden = $pick( 'hidden' );
	$pinned = array_diff( $pick( 'pinned' ), $hidden );
	return array(
		'hidden' => $hidden,
		'pinned' => array_values( array_intersect( array_unique( array_merge( $pick( 'order' ), $pinned ) ), $pinned ) ),
	);
}

/**
 * Enqueue Quick Links assets.
 *
 * @param string $screen_key Current Swift Dash screen key.
 */
function swiftdash_links_assets( $screen_key ) {
	if ( 'links' !== $screen_key && 'dashboard' !== $screen_key ) {
		return;
	}
	wp_enqueue_style( 'swiftdash-links', SWIFTDASH_URL . 'assets/css/links.css', array( 'swiftdash-app' ), SWIFTDASH_VERSION );
	// The dashboard widget is plain links, so the script only loads on the Quick Links screen.
	if ( 'links' === $screen_key ) {
		wp_enqueue_script( 'swiftdash-sortable-lib', SWIFTDASH_URL . 'assets/vendor/sortable/Sortable.min.js', array(), SWIFTDASH_VERSION, true );
		wp_enqueue_script( 'swiftdash-links', SWIFTDASH_URL . 'assets/js/links.js', array( 'swiftdash-app', 'swiftdash-sortable-lib', 'wp-api-fetch', 'wp-i18n' ), SWIFTDASH_VERSION, true );
		wp_set_script_translations( 'swiftdash-links', 'swift-dashboard-analytics' );
	}
}
add_action( 'swiftdash_enqueue_assets', 'swiftdash_links_assets' );

/**
 * Tone class for a shortcut.
 *
 * @param array $link Shortcut.
 * @return string
 */
function swiftdash_link_tone( $link ) {
	return 'sd-ql-tone--' . ( in_array( $link['tone'], array( 'blue', 'sky', 'purple', 'green', 'orange', 'red' ), true ) ? $link['tone'] : 'blue' );
}

/**
 * Quick Links screen.
 *
 * @param array $screen Screen definition.
 */
function swiftdash_screen_links( $screen ) {
	$links = swiftdash_quick_links();
	$state = swiftdash_links_state( $links );

	swiftdash_render_page_header(
		$screen['title'],
		$screen['description'],
		$links ? function () {
			?>
			<div class="sd-search sd-ql-search">
				<label class="screen-reader-text" for="sd-ql-search"><?php esc_html_e( 'Search shortcuts', 'swift-dashboard-analytics' ); ?></label>
				<?php echo swiftdash_icon( 'magnifying-glass', 'sd-search__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
				<input type="search" class="sd-input sd-search__input" id="sd-ql-search" placeholder="<?php esc_attr_e( 'Search shortcuts', 'swift-dashboard-analytics' ); ?>" autocomplete="off" data-sd-ql-search>
			</div>
			<button type="button" class="sd-btn sd-btn--tertiary" data-sd-ql-restore>
				<?php echo swiftdash_icon( 'arrow-clockwise' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup. ?>
				<?php esc_html_e( 'Restore defaults', 'swift-dashboard-analytics' ); ?>
			</button>
			<?php
		} : null
	);

	if ( ! $links ) {
		swiftdash_render_empty_state( 'link-simple', __( 'No shortcuts available', 'swift-dashboard-analytics' ), __( 'Your account does not have access to any of the tasks listed here. Ask a site administrator if you need more access.', 'swift-dashboard-analytics' ) );
		return;
	}

	$index = array_flip( array_keys( $links ) );
	?>
	<div class="sd-ql" data-sd-ql data-sd-ql-defaults="<?php echo esc_attr( wp_json_encode( swiftdash_links_default_pins( $links ) ) ); ?>">
		<p class="screen-reader-text" aria-live="polite" data-sd-ql-count></p>
		<p class="screen-reader-text" aria-live="assertive" data-sd-ql-announce></p>
		<p class="screen-reader-text" id="sd-ql-handle-help"><?php esc_html_e( 'Drag to reorder, or use the arrow keys.', 'swift-dashboard-analytics' ); ?></p>

		<section class="sd-ql__section sd-ql__section--pinned" aria-labelledby="sd-ql-pinned-title">
			<h2 class="sd-ql__heading" id="sd-ql-pinned-title"><?php esc_html_e( 'Pinned shortcuts', 'swift-dashboard-analytics' ); ?></h2>
			<p class="sd-ql__help"><?php esc_html_e( 'These appear on your dashboard in this order. Drag a tile to move it. Changes only affect your own account.', 'swift-dashboard-analytics' ); ?></p>
			<ul class="sd-ql__pins" aria-labelledby="sd-ql-pinned-title" data-sd-ql-list="pinned">
				<?php
				foreach ( $state['pinned'] as $id ) {
					swiftdash_render_link_item( $links[ $id ], $index[ $id ], true );
				}
				?>
			</ul>
			<p class="sd-ql__pins-empty" data-sd-ql-pins-empty<?php echo $state['pinned'] ? ' hidden' : ''; ?>><?php esc_html_e( 'Nothing pinned yet. Pin a shortcut below to add it to your dashboard.', 'swift-dashboard-analytics' ); ?></p>
		</section>

		<?php
		$present = wp_list_pluck( $links, 'section' );
		foreach ( swiftdash_links_sections() as $key => $label ) {
			if ( ! in_array( $key, $present, true ) ) {
				continue;
			}
			$ids = array();
			foreach ( $links as $id => $link ) {
				if ( $key === $link['section'] && ! in_array( $id, $state['pinned'], true ) && ! in_array( $id, $state['hidden'], true ) ) {
					$ids[] = $id;
				}
			}
			?>
			<section class="sd-ql__section" aria-labelledby="sd-ql-<?php echo esc_attr( $key ); ?>-title" data-sd-ql-section<?php echo $ids ? '' : ' hidden'; ?>>
				<h2 class="sd-ql__heading" id="sd-ql-<?php echo esc_attr( $key ); ?>-title"><?php echo esc_html( $label ); ?></h2>
				<ul class="sd-ql__grid" aria-labelledby="sd-ql-<?php echo esc_attr( $key ); ?>-title" data-sd-ql-list="<?php echo esc_attr( $key ); ?>">
					<?php
					foreach ( $ids as $id ) {
						swiftdash_render_link_item( $links[ $id ], $index[ $id ], false );
					}
					?>
				</ul>
			</section>
			<?php
		}
		?>

		<div data-sd-ql-nomatch hidden>
			<?php swiftdash_render_empty_state( 'magnifying-glass', __( 'No shortcuts match your search', 'swift-dashboard-analytics' ), __( 'Try a different word, or clear the search to see all shortcuts.', 'swift-dashboard-analytics' ) ); ?>
		</div>
		<section class="sd-ql__section sd-ql__hidden" aria-labelledby="sd-ql-hidden-title" data-sd-ql-hidden-section<?php echo $state['hidden'] ? '' : ' hidden'; ?>>
			<h2 class="sd-ql__heading" id="sd-ql-hidden-title"><?php esc_html_e( 'Hidden shortcuts', 'swift-dashboard-analytics' ); ?></h2>
			<ul class="sd-ql__grid sd-ql__grid--hidden" aria-labelledby="sd-ql-hidden-title" data-sd-ql-list="hidden">
				<?php
				foreach ( $state['hidden'] as $id ) {
					swiftdash_render_link_item( $links[ $id ], $index[ $id ], false );
				}
				?>
			</ul>
		</section>
	</div>
	<?php
}

/**
 * One shortcut. The same element moves between the pinned row, its section and the hidden list;
 * CSS shows the controls that fit where it is (drag handle when pinned, Show when hidden).
 *
 * @param array $link   Shortcut.
 * @param int   $index  Position in the default catalogue, used to put it back in place.
 * @param bool  $pinned Whether it is pinned to the dashboard.
 */
function swiftdash_render_link_item( $link, $index, $pinned ) {
	$icon = function ( $name ) {
		echo swiftdash_icon( $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
	};
	?>
	<li class="sd-ql__item <?php echo esc_attr( swiftdash_link_tone( $link ) ); ?><?php echo $pinned ? ' is-pinned' : ''; ?>" data-sd-link="<?php echo esc_attr( $link['id'] ); ?>" data-sd-section="<?php echo esc_attr( $link['section'] ); ?>" data-sd-index="<?php echo absint( $index ); ?>" data-sd-ql-text="<?php echo esc_attr( strtolower( $link['label'] . ' ' . $link['description'] ) ); ?>">
		<button type="button" class="sd-ql__handle" data-sd-ql-handle aria-describedby="sd-ql-handle-help" aria-label="<?php /* translators: %s: shortcut name. */ echo esc_attr( sprintf( __( 'Move %s', 'swift-dashboard-analytics' ), $link['label'] ) ); ?>"><?php $icon( 'dots-six-vertical' ); ?></button>
		<a class="sd-ql__tile" href="<?php echo esc_url( $link['url'] ); ?>" title="<?php echo esc_attr( $link['description'] ); ?>">
			<span class="sd-ql__chip"><?php $icon( $link['icon'] ); ?></span>
			<span class="sd-ql__text">
				<span class="sd-ql__label"><?php echo esc_html( $link['label'] ); ?></span>
				<span class="sd-ql__desc"><?php echo esc_html( $link['description'] ); ?></span>
			</span>
		</a>
		<div class="sd-ql__tools" role="group" aria-label="<?php /* translators: %s: shortcut name. */ echo esc_attr( sprintf( __( '%s options', 'swift-dashboard-analytics' ), $link['label'] ) ); ?>">
			<button type="button" class="sd-icon-btn sd-ql__pin" data-sd-ql-action="pin" aria-pressed="<?php echo $pinned ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Pin to dashboard', 'swift-dashboard-analytics' ); ?>" title="<?php esc_attr_e( 'Pin to dashboard', 'swift-dashboard-analytics' ); ?>"><?php $icon( 'push-pin' ); ?></button>
			<button type="button" class="sd-icon-btn sd-ql__hide" data-sd-ql-action="hide" aria-label="<?php esc_attr_e( 'Hide', 'swift-dashboard-analytics' ); ?>" title="<?php esc_attr_e( 'Hide', 'swift-dashboard-analytics' ); ?>"><?php $icon( 'eye-slash' ); ?></button>
		</div>
		<button type="button" class="sd-btn sd-btn--secondary sd-btn--sm sd-ql__show" data-sd-ql-action="show">
			<?php $icon( 'eye' ); ?>
			<?php esc_html_e( 'Show', 'swift-dashboard-analytics' ); ?><span class="screen-reader-text"> <?php echo esc_html( $link['label'] ); ?></span>
		</button>
	</li>
	<?php
}

/**
 * Register the dashboard widget.
 */
function swiftdash_register_links_widget() {
	swiftdash_register_widget(
		'quick-links',
		array(
			'title'       => __( 'Quick Links', 'swift-dashboard-analytics' ),
			'description' => __( 'Shortcuts to the WordPress tasks you pinned.', 'swift-dashboard-analytics' ),
			'screen'      => 'dashboard',
			'area'        => 'performance',
			'order'       => 30,
			'span'        => 4,
			'render'      => 'swiftdash_render_links_widget',
			'actions'     => 'swiftdash_render_links_manage',
		)
	);
}
add_action( 'swiftdash_register_widgets', 'swiftdash_register_links_widget' );

/**
 * Dashboard widget header: gear button to the Quick Links screen.
 */
function swiftdash_render_links_manage() {
	printf(
		'<a class="sd-icon-btn sd-ql-mini__manage" href="%1$s" aria-label="%2$s" title="%2$s">%3$s</a>',
		esc_url( admin_url( 'admin.php?page=swift-dash-links' ) ),
		esc_attr__( 'Manage shortcuts', 'swift-dashboard-analytics' ),
		swiftdash_icon( 'gear-six' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
	);
}

/**
 * Dashboard widget body: pinned shortcuts as pastel tiles.
 */
function swiftdash_render_links_widget() {
	$links = swiftdash_quick_links();
	$state = swiftdash_links_state( $links );

	if ( ! $state['pinned'] ) {
		swiftdash_render_empty_state( 'push-pin', __( 'No pinned shortcuts', 'swift-dashboard-analytics' ), __( 'Pin the tasks you use most and they will appear here.', 'swift-dashboard-analytics' ) );
		printf( '<p class="sd-ql-mini__empty-cta"><a class="sd-btn sd-btn--secondary sd-btn--sm" href="%s">%s</a></p>', esc_url( admin_url( 'admin.php?page=swift-dash-links' ) ), esc_html__( 'Choose shortcuts', 'swift-dashboard-analytics' ) );
		return;
	}

	echo '<ul class="sd-ql-mini">';
	foreach ( $state['pinned'] as $id ) {
		$link = $links[ $id ];
		printf(
			'<li><a class="sd-ql-mini__tile %1$s" href="%2$s" title="%3$s">%4$s<span class="sd-ql-mini__label">%5$s</span></a></li>',
			esc_attr( swiftdash_link_tone( $link ) ),
			esc_url( $link['url'] ),
			esc_attr( $link['description'] ),
			swiftdash_icon( $link['icon'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped markup.
			esc_html( $link['label'] )
		);
	}
	echo '</ul>';
}
