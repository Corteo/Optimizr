=== Haptic Optimizr ===
Contributors: alexandrevergnaud
Tags: seo, cookies, gdpr, redirects, ai
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent (GDPR), SEO, redirects, security, AI writing assistant and maintenance tools in one settings page, enabled module by module.

== Description ==

Haptic Optimizr gathers in a single admin page the settings a brochure website needs every day. Each feature is a **module** you can turn on or off from the plugin dashboard; a disabled module loads no code and its tab disappears.

The admin interface is currently in French.

= Cookies & GDPR =

* Cookie consent banner powered by Tarteaucitron.js (bundled), with configurable position and colors, live preview and contrast check.
* Third-party services loaded only after the visitor's consent: Google Analytics 4, Google Tag Manager, Microsoft Clarity, Matomo Tag Manager, Hotjar, Google Ads, Meta Pixel, Google Fonts, Google Maps, Google reCAPTCHA, hCaptcha, YouTube, Dailymotion, X, Facebook.
* Google Consent Mode v2 and Bing Consent Mode.

= SEO =

* Default title and description patterns built from variables, with Google and social previews.
* OpenGraph tags and a "SEO & OpenGraph" meta box in the editor (custom title, description, image, noindex).
* XML sitemap regenerated in the background, HTML sitemap shortcode, editable robots.txt.
* Optional: noindex for search, date and tag archives, attachment page redirects, Search Console and Bing verification codes, IndexNow, schema.org structured data.

= Redirects & links =

* 301, 302, 307 and 410 redirects with wildcards, hit counter, CSV import and export.
* Automatic redirect when a published URL changes.
* 404 log (no IP address stored) and broken link checker.

= Local SEO =

* Business details published as LocalBusiness structured data, with shortcodes for address, phone, email, opening hours, Google review and directions links.

= AI assistant =

* Generates meta descriptions and image ALT text with OpenAI, Anthropic (Claude) or Google (Gemini), using your own API key.
* Cost estimate before each run, model comparison, monthly usage tracking, bulk processing that never overwrites existing values.

= Special pages =

* Custom 404 page written in the editor, with suggestions of similar content.
* Maintenance mode with 503 status, Retry-After header and automatic reopening.

= Also included =

* Security: custom login URL, login attempt limiting, IP whitelist, hardening options.
* Custom post types (including a ready-made "Events" type using ACF Pro fields, if ACF Pro is installed), featured image tools, email settings.
* Settings export and import, setup wizard, choice of what is deleted when the plugin is uninstalled.

== External services ==

This plugin connects to the following third-party services, only when you enable the related feature.

= OpenAI API =

Used by the AI assistant when OpenAI is the selected provider, to generate meta descriptions and image ALT text. When you launch a generation (from the editor, the media library, the bulk tool, or automatically on image upload if you enabled it), the plugin sends to https://api.openai.com the content title and text, or the image file, together with the site context you entered and your API key.
Terms of use: https://openai.com/policies/terms-of-use/ - Privacy policy: https://openai.com/policies/privacy-policy/

= Anthropic API (Claude) =

Same use and same data as above, sent to https://api.anthropic.com when Anthropic is the selected provider.
Terms of service: https://www.anthropic.com/legal/commercial-terms - Privacy policy: https://www.anthropic.com/legal/privacy

= Google Gemini API =

Same use and same data as above, sent to https://generativelanguage.googleapis.com when Google is the selected provider.
Terms of service: https://ai.google.dev/gemini-api/terms - Privacy policy: https://policies.google.com/privacy

= IndexNow =

Used when the IndexNow option of the SEO module is enabled, to notify search engines (Bing, Yandex and other participants) that a page was published or updated. When content is published or updated, the plugin sends its URL, the site host name and the IndexNow key to https://api.indexnow.org.
Terms and privacy: https://www.indexnow.org/terms

= Third-party services of the cookie banner =

The cookie module loads the services you configure (Google Analytics, Google Tag Manager, Microsoft Clarity, Matomo, Hotjar, Google Ads, Meta Pixel, Google Fonts, Google Maps, reCAPTCHA, hCaptcha, YouTube, Dailymotion, X, Facebook) in your visitors' browsers, and only after they consent to each service. Each service then receives the data described in its own terms and privacy policy, which visitors can read from the consent panel.

= Broken link checker =

When you run a scan, the plugin requests the external URLs found in your published content to check whether they still respond. Only a standard HTTP request is sent to each linked site.

== Installation ==

1. Install the plugin from Plugins > Add New, or upload the zip file.
2. Activate Haptic Optimizr. The setup wizard opens on first activation; you can run it again from Optimizr > Assistant.
3. Turn on the modules you need from the Optimizr dashboard.

== Frequently Asked Questions ==

= Do I need an API key? =

Only for the AI assistant. You need your own key from OpenAI, Anthropic or Google; usage is billed by that provider.

= Does the plugin need ACF Pro? =

Only for the fields of the ready-made "Events" post type. Everything else works without it.

= What is deleted when I uninstall the plugin? =

You choose when deactivating the plugin, or in the Tools tab. By default, settings, redirects and logs are kept; scheduled tasks, caches and generated files are always removed.

== Changelog ==

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

= 3.0.0 =
The main plugin file is renamed: reactivate the plugin after updating. Settings are migrated automatically to the new `haptic_` prefix. Custom code using `hpt_` hooks or functions must be renamed.
