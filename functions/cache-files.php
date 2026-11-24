<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Cache : fichiers écrits hors du plugin (wp-content/advanced-cache.php, constante WP_CACHE de wp-config.php,
 * réglages et pages dans wp-content/cache/optimizr, règles du .htaccess). Toujours chargé, même module désactivé :
 * la désactivation du module, du plugin et sa suppression retirent ces fichiers.
 */

require_once __DIR__ . '/cache-serve.php';

/** Repère du fichier advanced-cache.php écrit par Optimizr. */
define('HAPTIC_CACHE_DROPIN_MARKER', 'OPTIMIZR_CACHE_DROPIN');
/** Repère de la ligne ajoutée à wp-config.php. */
define('HAPTIC_CACHE_WP_CONFIG_LINE', "define('WP_CACHE', true); // Added by Optimizr (page cache)");

/**
 * Extensions de cache actives, par nom affiché. Leur propre cache de pages entrerait en conflit avec celui d'Optimizr.
 *
 * @return string[]
 */
function haptic_cache_conflicting_plugins()
{
    $plugins = [
        'WP Rocket'              => defined('WP_ROCKET_VERSION'),
        'W3 Total Cache'         => defined('W3TC'),
        'WP Super Cache'         => defined('WPCACHEHOME') || function_exists('wp_cache_phase2'),
        'LiteSpeed Cache'        => defined('LSCWP_V'),
        'WP Fastest Cache'       => class_exists('WpFastestCache', false),
        'Cache Enabler'          => class_exists('Cache_Enabler', false),
        'Hummingbird'            => defined('WPHB_VERSION'),
        'SiteGround Speed Optimizer' => function_exists('sg_cachepress_purge_cache'),
        'Breeze'                 => defined('BREEZE_VERSION'),
        'WP-Optimize'            => defined('WPO_VERSION'),
        'FlyingPress'            => defined('FLYING_PRESS_VERSION'),
        'Swift Performance'      => class_exists('Swift_Performance', false),
        'Comet Cache'            => defined('COMET_CACHE_PLUGIN_FILE'),
    ];

    return array_keys(array_filter(apply_filters('haptic_cache_conflicting_plugins', $plugins)));
}

/**
 * Cache de pages de l'hébergeur (ou proxy de cache devant le site), par nom affiché, ou ''.
 * Il ne bloque pas le cache d'Optimizr, mais le rend en général inutile.
 */
function haptic_cache_host_cache()
{
    $hosts = [
        'WP Engine'   => class_exists('WpeCommon', false),
        'Kinsta'      => defined('KINSTAMU_VERSION'),
        'Pantheon'    => defined('PANTHEON_ENVIRONMENT') || isset($_ENV['PANTHEON_ENVIRONMENT']),
        'GoDaddy'     => class_exists('WPaaS\Plugin', false),
        'Pressable'   => defined('IS_PRESSABLE'),
        'Varnish'     => isset($_SERVER['HTTP_X_VARNISH']),
    ];

    $found = array_keys(array_filter(apply_filters('haptic_cache_host_caches', $hosts)));
    return $found ? $found[0] : '';
}

/** Un fichier advanced-cache.php d'une autre extension est-il en place ? */
function haptic_cache_foreign_dropin()
{
    $file = WP_CONTENT_DIR . '/advanced-cache.php';
    return is_file($file) && strpos((string) file_get_contents($file), HAPTIC_CACHE_DROPIN_MARKER) === false;
}

/**
 * Raison pour laquelle le cache de pages ne peut pas être activé, ou '' s'il le peut.
 * 'plugin' (extension de cache active), 'dropin' (advanced-cache.php d'une autre extension).
 */
function haptic_cache_blocker()
{
    if (haptic_cache_conflicting_plugins()) {
        return 'plugin';
    }
    if (haptic_cache_foreign_dropin()) {
        return 'dropin';
    }
    return '';
}

/** Hôte du site tel que le navigateur l'envoie (avec le port s'il y en a un). */
function haptic_cache_site_host()
{
    $parts = wp_parse_url(home_url());
    $host = strtolower($parts['host'] ?? '');
    return $host !== '' && !empty($parts['port']) ? $host . ':' . $parts['port'] : $host;
}

/** Chemin de l'adresse du site, entre deux barres : « / », ou « /site2/ » sur un réseau en sous-dossiers. */
function haptic_cache_site_path()
{
    $path = trim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    return $path === '' ? '/' : '/' . $path . '/';
}

/**
 * Nom du dossier des pages de ce site, sous wp-content/cache/optimizr/pages : son hôte, suivi du numéro du site
 * sur un multisite (deux sites d'un réseau en sous-dossiers ont le même hôte).
 */
function haptic_cache_pages_dir_name()
{
    $name = str_replace(':', '_', haptic_cache_site_host());
    return is_multisite() ? $name . '--' . get_current_blog_id() : $name;
}

/** Dossier des pages en cache de ce site. */
function haptic_cache_pages_dir()
{
    return haptic_cache_base_dir() . '/pages/' . haptic_cache_pages_dir_name();
}

/** Fichier des réglages du cache de ce site : config.php, ou un fichier par site sur un multisite. '' si l'hôte est inutilisable. */
function haptic_cache_config_path()
{
    if (!is_multisite()) {
        return haptic_cache_base_dir() . '/config.php';
    }
    return haptic_cache_site_config_file(haptic_cache_site_host(), haptic_cache_site_path());
}

/** Multisite : un site du réseau au moins utilise-t-il le cache de pages (fichier de réglages en place) ? */
function haptic_cache_network_in_use()
{
    return (bool) glob(haptic_cache_base_dir() . '/sites/*/*.php');
}

/** Le fichier advanced-cache.php d'Optimizr est-il en place ? */
function haptic_cache_dropin_is_ours()
{
    $file = WP_CONTENT_DIR . '/advanced-cache.php';
    return is_file($file) && strpos((string) file_get_contents($file), HAPTIC_CACHE_DROPIN_MARKER) !== false;
}

/**
 * Multisite : ce site peut-il mettre en place les fichiers communs du cache de pages (advanced-cache.php, WP_CACHE) ?
 * Oui s'ils y sont déjà ; sinon il faut un administrateur du réseau, seul à décider de ce qui touche tous les sites.
 */
function haptic_cache_network_allowed()
{
    if (!is_multisite() || (haptic_cache_dropin_is_ours() && defined('WP_CACHE') && WP_CACHE)) {
        return true;
    }
    return current_user_can('manage_network_options');
}

/** Cookies qui signalent une page personnalisée, jamais servie depuis le cache. */
function haptic_cache_bypass_cookies()
{
    return apply_filters('haptic_cache_bypass_cookies', [
        'wordpress_logged_in_',
        'wp-postpass_',
        'comment_author_',
        'woocommerce_items_in_cart',
        'woocommerce_cart_hash',
        'wp_woocommerce_session_',
        'edd_items_in_cart',
    ]);
}

/** Motifs d'adresses exclues, une par ligne dans le réglage (chemins commençant par /). */
function haptic_cache_exclusions()
{
    $lines = preg_split('/\R/', (string) get_option('haptic_cache_exclude', ''));
    $patterns = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        // Adresse complète collée : seul le chemin compte
        if (preg_match('#^https?://#i', $line)) {
            $line = (string) wp_parse_url($line, PHP_URL_PATH);
        }
        $patterns[] = '/' . ltrim($line, '/');
    }
    return array_values(array_unique($patterns));
}

/** Durée de vie des pages en cache, en heures. */
function haptic_cache_ttl_hours()
{
    $hours = (int) get_option('haptic_cache_ttl', 10);
    return $hours >= 1 && $hours <= 720 ? $hours : 10;
}

/** Le cache de pages est-il demandé (module actif et réglage activé) ? */
function haptic_cache_page_wanted()
{
    return haptic_is_module_active('cache') && get_option('haptic_cache_page');
}

/** Les règles de cache navigateur sont-elles demandées ? */
function haptic_cache_browser_wanted()
{
    return haptic_is_module_active('cache') && get_option('haptic_cache_browser');
}

/** Serveur qui lit le .htaccess (Apache, LiteSpeed). */
function haptic_cache_server_reads_htaccess()
{
    global $is_apache;
    return !empty($is_apache);
}

function haptic_cache_htaccess_path()
{
    require_once ABSPATH . 'wp-admin/includes/file.php';
    return get_home_path() . '.htaccess';
}

/** Règles de cache navigateur et de compression (Apache, LiteSpeed). */
function haptic_cache_htaccess_rules($gzip = true)
{
    $lines = [
        '<IfModule mod_expires.c>',
        'ExpiresActive On',
    ];
    foreach (['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon',
        'font/woff2', 'font/woff', 'font/ttf', 'font/otf', 'application/font-woff', 'application/font-woff2', 'video/mp4', 'video/webm'] as $type) {
        $lines[] = 'ExpiresByType ' . $type . ' "access plus 1 year"';
    }
    foreach (['text/css', 'application/javascript', 'text/javascript', 'application/x-javascript'] as $type) {
        $lines[] = 'ExpiresByType ' . $type . ' "access plus 1 month"';
    }
    $lines = array_merge($lines, [
        '</IfModule>',
        '<IfModule mod_headers.c>',
        '<FilesMatch "\.(avif|webp|jpe?g|png|gif|svg|ico|woff2?|ttf|otf|eot|mp4|webm)$">',
        'Header set Cache-Control "public, max-age=31536000"',
        '</FilesMatch>',
        '<FilesMatch "\.(css|js)$">',
        'Header set Cache-Control "public, max-age=2592000"',
        '</FilesMatch>',
        '</IfModule>',
    ]);

    if ($gzip) {
        $lines = array_merge($lines, [
            '<IfModule mod_deflate.c>',
            'AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml application/xml application/xhtml+xml application/rss+xml application/javascript text/javascript application/json image/svg+xml font/ttf font/otf',
            '</IfModule>',
        ]);
    }

    return $lines;
}

/** Mêmes règles pour Nginx, à copier dans la configuration du serveur (qui ne lit pas le .htaccess). */
function haptic_cache_nginx_rules($gzip = true)
{
    $rules = "location ~* \\.(avif|webp|jpe?g|png|gif|svg|ico|woff2?|ttf|otf|eot|mp4|webm)$ {\n    expires 1y;\n    add_header Cache-Control \"public\";\n}\nlocation ~* \\.(css|js)$ {\n    expires 1M;\n    add_header Cache-Control \"public\";\n}";
    if ($gzip) {
        $rules .= "\ngzip on;\ngzip_types text/plain text/css text/xml application/xml application/rss+xml application/javascript text/javascript application/json image/svg+xml;";
    }
    return $rules;
}

/** Écrit (ou retire, avec []) le bloc « Optimizr Cache » du .htaccess. */
function haptic_cache_write_htaccess(array $lines)
{
    $file = haptic_cache_htaccess_path();
    if (!$lines && (!is_file($file) || strpos((string) file_get_contents($file), '# BEGIN Optimizr Cache') === false)) {
        return true;
    }
    if (!(is_file($file) ? wp_is_writable($file) : wp_is_writable(dirname($file)))) {
        return false;
    }
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    return (bool) insert_with_markers($file, 'Optimizr Cache', $lines);
}

/** Chemin de wp-config.php (à la racine de WordPress ou un dossier au-dessus), ou ''. */
function haptic_cache_wp_config_path()
{
    if (is_file(ABSPATH . 'wp-config.php')) {
        return ABSPATH . 'wp-config.php';
    }
    $parent = dirname(ABSPATH) . '/wp-config.php';
    return (is_file($parent) && !is_file(dirname(ABSPATH) . '/wp-settings.php')) ? $parent : '';
}

/**
 * Ajoute ou retire define('WP_CACHE', true) dans wp-config.php. Seule la ligne écrite par Optimizr est retirée.
 *
 * @return bool La constante est (ou n'est plus) en place comme demandé.
 */
function haptic_cache_set_wp_cache($enable)
{
    $file = haptic_cache_wp_config_path();
    $content = $file !== '' ? (string) file_get_contents($file) : '';
    $ours = strpos($content, HAPTIC_CACHE_WP_CONFIG_LINE) !== false;

    if ($enable) {
        if ($ours || (defined('WP_CACHE') && WP_CACHE)) {
            return true;
        }
        // Déjà définie (à false) par quelqu'un d'autre, ou fichier inaccessible : à faire à la main
        if ($content === '' || preg_match('/define\s*\(\s*[\'"]WP_CACHE[\'"]/', $content) || !wp_is_writable($file)) {
            return false;
        }
        $new = preg_replace('/^<\?php[^\S\r\n]*\R/', "<?php\n" . HAPTIC_CACHE_WP_CONFIG_LINE . "\n", $content, 1, $count);
    } else {
        if (!$ours) {
            return true;
        }
        if (!wp_is_writable($file)) {
            return false;
        }
        $new = preg_replace('/^' . preg_quote(HAPTIC_CACHE_WP_CONFIG_LINE, '/') . '\R/m', '', $content, -1, $count);
    }

    // Garde-fou : seule la ligne d'Optimizr a été ajoutée ou retirée
    if (empty($count) || !is_string($new) || strpos($new, '<?php') !== 0
        || abs(strlen($new) - strlen($content)) > (strlen(HAPTIC_CACHE_WP_CONFIG_LINE) + 2) * $count) {
        return false;
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- écriture atomique (LOCK_EX) de wp-config.php, que WP_Filesystem ne propose pas
    $written = file_put_contents($file, $new, LOCK_EX) === strlen($new);
    haptic_cache_opcache_invalidate($file);
    return $written;
}

/** Écrit wp-content/advanced-cache.php, qui charge functions/cache-serve.php depuis le dossier du plugin. */
function haptic_cache_write_dropin()
{
    $file = WP_CONTENT_DIR . '/advanced-cache.php';
    if (haptic_cache_foreign_dropin()) {
        return false;
    }

    $content = "<?php\n"
        . "/*\n * " . HAPTIC_CACHE_DROPIN_MARKER . " — page cache of the Optimizr plugin.\n"
        . " * Written by Optimizr, deleted when its page cache is turned off. Do not edit.\n */\n"
        . "if (!defined('ABSPATH')) {\n    exit;\n}\n"
        . '$haptic_cache_serve = ' . var_export(HAPTIC_OPTIMIZR_PATH . 'functions/cache-serve.php', true) . ";\n" // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- écriture d'une chaîne PHP valide
        . "if (is_file(\$haptic_cache_serve)) {\n    include_once \$haptic_cache_serve;\n    if (function_exists('haptic_cache_serve')) {\n        haptic_cache_serve();\n    }\n}\n"
        . "unset(\$haptic_cache_serve);\n";

    if (is_file($file) && file_get_contents($file) === $content) {
        return true;
    }
    if (!(is_file($file) ? wp_is_writable($file) : wp_is_writable(WP_CONTENT_DIR))) {
        return false;
    }
    return haptic_cache_put_php($file, $content);
}

function haptic_cache_delete_dropin()
{
    $file = WP_CONTENT_DIR . '/advanced-cache.php';
    if (is_file($file) && !haptic_cache_foreign_dropin()) {
        wp_delete_file($file);
        haptic_cache_opcache_invalidate($file);
    }
}

/** Écrit les réglages lus par advanced-cache.php (avant WordPress, d'où un fichier PHP). */
function haptic_cache_write_config()
{
    $file = haptic_cache_config_path();
    if ($file === '') {
        return false;
    }

    $config = [
        'enabled'      => true,
        'host'         => haptic_cache_site_host(),
        // Multisite : chemin du site (« / », « /site2/ ») et dossier de ses pages
        'path'         => is_multisite() ? haptic_cache_site_path() : '',
        'dir'          => haptic_cache_pages_dir_name(),
        'ttl'          => haptic_cache_ttl_hours() * HOUR_IN_SECONDS,
        'mobile'       => (bool) get_option('haptic_cache_mobile'),
        'exclude'      => haptic_cache_exclusions(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- adresses exclues du cache, pas une requête get_posts()
        'cookies'      => array_values(haptic_cache_bypass_cookies()),
        'ignore_query' => array_values(apply_filters('haptic_cache_ignored_query_args', ['gclid', 'fbclid', 'msclkid', 'dclid', 'gbraid', 'wbraid', '_ga', '_gl', 'mc_cid', 'mc_eid', 'ref'])),
    ];

    if (!wp_mkdir_p(dirname($file))) {
        return false;
    }
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- écriture d'un tableau PHP valide
    $content = "<?php\nif (!defined('ABSPATH')) {\n    exit;\n}\n// Page cache settings written by Optimizr.\nreturn " . var_export($config, true) . ";\n";
    return haptic_cache_put_php($file, $content);
}

/** Écrit un fichier PHP et le retire d'OPcache : sans cela, l'ancienne version peut rester servie (validate_timestamps à 0). */
function haptic_cache_put_php($file, $content)
{
    if (!haptic_filesystem()->put_contents($file, $content, 0644)) {
        return false;
    }
    haptic_cache_opcache_invalidate($file);
    return true;
}

function haptic_cache_opcache_invalidate($file)
{
    if (function_exists('opcache_invalidate') && (!ini_get('opcache.restrict_api') || stripos(__FILE__, (string) ini_get('opcache.restrict_api')) === 0)) {
        opcache_invalidate($file, true);
    }
}

/** Supprime un dossier du cache et son contenu (uniquement sous wp-content/cache/optimizr). */
function haptic_cache_delete_dir($dir)
{
    $base = haptic_cache_base_dir();
    if (strpos($dir, $base) !== 0 || strpos($dir, '..') !== false || !is_dir($dir)) {
        return;
    }
    haptic_filesystem()->rmdir($dir, true);
}

/**
 * Met les fichiers en accord avec les réglages : appelé après leur enregistrement, l'activation du plugin,
 * l'import des réglages et le changement d'adresse du site. L'état obtenu est gardé dans haptic_cache_state.
 */
function haptic_cache_sync()
{
    $state = ['page' => false, 'browser' => false, 'error' => '', 'browser_error' => '', 'version' => HAPTIC_OPTIMIZR_VERSION, 'path' => HAPTIC_OPTIMIZR_PATH,
        'apache' => haptic_cache_server_reads_htaccess()];

    // Adresse du site changée depuis la dernière synchronisation (multisite) : les réglages et les pages
    // écrits sous l'ancienne adresse sont retirés
    $previous = get_option('haptic_cache_state');
    $state['config'] = (string) haptic_cache_config_path();
    $state['dir'] = haptic_cache_pages_dir();
    if (is_multisite() && is_array($previous)) {
        if (!empty($previous['config']) && $previous['config'] !== $state['config']) {
            haptic_cache_delete_config($previous['config']);
        }
        if (!empty($previous['dir']) && $previous['dir'] !== $state['dir']) {
            haptic_cache_delete_dir($previous['dir']);
        }
    }

    if (haptic_cache_page_wanted()) {
        $blocker = haptic_cache_blocker();
        if ($blocker !== '') {
            $state['error'] = $blocker;
        } elseif (!haptic_cache_network_allowed()) {
            // Multisite : advanced-cache.php et WP_CACHE pas encore en place, et ce compte n'administre pas le réseau
            $state['error'] = 'network';
        } elseif (!haptic_cache_write_config() || !haptic_cache_write_dropin()) {
            $state['error'] = 'files';
        } elseif (!haptic_cache_set_wp_cache(true)) {
            $state['error'] = 'wp_config';
            // advanced-cache.php reste en place : il servira dès que la constante sera ajoutée à la main
            $state['page'] = defined('WP_CACHE') && WP_CACHE;
        } else {
            $state['page'] = true;
        }
    }
    if (!$state['page'] && $state['error'] !== 'wp_config') {
        haptic_cache_disable_page();
    }

    if (!haptic_owns_shared_files()) {
        // Multisite : le .htaccess de la racine est commun au réseau, seul le site principal y écrit ces règles
        if (haptic_cache_browser_wanted()) {
            $state['browser_error'] = 'network';
        }
    } elseif (haptic_cache_browser_wanted()) {
        if (!haptic_cache_server_reads_htaccess()) {
            $state['browser_error'] = 'server';
        } elseif (!haptic_cache_write_htaccess(haptic_cache_htaccess_rules((bool) get_option('haptic_cache_gzip', 1)))) {
            $state['browser_error'] = 'htaccess';
        } else {
            $state['browser'] = true;
        }
    } else {
        // Sans condition sur le serveur : WP-CLI ne sait pas qu'il s'agit d'Apache
        haptic_cache_write_htaccess([]);
    }

    update_option('haptic_cache_state', $state, false);
    return $state;
}

/** Supprime un fichier de réglages du cache (uniquement sous wp-content/cache/optimizr). */
function haptic_cache_delete_config($file)
{
    if ($file === '' || strpos($file, haptic_cache_base_dir() . '/') !== 0 || strpos($file, '..') !== false || !is_file($file)) {
        return;
    }
    wp_delete_file($file);
    haptic_cache_opcache_invalidate($file);
}

/**
 * Retire le cache de pages de ce site : ses réglages et ses pages. Les fichiers communs (advanced-cache.php,
 * constante WP_CACHE ajoutée par Optimizr) le sont aussi, sauf si un autre site du réseau s'en sert encore.
 */
function haptic_cache_disable_page()
{
    haptic_cache_delete_config((string) haptic_cache_config_path());
    haptic_cache_delete_dir(haptic_cache_pages_dir());

    if (is_multisite() && haptic_cache_network_in_use()) {
        return;
    }
    haptic_cache_delete_dropin();
    haptic_cache_set_wp_cache(false);
    haptic_cache_delete_dir(haptic_cache_base_dir() . '/pages');
    haptic_cache_delete_dir(haptic_cache_base_dir() . '/sites');
}

/** Tout retirer : désactivation du plugin ou du module, suppression du plugin. */
function haptic_cache_teardown()
{
    haptic_cache_disable_page();
    delete_option('haptic_cache_state');

    // Multisite : le .htaccess de la racine appartient au site principal, et le dossier du cache reste
    // tant qu'un autre site du réseau s'en sert
    if (haptic_owns_shared_files()) {
        haptic_cache_write_htaccess([]);
    }
    if (!is_multisite() || !haptic_cache_network_in_use()) {
        haptic_cache_delete_dir(haptic_cache_base_dir());
    }
}

// Multisite, site supprimé du réseau : ses réglages et ses pages en cache disparaissent (avant la suppression de ses tables)
add_action('wp_uninitialize_site', function ($site) {
    switch_to_blog($site->id);
    haptic_cache_disable_page();
    restore_current_blog();
}, 5);

/*
 * Synchronisation en fin de requête, une seule fois, après l'enregistrement d'un réglage du cache, des modules
 * (activation ou désactivation du module Cache) ou de l'adresse du site.
 */
function haptic_cache_schedule_sync()
{
    static $scheduled = false;
    if (!$scheduled) {
        $scheduled = true;
        add_action('shutdown', 'haptic_cache_sync', 5);
    }
}
add_action('updated_option', 'haptic_cache_maybe_schedule_sync');
add_action('added_option', 'haptic_cache_maybe_schedule_sync');
function haptic_cache_maybe_schedule_sync($option)
{
    if ($option === 'haptic_modules' || $option === 'home'
        || (strpos($option, 'haptic_cache_') === 0 && !in_array($option, haptic_cache_internal_options(), true))) {
        haptic_cache_schedule_sync();
    }
}

/** Options du cache qui sont un état, pas un réglage : ni exportées ni déclencheurs de synchronisation. */
function haptic_cache_internal_options()
{
    return apply_filters('haptic_cache_internal_options', ['haptic_cache_state', 'haptic_cache_last_purge']);
}
add_filter('haptic_settings_internal_options', function ($options) {
    return array_merge($options, haptic_cache_internal_options());
});

add_action('haptic_activate', 'haptic_cache_sync');
add_action('haptic_deactivate', 'haptic_cache_teardown');
