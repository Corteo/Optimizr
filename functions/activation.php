<?php

if (!defined('ABSPATH')) {
    exit;
}

function hpt_options_activate()
{
    hpt_redirects_install();
    // Le fichier de clé IndexNow, supprimé à la désactivation, est republié
    if (function_exists('hpt_indexnow_setup') && get_option('hpt_seo_indexnow')) {
        hpt_indexnow_setup(true);
    }
    add_option('hpt_debug_mode', false);
    add_option('hpt_dev_mode', false);
    // Première activation : l'assistant de configuration s'ouvre (WordPress confirme déjà l'activation)
    hpt_onboarding_schedule_redirect();
}

function hpt_options_deactivate()
{
    hpt_sitemap_delete_files();
    hpt_robots_txt_delete_file();

    // Fichier de clé IndexNow à la racine du site
    $key = get_option('hpt_indexnow_key');
    if ($key && is_file(ABSPATH . $key . '.txt')) {
        @unlink(ABSPATH . $key . '.txt');
    }

    // Règle « pas de PHP dans les médias » du module Sécurité
    $htaccess = trailingslashit(wp_upload_dir()['basedir']) . '.htaccess';
    if (is_writable($htaccess)) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        insert_with_markers($htaccess, 'Optimizr', []);
    }
}

/* ---------- Tables du module Redirections (créées même si le module est activé plus tard) ---------- */

function hpt_redirects_table()
{
    global $wpdb;
    return $wpdb->prefix . 'optimizr_redirects';
}

function hpt_404_table()
{
    global $wpdb;
    return $wpdb->prefix . 'optimizr_404';
}

/** Création ou mise à jour des tables (activation et migration). */
function hpt_redirects_install()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE " . hpt_redirects_table() . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        source varchar(255) NOT NULL,
        target text NOT NULL,
        code smallint(3) unsigned NOT NULL DEFAULT 301,
        hits bigint(20) unsigned NOT NULL DEFAULT 0,
        last_hit datetime NULL DEFAULT NULL,
        note varchar(255) NOT NULL DEFAULT '',
        created datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY source (source(191))
    ) $charset;");

    dbDelta("CREATE TABLE " . hpt_404_table() . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        path varchar(255) NOT NULL,
        referrer varchar(255) NOT NULL DEFAULT '',
        hits bigint(20) unsigned NOT NULL DEFAULT 1,
        first_seen datetime NOT NULL,
        last_seen datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY path (path(191)),
        KEY last_seen (last_seen)
    ) $charset;");
}

// Activation du module depuis le tableau de bord : ses tables doivent exister avant la première visite
function hpt_redirects_maybe_install($new)
{
    if (is_array($new) && !empty($new['redirects'])) {
        hpt_redirects_install();
    }
}
add_action('update_option_hpt_modules', function ($old, $new) {
    hpt_redirects_maybe_install($new);
}, 10, 2);
// Premier enregistrement du tableau de bord ou de l'assistant : l'option est créée, pas modifiée
add_action('add_option_hpt_modules', function ($option, $value) {
    hpt_redirects_maybe_install($value);
}, 10, 2);

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

/** Supprime les fichiers de sitemap générés par Optimizr à la racine du site. */
function hpt_sitemap_delete_physical_files()
{
    $paths = array_merge([ABSPATH . 'sitemap.xml'], (array) glob(ABSPATH . 'sitemap-*.xml'));
    foreach ($paths as $path) {
        if (hpt_sitemap_is_ours($path)) {
            @unlink($path);
        }
    }
}

/**
 * Supprime les fichiers de sitemap générés, leur copie en cache et les tâches planifiées.
 * Appelé à la désactivation du plugin ou du module SEO : aucun sitemap périmé ne reste en ligne.
 */
function hpt_sitemap_delete_files()
{
    hpt_sitemap_delete_physical_files();
    delete_transient('hpt_sitemap_cache');

    wp_clear_scheduled_hook('haptic_GPT_daily_sitemap_cron');
    wp_clear_scheduled_hook('hpt_sitemap_regenerate');
}

// Désactivation du module SEO depuis le tableau de bord
add_action('update_option_hpt_modules', function ($old, $new) {
    if (is_array($new) && empty($new['seo'])) {
        hpt_sitemap_delete_files();
    }
}, 10, 2);
add_action('add_option_hpt_modules', function ($option, $value) {
    if (is_array($value) && empty($value['seo'])) {
        hpt_sitemap_delete_files();
    }
}, 10, 2);

/* ---------- robots.txt écrit par le bouton de l'onglet SEO ---------- */

/**
 * Le robots.txt à la racine est-il celui qu'Optimizr a écrit, sans modification depuis ?
 * (empreinte du contenu enregistrée à l'écriture)
 */
function hpt_robots_txt_is_ours()
{
    $hash = (string) get_option('hpt_robots_txt_hash', '');
    $path = ABSPATH . 'robots.txt';

    return $hash !== '' && is_file($path) && hash_equals($hash, md5((string) file_get_contents($path)));
}

/**
 * Supprime le robots.txt écrit par Optimizr, s'il n'a pas été modifié depuis.
 * Appelé à la désactivation et à la suppression du plugin : un fichier d'une autre origine est conservé.
 */
function hpt_robots_txt_delete_file()
{
    // Suppression impossible : l'empreinte est gardée pour un nouvel essai à la suppression du plugin
    if (hpt_robots_txt_is_ours() && !@unlink(ABSPATH . 'robots.txt')) {
        return;
    }

    delete_option('hpt_robots_txt_hash');
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

    if (version_compare($installed, '2.0.0-beta.11', '<')) {
        hpt_redirects_install();
    }

    if (version_compare($installed, '2.4.1', '<')) {
        // Journal des 404 : adresses enregistrées avec leur requête (« ?… »), qui pouvaient contenir des données personnelles
        global $wpdb;
        $wpdb->query('DELETE FROM ' . hpt_404_table() . " WHERE path LIKE '%?%'");

        // Ancien journal de debug public (logs/ du plugin), désormais dans uploads/optimizr-logs (cf. hpt_log_dir()) :
        // une mise à jour manuelle (FTP, git) le laisse en place
        @unlink(HPT_OPTIMIZR_PATH . 'logs/debug.log');
        @rmdir(HPT_OPTIMIZR_PATH . 'logs');
    }

    if (version_compare($installed, '2.4.1', '<') && is_multisite()) {
        // Ancien sitemap commun à tout le réseau : il masquerait celui de chaque site, désormais servi par WordPress
        hpt_sitemap_delete_physical_files();
    }

    if (version_compare($installed, '2.4.1', '<') && !is_multisite() && get_option('hpt_robots_txt_hash') === false) {
        // robots.txt écrit par le bouton d'une version précédente, sans empreinte : reconnu comme celui d'Optimizr
        // s'il est identique au réglage (inchangé depuis), comme ceux écrits désormais (cf. hpt_robots_txt_is_ours())
        $robots = (string) get_option('hpt_custom_robots_txt', '');
        if (trim($robots) !== '' && is_file(ABSPATH . 'robots.txt') && hash_equals(md5($robots), md5((string) file_get_contents(ABSPATH . 'robots.txt')))) {
            update_option('hpt_robots_txt_hash', md5($robots), false);
        }
    }

    update_option('hpt_optimizr_version', HPT_OPTIMIZR_VERSION);
}
// Sur init (tard) : types de contenu et shortcodes chargés, nécessaires pour comparer les extraits
add_action('init', 'hpt_options_maybe_upgrade', 99);

// Chemin sans « .. » : plugin_basename() ne le résout pas, et le hook ne correspondrait à aucune extension.
// dirname() plutôt que HPT_OPTIMIZR_PATH : uninstall.php charge ce fichier sans le fichier principal du plugin.
register_activation_hook(dirname(__DIR__) . '/hpt_options.php', 'hpt_options_activate');
register_deactivation_hook(dirname(__DIR__) . '/hpt_options.php', 'hpt_options_deactivate');
