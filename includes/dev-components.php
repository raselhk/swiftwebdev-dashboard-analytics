<?php
/**
 * Component preview screen. Loaded only when WP_DEBUG is on; excluded from release builds.
 * Values on this screen are labelled samples for checking the components, not site data.
 *
 * @package SwiftWebDevDashboard
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'swiftdash_screens',
	function ( $screens ) {
		$screens['swift-dash-ui'] = array(
			'key'         => 'ui',
			'menu'        => __( 'Components', 'swiftwebdev-dashboard-analytics' ),
			'title'       => __( 'Component preview', 'swiftwebdev-dashboard-analytics' ),
			'description' => __( 'Developer preview of every SwiftWebDev Dashboard component and state. Sample values only.', 'swiftwebdev-dashboard-analytics' ),
			'icon'        => 'dots-six-vertical',
			'nav'         => false,
		);
		return $screens;
	}
);

add_action( 'swiftdash_screen_ui', 'swiftdash_dev_components' );

/**
 * Print the preview.
 *
 * @param array $screen Screen definition.
 */
function swiftdash_dev_components( $screen ) {
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- swiftdash_icon() returns escaped markup; static demo strings.
	swiftdash_render_page_header(
		$screen['title'],
		$screen['description'],
		function () {
			?>
			<div class="sd-daterange">
				<label class="screen-reader-text" for="sd-demo-range">Date range</label>
				<select class="sd-select" id="sd-demo-range">
					<option>Last 24 hours</option>
					<option>Last 7 days</option>
					<option selected>Last 30 days</option>
					<option>Last 90 days</option>
					<option>Custom range</option>
				</select>
			</div>
			<button type="button" class="sd-btn sd-btn--secondary"><?php echo swiftdash_icon( 'download-simple' ); ?>Export report</button>
			<?php
		}
	);
	?>

	<section class="sd-card">
		<header class="sd-card__head"><h2 class="sd-card__title">Buttons</h2></header>
		<div class="sd-card__body sd-filters">
			<button type="button" class="sd-btn sd-btn--primary">Run test</button>
			<button type="button" class="sd-btn sd-btn--secondary">Export report</button>
			<button type="button" class="sd-btn sd-btn--tertiary">View details</button>
			<button type="button" class="sd-btn sd-btn--danger" data-sd-demo-confirm>Delete recording</button>
			<button type="button" class="sd-btn sd-btn--primary" disabled>Disabled</button>
			<button type="button" class="sd-btn sd-btn--primary is-busy">Saving</button>
			<button type="button" class="sd-btn sd-btn--primary sd-btn--sm">Small</button>
			<button type="button" class="sd-icon-btn" aria-label="Refresh"><?php echo swiftdash_icon( 'arrow-clockwise' ); ?></button>
		</div>
	</section>

	<div class="sd-grid">
		<section class="sd-card sd-span-3">
			<div class="sd-card__body sd-kpi">
				<span class="sd-kpi__icon"><?php echo swiftdash_icon( 'users' ); ?></span>
				<span class="sd-kpi__label">Visitors
					<button type="button" class="sd-tip" aria-label="About this metric"><?php echo swiftdash_icon( 'info' ); ?><span class="sd-tip__text" role="tooltip">Unique visitors per day, counted without cookies.</span></button>
				</span>
				<span class="sd-kpi__value">1,284</span>
				<span class="sd-kpi__meta"><span class="sd-trend sd-trend--good"><?php echo swiftdash_icon( 'arrow-up' ); ?>12%</span>vs previous 30 days</span>
			</div>
		</section>
		<section class="sd-card sd-span-3">
			<div class="sd-card__body sd-kpi">
				<span class="sd-kpi__icon sd-kpi__icon--purple"><?php echo swiftdash_icon( 'files' ); ?></span>
				<span class="sd-kpi__label">Page Views</span>
				<span class="sd-kpi__value">3,907</span>
				<span class="sd-kpi__meta"><span class="sd-trend sd-trend--bad"><?php echo swiftdash_icon( 'arrow-down' ); ?>4%</span>vs previous 30 days</span>
			</div>
		</section>
		<section class="sd-card sd-span-3">
			<div class="sd-card__body sd-kpi">
				<span class="sd-kpi__icon sd-kpi__icon--green"><?php echo swiftdash_icon( 'clock' ); ?></span>
				<span class="sd-kpi__label">Sessions</span>
				<span class="sd-kpi__value">-</span>
				<span class="sd-kpi__meta">No visitor data collected yet</span>
			</div>
		</section>
		<section class="sd-card sd-span-3" aria-busy="true">
			<div class="sd-card__body">
				<span class="sd-skeleton sd-skeleton--title"></span>
				<span class="sd-skeleton sd-skeleton--value"></span>
				<span class="sd-skeleton"></span>
			</div>
		</section>
	</div>

	<div class="sd-grid">
		<?php
		swiftdash_render_widget(
			array(
				'id'          => 'demo-widget',
				'title'       => 'Visitors by Country',
				'description' => 'Sample widget card.',
				'span'        => 4,
				'render'      => function () {
					echo '<p>Widget body</p>';
				},
				'actions'     => null,
				'kpi'         => null,
			)
		);
		?>
	</div>

	<div class="sd-grid">
		<section class="sd-card sd-span-8">
			<header class="sd-card__head">
				<h2 class="sd-card__title">Data table</h2>
				<div class="sd-card__actions"><span class="sd-status sd-status--success"><?php echo swiftdash_icon( 'check-circle' ); ?>Up to date</span></div>
			</header>
			<div class="sd-card__body">
				<div class="sd-table-wrap">
					<table class="sd-table" data-sd-table data-sd-page-size="4">
						<thead>
							<tr>
								<th scope="col" aria-sort="none"><button type="button" class="sd-sort">Page<?php echo swiftdash_icon( 'caret-down' ); ?></button></th>
								<th scope="col" class="is-num" aria-sort="descending"><button type="button" class="sd-sort">Views<?php echo swiftdash_icon( 'caret-down' ); ?></button></th>
								<th scope="col">Status</th>
							</tr>
						</thead>
						<tbody>
							<tr><td>/</td><td class="is-num" data-sort="5482">5,482</td><td><span class="sd-status sd-status--success">Good</span></td></tr>
							<tr><td>/blog/</td><td class="is-num" data-sort="3219">3,219</td><td><span class="sd-status sd-status--warning">Needs attention</span></td></tr>
							<tr><td>/contact/</td><td class="is-num" data-sort="1402">1,402</td><td><span class="sd-status sd-status--danger">Error</span></td></tr>
							<tr><td>/pricing/</td><td class="is-num" data-sort="912">912</td><td><span class="sd-status sd-status--info">New</span></td></tr>
							<tr><td>/about/</td><td class="is-num" data-sort="644">644</td><td><span class="sd-status sd-status--neutral">Unknown</span></td></tr>
						</tbody>
					</table>
				</div>
				<nav class="sd-pagination" aria-label="Table pages">
					<span data-sd-page-status aria-live="polite"></span>
					<span class="sd-pagination__buttons">
						<button type="button" class="sd-btn sd-btn--secondary sd-btn--sm" data-sd-page="prev">Previous</button>
						<button type="button" class="sd-btn sd-btn--secondary sd-btn--sm" data-sd-page="next">Next</button>
					</span>
				</nav>
			</div>
		</section>
		<section class="sd-card sd-span-4" data-sd-widget="demo-error">
			<header class="sd-card__head"><h2 class="sd-card__title">Error panel</h2></header>
			<div class="sd-card__body">
				<div class="sd-error" role="alert">
					<span class="sd-error__icon"><?php echo swiftdash_icon( 'warning-circle' ); ?></span>
					<p class="sd-error__title">Could not load this report</p>
					<p class="sd-error__text">The server did not respond in time.</p>
					<button type="button" class="sd-btn sd-btn--secondary sd-btn--sm" data-sd-retry>Try again</button>
					<p class="sd-error__ref">Reference: SD-TIMEOUT</p>
				</div>
			</div>
		</section>
	</div>

	<div class="sd-grid">
		<section class="sd-card sd-span-6">
			<header class="sd-card__head"><h2 class="sd-card__title">Empty state</h2></header>
			<div class="sd-card__body">
				<?php swiftdash_render_empty_state( 'chart-line-up', 'No visitor data collected yet', 'Stats appear after your site gets its first visit. Visits by logged-in admins are not counted.' ); ?>
			</div>
		</section>
		<section class="sd-card sd-span-6">
			<header class="sd-card__head"><h2 class="sd-card__title">Form controls</h2></header>
			<div class="sd-card__body" style="display:grid;gap:16px">
				<div class="sd-field">
					<label class="sd-field__label" for="sd-demo-input">Search filter</label>
					<input class="sd-input" id="sd-demo-input" type="search" placeholder="Filter pages">
				</div>
				<div class="sd-field">
					<label class="sd-field__label" for="sd-demo-invalid">API key</label>
					<input class="sd-input" id="sd-demo-invalid" aria-invalid="true" aria-describedby="sd-demo-invalid-err" value="abc">
					<p class="sd-field__error" id="sd-demo-invalid-err">This key is too short. Paste the full key from Google Cloud.</p>
				</div>
				<div class="sd-filters">
					<label class="screen-reader-text" for="sd-demo-device">Device</label>
					<select class="sd-select" id="sd-demo-device"><option>All devices</option><option>Desktop</option><option>Mobile</option></select>
					<div class="sd-dropdown">
						<button type="button" class="sd-btn sd-btn--secondary" data-sd-dropdown aria-expanded="false" aria-controls="sd-demo-menu">Actions<?php echo swiftdash_icon( 'caret-down' ); ?></button>
						<div class="sd-menu" id="sd-demo-menu" hidden>
							<button type="button" class="sd-menu__item"><?php echo swiftdash_icon( 'download-simple' ); ?>Export CSV</button>
							<button type="button" class="sd-menu__item"><?php echo swiftdash_icon( 'arrow-clockwise' ); ?>Refresh</button>
						</div>
					</div>
				</div>
				<div class="sd-filters">
					<button type="button" class="sd-btn sd-btn--secondary sd-btn--sm" data-sd-demo-toast="success">Success toast</button>
					<button type="button" class="sd-btn sd-btn--secondary sd-btn--sm" data-sd-demo-toast="error">Error toast</button>
					<button type="button" class="sd-btn sd-btn--secondary sd-btn--sm" data-sd-demo-toast="info">Info toast</button>
				</div>
			</div>
		</section>
	</div>

	<?php swiftdash_render_upgrade(); ?>

	<script>
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-sd-demo-toast]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { window.swiftdash.toast( 'Sample ' + b.dataset.sdDemoToast + ' message.', b.dataset.sdDemoToast ); } );
		} );
		document.querySelector( '[data-sd-demo-confirm]' ).addEventListener( 'click', function () {
			window.swiftdash.confirm( { title: 'Delete this recording?', message: 'The recording is removed permanently. This cannot be undone.', confirmLabel: 'Delete recording', danger: true } )
				.then( function ( ok ) { window.swiftdash.toast( ok ? 'Recording deleted (sample).' : 'Kept.', ok ? 'success' : 'info' ); } );
		} );
		document.querySelector( '[data-sd-widget="demo-error"]' ).addEventListener( 'swiftdash:retry', function () { window.swiftdash.toast( 'Retry requested.', 'info' ); } );
	} );
	</script>
	<?php
	// phpcs:enable
}
