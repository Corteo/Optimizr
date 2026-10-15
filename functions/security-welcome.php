<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Écran « Sécurisez votre compte », proposé juste après la connexion (wp-login.php?action=haptic_secure) :
 * à la première connexion qui suit l'activation de la double authentification (ou des passkeys d'Optimizr Pro),
 * le compte la met en place en quelques secondes, dans le cadre de la page de connexion, puis arrive où il allait.
 * Les mêmes étapes terminent l'assistant de configuration (functions/onboarding.php) pour l'administrateur qui l'a suivi.
 *
 * « Plus tard » repousse l'écran d'une semaine. Un rôle qui doit avoir la double authentification ne peut que la mettre
 * en place : l'administration le ramène ici tant qu'elle manque (functions/security-2fa.php).
 *
 * Proposé aux comptes qui peuvent écrire des contenus (filtre haptic_sec_welcome_user) et aux rôles obligatoires.
 * Étapes : filtre haptic_sec_welcome_steps (clé => obligatoire), contenu : actions haptic_sec_welcome_enqueue
 * et haptic_sec_welcome_content. Optimizr Pro y ajoute la passkey. Mise en forme : css/optimizr-welcome.css.
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
 * Protections que ce compte peut encore mettre en place : clé => étape obligatoire. Vide : rien à proposer.
 *
 * @return array
 */
function haptic_sec_account_steps($user)
{
    if (!$user instanceof WP_User || !$user->exists()) {
        return [];
    }
    $required = haptic_2fa_setup_required($user);

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

/**
 * Étapes à proposer à ce compte après la connexion : celles de haptic_sec_account_steps(), pour un compte concerné
 * qui n'a pas demandé « Plus tard » cette semaine (toujours pour un rôle obligatoire).
 *
 * @return array
 */
function haptic_sec_welcome_steps($user)
{
    if (!$user instanceof WP_User || !$user->exists()) {
        return [];
    }
    $eligible = (bool) apply_filters('haptic_sec_welcome_user', $user->has_cap('edit_posts'), $user);
    if (!haptic_2fa_setup_required($user) && (!$eligible || (int) get_user_meta($user->ID, 'haptic_sec_welcome_until', true) > time())) {
        return [];
    }
    return haptic_sec_account_steps($user);
}

/**
 * Feuille de style, scripts des étapes et passage de « Plus tard » à « Continuer ».
 * $deps : feuilles à charger avant (celle de la page de connexion, dont les règles générales s'appliqueraient sinon).
 *
 * @return array Feuilles et scripts mis en file : [styles, scripts], pour une page qui les affiche elle-même.
 */
function haptic_sec_account_enqueue($user, $steps, $deps = [])
{
    $styles = wp_styles()->queue;
    $scripts = wp_scripts()->queue;

    $css = HAPTIC_OPTIMIZR_PATH . 'css/optimizr-welcome.css';
    wp_enqueue_style('optimizr-welcome', HAPTIC_OPTIMIZR_URL . 'css/optimizr-welcome.css', $deps, filemtime($css));
    if (isset($steps['two_factor'])) {
        haptic_2fa_enqueue_assets($user->ID, 'welcome');
    }
    do_action('haptic_sec_welcome_enqueue', $user, $steps);

    wp_register_script('optimizr-welcome', false, [], HAPTIC_OPTIMIZR_VERSION, true);
    wp_enqueue_script('optimizr-welcome');
    // Une étape terminée (évènement des scripts de mise en place) : carte cochée, « Plus tard » devient « Continuer »
    wp_add_inline_script('optimizr-welcome', '(function(){var root=document.querySelector(".optimizr-welcome");if(!root){return;}var link=document.querySelector("[data-welcome-continue]");var required=root.getAttribute("data-required");document.addEventListener("optimizr:secured",function(event){var step=event.detail&&event.detail.step;var card=step&&root.querySelector("[data-welcome-step=\""+step+"\"]"+(step==="passkey"?",[data-passkey-welcome]":""));if(card){card.classList.add("is-done");}if(step===required){required="";}if(!required&&link){link.hidden=false;link.textContent=link.getAttribute("data-done");(link.getAttribute("data-later-class")||"").split(" ").forEach(function(name){if(name){link.classList.remove(name);}});(link.getAttribute("data-done-class")||"").split(" ").forEach(function(name){if(name){link.classList.add(name);}});if(!root.querySelector(".optimizr-welcome__step:not(.is-done):not([hidden])")){var hint=root.querySelector(".optimizr-welcome__hint");if(hint){hint.hidden=true;}link.focus();}}});})();');

    return [array_values(array_diff(wp_styles()->queue, $styles)), array_values(array_diff(wp_scripts()->queue, $scripts))];
}

/** Cartes des étapes (passkey d'Optimizr Pro, puis double authentification), dans un bloc .optimizr-welcome. */
function haptic_sec_account_cards($user, $steps)
{
    ?>
    <div class="optimizr-welcome__steps">
        <?php do_action('haptic_sec_welcome_content', $user, $steps); ?>
    </div>
    <?php
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

    // Après la feuille de la page de connexion : ses règles générales (marges, boutons flottants) ne s'appliquent plus
    haptic_sec_account_enqueue($user, $steps, ['login']);

    add_filter('login_body_class', function ($classes) {
        $classes[] = 'optimizr-welcome-page';
        return $classes;
    });
    login_header(__('Secure your account', 'optimizr'));
    $name = $user->first_name ?: $user->display_name;
    ?>
    <div class="optimizr-welcome optimizr-welcome--login" data-required="<?php echo $required ? 'two_factor' : ''; ?>">
        <div class="optimizr-welcome__head">
            <span class="optimizr-welcome__mark" aria-hidden="true"><?php echo wp_kses(haptic_icon('shield', 26), haptic_kses_ui()); ?></span>
            <h2 class="optimizr-welcome__heading"><?php esc_html_e('Secure your account', 'optimizr'); ?></h2>
            <p class="optimizr-welcome__intro">
                <?php
                echo esc_html($required
                    /* translators: %s: first name or display name of the user */
                    ? sprintf(__('Hello %s! Your role requires a second step after the password. Set it up now: it only takes a minute.', 'optimizr'), $name)
                    /* translators: %s: first name or display name of the user */
                    : sprintf(__('Hello %s! Protect your account in less than a minute: even with your password, nobody else will be able to log in.', 'optimizr'), $name));
                ?>
            </p>
        </div>
        <?php haptic_sec_account_cards($user, $steps); ?>
        <div class="optimizr-welcome__footer">
            <?php if (!$required) : ?>
                <p class="optimizr-welcome__hint"><?php esc_html_e('You can also do it later from your profile.', 'optimizr'); ?></p>
            <?php endif; ?>
            <a class="button button-large optimizr-welcome__later" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['later' => 1, 'redirect_to' => rawurlencode($redirect)], haptic_sec_welcome_url()), 'haptic_sec_welcome_later')); ?>" data-welcome-continue data-done="<?php esc_attr_e('Continue', 'optimizr'); ?>" data-done-class="button-primary" <?php echo $required ? 'hidden' : ''; ?>><?php esc_html_e('Later', 'optimizr'); ?></a>
        </div>
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
    <section class="optimizr-welcome__step" data-welcome-step="two_factor">
        <h2>
            <span class="optimizr-welcome__icon" aria-hidden="true"><?php echo wp_kses(haptic_icon('phone', 20), haptic_kses_ui()); ?></span>
            <span class="optimizr-welcome__title"><?php esc_html_e('Two-factor authentication', 'optimizr'); ?></span>
            <?php if (!empty($steps['two_factor'])) : ?>
                <span class="optimizr-welcome__badge"><?php esc_html_e('Required', 'optimizr'); ?></span>
            <?php endif; ?>
        </h2>
        <p class="optimizr-welcome__desc"><?php esc_html_e('After your password, a 6-digit code is asked. Choose how to receive it:', 'optimizr'); ?></p>
        <div class="optimizr-2fa" data-user="<?php echo esc_attr($user->ID); ?>">
            <?php haptic_2fa_setup_markup($user); ?>
            <p class="optimizr-2fa__message" role="status" aria-live="polite"></p>
        </div>
    </section>
    <?php
}, 20, 2);
