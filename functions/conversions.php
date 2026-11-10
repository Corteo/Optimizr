<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Suivi des conversions (module Cookies & RGPD), réglé par l'assistant campagnes (functions/campaigns.php).
 *
 * js/optimizr-conversions.js repère les actions choisies (formulaire Contact Form 7 envoyé, inscription à la lettre
 * d'information MailPoet, Brevo ou Mailchimp for WordPress, clic sur un numéro de téléphone, une adresse e-mail,
 * WhatsApp, un service de prise de rendez-vous, téléchargement) et les envoie :
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
        // Pas d'évènement standard Meta pour un téléchargement ; file_download est réservé à la mesure améliorée de GA4
        'download' => ['ga4' => 'click_download', 'meta' => ''],
        // Inscription à la lettre d'information (MailPoet, Brevo, Mailchimp for WordPress)
        'newsletter' => ['ga4' => 'sign_up', 'meta' => 'CompleteRegistration'],
        // Page de remerciement : un contact, comme le formulaire (conversion_action les distingue dans GTM)
        'thankyou'   => ['ga4' => 'generate_lead', 'meta' => 'Lead'],
        'directions' => ['ga4' => 'click_directions', 'meta' => 'FindLocation'],
        'cta'        => ['ga4' => 'click_cta', 'meta' => 'Lead'],
        'review'     => ['ga4' => 'click_review', 'meta' => ''],
        'social'     => ['ga4' => 'click_social', 'meta' => ''],
        // WooCommerce : évènements recommandés GA4 et standard Meta, avec value, currency (et items)
        'add_to_cart'    => ['ga4' => 'add_to_cart', 'meta' => 'AddToCart'],
        'begin_checkout' => ['ga4' => 'begin_checkout', 'meta' => 'InitiateCheckout'],
        'purchase'       => ['ga4' => 'purchase', 'meta' => 'Purchase'],
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
    if (!$settings['thankyou']) {
        $actions = array_values(array_diff($actions, ['thankyou']));
    }
    if (!$actions) {
        return null;
    }

    $config = [
        'actions' => $actions,
        'booking' => $settings['booking'],
        'thankyou' => $settings['thankyou'],
        'events'  => array_intersect_key(haptic_conversion_events(), array_flip($actions)),
        // gtag() défini par tarteaucitron.js pour GA4 (et Google Ads avec Optimizr Pro)
        'gtag'    => haptic_cookie_service_id('google_analytics') !== '',
        'gtm'     => haptic_cookie_service_id('google_tags') !== '',
        'ping'    => admin_url('admin-ajax.php'),
        // Mailchimp for WordPress : écouté par son API window.mc4wp, créée d'avance seulement s'il est actif
        'mc4wp'   => in_array('newsletter', $actions, true) && defined('MC4WP_VERSION'),
    ];
    $woo = haptic_conversions_woo_config($actions);
    if ($woo) {
        $config['woo'] = $woo;
    }
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

/* ---------- WooCommerce ---------- */

/** Montant et articles d'un panier ou d'une commande, au format GA4 (items). */
function haptic_conversions_woo_items($lines)
{
    $items = [];
    foreach ($lines as $line) {
        $product = $line['product'] ?? null;
        if (!$product instanceof WC_Product) {
            continue;
        }
        $quantity = max(1, (int) ($line['quantity'] ?? 1));
        $items[] = [
            'item_id'   => $product->get_sku() !== '' ? $product->get_sku() : (string) $product->get_id(),
            'item_name' => wp_strip_all_tags($product->get_name()),
            'price'     => round((float) ($line['total'] ?? 0) / $quantity, 2),
            'quantity'  => $quantity,
        ];
    }
    return $items;
}

/**
 * Évènements WooCommerce préparés par le serveur, lus par js/optimizr-conversions.js (window.optimizrConversions.woo) :
 * - added : produit ajouté au panier par un formulaire classique (page rechargée), gardé en session jusqu'à la page suivante ;
 *   les ajouts en AJAX (listes de produits, blocs) sont vus dans le navigateur ;
 * - checkout : page de commande, avec le montant du panier ;
 * - purchase : page de confirmation, une seule fois par commande (méta _haptic_conversion_sent).
 * Montants hors livraison et hors taxes, comme la valeur des conversions Google Ads et Meta.
 */
function haptic_conversions_woo_config($actions)
{
    if (!class_exists('WooCommerce') || !function_exists('WC') || is_admin()) {
        return [];
    }
    $woo = ['currency' => get_woocommerce_currency()];

    if (in_array('add_to_cart', $actions, true) && WC()->session) {
        $added = WC()->session->get('haptic_conversion_added');
        if (is_array($added) && $added) {
            $woo['added'] = $added;
            WC()->session->set('haptic_conversion_added', null);
        }
    }

    if (in_array('begin_checkout', $actions, true) && function_exists('is_checkout') && is_checkout()
        && !is_order_received_page() && !is_checkout_pay_page() && WC()->cart && !WC()->cart->is_empty()) {
        $lines = [];
        foreach (WC()->cart->get_cart() as $item) {
            $lines[] = ['product' => $item['data'] ?? null, 'quantity' => $item['quantity'] ?? 1, 'total' => $item['line_total'] ?? 0];
        }
        $woo['checkout'] = [
            'value' => round((float) WC()->cart->get_cart_contents_total(), 2),
            'items' => haptic_conversions_woo_items($lines),
        ];
    }

    if (in_array('purchase', $actions, true) && function_exists('is_order_received_page') && is_order_received_page()) {
        global $wp;
        $order = wc_get_order(absint($wp->query_vars['order-received'] ?? 0));
        // Clé de la commande vérifiée, comme le fait WooCommerce pour afficher la page
        $key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($order && hash_equals($order->get_order_key(), $key) && !$order->has_status('failed') && !$order->get_meta('_haptic_conversion_sent')) {
            $lines = [];
            foreach ($order->get_items() as $item) {
                $lines[] = ['product' => $item->get_product(), 'quantity' => $item->get_quantity(), 'total' => $item->get_total()];
            }
            $woo['purchase'] = [
                'transaction_id' => (string) $order->get_order_number(),
                'value'          => round((float) $order->get_subtotal() - (float) $order->get_discount_total(), 2),
                'currency'       => $order->get_currency(),
                'items'          => haptic_conversions_woo_items($lines),
                // Pour les conversions améliorées d'Optimizr Pro (haché dans le navigateur, avec l'accord du visiteur)
                'email'          => $order->get_billing_email(),
            ];
            $order->update_meta_data('_haptic_conversion_sent', time());
            $order->save();
        }
    }

    return count($woo) > 1 ? $woo : [];
}

// Ajout au panier par un formulaire envoyé normalement (pas en AJAX) : évènement gardé pour la page suivante
add_action('woocommerce_add_to_cart', function ($cart_item_key, $product_id, $quantity, $variation_id) {
    if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || !empty($_GET['wc-ajax']) || !WC()->session) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }
    $settings = haptic_conversions_settings();
    if (empty($settings['actions']['add_to_cart'])) {
        return;
    }
    $product = wc_get_product($variation_id ?: $product_id);
    if (!$product) {
        return;
    }
    $price = (float) wc_get_price_excluding_tax($product);
    WC()->session->set('haptic_conversion_added', [
        'value' => round($price * $quantity, 2),
        'items' => haptic_conversions_woo_items([['product' => $product, 'quantity' => $quantity, 'total' => $price * $quantity]]),
    ]);
}, 10, 4);

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
                    $active = !empty($settings['actions'][$key]) && ($key !== 'booking' || $settings['booking']) && ($key !== 'thankyou' || $settings['thankyou']);
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
