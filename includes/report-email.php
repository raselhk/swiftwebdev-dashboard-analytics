<?php
/**
 * Weekly Site Report email (opt-in): last 7 full days vs the 7 before, top lists, speed,
 * updates and Site Health. Sent with wp_mail every Monday 08:00 site time.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keep the weekly cron event in step with the setting.
 */
function swiftdash_report_schedule() {
	$next = wp_next_scheduled( 'swiftdash_weekly_report' );
	if ( swiftdash_setting( 'weekly_report' ) && ! $next ) {
		$monday = new DateTimeImmutable( 'next monday 08:00', wp_timezone() );
		wp_schedule_event( $monday->getTimestamp(), 'weekly', 'swiftdash_weekly_report' );
	} elseif ( ! swiftdash_setting( 'weekly_report' ) && $next ) {
		wp_clear_scheduled_hook( 'swiftdash_weekly_report' );
	}
}
add_action( 'add_option_swiftdash_settings', 'swiftdash_report_schedule' );
add_action( 'update_option_swiftdash_settings', 'swiftdash_report_schedule' );
add_action( 'swiftdash_installed', 'swiftdash_report_schedule' );

/**
 * Cron callback: send the report and remember when.
 */
function swiftdash_weekly_report_run() {
	if ( ! swiftdash_setting( 'weekly_report' ) ) {
		wp_clear_scheduled_hook( 'swiftdash_weekly_report' );
		return;
	}
	// Cron runs without a user; update counts are only read for users who may update.
	if ( ! get_current_user_id() ) {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		if ( $admins ) {
			wp_set_current_user( (int) $admins[0] );
		}
	}
	if ( swiftdash_send_report() ) {
		update_option( 'swiftdash_report_last', time(), false );
	}
}
add_action( 'swiftdash_weekly_report', 'swiftdash_weekly_report_run' );

/**
 * Where the report goes: the saved address, else the site admin email.
 *
 * @return string
 */
function swiftdash_report_recipient() {
	$email = swiftdash_setting( 'report_email' );
	return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
}

/**
 * Send the report.
 *
 * @param string $to Recipient; empty uses swiftdash_report_recipient().
 * @return bool Whether wp_mail accepted it.
 */
function swiftdash_send_report( $to = '' ) {
	$to   = '' !== $to ? $to : swiftdash_report_recipient();
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	/* translators: %s: site name. */
	$subject = sprintf( __( 'Your weekly site report: %s', 'swiftwebdev-dashboard-analytics' ), $site );
	return wp_mail( $to, $subject, swiftdash_report_html( swiftdash_report_data() ), array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/**
 * Numbers for the report.
 *
 * @return array
 */
function swiftdash_report_data() {
	// 15 days oldest first, today last: days 7-13 are the last 7 full days, 0-6 the week before.
	$daily = swiftdash_get_stats( 15 )['daily'];
	$now   = array(
		'visitors' => 0,
		'views'    => 0,
		'sessions' => 0,
	);
	$prev  = $now;
	foreach ( array_slice( $daily, 0, 14 ) as $i => $day ) {
		foreach ( $now as $key => $unused ) {
			if ( $i >= 7 ) {
				$now[ $key ] += $day[ $key ];
			} else {
				$prev[ $key ] += $day[ $key ];
			}
		}
	}

	// The last 7 full days, matching the totals above.
	$top = array();
	foreach ( array( 'page', 'referrer', 'ai' ) as $kind ) {
		$top[ $kind ] = function_exists( 'swiftdash_get_top' ) ? (array) swiftdash_get_top( $kind, 7, 5, true ) : array();
	}
	$ai = array();
	foreach ( array( 'ai', 'aibot' ) as $kind ) {
		$period      = swiftdash_dims_period( $kind, 7, true );
		$ai[ $kind ] = array( $period['total'], $period['previous'] );
	}
	$sources = function_exists( 'swiftdash_ai_sources' ) ? (array) swiftdash_ai_sources() : array();
	foreach ( $top['ai'] as $i => $row ) {
		$label = isset( $sources[ $row['item'] ] ) ? $sources[ $row['item'] ] : $row['item'];
		if ( is_array( $label ) ) {
			$label = isset( $label['label'] ) ? $label['label'] : $row['item'];
		}
		$top['ai'][ $i ]['item'] = (string) $label;
	}

	$speed = array();
	foreach ( swiftdash_speed_strategies() as $strategy ) {
		$result             = swiftdash_speed_get( $strategy );
		$speed[ $strategy ] = $result && isset( $result['scores']['performance'] ) ? (int) $result['scores']['performance'] : null;
	}

	if ( ! function_exists( 'get_core_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}
	$health = json_decode( (string) get_transient( 'health-check-site-status-result' ), true );

	return array(
		'from'    => isset( $daily[7] ) ? $daily[7]['date'] : '',
		'to'      => isset( $daily[13] ) ? $daily[13]['date'] : '',
		'now'     => $now,
		'prev'    => $prev,
		'top'     => $top,
		'ai'      => $ai,
		'speed'   => $speed,
		'updates' => (int) wp_get_update_data()['counts']['total'],
		'health'  => is_array( $health ) && isset( $health['good'], $health['recommended'], $health['critical'] ) ? $health : null,
	);
}

/**
 * Week-over-week change as text and color.
 *
 * @param int $now  This week.
 * @param int $prev Week before.
 * @return array { text, color }
 */
function swiftdash_report_change( $now, $prev ) {
	if ( ! $prev ) {
		return $now
			? array( __( 'New this week', 'swiftwebdev-dashboard-analytics' ), '#059669' )
			: array( __( 'No change', 'swiftwebdev-dashboard-analytics' ), '#64748b' );
	}
	$pct = (int) round( ( $now - $prev ) / $prev * 100 );
	if ( 0 === $pct ) {
		return array( __( 'No change', 'swiftwebdev-dashboard-analytics' ), '#64748b' );
	}
	/* translators: %s: percentage change with sign, e.g. +12% or -5%. */
	$text = sprintf( __( '%s vs last week', 'swiftwebdev-dashboard-analytics' ), ( $pct > 0 ? '+' : '&minus;' ) . number_format_i18n( abs( $pct ) ) . '%' );
	return array( $text, $pct > 0 ? '#059669' : '#dc2626' );
}

/**
 * Email body: table layout, inline styles, brand colors from tokens.css.
 *
 * @param array $data swiftdash_report_data().
 * @return string
 */
function swiftdash_report_html( $data ) {
	$font  = "font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Roboto,Arial,sans-serif;";
	$h2    = $font . 'margin:0 0 12px;font-size:16px;font-weight:700;color:#111827;';
	$muted = $font . 'font-size:13px;color:#64748b;';
	$cell  = $font . 'padding:10px 0;border-top:1px solid #e5eaf2;font-size:14px;color:#111827;';
	$fmt   = get_option( 'date_format' );
	$day   = function ( $date ) use ( $fmt ) {
		return wp_date( $fmt, ( new DateTimeImmutable( $date . ' 12:00:00', wp_timezone() ) )->getTimestamp() );
	};
	$range = $data['from'] ? $day( $data['from'] ) . ' &ndash; ' . $day( $data['to'] ) : '';
	$site  = get_bloginfo( 'name' );

	$kpis  = array(
		'visitors' => __( 'Visitors', 'swiftwebdev-dashboard-analytics' ),
		'views'    => __( 'Page views', 'swiftwebdev-dashboard-analytics' ),
		'sessions' => __( 'Sessions', 'swiftwebdev-dashboard-analytics' ),
	);
	$lists = array(
		'page'     => array( __( 'Top pages', 'swiftwebdev-dashboard-analytics' ), __( 'No page views this week.', 'swiftwebdev-dashboard-analytics' ) ),
		'referrer' => array( __( 'Top referrers', 'swiftwebdev-dashboard-analytics' ), __( 'No visits from other sites this week.', 'swiftwebdev-dashboard-analytics' ) ),
		'ai'       => array( __( 'AI assistant visits', 'swiftwebdev-dashboard-analytics' ), __( 'No visits from ChatGPT, Perplexity or other AI assistants this week.', 'swiftwebdev-dashboard-analytics' ) ),
	);

	ob_start();
	?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html( $site ); ?></title></head>
<body style="margin:0;padding:0;background:#f5f7fb;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e5eaf2;border-radius:16px;overflow:hidden;">
	<tr><td style="background:#172437;padding:24px 28px;">
		<p style="<?php echo esc_attr( $font ); ?>margin:0;font-size:12px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#a5b4fc;"><?php esc_html_e( 'Weekly site report', 'swiftwebdev-dashboard-analytics' ); ?></p>
		<p style="<?php echo esc_attr( $font ); ?>margin:6px 0 0;font-size:22px;font-weight:800;color:#ffffff;"><?php echo esc_html( $site ); ?></p>
		<?php if ( $range ) : ?>
			<p style="<?php echo esc_attr( $font ); ?>margin:4px 0 0;font-size:13px;color:#cbd5e1;"><?php echo wp_kses( $range, array() ); ?></p>
		<?php endif; ?>
	</td></tr>

	<tr><td style="padding:24px 28px 8px;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
			<?php foreach ( $kpis as $key => $label ) : ?>
				<?php list( $change, $color ) = swiftdash_report_change( $data['now'][ $key ], $data['prev'][ $key ] ); ?>
				<td width="33%" valign="top" style="padding:0 4px;">
					<div style="padding:14px;border:1px solid #e5eaf2;border-radius:12px;background:#f5f7fb;">
						<p style="<?php echo esc_attr( $muted ); ?>margin:0;"><?php echo esc_html( $label ); ?></p>
						<p style="<?php echo esc_attr( $font ); ?>margin:4px 0;font-size:26px;font-weight:800;color:#111827;"><?php echo esc_html( number_format_i18n( $data['now'][ $key ] ) ); ?></p>
						<p style="<?php echo esc_attr( $font ); ?>margin:0;font-size:12px;font-weight:600;color:<?php echo esc_attr( $color ); ?>;"><?php echo wp_kses( $change, array() ); ?></p>
					</div>
				</td>
			<?php endforeach; ?>
		</tr></table>
	</td></tr>

	<tr><td style="padding:12px 28px 0;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
			<?php
			foreach ( array(
				'ai'    => __( 'AI visitors', 'swiftwebdev-dashboard-analytics' ),
				'aibot' => __( 'AI bot visits', 'swiftwebdev-dashboard-analytics' ),
			) as $key => $label ) :
				list( $change, $color ) = swiftdash_report_change( $data['ai'][ $key ][0], $data['ai'][ $key ][1] );
				?>
				<td width="50%" valign="top" style="padding:0 4px;">
					<div style="padding:14px;border:1px solid #e5eaf2;border-radius:12px;background:#f5f7fb;">
						<p style="<?php echo esc_attr( $muted ); ?>margin:0;"><?php echo esc_html( $label ); ?></p>
						<p style="<?php echo esc_attr( $font ); ?>margin:4px 0;font-size:22px;font-weight:800;color:#111827;"><?php echo esc_html( number_format_i18n( $data['ai'][ $key ][0] ) ); ?></p>
						<p style="<?php echo esc_attr( $font ); ?>margin:0;font-size:12px;font-weight:600;color:<?php echo esc_attr( $color ); ?>;"><?php echo wp_kses( $change, array() ); ?></p>
					</div>
				</td>
			<?php endforeach; ?>
		</tr></table>
	</td></tr>

	<?php foreach ( $lists as $kind => $list ) : ?>
		<tr><td style="padding:20px 28px 0;">
			<h2 style="<?php echo esc_attr( $h2 ); ?>"><?php echo esc_html( $list[0] ); ?></h2>
			<?php if ( $data['top'][ $kind ] ) : ?>
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
					<?php foreach ( $data['top'][ $kind ] as $row ) : ?>
						<tr>
							<td style="<?php echo esc_attr( $cell ); ?>word-break:break-all;"><?php echo esc_html( $row['item'] ); ?></td>
							<td align="right" style="<?php echo esc_attr( $cell ); ?>padding-left:12px;font-weight:700;white-space:nowrap;"><?php echo esc_html( number_format_i18n( (int) $row['count'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php else : ?>
				<p style="<?php echo esc_attr( $muted ); ?>margin:0;"><?php echo esc_html( $list[1] ); ?></p>
			<?php endif; ?>
		</td></tr>
	<?php endforeach; ?>

	<tr><td style="padding:20px 28px 0;">
		<h2 style="<?php echo esc_attr( $h2 ); ?>"><?php esc_html_e( 'Site status', 'swiftwebdev-dashboard-analytics' ); ?></h2>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
			<?php
			$rows = array();
			foreach ( array(
				'mobile'  => __( 'Mobile speed score', 'swiftwebdev-dashboard-analytics' ),
				'desktop' => __( 'Desktop speed score', 'swiftwebdev-dashboard-analytics' ),
			) as $strategy => $label ) {
				$score  = $data['speed'][ $strategy ];
				$rows[] = null === $score
					? array( $label, __( 'Not tested yet', 'swiftwebdev-dashboard-analytics' ), '#64748b' )
					: array( $label, $score . ' / 100', $score >= 90 ? '#059669' : ( $score >= 50 ? '#d97706' : '#dc2626' ) );
			}
			$rows[] = $data['updates']
				/* translators: %d: number of pending updates. */
				? array( __( 'Updates', 'swiftwebdev-dashboard-analytics' ), sprintf( _n( '%d waiting', '%d waiting', $data['updates'], 'swiftwebdev-dashboard-analytics' ), $data['updates'] ), '#d97706' )
				: array( __( 'Updates', 'swiftwebdev-dashboard-analytics' ), __( 'All up to date', 'swiftwebdev-dashboard-analytics' ), '#059669' );
			$health = $data['health'];
			if ( ! $health ) {
				$rows[] = array( __( 'Site Health', 'swiftwebdev-dashboard-analytics' ), __( 'Not checked yet', 'swiftwebdev-dashboard-analytics' ), '#64748b' );
			} elseif ( (int) $health['critical'] ) {
				/* translators: %d: number of critical Site Health issues. */
				$rows[] = array( __( 'Site Health', 'swiftwebdev-dashboard-analytics' ), sprintf( _n( '%d critical issue', '%d critical issues', (int) $health['critical'], 'swiftwebdev-dashboard-analytics' ), (int) $health['critical'] ), '#dc2626' );
			} elseif ( (int) $health['recommended'] ) {
				/* translators: %d: number of recommended Site Health improvements. */
				$rows[] = array( __( 'Site Health', 'swiftwebdev-dashboard-analytics' ), sprintf( _n( '%d recommended improvement', '%d recommended improvements', (int) $health['recommended'], 'swiftwebdev-dashboard-analytics' ), (int) $health['recommended'] ), '#d97706' );
			} else {
				$rows[] = array( __( 'Site Health', 'swiftwebdev-dashboard-analytics' ), __( 'Good', 'swiftwebdev-dashboard-analytics' ), '#059669' );
			}
			foreach ( $rows as $row ) :
				?>
				<tr>
					<td style="<?php echo esc_attr( $cell ); ?>"><?php echo esc_html( $row[0] ); ?></td>
					<td align="right" style="<?php echo esc_attr( $cell ); ?>font-weight:700;color:<?php echo esc_attr( $row[2] ); ?>;"><?php echo esc_html( $row[1] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</td></tr>

	<tr><td align="center" style="padding:28px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=swift-dash' ) ); ?>" style="<?php echo esc_attr( $font ); ?>display:inline-block;padding:12px 24px;border-radius:8px;background:#4f5bff;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;"><?php esc_html_e( 'Open your dashboard', 'swiftwebdev-dashboard-analytics' ); ?></a>
	</td></tr>
</table>
<p style="<?php echo esc_attr( $muted ); ?>max-width:600px;margin:16px auto 0;font-size:12px;line-height:1.6;text-align:center;">
	<?php
	printf(
		/* translators: %s: link to the SwiftWebDev Dashboard settings screen. */
		esc_html__( 'You get this email because the weekly site report is on for this site. To stop it, turn off "Email a weekly site report" in %s.', 'swiftwebdev-dashboard-analytics' ),
		'<a href="' . esc_url( admin_url( 'admin.php?page=swift-dash-settings' ) ) . '" style="color:#4f5bff;">' . esc_html__( 'SwiftWebDev Dashboard settings', 'swiftwebdev-dashboard-analytics' ) . '</a>'
	);
	?>
</p>
</td></tr></table>
</body>
</html>
	<?php
	return (string) ob_get_clean();
}

/**
 * REST: POST swiftdash/v1/report/test sends the report now to the given or saved address.
 */
function swiftdash_report_routes() {
	register_rest_route(
		'swiftdash/v1',
		'/report/test',
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( swiftdash_capability() );
			},
			'args'                => array(
				'email' => array(
					'type'      => 'string',
					'default'   => '',
					'maxLength' => 100,
				),
			),
			'callback'            => function ( $request ) {
				$email = trim( (string) $request['email'] );
				if ( '' !== $email && ! is_email( $email ) ) {
					return new WP_Error( 'swiftdash_bad_email', __( 'Enter a valid email address.', 'swiftwebdev-dashboard-analytics' ), array( 'status' => 400 ) );
				}
				$to = '' !== $email ? $email : swiftdash_report_recipient();
				if ( ! swiftdash_send_report( $to ) ) {
					return new WP_Error( 'swiftdash_mail_failed', __( 'WordPress could not send the email. Check that this site can send email, for example with an SMTP plugin.', 'swiftwebdev-dashboard-analytics' ), array( 'status' => 500 ) );
				}
				return array( 'to' => $to );
			},
		)
	);
}
add_action( 'rest_api_init', 'swiftdash_report_routes' );
