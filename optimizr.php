<?php

/**
 * Plugin Name: Haptic Optimizr
 * Plugin URI: https://optimizr.hapticstudio.fr/
 * Description: Cookie consent (Tarteaucitron), SEO meta tags, login protection and maintenance tools in a single settings page, enabled module by module.
 * Version: 7.5.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Haptic
 * Author URI: https://alexandrevergnaud.fr/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: optimizr
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Doit rester synchronisé avec l'en-tête "Version" ci-dessus (SemVer, cf. README.md)
define('HAPTIC_OPTIMIZR_VERSION', '7.5.0');
define('HAPTIC_OPTIMIZR_FILE', __FILE__);
define('HAPTIC_OPTIMIZR_PATH', plugin_dir_path(__FILE__));
define('HAPTIC_OPTIMIZR_URL', plugin_dir_url(__FILE__));
// Page de présentation d'Optimizr Pro (lien du tableau de bord)
define('HAPTIC_OPTIMIZR_PRO_INFO_URL', 'https://optimizr.hapticstudio.fr/tarifs/');
// Hook de la page d'options (menu de premier niveau, cf. functions/admin-ui.php)
define('HAPTIC_OPTIMIZR_ADMIN_HOOK', 'toplevel_page_haptic-options');

// Traductions fournies avec le plugin (languages/) : français pour les sites installés depuis GitHub.
// Sur WordPress.org, les traductions de translate.wordpress.org (wp-content/languages/plugins) passent avant.
add_action('init', function () {
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- traduction française fournie dans languages/, utilisée tant que translate.wordpress.org n'en propose pas
    load_plugin_textdomain('optimizr', false, dirname(plugin_basename(HAPTIC_OPTIMIZR_FILE)) . '/languages');
}, 0);

// Plugin core includes (activation, admin UI, assets)
require_once(__DIR__ . '/functions/activation.php');
require_once(__DIR__ . '/functions/admin-ui.php');
require_once(__DIR__ . '/functions/assets.php');

require_once(__DIR__ . '/functions/tabs.php');
require_once(__DIR__ . '/functions/tools.php');
require_once(__DIR__ . '/functions/audit.php');
require_once(__DIR__ . '/functions/uninstall.php');
require_once(__DIR__ . '/functions/uninstall-admin.php');
require_once(__DIR__ . '/functions/onboarding.php');

// Modules activables depuis le tableau de bord (cf. haptic_modules())
if (haptic_is_module_active('emails')) {
    require_once(__DIR__ . '/functions/mails.php');
}
if (haptic_is_module_active('thumbnails')) {
    require_once(__DIR__ . '/functions/thumbnails.php');
    require_once(__DIR__ . '/functions/images.php');
}
if (haptic_is_module_active('cookies')) {
    require_once(__DIR__ . '/functions/cookies.php');
}
if (haptic_is_module_active('security')) {
    require_once(__DIR__ . '/functions/security.php');
    require_once(__DIR__ . '/functions/security-2fa.php');
    require_once(__DIR__ . '/functions/security-2fa-profile.php');
    require_once(__DIR__ . '/functions/security-welcome.php');
    require_once(__DIR__ . '/functions/security-admin.php');
}
if (haptic_is_module_active('seo')) {
    require_once(__DIR__ . '/functions/seo/seo.php');
}

/*
 * Extensions d'Optimizr (Optimizr Pro) : chargées ici, une fois le cœur et ses modules en place.
 * Une extension chargée avant Optimizr (ordre alphabétique des dossiers) s'y accroche avec add_action().
 */
do_action('haptic_loaded');
