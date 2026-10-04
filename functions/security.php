<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Module Sécurité.
 *
 * Toutes les protections sont désactivées par défaut et s'activent une à une dans l'onglet Sécurité.
 *
 * Adresse de connexion personnalisée : en cas de blocage, ajouter dans wp-config.php
 *     define('OPTIMIZR_DISABLE_CUSTOM_LOGIN', true);
 * pour revenir à wp-login.php.
 */

/* ---------- Outils ---------- */

function hpt_sec_option($name, $default = 0)
{
    return get_option('hpt_sec_' . $name, $default);
}

/**
 * Adresse IP du visiteur.
 *
 * REMOTE_ADDR par défaut : les en-têtes de proxy sont falsifiables. Ils ne sont lus que pour une requête
 * qui vient d'un proxy de confiance (réglage « Proxys de confiance »), sinon tous les visiteurs d'un site
 * derrière Cloudflare ou un répartiteur de charge auraient la même adresse.
 */
function hpt_sec_client_ip()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '0.0.0.0';
    }
    $proxies = hpt_sec_trusted_proxies();
    if (!hpt_sec_ip_in_ranges($ip, $proxies)) {
        return $ip;
    }

    // Cloudflare remplace toujours cet en-tête : fiable seulement si la requête vient directement de ses serveurs
    if (hpt_sec_option('proxy_cloudflare') && hpt_sec_ip_in_ranges($ip, hpt_sec_cloudflare_ranges())) {
        $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if (filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
    }

    // « visiteur, proxy 1, proxy 2 » : chaque proxy ajoute à droite l'adresse qui l'a contacté, le début de la liste est falsifiable.
    // On remonte depuis la droite jusqu'à la première adresse qui n'est pas un proxy de confiance.
    $forwarded = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
    foreach (array_reverse($forwarded) as $candidate) {
        if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
            break;
        }
        if (!hpt_sec_ip_in_ranges($candidate, $proxies)) {
            return $candidate;
        }
    }
    return $ip;
}

/** Adresse IP seule ou plage CIDR (10.0.0.0/8, 2001:db8::/32). */
function hpt_sec_is_ip_range($range)
{
    $parts = explode('/', (string) $range, 2);
    if (!filter_var($parts[0], FILTER_VALIDATE_IP)) {
        return false;
    }
    if (!isset($parts[1])) {
        return true;
    }
    $max = filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
    return ctype_digit($parts[1]) && (int) $parts[1] <= $max;
}

/** L'adresse appartient-elle à l'une des plages (adresses seules ou CIDR, cf. hpt_sec_is_ip_range) ? */
function hpt_sec_ip_in_ranges($ip, $ranges)
{
    $packed = filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : false;
    if ($packed === false) {
        return false;
    }
    foreach ($ranges as $range) {
        if (!hpt_sec_is_ip_range($range)) {
            continue;
        }
        $parts = explode('/', $range, 2);
        $subnet = inet_pton($parts[0]);
        // IPv4 et IPv6 ne se comparent pas
        if (strlen($subnet) !== strlen($packed)) {
            continue;
        }
        $bits = isset($parts[1]) ? (int) $parts[1] : strlen($packed) * 8;
        $bytes = intdiv($bits, 8);
        if (substr($packed, 0, $bytes) !== substr($subnet, 0, $bytes)) {
            continue;
        }
        // Octet partiellement couvert par le masque (/20, /29…)
        $mask = $bits % 8 ? chr((0xff << (8 - $bits % 8)) & 0xff) : '';
        if ($mask === '' || ($packed[$bytes] & $mask) === ($subnet[$bytes] & $mask)) {
            return true;
        }
    }
    return false;
}

/** Plages d'adresses des serveurs de Cloudflare (https://www.cloudflare.com/ips/). */
function hpt_sec_cloudflare_ranges()
{
    return apply_filters('hpt_sec_cloudflare_ranges', [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ]);
}

/** Proxys de confiance : plages saisies (une par ligne), et celles de Cloudflare si l'option est active. Vide par défaut. */
function hpt_sec_trusted_proxies()
{
    $ranges = array_filter(array_map('trim', preg_split('/\R/', (string) hpt_sec_option('trusted_proxies', ''))), 'hpt_sec_is_ip_range');
    if (hpt_sec_option('proxy_cloudflare')) {
        $ranges = array_merge($ranges, hpt_sec_cloudflare_ranges());
    }
    return array_values($ranges);
}

/** Adresses IP jamais limitées (une par ligne). */
function hpt_sec_whitelist()
{
    $lines = preg_split('/\R/', (string) hpt_sec_option('login_whitelist', ''));
    return array_values(array_filter(array_map('trim', $lines), function ($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP);
    }));
}

/**
 * Journal des évènements de sécurité (100 derniers, 30 jours maximum).
 * Les adresses IP sont des données personnelles : la durée de conservation est volontairement courte.
 */
function hpt_sec_log($event, $detail = '')
{
    $log = hpt_sec_get_log();
    array_unshift($log, ['time' => time(), 'ip' => hpt_sec_client_ip(), 'event' => $event, 'detail' => $detail]);
    update_option('hpt_security_log', array_slice($log, 0, 100), false);
}

/** Évènements des 30 derniers jours. Les plus anciens sont supprimés de la base au passage, même sans nouvel évènement. */
function hpt_sec_get_log()
{
    $stored = array_values((array) get_option('hpt_security_log', []));
    $limit = time() - 30 * DAY_IN_SECONDS;
    $log = array_values(array_filter($stored, function ($entry) use ($limit) {
        return ($entry['time'] ?? 0) >= $limit;
    }));
    if ($log !== $stored) {
        update_option('hpt_security_log', $log, false);
    }
    return $log;
}
// Purge quotidienne (tâche planifiée de WordPress), même si personne n'ouvre l'onglet Sécurité
add_action('wp_scheduled_delete', 'hpt_sec_get_log');

/* ---------- Adresse de connexion personnalisée ---------- */

/** Identifiant de l'adresse de connexion, ou chaîne vide si la fonction est inactive. */
function hpt_sec_login_slug()
{
    if ((defined('OPTIMIZR_DISABLE_CUSTOM_LOGIN') && OPTIMIZR_DISABLE_CUSTOM_LOGIN) || is_multisite()) {
        return '';
    }
    return (string) hpt_sec_option('login_slug', '');
}

/** Nouvelle adresse de connexion (avec permaliens simples : https://site/?identifiant). */
function hpt_sec_login_url($scheme = null, $slug = null)
{
    $slug = $slug ?? hpt_sec_login_slug();
    if (get_option('permalink_structure')) {
        return home_url('/' . $slug . '/', $scheme);
    }
    return home_url('/', $scheme) . '?' . $slug;
}

/** Chemin demandé, relatif à l'installation (« » pour l'accueil). */
function hpt_sec_request_path()
{
    $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $home = trim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home !== '' && strpos($path, $home) === 0) {
        $path = ltrim(substr($path, strlen($home)), '/');
    }
    return $path;
}

function hpt_sec_is_custom_login_request()
{
    $slug = hpt_sec_login_slug();
    $path = hpt_sec_request_path();

    if (get_option('permalink_structure')) {
        return $path === $slug;
    }
    return in_array($path, ['', 'index.php'], true) && isset($_GET[$slug]);
}

function hpt_sec_is_wp_login_request()
{
    return basename(hpt_sec_request_path()) === 'wp-login.php';
}

/** Page introuvable : modèle 404 du thème côté site, page minimale côté administration. */
function hpt_sec_not_found()
{
    status_header(404);
    nocache_headers();

    if (!is_admin()) {
        global $wp_query;
        if ($wp_query) {
            $wp_query->set_404();
        }
        $template = get_404_template();
        if ($template) {
            include $template;
            exit;
        }
    }

    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Page introuvable</title><p>Page introuvable.</p><p><a href="' . esc_url(home_url('/')) . '">Retour à l\'accueil</a></p></html>';
    exit;
}

/** Remplace wp-login.php par la nouvelle adresse dans une URL (paramètres conservés). */
function hpt_sec_replace_login_url($url, $scheme = null)
{
    if (strpos($url, 'wp-login.php') === false || !hpt_sec_login_slug()) {
        return $url;
    }
    $parts = explode('?', $url, 2);
    $new = hpt_sec_login_url($scheme);
    if (!empty($parts[1])) {
        $new .= (strpos($new, '?') === false ? '?' : '&') . $parts[1];
    }
    return $new;
}

if (hpt_sec_login_slug()) {
    // Liens générés par WordPress : connexion, déconnexion, mot de passe perdu, emails, formulaire des contenus protégés
    add_filter('site_url', function ($url, $path, $scheme) {
        return hpt_sec_replace_login_url($url, $scheme);
    }, 10, 3);
    add_filter('network_site_url', function ($url, $path, $scheme) {
        return hpt_sec_replace_login_url($url, $scheme);
    }, 10, 3);
    add_filter('wp_redirect', 'hpt_sec_replace_login_url');

    // Raccourcis /login, /admin, /dashboard : ils redirigeraient vers la nouvelle adresse et la révéleraient
    remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);

    add_action('wp_loaded', function () {
        global $pagenow;

        if (hpt_sec_is_custom_login_request()) {
            hpt_sec_load_login_page();
        }

        if (is_user_logged_in()) {
            return;
        }

        // wp-login.php et l'administration n'existent plus pour les visiteurs non connectés
        if (hpt_sec_is_wp_login_request()) {
            hpt_sec_not_found();
        }
        if (is_admin() && !wp_doing_ajax() && !wp_doing_cron() && $pagenow !== 'admin-post.php') {
            hpt_sec_not_found();
        }
    }, 1);
}

function hpt_sec_load_login_page()
{
    // wp-login.php s'attend à des variables globales
    global $error, $interim_login, $action, $user_login, $pagenow;
    $pagenow = 'wp-login.php';
    require_once ABSPATH . 'wp-login.php';
    exit;
}

/** Valide l'identifiant de connexion (lettres, chiffres, tirets ; 4 caractères minimum, non réservé). */
function hpt_sec_sanitize_login_slug($value)
{
    $value = sanitize_title((string) $value);
    if ($value === '') {
        return '';
    }

    $reserved = ['wp-admin', 'wp-login', 'wp-login-php', 'admin', 'login', 'dashboard', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'xmlrpc', 'sitemap'];
    $error = '';
    if (strlen($value) < 4) {
        $error = 'L\'adresse de connexion doit contenir au moins 4 caractères.';
    } elseif (in_array($value, $reserved, true)) {
        $error = 'Cette adresse de connexion est réservée par WordPress.';
    } elseif (get_page_by_path($value, OBJECT, get_post_types(['public' => true]))) {
        $error = 'Un contenu du site utilise déjà cette adresse.';
    }

    if ($error) {
        // Fonction de l'administration : absente si l'option est enregistrée depuis un autre contexte
        if (function_exists('add_settings_error')) {
            add_settings_error('hpt_sec_login_slug', 'hpt_sec_login_slug', $error);
        }
        return (string) get_option('hpt_sec_login_slug', '');
    }

    return $value;
}

// Nouvelle adresse envoyée par email : on ne la perd pas
function hpt_sec_mail_login_url($old, $new)
{
    if ($new === '' || $new === $old) {
        return;
    }
    wp_mail(
        get_option('admin_email'),
        sprintf('[%s] Nouvelle adresse de connexion', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)),
        sprintf(
            "L'adresse de connexion à l'administration a été modifiée.\n\nNouvelle adresse : %s\n\nL'ancienne adresse (wp-login.php) affiche désormais une page introuvable.\nConservez cet email. En cas de blocage, ajoutez define('OPTIMIZR_DISABLE_CUSTOM_LOGIN', true); dans wp-config.php.",
            hpt_sec_login_url(null, $new)
        )
    );
}
add_action('update_option_hpt_sec_login_slug', 'hpt_sec_mail_login_url', 10, 2);
add_action('add_option_hpt_sec_login_slug', function ($option, $value) {
    hpt_sec_mail_login_url('', $value);
}, 10, 2);

/* ---------- Tentatives de connexion ---------- */

function hpt_sec_lock_key($ip)
{
    return 'hpt_sec_lock_' . md5($ip);
}

function hpt_sec_fail_key($ip)
{
    return 'hpt_sec_fail_' . md5($ip);
}

if (hpt_sec_option('limit_login')) {
    // Après toutes les vérifications d'identifiants : une adresse verrouillée est refusée, même avec le bon mot de passe
    add_filter('authenticate', function ($user) {
        $ip = hpt_sec_client_ip();
        if (in_array($ip, hpt_sec_whitelist(), true)) {
            return $user;
        }
        $until = (int) get_transient(hpt_sec_lock_key($ip));
        if ($until > time()) {
            return new WP_Error('hpt_sec_locked', sprintf(
                'Trop de tentatives de connexion. Réessayez dans %d minute(s).',
                max(1, (int) ceil(($until - time()) / 60))
            ));
        }
        return $user;
    }, 99);

    add_action('wp_login_failed', function ($username) {
        $ip = hpt_sec_client_ip();
        if (in_array($ip, hpt_sec_whitelist(), true) || get_transient(hpt_sec_lock_key($ip))) {
            return;
        }

        $max = max(1, (int) hpt_sec_option('login_max', 5));
        $lockout = max(1, (int) hpt_sec_option('login_lockout', 15)) * MINUTE_IN_SECONDS;
        $fails = (int) get_transient(hpt_sec_fail_key($ip)) + 1;

        if ($fails >= $max) {
            delete_transient(hpt_sec_fail_key($ip));
            set_transient(hpt_sec_lock_key($ip), time() + $lockout, $lockout);
            hpt_sec_log('Verrouillage', sprintf('%d échecs, dernier identifiant essayé : %s', $fails, sanitize_user((string) $username)));
        } else {
            set_transient(hpt_sec_fail_key($ip), $fails, $lockout);
        }
    });

    add_action('wp_login', function () {
        delete_transient(hpt_sec_fail_key(hpt_sec_client_ip()));
    });
}

// Message neutre : ne révèle pas si l'identifiant existe
if (hpt_sec_option('generic_errors')) {
    add_filter('authenticate', function ($user) {
        if (is_wp_error($user) && array_intersect($user->get_error_codes(), ['invalid_username', 'invalid_email', 'incorrect_password'])) {
            return new WP_Error('hpt_sec_invalid', 'Identifiant ou mot de passe incorrect.');
        }
        return $user;
    }, 100);
}

// Alerte lors d'une connexion administrateur depuis une adresse IP inconnue
if (hpt_sec_option('login_alert')) {
    add_action('wp_login', function ($login, $user) {
        if (!user_can($user, 'manage_options')) {
            return;
        }
        $hash = wp_hash(hpt_sec_client_ip());
        // get_user_meta() renvoie « » pour un compte jamais suivi : (array) « » donnerait [''], pris pour une adresse connue
        $known = array_values(array_filter((array) get_user_meta($user->ID, 'hpt_sec_known_ips', true)));

        if (in_array($hash, $known, true)) {
            return;
        }
        // Première connexion suivie : on mémorise sans alerter
        if ($known) {
            wp_mail(
                $user->user_email,
                sprintf('[%s] Connexion administrateur depuis une nouvelle adresse', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)),
                sprintf(
                    "Le compte « %s » vient de se connecter depuis une adresse IP inconnue.\n\nDate : %s\nAdresse IP : %s\nNavigateur : %s\n\nSi ce n'est pas vous, changez immédiatement votre mot de passe.",
                    $user->user_login,
                    wp_date('j F Y à H:i'),
                    hpt_sec_client_ip(),
                    sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'inconnu'))
                )
            );
            hpt_sec_log('Alerte de connexion', 'Compte ' . $user->user_login);
        }
        array_unshift($known, $hash);
        update_user_meta($user->ID, 'hpt_sec_known_ips', array_slice(array_values(array_filter($known)), 0, 20));
    }, 10, 2);
}

/* ---------- Discrétion ---------- */

if (hpt_sec_option('hide_version')) {
    remove_action('wp_head', 'wp_generator');
    add_filter('the_generator', '__return_empty_string');

    // ?ver=6.x des fichiers du cœur remplacé par une empreinte : le cache navigateur fonctionne toujours
    $hide_ver = function ($src) {
        global $wp_version;
        if (!is_admin() && strpos((string) $src, 'ver=' . $wp_version) !== false) {
            $src = str_replace('ver=' . $wp_version, 'ver=' . substr(md5($wp_version . wp_salt('nonce')), 0, 8), $src);
        }
        return $src;
    };
    add_filter('style_loader_src', $hide_ver, 9999);
    add_filter('script_loader_src', $hide_ver, 9999);

    add_action('send_headers', function () {
        header_remove('X-Powered-By');
    });
}

if (hpt_sec_option('block_enumeration')) {
    // ?author=1 redirige normalement vers /author/identifiant/ : priorité 1, avant redirect_canonical
    add_action('template_redirect', function () {
        if (is_user_logged_in() || !is_author()) {
            return;
        }
        if (isset($_GET['author']) || hpt_sec_option('block_author_pages')) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
        }
    }, 1);

    // Liste des comptes de l'API REST réservée aux utilisateurs connectés
    add_filter('rest_pre_dispatch', function ($result, $server, $request) {
        if (!is_user_logged_in() && preg_match('#^/wp/v2/users#', $request->get_route())) {
            return new WP_Error('rest_user_cannot_view', 'Accès refusé.', ['status' => 401]);
        }
        return $result;
    }, 10, 3);

    // Nom de l'auteur dans les intégrations oEmbed
    add_filter('oembed_response_data', function ($data) {
        unset($data['author_name'], $data['author_url']);
        return $data;
    });

    // Sitemap natif de WordPress (s'il est actif) : pas de liste des auteurs
    add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
        return $name === 'users' ? false : $provider;
    }, 10, 2);
}

/* ---------- Surface d'attaque ---------- */

if (hpt_sec_option('disable_xmlrpc')) {
    add_filter('xmlrpc_enabled', '__return_false');
    add_filter('xmlrpc_methods', '__return_empty_array');
    remove_action('wp_head', 'rsd_link');
    add_filter('wp_headers', function ($headers) {
        unset($headers['X-Pingback']);
        return $headers;
    });
    // xmlrpc.php refusé avant tout traitement
    if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
        status_header(403);
        exit('XML-RPC est désactivé sur ce site.');
    }
}

if (hpt_sec_option('disable_app_passwords')) {
    add_filter('wp_is_application_passwords_available', '__return_false');
}

if (hpt_sec_option('disable_file_edit') && !defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

/**
 * Interdit l'exécution de PHP dans le dossier uploads (serveurs Apache, via .htaccess).
 *
 * @return bool
 */
function hpt_sec_apply_uploads_rule($enable)
{
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    $file = trailingslashit(wp_upload_dir()['basedir']) . '.htaccess';
    $lines = $enable ? [
        '<FilesMatch "\.(?i:php[0-9]?|phtml|phar)$">',
        '    <IfModule mod_authz_core.c>',
        '        Require all denied',
        '    </IfModule>',
        '    <IfModule !mod_authz_core.c>',
        '        Order allow,deny',
        '        Deny from all',
        '    </IfModule>',
        '</FilesMatch>',
    ] : [];

    if (!$enable && !file_exists($file)) {
        return true;
    }
    return (bool) insert_with_markers($file, 'Optimizr', $lines);
}

function hpt_sec_uploads_rule_present()
{
    $file = trailingslashit(wp_upload_dir()['basedir']) . '.htaccess';
    return is_readable($file) && strpos((string) file_get_contents($file), '# BEGIN Optimizr') !== false;
}

add_action('update_option_hpt_sec_uploads_php', function ($old, $new) {
    hpt_sec_apply_uploads_rule((bool) $new);
}, 10, 2);
add_action('add_option_hpt_sec_uploads_php', function ($option, $value) {
    hpt_sec_apply_uploads_rule((bool) $value);
}, 10, 2);

/* ---------- En-têtes HTTP ---------- */

function hpt_sec_send_headers()
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Pas de « payment » : les paiements Apple Pay / Google Pay de WooCommerce en ont besoin
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');

    if (hpt_sec_option('hsts') && is_ssl()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

if (hpt_sec_option('headers')) {
    add_action('send_headers', 'hpt_sec_send_headers');
    add_action('login_init', 'hpt_sec_send_headers');
}

/* ---------- Bilan ---------- */

/**
 * Points de contrôle de l'environnement (indépendants des options du module).
 *
 * @return array[] ['status' => ok|warn, 'label' => …]
 */
function hpt_sec_environment_checks()
{
    $checks = [];

    $checks[] = is_ssl() || strpos(home_url(), 'https://') === 0
        ? ['status' => 'ok', 'label' => 'Site servi en HTTPS']
        : ['status' => 'warn', 'label' => 'Site servi sans HTTPS : identifiants transmis en clair'];

    $admin = get_user_by('login', 'admin');
    $checks[] = $admin && user_can($admin, 'manage_options')
        ? ['status' => 'warn', 'label' => 'Un administrateur s\'appelle « admin », le premier identifiant essayé par les robots']
        : ['status' => 'ok', 'label' => 'Aucun administrateur nommé « admin »'];

    $display = defined('WP_DEBUG') && WP_DEBUG && (!defined('WP_DEBUG_DISPLAY') || WP_DEBUG_DISPLAY);
    $checks[] = $display
        ? ['status' => 'warn', 'label' => 'Les erreurs PHP s\'affichent aux visiteurs (WP_DEBUG_DISPLAY)']
        : ['status' => 'ok', 'label' => 'Erreurs PHP masquées aux visiteurs'];

    $checks[] = version_compare(PHP_VERSION, '8.2', '<')
        ? ['status' => 'warn', 'label' => sprintf('PHP %s n\'est plus maintenu : demandez une version récente à l\'hébergeur', PHP_VERSION)]
        : ['status' => 'ok', 'label' => sprintf('PHP %s maintenu', PHP_VERSION)];

    return $checks;
}
