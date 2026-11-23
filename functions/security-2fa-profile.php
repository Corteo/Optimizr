<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Double authentification : mise en place depuis le profil (functions/security-2fa.php pour la connexion).
 * Chacun l'active pour son propre compte ; un administrateur peut la réinitialiser pour un autre compte (téléphone perdu).
 * La mise en place (haptic_2fa_setup_markup) sert aussi à l'écran proposé à la connexion (functions/security-welcome.php).
 *
 * Opérations sensibles (nouveaux codes de secours, changement de méthode, désactivation) : un code est demandé,
 * pour qu'une session volée ne suffise pas à retirer ou contourner la protection.
 */

add_action('show_user_profile', 'haptic_2fa_profile_section', 5);
add_action('edit_user_profile', 'haptic_2fa_profile_section', 5);

function haptic_2fa_profile_section($user)
{
    if (!haptic_2fa_available()) {
        return;
    }
    $own = (int) $user->ID === get_current_user_id();
    $config = haptic_2fa_config($user->ID);
    $changing = $own && $config && haptic_2fa_change_allowed($user->ID);
    haptic_2fa_enqueue_assets($user->ID);
    ?>
    <h2 id="optimizr-2fa"><?php esc_html_e('Two-factor authentication', 'optimizr'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Second step', 'optimizr'); ?></th>
            <td>
                <div class="optimizr-2fa" data-user="<?php echo esc_attr($user->ID); ?>">
                    <?php if ($config) : ?>
                        <p>
                            <span class="optimizr-2fa__status optimizr-2fa__status--on"><?php esc_html_e('Enabled', 'optimizr'); ?></span>
                            <?php /* translators: %s: date two-factor authentication was enabled */ echo esc_html(sprintf(__('since %s', 'optimizr'), wp_date(get_option('date_format'), (int) ($config['since'] ?? time())))); ?>
                            —
                            <?php
                            echo esc_html(haptic_2fa_method($config) === 'email'
                                /* translators: %s: masked email address */
                                ? sprintf(__('code sent by email to %s', 'optimizr'), haptic_2fa_mask_email($config['email']))
                                : __('authentication app', 'optimizr'));
                            ?>
                        </p>
                        <?php $left = count((array) ($config['backup'] ?? [])); ?>
                        <p class="description"><?php /* translators: %s: number of unused backup codes */ echo esc_html(sprintf(_n('%s backup code left.', '%s backup codes left.', $left, 'optimizr'), number_format_i18n($left))); ?></p>

                        <?php if ($changing) : ?>
                            <p><?php esc_html_e('Choose the new method. The current one keeps working until the new one is enabled.', 'optimizr'); ?></p>
                            <?php haptic_2fa_setup_markup($user); ?>
                        <?php elseif ($own) : ?>
                            <div class="optimizr-2fa__confirm">
                                <p>
                                    <label for="optimizr-2fa-confirm-code"><?php
                                        echo esc_html(haptic_2fa_method($config) === 'email'
                                            ? __('To change these settings, enter a code received by email, or a backup code:', 'optimizr')
                                            : __('To change these settings, enter a code from the app, or a backup code:', 'optimizr'));
                                    ?></label><br>
                                    <input type="text" id="optimizr-2fa-confirm-code" class="regular-text optimizr-2fa__code" inputmode="numeric" autocomplete="one-time-code" spellcheck="false">
                                    <?php if (haptic_2fa_method($config) === 'email') : ?>
                                        <button type="button" class="button" data-2fa-action="send" data-purpose="confirm"><?php esc_html_e('Email me a code', 'optimizr'); ?></button>
                                    <?php endif; ?>
                                </p>
                                <p>
                                    <button type="button" class="button" data-2fa-action="backup"><?php esc_html_e('Generate new backup codes', 'optimizr'); ?></button>
                                    <button type="button" class="button" data-2fa-action="change"><?php esc_html_e('Change method', 'optimizr'); ?></button>
                                    <?php if (!haptic_2fa_is_required_for($user)) : ?>
                                        <button type="button" class="button button-link-delete" data-2fa-action="disable"><?php esc_html_e('Disable', 'optimizr'); ?></button>
                                    <?php endif; ?>
                                </p>
                                <?php if (haptic_2fa_is_required_for($user)) : ?>
                                    <p class="description"><?php esc_html_e('Required for your role: it cannot be disabled.', 'optimizr'); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="optimizr-2fa__codes" hidden></div>
                        <?php else : ?>
                            <p><button type="button" class="button" data-2fa-action="reset"><?php esc_html_e('Reset', 'optimizr'); ?></button></p>
                            <p class="description"><?php esc_html_e('If this person has lost their phone and their backup codes: they will log in with their password only, then set it up again.', 'optimizr'); ?></p>
                        <?php endif; ?>

                    <?php elseif ($own) : ?>
                        <p><span class="optimizr-2fa__status"><?php esc_html_e('Not enabled', 'optimizr'); ?></span></p>
                        <p class="description"><?php esc_html_e('After your password, a 6-digit code will be asked. Someone who finds your password still cannot log in.', 'optimizr'); ?></p>
                        <?php haptic_2fa_setup_markup($user); ?>

                    <?php else : ?>
                        <p><span class="optimizr-2fa__status"><?php esc_html_e('Not enabled', 'optimizr'); ?></span></p>
                        <p class="description"><?php esc_html_e('Only the account holder can set it up, from their own profile.', 'optimizr'); ?></p>
                    <?php endif; ?>

                    <?php if (!$config && haptic_2fa_is_required_for($user)) : ?>
                        <p class="description"><strong><?php esc_html_e('Required for this role: until it is set up, the dashboard is limited to the profile.', 'optimizr'); ?></strong></p>
                    <?php endif; ?>
                    <p class="optimizr-2fa__message" role="status" aria-live="polite"></p>
                </div>
            </td>
        </tr>
    </table>
    <?php
}

/** Clé proposée pendant la mise en place de l'application (gardée jusqu'à l'activation). */
function haptic_2fa_pending_secret($user_id)
{
    $secret = (string) get_user_meta($user_id, 'haptic_2fa_pending', true);
    if ($secret === '') {
        $secret = haptic_2fa_generate_secret();
        update_user_meta($user_id, 'haptic_2fa_pending', $secret);
    }
    return $secret;
}

/**
 * Choix de la méthode puis mise en place (application ou email), pour le compte connecté.
 * Pilotée par js/optimizr-2fa.js, dans un bloc .optimizr-2fa.
 */
function haptic_2fa_setup_markup($user)
{
    $secret = haptic_2fa_pending_secret($user->ID);
    $email = haptic_2fa_email_allowed_for($user) && is_email($user->user_email);
    ?>
    <div class="optimizr-2fa__choices">
        <button type="button" class="optimizr-2fa__choice" data-2fa-action="choose" data-method="app">
            <?php echo wp_kses(haptic_icon('phone', 20), haptic_kses_ui()); ?>
            <span class="optimizr-2fa__choice-text">
                <strong><?php esc_html_e('Authentication app', 'optimizr'); ?></strong>
                <span><?php esc_html_e('A code from an app on your phone: Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden… The most secure.', 'optimizr'); ?></span>
            </span>
        </button>
        <?php if ($email) : ?>
            <button type="button" class="optimizr-2fa__choice" data-2fa-action="choose" data-method="email">
                <?php echo wp_kses(haptic_icon('mail', 20), haptic_kses_ui()); ?>
                <span class="optimizr-2fa__choice-text">
                    <strong><?php esc_html_e('Email', 'optimizr'); ?></strong>
                    <span><?php esc_html_e('A code sent to your email address at each login. Nothing to install, but only as safe as your mailbox.', 'optimizr'); ?></span>
                </span>
            </button>
        <?php endif; ?>
    </div>

    <div class="optimizr-2fa__setup" data-method="app" hidden>
        <p><?php esc_html_e('1. In your authentication app, add an account and scan this QR code:', 'optimizr'); ?></p>
        <div class="optimizr-2fa__qr" data-uri="<?php echo esc_attr(haptic_2fa_otpauth_uri($user, $secret)); ?>"></div>
        <p class="description"><?php esc_html_e('Or enter this key manually:', 'optimizr'); ?> <code class="optimizr-2fa__secret"><?php echo esc_html(trim(chunk_split($secret, 4, ' '))); ?></code></p>
        <p>
            <label for="optimizr-2fa-app-code"><?php esc_html_e('2. Enter the code shown by the app:', 'optimizr'); ?></label><br>
            <input type="text" id="optimizr-2fa-app-code" class="regular-text optimizr-2fa__code" inputmode="numeric" autocomplete="one-time-code" spellcheck="false">
            <button type="button" class="button button-primary" data-2fa-action="activate" data-method="app"><?php esc_html_e('Enable', 'optimizr'); ?></button>
        </p>
        <p><button type="button" class="button-link" data-2fa-action="back"><?php esc_html_e('Choose another method', 'optimizr'); ?></button></p>
    </div>

    <?php if ($email) : ?>
        <div class="optimizr-2fa__setup" data-method="email" hidden>
            <p>
                <?php /* translators: %s: email address of the account */ echo wp_kses_post(sprintf(__('1. Receive a code at %s:', 'optimizr'), '<strong>' . esc_html($user->user_email) . '</strong>')); ?>
                <button type="button" class="button" data-2fa-action="send" data-purpose="setup"><?php esc_html_e('Send the code', 'optimizr'); ?></button>
            </p>
            <p>
                <label for="optimizr-2fa-email-code"><?php esc_html_e('2. Enter the code received:', 'optimizr'); ?></label><br>
                <input type="text" id="optimizr-2fa-email-code" class="regular-text optimizr-2fa__code" inputmode="numeric" autocomplete="one-time-code" spellcheck="false">
                <button type="button" class="button button-primary" data-2fa-action="activate" data-method="email"><?php esc_html_e('Enable', 'optimizr'); ?></button>
            </p>
            <p class="description"><?php esc_html_e('Codes will always be sent to this address, even if the account email changes later.', 'optimizr'); ?></p>
            <p><button type="button" class="button-link" data-2fa-action="back"><?php esc_html_e('Choose another method', 'optimizr'); ?></button></p>
        </div>
    <?php endif; ?>

    <div class="optimizr-2fa__codes" hidden></div>
    <?php
}

function haptic_2fa_enqueue_assets($user_id, $context = 'profile')
{
    $js = HAPTIC_OPTIMIZR_PATH . 'js/optimizr-2fa.js';
    wp_enqueue_script('optimizr-qrcode', HAPTIC_OPTIMIZR_URL . 'assets/qrcode-generator/qrcode.js', [], '1.4.4', true);
    wp_enqueue_script('optimizr-2fa', HAPTIC_OPTIMIZR_URL . 'js/optimizr-2fa.js', ['wp-i18n', 'optimizr-qrcode'], filemtime($js), true);
    wp_set_script_translations('optimizr-2fa', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
    wp_localize_script('optimizr-2fa', 'optimizr2fa', [
        'ajaxUrl'  => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('haptic_2fa_' . $user_id),
        'context'  => $context,
        'fileName' => sanitize_file_name(wp_parse_url(home_url(), PHP_URL_HOST) . '-backup-codes.txt'),
    ]);
    // Styles du bloc : quelques règles, pas de feuille dédiée
    wp_register_style('optimizr-2fa', false, [], HAPTIC_OPTIMIZR_VERSION);
    wp_enqueue_style('optimizr-2fa');
    wp_add_inline_style('optimizr-2fa', '
        .optimizr-2fa__status{display:inline-block;padding:2px 8px;border-radius:999px;background:#f0f0f1;font-weight:600}
        .optimizr-2fa__status--on{background:#d1f0d9;color:#0a5c25}
        .optimizr-2fa [hidden]{display:none!important}
        .optimizr-2fa__choices{display:grid;gap:8px;max-width:480px;margin:12px 0}
        .optimizr-2fa__choice{display:flex;gap:12px;align-items:flex-start;width:100%;text-align:left;padding:12px 14px;border:1px solid #c3c4c7;border-radius:6px;background:#fff;cursor:pointer;font:inherit;color:inherit}
        .optimizr-2fa__choice:hover,.optimizr-2fa__choice:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:0}
        .optimizr-2fa__choice .optimizr-icon{margin-top:1px;color:#2271b1}
        .optimizr-2fa__choice strong{display:block;margin-bottom:2px}
        .optimizr-2fa__choice-text span{color:#50575e}
        .optimizr-2fa__qr{display:inline-block;background:#fff;border:1px solid #dcdcde;line-height:0}
        .optimizr-2fa__qr svg{width:200px;height:200px}
        .optimizr-2fa__secret{letter-spacing:.05em;user-select:all;word-break:break-all}
        .optimizr-2fa__code{width:12em;font-family:monospace;font-size:1.2em;letter-spacing:.1em}
        .optimizr-2fa__codes ul{columns:2;max-width:22em;font-family:monospace;font-size:1.1em}
        .optimizr-2fa__message.is-error{color:#b32d2e}
    ');
}

// Rappel en haut du profil pour un compte qui doit la mettre en place
add_action('admin_notices', function () {
    global $pagenow;
    if ($pagenow === 'profile.php' && haptic_2fa_setup_required(wp_get_current_user())) {
        echo '<div class="notice notice-warning"><p>' . wp_kses_post(sprintf(
            /* translators: %s: link to the two-factor section of the profile */
            __('Two-factor authentication is required for your account. <a href="%s">Set it up</a> to access the rest of the dashboard.', 'optimizr'),
            '#optimizr-2fa'
        )) . '</p></div>';
    }
});

/* ---------- Codes du profil ---------- */

/** Changement de méthode autorisé (un code valide a été saisi il y a moins de 10 minutes). */
function haptic_2fa_change_allowed($user_id)
{
    return (int) get_user_meta($user_id, 'haptic_2fa_change', true) > time();
}

/**
 * Code exigé pour une opération sensible du profil : code de la méthode en place ou code de secours.
 * 5 essais faux au plus par quart d'heure, sinon un code de l'application finirait par être deviné.
 *
 * @return true|WP_Error
 */
function haptic_2fa_confirm_code($user, $code)
{
    if (haptic_2fa_too_many_fails($user->ID)) {
        return haptic_2fa_fails_error();
    }
    $pending = get_user_meta($user->ID, 'haptic_2fa_email', true);
    $hash = is_array($pending) && ($pending['purpose'] ?? '') === 'confirm' && (int) ($pending['expires'] ?? 0) >= time() ? (string) $pending['code'] : '';

    $result = haptic_2fa_verify_code($user->ID, $code, $hash);
    if ($result === false) {
        haptic_2fa_add_fail($user->ID);
        return new WP_Error('haptic_2fa_invalid', __('Invalid code.', 'optimizr'));
    }
    if ($result === 'email') {
        delete_user_meta($user->ID, 'haptic_2fa_email');
    }
    delete_user_meta($user->ID, 'haptic_2fa_fails');
    return true;
}

/**
 * Envoie un code par email depuis le profil : mise en place (adresse du compte) ou confirmation (adresse enregistrée).
 *
 * @return string|WP_Error adresse masquée
 */
function haptic_2fa_send_profile_code($user, $purpose)
{
    $config = haptic_2fa_config($user->ID);
    if ($purpose === 'setup' && haptic_2fa_email_allowed_for($user) && (!$config || haptic_2fa_change_allowed($user->ID))) {
        $address = $user->user_email;
    } elseif ($purpose === 'confirm' && haptic_2fa_method($config) === 'email' && $config) {
        $address = $config['email'];
    } else {
        return new WP_Error('haptic_2fa_denied', __('You are not allowed to do this.', 'optimizr'));
    }

    $previous = get_user_meta($user->ID, 'haptic_2fa_email', true);
    if (is_array($previous) && (int) ($previous['sent'] ?? 0) > time() - HAPTIC_2FA_EMAIL_WAIT) {
        return new WP_Error('haptic_2fa_wait', __('Wait a few seconds before asking for a new code.', 'optimizr'));
    }
    if (!haptic_2fa_mail_quota($user->ID)) {
        return new WP_Error('haptic_2fa_mail_limited', __('Too many codes have been sent in the last hour. Try again later.', 'optimizr'));
    }
    $code = haptic_2fa_new_email_code();
    update_user_meta($user->ID, 'haptic_2fa_email', [
        'code'     => haptic_2fa_email_hash($user->ID, $code),
        'purpose'  => $purpose,
        'address'  => $address,
        'expires'  => time() + HAPTIC_2FA_LOGIN_TTL,
        'attempts' => 0,
        'sent'     => time(),
    ]);
    if (!is_email($address) || !haptic_2fa_send_email_code($user, $address, $code, $purpose)) {
        return new WP_Error('haptic_2fa_mail', __('The site could not send the email. Check its email settings (SMTP), or use an authentication app.', 'optimizr'));
    }
    return haptic_2fa_mask_email($address);
}

/**
 * Code de mise en place reçu par email : valable une fois, 10 minutes, 5 essais.
 *
 * @return string|null adresse vérifiée
 */
function haptic_2fa_check_setup_code($user_id, $code)
{
    $pending = get_user_meta($user_id, 'haptic_2fa_email', true);
    if (!is_array($pending) || ($pending['purpose'] ?? '') !== 'setup' || (int) ($pending['expires'] ?? 0) < time()) {
        return null;
    }
    if (hash_equals((string) $pending['code'], haptic_2fa_email_hash($user_id, $code))) {
        delete_user_meta($user_id, 'haptic_2fa_email');
        return (string) $pending['address'];
    }
    $pending['attempts'] = (int) $pending['attempts'] + 1;
    if ($pending['attempts'] >= HAPTIC_2FA_MAX_ATTEMPTS) {
        delete_user_meta($user_id, 'haptic_2fa_email');
    } else {
        update_user_meta($user_id, 'haptic_2fa_email', $pending);
    }
    return null;
}

/* ---------- Requêtes du profil ---------- */

/** Vérifie le nonce et renvoie le compte concerné : le sien, ou un autre pour une réinitialisation. */
function haptic_2fa_ajax_user($own = true)
{
    $user_id = isset($_POST['user']) ? absint($_POST['user']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifié juste après, le nonce dépend du compte
    check_ajax_referer('haptic_2fa_' . $user_id, 'nonce');
    $self = $user_id === get_current_user_id();
    // Réinitialisation : jamais sur son propre compte, qui passe par la désactivation (code exigé)
    if (!haptic_2fa_available() || !$user_id || ($own && !$self) || (!$own && ($self || !current_user_can('edit_user', $user_id)))) {
        wp_send_json_error(['message' => __('You are not allowed to do this.', 'optimizr')], 403);
    }
    return get_userdata($user_id);
}

function haptic_2fa_ajax_code()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié par haptic_2fa_ajax_user()
    return isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
}

/** Code de confirmation de la requête, ou réponse d'erreur. */
function haptic_2fa_ajax_confirm($user)
{
    $checked = haptic_2fa_confirm_code($user, haptic_2fa_ajax_code());
    if (is_wp_error($checked)) {
        wp_send_json_error(['message' => $checked->get_error_message()]);
    }
}

add_action('wp_ajax_haptic_2fa_send', function () {
    $user = haptic_2fa_ajax_user();
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié par haptic_2fa_ajax_user()
    $purpose = isset($_POST['purpose']) && sanitize_key($_POST['purpose']) === 'confirm' ? 'confirm' : 'setup';
    $sent = haptic_2fa_send_profile_code($user, $purpose);
    if (is_wp_error($sent)) {
        wp_send_json_error(['message' => $sent->get_error_message()]);
    }
    /* translators: %s: masked email address */
    wp_send_json_success(['message' => sprintf(__('Code sent to %s. It is valid for 10 minutes.', 'optimizr'), $sent)]);
});

add_action('wp_ajax_haptic_2fa_activate', function () {
    $user = haptic_2fa_ajax_user();
    $previous = haptic_2fa_config($user->ID);
    if ($previous && !haptic_2fa_change_allowed($user->ID)) {
        wp_send_json_error(['message' => __('You are not allowed to do this.', 'optimizr')], 403);
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié par haptic_2fa_ajax_user()
    $method = isset($_POST['method']) && sanitize_key($_POST['method']) === 'email' ? 'email' : 'app';

    if ($method === 'email') {
        $address = haptic_2fa_email_allowed_for($user) ? haptic_2fa_check_setup_code($user->ID, haptic_2fa_ajax_code()) : null;
        if ($address === null) {
            wp_send_json_error(['message' => __('Invalid or expired code. Ask for a new code and use the latest email received.', 'optimizr')]);
        }
        $config = ['method' => 'email', 'email' => $address];
    } else {
        $secret = (string) get_user_meta($user->ID, 'haptic_2fa_pending', true);
        $step = $secret !== '' ? haptic_2fa_check_totp($secret, haptic_2fa_ajax_code()) : false;
        if ($step === false) {
            wp_send_json_error(['message' => __('Invalid code. Check that your phone is set to the right time, then try again.', 'optimizr')]);
        }
        $config = ['method' => 'app', 'secret' => $secret, 'last_step' => $step];
    }
    update_user_meta($user->ID, 'haptic_2fa', $config + ['since' => time(), 'backup' => []]);
    foreach (['haptic_2fa_pending', 'haptic_2fa_change', 'haptic_2fa_email'] as $key) {
        delete_user_meta($user->ID, $key);
    }
    // Type d'évènement enregistré tel quel (clé du journal), libellé traduit à l'affichage
    haptic_sec_log('Double authentification', sprintf(
        $previous
            /* translators: 1: username, 2: authentication method (authentication app, email) */
            ? __('Account %1$s: method changed to %2$s', 'optimizr')
            /* translators: 1: username, 2: authentication method (authentication app, email) */
            : __('Account %1$s: enabled (%2$s)', 'optimizr'),
        $user->user_login,
        $method === 'email' ? __('email', 'optimizr') : __('authentication app', 'optimizr')
    ));
    wp_send_json_success(['codes' => haptic_2fa_new_backup_codes($user->ID)]);
});

add_action('wp_ajax_haptic_2fa_backup', function () {
    $user = haptic_2fa_ajax_user();
    haptic_2fa_ajax_confirm($user);
    wp_send_json_success(['codes' => haptic_2fa_new_backup_codes($user->ID)]);
});

add_action('wp_ajax_haptic_2fa_change', function () {
    $user = haptic_2fa_ajax_user();
    if (!haptic_2fa_config($user->ID)) {
        wp_send_json_error(['message' => __('You are not allowed to do this.', 'optimizr')], 403);
    }
    haptic_2fa_ajax_confirm($user);
    update_user_meta($user->ID, 'haptic_2fa_change', time() + HAPTIC_2FA_LOGIN_TTL);
    wp_send_json_success();
});

add_action('wp_ajax_haptic_2fa_disable', function () {
    $user = haptic_2fa_ajax_user();
    if (haptic_2fa_is_required_for($user)) {
        wp_send_json_error(['message' => __('Required for your role: it cannot be disabled.', 'optimizr')]);
    }
    haptic_2fa_ajax_confirm($user);
    haptic_2fa_disable($user->ID);
    // Type d'évènement enregistré tel quel (clé du journal), libellé traduit à l'affichage
    /* translators: %s: username */
    haptic_sec_log('Double authentification', sprintf(__('Account %s: disabled', 'optimizr'), $user->user_login));
    wp_send_json_success();
});

add_action('wp_ajax_haptic_2fa_reset', function () {
    $user = haptic_2fa_ajax_user(false);
    haptic_2fa_disable($user->ID);
    // Type d'évènement enregistré tel quel (clé du journal), libellé traduit à l'affichage
    /* translators: 1: username, 2: username of the administrator */
    haptic_sec_log('Double authentification', sprintf(__('Account %1$s: reset by %2$s', 'optimizr'), $user->user_login, wp_get_current_user()->user_login));
    wp_send_json_success();
});

/*
 * Confirmation de la session (mot de passe, ou code si la double authentification est en place) :
 * rouvre pour 10 minutes l'ajout d'un moyen de connexion (passkey d'Optimizr Pro, mot de passe d'application).
 */
add_action('wp_ajax_haptic_sec_confirm', function () {
    check_ajax_referer('haptic_sec_confirm', 'nonce');
    $user = wp_get_current_user();
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié ci-dessus ; mot de passe lu tel quel, comme wp-login.php
    $secret = isset($_POST['secret']) ? wp_unslash($_POST['secret']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    if (!is_string($secret) || !$user->exists()) {
        wp_send_json_error(['message' => __('You are not allowed to do this.', 'optimizr')], 403);
    }

    if (haptic_sec_confirm_method($user) === 'code') {
        $checked = haptic_2fa_confirm_code($user, sanitize_text_field($secret));
    } elseif (haptic_2fa_too_many_fails($user->ID)) {
        $checked = haptic_2fa_fails_error();
    } elseif (wp_check_password($secret, $user->user_pass, $user->ID)) {
        delete_user_meta($user->ID, 'haptic_2fa_fails');
        $checked = true;
    } else {
        haptic_2fa_add_fail($user->ID);
        $checked = new WP_Error('haptic_sec_password', __('Incorrect password.', 'optimizr'));
    }
    if (is_wp_error($checked)) {
        wp_send_json_error(['message' => $checked->get_error_message()]);
    }
    haptic_sec_mark_recent_auth();
    wp_send_json_success();
});

/*
 * Mot de passe d'application : il permet d'utiliser l'API sans code de double authentification.
 * Pour un compte protégé, sa création demande une session récente : un cookie de session volé ne suffit pas.
 * Deux chemins dans WordPress : l'API REST (profil) et l'écran « Autoriser l'application » (wp-admin/authorize-application.php).
 */
function haptic_2fa_app_password_blocked($user)
{
    return $user instanceof WP_User && haptic_2fa_is_enabled_for($user) && !haptic_sec_recent_auth();
}

function haptic_2fa_app_password_message()
{
    return __('For security, application passwords of an account protected by two-factor authentication can only be created within 10 minutes of logging in. Log out, log in again, then create it.', 'optimizr');
}

add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    if ($result !== null || $request->get_method() !== 'POST' || !preg_match('#^/wp/v2/users/(\d+|me)/application-passwords/?$#', $request->get_route(), $m)) {
        return $result;
    }
    if (haptic_2fa_app_password_blocked($m[1] === 'me' ? wp_get_current_user() : get_userdata((int) $m[1]))) {
        return new WP_Error('haptic_sec_recent_auth', haptic_2fa_app_password_message(), ['status' => 403]);
    }
    return $result;
}, 10, 3);

// Écran « Autoriser l'application » : affiché avec l'erreur…
add_action('wp_authorize_application_password_request_errors', function ($error, $request, $user) {
    if (haptic_2fa_app_password_blocked($user)) {
        $error->add('haptic_sec_recent_auth', haptic_2fa_app_password_message());
    }
}, 10, 3);

// … et son envoi, qui crée le mot de passe sans repasser par la vérification ci-dessus
add_action('check_admin_referer', function ($action, $result) {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié par WordPress juste avant cette action
    if ($action === 'authorize_application_password' && $result && isset($_POST['approve']) && haptic_2fa_app_password_blocked(wp_get_current_user())) {
        wp_die(esc_html(haptic_2fa_app_password_message()), esc_html__('Cannot Authorize Application', 'optimizr'), ['response' => 403]);
    }
}, 10, 2);
