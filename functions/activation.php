<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Accès direct aux fichiers par l'API Filesystem de WordPress (sitemap, robots.txt, clé IndexNow, journal).
 * Défini ici car ce fichier est chargé partout, uninstall.php compris.
 */
function haptic_filesystem()
{
    static $fs = null;
    if ($fs === null) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        $fs = new WP_Filesystem_Direct(null);
    }
    return $fs;
}

/**
 * Passage du préfixe « hpt_ » (versions 2.x) à « haptic_ » (3.0) : réglages, métadonnées et tâches planifiées
 * sont renommés sur place, une seule fois. Appelé au chargement du plugin, avant tout module (qui lirait
 * sinon des réglages vides), et par la suppression du plugin pour un site resté en 2.x.
 */
function haptic_migrate_legacy_prefix()
{
    global $wpdb;

    if (get_option('haptic_optimizr_version') !== false || get_option('hpt_optimizr_version') === false) {
        return;
    }

    // Options : renommées sans être réécrites (valeur et chargement automatique conservés).
    // Outils développeur retirés en 3.0 : leurs réglages disparaissent.
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name IN ('hpt_debug_mode', 'hpt_dev_mode')"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- migration ponctuelle, sans cache à utiliser
    $legacy = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'hpt\\_%'");
    foreach ($legacy as $name) {
        $new = 'haptic_' . substr($name, 4);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $new))) {
            $wpdb->delete($wpdb->options, ['option_name' => $name]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        } else {
            $wpdb->update($wpdb->options, ['option_name' => $new], ['option_name' => $name]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }
    // Caches temporaires (verrous de connexion, jeton…) : recréés au besoin sous le nouveau nom
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_hpt\\_%' OR option_name LIKE '\\_transient\\_timeout\\_hpt\\_%'");

    // Métadonnées des contenus (SEO) et des comptes (adresses IP connues)
    foreach (['noindex', 'og_title', 'og_desc', 'og_image'] as $key) {
        $wpdb->update($wpdb->postmeta, ['meta_key' => '_haptic_' . $key], ['meta_key' => '_hpt_' . $key]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery
    }
    if (!is_multisite() || is_main_site()) {
        $wpdb->update($wpdb->usermeta, ['meta_key' => 'haptic_sec_known_ips'], ['meta_key' => 'hpt_sec_known_ips']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery
    }

    // Tâches planifiées : même date, mêmes arguments, nouveau nom de hook
    $crons = _get_cron_array();
    if (is_array($crons)) {
        foreach ($crons as $timestamp => $hooks) {
            foreach ((array) $hooks as $hook => $events) {
                if (strpos($hook, 'hpt_') === 0) {
                    $new = 'haptic_' . substr($hook, 4);
                } elseif (strpos($hook, 'haptic_GPT_') === 0) {
                    $new = 'haptic_' . substr($hook, 11);
                } else {
                    continue;
                }
                foreach ($events as $key => $event) {
                    $crons[$timestamp][$new][$key] = $event;
                }
                unset($crons[$timestamp][$hook]);
            }
        }
        _set_cron_array($crons);
    }

    wp_cache_flush();
}
haptic_migrate_legacy_prefix();

function haptic_options_activate()
{
    haptic_redirects_install();
    // Le fichier de clé IndexNow, supprimé à la désactivation, est republié
    if (function_exists('haptic_indexnow_setup') && get_option('haptic_seo_indexnow')) {
        haptic_indexnow_setup(true);
    }
    // Première activation : l'assistant de configuration s'ouvre (WordPress confirme déjà l'activation)
    haptic_onboarding_schedule_redirect();
}

function haptic_options_deactivate()
{
    haptic_sitemap_delete_files();
    haptic_robots_txt_delete_file();

    // Fichier de clé IndexNow à la racine du site
    $key = get_option('haptic_indexnow_key');
    if ($key && is_file(ABSPATH . $key . '.txt')) {
        wp_delete_file(ABSPATH . $key . '.txt');
    }

    // Règle « pas de PHP dans les médias » du module Sécurité
    $htaccess = trailingslashit(wp_upload_dir()['basedir']) . '.htaccess';
    if (wp_is_writable($htaccess)) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        insert_with_markers($htaccess, 'Optimizr', []);
    }
}

/* ---------- Tables du module Redirections (créées même si le module est activé plus tard) ---------- */

function haptic_redirects_table()
{
    global $wpdb;
    return $wpdb->prefix . 'optimizr_redirects';
}

function haptic_404_table()
{
    global $wpdb;
    return $wpdb->prefix . 'optimizr_404';
}

/** Création ou mise à jour des tables (activation et migration). */
function haptic_redirects_install()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE " . haptic_redirects_table() . " (
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

    dbDelta("CREATE TABLE " . haptic_404_table() . " (
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
function haptic_redirects_maybe_install($new)
{
    if (is_array($new) && !empty($new['redirects'])) {
        haptic_redirects_install();
    }
}
add_action('update_option_haptic_modules', function ($old, $new) {
    haptic_redirects_maybe_install($new);
}, 10, 2);
// Premier enregistrement du tableau de bord ou de l'assistant : l'option est créée, pas modifiée
add_action('add_option_haptic_modules', function ($option, $value) {
    haptic_redirects_maybe_install($value);
}, 10, 2);

/** Marqueur présent dans chaque fichier de sitemap généré par Optimizr. */
define('HAPTIC_SITEMAP_MARKER', '<!-- Généré par Optimizr -->');

/**
 * Le fichier a-t-il été généré par Optimizr ? (évite de supprimer un sitemap d'une autre origine)
 */
function haptic_sitemap_is_ours($path)
{
    if (!is_file($path)) {
        return false;
    }
    $head = (string) file_get_contents($path, false, null, 0, 300);
    return strpos($head, HAPTIC_SITEMAP_MARKER) !== false;
}

/** Supprime les fichiers de sitemap générés par Optimizr à la racine du site. */
function haptic_sitemap_delete_physical_files()
{
    $paths = array_merge([ABSPATH . 'sitemap.xml'], (array) glob(ABSPATH . 'sitemap-*.xml'));
    foreach ($paths as $path) {
        if (haptic_sitemap_is_ours($path)) {
            wp_delete_file($path);
        }
    }
}

/**
 * Supprime les fichiers de sitemap générés, leur copie en cache et les tâches planifiées.
 * Appelé à la désactivation du plugin ou du module SEO : aucun sitemap périmé ne reste en ligne.
 */
function haptic_sitemap_delete_files()
{
    haptic_sitemap_delete_physical_files();
    delete_transient('haptic_sitemap_cache');

    wp_clear_scheduled_hook('haptic_daily_sitemap_cron');
    wp_clear_scheduled_hook('haptic_sitemap_regenerate');
}

// Désactivation du module SEO depuis le tableau de bord
add_action('update_option_haptic_modules', function ($old, $new) {
    if (is_array($new) && empty($new['seo'])) {
        haptic_sitemap_delete_files();
    }
}, 10, 2);
add_action('add_option_haptic_modules', function ($option, $value) {
    if (is_array($value) && empty($value['seo'])) {
        haptic_sitemap_delete_files();
    }
}, 10, 2);

/* ---------- robots.txt écrit par le bouton de l'onglet SEO ---------- */

/**
 * Le robots.txt à la racine est-il celui qu'Optimizr a écrit, sans modification depuis ?
 * (empreinte du contenu enregistrée à l'écriture)
 */
function haptic_robots_txt_is_ours()
{
    $hash = (string) get_option('haptic_robots_txt_hash', '');
    $path = ABSPATH . 'robots.txt';

    return $hash !== '' && is_file($path) && hash_equals($hash, md5((string) file_get_contents($path)));
}

/**
 * Supprime le robots.txt écrit par Optimizr, s'il n'a pas été modifié depuis.
 * Appelé à la désactivation et à la suppression du plugin : un fichier d'une autre origine est conservé.
 */
function haptic_robots_txt_delete_file()
{
    // Suppression impossible : l'empreinte est gardée pour un nouvel essai à la suppression du plugin
    if (haptic_robots_txt_is_ours()) {
        wp_delete_file(ABSPATH . 'robots.txt');
        if (file_exists(ABSPATH . 'robots.txt')) {
            return;
        }
    }

    delete_option('haptic_robots_txt_hash');
}

/**
 * Migrations exécutées une seule fois après une mise à jour du plugin.
 */
function haptic_options_maybe_upgrade()
{
    $installed = get_option('haptic_optimizr_version', '0');

    if (version_compare($installed, HAPTIC_OPTIMIZR_VERSION, '>=')) {
        return;
    }

    if (version_compare($installed, '2.0', '<')) {
        // Avant la 2.0, ces cases n'avaient aucun effet : les balises étaient toujours affichées
        update_option('haptic_enable_seo', 1);
        update_option('haptic_enable_opengraph', 1);
    }

    if (version_compare($installed, '2.0.0-beta.3', '<') && function_exists('haptic_seo_cleanup_saved_meta')) {
        haptic_seo_cleanup_saved_meta();
    }

    if (version_compare($installed, '2.0.0-beta.4', '<')) {
        // Ancien journal public du sitemap, remplacé par un historique en base
        wp_delete_file(HAPTIC_OPTIMIZR_PATH . 'functions/seo/sitemap.log');

        // Ancien plan du site HTML statique, remplacé par le shortcode [optimizr_plan_du_site]
        $html = ABSPATH . 'sitemap.html';
        if (is_file($html)) {
            $content = (string) file_get_contents($html, false, null, 0, 2000);
            if (strpos($content, '<title>Plan du site</title>') !== false && strpos($content, 'bootstrap.min.css') !== false) {
                wp_delete_file($html);
            }
        }

        // Sitemap régénéré avec les nouvelles règles (exclusions, page d'accueil)
        if (function_exists('haptic_write_sitemap_to_file')) {
            haptic_write_sitemap_to_file(_x('plugin update', 'sitemap generation trigger', 'optimizr'));
        }
    }

    if (version_compare($installed, '2.0.0-beta.11', '<')) {
        haptic_redirects_install();
    }

    if (version_compare($installed, '2.4.1', '<')) {
        // Journal des 404 : adresses enregistrées avec leur requête (« ?… »), qui pouvaient contenir des données personnelles
        global $wpdb;
        $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE path LIKE %s', haptic_404_table(), '%?%'));

        // Ancien journal de debug public (logs/ du plugin), supprimé depuis :
        // une mise à jour manuelle (FTP, git) le laisse en place
        wp_delete_file(HAPTIC_OPTIMIZR_PATH . 'logs/debug.log');
        haptic_filesystem()->rmdir(HAPTIC_OPTIMIZR_PATH . 'logs');
    }

    if (version_compare($installed, '2.4.1', '<') && is_multisite()) {
        // Ancien sitemap commun à tout le réseau : il masquerait celui de chaque site, désormais servi par WordPress
        haptic_sitemap_delete_physical_files();
    }

    if (version_compare($installed, '2.4.1', '<') && !is_multisite() && get_option('haptic_robots_txt_hash') === false) {
        // robots.txt écrit par le bouton d'une version précédente, sans empreinte : reconnu comme celui d'Optimizr
        // s'il est identique au réglage (inchangé depuis), comme ceux écrits désormais (cf. haptic_robots_txt_is_ours())
        $robots = (string) get_option('haptic_custom_robots_txt', '');
        if (trim($robots) !== '' && is_file(ABSPATH . 'robots.txt') && hash_equals(md5($robots), md5((string) file_get_contents(ABSPATH . 'robots.txt')))) {
            update_option('haptic_robots_txt_hash', md5($robots), false);
        }
    }

    if (version_compare($installed, '3.0.0', '<')) {
        // Journal de debug des outils développeur, retirés en 3.0
        $log_dir = trailingslashit(wp_upload_dir(null, false)['basedir']) . 'optimizr-logs';
        if (is_dir($log_dir)) {
            haptic_filesystem()->rmdir($log_dir, true);
        }
    }

    update_option('haptic_optimizr_version', HAPTIC_OPTIMIZR_VERSION);
}
// Sur init (tard) : types de contenu et shortcodes chargés, nécessaires pour comparer les extraits
add_action('init', 'haptic_options_maybe_upgrade', 99);

// Chemin sans « .. » : plugin_basename() ne le résout pas, et le hook ne correspondrait à aucune extension.
// dirname() plutôt que HAPTIC_OPTIMIZR_FILE : uninstall.php charge ce fichier sans le fichier principal du plugin.
register_activation_hook(dirname(__DIR__) . '/optimizr.php', 'haptic_options_activate');
register_deactivation_hook(dirname(__DIR__) . '/optimizr.php', 'haptic_options_deactivate');
