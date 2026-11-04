<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Suivi des conversions (module Cookies & RGPD), réglé par l'assistant campagnes (functions/campaigns.php).
 *
 * js/optimizr-conversions.js repère les actions choisies (formulaire Contact Form 7 envoyé, clic sur un numéro de
 * téléphone, une adresse e-mail, WhatsApp, un service de prise de rendez-vous) et les envoie :
 * - à Google Analytics 4 / Google Ads par gtag(), qui applique le Google Consent Mode v2 (tarteaucitron.js) ;
 * - à Google Tag Manager par le dataLayer (évènement optimizr_conversion) ;
 * - aux extensions par l'évènement DOM « optimizr:conversion » (Optimizr Pro : conversions Google Ads, Pixel Meta).
 * Un signal anonyme (type d'action seulement, ni cookie ni donnée du visiteur) date la dernière conversion vue,
 * affichée dans l'onglet Cookies & RGPD.
 */

/** Évènements envoyés pour chaque action : nom GA4 et évènement standard Meta. */
function haptic_conversion_events()
{
    return apply_filters('haptic_conversion_events', [
        'form'     => ['ga4' => 'generate_lead', 'meta' => 'Lead'],
        'phone'    => ['ga4' => 'click_phone', 'meta' => 'Contact'],
        'email'    => ['ga4' => 'click_email', 'meta' => 'Contact'],
        'whatsapp' => ['ga4' => 'click_whatsapp', 'meta' => 'Contact'],
        'booking'  => ['ga4' => 'click_booking', 'meta' => 'Schedule'],
    ]);
}

/** Identifiant d'un service de l'onglet Cookies (chaîne vide s'il n'est pas réglé). */
function haptic_cookie_service_id($service, $key = 'ID')
{
    $option = get_option('haptic_option_service_' . $service);
    return is_array($option) && isset($option[$key]) ? trim((string) $option[$key]) : '';
}

/** Configuration lue par js/optimizr-conversions.js, ou null si rien n'est à suivre. */
function haptic_conversions_front_config()
{
    $settings = haptic_conversions_settings();
    $actions = array_keys(array_filter($settings['actions']));
    if (!$settings['booking']) {
        $actions = array_values(array_diff($actions, ['booking']));
    }
    if (!$actions) {
        return null;
    }

    $config = [
        'actions' => $actions,
        'booking' => $settings['booking'],
        'events'  => array_intersect_key(haptic_conversion_events(), array_flip($actions)),
        // gtag() défini par tarteaucitron.js pour GA4 (et Google Ads avec Optimizr Pro)
        'gtag'    => haptic_cookie_service_id('google_analytics') !== '',
        'gtm'     => haptic_cookie_service_id('google_tags') !== '',
        'ping'    => admin_url('admin-ajax.php'),
    ];
    return apply_filters('haptic_conversions_front_config', $config);
}

add_action('haptic_before_enqueue_scripts', function () {
    if (!get_option('haptic_option_enable_tarteaucitron')) {
        return;
    }
    $config = haptic_conversions_front_config();
    if (!$config) {
        return;
    }
    wp_enqueue_script('optimizr-conversions', HAPTIC_OPTIMIZR_URL . 'js/optimizr-conversions.js', [], HAPTIC_OPTIMIZR_VERSION, true);
    wp_add_inline_script('optimizr-conversions', 'window.optimizrConversions = ' . wp_json_encode($config, JSON_HEX_TAG) . ';', 'before');
});

/* ---------- Dernière conversion vue ---------- */

add_action('wp_ajax_haptic_conversion_seen', 'haptic_conversion_seen');
add_action('wp_ajax_nopriv_haptic_conversion_seen', 'haptic_conversion_seen');

/**
 * Signal envoyé par le navigateur à chaque conversion : seule la date est gardée, au plus une écriture
 * toutes les 5 minutes par action. Sans nonce : la page peut venir du cache, et l'information n'a pas d'enjeu.
 */
function haptic_conversion_seen()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- signal public sans effet sensible, voir ci-dessus
    $action = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
    if (!isset(haptic_conversion_events()[$action])) {
        wp_die('', '', ['response' => 400]);
    }
    $seen = get_option('haptic_conversions_seen');
    $seen = is_array($seen) ? $seen : [];
    if (empty($seen[$action]) || time() - (int) $seen[$action] > 5 * MINUTE_IN_SECONDS) {
        $seen[$action] = time();
        update_option('haptic_conversions_seen', $seen, false);
    }
    wp_die('', '', ['response' => 204]);
}

/* ---------- État du suivi, dans l'onglet Cookies & RGPD ---------- */

/**
 * Lignes de l'état du suivi : [libellé, valeur, actif].
 * Optimizr Pro ajoute Google Ads et Meta.
 */
function haptic_campaigns_status_rows()
{
    $rows = [];
    foreach (['google_analytics' => 'Google Analytics 4', 'google_tags' => 'Google Tag Manager'] as $service => $label) {
        $id = haptic_cookie_service_id($service);
        $rows[] = [$label, $id !== '' ? $id : __('Not set', 'optimizr'), $id !== ''];
    }
    return apply_filters('haptic_campaigns_status_rows', $rows);
}

add_action('haptic_admin_aside_cookies', function () {
    $settings = haptic_conversions_settings();
    $seen = (array) get_option('haptic_conversions_seen', []);
    $banner = (bool) get_option('haptic_option_enable_tarteaucitron');
    ?>
    <section class="optimizr-card optimizr-campaigns">
        <div class="optimizr-card__header">
            <h2 class="optimizr-card__title"><?php esc_html_e('Campaign tracking', 'optimizr'); ?></h2>
        </div>
        <div class="optimizr-card__body">
            <?php if (!$banner) : ?>
                <p class="optimizr-campaigns__alert"><?php esc_html_e('The banner is off: nothing is measured.', 'optimizr'); ?></p>
            <?php endif; ?>
            <dl class="optimizr-campaigns__list">
                <?php foreach (haptic_campaigns_status_rows() as list($label, $value, $active)) : ?>
                    <div class="optimizr-campaigns__row">
                        <dt><?php echo esc_html($label); ?></dt>
                        <dd><span class="optimizr-status optimizr-status--<?php echo $active ? 'ok' : 'off'; ?>"><?php echo esc_html($value); ?></span></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
            <h3 class="optimizr-campaigns__subtitle"><?php esc_html_e('Conversions', 'optimizr'); ?></h3>
            <dl class="optimizr-campaigns__list">
                <?php foreach (haptic_conversion_actions() as $key => list($title)) :
                    $active = !empty($settings['actions'][$key]) && ($key !== 'booking' || $settings['booking']);
                    if (!$active) {
                        $value = _x('Not tracked', 'conversion', 'optimizr');
                    } elseif (!empty($seen[$key])) {
                        /* translators: %s: time since the last conversion, e.g. "2 hours" */
                        $value = sprintf(__('Last: %s ago', 'optimizr'), human_time_diff((int) $seen[$key]));
                    } else {
                        $value = __('Not seen yet', 'optimizr');
                    }
                    ?>
                    <div class="optimizr-campaigns__row">
                        <dt><?php echo esc_html($title); ?></dt>
                        <dd><span class="optimizr-status optimizr-status--<?php echo $active ? (!empty($seen[$key]) ? 'ok' : 'warn') : 'off'; ?>"><?php echo esc_html($value); ?></span></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
            <a class="optimizr-btn optimizr-btn--ghost optimizr-campaigns__btn" href="<?php echo esc_url(haptic_campaigns_url()); ?>"><?php echo wp_kses(haptic_icon('sparkles', 16), haptic_kses_ui()); ?><?php esc_html_e('Campaign tracking wizard', 'optimizr'); ?></a>
        </div>
    </section>
    <?php
}, 5);
