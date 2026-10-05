<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Suppression du plugin : ce qui est effacé de la base, selon les choix enregistrés (option hpt_uninstall).
 * Chargé par le plugin (choix) et par uninstall.php (nettoyage) : n'utilise aucune autre fonction d'Optimizr.
 */

/**
 * Données facultatives, effacées seulement si la personne l'a demandé.
 * Tout ce qui n'est pas ici (tâches planifiées, caches, fichiers générés) est toujours supprimé.
 *
 * @return array clé => [label, help]
 */
function hpt_uninstall_groups()
{
    return [
        'settings' => [
            'label' => 'Réglages',
            'help'  => 'Modules, motifs SEO, services cookies, clés API, sécurité… Conservés, ils seront retrouvés à la réinstallation.',
        ],
        'content'  => [
            'label' => 'Redirections et SEO des contenus',
            'help'  => 'Les redirections, et les titres, descriptions, images et « ne pas indexer » saisis sur chaque contenu.',
        ],
        'logs'     => [
            'label' => 'Journaux et historiques',
            'help'  => 'Pages introuvables, journal de sécurité, historique du sitemap, consommation de l\'IA, analyse des liens.',
        ],
    ];
}

/**
 * Choix enregistrés : par défaut, rien n'est effacé.
 *
 * @return array clé => bool
 */
function hpt_uninstall_choices()
{
    $saved = get_option('hpt_uninstall', []);
    $choices = [];
    foreach (array_keys(hpt_uninstall_groups()) as $key) {
        $choices[$key] = is_array($saved) && !empty($saved[$key]);
    }
    return $choices;
}

/** Nettoie une saisie (formulaire ou AJAX) : seules les clés connues, en booléens. */
function hpt_uninstall_sanitize($value)
{
    $value = is_array($value) ? $value : [];
    $clean = [];
    foreach (array_keys(hpt_uninstall_groups()) as $key) {
        $clean[$key] = !empty($value[$key]) ? 1 : 0;
    }
    return $clean;
}

/** Options qui sont des journaux ou des historiques, pas des réglages. */
function hpt_uninstall_log_options()
{
    return [
        'hpt_security_log',
        'hpt_sitemap_history',
        'hpt_sitemap_error',
        'hpt_sitemap_last_generated',
        'hpt_ai_usage',
        'hpt_links_report',
        'hpt_links_queue',
        'hpt_indexnow_last',
    ];
}

/**
 * Nettoyage d'un site. Appelé depuis uninstall.php, une fois par site sur un multisite.
 */
function hpt_uninstall_site()
{
    global $wpdb;

    $choices = hpt_uninstall_choices();

    // Toujours : fichiers générés (sitemap, clé IndexNow, robots.txt écrit par Optimizr et inchangé, règle .htaccess) et tâches planifiées.
    // Avant de toucher aux options : la clé IndexNow est lue dans hpt_indexnow_key.
    if (function_exists('hpt_options_deactivate')) {
        hpt_options_deactivate();
    }
    wp_clear_scheduled_hook('haptic_GPT_daily_sitemap_cron');
    wp_clear_scheduled_hook('hpt_sitemap_regenerate');
    // ALT automatique en attente : une tâche par image (identifiant en argument), que wp_clear_scheduled_hook() sans argument ne retire pas
    wp_unschedule_hook('hpt_ai_alt_on_upload');
    // Empreinte du robots.txt écrit par Optimizr : gardée si le fichier n'a pas pu être supprimé, inutile désormais
    delete_option('hpt_robots_txt_hash');

    // Toujours : journal de debug (uploads/optimizr-logs, cf. hpt_log_dir()), autrefois effacé avec le dossier du plugin
    $log_dir = trailingslashit(wp_upload_dir(null, false)['basedir']) . 'optimizr-logs';
    if (is_dir($log_dir)) {
        hpt_filesystem()->rmdir($log_dir, true);
    }

    // Toujours : caches temporaires (verrous de connexion, notices, token de mise à jour…)
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\\_transient\\_hpt\\_%'
            OR option_name LIKE '\\_transient\\_timeout\\_hpt\\_%'
            OR option_name LIKE '\\_transient\\_optimizr\\_%'
            OR option_name LIKE '\\_transient\\_timeout\\_optimizr\\_%'"
    );

    if ($choices['logs']) {
        foreach (hpt_uninstall_log_options() as $name) {
            delete_option($name);
        }
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}optimizr_404");
        // Adresses IP déjà vues pour l'alerte de connexion (métadonnée propre à chaque compte, partagée sur un multisite)
        if (!is_multisite() || is_main_site()) {
            delete_metadata('user', 0, 'hpt_sec_known_ips', '', true);
        }
    }

    if ($choices['content']) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}optimizr_redirects");
        foreach (['_hpt_noindex', '_hpt_og_title', '_hpt_og_desc', '_hpt_og_image'] as $meta_key) {
            delete_post_meta_by_key($meta_key);
        }
    }

    if ($choices['settings']) {
        // Toutes les options hpt_*, comme l'export (functions/tools.php), sauf les journaux si on les garde
        $names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'hpt\\_%'");
        $keep = $choices['logs'] ? [] : hpt_uninstall_log_options();
        foreach ($names as $name) {
            if (!in_array($name, $keep, true)) {
                delete_option($name);
            }
        }
    }
}
