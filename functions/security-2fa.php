<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Double authentification : après le mot de passe, un code à 6 chiffres donné par une application
 * d'authentification (TOTP, RFC 6238 : Google Authenticator, Microsoft Authenticator, 1Password…).
 * Tout est calculé ici, sans service tiers ; le QR code est dessiné dans le navigateur (assets/qrcode-generator).
 *
 * Chaque compte l'active depuis son profil (functions/security-2fa-profile.php), une fois le réglage activé
 * dans l'onglet Sécurité. Il peut être imposé à certains rôles.
 *
 * Téléphone perdu : un code de secours remplace le code de l'application. En dernier recours, ajouter dans wp-config.php
 *     define('OPTIMIZR_DISABLE_2FA', true);
 * pour se connecter avec le seul mot de passe.
 *
 * Métadonnées des comptes :
 * - haptic_2fa         : ['secret' => clé en base 32, 'since' => horodatage, 'backup' => [empreintes], 'last_step' => dernier code utilisé]
 * - haptic_2fa_pending : clé proposée pendant la mise en place, avant le premier code
 * - haptic_2fa_login   : connexion en attente du code (empreinte du jeton, échéance, essais, redirection)
 */

const HAPTIC_2FA_PERIOD = 30;
const HAPTIC_2FA_DIGITS = 6;
/** Durée pour saisir le code après le mot de passe. */
const HAPTIC_2FA_LOGIN_TTL = 10 * MINUTE_IN_SECONDS;
/** Codes faux acceptés pour une même saisie du mot de passe. */
const HAPTIC_2FA_MAX_ATTEMPTS = 5;
const HAPTIC_2FA_BACKUP_COUNT = 10;

/* ---------- Réglages ---------- */

/** Fonction proposée aux comptes (réglage de l'onglet Sécurité), sauf constante de secours. */
function haptic_2fa_available()
{
    return haptic_sec_option('two_factor') && !(defined('OPTIMIZR_DISABLE_2FA') && OPTIMIZR_DISABLE_2FA);
}

/** Rôles qui doivent mettre en place la double authentification. */
function haptic_2fa_required_roles()
{
    $roles = haptic_sec_option('two_factor_roles', []);
    return is_array($roles) ? array_values($roles) : [];
}

function haptic_2fa_config($user_id)
{
    $config = get_user_meta($user_id, 'haptic_2fa', true);
    return is_array($config) && !empty($config['secret']) ? $config : null;
}

/** Le compte a activé la double authentification (et la fonction est disponible). */
function haptic_2fa_is_enabled_for($user)
{
    return $user instanceof WP_User && haptic_2fa_available() && haptic_2fa_config($user->ID) !== null;
}

/** Le rôle du compte impose la double authentification. */
function haptic_2fa_is_required_for($user)
{
    return $user instanceof WP_User && haptic_2fa_available() && array_intersect((array) $user->roles, haptic_2fa_required_roles());
}

/** Compte qui doit encore la mettre en place : il n'a accès qu'à son profil. */
function haptic_2fa_setup_required($user)
{
    return haptic_2fa_is_required_for($user) && haptic_2fa_config($user->ID) === null;
}

/**
 * Un code est demandé après le mot de passe pour cette connexion.
 * Filtre haptic_sec_two_factor_bypass : une connexion qui vaut déjà deux facteurs (passkey d'Optimizr Pro) s'en dispense.
 */
function haptic_2fa_challenge_needed($user)
{
    return haptic_2fa_is_enabled_for($user) && !apply_filters('haptic_sec_two_factor_bypass', false, $user);
}

/* ---------- Codes TOTP (RFC 6238) ---------- */

function haptic_2fa_base32_encode($binary)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($binary) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function haptic_2fa_base32_decode($text)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $text = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', (string) $text));
    $bits = '';
    foreach (str_split($text) as $char) {
        $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

/** Nouvelle clé secrète : 160 bits, la taille recommandée pour HMAC-SHA1. */
function haptic_2fa_generate_secret()
{
    return haptic_2fa_base32_encode(random_bytes(20));
}

/** Code à 6 chiffres pour un intervalle de 30 secondes donné. */
function haptic_2fa_totp($secret, $step)
{
    // Compteur sur 8 octets, gros-boutiste (pack('J') demanderait PHP 64 bits)
    $counter = pack('N', (int) floor($step / 4294967296)) . pack('N', $step & 0xFFFFFFFF);
    $hash = hash_hmac('sha1', $counter, haptic_2fa_base32_decode($secret), true);
    $offset = ord($hash[19]) & 0x0F;
    $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    return str_pad((string) ($value % (10 ** HAPTIC_2FA_DIGITS)), HAPTIC_2FA_DIGITS, '0', STR_PAD_LEFT);
}

/**
 * Vérifie un code de l'application : l'intervalle courant, le précédent et le suivant sont acceptés
 * (horloge du téléphone légèrement décalée), jamais un intervalle déjà utilisé (code intercepté et rejoué).
 *
 * @return int|false intervalle du code, ou false
 */
function haptic_2fa_check_totp($secret, $code, $last_step = 0)
{
    $code = preg_replace('/\D/', '', (string) $code);
    if (strlen($code) !== HAPTIC_2FA_DIGITS) {
        return false;
    }
    $now = (int) floor(time() / HAPTIC_2FA_PERIOD);
    foreach ([$now, $now - 1, $now + 1] as $step) {
        if ($step > $last_step && hash_equals(haptic_2fa_totp($secret, $step), $code)) {
            return $step;
        }
    }
    return false;
}

/** Nouveaux codes de secours : affichés une seule fois, seules leurs empreintes sont enregistrées. */
function haptic_2fa_new_backup_codes($user_id)
{
    $config = haptic_2fa_config($user_id);
    if (!$config) {
        return [];
    }
    $codes = [];
    $hashes = [];
    for ($i = 0; $i < HAPTIC_2FA_BACKUP_COUNT; $i++) {
        $code = str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $codes[] = substr($code, 0, 5) . '-' . substr($code, 5);
        $hashes[] = wp_hash_password($code);
    }
    $config['backup'] = $hashes;
    update_user_meta($user_id, 'haptic_2fa', $config);
    return $codes;
}

/**
 * Vérifie un code saisi par le compte : code de l'application ou code de secours (alors consommé).
 *
 * @return string|false 'totp', 'backup' ou false
 */
function haptic_2fa_verify_code($user_id, $code)
{
    $config = haptic_2fa_config($user_id);
    if (!$config) {
        return false;
    }

    $step = haptic_2fa_check_totp($config['secret'], $code, (int) ($config['last_step'] ?? 0));
    if ($step !== false) {
        $config['last_step'] = $step;
        update_user_meta($user_id, 'haptic_2fa', $config);
        return 'totp';
    }

    $digits = preg_replace('/\D/', '', (string) $code);
    if (strlen($digits) === 10) {
        foreach ((array) ($config['backup'] ?? []) as $i => $hash) {
            if (wp_check_password($digits, $hash)) {
                unset($config['backup'][$i]);
                $config['backup'] = array_values($config['backup']);
                update_user_meta($user_id, 'haptic_2fa', $config);
                return 'backup';
            }
        }
    }
    return false;
}

/** Désactive la double authentification d'un compte. */
function haptic_2fa_disable($user_id)
{
    delete_user_meta($user_id, 'haptic_2fa');
    delete_user_meta($user_id, 'haptic_2fa_pending');
    delete_user_meta($user_id, 'haptic_2fa_login');
}

/** Adresse lue par l'application d'authentification (contenu du QR code). */
function haptic_2fa_otpauth_uri($user, $secret)
{
    $issuer = trim(str_replace(':', '', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)));
    if ($issuer === '') {
        $issuer = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    }
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $user->user_login)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=' . HAPTIC_2FA_DIGITS . '&period=' . HAPTIC_2FA_PERIOD;
}

/* ---------- Connexion ---------- */

/** Page de saisie du code, sur l'adresse de connexion (personnalisée ou wp-login.php). */
function haptic_2fa_login_url($scheme = 'login')
{
    return site_url('wp-login.php?action=haptic_2fa', $scheme);
}

function haptic_2fa_cookie_name()
{
    return 'haptic_2fa_' . COOKIEHASH;
}

function haptic_2fa_set_cookie($value, $expire)
{
    $secure = 'https' === wp_parse_url(wp_login_url(), PHP_URL_SCHEME);
    foreach (array_unique([COOKIEPATH, SITECOOKIEPATH]) as $path) {
        setcookie(haptic_2fa_cookie_name(), $value, [
            'expires'  => $expire,
            'path'     => $path,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

// Sessions ouvertes par le mot de passe : annulées si un code est encore attendu
add_action('set_logged_in_cookie', function ($cookie, $expire, $expiration, $user_id, $scheme, $token) {
    $GLOBALS['haptic_2fa_new_tokens'][$user_id][] = $token;
}, 10, 6);

/*
 * Mot de passe correct pour un compte protégé : la connexion est suspendue jusqu'au code.
 * En dernière position sur wp_login, quel que soit le formulaire (wp-login.php, compte client d'une boutique…).
 * Le jeton de la connexion en attente est dans un cookie (jamais dans l'adresse), son empreinte sur le compte.
 */
add_action('wp_login', function ($login, $user) {
    if (!haptic_2fa_challenge_needed($user)) {
        return;
    }

    foreach ($GLOBALS['haptic_2fa_new_tokens'][$user->ID] ?? [] as $token) {
        WP_Session_Tokens::get_instance($user->ID)->destroy($token);
    }
    wp_clear_auth_cookie();
    wp_set_current_user(0);

    // phpcs:disable WordPress.Security.NonceVerification -- formulaire de connexion de WordPress, sans nonce : seuls la redirection et « Se souvenir de moi » sont repris
    $redirect = isset($_REQUEST['redirect_to']) ? wp_sanitize_redirect(wp_unslash($_REQUEST['redirect_to'])) : '';
    $remember = !empty($_REQUEST['rememberme']);
    $interim = isset($_REQUEST['interim-login']);
    // phpcs:enable WordPress.Security.NonceVerification

    $token = wp_generate_password(40, false);
    update_user_meta($user->ID, 'haptic_2fa_login', [
        'token'    => hash('sha256', $token),
        'expires'  => time() + HAPTIC_2FA_LOGIN_TTL,
        'attempts' => 0,
        'remember' => $remember,
        'redirect' => $redirect,
        'interim'  => $interim,
    ]);
    haptic_2fa_set_cookie($user->ID . '|' . $token, time() + HAPTIC_2FA_LOGIN_TTL);

    wp_safe_redirect($interim ? add_query_arg('interim-login', 1, haptic_2fa_login_url()) : haptic_2fa_login_url());
    exit;
}, PHP_INT_MAX, 2);

/**
 * Connexion en attente du code pour ce navigateur.
 *
 * @return array|null ['user' => WP_User, 'challenge' => array]
 */
function haptic_2fa_pending_login()
{
    $cookie = sanitize_text_field(wp_unslash($_COOKIE[haptic_2fa_cookie_name()] ?? ''));
    if (!preg_match('/^(\d+)\|([A-Za-z0-9]{40})$/', $cookie, $m)) {
        return null;
    }
    $user = get_user_by('id', (int) $m[1]);
    $challenge = $user ? get_user_meta($user->ID, 'haptic_2fa_login', true) : null;
    if (!is_array($challenge) || empty($challenge['token']) || !hash_equals($challenge['token'], hash('sha256', $m[2]))) {
        return null;
    }
    if ((int) $challenge['expires'] < time() || !haptic_2fa_is_enabled_for($user)) {
        delete_user_meta($user->ID, 'haptic_2fa_login');
        return null;
    }
    return ['user' => $user, 'challenge' => $challenge];
}

function haptic_2fa_end_pending_login($user_id)
{
    delete_user_meta($user_id, 'haptic_2fa_login');
    haptic_2fa_set_cookie('', time() - YEAR_IN_SECONDS);
}

// Retour au formulaire de connexion après un code expiré ou trop d'essais
add_filter('wp_login_errors', function ($errors) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple message affiché
    $reason = sanitize_key(wp_unslash($_GET['haptic_2fa'] ?? ''));
    if ($reason === 'expired') {
        $errors->add('haptic_2fa_expired', __('The time to enter the code has run out. Log in again.', 'optimizr'), 'message');
    } elseif ($reason === 'attempts') {
        $errors->add('haptic_2fa_attempts', __('Too many wrong codes. Log in again.', 'optimizr'));
    }
    return $errors;
});

add_action('login_form_haptic_2fa', 'haptic_2fa_login_page');

function haptic_2fa_login_page()
{
    global $interim_login;

    $pending = haptic_2fa_pending_login();
    if (!$pending) {
        wp_safe_redirect(add_query_arg('haptic_2fa', 'expired', wp_login_url()));
        exit;
    }
    $user = $pending['user'];
    $challenge = $pending['challenge'];
    $interim_login = !empty($challenge['interim']);
    $errors = new WP_Error();

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- le jeton de la connexion en attente (cookie) tient lieu de nonce
    if (isset($_POST['haptic_2fa_code'])) {
        $minutes = function_exists('haptic_sec_lockout_minutes') ? haptic_sec_lockout_minutes() : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $code = sanitize_text_field(wp_unslash($_POST['haptic_2fa_code']));

        if ($minutes) {
            $errors = haptic_sec_lockout_error($minutes);
        } elseif (haptic_2fa_verify_code($user->ID, $code)) {
            haptic_2fa_end_pending_login($user->ID);
            wp_set_auth_cookie($user->ID, !empty($challenge['remember']));
            wp_set_current_user($user->ID);
            do_action('haptic_sec_login', $user);
            haptic_2fa_finish_login($user, (string) $challenge['redirect']);
        } else {
            $challenge['attempts'] = (int) $challenge['attempts'] + 1;
            // Compte comme un mot de passe faux pour la limitation des tentatives
            do_action('wp_login_failed', $user->user_login, new WP_Error('haptic_2fa_invalid'));

            if ($challenge['attempts'] >= HAPTIC_2FA_MAX_ATTEMPTS) {
                haptic_2fa_end_pending_login($user->ID);
                // Type d'évènement enregistré tel quel (clé du journal), libellé traduit à l'affichage
                /* translators: %s: username */
                haptic_sec_log('Double authentification', sprintf(__('Account %s: too many wrong codes', 'optimizr'), $user->user_login));
                wp_safe_redirect(add_query_arg('haptic_2fa', 'attempts', wp_login_url()));
                exit;
            }
            update_user_meta($user->ID, 'haptic_2fa_login', $challenge);
            $errors->add('haptic_2fa_invalid', __('Invalid code. Check that your phone is set to the right time, then try again.', 'optimizr'));
        }
    }

    login_header(__('Two-factor authentication', 'optimizr'), '<p class="message">' . esc_html__('Enter the 6-digit code shown by your authentication app.', 'optimizr') . '</p>', $errors);
    ?>
    <form name="haptic_2fa_form" id="loginform" action="<?php echo esc_url(add_query_arg($interim_login ? ['interim-login' => 1] : [], haptic_2fa_login_url('login_post'))); ?>" method="post" autocomplete="off">
        <p>
            <label for="haptic_2fa_code"><?php esc_html_e('Authentication code', 'optimizr'); ?></label>
            <input type="text" name="haptic_2fa_code" id="haptic_2fa_code" class="input" value="" size="20" inputmode="numeric" autocomplete="one-time-code" spellcheck="false" autofocus required>
        </p>
        <p class="description" style="margin-bottom:16px"><?php esc_html_e('Lost your phone? Enter one of your backup codes instead.', 'optimizr'); ?></p>
        <p class="submit">
            <input type="submit" class="button button-primary button-large" value="<?php esc_attr_e('Verify', 'optimizr'); ?>">
        </p>
    </form>
    <?php if (!$interim_login) : ?>
        <p id="nav"><a href="<?php echo esc_url(wp_login_url()); ?>"><?php esc_html_e('Back to login', 'optimizr'); ?></a></p>
    <?php endif; ?>
    <?php
    login_footer('haptic_2fa_code');
    exit;
}

/** Fin de la connexion après le code, comme wp-login.php après le mot de passe. */
function haptic_2fa_finish_login($user, $requested)
{
    global $interim_login;

    if ($interim_login) {
        // Fenêtre « Session expirée » de l'administration : elle se ferme en voyant interim-login-success
        $interim_login = 'success';
        login_header('', '<p class="message">' . esc_html__('You have logged in successfully.', 'optimizr') . '</p>');
        echo '</div>';
        do_action('login_footer');
        echo '</body></html>';
        exit;
    }

    $redirect = $requested !== '' ? $requested : admin_url();
    // Filtre de WordPress : même redirection qu'une connexion par mot de passe
    $redirect = apply_filters('login_redirect', $redirect, $requested, $user);
    if (($redirect === '' || $redirect === admin_url()) && !$user->has_cap('edit_posts')) {
        $redirect = $user->has_cap('read') ? admin_url('profile.php') : home_url();
    }
    wp_safe_redirect($redirect ?: admin_url());
    exit;
}

/*
 * XML-RPC avec le mot de passe du compte : il contournerait le code. Refusé pour un compte protégé.
 * Les mots de passe d'application restent acceptés : propres à une application et révocables (Optimizr Pro peut les désactiver).
 */
add_filter('authenticate', function ($user) {
    if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST && $user instanceof WP_User
        && !did_action('application_password_did_authenticate') && haptic_2fa_is_enabled_for($user)) {
        return new WP_Error('haptic_2fa_xmlrpc', __('This account uses two-factor authentication: use an application password.', 'optimizr'));
    }
    return $user;
}, 200);

/* ---------- Mise en place obligatoire ---------- */

// Rôle concerné sans double authentification : seul le profil reste accessible, jusqu'à sa mise en place
add_action('admin_init', function () {
    global $pagenow;
    if (wp_doing_ajax() || in_array($pagenow, ['profile.php', 'admin-post.php'], true) || !haptic_2fa_setup_required(wp_get_current_user())) {
        return;
    }
    wp_safe_redirect(admin_url('profile.php#optimizr-2fa'));
    exit;
});
