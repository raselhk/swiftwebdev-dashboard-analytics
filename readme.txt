=== Swift Dash: Admin Dashboard & Analytics, Visitor Stats, PageSpeed & Site Health ===
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

Swift Dash gives your WordPress site a clean, modern dashboard with three things you check every day:

* **Visitor stats.** Visitors, page views and sessions for the last 7 or 30 days, counted on your own site. No cookies, no third-party analytics account, and IP addresses are never stored.
* **Speed scores.** Test your homepage with Google PageSpeed for mobile and desktop and see the measured scores, Core Web Vitals and a ranked list of what to fix to reach 90+. No API key needed.
* **Quick links.** Shortcuts to the WordPress tasks you use most. Pin, reorder and hide them.

The dashboard also shows pending updates, your PHP version, the Site Health status and recent activity on your site (published posts, new users, plugin and theme updates).

For administrators, Swift Dash also gives the WordPress admin menu a matching modern style on every admin page, with your own logo and name at the top. Other users see the normal WordPress admin.

= Swift Dash Pro =

Pro is a separate add-on that adds full analytics (real-time visitors, countries, devices, traffic sources, top pages, forms, clicks, bots, events, session recordings), longer date ranges, exports and scheduled speed tests with alerts. Pro features are shown in the free plugin as locked cards that link to the Pro plans. The free plugin works fully on its own.

= Privacy =

Free visitor stats are counted without cookies or browser storage. Each visit is identified by a one-way hash of the IP address and browser, combined with a random value that changes every day, so visitors cannot be followed from one day to the next. IP addresses and browser details are never saved. Raw visit records are deleted after 48 hours; daily totals are kept for 60 days. All data stays in your WordPress database. Logged-in users are not counted by default.

== External services ==

This plugin connects to one external service, and only when an administrator asks it to.

**Swift Dash speed test service (swdashapi.swiftoutbox.com), which uses the Google PageSpeed Insights API**

Used to measure the speed scores shown on the Speed & Optimize screen and the dashboard. A request is sent only when an administrator clicks "Run test". The request contains your site address, the address of the page to test (the free version only tests your homepage) and the test type (mobile or desktop). Our service checks that the page belongs to your site, then asks Google PageSpeed Insights to test it with our own Google API key and returns the result. No visitor data is sent, and the service does not store the result.

* Swift Dash terms of service: https://swiftwebdev.com/terms/
* Swift Dash privacy policy: https://swiftwebdev.com/privacy-policy/
* Google PageSpeed Insights: https://developers.google.com/speed/docs/insights/v5/about
* Google terms of service: https://developers.google.com/terms
* Google privacy policy: https://policies.google.com/privacy

== Third-party libraries ==

All are bundled locally (nothing is loaded from a CDN) and GPL-compatible:

* SortableJS, MIT license (assets/vendor/sortable/LICENSE).
* Plus Jakarta Sans font, SIL Open Font License 1.1 (assets/fonts/OFL.txt).

== Installation ==

1. Install and activate Swift Dash from the Plugins screen.
2. Open **Swift Dash** in the admin menu.
3. Optional: in Swift Dash settings, set your own sidebar logo and name.

== Frequently Asked Questions ==

= Does Swift Dash slow down my site? =

The visitor counter is a small script that sends one request per page view after the page has loaded. Swift Dash adds nothing else to your front end.

= Why do the speed tests not work on my local site? =

Google can only test websites that are reachable on the public internet. Run the test on your live site.

= Do I need a Google API key for speed tests? =

No. Speed tests run through the Swift Dash service with our own key. The free version tests your homepage; Swift Dash Pro can test any page of your site.

= Can I still use the normal WordPress dashboard? =

Yes. Open your user menu in Swift Dash and choose "Classic WordPress dashboard". "Open Swift Dash after login" is off by default; you can turn it on in Swift Dash settings.

== Screenshots ==

1. Dashboard with visitor stats, speed scores, quick links, updates and recent activity.
2. Analytics overview.
3. Speed & Optimize with mobile and desktop scores and fixes.
4. Quick Links.

== Changelog ==

= 1.0.0 =
* First public release: admin dashboard, cookieless visitor stats, Google PageSpeed scores with Core Web Vitals, quick links, updates and site health, modern admin menu.
