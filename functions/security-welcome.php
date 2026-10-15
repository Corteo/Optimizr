<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Écran « Sécurisez votre compte », proposé juste après la connexion (wp-login.php?action=haptic_secure) :
 * à la première connexion qui suit l'activation de la double authentification (ou des passkeys d'Optimizr Pro),
 * le compte la met en place en quelques secondes, dans le style de la page de connexion, puis arrive où il allait.
 *
 * « Plus tard » repousse l'écran d'une semaine. Un rôle qui doit avoir la double authentification ne peut que la mettre
 * en place : l'administration le ramène ici tant qu'elle manque (functions/security-2fa.php).
 *
 * Proposé aux comptes qui peuvent écrire des contenus (filtre haptic_sec_welcome_user) et aux rôles obligatoires.
 * Étapes : filtre haptic_sec_welcome_steps (clé => obligatoire), contenu : actions haptic_sec_welcome_enqueue
 * et haptic_sec_welcome_content. Optimizr Pro y ajoute la passkey.
 *
 * Métadonnée des comptes : haptic_sec_welcome_until (horodatage avant lequel l'écran n'est plus proposé).
 */

const HAPTIC_SEC_WELCOME_POSTPONE = 7 * DAY_IN_SECONDS;

/** Adresse de l'écran, sur l'adresse de connexion (personnalisée ou wp-login.php), avec la page où revenir. */
function haptic_sec_welcome_url($redirect = '')
{
    $url = site_url('wp-login.php?action=haptic_secure', 'login');
    return $redirect !== '' ? add_query_arg('redirect_to', rawurlencode($redirect), $url) : $url;
}

/**
 * Étapes à proposer à ce compte : clé => étape obligatoire. Vide : rien à proposer.
 *
 * @return array
 */
function haptic_sec_welcome_steps($user)
{
    if (!$user instanceof WP_User || !$user->exists()) {
        return [];
    }
    $required = haptic_2fa_setup_required($user);
    $eligible = (bool) apply_filters('haptic_sec_welcome_user', $user->has_cap('edit_posts'), $user);
    if (!$required && (!$eligible || (int) get_user_meta($user->ID, 'haptic_sec_welcome_until', true) > time())) {
        return [];
    }

    $steps = [];
    if (haptic_2fa_available() && !haptic_2fa_config($user->ID)) {
        $steps['two_factor'] = $required;
    }
    $steps = (array) apply_filters('haptic_sec_welcome_steps', $steps, $user);
    // Jamais retirée par le filtre quand le rôle l'impose
    if ($required) {
        $steps['two_factor'] = true;
    }
    return $steps;
}

// Après le mot de passe (ou le code, ou la passkey) : l'écran s'intercale avant la page demandée
add_filter('login_redirect', function ($redirect, $requested, $user) {
    if (!$user instanceof WP_User || !haptic_sec_welcome_steps($user)) {
        return $redirect;
    }
    return haptic_sec_welcome_url((string) $redirect);
}, PHP_INT_MAX, 3);

add_action('login_form_haptic_secure', 'haptic_sec_welcome_page');

function haptic_sec_welcome_page()
{
    $user = wp_get_current_user();
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- adresse de retour, vérifiée par wp_validate_redirect()
    $redirect = wp_validate_redirect(isset($_REQUEST['redirect_to']) ? wp_sanitize_redirect(wp_unslash($_REQUEST['redirect_to'])) : '', admin_url());
    if ($redirect === '' || strpos($redirect, 'action=haptic_secure') !== false) {
        $redirect = admin_url();
    }
    if (!$user->exists()) {
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    // « Plus tard », ou « Continuer » une fois une protection en place : les étapes restantes attendront
    if (isset($_GET['later'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- vérifié juste après
        check_admin_referer('haptic_sec_welcome_later');
        update_user_meta($user->ID, 'haptic_sec_welcome_until', time() + HAPTIC_SEC_WELCOME_POSTPONE);
        wp_safe_redirect($redirect);
        exit;
    }

    $steps = haptic_sec_welcome_steps($user);
    if (!$steps) {
        wp_safe_redirect($redirect);
        exit;
    }
    $required = !empty($steps['two_factor']);

    if (isset($steps['two_factor'])) {
        haptic_2fa_enqueue_assets($user->ID, 'welcome');
    }
    do_action('haptic_sec_welcome_enqueue', $user, $steps);
    wp_register_style('optimizr-welcome', false, [], HAPTIC_OPTIMIZR_VERSION);
    wp_enqueue_style('optimizr-welcome');
    wp_add_inline_style('optimizr-welcome', '
        body.optimizr-welcome-page #login{width:440px;max-width:calc(100% - 32px)}
        .optimizr-welcome__intro{margin:0 0 16px;font-size:14px;line-height:1.5}
        .optimizr-welcome__step{background:#fff;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.04);padding:20px 24px;margin:0 0 16px}
        .optimizr-welcome__step h2{margin:0 0 6px;font-size:16px}
        .optimizr-welcome__step > p{margin:0 0 8px}
        .optimizr-welcome [hidden]{display:none!important}
        .optimizr-welcome__badge{display:inline-block;margin-left:6px;padding:1px 8px;border-radius:999px;background:#fcf0f1;color:#8a2424;font-size:12px;font-weight:600;vertical-align:middle}
        .optimizr-welcome__badge--ok{background:#d1f0d9;color:#0a5c25}
        .optimizr-welcome__footer{text-align:right}
        .optimizr-welcome .optimizr-2fa__choices{max-width:none}
        .optimizr-welcome .optimizr-2fa__code{width:100%;margin:4px 0 8px}
        .optimizr-welcome .optimizr-2fa__done{color:#0a5c25;font-weight:600}
    ');
    wp_register_script('optimizr-welcome', false, [], HAPTIC_OPTIMIZR_VERSION, true);
    wp_enqueue_script('optimizr-welcome');
    // Une protection en place (évènement des scripts de mise en place) : « Plus tard » devient « Continuer »
    wp_add_inline_script('optimizr-welcome', '(function(){var root=document.querySelector(".optimizr-welcome");if(!root){return;}var link=root.querySelector("[data-welcome-continue]");var required=root.getAttribute("data-required");document.addEventListener("optimizr:secured",function(event){if(event.detail&&event.detail.step===required){required="";}if(!required){link.hidden=false;link.textContent=link.getAttribute("data-done");link.classList.add("button-primary");}});})();');

    add_filter('login_body_class', function ($classes) {
        $classes[] = 'optimizr-welcome-page';
        return $classes;
    });
    login_header(__('Secure your account', 'optimizr'));
    ?>
    <div class="optimizr-welcome" data-required="<?php echo $required ? 'two_factor' : ''; ?>">
        <p class="optimizr-welcome__intro">
            <?php
            echo esc_html($required
                /* translators: %s: display name of the user */
                ? sprintf(__('%s, your role requires a second step after the password. Set it up now: it only takes a minute.', 'optimizr'), $user->display_name)
                /* translators: %s: display name of the user */
                : sprintf(__('%s, protect your account in less than a minute: even with your password, nobody else will be able to log in.', 'optimizr'), $user->display_name));
            ?>
        </p>
        <?php do_action('haptic_sec_welcome_content', $user, $steps); ?>
        <p class="optimizr-welcome__footer">
            <a class="button button-large" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['later' => 1, 'redirect_to' => rawurlencode($redirect)], haptic_sec_welcome_url()), 'haptic_sec_welcome_later')); ?>" data-welcome-continue data-done="<?php esc_attr_e('Continue', 'optimizr'); ?>" <?php echo $required ? 'hidden' : ''; ?>><?php esc_html_e('Later', 'optimizr'); ?></a>
        </p>
    </div>
    <?php
    login_footer();
    exit;
}

// Étape double authentification (après la passkey d'Optimizr Pro, priorité 10)
add_action('haptic_sec_welcome_content', function ($user, $steps) {
    if (!isset($steps['two_factor'])) {
        return;
    }
    ?>
    <section class="optimizr-welcome__step">
        <h2>
            <?php esc_html_e('Two-factor authentication', 'optimizr'); ?>
            <?php if (!empty($steps['two_factor'])) : ?>
                <span class="optimizr-welcome__badge"><?php esc_html_e('Required', 'optimizr'); ?></span>
            <?php endif; ?>
        </h2>
        <p><?php esc_html_e('After your password, a 6-digit code is asked. Choose how to receive it:', 'optimizr'); ?></p>
        <div class="optimizr-2fa" data-user="<?php echo esc_attr($user->ID); ?>">
            <?php haptic_2fa_setup_markup($user); ?>
            <p class="optimizr-2fa__message" role="status" aria-live="polite"></p>
        </div>
    </section>
    <?php
}, 20, 2);
