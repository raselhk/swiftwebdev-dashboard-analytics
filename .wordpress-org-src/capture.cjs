// Raw UI shots for the wp.org screenshots, in readme.txt caption order: shots/v12-*-2x.png
// (1440 css px wide at deviceScaleFactor 2), from the dev site localhost/swift-email-wp with the
// "Northwind" demo data. Free plugin only. Then `node render.cjs screenshots`.
//
//   1. Back up the dev database:   D:/Xampp/mysql/bin/mysqldump -uroot swift_email_wp > backup.sql
//   2. Seed (from the repo root):  tools/wp eval-file swiftwebdev-dashboard-analytics/.wordpress-org-src/seed.php added.txt --user=1 --skip-plugins=swift-dash-pro,swd-owner-hub
//   3. Capture right after it:     node capture.cjs [name ...]   (the live panel shows the last 5 minutes)
//   4. Restore:                    D:/Xampp/mysql/bin/mysql -uroot swift_email_wp < backup.sql
//                                  and delete the upload files listed in added.txt.
const { chromium } = require('D:/Simple Swift Email/app/node_modules/.pnpm/playwright@1.63.0/node_modules/playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const SITE = 'http://localhost/swift-email-wp/';
const A = SITE + 'wp-admin/';
const O = path.join(__dirname, 'shots') + path.sep;
const V = 'v12';
const only = process.argv.slice(2);

// Hide third-party notices, keep the WP menu background full height, show the demo domain.
const scrub = () => {
	if (!document.getElementById('x-hide')) {
		const s = document.createElement('style');
		s.id = 'x-hide';
		s.textContent = '.notice,.rank-math-notice,[class*="notice"]:not([class*="sd-"]){display:none!important}#wpwrap{position:relative}#adminmenuback{position:absolute!important;top:0;bottom:0;height:auto!important}';
		document.head.appendChild(s);
	}
	const fix = (v) => v.replace(/http:\/\/localhost\/swift-email-wp/g, 'https://northwindcoffee.com').replace(/localhost\/swift-email-wp/g, 'northwindcoffee.com').replace(/^\/swift-email-wp\//, '/');
	const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
	let n;
	while ((n = w.nextNode())) if (/swift-email-wp/.test(n.nodeValue)) n.nodeValue = fix(n.nodeValue);
	document.querySelectorAll('input').forEach((i) => { if (/swift-email-wp/.test(i.value)) i.value = fix(i.value); });
};
// Show only these dashboard cards (the grid closes up under the page header).
const onlyCards = (ids) => {
	const s = document.createElement('style');
	s.textContent = 'section[data-sd-widget]{display:none!important}' + ids.map((id) => `section[data-sd-widget="${id}"]{display:flex!important}`).join('');
	document.head.appendChild(s);
};
// Full-page clip from the top to the bottom of an element, plus a margin.
const toBottom = async (p, sel, pad = 12) => {
	const r = await p.evaluate((s) => { const b = document.querySelector(s).getBoundingClientRect(); return b.bottom + scrollY; }, sel);
	return { x: 0, y: 0, width: 1440, height: Math.ceil(r + pad) };
};

(async () => {
	const br = await chromium.launch();
	const c = await br.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
	const p = await c.newPage();
	p.setDefaultTimeout(120000);
	p.setDefaultNavigationTimeout(120000);
	await p.goto(SITE + 'wp-login.php');
	await p.fill('#user_login', 'admin');
	await p.fill('#user_pass', 'admin');
	await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
	const go = async (url) => { await p.goto(url); await p.waitForTimeout(1500); await p.evaluate(scrub); };
	const shot = async (name, opts = {}) => { await p.evaluate(scrub); await p.screenshot({ path: `${O}${V}-${name}-2x.png`, ...opts }); console.log(name); };
	const want = (k) => !only.length || only.includes(k);

	// 9. Live visitors panel on a post (the toolbar items show on the site only). First: the 5-minute window moves.
	const POST = SITE + 'blog/cold-brew-at-home/';
	if (want('toolbar-live')) {
		await go(POST);
		await p.hover('#wp-admin-bar-swiftdash-live .sdtb-live');
		await p.waitForFunction(() => /Updated/.test(document.querySelector('#sdtb-live-panel:not([hidden])')?.innerText || '')); // Past the loading skeleton.
		await p.waitForTimeout(1500);
		console.log('online', await p.locator('.sdtb-live__n').textContent());
		await shot('toolbar-live');
		await p.mouse.move(1300, 800);
	}
	// 10. Toolbar search on the site.
	if (want('toolbar-search')) {
		await go(POST);
		await p.click('.sdtb-search__btn');
		await p.locator('#sdtb-search-input').pressSequentially('new', { delay: 120 });
		await p.waitForSelector('#sdtb-search-results img');
		await p.waitForTimeout(1200);
		await shot('toolbar-search');
	}
	// 1. Dashboard down to the Speed scores / Quick Links row.
	if (want('overview')) {
		await go(A + 'admin.php?page=swift-dash');
		await shot('overview', { fullPage: true, clip: await toBottom(p, '#sd-w-quick-links') });
	}
	// 2. Header search, typed. 3. Header search, focused (recent items and quick actions).
	if (want('search-typed')) {
		await go(A + 'admin.php?page=swift-dash');
		await p.click('#sd-search-input');
		await p.keyboard.type('new', { delay: 120 });
		await p.waitForFunction(() => /Newman/.test(document.querySelector('#sd-search-results')?.innerText || ''));
		await p.waitForSelector('#sd-search-results img');
		// Arrow down to the last result (Users), like a person would: the list scrolls it into view.
		for (let i = 0; i < 10 && !/Newman/.test(await p.locator('#sd-search-results [aria-selected="true"]').innerText().catch(() => '')); i++) await p.keyboard.press('ArrowDown');
		await p.waitForTimeout(800);
		await shot('search-typed');
	}
	if (want('search-focus')) {
		await go(A + 'admin.php?page=swift-dash');
		await p.click('#sd-search-input');
		await p.waitForTimeout(1000);
		await shot('search-focus');
	}
	// 4. AI row: AI crawlers (with People from AI assistants), Bots & automated visits, AI access check.
	if (want('ai')) {
		await go(A + 'admin.php?page=swift-dash');
		await p.evaluate(onlyCards, ['ai-crawlers', 'bots', 'ai-access']);
		await shot('ai', { fullPage: true, clip: await toBottom(p, '#sd-w-ai-access') });
	}
	// 5. Top pages, Referrers (AI tags), Devices.
	if (want('insights')) {
		await go(A + 'admin.php?page=swift-dash');
		await p.evaluate(onlyCards, ['insight-pages', 'insight-referrers', 'insight-devices']);
		await shot('insights');
	}
	// 6. Speed & Optimize: scores, Core Web Vitals and the first fix.
	if (want('speed')) {
		await go(A + 'admin.php?page=swift-dash-speed');
		await p.waitForSelector('.sd-fix');
		await shot('speed', { fullPage: true, clip: await toBottom(p, '.sd-fix') });
	}
	// 7. Quick Links: pinned, Content and Design.
	if (want('links')) {
		await go(A + 'admin.php?page=swift-dash-links');
		await shot('links', { fullPage: true, clip: await toBottom(p, 'section[aria-labelledby="sd-ql-design-title"]') });
	}
	// 8. Weekly email, as sent (rendered by the plugin through WP-CLI), at its own width.
	if (want('email')) {
		const wp = path.join(__dirname, '..', '..', 'tools', 'wp');
		const html = execFileSync('sh', [wp, 'eval', 'echo swiftdash_report_html( swiftdash_report_data() );', '--user=1'], { encoding: 'utf8', maxBuffer: 1 << 24 });
		const e = await br.newPage({ viewport: { width: 740, height: 900 }, deviceScaleFactor: 2 });
		await e.setContent(html);
		await e.evaluate(() => document.fonts.ready);
		await e.evaluate(scrub);
		await e.screenshot({ path: `${O}${V}-email-2x.png`, fullPage: true });
		await e.close();
		console.log('email');
	}
	await br.close();
})().catch((e) => { console.error(e); process.exit(1); });
