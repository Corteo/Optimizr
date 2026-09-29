<?php

if (!defined('ABSPATH')) {
    exit;
}

function hpt_options_activate()
{
    add_option('hpt_debug_mode', false);
    add_option('hpt_dev_mode', false);
    add_action('admin_notices', 'hpt_options_activation_notice');
}

function hpt_options_deactivate()
{
    hpt_sitemap_delete_files();
}

/** Marqueur présent dans chaque fichier de sitemap généré par Optimizr. */
define('HPT_SITEMAP_MARKER', '<!-- Généré par Optimizr -->');

/**
 * Le fichier a-t-il été généré par Optimizr ? (évite de supprimer un sitemap d'une autre origine)
 */
function hpt_sitemap_is_ours($path)
{
    if (!is_file($path)) {
        return false;
    }
    $head = (string) file_get_contents($path, false, null, 0, 300);
    return strpos($head, HPT_SITEMAP_MARKER) !== false;
}

/**
 * Supprime les fichiers de sitemap générés et les tâches planifiées.
 * Appelé à la désactivation du plugin ou du module SEO : aucun sitemap périmé ne reste en ligne.
 */
function hpt_sitemap_delete_files()
{
    $paths = array_merge([ABSPATH . 'sitemap.xml'], (array) glob(ABSPATH . 'sitemap-*.xml'));
    foreach ($paths as $path) {
        if (hpt_sitemap_is_ours($path)) {
            @unlink($path);
        }
    }

    wp_clear_scheduled_hook('haptic_GPT_daily_sitemap_cron');
    wp_clear_scheduled_hook('hpt_sitemap_regenerate');
}

// Désactivation du module SEO depuis le tableau de bord
add_action('update_option_hpt_modules', function ($old, $new) {
    if (is_array($new) && empty($new['seo'])) {
        hpt_sitemap_delete_files();
    }
}, 10, 2);

function hpt_options_activation_notice()
{
    echo '<div class="notice notice-success is-dismissible"><p>Haptic Optimizr a été activé avec succès !</p></div>';
}

/**
 * Migrations exécutées une seule fois après une mise à jour du plugin.
 */
function hpt_options_maybe_upgrade()
{
    $installed = get_option('hpt_optimizr_version', '0');

    if (version_compare($installed, HPT_OPTIMIZR_VERSION, '>=')) {
        return;
    }

    if (version_compare($installed, '2.0', '<')) {
        // Avant la 2.0, ces cases n'avaient aucun effet : les balises étaient toujours affichées
        update_option('hpt_enable_seo', 1);
        update_option('hpt_enable_opengraph', 1);
    }

    if (version_compare($installed, '2.0.0-beta.3', '<') && function_exists('hpt_seo_cleanup_saved_meta')) {
        $removed = hpt_seo_cleanup_saved_meta();
        hpt_log('[Migration] Valeurs SEO pré-remplies supprimées : ' . $removed);
    }

    if (version_compare($installed, '2.0.0-beta.4', '<')) {
        // Ancien journal public du sitemap, remplacé par un historique en base
        @unlink(HPT_OPTIMIZR_PATH . 'functions/seo/sitemap.log');

        // Ancien plan du site HTML statique, remplacé par le shortcode [optimizr_plan_du_site]
        $html = ABSPATH . 'sitemap.html';
        if (is_file($html)) {
            $content = (string) file_get_contents($html, false, null, 0, 2000);
            if (strpos($content, '<title>Plan du site</title>') !== false && strpos($content, 'cdn.jsdelivr.net/npm/bootstrap') !== false) {
                @unlink($html);
            }
        }

        // Sitemap régénéré avec les nouvelles règles (exclusions, page d'accueil)
        if (function_exists('haptic_GPT_write_sitemap_to_file')) {
            haptic_GPT_write_sitemap_to_file('mise à jour du plugin');
        }
    }

    update_option('hpt_optimizr_version', HPT_OPTIMIZR_VERSION);
}
// Sur init (tard) : types de contenu et shortcodes chargés, nécessaires pour comparer les extraits
add_action('init', 'hpt_options_maybe_upgrade', 99);

register_activation_hook(__DIR__ . '/../hpt_options.php', 'hpt_options_activate');
register_deactivation_hook(__DIR__ . '/../hpt_options.php', 'hpt_options_deactivate');
