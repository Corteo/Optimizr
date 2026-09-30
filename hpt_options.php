<?php

/**
 * Plugin Name: Haptic Optimizr
 * Plugin URI: https://alexandrevergnaud.fr/
 * Description: Cookies et RGPD (Tarteaucitron), SEO, assistant IA, types de contenu personnalisés et outils de maintenance, réunis dans une seule page de réglages et activables module par module.
 * Version: 2.0.0-beta.6
 * Author: Alexandre Vergnaud
 * Author URI: https://alexandrevergnaud.fr/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hpt-options
 */

if (!defined('ABSPATH')) {
    exit;
}

// Doit rester synchronisé avec l'en-tête "Version" ci-dessus (SemVer, cf. README.md)
define('HPT_OPTIMIZR_VERSION', '2.0.0-beta.6');
define('HPT_OPTIMIZR_PATH', plugin_dir_path(__FILE__));
define('HPT_OPTIMIZR_URL', plugin_dir_url(__FILE__));
// Hook de la page d'options (menu de premier niveau, cf. functions/admin-ui.php)
define('HPT_OPTIMIZR_ADMIN_HOOK', 'toplevel_page_hpt-options');

// Plugin core includes (activation, admin UI, assets, updates)
require_once('functions/activation.php');
require_once('functions/admin-ui.php');
require_once('functions/assets.php');

require_once('hpt_admin-page.php');
require_once('functions/utils.php');
require_once('functions/updates.php');
require_once('functions/tabs.php');
require_once('functions/settings.php');
require_once('functions/tools.php');

// Modules activables depuis le tableau de bord (cf. hpt_modules())
if (hpt_is_module_active('emails')) {
    require_once('functions/mails.php');
}
if (hpt_is_module_active('thumbnails')) {
    require_once('functions/thumbnails.php');
}
if (hpt_is_module_active('cpts')) {
    require_once('functions/cpts.php');
}
if (hpt_is_module_active('cookies')) {
    require_once('functions/cookies.php');
}
if (hpt_is_module_active('ai')) {
    require_once('functions/ai-pricing.php');
    require_once('functions/ai.php');
    require_once('functions/ai-bulk.php');
    require_once('functions/media.php');
}
if (hpt_is_module_active('seo')) {
    require_once('functions/seo/seo.php');
    require_once('gutenberg/gutenberg.php');
}
