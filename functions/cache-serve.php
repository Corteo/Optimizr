<?php

/*
 * Cache de pages : partie commune au fichier wp-content/advanced-cache.php (chargé avant WordPress, sans aucune
 * de ses fonctions) et au module Cache (functions/cache.php), qui écrit les pages. Uniquement du PHP natif.
 *
 * Une page est mise en cache pour les visiteurs non connectés, en GET, sans paramètre d'adresse autre que ceux
 * de suivi (utm_*, gclid…), sur l'hôte du site seulement : un en-tête Host forgé ne crée ni ne lit aucun fichier.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('haptic_cache_config')) {

    /** Dossier du cache de pages (réglages et pages). */
    function haptic_cache_base_dir()
    {
        return WP_CONTENT_DIR . '/cache/optimizr';
    }

    /**
     * Valeur de $_SERVER en chaîne, ou '' si elle est absente ou contient des caractères de contrôle.
     * Ce fichier tourne aussi avant WordPress, sans sanitize_text_field() : rien n'est réécrit (deux adresses
     * différentes ne doivent pas donner le même fichier de cache), une valeur douteuse est écartée et la requête
     * n'est alors ni servie ni mise en cache. Les valeurs ne servent qu'à des comparaisons et au chemin du fichier,
     * filtré par haptic_cache_request_file().
     */
    function haptic_cache_server_var($key)
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validée ci-dessous en PHP natif (WordPress pas encore chargé dans advanced-cache.php)
        $value = isset($_SERVER[$key]) && is_scalar($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
        // Une fois WordPress chargé, $_SERVER est échappé (wp_magic_quotes(), juste avant cette action) :
        // même valeur des deux côtés pour que la page écrite soit celle qui sera servie
        if (function_exists('did_action') && did_action('sanitize_comment_cookies')) {
            $value = wp_unslash($value);
        }
        return preg_match('/[\x00-\x1F\x7F]/', $value) ? '' : $value;
    }

    /**
     * Réglages écrits par le module (functions/cache-files.php) : hôte, durée de vie, exclusions…
     *
     * @return array|null null si le cache de pages n'est pas en place
     */
    function haptic_cache_config()
    {
        static $configs = [];

        if (defined('MULTISITE') && MULTISITE) {
            // Multisite : un fichier de réglages par site. Une fois WordPress chargé, celui du site courant
            // (functions/cache-files.php) ; avant, dans advanced-cache.php, celui que désigne l'adresse demandée.
            $file = function_exists('haptic_cache_config_path') ? haptic_cache_config_path() : haptic_cache_request_config_file();
        } else {
            $file = haptic_cache_base_dir() . '/config.php';
        }

        if (!array_key_exists($file, $configs)) {
            $config = $file !== '' && is_file($file) ? include $file : null;
            if (!is_array($config) || empty($config['host']) || empty($config['enabled'])) {
                $config = null;
            }
            $configs[$file] = $config;
        }

        return $configs[$file];
    }

    /** Nom de dossier d'un hôte (« : » du port remplacé), ou '' si l'hôte contient autre chose qu'un nom de domaine. */
    function haptic_cache_host_dir($host)
    {
        return preg_match('/^[a-z0-9.\-]+(:[0-9]+)?$/', (string) $host) ? str_replace(':', '_', $host) : '';
    }

    /**
     * Multisite : fichier de réglages du site d'adresse https://{$host}{$path} ($path : « / », « /site2/ »…).
     * Un fichier par site, que chaque site écrit et retire sans toucher à ceux des autres.
     */
    function haptic_cache_site_config_file($host, $path)
    {
        $dir = haptic_cache_host_dir($host);
        return $dir === '' ? '' : haptic_cache_base_dir() . '/sites/' . $dir . '/' . md5((string) $path) . '.php';
    }

    /**
     * Multisite, avant le chargement de WordPress : fichier de réglages du site auquel s'adresse la requête.
     * Réseau en sous-domaines ou à domaines distincts : l'hôte suffit. Réseau en sous-dossiers : le début du chemin
     * désigne le site (« /site2/page/ » : site « /site2/ », sinon le site principal « / »), du plus précis au plus général.
     */
    function haptic_cache_request_config_file()
    {
        $host = strtolower(haptic_cache_server_var('HTTP_HOST'));
        $parts = explode('?', haptic_cache_server_var('REQUEST_URI'), 2);
        $segments = array_slice(array_values(array_filter(explode('/', $parts[0]), 'strlen')), 0, 3);

        for ($i = count($segments); $i >= 0; $i--) {
            $prefix = $i ? '/' . implode('/', array_slice($segments, 0, $i)) . '/' : '/';
            $file = haptic_cache_site_config_file($host, $prefix);
            if ($file !== '' && is_file($file)) {
                return $file;
            }
        }
        return '';
    }

    function haptic_cache_is_https()
    {
        $https = strtolower(haptic_cache_server_var('HTTPS'));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if (haptic_cache_server_var('SERVER_PORT') === '443') {
            return true;
        }
        // Proxy ou répartiteur de charge qui termine le HTTPS
        return strtolower(haptic_cache_server_var('HTTP_X_FORWARDED_PROTO')) === 'https';
    }

    /** Téléphone ou tablette, même détection que wp_is_mobile(). */
    function haptic_cache_is_mobile()
    {
        return (bool) preg_match('/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi/', haptic_cache_server_var('HTTP_USER_AGENT'));
    }

    /** L'adresse correspond-elle à l'un des motifs exclus (« * » pour n'importe quelle suite de caractères) ? */
    function haptic_cache_path_excluded($path, array $patterns)
    {
        foreach ($patterns as $pattern) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
            if (preg_match($regex, $path) || preg_match($regex, rtrim($path, '/') . '/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fichier de cache de la requête en cours, ou '' si elle ne peut pas être mise en cache
     * (méthode, connexion, cookies, paramètres, adresse exclue ou inhabituelle).
     */
    function haptic_cache_request_file(array $config)
    {
        $method = strtoupper(haptic_cache_server_var('REQUEST_METHOD'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return '';
        }
        if (PHP_SAPI === 'cli' || (defined('DOING_CRON') && DOING_CRON) || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '';
        }

        $host = strtolower(haptic_cache_server_var('HTTP_HOST'));
        if ($host !== $config['host']) {
            return '';
        }

        // Visiteur connecté, contenu protégé par mot de passe, commentateur, panier… : page personnalisée
        foreach (array_keys($_COOKIE) as $name) {
            foreach ($config['cookies'] as $prefix) {
                if (strpos((string) $name, $prefix) === 0) {
                    return '';
                }
            }
        }

        $uri = haptic_cache_server_var('REQUEST_URI');
        $parts = explode('?', $uri, 2);
        $path = $parts[0];

        // Paramètres de suivi seulement : même page que sans paramètre
        if (isset($parts[1]) && $parts[1] !== '') {
            parse_str($parts[1], $query);
            foreach (array_keys($query) as $key) {
                if (!in_array(strtolower((string) $key), $config['ignore_query'], true) && strpos(strtolower((string) $key), 'utm_') !== 0) {
                    return '';
                }
            }
        }

        // Adresses inhabituelles (caractères, « .. », fichiers PHP) : jamais en cache
        if ($path === '' || $path[0] !== '/' || strpos($path, '..') !== false || strpos($path, '//') !== false
            || !preg_match('#^[A-Za-z0-9/_.\-~%@!$&\'()*+,;=:]*$#', $path) || preg_match('#\.php$|^/wp-(admin|includes|json)(/|$)#i', $path)) {
            return '';
        }
        // Multisite en sous-dossiers : seulement les adresses du site (« /site2/… »)
        if (!empty($config['path']) && $config['path'] !== '/' && strpos($path . '/', $config['path']) !== 0) {
            return '';
        }
        if (!empty($config['exclude']) && haptic_cache_path_excluded($path, $config['exclude'])) {
            return '';
        }

        $name = 'index';
        if (haptic_cache_is_https()) {
            $name .= '-https';
        }
        if (!empty($config['mobile']) && haptic_cache_is_mobile()) {
            $name .= '-mobile';
        }
        // /page et /page/ : la seconde est souvent redirigée vers la première, on ne les confond pas
        if ($path !== '/' && substr($path, -1) !== '/') {
            $name .= '-noslash';
        }

        $dir = trim($path, '/');
        // Dossier des pages du site : son hôte, suivi du numéro du site sur un multisite (réglage « dir »)
        $root = !empty($config['dir']) && preg_match('/^[a-z0-9._\-]+$/', $config['dir']) ? $config['dir'] : str_replace(':', '_', $config['host']);
        return haptic_cache_base_dir() . '/pages/' . $root . ($dir !== '' ? '/' . $dir : '') . '/' . $name . '.html';
    }

    /** Sert la page depuis le cache si elle y est et n'a pas expiré, puis arrête PHP. Appelé par advanced-cache.php. */
    function haptic_cache_serve()
    {
        $config = haptic_cache_config();
        if (!$config) {
            return;
        }

        $file = haptic_cache_request_file($config);
        $mtime = ($file !== '' && is_file($file)) ? filemtime($file) : 0;
        if (!$mtime || $mtime + (int) $config['ttl'] < time()) {
            return;
        }

        header('Content-Type: text/html; charset=UTF-8');
        header('X-Optimizr-Cache: HIT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        if (!empty($config['mobile'])) {
            header('Vary: User-Agent');
        }

        $since = strtotime(haptic_cache_server_var('HTTP_IF_MODIFIED_SINCE'));
        if ($since && $since >= $mtime) {
            http_response_code(304);
            exit;
        }
        if (strtoupper(haptic_cache_server_var('REQUEST_METHOD')) !== 'HEAD') {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- servi par advanced-cache.php avant le chargement de WordPress (pas de WP_Filesystem), fichier de wp-content/cache/optimizr
            readfile($file);
        }
        exit;
    }
}
