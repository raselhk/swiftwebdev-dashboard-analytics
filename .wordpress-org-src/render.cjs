// Builds the wp.org listing assets and the site media from the HTML sources here and the raw
// UI shots in shots/ (made by youtube/_screens/_scripts/assets-cap.cjs).
//   node render.cjs
const { chromium } = require('D:/Simple Swift Email/app/node_modules/.pnpm/playwright@1.63.0/node_modules/playwright');
const sharp = require('D:/Simple Swift Email/app/node_modules/.pnpm/sharp@0.35.5_@types+node@22.20.3/node_modules/sharp');
const fs = require('fs');
const path = require('path');
const SRC = __dirname;
const ORG = path.join(SRC, '..', '.wordpress-org');
const MEDIA = 'D:/Swift WP Dashboard/build/site-media';
const shot = (f) => path.join(SRC, 'shots', f);
const url = (f, q = '') => 'file:///' + path.join(SRC, f).replace(/\\/g, '/') + q;

// PNG under 1 MB: lossless first, palette (still crisp for flat UI) when it is too big.
async function png(input, out, width, extract) {
	let img = sharp(input);
	if (extract) img = img.extract(extract);
	if (width) img = img.resize({ width, kernel: 'lanczos3' });
	const buf = await img.png({ compressionLevel: 9 }).toBuffer();
	const final = buf.length < 1000000 ? buf : await sharp(buf).png({ palette: true, quality: 95, colors: 256, dither: 0.5 }).toBuffer();
	fs.writeFileSync(out, final);
	return final.length;
}
async function webp(input, out, width, max, extract) {
	for (let q = 86; q >= 50; q -= 4) {
		let img = sharp(input);
		if (extract) img = img.extract(extract);
		if (width) img = img.resize({ width, kernel: 'lanczos3' });
		const buf = await img.webp({ quality: q, effort: 6 }).toBuffer();
		if (buf.length < max || q <= 50) { fs.writeFileSync(out, buf); return buf.length + ' q' + q; }
	}
}

(async () => {
	fs.mkdirSync(MEDIA, { recursive: true });
	const br = await chromium.launch();
	async function render(file, w, h, dsf, out, q) {
		const p = await br.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: dsf });
		await p.goto(url(file, q));
		await p.evaluate(() => document.fonts.ready);
		await p.waitForTimeout(300);
		const buf = await p.screenshot();
		await p.close();
		return out ? (fs.writeFileSync(out, buf), buf) : buf;
	}
	const log = (n, b) => console.log(n, b);

	// Banners and icons.
	for (const [w, h, dsf] of [[772, 250, 1], [772, 250, 2]]) {
		const buf = await render('banner.html', w, h, dsf);
		log('banner', await png(buf, path.join(ORG, `banner-${w * dsf}x${h * dsf}.png`)));
	}
	for (const dsf of [1, 2]) {
		const buf = await render('icon.html', 128, 128, dsf);
		log('icon', await png(buf, path.join(ORG, `icon-${128 * dsf}x${128 * dsf}.png`)));
	}

	// wp.org screenshots, in readme.txt caption order, downscaled from 2x for crisp text.
	const ss = ['dash-full@2x.png', 'visitors-90d-view@2x.png', 'speed-view@2x.png', 'links-view@2x.png'];
	for (let i = 0; i < 4; i++) log('screenshot-' + (i + 1), await png(shot(ss[i]), path.join(ORG, `screenshot-${i + 1}.png`), 1600));

	// Landing-page images: 1600 px wide WebP (<150 KB) plus PNG.
	const speedMeta = await sharp(shot('speed-view@2x.png')).metadata();
	// Card clips are padded 24 px; the row gap is 20 px, so trim 8 css px (16 at 2x) top and bottom.
	const trim = async (f) => { const m = await sharp(shot(f)).metadata(); return { left: 0, top: 16, width: m.width, height: m.height - 32 }; };
	const land = {
		'hero-dashboard': [shot('dash-view@2x.png')],
		'visitor-stats': [shot('visitors-90d-widgets@2x.png'), await trim('visitors-90d-widgets@2x.png')],
		'speed-scores': [shot('speed-view@2x.png'), { left: 0, top: 0, width: speedMeta.width, height: Math.min(speedMeta.height, 2440) }],
		'quick-links': [shot('links-view@2x.png')],
		'updates-health': [shot('updates-health@2x.png'), await trim('updates-health@2x.png')],
	};
	for (const [name, [file, ex]] of Object.entries(land)) {
		log(name + '.webp', await webp(file, path.join(MEDIA, name + '.webp'), 1600, 150000, ex));
		log(name + '.png', await png(file, path.join(MEDIA, name + '.png'), 1600, ex));
	}

	// Blog covers 1200x630 WebP (<100 KB). The speed cover uses the score cards + Core Web Vitals
	// (css box 244,372 to 1576,1083 on the 1600 px Speed screen, doubled for the 2x shot).
	await sharp(shot('speed-view@2x.png')).extract({ left: 488, top: 744, width: 2664, height: 1422 }).toFile(shot('cover-speed@2x.png'));
	const covers = {
		'cover-wordpress-analytics-without-google-analytics': {
			t: 'WordPress analytics without Google Analytics',
			e: 'Cookieless visitor stats inside your WordPress dashboard.',
			c: 'No cookies|No GA account|Your data',
			i: 'shots/visitors-90d-widgets@2x.png',
		},
		'cover-check-pagespeed-score-in-wordpress': {
			t: 'Check PageSpeed score in WordPress',
			e: 'Mobile and desktop scores, Core Web Vitals and clear fixes.',
			c: 'No API key|One click|Fixes for 90+',
			i: 'shots/cover-speed@2x.png',
		},
	};
	for (const [name, v] of Object.entries(covers)) {
		const buf = await render('cover.html', 1200, 630, 1, null, '?' + new URLSearchParams(v));
		log(name, await webp(buf, path.join(MEDIA, name + '.webp'), null, 100000));
	}
	await br.close();
})().catch((e) => { console.error(e); process.exit(1); });
