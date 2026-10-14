<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Module Sécurité : protection de la connexion.
 * Optimizr Pro ajoute la discrétion, la désactivation des fonctions inutilisées, les en-têtes et le bilan.
 *
 * Toutes les protections sont désactivées par défaut et s'activent une à une dans l'onglet Sécurité.
 *
 * Adresse de connexion personnalisée : en cas de blocage, ajouter dans wp-config.php
 *     define('OPTIMIZR_DISABLE_CUSTOM_LOGIN', true);
 * pour revenir à wp-login.php.
 *
 * Double authentification : functions/security-2fa.php (connexion) et functions/security-2fa-profile.php (profil).
 */

/* ---------- Outils ---------- */

function haptic_sec_option($name, $default = 0)
{
    return get_option('haptic_sec_' . $name, $default);
}

/**
 * Adresse IP du visiteur.
 *
 * REMOTE_ADDR par défaut : les en-têtes de proxy sont falsifiables. Ils ne sont lus que pour une requête
 * qui vient d'un proxy de confiance (réglage « Proxys de confiance »), sinon tous les visiteurs d'un site
 * derrière Cloudflare ou un répartiteur de charge auraient la même adresse.
 */
function haptic_sec_client_ip()
{
    $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '0.0.0.0';
    }
    $proxies = haptic_sec_trusted_proxies();
    if (!haptic_sec_ip_in_ranges($ip, $proxies)) {
        return $ip;
    }

    // Cloudflare remplace toujours cet en-tête : fiable seulement si la requête vient directement de ses serveurs
    if (haptic_sec_option('proxy_cloudflare') && haptic_sec_ip_in_ranges($ip, haptic_sec_cloudflare_ranges())) {
        $cf = trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '')));
        if (filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
    }

    // « visiteur, proxy 1, proxy 2 » : chaque proxy ajoute à droite l'adresse qui l'a contacté, le début de la liste est falsifiable.
    // On remonte depuis la droite jusqu'à la première adresse qui n'est pas un proxy de confiance.
    $forwarded = array_map('trim', explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))));
    foreach (array_reverse($forwarded) as $candidate) {
        if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
            break;
        }
        if (!haptic_sec_ip_in_ranges($candidate, $proxies)) {
            return $candidate;
        }
    }
    return $ip;
}

/** Adresse IP seule ou plage CIDR (10.0.0.0/8, 2001:db8::/32). */
function haptic_sec_is_ip_range($range)
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

/** L'adresse appartient-elle à l'une des plages (adresses seules ou CIDR, cf. haptic_sec_is_ip_range) ? */
function haptic_sec_ip_in_ranges($ip, $ranges)
{
    $packed = filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : false;
    if ($packed === false) {
        return false;
    }
    foreach ($ranges as $range) {
        if (!haptic_sec_is_ip_range($range)) {
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
function haptic_sec_cloudflare_ranges()
{
    return apply_filters('haptic_sec_cloudflare_ranges', [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ]);
}

/** Proxys de confiance : plages saisies (une par ligne), et celles de Cloudflare si l'option est active. Vide par défaut. */
function haptic_sec_trusted_proxies()
{
    $ranges = array_filter(array_map('trim', preg_split('/\R/', (string) haptic_sec_option('trusted_proxies', ''))), 'haptic_sec_is_ip_range');
    if (haptic_sec_option('proxy_cloudflare')) {
        $ranges = array_merge($ranges, haptic_sec_cloudflare_ranges());
    }
    return array_values($ranges);
}

/** Adresses IP jamais limitées (une par ligne). */
function haptic_sec_whitelist()
{
    $lines = preg_split('/\R/', (string) haptic_sec_option('login_whitelist', ''));
    return array_values(array_filter(array_map('trim', $lines), function ($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP);
    }));
}

/**
 * Journal des évènements de sécurité (100 derniers, 30 jours maximum).
 * Les adresses IP sont des données personnelles : la durée de conservation est volontairement courte.
 */
function haptic_sec_log($event, $detail = '')
{
    $log = haptic_sec_get_log();
    array_unshift($log, ['time' => time(), 'ip' => haptic_sec_client_ip(), 'event' => $event, 'detail' => $detail]);
    update_option('haptic_security_log', array_slice($log, 0, 100), false);
}

/** Évènements des 30 derniers jours. Les plus anciens sont supprimés de la base au passage, même sans nouvel évènement. */
function haptic_sec_get_log()
{
    $stored = array_values((array) get_option('haptic_security_log', []));
    $limit = time() - 30 * DAY_IN_SECONDS;
    $log = array_values(array_filter($stored, function ($entry) use ($limit) {
        return ($entry['time'] ?? 0) >= $limit;
    }));
    if ($log !== $stored) {
        update_option('haptic_security_log', $log, false);
    }
    return $log;
}
// Purge quotidienne (tâche planifiée de WordPress), même si personne n'ouvre l'onglet Sécurité
add_action('wp_scheduled_delete', 'haptic_sec_get_log');

/* ---------- Adresse de connexion personnalisée ---------- */

/** Identifiant de l'adresse de connexion, ou chaîne vide si la fonction est inactive. */
function haptic_sec_login_slug()
{
    if ((defined('OPTIMIZR_DISABLE_CUSTOM_LOGIN') && OPTIMIZR_DISABLE_CUSTOM_LOGIN) || is_multisite()) {
        return '';
    }
    return (string) haptic_sec_option('login_slug', '');
}

/** Nouvelle adresse de connexion (avec permaliens simples : https://site/?identifiant). */
function haptic_sec_login_url($scheme = null, $slug = null)
{
    $slug = $slug ?? haptic_sec_login_slug();
    if (get_option('permalink_structure')) {
        return home_url('/' . $slug . '/', $scheme);
    }
    return home_url('/', $scheme) . '?' . $slug;
}

/** Chemin demandé, relatif à l'installation (« » pour l'accueil). */
function haptic_sec_request_path()
{
    $path = trim((string) wp_parse_url(wp_strip_all_tags(wp_unslash($_SERVER['REQUEST_URI'] ?? '')), PHP_URL_PATH), '/');
    $home = trim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home !== '' && strpos($path, $home) === 0) {
        $path = ltrim(substr($path, strlen($home)), '/');
    }
    return $path;
}

function haptic_sec_is_custom_login_request()
{
    $slug = haptic_sec_login_slug();
    $path = haptic_sec_request_path();

    if (get_option('permalink_structure')) {
        return $path === $slug;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- adresse de connexion personnalisée (?slug sans permaliens), seulement testée
    return in_array($path, ['', 'index.php'], true) && isset($_GET[$slug]);
}

function haptic_sec_is_wp_login_request()
{
    return basename(haptic_sec_request_path()) === 'wp-login.php';
}

/** Page introuvable : modèle 404 du thème côté site, page minimale côté administration. */
function haptic_sec_not_found()
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

    echo '<!doctype html><html lang="' . esc_attr(get_bloginfo('language')) . '"><meta charset="utf-8"><title>' . esc_html__('Page not found', 'optimizr') . '</title><p>' . esc_html__('Page not found.', 'optimizr') . '</p><p><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Back to the home page', 'optimizr') . '</a></p></html>';
    exit;
}

/** Remplace wp-login.php par la nouvelle adresse dans une URL (paramètres conservés). */
function haptic_sec_replace_login_url($url, $scheme = null)
{
    if (strpos($url, 'wp-login.php') === false || !haptic_sec_login_slug()) {
        return $url;
    }
    $parts = explode('?', $url, 2);
    $new = haptic_sec_login_url($scheme);
    if (!empty($parts[1])) {
        $new .= (strpos($new, '?') === false ? '?' : '&') . $parts[1];
    }
    return $new;
}

if (haptic_sec_login_slug()) {
    // Liens générés par WordPress : connexion, déconnexion, mot de passe perdu, emails, formulaire des contenus protégés
    add_filter('site_url', function ($url, $path, $scheme) {
        return haptic_sec_replace_login_url($url, $scheme);
    }, 10, 3);
    add_filter('network_site_url', function ($url, $path, $scheme) {
        return haptic_sec_replace_login_url($url, $scheme);
    }, 10, 3);
    add_filter('wp_redirect', 'haptic_sec_replace_login_url');

    // Raccourcis /login, /admin, /dashboard : ils redirigeraient vers la nouvelle adresse et la révéleraient
    remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);

    add_action('wp_loaded', function () {
        global $pagenow;

        if (haptic_sec_is_custom_login_request()) {
            haptic_sec_load_login_page();
        }

        if (is_user_logged_in()) {
            return;
        }

        // wp-login.php et l'administration n'existent plus pour les visiteurs non connectés
        if (haptic_sec_is_wp_login_request()) {
            haptic_sec_not_found();
        }
        if (is_admin() && !wp_doing_ajax() && !wp_doing_cron() && $pagenow !== 'admin-post.php') {
            haptic_sec_not_found();
        }
    }, 1);
}

function haptic_sec_load_login_page()
{
    // wp-login.php s'attend à des variables globales
    global $error, $interim_login, $action, $user_login, $pagenow;
    $pagenow = 'wp-login.php';
    require_once ABSPATH . 'wp-login.php';
    exit;
}

/** Valide l'identifiant de connexion (lettres, chiffres, tirets ; 4 caractères minimum, non réservé). */
function haptic_sec_sanitize_login_slug($value)
{
    $value = sanitize_title((string) $value);
    if ($value === '') {
        return '';
    }

    $reserved = ['wp-admin', 'wp-login', 'wp-login-php', 'admin', 'login', 'dashboard', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'xmlrpc', 'sitemap'];
    $error = '';
    if (strlen($value) < 4) {
        $error = __('The login address must be at least 4 characters long.', 'optimizr');
    } elseif (in_array($value, $reserved, true)) {
        $error = __('This login address is reserved by WordPress.', 'optimizr');
    } elseif (get_page_by_path($value, OBJECT, get_post_types(['public' => true]))) {
        $error = __('Some content on the site already uses this address.', 'optimizr');
    }

    if ($error) {
        // Fonction de l'administration : absente si l'option est enregistrée depuis un autre contexte
        if (function_exists('add_settings_error')) {
            add_settings_error('haptic_sec_login_slug', 'haptic_sec_login_slug', $error);
        }
        return (string) get_option('haptic_sec_login_slug', '');
    }

    return $value;
}

// Nouvelle adresse envoyée par email : on ne la perd pas
function haptic_sec_mail_login_url($old, $new)
{
    if ($new === '' || $new === $old) {
        return;
    }
    wp_mail(
        get_option('admin_email'),
        /* translators: %s: site name */
        sprintf(__('[%s] New login address', 'optimizr'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)),
        sprintf(
            /* translators: %s: new login URL */
            __("The admin login address has been changed.\n\nNew address: %s\n\nThe old address (wp-login.php) now shows a not-found page.\nKeep this email. If you get locked out, add define('OPTIMIZR_DISABLE_CUSTOM_LOGIN', true); to wp-config.php.", 'optimizr'),
            haptic_sec_login_url(null, $new)
        )
    );
}
add_action('update_option_haptic_sec_login_slug', 'haptic_sec_mail_login_url', 10, 2);
add_action('add_option_haptic_sec_login_slug', function ($option, $value) {
    haptic_sec_mail_login_url('', $value);
}, 10, 2);

/* ---------- Tentatives de connexion ---------- */

function haptic_sec_lock_key($ip)
{
    return 'haptic_sec_lock_' . md5($ip);
}

function haptic_sec_fail_key($ip)
{
    return 'haptic_sec_fail_' . md5($ip);
}

/** Minutes de verrouillage restantes pour l'adresse du visiteur (0 si elle n'est pas verrouillée ou si la limitation est inactive). */
function haptic_sec_lockout_minutes()
{
    if (!haptic_sec_option('limit_login')) {
        return 0;
    }
    $ip = haptic_sec_client_ip();
    if (in_array($ip, haptic_sec_whitelist(), true)) {
        return 0;
    }
    $until = (int) get_transient(haptic_sec_lock_key($ip));
    return $until > time() ? max(1, (int) ceil(($until - time()) / 60)) : 0;
}

function haptic_sec_lockout_error($minutes)
{
    return new WP_Error('haptic_sec_locked', sprintf(
        /* translators: %s: number of minutes */
        _n('Too many login attempts. Try again in %s minute.', 'Too many login attempts. Try again in %s minutes.', $minutes, 'optimizr'),
        number_format_i18n($minutes)
    ));
}

/*
 * Connexion terminée : action haptic_sec_login, déclenchée à la place de wp_login pour les protections du module.
 * Pour un compte protégé par la double authentification, elle ne l'est qu'une fois le code validé (functions/security-2fa.php).
 */
add_action('wp_login', function ($login, $user) {
    if (function_exists('haptic_2fa_challenge_needed') && haptic_2fa_challenge_needed($user)) {
        return;
    }
    do_action('haptic_sec_login', $user);
}, 10, 2);

if (haptic_sec_option('limit_login')) {
    // Après toutes les vérifications d'identifiants : une adresse verrouillée est refusée, même avec le bon mot de passe
    add_filter('authenticate', function ($user) {
        $minutes = haptic_sec_lockout_minutes();
        return $minutes ? haptic_sec_lockout_error($minutes) : $user;
    }, 99);

    add_action('wp_login_failed', function ($username) {
        $ip = haptic_sec_client_ip();
        if (in_array($ip, haptic_sec_whitelist(), true) || get_transient(haptic_sec_lock_key($ip))) {
            return;
        }

        $max = max(1, (int) haptic_sec_option('login_max', 5));
        $lockout = max(1, (int) haptic_sec_option('login_lockout', 15)) * MINUTE_IN_SECONDS;
        $fails = (int) get_transient(haptic_sec_fail_key($ip)) + 1;

        if ($fails >= $max) {
            delete_transient(haptic_sec_fail_key($ip));
            set_transient(haptic_sec_lock_key($ip), time() + $lockout, $lockout);
            // Type d'évènement enregistré tel quel (clé du journal), libellé traduit à l'affichage ; détail traduit à l'écriture
            /* translators: 1: number of failed attempts, 2: last username tried */
            haptic_sec_log('Verrouillage', sprintf(_n('%1$s failed attempt, last username tried: %2$s', '%1$s failed attempts, last username tried: %2$s', $fails, 'optimizr'), number_format_i18n($fails), sanitize_user((string) $username)));
        } else {
            set_transient(haptic_sec_fail_key($ip), $fails, $lockout);
        }
    });

    // Pas dès le mot de passe quand un code de double authentification est encore attendu : les échecs de code comptent aussi
    add_action('haptic_sec_login', function () {
        delete_transient(haptic_sec_fail_key(haptic_sec_client_ip()));
    });
}

// Message neutre : ne révèle pas si l'identifiant existe
if (haptic_sec_option('generic_errors')) {
    add_filter('authenticate', function ($user) {
        if (is_wp_error($user) && array_intersect($user->get_error_codes(), ['invalid_username', 'invalid_email', 'incorrect_password'])) {
            return new WP_Error('haptic_sec_invalid', __('Incorrect username or password.', 'optimizr'));
        }
        return $user;
    }, 100);
}

// Alerte lors d'une connexion administrateur depuis une adresse IP inconnue
if (haptic_sec_option('login_alert')) {
    add_action('haptic_sec_login', function ($user) {
        if (!user_can($user, 'manage_options')) {
            return;
        }
        $hash = wp_hash(haptic_sec_client_ip());
        // get_user_meta() renvoie « » pour un compte jamais suivi : (array) « » donnerait [''], pris pour une adresse connue
        $known = array_values(array_filter((array) get_user_meta($user->ID, 'haptic_sec_known_ips', true)));

        if (in_array($hash, $known, true)) {
            return;
        }
        // Première connexion suivie : on mémorise sans alerter
        if ($known) {
            wp_mail(
                $user->user_email,
                /* translators: %s: site name */
                sprintf(__('[%s] Administrator login from a new address', 'optimizr'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)),
                sprintf(
                    /* translators: 1: username, 2: date and time, 3: IP address, 4: browser user agent */
                    __("The account \"%1\$s\" has just logged in from an unknown IP address.\n\nDate: %2\$s\nIP address: %3\$s\nBrowser: %4\$s\n\nIf this wasn't you, change your password immediately.", 'optimizr'),
                    $user->user_login,
                    /* translators: date format in the login alert email, see https://www.php.net/manual/datetime.format.php */
                    wp_date(__('F j, Y \a\t H:i', 'optimizr')),
                    haptic_sec_client_ip(),
                    sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? __('unknown', 'optimizr')))
                )
            );
            // Type d'évènement enregistré tel quel (clé du journal), libellé traduit à l'affichage
            /* translators: %s: username */
            haptic_sec_log('Alerte de connexion', sprintf(__('Account %s', 'optimizr'), $user->user_login));
        }
        array_unshift($known, $hash);
        update_user_meta($user->ID, 'haptic_sec_known_ips', array_slice(array_values(array_filter($known)), 0, 20));
    });
}
