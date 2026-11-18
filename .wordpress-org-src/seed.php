<?php
// Demo data for the wp.org screenshots: "Northwind" coffee roasters (fictional), on the dev site
// localhost/swift-email-wp. It rewrites options, posts, users and the plugin tables: back up the
// database first and restore it afterwards (steps at the top of capture.cjs).
//   tools/wp eval-file swiftwebdev-dashboard-analytics/.wordpress-org-src/seed.php <added.txt> --user=1
//   <added.txt>: list of the upload files this creates (delete them after the restore).
// The toolbar live panel shows the last 5 minutes: run capture.cjs right after this.
global $wpdb, $wp_rewrite;
mt_srand( 7 );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$added = array();
$list  = isset( $args[0] ) ? $args[0] : '';

// The free plugin only (wp.org screenshots never show the PRO add-on).
update_option( 'active_plugins', array( 'swiftwebdev-dashboard-analytics/swiftwebdev-dashboard-analytics.php' ) );
update_option( 'blogname', 'Northwind' ); // Short: the menu brand block truncates long names.
update_option( 'blogdescription', 'Small-batch coffee roasters' );
update_option( 'admin_email', 'alex@northwindcoffee.com' );
// Through $wp_rewrite: page titles are looked up below, with the new rules.
$wp_rewrite->set_permalink_structure( '/blog/%postname%/' );
$wp_rewrite->flush_rules( false );
update_option( 'blog_public', 1 );

function nw_attach( $src, $title, &$added ) {
	$up  = wp_upload_dir();
	$dst = $up['path'] . '/' . basename( $src );
	copy( $src, $dst );
	$id   = wp_insert_attachment( array( 'post_mime_type' => wp_check_filetype( $dst )['type'], 'post_title' => $title, 'post_status' => 'inherit' ), $dst );
	$meta = wp_generate_attachment_metadata( $id, $dst );
	wp_update_attachment_metadata( $id, $meta );
	$added[] = $dst;
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $s ) {
		$added[] = $up['path'] . '/' . $s['file'];
	}
	return $id;
}
// Site icon (the menu brand block is site title + icon).
update_option( 'site_icon', nw_attach( dirname( __DIR__, 2 ) . '/youtube/_screens/_scripts/northwind-icon.png', 'Northwind icon', $added ) );
$cafe = nw_attach( __DIR__ . '/media/new-roastery-cafe.jpg', 'New roastery cafe', $added );
$bag  = nw_attach( __DIR__ . '/media/holiday-blend-bag.jpg', 'Holiday blend bag', $added );
$cold_img = nw_attach( __DIR__ . '/media/cold-brew-bottles.jpg', 'Cold brew bottles', $added );

$s               = get_option( 'swiftdash_settings', array() );
$s['brand_name'] = '';
$s['brand_logo'] = 0;
update_option( 'swiftdash_settings', $s );

// Hide unrelated content (dev-site IDs); fewer "new" matches so every search group fits the panel.
foreach ( array( 1, 2, 3, 9, 10, 11, 12, 28, 29, 165 ) as $id ) {
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'ID' => $id ) );
}
$wpdb->update( $wpdb->posts, array( 'post_title' => 'Ethiopia Yirgacheffe: Our Latest Roast' ), array( 'ID' => 35 ) );
clean_post_cache( 35 );
$cold = wp_insert_post( array( 'post_title' => 'Cold Brew at Home: A Simple Guide', 'post_name' => 'cold-brew-at-home', 'post_status' => 'publish', 'post_type' => 'post', 'post_date' => wp_date( 'Y-m-d H:i:s', strtotime( '-40 days' ) ), 'post_content' => '<!-- wp:paragraph --><p>Cold brew is the easiest coffee you will ever make: coarse grounds, cold water and a little patience. No special gear, no hot kettle, and it keeps in the fridge for a week.</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">What you need</h2><!-- /wp:heading --><!-- wp:paragraph --><p>A large jar, 100 grams of coarsely ground coffee and one liter of cold, filtered water. A medium roast with chocolate notes, like our Northwind House Blend, gives a smooth and sweet cup.</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">Steep, strain, enjoy</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Stir the grounds into the water, cover the jar and leave it in the fridge for 16 to 18 hours. Strain it through a paper filter, then dilute one part concentrate with one part water or milk over ice.</p><!-- /wp:paragraph -->' ) );
set_post_thumbnail( $cold, $cold_img );
set_post_thumbnail( 34, $cafe );
set_post_thumbnail( 35, $bag );
wp_insert_post( array( 'post_title' => 'New Holiday Blend: Winter Spice', 'post_name' => 'new-holiday-blend', 'post_status' => 'draft', 'post_type' => 'post' ) );
wp_insert_post( array( 'post_title' => 'Subscriptions', 'post_name' => 'subscriptions', 'post_status' => 'publish', 'post_type' => 'page' ) );

// People.
wp_update_user( array( 'ID' => 1, 'display_name' => 'Alex Rivera', 'first_name' => 'Alex', 'last_name' => 'Rivera', 'user_email' => 'alex@northwindcoffee.com' ) );
foreach ( array( array( 'jordan', 'Jordan Newman', 'jordan@northwindcoffee.com', 'editor' ), array( 'maya', 'Maya Chen', 'maya@northwindcoffee.com', 'author' ) ) as $u ) {
	if ( ! username_exists( $u[0] ) ) {
		wp_insert_user( array( 'user_login' => $u[0], 'display_name' => $u[1], 'user_email' => $u[2], 'role' => $u[3], 'user_pass' => wp_generate_password() ) );
	}
}

// 180 days of daily totals and dims. Page paths keep the site's subfolder, like the tracker stores them.
$daily = $wpdb->prefix . 'swiftdash_daily';
$dims  = $wpdb->prefix . 'swiftdash_dims';
$hits  = $wpdb->prefix . 'swiftdash_hits';
$wpdb->query( "DELETE FROM $daily" );
$wpdb->query( "DELETE FROM $dims" );
$tz    = wp_timezone();
$today = new DateTimeImmutable( 'today', $tz );
$p     = wp_parse_url( home_url(), PHP_URL_PATH ) ?: '';
$pages = array( '/' => 22, '/shop/' => 15, '/blog/how-to-brew-pour-over-at-home/' => 12, '/menu-pricing/' => 9, '/blog/choosing-the-right-grind-size/' => 8, '/blog/cold-brew-at-home/' => 7, '/wholesale/' => 5, '/subscriptions/' => 5, '/blog/our-new-ethiopia-yirgacheffe-roast/' => 4, '/about-us/' => 4, '/contact/' => 3 );
$refs  = array( 'google.com' => 58, 'instagram.com' => 11, 'bing.com' => 6, 'facebook.com' => 5, 'reddit.com' => 4, 'duckduckgo.com' => 3, 'youtube.com' => 2 );
// AI assistants: enough that ChatGPT and Perplexity rank in Referrers (with the "AI" tag).
$ais    = array( 'chatgpt' => 50, 'perplexity' => 28, 'gemini' => 10, 'claude' => 8, 'copilot' => 4 );
$aihost = array( 'chatgpt' => 'chatgpt.com', 'perplexity' => 'perplexity.ai', 'gemini' => 'gemini.google.com', 'claude' => 'claude.ai', 'copilot' => 'copilot.microsoft.com' );
$bots   = array( 'gptbot' => 21, 'oai-searchbot' => 12, 'chatgpt-user' => 6, 'claudebot' => 14, 'claude-searchbot' => 4, 'claude-user' => 1.5, 'perplexitybot' => 8, 'perplexity-user' => 2, 'applebot' => 10, 'amazonbot' => 6, 'meta-externalagent' => 9, 'bytespider' => 5, 'ccbot' => 2.5, 'duckassistbot' => 1.2, 'google-cloudvertexbot' => 1 );
// Search, SEO and social crawlers per day (1.2 "Bots & automated visits" card).
$crawl = array( 'googlebot' => 70, 'bingbot' => 35, 'ahrefsbot' => 22, 'semrushbot' => 16, 'yandexbot' => 6, 'mj12bot' => 5, 'petalbot' => 4, 'duckduckbot' => 3, 'dotbot' => 2, 'facebookexternalhit' => 5, 'twitterbot' => 1.5, 'pinterestbot' => 1.5, 'linkedinbot' => 1 );
function nw_split( $total, $weights ) {
	$sum = array_sum( $weights );
	$out = array();
	foreach ( $weights as $k => $w ) {
		$n = (int) round( $total * $w / $sum * ( 0.75 + mt_rand( 0, 50 ) / 100 ) );
		if ( $n > 0 ) {
			$out[ $k ] = $n;
		}
	}
	return $out;
}
$dim = function ( $day, $kind, $item, $count ) use ( $wpdb, $dims ) {
	if ( $count > 0 ) {
		$wpdb->query( $wpdb->prepare( "INSERT INTO $dims (day, kind, item, count) VALUES (%s, %s, %s, %d) ON DUPLICATE KEY UPDATE count = count + VALUES(count)", $day, $kind, $item, $count ) );
	}
};
for ( $i = 179; $i >= 0; $i-- ) {
	$d      = $today->modify( "-$i days" );
	$day    = $d->format( 'Y-m-d' );
	$dow    = (int) $d->format( 'N' );
	$growth = 0.78 + ( 179 - $i ) / 179 * 0.62;
	$growth *= ( $i <= 6 ? 1.12 : 1 );
	$base   = 150 * $growth * ( $dow >= 6 ? 0.66 : ( 2 === $dow || 3 === $dow ? 1.14 : 1.0 ) );
	$frac   = 0 === $i ? 0.55 : 1; // Today so far.
	$v      = (int) round( $base * ( 0.88 + mt_rand( 0, 24 ) / 100 ) * $frac );
	$views  = (int) round( $v * ( 1.75 + mt_rand( 0, 40 ) / 100 ) );
	$sess   = (int) round( $v * ( 1.08 + mt_rand( 0, 10 ) / 100 ) );
	if ( $i > 0 ) {
		// Automated visits: page loads with no sign of a person, 15-40% of the human page views.
		$wpdb->insert( $daily, array( 'day' => $day, 'visitors' => $v, 'views' => $views, 'sessions' => $sess, 'automated' => (int) round( $views * ( 0.15 + mt_rand( 0, 25 ) / 100 ) ) ) );
	}
	// AI bots and AI visitors grow faster than people; search crawlers stay steady.
	$aig = 0.45 + ( 179 - $i ) / 179 * 0.9;
	foreach ( $bots as $b => $rate ) {
		$dim( $day, 'aibot', $b, (int) round( $rate * $aig * $frac * ( 0.6 + mt_rand( 0, 80 ) / 100 ) ) );
	}
	foreach ( $crawl as $b => $rate ) {
		$dim( $day, 'crawler', $b, (int) round( $rate * ( 0.85 + $growth * 0.15 ) * $frac * ( 0.6 + mt_rand( 0, 80 ) / 100 ) ) );
	}
	if ( 0 === $i ) {
		continue; // Today's people dims come from today's hits below.
	}
	$ai_total = (int) round( $sess * 0.035 * $aig * ( 0.5 + mt_rand( 0, 100 ) / 100 ) );
	$ai_split = $ai_total ? nw_split( $ai_total, $ais ) : array();
	foreach ( $ai_split as $k => $n ) {
		$dim( $day, 'ai', $k, $n );
	}
	if ( $i > 89 ) {
		continue; // Pages, referrers and devices are kept 90 days.
	}
	foreach ( nw_split( $views, $pages ) as $k => $n ) {
		$dim( $day, 'page', $p . $k, $n );
	}
	foreach ( nw_split( (int) round( $sess * 0.42 ), $refs ) as $k => $n ) {
		$dim( $day, 'referrer', $k, $n );
	}
	foreach ( $ai_split as $k => $n ) {
		$dim( $day, 'referrer', $aihost[ $k ], $n );
	}
	foreach ( nw_split( $sess, array( 'mobile' => 57, 'desktop' => 39, 'tablet' => 4 ) ) as $k => $n ) {
		$dim( $day, 'device', $k, $n );
	}
}

// Raw hits for yesterday and today: people (human = 1, 12 online in the last 5 minutes) and
// automated page loads (human = 0). The rollup turns them into yesterday's and today's totals.
$wpdb->query( "DELETE FROM $hits" );
$now   = time();
$curve = array( 1, .6, .4, .3, .3, .5, 1.2, 2.4, 3.6, 4.4, 4.6, 4.5, 4.3, 4.0, 3.8, 3.7, 3.6, 3.5, 3.6, 3.9, 3.7, 3.0, 2.2, 1.5 ); // Per local hour.
$pick  = function ( $start, $end ) use ( $curve, $tz ) {
	for ( $try = 0; $try < 50; $try++ ) {
		$t = mt_rand( $start, $end );
		$h = (int) ( new DateTimeImmutable( '@' . $t ) )->setTimezone( $tz )->format( 'G' );
		if ( mt_rand( 0, 460 ) / 100 <= $curve[ $h ] ) {
			return $t;
		}
	}
	return $t;
};
$hit = function ( $visitor, $at, $new, $human = 1 ) use ( $wpdb, $hits ) {
	$wpdb->insert( $hits, array( 'visitor' => hash( 'sha256', $visitor ), 'token' => substr( md5( $visitor . $at . mt_rand() ), 0, 16 ), 'human' => $human, 'new_session' => $new, 'created' => gmdate( 'Y-m-d H:i:s', $at ) ) );
};
$person = function ( $id, $t ) use ( $hit, $now ) {
	$r = mt_rand( 0, 100 );
	$n = $r < 45 ? 1 : ( $r < 75 ? 2 : ( $r < 92 ? 3 : 4 ) );
	for ( $i = 0; $i < $n; $i++ ) {
		$hit( $id, min( $now - 5, $t + $i * mt_rand( 30, 150 ) ), 0 === $i ? 1 : 0 );
	}
	return $n;
};
$ys = $today->modify( '-1 day' )->getTimestamp();
$ts = $today->getTimestamp();
$yv = 0;
$tv = 0;
$by = 0; // Yesterday's visitors up to this time of day.
for ( $n = 0; $n < 212; $n++ ) {
	$t   = $pick( $ys, $ys + DAY_IN_SECONDS - 600 );
	$yv += $person( "y$n", $t );
	$by += $t < $now - DAY_IN_SECONDS ? 1 : 0;
}
// Today up to 30 minutes ago: about 12% more people than yesterday at this time (the last 30 minutes add ~45).
for ( $n = 0, $max = max( 20, (int) round( $by * 1.12 ) - 45 ); $n < $max; $n++ ) {
	$tv += $person( "t$n", $pick( $ts, $now - 31 * MINUTE_IN_SECONDS ) );
}
// Last 30 minutes: 1-3 new visitors a minute, 12 in the last 5 minutes.
$recent = array( 3, 2, 3, 2, 2 );
for ( $ago = 29; $ago >= 0; $ago-- ) {
	$k = $ago < 5 ? $recent[ $ago ] : ( mt_rand( 0, 100 ) < 70 ? 1 : 2 );
	for ( $j = 0; $j < $k; $j++ ) {
		$t = $now - $ago * 60 - mt_rand( 5, 55 );
		$hit( "m$ago-$j", $t, 1 );
		++$tv;
		if ( $ago >= 2 && mt_rand( 0, 1 ) ) {
			$hit( "m$ago-$j", min( $now - 5, $t + 50 ), 0 );
			++$tv;
		}
	}
}
foreach ( array( array( 'ya', $ys, $ys + DAY_IN_SECONDS - 60, $yv ), array( 'ta', $ts, $now - 60, $tv ) ) as $a ) {
	for ( $n = 0, $max = (int) round( $a[3] * 0.24 ); $n < $max; $n++ ) {
		$hit( $a[0] . $n, mt_rand( $a[1], $a[2] ), 0, 0 );
	}
}

// Today's page, referrer, device and AI assistant totals from today's numbers.
$live  = swiftdash_live_day( $today->format( 'Y-m-d' ) );
$td    = $today->format( 'Y-m-d' );
$share = function ( $total, $weights ) {
	$sum = array_sum( $weights );
	$out = array();
	foreach ( $weights as $k => $w ) {
		$out[ $k ] = (int) round( $total * $w / $sum );
	}
	return $out;
};
foreach ( $share( $live['views'], array( '/' => 21, '/shop/' => 16, '/blog/cold-brew-at-home/' => 13, '/blog/how-to-brew-pour-over-at-home/' => 10, '/menu-pricing/' => 9, '/blog/choosing-the-right-grind-size/' => 7, '/subscriptions/' => 5, '/wholesale/' => 4, '/about-us/' => 3 ) ) as $k => $n ) {
	$dim( $td, 'page', $p . $k, $n );
}
foreach ( $share( (int) round( $live['sessions'] * 0.44 ), array( 'google.com' => 60, 'instagram.com' => 13, 'facebook.com' => 6, 'bing.com' => 5, 'reddit.com' => 4 ) ) as $k => $n ) {
	$dim( $td, 'referrer', $k, $n );
}
foreach ( array( 'chatgpt' => 4, 'perplexity' => 2, 'gemini' => 1 ) as $k => $n ) {
	$dim( $td, 'ai', $k, $n );
	$dim( $td, 'referrer', $aihost[ $k ], $n );
}
foreach ( $share( $live['sessions'], array( 'mobile' => 58, 'desktop' => 38, 'tablet' => 4 ) ) as $k => $n ) {
	$dim( $td, 'device', $k, $n );
}
swiftdash_rollup();

// Recent activity.
$wpdb->query( "DELETE FROM {$wpdb->prefix}swiftdash_activity" );
$rows = array( array( 'publish', 'Post published', 'Cold Brew at Home: A Simple Guide', $cold, 35 ), array( 'plugin', 'Plugin updated', 'SWD Dashboard 1.3.0', 0, 130 ), array( 'user', 'New user registered', 'Maya Chen', 0, 260 ), array( 'publish', 'Page published', 'Subscriptions', 0, 420 ), array( 'theme', 'Theme updated', 'Twenty Twenty-Five 1.3', 0, 700 ), array( 'core', 'WordPress updated', 'WordPress ' . get_bloginfo( 'version' ), 0, 1500 ), array( 'publish', 'Post published', 'Ethiopia Yirgacheffe: Our Latest Roast', 35, 2300 ) );
foreach ( array_reverse( $rows ) as $r ) {
	$wpdb->insert( "{$wpdb->prefix}swiftdash_activity", array( 'created' => gmdate( 'Y-m-d H:i:s', $now - $r[4] * 60 ), 'type' => $r[0], 'object_id' => $r[3], 'title' => $r[1], 'detail' => $r[2], 'user_id' => 1 ) );
}

// Speed results: this site, tested 20 minutes ago.
foreach ( array( 'mobile', 'desktop' ) as $dev ) {
	$o = get_option( "swiftdash_speed_$dev" );
	if ( is_array( $o ) ) {
		$o['url']       = home_url( '/' );
		$o['tested_at'] = $now - 1200;
		// 1.3.0 tabs: accessibility, best practices and SEO items from the sample report; performance stays the site's own list.
		$nw_lr                  = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/tools/fixtures/psi-sample-mobile.json' ), true )['lighthouseResult'];
		$o['fixes_by_category'] = array(
			'performance'    => isset( $o['fixes'] ) ? $o['fixes'] : array(),
			'accessibility'  => array_slice( swiftdash_speed_parse_checks( $nw_lr, 'accessibility' ), -2 ),
			'best_practices' => array_slice( swiftdash_speed_parse_checks( $nw_lr, 'best-practices' ), -1 ),
			'seo'            => array_slice( swiftdash_speed_parse_checks( $nw_lr, 'seo' ), 0, 1 ),
		);
		// Scores that match the listed items: 100 minus what the items cost.
		foreach ( array( 'accessibility', 'best_practices', 'seo' ) as $nw_cat ) {
			$o['scores'][ $nw_cat ] = 100 - array_sum( array_map( 'intval', array_column( $o['fixes_by_category'][ $nw_cat ], 'points' ) ) );
		}
		update_option( "swiftdash_speed_$dev", $o );
	}
}

// AI access check: robots.txt read, every AI company allowed, llms.txt present. Healthy site.
$blocked = array();
foreach ( swiftdash_ai_bots() as $bot ) {
	$blocked[ strtolower( $bot['token'] ) ] = false;
}
set_transient( 'swiftdash_ai_access', array( 'checked' => $now - 1500, 'status' => 'ok', 'code' => 200, 'physical' => false, 'llms' => true, 'blocked' => $blocked, 'all' => false ), 12 * HOUR_IN_SECONDS );
set_transient( 'health-check-site-status-result', wp_json_encode( array( 'good' => 21, 'recommended' => 3, 'critical' => 0 ) ) );
delete_transient( 'swiftdash_live_count' );
delete_transient( 'swiftdash_live_full' );
// Everything up to date (an inactive dev plugin may have an update waiting).
$u = get_site_transient( 'update_plugins' );
if ( is_object( $u ) ) {
	$u->response = array();
	set_site_transient( 'update_plugins', $u );
}

// Prefs: all cards, promo + review snoozed, PRO tips hidden, welcomed, a few recent items.
$a = admin_url();
update_user_meta(
	1,
	'swiftdash_prefs',
	array(
		'hidden'          => array(),
		'upgrade_snooze'  => $now + 30 * DAY_IN_SECONDS,
		'review_snooze'   => $now + 30 * DAY_IN_SECONDS,
		'welcomed'        => true,
		'pro_tips_hidden' => true,
		'recent'          => array(
			array( 'label' => 'Cold Brew at Home: A Simple Guide', 'url' => $a . "post.php?post=$cold&action=edit", 'hint' => 'Post', 'icon' => 'note-fill', 'tone' => 'neutral' ),
			array( 'label' => 'Menu & Pricing', 'url' => $a . 'post.php?post=30&action=edit', 'hint' => 'Page', 'icon' => 'file-text-fill', 'tone' => 'neutral' ),
			array( 'label' => 'Maya Chen', 'url' => $a . 'user-edit.php?user_id=' . get_user_by( 'login', 'maya' )->ID, 'hint' => 'maya@northwindcoffee.com', 'icon' => 'user-circle-fill', 'tone' => 'neutral' ),
		),
	)
);
delete_user_meta( 1, 'swiftdash_layout' );
delete_user_meta( 1, 'swiftdash_links' );
delete_option( 'swiftdash_first_run' );
wp_cache_flush();
if ( $list ) {
	file_put_contents( $list, implode( "\n", $added ) );
}
$f = swiftdash_live_full();
delete_transient( 'swiftdash_live_count' );
delete_transient( 'swiftdash_live_full' );
echo wp_json_encode( array( 'cold' => $cold, 'online' => $f['online'], 'today' => $f['today'], 'ai' => $f['ai'], 'pages' => wp_list_pluck( $f['pages'], 'count', 'title' ), 'sources' => wp_list_pluck( $f['sources'], 'count', 'label' ) ) ), "\n";
