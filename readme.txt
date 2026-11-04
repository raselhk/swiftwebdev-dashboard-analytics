=== SwiftWebDev Dashboard & Analytics - Visitor Statistics & PageSpeed ===
Contributors: raselhk
Tags: dashboard, analytics, cookieless analytics, statistics, pagespeed
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Admin dashboard and cookieless analytics for WordPress: visitor stats, Google PageSpeed scores, site health and a modern admin menu.

== Description ==

= Your whole site at a glance: visitors, speed and health on one modern dashboard =

SwiftWebDev Dashboard & Analytics adds a clean, colorful home screen to WordPress that answers the questions you ask every day: how many people visited, how fast is the site, and is anything out of date?

No cookies, no Google Analytics account and no API key for speed tests. Install, activate and your dashboard is ready.

The plugin is lightweight by design. The front end gets one small counter script that runs after the page has loaded. Everything else happens in the admin area.

= 📊 Cookieless visitor stats =

* Visitors, page views and sessions for the last 7, 30 or 90 days
* Visitors over time chart, busiest days of the week and engagement (pages per visit, visits per visitor, best day)
* Trend versus the previous period, with a sparkline on every card
* Counted on your own site, stored in your own database
* No cookies or browser storage, and IP addresses are never saved
* Logged-in users are not counted by default

= ⚡ PageSpeed scores with fixes =

* Google PageSpeed scores for mobile and desktop, run with one click
* Test your homepage or any other page of your site
* Performance, Accessibility, Best practices and SEO scores
* Core Web Vitals from the lab test, plus real-user Chrome UX data when Google has it
* A ranked list of what to fix to reach 90+, written in plain language
* No Google API key needed

= 🩺 Updates and site health =

* Pending WordPress, plugin and theme updates
* Your PHP version, with the recommended version
* Site Health status with a shortcut to run the checks
* Recent activity: published posts and pages, new users, plugin and theme updates

= 🔗 Quick links =

* One-click shortcuts to the WordPress tasks you use most
* Pin, reorder and hide shortcuts, and search them
* Each user keeps their own pinned list

= 🎨 A modern admin experience =

* A matching modern style for the WordPress admin menu on every admin page
* Your own logo and site name at the top of the menu
* Search posts, pages and tools from the dashboard header
* Show, hide and drag dashboard cards into your own order
* Optionally open this dashboard after login, with the classic WordPress dashboard one click away
* Administrators get the new look; other users keep the normal WordPress admin

= 🏆 PRO add-on =

Everything described above is included in this plugin, with no limits, license keys or time restrictions. For extra reports, SwiftWebDev Dashboard PRO is a separate add-on plugin, sold and downloaded from our website: real-time visitors, countries and devices, traffic sources and referrers, top pages, form and click tracking, session recordings, CSV export and weekly scheduled speed tests. [Learn more about the PRO add-on](https://swiftwebdev.com/swiftwebdev-dashboard-pro/)

= 🔒 Privacy =

Free visitor stats are counted without cookies or browser storage. Each visit is identified by a one-way hash of the IP address and browser, combined with a random value that changes every day, so visitors cannot be followed from one day to the next. IP addresses and browser details are never saved. Raw visit records are deleted after 48 hours; daily totals are kept for 180 days (the longest period shown, plus the period before it for comparison). All data stays in your WordPress database.

== External services ==

This plugin connects to one external service, and only when an administrator asks it to.

**SwiftWebDev speed test service (swdashapi.swiftoutbox.com), which uses the Google PageSpeed Insights API**

Used to measure the speed scores shown on the Speed & Optimize screen and the dashboard. A request is sent only when an administrator clicks "Run test". The request contains your site address, the address of the page to test (your homepage or another page of your site) and the test type (mobile or desktop). Our service checks that the page belongs to your site, then asks Google PageSpeed Insights to test it with our own Google API key and returns the result. No visitor data is sent, and the service does not store the result.

* Swift Web Dev terms of service: https://swiftwebdev.com/terms/
* Swift Web Dev privacy policy: https://swiftwebdev.com/privacy-policy/
* Google PageSpeed Insights: https://developers.google.com/speed/docs/insights/v5/about
* Google terms of service: https://developers.google.com/terms
* Google privacy policy: https://policies.google.com/privacy

== Third-party libraries ==

All are bundled locally (nothing is loaded from a CDN) and GPL-compatible:

* SortableJS, MIT license (assets/vendor/sortable/LICENSE).
* Plus Jakarta Sans font, SIL Open Font License 1.1 (assets/fonts/OFL.txt).

== Installation ==

1. Install and activate SwiftWebDev Dashboard & Analytics from the Plugins screen.
2. Open **SwiftWebDev** in the admin menu.
3. Optional: in the plugin settings (user menu, top right), set your own sidebar logo and name.

== Frequently Asked Questions ==

= Does the plugin slow down my site? =

The visitor counter is a small script that sends one request per page view after the page has loaded. The plugin adds nothing else to your front end.

= Why do the speed tests not work on my local site? =

Google can only test websites that are reachable on the public internet. Run the test on your live site.

= Do I need a Google API key for speed tests? =

No. Speed tests run through the SwiftWebDev speed test service with our own key. You can test your homepage or any other page of your site.

= Can I still use the normal WordPress dashboard? =

Yes. The WordPress Dashboard menu item is not changed. You can also open your user menu in the plugin and choose "Classic WordPress dashboard". "Open SwiftWebDev Dashboard after login" is off by default; you can turn it on in the plugin settings.

== Screenshots ==

1. The dashboard: visitor stats, speed scores, quick links, updates and recent activity on one screen.
2. Cookieless visitor stats on the dashboard: visitors over time, busiest days and engagement for the last 7, 30 or 90 days.
3. Speed & Optimize: mobile and desktop PageSpeed scores, Core Web Vitals and fixes to reach 90+.
4. Quick Links: pin, reorder and search shortcuts to common WordPress tasks.

== Changelog ==

= 1.0.0 =
* First public release: admin dashboard, cookieless visitor stats, Google PageSpeed scores with Core Web Vitals, quick links, updates and site health, modern admin menu.
