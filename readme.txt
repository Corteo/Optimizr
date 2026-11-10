=== Optimizr ===
Contributors: alexandrevergnaud
Tags: seo, cookies, gdpr, security, cache
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 7.13.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent (GDPR), SEO tags, login protection with two-factor authentication, page cache and maintenance tools in one settings page, by module.

== Description ==

Optimizr gathers in a single admin page the settings a brochure website needs every day. Each feature is a **module** you can turn on or off from the plugin dashboard; a disabled module loads no code and its tab disappears.

An "Optimizr" menu in the admin bar, on the site and in the dashboard, opens the plugin dashboard and each of its tabs (administrators only).

= Cookies & GDPR =

* Cookie consent banner powered by Tarteaucitron.js (bundled), with configurable position and live preview. Blends in with your theme (background, text, fonts, buttons) or uses its own colors; texts always readable (WCAG AA contrast), mobile-friendly preferences panel.
* Google Analytics 4 and Google Tag Manager, loaded only after the visitor's consent.
* Campaign tracking wizard: conversions (contact form, phone, email, WhatsApp, booking and download clicks) sent to Google Analytics 4 and Google Tag Manager.
* Google Consent Mode v2.
* YouTube, Dailymotion, X, Facebook and Google Maps embeds always offered in the consent panel.

= SEO =

* Default title and description patterns built from variables, with Google and social previews.
* OpenGraph tags and a "SEO & OpenGraph" meta box in the editor (custom title, description, image, noindex).

= Security =

* Custom login URL, login attempt limiting (IP whitelist, trusted proxies and Cloudflare), neutral login error messages, email alert on administrator login from a new IP address.
* Two-factor authentication with an authenticator app (TOTP codes computed by the plugin, no external service) or a code sent by email, backup codes, required for chosen roles.
* "Secure your account" screen right after login: users set up two-factor authentication in a few seconds.

= Images =

* Uploaded JPEG and PNG images saved as WebP or AVIF (when the server supports it) by WordPress's own image editor, with a quality setting. No external service; the original file is kept.
* Choice of the content types that accept a featured image.

= Lighthouse audit =

* Performance, accessibility, best practices and SEO scores of any page of the site, on mobile or desktop, run on demand by Google PageSpeed Insights (free Google API key required).
* Lab measurements (LCP, CLS, TBT…), real-visitor data from the Chrome UX Report when available, the main points to improve and a history of the last 30 audits.

= Cache =

* Page cache: pages are saved as HTML files and sent to visitors without loading WordPress (advanced-cache.php drop-in), with a lifespan, excluded addresses and an optional separate cache for phones. Logged-in users, searches, password-protected content and shopping carts are never cached.
* Cleared automatically when content, comments, menus, the theme or settings change, and on demand from the Cache tab or the Optimizr menu of the admin bar (whole site or current page).
* Browser cache and gzip compression rules written to .htaccess on Apache and LiteSpeed; rules to copy for Nginx.
* Detects other cache plugins and the host's own cache to avoid conflicts, and clears the host's cache (WP Engine, Pantheon, Nginx Helper) along with its own.

= Also included =

* Email settings.
* Settings export and import, setup wizard, choice of what is deleted when the plugin is uninstalled.

== External services ==

= Third-party services of the cookie banner =

The cookie module loads the services you configure (Google Analytics, Google Tag Manager) in your visitors' browsers, and only after they consent to each service. Embeds from YouTube, Dailymotion, X, Facebook and Google Maps are also blocked until the visitor consents. Each service then receives the data described in its own terms and privacy policy, which visitors can read from the consent panel.

= Google PageSpeed Insights (Lighthouse audit) =

The Audit tab uses the Google PageSpeed Insights API to run Lighthouse on a page of your site. Nothing is sent until an administrator clicks "Run the audit"; the plugin then sends the address of the page, the device (mobile or desktop), the admin language and the Google API key entered in the tab to `https://www.googleapis.com/pagespeedonline/v5/runPagespeed`. Google loads the page from its servers, like any visitor, and returns the report. Service provided by Google: [Terms of Service](https://developers.google.com/terms), [Privacy Policy](https://policies.google.com/privacy).

== Installation ==

1. Install the plugin from Plugins > Add New, or upload the zip file.
2. Activate Optimizr. The setup wizard opens on first activation; you can run it again from Optimizr > Wizard.
3. Turn on the modules you need from the Optimizr dashboard.

== Frequently Asked Questions ==

= What is deleted when I uninstall the plugin? =

You choose when deactivating the plugin, or in the Tools tab. By default, settings, redirects (created with version 4.x) and logs are kept; scheduled tasks, caches and generated files are always removed.

= Which files does the page cache change? =

When you turn it on, Optimizr writes wp-content/advanced-cache.php, adds define('WP_CACHE', true); to wp-config.php and stores pages in wp-content/cache/optimizr. The browser cache rules go into a "# BEGIN Optimizr Cache" block of .htaccess. All of this is removed when you turn the feature, the module or the plugin off. If another cache plugin is active, the page cache stays off.

== Changelog ==

= 7.13.0 =
* New import from Yoast SEO, All in One SEO, Rank Math, SEOPress and The SEO Framework (Tools tab): SEO title, description, share image and "noindex" of each content, and optionally the title and description patterns. Values already set in Optimizr are kept, the other plugin's data stays in the database, and the plugin can be deactivated right after the import.

= 7.12.0 =
* New floating toolbar, off by default (Tools tab): on the site, it replaces the WordPress admin bar with a small floating module to edit the page, create content, check the page SEO, clear the cache and see pending comments and updates. Three designs to choose from (bubble, dock, page panel), left or right side, and the roles that get it.
* "Audit this page" opens the Audit tab with the page address filled in and runs the audit.

= 7.11.0 =
* New "Optimizr" menu in the admin bar, on the site and in the dashboard: it opens the plugin dashboard and links to each tab, the setup wizard and campaign tracking. Shown to administrators only.
* The admin bar "Cache" menu (clear all cache, clear this page) now sits under Optimizr › Cache.

= 7.10.0 =
* New "Google Search" card on the dashboard, with a link to Google Search Console. It can be hidden.

= 7.9.0 =
* New campaign tracking wizard (Optimizr › Campaign tracking): a guided path to set up Google Analytics 4 or Google Tag Manager for Google campaigns, with help to find each ID and a warning against counting visits twice.
* Conversions: contact form sent (Contact Form 7), clicks on a phone number, an email address, WhatsApp, a booking service (Doctolib, Calendly…) or a file to download (PDF, Word, Excel, ZIP…) are sent to Google Analytics 4 as events (generate_lead, click_phone…) and to Google Tag Manager, through the consent banner and Google Consent Mode v2.
* The Cookies & GDPR tab shows the tracking status and when each conversion was last seen on the site. The final screen of the wizard lists the checks to do before launching the campaigns.

= 7.8.0 =
* New dashboard. An illustrated header greets you and lists what needs your attention, with one status per theme (security, visibility, performance, content); passed checks can be shown below.
* Modules are grouped by theme in a compact list. A switch now enables or disables a module right away, without the Save button, with a confirmation offering "Configure" or "Undo". The module's tab appears or disappears at once.
* The latest PageSpeed audit (scores on mobile and desktop, trend of the Performance score) and the cache status are shown on the dashboard, with quick actions: clear the cache, run an audit.
* One "Versions and licence" card, which says when WordPress offers an update. The setup wizard invitation can be postponed ("Later").
* Without Optimizr Pro, it is presented in a single card that can be hidden.

= 7.7.0 =
* New: "Site theme" appearance for the cookie banner (default). The banner and the "Customize" panel use your theme's background, text color, fonts and button style, with block themes as well as classic themes. Sites whose banner colors were saved with Optimizr Pro keep them.
* New look for the cookie banner and the "Customize" panel: rounded card on a dimmed background (center) or floating bar (top, bottom), clearer buttons, one card per service with Allow / Deny choices, Close and Save at the bottom of the panel. The theme's font is used.
* Fixed: unreadable texts (white on a light background) in the banner and the panel, and the color of the "Customize" button, which was not applied. Text color is now computed for each background so that it always reaches 4.5:1 (WCAG AA).
* New default colors: white banner and panel, dark "Deny all" button, light "Customize" button. Colors saved with Optimizr Pro are kept.

= 7.6.2 =
* Fixed: with a custom login address, admin addresses (/wp-admin/) now show the theme's 404 page to logged-out visitors, like any address that does not exist, instead of a plain "Page not found" page.

= 7.6.1 =
* The plugin is now named "Optimizr" (formerly "Haptic Optimizr"). Settings, slug and folder are unchanged.
* Code cleanup following Plugin Check: page cache request data validated in a single place, script of the "Secure your account" screen moved to its own file, unique handle for the Tarteaucitron script.
* Fixed: pages whose address contains an apostrophe were never saved in the page cache.

= 7.6.0 =
* New: Cache module and tab. Page cache served before WordPress loads, browser cache and compression rules (.htaccess), automatic clearing when content changes, manual clearing from the tab and the admin bar, and detection of other cache plugins and host caches. Off by default.

= 7.5.0 =
* New: Lighthouse audit (Audit tab). Performance, accessibility, best practices and SEO scores of any page of the site, on mobile or desktop, with lab measurements, real-visitor data (Chrome UX Report), the main points to improve and a history of the last 30 audits. Run by Google PageSpeed Insights, on demand, with a free Google API key.

= 7.4.1 =
* Fixed: CMYK JPEG images (prepared for print) converted to WebP or AVIF had very different colors, because their color profile was ignored. With Imagick they are now converted to sRGB with their profile; with GD, which cannot do it faithfully, they stay in JPEG.

= 7.4.0 =
* New: uploaded JPEG and PNG images can be converted to WebP or AVIF (Content tab, "Image formats" card). Conversion is done by your server, with no external service; the original file is kept. AVIF is offered only when the server supports it.

= 7.3.0 =
* New: the setup wizard turns on two-factor authentication (and the passkeys of Optimizr Pro) in its Security step, then lets the administrator secure their own account right away, before the final screen.
* Improved: the "Secure your account" screen shown after login was redesigned. It is no longer broken by the styles of the login page (missing spacing, floating buttons): clear cards with an icon per step, method choice, code fields on one line, backup codes in a grid, a checked state once a step is done, and a layout that fits small screens.

= 7.2.0 =
* New: the code can be sent by email instead of an authenticator app (setting "Code by email", on by default). Valid for 10 minutes, up to 3 emails per login, always sent to the address verified at setup. A setting can require the app for the roles where two-factor authentication is mandatory.
* New: "Secure your account" screen. At the first login after two-factor authentication is enabled, users are offered to set it up in a few seconds before reaching the dashboard. "Later" postpones it by a week; required roles can only set it up.
* Security: new backup codes, changing the method and disabling two-factor authentication now require a code. At most 5 wrong codes per account every 15 minutes (login and profile together) and 10 emailed codes per hour. An administrator can no longer reset their own two-factor authentication without a code. Application passwords of a protected account can only be created within 10 minutes of logging in.

= 7.1.0 =
* New: two-factor authentication. After the password, a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…). Set up from each user's profile with a QR code, with 10 single-use backup codes. Can be required for chosen roles.
* Codes are computed by the plugin itself (TOTP, RFC 6238): no external service. The QR code is drawn in the browser by the bundled qrcode-generator library (MIT).
* Recovery: backup codes, reset by an administrator from the user's profile, or the `OPTIMIZR_DISABLE_2FA` constant in wp-config.php.
* XML-RPC logins with the password of a protected account are refused (application passwords still work).

= 7.0.0 =
* Removed: the Content types module (ready-made "Events" type and custom post types). It is now part of Optimizr Pro 1.3.0, without ACF Pro.
* Existing content types and their content stay in the database: Optimizr Pro 1.3.0 picks them up as they are. Without Optimizr Pro, they are no longer registered.
* An older Optimizr Pro (before 1.3.0) shows a notice asking for its update.

= 6.0.1 =
* Removed unused legacy admin hooks (`haptic_before_admin_init`, `haptic_add_options_tabs`, `haptic_after_admin_init`).

= 6.0.0 =
* Removed: updates from GitHub (Plugin Update Checker) in the GitHub distribution. Updates now come from WordPress.org only. Sites installed from GitHub get them once Optimizr is published there under the `optimizr` slug.
* The `OPTIMIZR_GITHUB_TOKEN` constant is no longer used.

= 5.0.1 =
* Tested up to WordPress 7.1.
* Development files of the bundled Tarteaucitron library are no longer shipped.
* Code cleanup following Plugin Check: URL parameters are unslashed and sanitized, intended direct database queries are documented.

= 5.0.0 =
* Removed: the Redirects module (redirects and 404 log). It is now part of Optimizr Pro 1.2.0, without the 5-redirect limit.
* Existing redirects and the 404 log stay in the database: Optimizr Pro 1.2.0 picks them up as they are. Without Optimizr Pro, they no longer apply.
* An older Optimizr Pro (before 1.2.0) pauses its redirects and shows a notice asking for its update.

= 4.0.0 =
* The plugin now covers cookie consent with Google Analytics and Google Tag Manager, SEO meta tags, the 404 log with up to 5 redirects, login protection, custom post types, featured images, emails and tools.
* Removed: AI assistant, local SEO, special pages (404 page, maintenance mode), XML sitemap, robots.txt, IndexNow, structured data, other cookie services and banner colors, redirect wildcards and CSV import, automatic redirects, broken link checker, other security options.
* New hooks for add-ons (modules, tabs, cookie services, redirects, security, setup wizard).

= 3.0.0 =
* All functions, options, hooks and constants now use the `haptic_` prefix. Existing settings, SEO data and scheduled tasks are migrated automatically on update; custom code using the old `hpt_` hooks must be updated.
* Developer tools removed (debug mode, debug log, `vd()` function).
* Plugin translatable: English source strings, French translation included.
* Main file renamed to `optimizr.php`: reactivate the plugin after updating.
* Security hardening: all output escaped, inline scripts and styles printed with the WordPress APIs, prepared SQL queries, request data sanitized.
* File operations use the WordPress Filesystem API.
* Requires WordPress 6.2 or later.

= 2.5.0 =
* Version ready for the WordPress.org plugin directory: updates are handled by WordPress.
* The developer debug tool no longer loads Bootstrap from a CDN.

== Upgrade Notice ==

= 4.0.0 =
Several modules and options were removed. Settings are kept.

= 3.0.0 =
The main plugin file is renamed: reactivate the plugin after updating. Settings are migrated automatically to the new `haptic_` prefix. Custom code using `hpt_` hooks or functions must be renamed.
