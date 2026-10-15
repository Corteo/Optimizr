<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Double authentification : mise en place depuis le profil (functions/security-2fa.php pour la connexion).
 * Chacun l'active pour son propre compte ; un administrateur peut la réinitialiser pour un autre compte (téléphone perdu).
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

    if ($own && !$config) {
        $secret = (string) get_user_meta($user->ID, 'haptic_2fa_pending', true);
        if ($secret === '') {
            $secret = haptic_2fa_generate_secret();
            update_user_meta($user->ID, 'haptic_2fa_pending', $secret);
        }
    }
    haptic_2fa_enqueue_assets($user->ID);
    ?>
    <h2 id="optimizr-2fa"><?php esc_html_e('Two-factor authentication', 'optimizr'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Authentication app', 'optimizr'); ?></th>
            <td>
                <div class="optimizr-2fa" data-user="<?php echo esc_attr($user->ID); ?>">
                    <?php if ($config) : ?>
                        <p>
                            <span class="optimizr-2fa__status optimizr-2fa__status--on"><?php esc_html_e('Enabled', 'optimizr'); ?></span>
                            <?php /* translators: %s: date two-factor authentication was enabled */ echo esc_html(sprintf(__('since %s', 'optimizr'), wp_date(get_option('date_format'), (int) ($config['since'] ?? time())))); ?>
                        </p>
                        <?php $left = count((array) ($config['backup'] ?? [])); ?>
                        <p class="description"><?php /* translators: %s: number of unused backup codes */ echo esc_html(sprintf(_n('%s backup code left.', '%s backup codes left.', $left, 'optimizr'), number_format_i18n($left))); ?></p>

                        <?php if ($own) : ?>
                            <p><button type="button" class="button" data-2fa-action="backup"><?php esc_html_e('Generate new backup codes', 'optimizr'); ?></button></p>
                            <div class="optimizr-2fa__codes" hidden></div>
                            <?php if (!haptic_2fa_is_required_for($user)) : ?>
                                <p class="optimizr-2fa__disable">
                                    <label for="optimizr-2fa-disable-code"><?php esc_html_e('To disable it, enter a code from the app or a backup code:', 'optimizr'); ?></label><br>
                                    <input type="text" id="optimizr-2fa-disable-code" class="regular-text optimizr-2fa__code" inputmode="numeric" autocomplete="one-time-code" spellcheck="false">
                                    <button type="button" class="button" data-2fa-action="disable"><?php esc_html_e('Disable', 'optimizr'); ?></button>
                                </p>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e('Required for your role: it cannot be disabled.', 'optimizr'); ?></p>
                            <?php endif; ?>
                        <?php else : ?>
                            <p><button type="button" class="button" data-2fa-action="reset"><?php esc_html_e('Reset', 'optimizr'); ?></button></p>
                            <p class="description"><?php esc_html_e('If this person has lost their phone and their backup codes: they will log in with their password only, then set it up again.', 'optimizr'); ?></p>
                        <?php endif; ?>

                    <?php elseif ($own) : ?>
                        <p><span class="optimizr-2fa__status"><?php esc_html_e('Not enabled', 'optimizr'); ?></span></p>
                        <p class="description"><?php esc_html_e('After your password, a 6-digit code from an app on your phone will be asked (Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden…). Someone who finds your password still cannot log in.', 'optimizr'); ?></p>
                        <p><button type="button" class="button button-primary" data-2fa-action="start"><?php esc_html_e('Set up', 'optimizr'); ?></button></p>
                        <div class="optimizr-2fa__setup" hidden>
                            <p><?php esc_html_e('1. In your authentication app, add an account and scan this QR code:', 'optimizr'); ?></p>
                            <div class="optimizr-2fa__qr" data-uri="<?php echo esc_attr(haptic_2fa_otpauth_uri($user, $secret)); ?>"></div>
                            <p class="description"><?php esc_html_e('Or enter this key manually:', 'optimizr'); ?> <code class="optimizr-2fa__secret"><?php echo esc_html(trim(chunk_split($secret, 4, ' '))); ?></code></p>
                            <p>
                                <label for="optimizr-2fa-setup-code"><?php esc_html_e('2. Enter the code shown by the app:', 'optimizr'); ?></label><br>
                                <input type="text" id="optimizr-2fa-setup-code" class="regular-text optimizr-2fa__code" inputmode="numeric" autocomplete="one-time-code" spellcheck="false">
                                <button type="button" class="button button-primary" data-2fa-action="activate"><?php esc_html_e('Enable', 'optimizr'); ?></button>
                            </p>
                        </div>
                        <div class="optimizr-2fa__codes" hidden></div>

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

function haptic_2fa_enqueue_assets($user_id)
{
    $js = HAPTIC_OPTIMIZR_PATH . 'js/optimizr-2fa.js';
    wp_enqueue_script('optimizr-qrcode', HAPTIC_OPTIMIZR_URL . 'assets/qrcode-generator/qrcode.js', [], '1.4.4', true);
    wp_enqueue_script('optimizr-2fa', HAPTIC_OPTIMIZR_URL . 'js/optimizr-2fa.js', ['wp-i18n', 'optimizr-qrcode'], filemtime($js), true);
    wp_set_script_translations('optimizr-2fa', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
    wp_localize_script('optimizr-2fa', 'optimizr2fa', [
        'ajaxUrl'  => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('haptic_2fa_' . $user_id),
        'fileName' => sanitize_file_name(wp_parse_url(home_url(), PHP_URL_HOST) . '-backup-codes.txt'),
    ]);
    // Styles du bloc : quelques règles, pas de feuille dédiée
    wp_register_style('optimizr-2fa', false, [], HAPTIC_OPTIMIZR_VERSION);
    wp_enqueue_style('optimizr-2fa');
    wp_add_inline_style('optimizr-2fa', '
        .optimizr-2fa__status{display:inline-block;padding:2px 8px;border-radius:999px;background:#f0f0f1;font-weight:600}
        .optimizr-2fa__status--on{background:#d1f0d9;color:#0a5c25}
        .optimizr-2fa__qr{display:inline-block;background:#fff;border:1px solid #dcdcde;line-height:0}
        .optimizr-2fa__qr svg{width:200px;height:200px}
        .optimizr-2fa__secret{letter-spacing:.05em;user-select:all}
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

/* ---------- Requêtes du profil ---------- */

/** Vérifie le nonce et renvoie le compte concerné (le sien, ou un autre pour une réinitialisation). */
function haptic_2fa_ajax_user($own = true)
{
    $user_id = isset($_POST['user']) ? absint($_POST['user']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifié juste après, le nonce dépend du compte
    check_ajax_referer('haptic_2fa_' . $user_id, 'nonce');
    if (!haptic_2fa_available() || !$user_id || ($own && $user_id !== get_current_user_id()) || (!$own && !current_user_can('edit_user', $user_id))) {
        wp_send_json_error(['message' => __('You are not allowed to do this.', 'optimizr')], 403);
    }
    return get_userdata($user_id);
}

function haptic_2fa_ajax_code()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié par haptic_2fa_ajax_user()
    return isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
}

add_action('wp_ajax_haptic_2fa_activate', function () {
    $user = haptic_2fa_ajax_user();
    $secret = (string) get_user_meta($user->ID, 'haptic_2fa_pending', true);
    $step = $secret !== '' ? haptic_2fa_check_totp($secret, haptic_2fa_ajax_code()) : false;
    if ($step === false) {
        wp_send_json_error(['message' => __('Invalid code. Check that your phone is set to the right time, then try again.', 'optimizr')]);
    }
    update_user_meta($user->ID, 'haptic_2fa', ['secret' => $secret, 'since' => time(), 'backup' => [], 'last_step' => $step]);
    delete_user_meta($user->ID, 'haptic_2fa_pending');
    wp_send_json_success(['codes' => haptic_2fa_new_backup_codes($user->ID)]);
});

add_action('wp_ajax_haptic_2fa_backup', function () {
    $user = haptic_2fa_ajax_user();
    wp_send_json_success(['codes' => haptic_2fa_new_backup_codes($user->ID)]);
});

add_action('wp_ajax_haptic_2fa_disable', function () {
    $user = haptic_2fa_ajax_user();
    if (haptic_2fa_is_required_for($user)) {
        wp_send_json_error(['message' => __('Required for your role: it cannot be disabled.', 'optimizr')]);
    }
    // Un code est demandé : une session volée ne suffit pas à retirer la protection
    if (!haptic_2fa_verify_code($user->ID, haptic_2fa_ajax_code())) {
        wp_send_json_error(['message' => __('Invalid code. Check that your phone is set to the right time, then try again.', 'optimizr')]);
    }
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
