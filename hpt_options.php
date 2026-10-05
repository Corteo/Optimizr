<?php

/**
 * Plugin Name: Haptic Optimizr
 * Description: Cookies et RGPD (Tarteaucitron), SEO, assistant IA, types de contenu personnalisés et outils de maintenance, réunis dans une seule page de réglages et activables module par module.
 * Version: 3.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Haptic
 * Author URI: https://alexandrevergnaud.fr/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: optimizr
 */

if (!defined('ABSPATH')) {
    exit;
}

// Doit rester synchronisé avec l'en-tête "Version" ci-dessus (SemVer, cf. README.md)
define('HAPTIC_OPTIMIZR_VERSION', '3.0.0');
define('HAPTIC_OPTIMIZR_PATH', plugin_dir_path(__FILE__));
define('HAPTIC_OPTIMIZR_URL', plugin_dir_url(__FILE__));
// Hook de la page d'options (menu de premier niveau, cf. functions/admin-ui.php)
define('HAPTIC_OPTIMIZR_ADMIN_HOOK', 'toplevel_page_haptic-options');

// Plugin core includes (activation, admin UI, assets, updates)
require_once(__DIR__ . '/functions/activation.php');
require_once(__DIR__ . '/functions/admin-ui.php');
require_once(__DIR__ . '/functions/assets.php');

require_once(__DIR__ . '/haptic_admin-page.php');
require_once(__DIR__ . '/functions/updates.php'); // build:github-only (retiré de la version WordPress.org)
require_once(__DIR__ . '/functions/tabs.php');
require_once(__DIR__ . '/functions/tools.php');
require_once(__DIR__ . '/functions/uninstall.php');
require_once(__DIR__ . '/functions/uninstall-admin.php');
require_once(__DIR__ . '/functions/onboarding.php');

// Modules activables depuis le tableau de bord (cf. haptic_modules())
if (haptic_is_module_active('emails')) {
    require_once(__DIR__ . '/functions/mails.php');
}
if (haptic_is_module_active('thumbnails')) {
    require_once(__DIR__ . '/functions/thumbnails.php');
}
if (haptic_is_module_active('cpts')) {
    require_once(__DIR__ . '/functions/cpts.php');
}
if (haptic_is_module_active('cookies')) {
    require_once(__DIR__ . '/functions/cookies.php');
}
if (haptic_is_module_active('ai')) {
    require_once(__DIR__ . '/functions/ai-pricing.php');
    require_once(__DIR__ . '/functions/ai-providers.php');
    require_once(__DIR__ . '/functions/ai.php');
    require_once(__DIR__ . '/functions/ai-bulk.php');
    require_once(__DIR__ . '/functions/media.php');
}
if (haptic_is_module_active('redirects')) {
    require_once(__DIR__ . '/functions/redirects.php');
    require_once(__DIR__ . '/functions/links.php');
    require_once(__DIR__ . '/functions/redirects-admin.php');
}
if (haptic_is_module_active('local')) {
    require_once(__DIR__ . '/functions/local-seo.php');
}
if (haptic_is_module_active('pages')) {
    require_once(__DIR__ . '/functions/pages.php');
}
if (haptic_is_module_active('security')) {
    require_once(__DIR__ . '/functions/security.php');
    require_once(__DIR__ . '/functions/security-admin.php');
}
if (haptic_is_module_active('seo')) {
    require_once(__DIR__ . '/functions/seo/seo.php');
}
