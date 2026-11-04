<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Assistant campagnes : parcours guidé pour mettre en place le suivi des campagnes Google (et Meta avec Optimizr Pro).
 *
 * Même présentation et même navigation que l'assistant de configuration (css/optimizr-onboarding.css,
 * js/optimizr-onboarding.js) : objectif, outils de mesure, actions à suivre (conversions), consentement, récapitulatif.
 * Les réglages sont enregistrés à la dernière étape, puis un écran donne les vérifications à faire.
 * Optimizr Pro ajoute ses objectifs et ses étapes (Google Ads, Meta) par les filtres haptic_campaigns_goals,
 * haptic_campaigns_steps et haptic_campaigns_save.
 * Suivi des conversions sur le site : functions/conversions.php.
 */

define('HAPTIC_CAMPAIGNS_SLUG', 'haptic-campaigns');

function haptic_campaigns_url($args = [])
{
    return add_query_arg($args, admin_url('admin.php?page=' . HAPTIC_CAMPAIGNS_SLUG));
}

// Page du menu Optimizr, affichée sans l'interface de l'administration
add_action('admin_menu', function () {
    $hook = add_submenu_page('haptic-options', __('Campaign tracking - Optimizr', 'optimizr'), __('Campaign tracking', 'optimizr'), 'manage_options', HAPTIC_CAMPAIGNS_SLUG, '__return_null');
    if ($hook) {
        add_action('load-' . $hook, 'haptic_campaigns_render');
    }
}, 21);

/* ---------- Contenu ---------- */

/**
 * Objectifs proposés à la première étape : clé => [titre, description, icône].
 * Chaque objectif affiche les étapes dont le 'module' porte sa clé.
 */
function haptic_campaigns_goals()
{
    // Optimizr Pro ajoute Google Ads à l'objectif Google, et l'objectif Meta
    return apply_filters('haptic_campaigns_goals', [
        'google' => [__('Google', 'optimizr'), __('Google Analytics 4 or Google Tag Manager: visits and conversions measured, with Google Consent Mode v2.', 'optimizr'), 'search'],
    ]);
}

/**
 * Étapes de l'assistant, même format que haptic_onboarding_steps().
 * 'module' : clé d'objectif qui affiche l'étape ; 'illu' : illustration de l'assistant de configuration reprise.
 */
function haptic_campaigns_steps()
{
    return apply_filters('haptic_campaigns_steps', [
        'welcome'     => ['label' => __('Goal', 'optimizr'), 'module' => '', 'pillar' => 'all', 'illu' => 'welcome', 'render' => 'haptic_campaigns_step_welcome',
            'kicker' => __('Campaigns', 'optimizr'), 'tagline' => __('An ad that is measured is an ad you can improve.', 'optimizr')],
        'google'      => ['label' => __('Analytics', 'optimizr'), 'module' => 'google', 'pillar' => 'performance', 'illu' => 'seo', 'render' => 'haptic_campaigns_step_google',
            'kicker' => __('Measurement', 'optimizr'), 'tagline' => __('Know where visitors come from and what they do.', 'optimizr')],
        'conversions' => ['label' => __('Conversions', 'optimizr'), 'module' => '', 'pillar' => 'optimization', 'illu' => 'modules', 'render' => 'haptic_campaigns_step_conversions',
            'kicker' => __('Conversions', 'optimizr'), 'tagline' => __('A form sent or a call is worth more than a thousand visits.', 'optimizr')],
        'consent'     => ['label' => __('Consent', 'optimizr'), 'module' => '', 'pillar' => 'security', 'illu' => 'cookies', 'render' => 'haptic_campaigns_step_consent',
            'kicker' => __('Privacy', 'optimizr'), 'tagline' => __('No tracker before the visitor agrees, Google Consent Mode v2 included.', 'optimizr')],
        'summary'     => ['label' => __('Summary', 'optimizr'), 'module' => '', 'pillar' => 'all', 'illu' => 'summary', 'render' => 'haptic_onboarding_step_summary',
            'kicker' => __('Last step', 'optimizr'), 'tagline' => __('Review, then apply. Everything can still be changed later.', 'optimizr')],
    ]);
}

/**
 * Actions du visiteur suivies comme conversions : clé => [titre, description, icône, cochée par défaut].
 * Évènements envoyés : voir haptic_conversion_events() (functions/conversions.php).
 */
function haptic_conversion_actions()
{
    $cf7 = defined('WPCF7_VERSION');
    return apply_filters('haptic_conversion_actions', [
        'form'     => [__('Contact form sent', 'optimizr'), $cf7
            ? __('Contact Form 7: counted when the message is actually sent, not on each click.', 'optimizr')
            : __('Contact Form 7 is not active on this site: counted as soon as it is.', 'optimizr'), 'mail', $cf7],
        'phone'    => [__('Click on a phone number', 'optimizr'), __('Every tel: link, on a computer or a phone.', 'optimizr'), 'phone', true],
        'email'    => [__('Click on an email address', 'optimizr'), __('Every mailto: link.', 'optimizr'), 'mail', true],
        'whatsapp' => [__('Click to WhatsApp', 'optimizr'), __('Links to wa.me and api.whatsapp.com.', 'optimizr'), 'phone', true],
        'booking'  => [__('Click to book an appointment', 'optimizr'), __('Links to the booking services listed below (Doctolib, Calendly…).', 'optimizr'), 'check', false],
    ]);
}

/** Réglages enregistrés du suivi des conversions, avec les valeurs par défaut. */
function haptic_conversions_settings()
{
    $saved = get_option('haptic_conversions');
    $actions = [];
    foreach (haptic_conversion_actions() as $key => $action) {
        $actions[$key] = is_array($saved) ? (int) !empty($saved['actions'][$key]) : (int) $action[3];
    }
    return [
        'actions' => $actions,
        'booking' => is_array($saved) && isset($saved['booking']) ? (array) $saved['booking'] : ['doctolib.fr', 'calendly.com', 'planity.com'],
    ];
}

/** Domaines de prise de rendez-vous saisis (un par ligne, adresse complète acceptée). */
function haptic_conversions_sanitize_domains($text)
{
    $domains = [];
    foreach (preg_split('/[\s,]+/', (string) $text) as $line) {
        $line = strtolower(trim($line));
        if ($line === '') {
            continue;
        }
        $host = wp_parse_url(preg_match('#^[a-z]+://#', $line) ? $line : 'https://' . $line, PHP_URL_HOST);
        $host = $host ? preg_replace('/^www\./', '', $host) : '';
        if ($host && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host)) {
            $domains[] = $host;
        }
    }
    return array_values(array_unique($domains));
}

/* ---------- Enregistrement ---------- */

add_action('admin_post_haptic_campaigns_save', 'haptic_campaigns_save');

function haptic_campaigns_save()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'optimizr'), 403);
    }
    check_admin_referer('haptic_campaigns');

    $post = wp_unslash($_POST);
    $notices = [];

    // Le suivi passe par le bandeau de consentement : module Cookies et bandeau activés
    $modules = get_option('haptic_modules');
    if (is_array($modules) && empty($modules['cookies'])) {
        $modules['cookies'] = 1;
        update_option('haptic_modules', $modules);
    }
    update_option('haptic_option_enable_tarteaucitron', empty($post['haptic_option_enable_tarteaucitron']) ? 0 : 1);
    if (empty($post['haptic_option_enable_tarteaucitron'])) {
        $notices[] = __('The consent banner is off: analytics, advertising tags and conversions are not loaded.', 'optimizr');
    }

    $goals = [];
    foreach (array_keys(haptic_campaigns_goals()) as $goal) {
        $goals[$goal] = empty($post['haptic_campaigns_goal'][$goal]) ? 0 : 1;
    }
    update_option('haptic_campaigns_goals', $goals);

    // Identifiants Google : format vérifié, un identifiant refusé laisse l'ancien en place
    if (!empty($goals['google'])) {
        $ids = [
            'google_analytics' => ['/^G-[A-Z0-9]{4,}$/', 'Google Analytics 4'],
            'google_tags'      => ['/^GTM-[A-Z0-9]{4,}$/', 'Google Tag Manager'],
        ];
        foreach ($ids as $service => list($pattern, $label)) {
            $field = 'haptic_campaigns_' . $service;
            if (!isset($post[$field])) {
                continue;
            }
            $id = strtoupper(sanitize_text_field(trim((string) $post[$field])));
            if ($id !== '' && !preg_match($pattern, $id)) {
                /* translators: %s: service name (Google Analytics 4, Google Tag Manager) */
                $notices[] = sprintf(__('%s: the ID does not have the expected format and was not saved.', 'optimizr'), $label);
                continue;
            }
            $value = get_option('haptic_option_service_' . $service, null);
            if ($id === '' && $value === null) {
                continue;
            }
            $value = (array) $value;
            $value['ID'] = $id;
            update_option('haptic_option_service_' . $service, $value);
        }
    }

    $actions = [];
    foreach (array_keys(haptic_conversion_actions()) as $key) {
        $actions[$key] = empty($post['haptic_conversions_action'][$key]) ? 0 : 1;
    }
    $booking = haptic_conversions_sanitize_domains($post['haptic_conversions_booking'] ?? '');
    if (!empty($actions['booking']) && !$booking) {
        $notices[] = __('Appointment booking: no valid address entered, these clicks are not tracked.', 'optimizr');
    }
    update_option('haptic_conversions', ['actions' => $actions, 'booking' => $booking]);

    // Étapes d'une extension (Optimizr Pro) : leurs réglages, et les messages à afficher sur l'écran final
    $notices = (array) apply_filters('haptic_campaigns_save', $notices, $post, $goals);

    if ($notices) {
        set_transient('haptic_campaigns_notices_' . get_current_user_id(), $notices, 10 * MINUTE_IN_SECONDS);
    }
    // Réglages enregistrés par admin-post.php : le cache de pages est vidé (haptic_cache_option_changed())
    do_action('haptic_campaigns_saved', $goals);

    wp_safe_redirect(haptic_campaigns_url(['step' => 'done']));
    exit;
}

/* ---------- Affichage ---------- */

function haptic_campaigns_render()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'optimizr'), 403);
    }

    $css = HAPTIC_OPTIMIZR_PATH . 'css/optimizr-onboarding.css';
    $js  = HAPTIC_OPTIMIZR_PATH . 'js/optimizr-onboarding.js';
    wp_register_style('optimizr-onboarding', HAPTIC_OPTIMIZR_URL . 'css/optimizr-onboarding.css', [], filemtime($css));
    wp_register_script('optimizr-onboarding', HAPTIC_OPTIMIZR_URL . 'js/optimizr-onboarding.js', ['wp-i18n'], filemtime($js), true);
    wp_set_script_translations('optimizr-onboarding', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- écran affiché, lecture seule
    $done = sanitize_key(wp_unslash($_GET['step'] ?? '')) === 'done';
    $steps = haptic_campaigns_steps();

    nocache_headers();
    header('Content-Type: text/html; charset=' . get_option('blog_charset'));
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
    <meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $done ? esc_html__('Tracking in place', 'optimizr') : esc_html__('Campaign tracking', 'optimizr'); ?> - Optimizr</title>
    <?php wp_print_inline_script_tag("document.documentElement.className = 'js';"); ?>
    <?php wp_print_styles(['optimizr-onboarding']); ?>
</head>
<body class="ob-body">
    <?php haptic_onboarding_svg_defs(); ?>
    <div class="ob ob--campaigns<?php echo $done ? ' ob--done' : ''; ?>">
        <aside class="ob-visual" aria-hidden="true">
            <div class="ob-brand">
                <img class="ob-brand__logo" src="<?php echo esc_url(HAPTIC_OPTIMIZR_URL . 'images/optimizr-icon.svg'); ?>" width="34" height="34" alt="" />
                <span class="ob-brand__name">Optimizr</span>
            </div>
            <div class="ob-stage">
                <?php if ($done) : ?>
                    <div class="ob-illu is-active"><?php haptic_onboarding_illustration('done'); ?></div>
                <?php else : ?>
                    <?php foreach (array_keys($steps) as $i => $slug) : ?>
                        <div class="ob-illu<?php echo $i === 0 ? ' is-active' : ''; ?>" data-illu="<?php echo esc_attr($slug); ?>"><?php haptic_onboarding_illustration($steps[$slug]['illu'] ?? $slug); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="ob-caption">
                <?php if ($done) : ?>
                    <p class="ob-caption__kicker"><?php esc_html_e('Last check', 'optimizr'); ?></p>
                    <p class="ob-caption__text"><?php esc_html_e('Five minutes of testing now saves weeks of blind campaigns.', 'optimizr'); ?></p>
                <?php else : ?>
                    <?php foreach ($steps as $slug => $step) : ?>
                        <div class="ob-caption__item<?php echo $slug === 'welcome' ? ' is-active' : ''; ?>" data-caption="<?php echo esc_attr($slug); ?>" data-pillar="<?php echo esc_attr($step['pillar']); ?>">
                            <p class="ob-caption__kicker"><?php echo esc_html($step['kicker']); ?></p>
                            <p class="ob-caption__text"><?php echo esc_html($step['tagline']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <main class="ob-panel">
            <?php $done ? haptic_campaigns_render_done() : haptic_campaigns_render_form($steps); ?>
        </main>
    </div>
    <?php wp_print_scripts(['optimizr-onboarding']); ?>
    <?php do_action('haptic_campaigns_print_scripts'); ?>
</body>
</html>
    <?php
    exit;
}

function haptic_campaigns_render_form($steps)
{
    ?>
    <div class="ob-top">
        <div class="ob-progress" aria-hidden="true"><span class="ob-progress__bar" data-ob-progress></span></div>
        <p class="ob-top__count" data-ob-count aria-live="polite"></p>
        <a class="ob-top__skip" href="<?php echo esc_url(admin_url('admin.php?page=haptic-options')); ?>"><?php esc_html_e('Exit the wizard', 'optimizr'); ?></a>
    </div>

    <ol class="ob-stepper" data-ob-stepper>
        <?php foreach ($steps as $slug => $step) : ?>
            <li class="ob-stepper__item" data-stepper="<?php echo esc_attr($slug); ?>" data-module="<?php echo esc_attr($step['module']); ?>"><span class="ob-stepper__dot" aria-hidden="true"></span><span class="ob-stepper__label"><?php echo esc_html($step['label']); ?></span></li>
        <?php endforeach; ?>
    </ol>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ob-form" data-ob-form novalidate>
        <input type="hidden" name="action" value="haptic_campaigns_save">
        <?php wp_nonce_field('haptic_campaigns'); ?>

        <?php foreach (array_keys($steps) as $i => $slug) : ?>
            <section class="ob-step<?php echo $i === 0 ? ' is-active' : ''; ?>" data-step="<?php echo esc_attr($slug); ?>" data-module="<?php echo esc_attr($steps[$slug]['module']); ?>" aria-labelledby="ob-title-<?php echo esc_attr($slug); ?>">
                <?php
                call_user_func($steps[$slug]['render'], []);
                do_action('haptic_campaigns_step_after', $slug);
                ?>
            </section>
        <?php endforeach; ?>

        <div class="ob-nav">
            <button type="button" class="ob-btn ob-btn--ghost" data-ob-prev hidden><?php esc_html_e('Back', 'optimizr'); ?></button>
            <button type="button" class="ob-btn ob-btn--primary" data-ob-next><?php esc_html_e('Get started', 'optimizr'); ?></button>
            <button type="submit" class="ob-btn ob-btn--primary" data-ob-submit><?php esc_html_e('Apply settings', 'optimizr'); ?></button>
        </div>
    </form>
    <?php
}

/** Lien d'aide « Où le trouver ? » vers la bonne page d'un service. */
function haptic_campaigns_help($text, $url = '', $link = '')
{
    echo '<span class="ob-field__help">' . esc_html($text);
    if ($url) {
        echo ' <a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link ?: __('Open', 'optimizr')) . ' ↗</a>';
    }
    echo '</span>';
}

function haptic_campaigns_step_welcome()
{
    haptic_onboarding_step_head(
        'welcome',
        __('Campaign tracking', 'optimizr'),
        __('Which campaigns do you run?', 'optimizr'),
        __('Choose the platforms: the wizard only shows what they need, then the actions to count as conversions.', 'optimizr')
    );
    $saved = get_option('haptic_campaigns_goals');
    ?>
    <div class="ob-modules">
        <?php foreach (haptic_campaigns_goals() as $goal => list($title, $desc, $icon)) : ?>
            <label class="ob-module" for="ob-goal-<?php echo esc_attr($goal); ?>">
                <span class="ob-module__icon"><?php echo wp_kses(haptic_icon($icon, 20), haptic_kses_ui()); ?></span>
                <span class="ob-module__text">
                    <span class="ob-module__title"><?php echo esc_html($title); ?></span>
                    <span class="ob-module__desc"><?php echo esc_html($desc); ?></span>
                </span>
                <input type="checkbox" role="switch" class="ob-switch" id="ob-goal-<?php echo esc_attr($goal); ?>" name="haptic_campaigns_goal[<?php echo esc_attr($goal); ?>]" value="1" data-module-toggle="<?php echo esc_attr($goal); ?>" data-summary="<?php echo esc_attr($title); ?>" <?php checked(is_array($saved) ? !empty($saved[$goal]) : true); ?>>
            </label>
        <?php endforeach; ?>
    </div>
    <?php if (!haptic_is_pro()) : ?>
        <p class="ob-note"><?php echo wp_kses(haptic_icon('sparkles', 16), haptic_kses_ui()); ?><span><?php esc_html_e('Google Ads conversions, enhanced conversions and Meta (Pixel, Lead and Contact events, domain verification) are set up in the same wizard with Optimizr Pro.', 'optimizr'); ?> <a href="<?php echo esc_url(HAPTIC_OPTIMIZR_PRO_INFO_URL); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Learn more', 'optimizr'); ?> ↗</a></span></p>
    <?php endif; ?>
    <p class="ob-note"><?php echo wp_kses(haptic_icon('info', 16), haptic_kses_ui()); ?><?php esc_html_e('About 5 minutes. Keep your Google (and Meta) accounts open in another tab to copy the IDs.', 'optimizr'); ?></p>
    <?php
}

function haptic_campaigns_step_google()
{
    haptic_onboarding_step_head(
        'google',
        __('Google', 'optimizr'),
        __('Connect Google Analytics', 'optimizr'),
        __('Google Analytics 4 measures visits and conversions, and shares them with Google Ads once the accounts are linked.', 'optimizr')
    );
    $ga = (array) get_option('haptic_option_service_google_analytics', []);
    $gtm = (array) get_option('haptic_option_service_google_tags', []);
    ?>
    <div class="ob-fields">
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label"><?php esc_html_e('Google Analytics 4 measurement ID', 'optimizr'); ?> <span class="ob-tag"><?php esc_html_e('Recommended', 'optimizr'); ?></span></span>
            <input type="text" name="haptic_campaigns_google_analytics" value="<?php echo esc_attr($ga['ID'] ?? ''); ?>" placeholder="G-XXXXXXXXXX" pattern="[Gg]-[A-Za-z0-9]{4,}" title="<?php esc_attr_e('Starts with G-, e.g. G-AB12CD34EF.', 'optimizr'); ?>" spellcheck="false" autocomplete="off" data-summary-value="Google Analytics 4">
            <?php haptic_campaigns_help(__('Google Analytics › Admin › Data streams › your website: “Measurement ID”. No account yet? Create a property, then a Web stream with the site address.', 'optimizr'), 'https://analytics.google.com/analytics/web/#/a/admin', __('Open Google Analytics', 'optimizr')); ?>
        </label>
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label"><?php esc_html_e('Google Tag Manager container', 'optimizr'); ?> <span class="ob-field__opt"><?php esc_html_e('optional', 'optimizr'); ?></span></span>
            <input type="text" name="haptic_campaigns_google_tags" value="<?php echo esc_attr($gtm['ID'] ?? ''); ?>" placeholder="GTM-XXXXXXX" pattern="[Gg][Tt][Mm]-[A-Za-z0-9]{4,}" title="<?php esc_attr_e('Starts with GTM-, e.g. GTM-AB12CD3.', 'optimizr'); ?>" spellcheck="false" autocomplete="off" data-summary-value="Google Tag Manager">
            <?php haptic_campaigns_help(__('Only if an agency or you already manage tags in Tag Manager. The ID is shown next to the container name.', 'optimizr'), 'https://tagmanager.google.com/', __('Open Tag Manager', 'optimizr')); ?>
        </label>
    </div>
    <p class="ob-note"><?php echo wp_kses(haptic_icon('alert', 16), haptic_kses_ui()); ?><?php esc_html_e('Fill in one or the other: if Google Analytics is already set up inside your Tag Manager container, leave the measurement ID empty, otherwise every visit is counted twice.', 'optimizr'); ?></p>
    <?php
}

function haptic_campaigns_step_conversions()
{
    haptic_onboarding_step_head(
        'conversions',
        __('Conversions', 'optimizr'),
        __('What counts as a result?', 'optimizr'),
        __('Choose the visitor actions that matter. Each one is sent to the tools set up above, so campaigns can optimize for real contacts rather than clicks.', 'optimizr')
    );
    $settings = haptic_conversions_settings();
    ?>
    <div class="ob-modules ob-modules--single">
        <?php foreach (haptic_conversion_actions() as $key => list($title, $desc, $icon)) : ?>
            <label class="ob-module" for="ob-conv-<?php echo esc_attr($key); ?>">
                <span class="ob-module__icon"><?php echo wp_kses(haptic_icon($icon, 20), haptic_kses_ui()); ?></span>
                <span class="ob-module__text">
                    <span class="ob-module__title"><?php echo esc_html($title); ?></span>
                    <span class="ob-module__desc"><?php echo esc_html($desc); ?></span>
                </span>
                <input type="checkbox" role="switch" class="ob-switch" id="ob-conv-<?php echo esc_attr($key); ?>" name="haptic_conversions_action[<?php echo esc_attr($key); ?>]" value="1" data-summary="<?php echo esc_attr($title); ?>" <?php checked(!empty($settings['actions'][$key])); ?>>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="ob-fields">
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label"><?php esc_html_e('Booking services', 'optimizr'); ?> <span class="ob-field__opt"><?php esc_html_e('one per line', 'optimizr'); ?></span></span>
            <textarea name="haptic_conversions_booking" rows="3" spellcheck="false" placeholder="doctolib.fr" data-summary-value="<?php esc_attr_e('Booking services', 'optimizr'); ?>"><?php echo esc_textarea(implode("\n", $settings['booking'])); ?></textarea>
            <?php haptic_campaigns_help(__('Domain of the service, or the full address of your booking page. Used by “Click to book an appointment”.', 'optimizr')); ?>
        </label>
    </div>
    <p class="ob-note"><?php echo wp_kses(haptic_icon('info', 16), haptic_kses_ui()); ?><?php esc_html_e('Events sent to Google Analytics: generate_lead (form), click_phone, click_email, click_whatsapp and click_booking. Mark them as key events in Google Analytics to use them in Google Ads.', 'optimizr'); ?></p>
    <?php
}

function haptic_campaigns_step_consent()
{
    haptic_onboarding_step_head(
        'consent',
        __('Consent', 'optimizr'),
        __('Track with the visitor\'s consent', 'optimizr'),
        __('Tags go through the Tarteaucitron banner. Google receives the visitor\'s choice through Consent Mode v2, which Google Ads requires in Europe.', 'optimizr')
    );
    $privacy = (int) get_option('wp_page_for_privacy_policy');
    $published = $privacy && get_post_status($privacy) === 'publish';
    ?>
    <div class="ob-options">
        <?php haptic_onboarding_switch('haptic_option_enable_tarteaucitron', get_option('haptic_option_enable_tarteaucitron', 1), __('Show the consent banner', 'optimizr'), __('Required as soon as an analytics or advertising service is used.', 'optimizr'), __('Required', 'optimizr')); ?>
    </div>
    <ul class="ob-checks">
        <li class="ob-checks__item is-ok"><?php echo wp_kses(haptic_icon('check', 16), haptic_kses_ui()); ?><span><?php esc_html_e('Google Consent Mode v2 sent automatically (ad_storage, analytics_storage, ad_user_data, ad_personalization).', 'optimizr'); ?></span></li>
        <li class="ob-checks__item <?php echo $published ? 'is-ok' : 'is-warn'; ?>"><?php echo wp_kses(haptic_icon($published ? 'check' : 'alert', 16), haptic_kses_ui()); ?><span>
            <?php if ($published) : ?>
                <?php esc_html_e('Privacy policy published and linked from the banner.', 'optimizr'); ?>
            <?php else : ?>
                <?php esc_html_e('No published privacy policy: the banner cannot link to it.', 'optimizr'); ?>
                <a href="<?php echo esc_url(admin_url('options-privacy.php')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Choose the page', 'optimizr'); ?> ↗</a>
            <?php endif; ?>
        </span></li>
        <li class="ob-checks__item is-ok"><?php echo wp_kses(haptic_icon('check', 16), haptic_kses_ui()); ?><span><?php esc_html_e('Conversions only reach Meta once the visitor accepts its cookies.', 'optimizr'); ?></span></li>
    </ul>
    <p class="ob-note"><?php echo wp_kses(haptic_icon('info', 16), haptic_kses_ui()); ?><?php esc_html_e('List the tools used (Google Analytics, Google Ads, Meta) in your privacy policy, with what they collect.', 'optimizr'); ?></p>
    <?php
}

/** Écran final : vérifications à faire, et ce qui reste à régler dans les comptes Google et Meta. */
function haptic_campaigns_render_done()
{
    $key = 'haptic_campaigns_notices_' . get_current_user_id();
    $notices = (array) get_transient($key);
    delete_transient($key);

    $checks = apply_filters('haptic_campaigns_checks', [
        [__('Check the tags', 'optimizr'), __('Open the site in a private window, accept the cookies, then check with Google Tag Assistant that your tags fire.', 'optimizr'), 'https://tagassistant.google.com/'],
        [__('Test a conversion', 'optimizr'), __('Send the contact form or click the phone number, then look for the event in Google Analytics › Reports › Realtime.', 'optimizr'), 'https://analytics.google.com/'],
        [__('Mark the key events', 'optimizr'), __('In Google Analytics › Admin › Events, mark generate_lead and the click events you track as key events (they show up after the first conversion).', 'optimizr'), ''],
        [__('Link Google Analytics and Google Ads', 'optimizr'), __('Google Analytics › Admin › Product links › Google Ads, then import the key events as conversions in Google Ads.', 'optimizr'), ''],
    ]);
    ?>
    <div class="ob-done">
        <p class="ob-step__eyebrow"><?php esc_html_e('Settings applied', 'optimizr'); ?></p>
        <h1 class="ob-step__title" tabindex="-1"><?php esc_html_e('Tracking is in place', 'optimizr'); ?></h1>
        <p class="ob-step__intro"><?php esc_html_e('Tags and conversions now load on the site after consent. A few minutes of checks before launching the campaigns:', 'optimizr'); ?></p>

        <?php foreach (array_filter($notices) as $notice) : ?>
            <p class="ob-alert" role="alert"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><?php echo esc_html($notice); ?></p>
        <?php endforeach; ?>

        <ol class="ob-todo">
            <?php foreach ($checks as list($title, $text, $url)) : ?>
                <li class="ob-todo__item">
                    <p class="ob-todo__title"><?php echo esc_html($title); ?></p>
                    <p class="ob-todo__text"><?php echo esc_html($text); ?><?php if ($url) : ?> <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open', 'optimizr'); ?> ↗</a><?php endif; ?></p>
                </li>
            <?php endforeach; ?>
        </ol>

        <p class="ob-note"><?php echo wp_kses(haptic_icon('info', 16), haptic_kses_ui()); ?><?php esc_html_e('The Cookies & GDPR tab shows the tracking status and the last conversion seen on the site.', 'optimizr'); ?></p>

        <div class="ob-nav ob-nav--done">
            <a class="ob-btn ob-btn--ghost" href="<?php echo esc_url(haptic_campaigns_url()); ?>"><?php esc_html_e('Back to the wizard', 'optimizr'); ?></a>
            <a class="ob-btn ob-btn--primary" href="<?php echo esc_url(haptic_admin_tab_url('cookies')); ?>"><?php esc_html_e('See the tracking status', 'optimizr'); ?></a>
        </div>
    </div>
    <?php
}
