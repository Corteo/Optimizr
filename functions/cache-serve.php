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
     * Valeur de $_SERVER nettoyée selon ce qu'elle doit contenir, en PHP natif : ce fichier tourne aussi avant WordPress
     * (advanced-cache.php), sans sanitize_text_field() ni esc_url_raw().
     * - 'token' : lettres, chiffres, « - » et « _ » (HTTPS, REQUEST_METHOD, HTTP_X_FORWARDED_PROTO) ;
     * - 'int'   : chiffres seulement (SERVER_PORT) ;
     * - 'host'  : nom d'hôte et port, en minuscules (HTTP_HOST) ;
     * - 'url'   : caractères permis dans une adresse, FILTER_SANITIZE_URL (REQUEST_URI) ;
     * - 'text'  : texte sans balises ni caractères de contrôle (HTTP_USER_AGENT, HTTP_IF_MODIFIED_SINCE).
     * Pour 'token', 'int', 'host' et 'url', une valeur que le nettoyage modifierait est écartée ('') plutôt que
     * réécrite : deux adresses différentes ne doivent jamais donner le même fichier de cache. La requête n'est
     * alors ni servie ni mise en cache.
     */
    function haptic_cache_server_var($key, $type = 'text')
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nettoyée ci-dessous en PHP natif (WordPress pas encore chargé dans advanced-cache.php)
        $value = isset($_SERVER[$key]) && is_scalar($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
        // Une fois WordPress chargé, $_SERVER est échappé (wp_magic_quotes(), juste avant cette action) :
        // même valeur des deux côtés pour que la page écrite soit celle qui sera servie
        if (function_exists('did_action') && did_action('sanitize_comment_cookies')) {
            $value = wp_unslash($value);
        }

        switch ($type) {
            case 'token':
                $clean = preg_replace('/[^A-Za-z0-9_\-]/', '', $value);
                break;
            case 'int':
                $clean = preg_replace('/[^0-9]/', '', $value);
                break;
            case 'host':
                $clean = strtolower(preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $value));
                $value = strtolower($value);
                break;
            case 'url':
                $clean = (string) filter_var($value, FILTER_SANITIZE_URL);
                break;
            default:
                return trim(preg_replace(['/<[^>]*>?/', '/[\x00-\x1F\x7F]+/'], ['', ' '], $value));
        }

        return $clean === $value ? $clean : '';
    }

    /** Noms des cookies de la requête, nettoyés comme des jetons (ils ne servent qu'à reconnaître un visiteur connecté). */
    function haptic_cache_cookie_names()
    {
        $names = [];
        foreach (array_keys($_COOKIE) as $name) {
            $names[] = preg_replace('/[^A-Za-z0-9_\-.%]/', '', (string) $name);
        }
        return $names;
    }

    /**
     * Réglages écrits par le module (functions/cache-files.php) : hôte, durée de vie, exclusions…
     *
     * @return array|null null si le cache de pages n'est pas en place
     */
    function haptic_cache_config()
    {
        static $config = false;

        if ($config === false) {
            $file = haptic_cache_base_dir() . '/config.php';
            $config = is_file($file) ? include $file : null;
            if (!is_array($config) || empty($config['host']) || empty($config['enabled'])) {
                $config = null;
            }
        }

        return $config;
    }

    function haptic_cache_is_https()
    {
        $https = strtolower(haptic_cache_server_var('HTTPS', 'token'));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if (haptic_cache_server_var('SERVER_PORT', 'int') === '443') {
            return true;
        }
        // Proxy ou répartiteur de charge qui termine le HTTPS
        return strtolower(haptic_cache_server_var('HTTP_X_FORWARDED_PROTO', 'token')) === 'https';
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
        $method = strtoupper(haptic_cache_server_var('REQUEST_METHOD', 'token'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return '';
        }
        if (PHP_SAPI === 'cli' || (defined('DOING_CRON') && DOING_CRON) || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '';
        }

        $host = haptic_cache_server_var('HTTP_HOST', 'host');
        if ($host !== $config['host']) {
            return '';
        }

        // Visiteur connecté, contenu protégé par mot de passe, commentateur, panier… : page personnalisée
        foreach (haptic_cache_cookie_names() as $name) {
            foreach ($config['cookies'] as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    return '';
                }
            }
        }

        $uri = haptic_cache_server_var('REQUEST_URI', 'url');
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
        return haptic_cache_base_dir() . '/pages/' . str_replace(':', '_', $config['host']) . ($dir !== '' ? '/' . $dir : '') . '/' . $name . '.html';
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
        if (strtoupper(haptic_cache_server_var('REQUEST_METHOD', 'token')) !== 'HEAD') {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- servi par advanced-cache.php avant le chargement de WordPress (pas de WP_Filesystem), fichier de wp-content/cache/optimizr
            readfile($file);
        }
        exit;
    }
}
