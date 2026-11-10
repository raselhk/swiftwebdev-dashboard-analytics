<?php
/**
 * AI & Search: AI crawler counter (server side, by user agent), AI visitors, AI crawlers and
 * AI access check cards on the dashboard.
 *
 * Free stores only what it shows: one daily total per AI bot in the swiftdash_dims table
 * (kind "aibot"). Each counted request also fires swiftdash_ai_bot_hit, so an add-on can keep
 * its own detail. Nothing is sent anywhere; the access check reads this site's own robots.txt.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * AI crawlers and fetchers, from each company's own documentation.
 *
 * @return array[] key => {
 *     @type string $label   Bot name as shown.
 *     @type string $company Company key (swiftdash_ai_companies()).
 *     @type string $purpose training|search|user.
 *     @type string $token   User agent substring and robots.txt token.
 *     @type string $docs    Official documentation URL.
 *     @type bool   $count   Count requests with this user agent. False for robots.txt-only tokens
 *                           (they never appear in a user agent) and general search crawlers.
 * }
 */
function swiftdash_ai_bots() {
	$openai     = 'https://developers.openai.com/api/docs/bots';
	$anthropic  = 'https://support.claude.com/en/articles/8896518';
	$perplexity = 'https://docs.perplexity.ai/guides/bots';
	$google     = 'https://developers.google.com/search/docs/crawling-indexing/google-common-crawlers';
	$google_ut  = 'https://developers.google.com/crawling/docs/crawlers-fetchers/google-user-triggered-fetchers';
	$apple      = 'https://support.apple.com/en-us/119829';
	$meta       = 'https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/';
	$amazon     = 'https://developer.amazon.com/amazonbot';
	$mistral    = 'https://docs.mistral.ai/robots';
	$bots       = array(
		'gptbot'                => array( 'GPTBot', 'openai', 'training', 'GPTBot', $openai, true ),
		'oai-searchbot'         => array( 'OAI-SearchBot', 'openai', 'search', 'OAI-SearchBot', $openai, true ),
		'chatgpt-user'          => array( 'ChatGPT-User', 'openai', 'user', 'ChatGPT-User', $openai, true ),
		'claudebot'             => array( 'ClaudeBot', 'anthropic', 'training', 'ClaudeBot', $anthropic, true ),
		'claude-searchbot'      => array( 'Claude-SearchBot', 'anthropic', 'search', 'Claude-SearchBot', $anthropic, true ),
		'claude-user'           => array( 'Claude-User', 'anthropic', 'user', 'Claude-User', $anthropic, true ),
		'perplexitybot'         => array( 'PerplexityBot', 'perplexity', 'search', 'PerplexityBot', $perplexity, true ),
		'perplexity-user'       => array( 'Perplexity-User', 'perplexity', 'user', 'Perplexity-User', $perplexity, true ),
		'googlebot'             => array( 'Googlebot', 'google', 'search', 'Googlebot', $google, false ),
		'google-extended'       => array( 'Google-Extended', 'google', 'training', 'Google-Extended', $google, false ),
		'google-cloudvertexbot' => array( 'Google-CloudVertexBot', 'google', 'user', 'Google-CloudVertexBot', $google, true ),
		'google-agent'          => array( 'Google-Agent', 'google', 'user', 'Google-Agent', $google_ut, true ),
		'google-geminotebook'   => array( 'Google-GeminiNotebook', 'google', 'user', 'Google-GeminiNotebook', $google_ut, true ),
		'google-notebooklm'     => array( 'Google-NotebookLM', 'google', 'user', 'Google-NotebookLM', $google_ut, true ),
		'bingbot'               => array( 'Bingbot', 'microsoft', 'search', 'bingbot', 'https://www.bing.com/webmasters/help/which-crawlers-does-bing-use-8c184ec0', false ),
		'applebot'              => array( 'Applebot', 'apple', 'search', 'Applebot', $apple, true ),
		'applebot-extended'     => array( 'Applebot-Extended', 'apple', 'training', 'Applebot-Extended', $apple, false ),
		'meta-externalagent'    => array( 'Meta-ExternalAgent', 'meta', 'training', 'meta-externalagent', $meta, true ),
		'meta-webindexer'       => array( 'Meta-WebIndexer', 'meta', 'search', 'meta-webindexer', $meta, true ),
		'meta-externalfetcher'  => array( 'Meta-ExternalFetcher', 'meta', 'user', 'meta-externalfetcher', $meta, true ),
		'amazonbot'             => array( 'Amazonbot', 'amazon', 'training', 'Amazonbot', $amazon, true ),
		'amzn-searchbot'        => array( 'Amzn-SearchBot', 'amazon', 'search', 'Amzn-SearchBot', $amazon, true ),
		'amzn-user'             => array( 'Amzn-User', 'amazon', 'user', 'Amzn-User', $amazon, true ),
		'duckassistbot'         => array( 'DuckAssistBot', 'duckduckgo', 'search', 'DuckAssistBot', 'https://duckduckgo.com/duckduckgo-help-pages/results/duckassistbot', true ),
		'mistralai-user'        => array( 'MistralAI-User', 'mistral', 'user', 'MistralAI-User', $mistral, true ),
		'mistralai-index'       => array( 'MistralAI-Index', 'mistral', 'search', 'MistralAI-Index', $mistral, true ),
		'mistralai-training'    => array( 'MistralAI-Training', 'mistral', 'training', 'MistralAI-Training', $mistral, true ),
		'ccbot'                 => array( 'CCBot', 'commoncrawl', 'training', 'CCBot', 'https://commoncrawl.org/ccbot', true ),
		'bytespider'            => array( 'Bytespider', 'bytedance', 'training', 'Bytespider', '', true ),
	);
	foreach ( $bots as $key => $bot ) {
		$bots[ $key ] = array_combine( array( 'label', 'company', 'purpose', 'token', 'docs', 'count' ), $bot );
	}
	/**
	 * Filters the AI crawlers counted on the AI crawlers card and read in the AI access check.
	 *
	 * @param array[] $bots key => { label, company, purpose, token, docs, count }.
	 */
	return (array) apply_filters( 'swiftdash_ai_bots', $bots );
}

/**
 * Companies behind the AI bots: key => array( name, brand key for swiftdash_render_brand_tile() ).
 *
 * @return array[]
 */
function swiftdash_ai_companies() {
	return array(
		'openai'      => array( 'OpenAI', 'openai' ),
		'anthropic'   => array( 'Anthropic', 'claude' ),
		'google'      => array( 'Google', 'google' ),
		'perplexity'  => array( 'Perplexity', 'perplexity' ),
		'microsoft'   => array( 'Microsoft', 'microsoft' ),
		'apple'       => array( 'Apple', 'apple' ),
		'meta'        => array( 'Meta', 'meta' ),
		'amazon'      => array( 'Amazon', 'amazon' ),
		'duckduckgo'  => array( 'DuckDuckGo', 'duckduckgo' ),
		'mistral'     => array( 'Mistral', 'mistralai' ),
		'commoncrawl' => array( 'Common Crawl', 'commoncrawl' ),
		'bytedance'   => array( 'ByteDance', 'bytedance' ),
	);
}

/**
 * Purpose labels.
 *
 * @return string[] training|search|user => label.
 */
function swiftdash_ai_purposes() {
	return array(
		'training' => __( 'Training', 'swiftwebdev-dashboard-analytics' ),
		'search'   => __( 'AI search', 'swiftwebdev-dashboard-analytics' ),
		'user'     => __( 'Asked by a user', 'swiftwebdev-dashboard-analytics' ),
	);
}

/**
 * Key of the counted AI bot a user agent claims to be, or ''.
 *
 * @param string $ua User agent.
 * @return string
 */
function swiftdash_ai_bot_match( $ua ) {
	static $regex = null, $keys = array();
	if ( null === $regex ) {
		$tokens = array();
		foreach ( swiftdash_ai_bots() as $key => $bot ) {
			if ( ! empty( $bot['count'] ) && '' !== (string) $bot['token'] ) {
				$keys[ strtolower( $bot['token'] ) ] = $key;
				$tokens[]                            = preg_quote( $bot['token'], '/' );
			}
		}
		$regex = $tokens ? '/' . implode( '|', $tokens ) . '/i' : '';
	}
	// One compiled pattern for all tokens; no per-bot loop on ordinary visits.
	if ( '' === $regex || strlen( $ua ) < 5 || ! preg_match( $regex, $ua, $m ) ) {
		return '';
	}
	return isset( $keys[ strtolower( $m[0] ) ] ) ? $keys[ strtolower( $m[0] ) ] : '';
}

/**
 * Count front-end requests from AI bots (pages, feeds, robots.txt and llms.txt when WordPress
 * serves them). Admin, AJAX, cron, CLI, XML-RPC, REST and login requests are skipped.
 */
function swiftdash_ai_bot_detect() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
		return;
	}
	if ( isset( $GLOBALS['pagenow'] ) && 'index.php' !== $GLOBALS['pagenow'] ) {
		return;
	}
	$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$key = swiftdash_ai_bot_match( $ua );
	if ( '' === $key ) {
		return;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks whether the request is a REST call.
	if ( isset( $_GET['rest_route'] ) || false !== strpos( $uri, '/' . rest_get_url_prefix() . '/' ) ) {
		return;
	}
	add_action(
		'shutdown',
		function () use ( $key, $uri ) {
			swiftdash_ai_bot_record( $key, $uri );
		}
	);
}
add_action( 'init', 'swiftdash_ai_bot_detect', 1 );

/**
 * Add one visit to today's total for a bot and tell add-ons about it.
 *
 * @param string $key Bot key.
 * @param string $uri Requested path and query.
 */
function swiftdash_ai_bot_record( $key, $uri ) {
	global $wpdb;
	$bots = swiftdash_ai_bots();
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'INSERT INTO %i (day, kind, item, count) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE count = count + 1', swiftdash_table( 'dims' ), wp_date( 'Y-m-d' ), 'aibot', $key )
	);
	/**
	 * Fires once per front-end request from an AI bot (matched by user agent only), at shutdown.
	 *
	 * @param string $bot_key     Key in swiftdash_ai_bots().
	 * @param string $purpose     training|search|user.
	 * @param string $request_uri Requested path and query string.
	 * @param int    $status      HTTP status code sent to the bot.
	 */
	do_action( 'swiftdash_ai_bot_hit', $key, isset( $bots[ $key ]['purpose'] ) ? $bots[ $key ]['purpose'] : '', $uri, (int) http_response_code() );
}

/**
 * Totals of one dims kind for the last $days days and the equal period before it.
 *
 * @param string $kind      Dims kind, e.g. ai or aibot.
 * @param int    $days      Number of days (1-90).
 * @param bool   $full_days End with yesterday instead of today.
 * @return array { items: key => { count, last (Y-m-d) }, highest first; total; previous; daily: int[] oldest first }.
 */
function swiftdash_dims_period( $kind, $days, $full_days = false ) {
	global $wpdb;
	$days     = max( 1, min( 90, (int) $days ) );
	$today    = new DateTimeImmutable( 'today', wp_timezone() );
	$end      = $full_days ? $today : $today->modify( '+1 day' ); // Exclusive.
	$start    = $end->modify( '-' . $days . ' days' )->format( 'Y-m-d' );
	$first    = $end->modify( '-' . ( 2 * $days ) . ' days' )->format( 'Y-m-d' );
	$rows     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT item, SUM(CASE WHEN day >= %s THEN count ELSE 0 END) AS cur, SUM(CASE WHEN day < %s THEN count ELSE 0 END) AS prev, MAX(day) AS last FROM %i WHERE kind = %s AND day >= %s AND day < %s GROUP BY item ORDER BY cur DESC, item ASC', $start, $start, swiftdash_table( 'dims' ), (string) $kind, $first, $end->format( 'Y-m-d' ) ),
		ARRAY_A
	);
	$out      = array(
		'items'    => array(),
		'total'    => 0,
		'previous' => 0,
		'daily'    => array(),
	);
	$days_map = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT day, SUM(count) AS total FROM %i WHERE kind = %s AND day >= %s AND day < %s GROUP BY day', swiftdash_table( 'dims' ), (string) $kind, $start, $end->format( 'Y-m-d' ) ),
		OBJECT_K
	);
	for ( $i = $days; $i >= 1; $i-- ) {
		$day            = $end->modify( '-' . $i . ' days' )->format( 'Y-m-d' );
		$out['daily'][] = isset( $days_map[ $day ] ) ? (int) $days_map[ $day ]->total : 0;
	}
	foreach ( (array) $rows as $row ) {
		$out['total']    += (int) $row['cur'];
		$out['previous'] += (int) $row['prev'];
		if ( (int) $row['cur'] > 0 ) {
			$out['items'][ $row['item'] ] = array(
				'count' => (int) $row['cur'],
				'last'  => (string) $row['last'],
			);
		}
	}
	return $out;
}

/**
 * Whether a page cache probably answers requests before WordPress runs.
 *
 * @return bool
 */
function swiftdash_page_cache_active() {
	// WP_CACHE covers most cache plugins; the rest are plugins and hosts that cache without it.
	foreach ( array( 'WP_CACHE', 'WP_ROCKET_VERSION', 'LSCWP_V', 'NITROPACK_VERSION', 'WPE_APIKEY', 'KINSTAMU_VERSION' ) as $constant ) {
		if ( defined( $constant ) && constant( $constant ) ) {
			return true;
		}
	}
	return file_exists( WP_CONTENT_DIR . '/advanced-cache.php' );
}

/**
 * Split robots.txt into groups of user agents and their Allow/Disallow rules.
 *
 * @param string $body robots.txt content.
 * @return array[] Each: array( string[] lowercased agents, array[] rules as array( allow|disallow, path ) ).
 */
function swiftdash_robots_parse( $body ) {
	$groups   = array();
	$agents   = array();
	$rules    = array();
	$in_rules = false;
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $body ) as $line ) {
		$line = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( ! preg_match( '/^([a-z-]+)\s*:\s*(.*)$/i', $line, $m ) ) {
			continue;
		}
		$field = strtolower( $m[1] );
		if ( 'user-agent' === $field ) {
			if ( $in_rules ) {
				$groups[] = array( $agents, $rules );
				$agents   = array();
				$rules    = array();
				$in_rules = false;
			}
			$agents[] = strtolower( trim( $m[2] ) );
		} elseif ( $agents && ( 'allow' === $field || 'disallow' === $field ) ) {
			$in_rules = true;
			$rules[]  = array( $field, trim( $m[2] ) );
		}
	}
	if ( $agents ) {
		$groups[] = array( $agents, $rules );
	}
	return $groups;
}

/**
 * Whether robots.txt keeps a crawler out of the whole site: the groups naming its token (else the
 * "*" group) disallow "/" without allowing it again.
 *
 * @param array[] $groups swiftdash_robots_parse().
 * @param string  $token  robots.txt token.
 * @return bool
 */
function swiftdash_robots_blocks( $groups, $token ) {
	$rules = null;
	foreach ( array( strtolower( $token ), '*' ) as $agent ) {
		foreach ( $groups as $group ) {
			if ( in_array( $agent, $group[0], true ) ) {
				$rules = array_merge( (array) $rules, $group[1] );
			}
		}
		if ( null !== $rules ) {
			break;
		}
	}
	// ponytail: checks the home page path only (Allow wins a tie, RFC 9309); a full path matcher if per-section rules matter.
	$allow    = false;
	$disallow = false;
	foreach ( (array) $rules as $rule ) {
		if ( in_array( $rule[1], array( '/', '/*', '*', '/$' ), true ) ) {
			$allow    = $allow || 'allow' === $rule[0];
			$disallow = $disallow || 'disallow' === $rule[0];
		}
	}
	return $disallow && ! $allow;
}

/**
 * Site root URL (scheme and host). Crawlers read robots.txt and llms.txt from the domain root,
 * even when WordPress lives in a subfolder.
 *
 * @param string $path Path starting with a slash.
 * @return string
 */
function swiftdash_root_url( $path ) {
	$home = wp_parse_url( home_url() );
	$port = isset( $home['port'] ) ? ':' . $home['port'] : '';
	return $home['scheme'] . '://' . $home['host'] . $port . $path;
}

/**
 * Read this site's own robots.txt and llms.txt (cached 12 hours, 1 hour after an error).
 *
 * @return array {
 *     @type int    $checked  Unix time of the check.
 *     @type string $status   ok, missing (no robots.txt: everything allowed) or error.
 *     @type int    $code     HTTP status, 0 when the request failed.
 *     @type bool   $physical robots.txt is a file on the server (not generated by WordPress).
 *     @type bool   $llms     llms.txt is present.
 *     @type bool[] $blocked  robots.txt token (lowercased) => kept out of the site.
 *     @type bool   $all      The rules for every crawler ("*") keep them out of the site.
 * }
 */
function swiftdash_ai_access() {
	$cached = get_transient( 'swiftdash_ai_access' );
	if ( is_array( $cached ) && isset( $cached['status'] ) ) {
		return $cached;
	}
	$root = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
	$args = array(
		'timeout'             => 5,
		'redirection'         => 3,
		'limit_response_size' => 512 * KB_IN_BYTES,
		/** This filter is documented in wp-includes/class-wp-http-streams.php */
		'sslverify'           => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter for requests to the own site.
	);

	$response = wp_remote_get( swiftdash_root_url( '/robots.txt' ), $args );
	$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$status   = 200 === $code ? 'ok' : ( $code >= 400 && $code < 500 ? 'missing' : 'error' );
	$groups   = 'ok' === $status ? swiftdash_robots_parse( wp_remote_retrieve_body( $response ) ) : array();
	$blocked  = array();
	foreach ( swiftdash_ai_bots() as $bot ) {
		$blocked[ strtolower( $bot['token'] ) ] = swiftdash_robots_blocks( $groups, $bot['token'] );
	}

	$llms = file_exists( $root . 'llms.txt' );
	if ( ! $llms && 'error' !== $status ) {
		$args['limit_response_size'] = KB_IN_BYTES;
		$res                         = wp_remote_get( swiftdash_root_url( '/llms.txt' ), $args );
		$llms                        = 200 === (int) wp_remote_retrieve_response_code( $res ) && false === stripos( (string) wp_remote_retrieve_header( $res, 'content-type' ), 'html' );
	}

	$data = array(
		'checked'  => time(),
		'status'   => $status,
		'code'     => $code,
		'physical' => file_exists( $root . 'robots.txt' ),
		'llms'     => $llms,
		'blocked'  => $blocked,
		'all'      => swiftdash_robots_blocks( $groups, '*' ),
	);
	set_transient( 'swiftdash_ai_access', $data, 'error' === $status ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );
	return $data;
}

/* ---------- Dashboard cards ---------- */

/**
 * Register the AI & Search row: AI crawlers and AI visitors side by side, the access check below.
 */
function swiftdash_ai_register_widgets() {
	$range = swiftdash_analytics_range();
	$cards = array(
		'ai-crawlers' => array( __( 'AI crawlers', 'swiftwebdev-dashboard-analytics' ), __( 'Visits from AI bots that read your pages, by company and purpose.', 'swiftwebdev-dashboard-analytics' ), 8, 'swiftdash_render_ai_crawlers_widget', null ),
		'insight-ai'  => array( __( 'AI visitors', 'swiftwebdev-dashboard-analytics' ), __( 'People who clicked a link to your site in an AI assistant answer.', 'swiftwebdev-dashboard-analytics' ), 4, 'swiftdash_render_ai_widget', null ),
		'ai-access'   => array( __( 'AI access check', 'swiftwebdev-dashboard-analytics' ), __( 'Which AI companies your robots.txt lets in, for AI search and for training.', 'swiftwebdev-dashboard-analytics' ), 12, 'swiftdash_render_ai_access_widget', 'swiftdash_render_ai_access_status' ),
	);
	$order = 10;
	foreach ( $cards as $id => $c ) {
		swiftdash_register_widget(
			$id,
			array(
				'title'       => $c[0],
				'description' => $c[1],
				'area'        => 'ai',
				'order'       => $order,
				'span'        => $c[2],
				'render'      => $c[3],
				'actions'     => $c[4],
				'days'        => $range,
			)
		);
		$order += 10;
	}
}
add_action( 'swiftdash_register_widgets', 'swiftdash_ai_register_widgets' );

/**
 * Fit the insights row to the cards it ends up with (an add-on may take one over or add one),
 * so it never leaves a hole: three across, two across or one full width.
 */
function swiftdash_ai_fit_insights() {
	$cards = swiftdash_get_widgets( 'dashboard', 'insights' );
	$count = count( $cards );
	$span  = 1 === $count ? 12 : ( 0 === $count % 2 && 0 !== $count % 3 ? 6 : 4 );
	foreach ( $cards as $id => $card ) {
		swiftdash_register_widget( $id, array( 'span' => $span ) + $card );
	}
}
add_action( 'swiftdash_register_widgets', 'swiftdash_ai_fit_insights', 99 );

/**
 * Whether the small PRO links show on the AI cards: never with PRO active, never after the
 * user chose "Hide PRO tips".
 *
 * @return bool
 */
function swiftdash_show_pro_tips() {
	/** This filter is documented in includes/widgets.php */
	return (bool) apply_filters( 'swiftdash_show_upgrade', true ) && ! swiftdash_prefs()['pro_tips_hidden'];
}

/**
 * One text link to the PRO add-on at the foot of an AI card, with the per-user hide button.
 *
 * @param string $text Link text.
 * @param string $card Card id, for the campaign tag.
 */
function swiftdash_render_pro_tip( $text, $card ) {
	if ( ! swiftdash_show_pro_tips() ) {
		return;
	}
	$url = add_query_arg(
		array(
			'utm_source'  => 'plugin',
			'utm_medium'  => 'dashboard-ai',
			'utm_content' => sanitize_key( $card ),
		),
		SWIFTDASH_ADDON_URL
	);
	printf(
		'<p class="sd-ai-tip" data-sd-pro-tip><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s%3$s<span class="screen-reader-text"> %4$s</span></a><button type="button" class="sd-ai-tip__hide" data-sd-hide-pro-tips>%5$s</button></p>',
		esc_url( $url ),
		esc_html( $text ),
		wp_kses( swiftdash_icon( 'arrow-square-out' ), swiftdash_icon_kses() ),
		esc_html__( '(opens in a new tab)', 'swiftwebdev-dashboard-analytics' ),
		esc_html__( 'Hide PRO tips', 'swiftwebdev-dashboard-analytics' )
	);
}

/**
 * Big number with its change versus the previous period.
 *
 * @param string $label    Label above the number ('' for none).
 * @param int    $total    This period.
 * @param int    $previous Period before.
 * @param int    $days     Period length.
 * @param int[]  $series   Daily values for a sparkline beside the number, or none.
 */
function swiftdash_ai_figure( $label, $total, $previous, $days, $series = array() ) {
	echo '<div class="sd-ai-figure">';
	if ( '' !== $label ) {
		echo '<span class="sd-ai-figure__label">' . esc_html( $label ) . '</span>';
	}
	echo '<span class="sd-ai-figure__row"><span class="sd-kpi__value">' . esc_html( number_format_i18n( $total ) ) . '</span>';
	swiftdash_render_sparkline( $series );
	echo '</span>';
	echo '<span class="sd-kpi__meta">';
	swiftdash_render_trend( swiftdash_kpi_change( $total, $previous ), $days );
	echo '</span></div>';
}

/**
 * "Today", "Yesterday" or a short date.
 *
 * @param string $date Y-m-d.
 * @return string
 */
function swiftdash_ai_day_label( $date ) {
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	if ( $today->format( 'Y-m-d' ) === $date ) {
		return __( 'Today', 'swiftwebdev-dashboard-analytics' );
	}
	if ( $today->modify( '-1 day' )->format( 'Y-m-d' ) === $date ) {
		return __( 'Yesterday', 'swiftwebdev-dashboard-analytics' );
	}
	return date_i18n( _x( 'M j', 'last seen date format', 'swiftwebdev-dashboard-analytics' ), strtotime( $date . ' 12:00:00' ) );
}

/**
 * Page cache line under the crawler counts, with what it means and how to count more.
 */
function swiftdash_ai_cache_note() {
	printf(
		'<p class="sd-ai-note">%1$s<span>%2$s</span> <button type="button" class="sd-tip sd-tip--text" aria-describedby="sd-ai-cache-tip">%3$s<span class="sd-tip__text" role="tooltip" id="sd-ai-cache-tip">%4$s</span></button></p>',
		wp_kses( swiftdash_icon( 'info' ), swiftdash_icon_kses() ),
		esc_html__( 'Page cache detected: these are minimum counts.', 'swiftwebdev-dashboard-analytics' ),
		esc_html__( 'Why?', 'swiftwebdev-dashboard-analytics' ),
		esc_html__( 'Your page cache answers many requests before WordPress runs, so those bot visits cannot be counted here. Pages the cache misses are still counted. To count more, add the AI bot names to the "rejected user agents" list in your cache settings.', 'swiftwebdev-dashboard-analytics' )
	);
}

/**
 * AI crawlers card: AI bot visits next to AI visitors, split by purpose and by company.
 *
 * @param array $widget Widget args (days).
 */
function swiftdash_render_ai_crawlers_widget( $widget ) {
	$days  = (int) $widget['days'];
	$bots  = swiftdash_dims_period( 'aibot', $days );
	$cache = swiftdash_page_cache_active();

	if ( ! $bots['total'] ) {
		$installed = (int) get_option( 'swiftdash_installed', time() );
		if ( $bots['previous'] ) {
			swiftdash_render_empty_state( 'info', __( 'No AI bot visits in this period', 'swiftwebdev-dashboard-analytics' ), __( 'AI bots visited before this period. Pick a longer period to see them.', 'swiftwebdev-dashboard-analytics' ) );
		} elseif ( $cache && time() - $installed > 7 * DAY_IN_SECONDS ) {
			swiftdash_render_empty_state( 'info', __( 'No AI bot visits reached WordPress', 'swiftwebdev-dashboard-analytics' ), __( 'Your page cache answers most requests before WordPress runs, so bots cannot be counted here.', 'swiftwebdev-dashboard-analytics' ) );
		} else {
			swiftdash_render_empty_state( 'clock', __( 'Counting starts now', 'swiftwebdev-dashboard-analytics' ), __( 'AI bots usually show up within a few days. Nothing is sent anywhere; counts stay in your database.', 'swiftwebdev-dashboard-analytics' ) );
		}
		if ( $cache ) {
			swiftdash_ai_cache_note();
		}
		return;
	}

	$registry  = swiftdash_ai_bots();
	$companies = swiftdash_ai_companies();
	$purposes  = array_fill_keys( array_keys( swiftdash_ai_purposes() ), 0 );
	$by_co     = array();
	foreach ( $bots['items'] as $key => $item ) {
		$bot = isset( $registry[ $key ] ) ? $registry[ $key ] : null;
		$co  = $bot ? $bot['company'] : '';
		if ( $bot && isset( $purposes[ $bot['purpose'] ] ) ) {
			$purposes[ $bot['purpose'] ] += $item['count'];
		}
		if ( ! isset( $by_co[ $co ] ) ) {
			$by_co[ $co ] = array(
				'count' => 0,
				'last'  => '',
			);
		}
		$by_co[ $co ]['count'] += $item['count'];
		$by_co[ $co ]['last']   = max( $by_co[ $co ]['last'], $item['last'] );
	}
	uasort(
		$by_co,
		function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		}
	);
	$visitors = swiftdash_dims_period( 'ai', $days );
	?>
	<div class="sd-ai-split">
		<div class="sd-ai-split__main">
			<div class="sd-ai-pair">
				<?php
				swiftdash_ai_figure( __( 'AI bot visits', 'swiftwebdev-dashboard-analytics' ), $bots['total'], $bots['previous'], $days );
				swiftdash_ai_figure( __( 'AI visitors', 'swiftwebdev-dashboard-analytics' ), $visitors['total'], $visitors['previous'], $days );
				?>
			</div>
			<div class="sd-ai-daily">
				<span class="sd-ai-figure__label"><?php esc_html_e( 'AI bot visits per day', 'swiftwebdev-dashboard-analytics' ); ?></span>
				<?php swiftdash_render_sparkline( $bots['daily'] ); ?>
			</div>
			<ul class="sd-ai-chips" aria-label="<?php esc_attr_e( 'AI bot visits by purpose', 'swiftwebdev-dashboard-analytics' ); ?>">
				<?php foreach ( swiftdash_ai_purposes() as $purpose => $label ) : ?>
					<li class="sd-ai-chip sd-ai-chip--<?php echo esc_attr( $purpose ); ?>"><span class="sd-ai-chip__dot" aria-hidden="true"></span><?php echo esc_html( $label ); ?> <strong><?php echo esc_html( number_format_i18n( $purposes[ $purpose ] ) ); ?></strong></li>
				<?php endforeach; ?>
			</ul>
			<p class="sd-field__help"><?php esc_html_e( 'Training bots collect pages for AI models. AI search bots index pages for answers. "Asked by a user" means someone asked an assistant to open your page.', 'swiftwebdev-dashboard-analytics' ); ?></p>
		</div>
		<div class="sd-ai-split__list">
			<?php
			$rows = array();
			foreach ( $by_co as $co => $c ) {
				$rows[] = array(
					'label' => isset( $companies[ $co ] ) ? $companies[ $co ][0] : __( 'Other', 'swiftwebdev-dashboard-analytics' ),
					/* translators: %s: day, e.g. Today or Nov 7. */
					'meta'  => sprintf( __( 'Last seen %s', 'swiftwebdev-dashboard-analytics' ), swiftdash_ai_day_label( $c['last'] ) ),
					'icon'  => '',
					'brand' => isset( $companies[ $co ] ) ? $companies[ $co ][1] : '',
					'count' => $c['count'],
					'value' => swiftdash_insights_count( $c['count'], false ),
				);
			}
			swiftdash_insights_list( $rows );
			?>
		</div>
	</div>
	<?php
	if ( $cache ) {
		swiftdash_ai_cache_note();
	}
	swiftdash_render_pro_tip( __( 'Which pages did they read? See PRO', 'swiftwebdev-dashboard-analytics' ), 'ai-crawlers' );
}

/**
 * AI visitors card: visits from AI assistants, by referrer or utm_source tag.
 *
 * @param array $widget Widget args (days).
 */
function swiftdash_render_ai_widget( $widget ) {
	$days    = (int) $widget['days'];
	$ai      = swiftdash_dims_period( 'ai', $days );
	$sources = swiftdash_ai_sources();
	if ( ! $ai['total'] ) {
		swiftdash_render_empty_state(
			'chats-circle-fill',
			__( 'No visits from AI assistants yet', 'swiftwebdev-dashboard-analytics' ),
			sprintf(
				/* translators: %s: number of other AI assistants. */
				__( 'Most sites get under 1 in 300 visits from AI. We count ChatGPT, Perplexity, Gemini, Claude, Copilot and %s more as soon as they arrive.', 'swiftwebdev-dashboard-analytics' ),
				number_format_i18n( max( 0, count( $sources ) - 5 ) )
			)
		);
		return;
	}
	$sessions = (int) swiftdash_analytics_stats( $days )['totals']['sessions'];
	$share    = $sessions ? $ai['total'] / $sessions * 100 : 0;
	swiftdash_ai_figure( '', $ai['total'], $ai['previous'], $days, $ai['daily'] );
	if ( $sessions ) {
		/* translators: %s: percentage number. */
		echo '<p class="sd-ai-share">' . esc_html( sprintf( __( '%s%% of all visits', 'swiftwebdev-dashboard-analytics' ), number_format_i18n( $share, $share < 10 ? 1 : 0 ) ) ) . '</p>';
	}
	$rows = array();
	foreach ( $ai['items'] as $key => $item ) {
		$rows[] = array(
			'label' => isset( $sources[ $key ] ) ? $sources[ $key ]['label'] : $key,
			'meta'  => '',
			'icon'  => '',
			'brand' => swiftdash_ai_brand( $key ),
			'count' => $item['count'],
			'value' => swiftdash_insights_count( $item['count'], false ),
		);
	}
	swiftdash_insights_list( $rows );
	swiftdash_render_pro_tip( __( 'Which pages do they land on? See PRO', 'swiftwebdev-dashboard-analytics' ), 'ai-visitors' );
}

/**
 * Per company: whether its AI search and training crawlers may read the site.
 *
 * @param array $access swiftdash_ai_access().
 * @return array[] company key => { search: bool|null, training: bool|null } (true = allowed, null = no such crawler).
 */
function swiftdash_ai_access_by_company( $access ) {
	$out = array_fill_keys(
		array_keys( swiftdash_ai_companies() ),
		array(
			'search'   => null,
			'training' => null,
		)
	);
	foreach ( swiftdash_ai_bots() as $bot ) {
		if ( ! isset( $out[ $bot['company'] ] ) || ! in_array( $bot['purpose'], array( 'search', 'training' ), true ) ) {
			continue;
		}
		$token   = strtolower( $bot['token'] );
		$allowed = empty( $access['blocked'][ $token ] );
		$current = $out[ $bot['company'] ][ $bot['purpose'] ];
		// A company counts as blocked when any of its crawlers for that purpose is blocked.
		$out[ $bot['company'] ][ $bot['purpose'] ] = null === $current ? $allowed : ( $current && $allowed );
	}
	return $out;
}

/**
 * Status pill in the access check header.
 */
function swiftdash_render_ai_access_status() {
	$access = swiftdash_ai_access();
	if ( 'error' === $access['status'] ) {
		$pill = array( 'warning', 'warning-circle-fill', __( 'Not checked', 'swiftwebdev-dashboard-analytics' ) );
	} elseif ( in_array( false, wp_list_pluck( swiftdash_ai_access_by_company( $access ), 'search' ), true ) ) {
		$pill = array( 'danger', 'warning-fill', __( 'AI search blocked', 'swiftwebdev-dashboard-analytics' ) );
	} else {
		$pill = array( 'success', 'check-circle-fill', __( 'AI search allowed', 'swiftwebdev-dashboard-analytics' ) );
	}
	printf( '<span class="sd-status sd-status--%1$s">%2$s%3$s</span>', esc_attr( $pill[0] ), wp_kses( swiftdash_icon( $pill[1] ), swiftdash_icon_kses() ), esc_html( $pill[2] ) );
}

/**
 * AI access check card: what robots.txt allows per AI company. Read-only.
 */
function swiftdash_render_ai_access_widget() {
	$access = swiftdash_ai_access();
	$robots = swiftdash_root_url( '/robots.txt' );
	if ( 'error' === $access['status'] ) {
		$reason = $access['code']
			/* translators: %d: HTTP status code. */
			? sprintf( __( 'HTTP %d', 'swiftwebdev-dashboard-analytics' ), $access['code'] )
			: __( 'no response', 'swiftwebdev-dashboard-analytics' );
		swiftdash_render_empty_state(
			'warning-circle',
			/* translators: %s: reason, e.g. HTTP 500. */
			sprintf( __( 'We could not read your robots.txt (%s)', 'swiftwebdev-dashboard-analytics' ), $reason ),
			/* translators: %s: robots.txt URL. */
			sprintf( __( 'Check that %s opens in a browser. We try again within an hour.', 'swiftwebdev-dashboard-analytics' ), $robots )
		);
		return;
	}

	$companies = swiftdash_ai_companies();
	$results   = swiftdash_ai_access_by_company( $access );
	$blocked   = array();
	foreach ( $results as $co => $r ) {
		if ( false === $r['search'] ) {
			$blocked[] = $companies[ $co ][0];
		}
	}
	$source = 'missing' === $access['status']
		? __( 'None, so every crawler is allowed', 'swiftwebdev-dashboard-analytics' )
		: ( $access['physical'] ? __( 'File on your server', 'swiftwebdev-dashboard-analytics' ) : __( 'Generated by WordPress', 'swiftwebdev-dashboard-analytics' ) );
	$facts  = array(
		array( 'file-text', __( 'robots.txt', 'swiftwebdev-dashboard-analytics' ), $source ),
		array( 'file-text', __( 'llms.txt', 'swiftwebdev-dashboard-analytics' ), $access['llms'] ? __( 'Found', 'swiftwebdev-dashboard-analytics' ) : __( 'Not found', 'swiftwebdev-dashboard-analytics' ) ),
		/* translators: %s: time since the check, e.g. 2 hours. */
		array( 'clock', __( 'Checked', 'swiftwebdev-dashboard-analytics' ), sprintf( __( '%s ago', 'swiftwebdev-dashboard-analytics' ), human_time_diff( $access['checked'] ) ) ),
	);
	$states = array(
		'search'   => __( 'AI search', 'swiftwebdev-dashboard-analytics' ),
		'training' => __( 'Training', 'swiftwebdev-dashboard-analytics' ),
	);
	?>
	<ul class="sd-ai-facts">
		<?php foreach ( $facts as $fact ) : ?>
			<li><?php echo wp_kses( swiftdash_icon( $fact[0] ), swiftdash_icon_kses() ); ?><span><?php echo esc_html( $fact[1] ); ?></span><strong><?php echo esc_html( $fact[2] ); ?></strong></li>
		<?php endforeach; ?>
	</ul>
	<?php if ( $blocked ) : ?>
		<div class="sd-ai-alert">
			<?php echo wp_kses( swiftdash_icon( 'warning-fill' ), swiftdash_icon_kses() ); ?>
			<p>
				<?php
				/* translators: %l: list of company names, e.g. OpenAI and Perplexity. */
				echo esc_html( wp_sprintf( __( 'AI search is blocked for %l. Their assistants cannot show your pages in answers, for example ChatGPT search for OpenAI.', 'swiftwebdev-dashboard-analytics' ), $blocked ) );
				if ( ! empty( $access['all'] ) && ! get_option( 'blog_public' ) ) {
					echo ' ';
					printf(
						/* translators: %s: link to Settings > Reading. */
						esc_html__( 'Search engine visibility is turned off in %s, so WordPress asks every crawler to stay away.', 'swiftwebdev-dashboard-analytics' ),
						'<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Settings > Reading', 'swiftwebdev-dashboard-analytics' ) . '</a>'
					);
				}
				?>
			</p>
		</div>
	<?php endif; ?>
	<ul class="sd-ai-access">
		<?php foreach ( $results as $co => $r ) : ?>
			<li class="sd-ai-access__row<?php echo false === $r['search'] ? ' is-blocked' : ''; ?>">
				<?php swiftdash_render_brand_tile( $companies[ $co ][1] ); ?>
				<span class="sd-ai-access__name"><?php echo esc_html( $companies[ $co ][0] ); ?></span>
				<?php
				foreach ( $states as $purpose => $label ) {
					$allowed = $r[ $purpose ];
					if ( null === $allowed ) {
						$state = array( 'none', '', __( 'No crawler', 'swiftwebdev-dashboard-analytics' ) );
					} elseif ( $allowed ) {
						$state = array( 'allowed', 'check-circle-fill', __( 'Allowed', 'swiftwebdev-dashboard-analytics' ) );
					} else {
						$state = array( 'search' === $purpose ? 'danger' : 'blocked', 'search' === $purpose ? 'warning-fill' : 'lock-simple-fill', __( 'Blocked', 'swiftwebdev-dashboard-analytics' ) );
					}
					printf(
						'<span class="sd-ai-state sd-ai-state--%1$s"><span class="sd-ai-state__label">%2$s</span>%3$s<span>%4$s</span></span>',
						esc_attr( $state[0] ),
						esc_html( $label ),
						$state[1] ? wp_kses( swiftdash_icon( $state[1] ), swiftdash_icon_kses() ) : '',
						esc_html( $state[2] )
					);
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="sd-field__help"><?php esc_html_e( 'Read-only check of your robots.txt for the whole site; nothing is changed. Blocking training keeps your pages out of AI models. Blocking AI search keeps them out of AI answers.', 'swiftwebdev-dashboard-analytics' ); ?></p>
	<?php
	swiftdash_render_pro_tip( __( 'Allow AI search and block training in one click? See PRO', 'swiftwebdev-dashboard-analytics' ), 'ai-access' );
}
