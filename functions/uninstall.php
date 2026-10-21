<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Suppression du plugin : ce qui est effacé de la base, selon les choix enregistrés (option haptic_uninstall).
 * Chargé par le plugin (choix) et par uninstall.php (nettoyage) : n'utilise aucune autre fonction d'Optimizr.
 */

/**
 * Données facultatives, effacées seulement si la personne l'a demandé.
 * Tout ce qui n'est pas ici (tâches planifiées, caches, fichiers générés) est toujours supprimé.
 *
 * @return array clé => [label, help]
 */
function haptic_uninstall_groups()
{
    return [
        'settings' => [
            'label' => __('Settings', 'optimizr'),
            'help'  => __('Modules, SEO patterns, cookie services, API keys, security… If kept, they will be restored on reinstall.', 'optimizr'),
        ],
        'content'  => [
            'label' => __('Redirects and content SEO', 'optimizr'),
            'help'  => __('Redirects, and the titles, descriptions, images and “noindex” settings entered on each piece of content.', 'optimizr'),
        ],
        'logs'     => [
            'label' => __('Logs and history', 'optimizr'),
            'help'  => __('Pages not found, security log, sitemap history, AI usage, link analysis.', 'optimizr'),
        ],
    ];
}

/**
 * Choix enregistrés : par défaut, rien n'est effacé.
 *
 * @return array clé => bool
 */
function haptic_uninstall_choices()
{
    $saved = get_option('haptic_uninstall', []);
    $choices = [];
    foreach (array_keys(haptic_uninstall_groups()) as $key) {
        $choices[$key] = is_array($saved) && !empty($saved[$key]);
    }
    return $choices;
}

/** Nettoie une saisie (formulaire ou AJAX) : seules les clés connues, en booléens. */
function haptic_uninstall_sanitize($value)
{
    $value = is_array($value) ? $value : [];
    $clean = [];
    foreach (array_keys(haptic_uninstall_groups()) as $key) {
        $clean[$key] = !empty($value[$key]) ? 1 : 0;
    }
    return $clean;
}

/** Options qui sont des journaux ou des historiques, pas des réglages. */
function haptic_uninstall_log_options()
{
    return [
        'haptic_security_log',
        'haptic_sitemap_history',
        'haptic_sitemap_error',
        'haptic_sitemap_last_generated',
        'haptic_ai_usage',
        'haptic_links_report',
        'haptic_links_queue',
        'haptic_indexnow_last',
        'haptic_audit_results',
        'haptic_audit_history',
    ];
}

/**
 * Nettoyage d'un site. Appelé depuis uninstall.php, une fois par site sur un multisite.
 */
function haptic_uninstall_site()
{
    global $wpdb;

    // Site resté en 2.x (plugin désactivé avant la mise à jour) : réglages encore sous l'ancien préfixe
    haptic_migrate_legacy_prefix();

    $choices = haptic_uninstall_choices();

    // Toujours : fichiers générés (sitemap, clé IndexNow, robots.txt écrit par Optimizr et inchangé, règle .htaccess) et tâches planifiées.
    // Avant de toucher aux options : la clé IndexNow est lue dans haptic_indexnow_key.
    if (function_exists('haptic_options_deactivate')) {
        haptic_options_deactivate();
    }
    wp_clear_scheduled_hook('haptic_daily_sitemap_cron');
    wp_clear_scheduled_hook('haptic_sitemap_regenerate');
    // ALT automatique en attente : une tâche par image (identifiant en argument), que wp_clear_scheduled_hook() sans argument ne retire pas
    wp_unschedule_hook('haptic_ai_alt_on_upload');
    // Empreinte du robots.txt écrit par Optimizr : gardée si le fichier n'a pas pu être supprimé, inutile désormais
    delete_option('haptic_robots_txt_hash');

    // Toujours : ancien journal de debug des versions ≤ 2.5 (uploads/optimizr-logs)
    $log_dir = trailingslashit(wp_upload_dir(null, false)['basedir']) . 'optimizr-logs';
    if (is_dir($log_dir)) {
        haptic_filesystem()->rmdir($log_dir, true);
    }

    // Toujours : caches temporaires (verrous de connexion, notices, token de mise à jour…)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- suppression des transients par préfixe, qu'aucune fonction WordPress ne permet
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\\_transient\\_haptic\\_%'
            OR option_name LIKE '\\_transient\\_timeout\\_haptic\\_%'
            OR option_name LIKE '\\_transient\\_optimizr\\_%'
            OR option_name LIKE '\\_transient\\_timeout\\_optimizr\\_%'"
    );

    if ($choices['logs']) {
        foreach (haptic_uninstall_log_options() as $name) {
            delete_option($name);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- suppression, à la désinstallation, d'une table propre au plugin
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}optimizr_404");
        // Adresses IP déjà vues pour l'alerte de connexion (métadonnée propre à chaque compte, partagée sur un multisite)
        if (!is_multisite() || is_main_site()) {
            delete_metadata('user', 0, 'haptic_sec_known_ips', '', true);
        }
    }

    if ($choices['content']) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- suppression, à la désinstallation, d'une table propre au plugin
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}optimizr_redirects");
        foreach (['_haptic_noindex', '_haptic_og_title', '_haptic_og_desc', '_haptic_og_image'] as $meta_key) {
            delete_post_meta_by_key($meta_key);
        }
    }

    if ($choices['settings']) {
        // Toutes les options haptic_*, comme l'export (functions/tools.php), sauf les journaux si on les garde
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- aucune fonction WordPress ne liste les options par préfixe ; lecture unique à la désinstallation
        $names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'haptic\\_%'");
        $keep = $choices['logs'] ? [] : haptic_uninstall_log_options();
        foreach ($names as $name) {
            if (!in_array($name, $keep, true)) {
                delete_option($name);
            }
        }
        // Double authentification et passkeys d'Optimizr Pro (métadonnées des comptes, partagées sur un multisite)
        if (!is_multisite() || is_main_site()) {
            foreach (['haptic_2fa', 'haptic_2fa_pending', 'haptic_2fa_email', 'haptic_2fa_change', 'haptic_2fa_fails', 'haptic_2fa_mails', 'haptic_2fa_login', 'haptic_sec_welcome_until', 'haptic_passkeys', 'haptic_passkey_handle'] as $meta_key) {
                delete_metadata('user', 0, $meta_key, '', true);
            }
        }
    }
}
