=== Haptic Optimizr ===
Contributors: alexandrevergnaud
Tags: seo, cookies, gdpr, security
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 7.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent (GDPR), SEO meta tags, login protection and maintenance tools in one settings page, enabled module by module.

== Description ==

Haptic Optimizr gathers in a single admin page the settings a brochure website needs every day. Each feature is a **module** you can turn on or off from the plugin dashboard; a disabled module loads no code and its tab disappears.

= Cookies & GDPR =

* Cookie consent banner powered by Tarteaucitron.js (bundled), with configurable position and live preview.
* Google Analytics 4 and Google Tag Manager, loaded only after the visitor's consent.
* Google Consent Mode v2.
* YouTube, Dailymotion, X, Facebook and Google Maps embeds always offered in the consent panel.

= SEO =

* Default title and description patterns built from variables, with Google and social previews.
* OpenGraph tags and a "SEO & OpenGraph" meta box in the editor (custom title, description, image, noindex).

= Security =

* Custom login URL, login attempt limiting (IP whitelist, trusted proxies and Cloudflare), neutral login error messages, email alert on administrator login from a new IP address.

= Also included =

* Featured image tools, email settings.
* Settings export and import, setup wizard, choice of what is deleted when the plugin is uninstalled.

== External services ==

= Third-party services of the cookie banner =

The cookie module loads the services you configure (Google Analytics, Google Tag Manager) in your visitors' browsers, and only after they consent to each service. Embeds from YouTube, Dailymotion, X, Facebook and Google Maps are also blocked until the visitor consents. Each service then receives the data described in its own terms and privacy policy, which visitors can read from the consent panel.

== Installation ==

1. Install the plugin from Plugins > Add New, or upload the zip file.
2. Activate Haptic Optimizr. The setup wizard opens on first activation; you can run it again from Optimizr > Assistant.
3. Turn on the modules you need from the Optimizr dashboard.

== Frequently Asked Questions ==

= What is deleted when I uninstall the plugin? =

You choose when deactivating the plugin, or in the Tools tab. By default, settings, redirects (created with version 4.x) and logs are kept; scheduled tasks, caches and generated files are always removed.

== Changelog ==

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
