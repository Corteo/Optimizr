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
    wp_clear_scheduled_hook('haptic_GPT_daily_sitemap_cron');
}

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

    update_option('hpt_optimizr_version', HPT_OPTIMIZR_VERSION);
}
add_action('plugins_loaded', 'hpt_options_maybe_upgrade');

register_activation_hook(__DIR__ . '/../hpt_options.php', 'hpt_options_activate');
register_deactivation_hook(__DIR__ . '/../hpt_options.php', 'hpt_options_deactivate');
