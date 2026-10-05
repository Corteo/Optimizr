<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Assistant de configuration : page plein écran en plusieurs étapes.
 *
 * Ouvert automatiquement à la première activation du plugin, puis à la demande
 * (bouton « Assistant » de la page d'options, lien dans la liste des extensions).
 * Les réglages ne sont enregistrés qu'à la dernière étape, par admin-post.php.
 * Mise en forme : css/optimizr-onboarding.css, navigation : js/optimizr-onboarding.js.
 */

define('HAPTIC_ONBOARDING_SLUG', 'haptic-onboarding');

function haptic_onboarding_url($args = [])
{
    return add_query_arg($args, admin_url('admin.php?page=' . HAPTIC_ONBOARDING_SLUG));
}

/* ---------- Ouverture : à l'activation, puis à la demande ---------- */

/** Appelé par haptic_options_activate() : l'assistant s'ouvre tant qu'il n'a été ni terminé ni écarté. */
function haptic_onboarding_schedule_redirect()
{
    if (!get_option('haptic_onboarding_done')) {
        set_transient('haptic_onboarding_redirect', 1, MINUTE_IN_SECONDS);
    }
}

add_action('admin_init', function () {
    if (!get_transient('haptic_onboarding_redirect')) {
        return;
    }
    delete_transient('haptic_onboarding_redirect');

    // Pas pendant une activation groupée, une requête AJAX ou depuis l'administration du réseau
    if (wp_doing_ajax() || is_network_admin() || isset($_GET['activate-multi']) || !current_user_can('manage_options')) {
        return;
    }

    wp_safe_redirect(haptic_onboarding_url());
    exit;
});

// Lien dans la liste des extensions
add_filter('plugin_action_links_' . plugin_basename(HAPTIC_OPTIMIZR_FILE), function ($links) {
    if (current_user_can('manage_options')) {
        array_unshift($links, '<a href="' . esc_url(haptic_onboarding_url()) . '">' . esc_html__('Setup wizard', 'optimizr') . '</a>');
    }
    return $links;
});

// Page du menu Optimizr, affichée sans l'interface de l'administration
add_action('admin_menu', function () {
    $hook = add_submenu_page('haptic-options', __('Setup wizard - Optimizr', 'optimizr'), __('Wizard', 'optimizr'), 'manage_options', HAPTIC_ONBOARDING_SLUG, '__return_null');
    if ($hook) {
        add_action('load-' . $hook, 'haptic_onboarding_render');
    }
}, 20);

/* ---------- Contenu des étapes ---------- */

/**
 * Étapes de l'assistant. 'module' : étape affichée seulement si ce module est choisi.
 * 'pillar' : thème mis en avant dans le panneau visuel (security, performance, optimization ou all).
 * 'render' : fonction qui affiche l'étape (par défaut haptic_onboarding_step_{étape}) ; Optimizr Pro ajoute les siennes.
 */
function haptic_onboarding_steps()
{
    return apply_filters('haptic_onboarding_steps', [
        'welcome'  => ['label' => __('Welcome', 'optimizr'), 'module' => '', 'pillar' => 'all',
            'kicker' => __('Security · Performance · Optimization', 'optimizr'), 'tagline' => __('One plugin for a safe, fast and well-ranked site.', 'optimizr')],
        'modules'  => ['label' => __('Modules', 'optimizr'), 'module' => '', 'pillar' => 'all',
            'kicker' => __('Tailor-made', 'optimizr'), 'tagline' => __('Only enabled modules load their code: nothing unnecessary slows the site down.', 'optimizr')],
        'security' => ['label' => __('Security', 'optimizr'), 'module' => 'security', 'pillar' => 'security',
            'kicker' => __('Security', 'optimizr'), 'tagline' => __('Bots try passwords around the clock: shut the door on them.', 'optimizr')],
        'seo'      => ['label' => _x('SEO', 'onboarding step', 'optimizr'), 'module' => 'seo', 'pillar' => 'performance',
            'kicker' => __('Performance', 'optimizr'), 'tagline' => __('Clean title and description tags: Google shows your pages the way you want.', 'optimizr')],
        'cookies'  => ['label' => __('Cookies & GDPR', 'optimizr'), 'module' => 'cookies', 'pillar' => 'security',
            'kicker' => __('Privacy', 'optimizr'), 'tagline' => __('No tracker before the visitor agrees, Google Consent Mode v2 included.', 'optimizr')],
        'summary'  => ['label' => __('Summary', 'optimizr'), 'module' => '', 'pillar' => 'all',
            'kicker' => __('Last step', 'optimizr'), 'tagline' => __('Review, then apply. Everything can still be changed in Optimizr.', 'optimizr')],
    ]);
}

/** Regroupement des modules par thème dans l'étape « Modules ». */
function haptic_onboarding_module_groups()
{
    return [
        'security'     => ['label' => __('Security & compliance', 'optimizr'), 'modules' => ['security', 'cookies']],
        'performance'  => ['label' => __('Performance & visibility', 'optimizr'), 'modules' => ['seo', 'redirects', 'local', 'pages']],
        'optimization' => ['label' => __('Optimization & content', 'optimizr'), 'modules' => ['ai', 'cpts', 'thumbnails', 'emails']],
    ];
}

/**
 * Protections proposées par l'étape Sécurité (options haptic_sec_*).
 * 'recommended' : cochée par défaut tant que l'option n'a jamais été enregistrée.
 */
function haptic_onboarding_security_toggles()
{
    // Optimizr Pro ajoute ses protections (version masquée, XML-RPC, en-têtes…), enregistrées par la même boucle
    return apply_filters('haptic_onboarding_security_toggles', [
        'limit_login'       => [__('Limit login attempts', 'optimizr'), __('An IP address is blocked for 15 minutes after 5 wrong passwords.', 'optimizr'), true],
        'generic_errors'    => [__('Neutral error messages', 'optimizr'), __('The login page no longer says whether the username or the password is wrong.', 'optimizr'), true],
        'login_alert'       => [__('Login alert', 'optimizr'), __('An email alerts an administrator who logs in from an unknown IP address.', 'optimizr'), false],
    ]);
}

/** Réglages proposés par l'étape Référencement : option => [titre, description, valeur par défaut]. */
function haptic_onboarding_seo_toggles()
{
    // Optimizr Pro ajoute les données structurées et les options d'indexation
    return apply_filters('haptic_onboarding_seo_toggles', [
        'haptic_enable_seo'              => [__('Automatic meta tags', 'optimizr'), __('Title and description generated for any content that has none.', 'optimizr'), 1],
        'haptic_enable_opengraph'        => [__('Share previews (OpenGraph)', 'optimizr'), __('Title, description and image when shared on social networks.', 'optimizr'), 1],
    ]);
}

/** Charge le fichier d'un module pas encore actif dans cette requête (fonctions de nettoyage, listes). */
function haptic_onboarding_require($function, $file)
{
    if (!function_exists($function)) {
        require_once HAPTIC_OPTIMIZR_PATH . 'functions/' . $file;
    }
}

/** Variables proposées pour les motifs de titre et de description. */
function haptic_onboarding_seo_variables()
{
    return function_exists('haptic_seo_pattern_variables') ? haptic_seo_pattern_variables() : [
        '{titre_page}' => __('Page title', 'optimizr'),
        '{nom_site}'   => __('Site name', 'optimizr'),
        '{slogan}'     => __('Tagline', 'optimizr'),
        '{resume}'     => __('Excerpt', 'optimizr'),
        '{categorie}'  => __('Category', 'optimizr'),
    ];
}

/** Services de mesure proposés à l'étape Cookies : champ du formulaire => [service, clé, libellé, exemple]. */
function haptic_onboarding_cookie_fields()
{
    // Optimizr Pro ajoute Microsoft Clarity et le Pixel Meta
    return apply_filters('haptic_onboarding_cookie_fields', [
        'haptic_ob_service_google_analytics' => ['google_analytics', 'ID', 'Google Analytics 4', 'G-XXXXXXXXXX'],
        'haptic_ob_service_google_tags'      => ['google_tags', 'ID', 'Google Tag Manager', 'GTM-XXXXXXX'],
    ]);
}

/* ---------- Enregistrement ---------- */

add_action('admin_post_haptic_onboarding_save', 'haptic_onboarding_save');

function haptic_onboarding_save()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'optimizr'), 403);
    }
    check_admin_referer('haptic_onboarding');

    $post = wp_unslash($_POST);

    $modules = haptic_sanitize_modules(isset($post['haptic_modules']) ? $post['haptic_modules'] : []);
    update_option('haptic_modules', $modules);

    $flag = function ($name) use ($post) {
        return empty($post[$name]) ? 0 : 1;
    };

    $text = function ($name) use ($post) {
        return isset($post[$name]) ? sanitize_text_field(trim((string) $post[$name])) : null;
    };
    // Messages affichés sur l'écran final (réglage refusé, par exemple)
    $notices = [];

    if (!empty($modules['security'])) {
        foreach (array_keys(haptic_onboarding_security_toggles()) as $key) {
            update_option('haptic_sec_' . $key, $flag('haptic_sec_' . $key));
        }

        if (isset($post['haptic_sec_login_max'])) {
            update_option('haptic_sec_login_max', min(20, max(2, absint($post['haptic_sec_login_max']) ?: 5)));
        }
        if (isset($post['haptic_sec_login_lockout'])) {
            update_option('haptic_sec_login_lockout', min(1440, max(1, absint($post['haptic_sec_login_lockout']) ?: 15)));
        }
        if (isset($post['haptic_sec_login_whitelist'])) {
            $ips = array_filter(array_map('trim', preg_split('/\R/', (string) $post['haptic_sec_login_whitelist'])), function ($ip) {
                return filter_var($ip, FILTER_VALIDATE_IP);
            });
            update_option('haptic_sec_login_whitelist', implode("\n", array_unique($ips)));
        }

        // Adresse de connexion : mêmes contrôles que l'onglet Sécurité, et l'email de la nouvelle adresse part à l'enregistrement
        if (isset($post['haptic_sec_login_slug'])) {
            haptic_onboarding_require('haptic_sec_sanitize_login_slug', 'security.php');
            $wanted = sanitize_title((string) $post['haptic_sec_login_slug']);
            $slug = haptic_sec_sanitize_login_slug($wanted);
            if ($slug === $wanted) {
                update_option('haptic_sec_login_slug', $slug);
            } else {
                $errors = get_settings_errors('haptic_sec_login_slug');
                /* translators: %s: reason why the login URL was rejected */
                $notices[] = sprintf(__('Login URL not changed: %s', 'optimizr'), $errors ? end($errors)['message'] : __('invalid URL.', 'optimizr'));
            }
        }
    }

    if (!empty($modules['seo'])) {
        foreach (array_keys(haptic_onboarding_seo_toggles()) as $option) {
            update_option($option, $flag($option));
        }
        if (isset($post['blog_public'])) {
            update_option('blog_public', $flag('blog_public'));
        }

        foreach (['blogname', 'blogdescription', 'haptic_seo_title_pattern', 'haptic_seo_desc_pattern'] as $option) {
            $value = $text($option);
            if ($value !== null && ($value !== '' || $option === 'blogdescription')) {
                update_option($option, $value);
            }
        }
    }

    if (!empty($modules['cookies'])) {
        update_option('haptic_option_enable_tarteaucitron', $flag('haptic_option_enable_tarteaucitron'));
        $style = isset($post['haptic_option_tarteaucitron_banner_style']) ? (string) $post['haptic_option_tarteaucitron_banner_style'] : 'middle';
        update_option('haptic_option_tarteaucitron_banner_style', in_array($style, ['top', 'middle', 'bottom'], true) ? $style : 'middle');

        foreach (haptic_onboarding_cookie_fields() as $field => list($service, $key)) {
            $id = $text($field);
            if ($id === null) {
                continue;
            }
            $value = get_option('haptic_option_service_' . $service, null);
            // Service jamais configuré et laissé vide : rien à enregistrer
            if ($id === '' && $value === null) {
                continue;
            }
            $value = (array) $value;
            $value[$key] = $id;
            update_option('haptic_option_service_' . $service, $value);
        }
    }

    // Étapes d'une extension (Optimizr Pro) : leurs réglages, et les messages à afficher sur l'écran final
    $notices = (array) apply_filters('haptic_onboarding_save', $notices, $post, $modules);

    update_option('haptic_onboarding_done', time());
    if ($notices) {
        set_transient('haptic_onboarding_notices_' . get_current_user_id(), $notices, 10 * MINUTE_IN_SECONDS);
    }

    wp_safe_redirect(haptic_onboarding_url(['step' => 'done']));
    exit;
}

// « Quitter l'assistant » : il ne s'ouvrira plus de lui-même
add_action('admin_post_haptic_onboarding_skip', function () {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'optimizr'), 403);
    }
    check_admin_referer('haptic_onboarding_skip');

    if (!get_option('haptic_onboarding_done')) {
        update_option('haptic_onboarding_done', time());
    }

    wp_safe_redirect(admin_url('admin.php?page=haptic-options'));
    exit;
});

/* ---------- Affichage ---------- */

/** Interrupteur de l'assistant. Le champ caché envoie 0 quand la case est décochée. */
function haptic_onboarding_switch($name, $checked, $title, $desc = '', $tag = '')
{
    $id = 'ob-' . sanitize_html_class($name);
    ?>
    <label class="ob-option" for="<?php echo esc_attr($id); ?>">
        <span class="ob-option__text">
            <span class="ob-option__title"><?php echo esc_html($title); ?><?php if ($tag) : ?> <span class="ob-tag"><?php echo esc_html($tag); ?></span><?php endif; ?></span>
            <?php if ($desc) : ?><span class="ob-option__desc"><?php echo esc_html($desc); ?></span><?php endif; ?>
        </span>
        <input type="hidden" name="<?php echo esc_attr($name); ?>" value="0">
        <input type="checkbox" role="switch" class="ob-switch" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="1" data-summary="<?php echo esc_attr($title); ?>" <?php checked((bool) $checked); ?>>
    </label>
    <?php
}

function haptic_onboarding_render()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'optimizr'), 403);
    }

    $css = HAPTIC_OPTIMIZR_PATH . 'css/optimizr-onboarding.css';
    $js  = HAPTIC_OPTIMIZR_PATH . 'js/optimizr-onboarding.js';
    wp_register_style('optimizr-onboarding', HAPTIC_OPTIMIZR_URL . 'css/optimizr-onboarding.css', [], filemtime($css));
    wp_register_script('optimizr-onboarding', HAPTIC_OPTIMIZR_URL . 'js/optimizr-onboarding.js', ['wp-i18n'], filemtime($js), true);
    wp_set_script_translations('optimizr-onboarding', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');

    $done = isset($_GET['step']) && $_GET['step'] === 'done';
    $steps = haptic_onboarding_steps();

    nocache_headers();
    header('Content-Type: text/html; charset=' . get_option('blog_charset'));
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
    <meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $done ? esc_html__('Setup complete', 'optimizr') : esc_html__('Setup wizard', 'optimizr'); ?> - Optimizr</title>
    <?php wp_print_inline_script_tag("document.documentElement.className = 'js';"); ?>
    <?php wp_print_styles('optimizr-onboarding'); ?>
</head>
<body class="ob-body">
    <?php haptic_onboarding_svg_defs(); ?>
    <div class="ob<?php echo $done ? ' ob--done' : ''; ?>">
        <aside class="ob-visual" aria-hidden="true">
            <div class="ob-brand">
                <span class="ob-brand__logo">O</span>
                <span class="ob-brand__name">Optimizr</span>
            </div>
            <div class="ob-stage">
                <?php if ($done) : ?>
                    <div class="ob-illu is-active"><?php haptic_onboarding_illustration('done'); ?></div>
                <?php else : ?>
                    <?php foreach (array_keys($steps) as $i => $slug) : ?>
                        <div class="ob-illu<?php echo $i === 0 ? ' is-active' : ''; ?>" data-illu="<?php echo esc_attr($slug); ?>"><?php haptic_onboarding_illustration($slug); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="ob-caption">
                <?php if ($done) : ?>
                    <p class="ob-caption__kicker"><?php esc_html_e('Here we go', 'optimizr'); ?></p>
                    <p class="ob-caption__text"><?php esc_html_e('Your site is protected, visible and ready to be optimized.', 'optimizr'); ?></p>
                <?php else : ?>
                    <?php foreach ($steps as $slug => $step) : ?>
                        <div class="ob-caption__item<?php echo $slug === 'welcome' ? ' is-active' : ''; ?>" data-caption="<?php echo esc_attr($slug); ?>" data-pillar="<?php echo esc_attr($step['pillar']); ?>">
                            <p class="ob-caption__kicker"><?php echo esc_html($step['kicker']); ?></p>
                            <p class="ob-caption__text"><?php echo esc_html($step['tagline']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <ul class="ob-pillars" data-pillar="all">
                <li class="ob-pillars__item" data-pillar-item="security"><?php echo wp_kses(haptic_icon('shield', 16), haptic_kses_ui()); ?><?php esc_html_e('Security', 'optimizr'); ?></li>
                <li class="ob-pillars__item" data-pillar-item="performance"><?php echo wp_kses(haptic_onboarding_icon('gauge'), haptic_kses_ui()); ?><?php esc_html_e('Performance', 'optimizr'); ?></li>
                <li class="ob-pillars__item" data-pillar-item="optimization"><?php echo wp_kses(haptic_icon('sparkles', 16), haptic_kses_ui()); ?><?php esc_html_e('Optimization', 'optimizr'); ?></li>
            </ul>
        </aside>

        <main class="ob-panel">
            <?php $done ? haptic_onboarding_render_done() : haptic_onboarding_render_form($steps); ?>
        </main>
    </div>
    <?php wp_print_scripts('optimizr-onboarding'); ?>
    <?php do_action('haptic_onboarding_print_scripts'); ?>
</body>
</html>
    <?php
    exit;
}

function haptic_onboarding_render_form($steps)
{
    $modules = haptic_modules();
    $skip_url = wp_nonce_url(admin_url('admin-post.php?action=haptic_onboarding_skip'), 'haptic_onboarding_skip');
    ?>
    <div class="ob-top">
        <div class="ob-progress" aria-hidden="true"><span class="ob-progress__bar" data-ob-progress></span></div>
        <p class="ob-top__count" data-ob-count aria-live="polite"></p>
        <a class="ob-top__skip" href="<?php echo esc_url($skip_url); ?>"><?php esc_html_e('Exit the wizard', 'optimizr'); ?></a>
    </div>

    <ol class="ob-stepper" data-ob-stepper>
        <?php foreach ($steps as $slug => $step) : ?>
            <li class="ob-stepper__item" data-stepper="<?php echo esc_attr($slug); ?>" data-module="<?php echo esc_attr($step['module']); ?>"><span class="ob-stepper__dot" aria-hidden="true"></span><span class="ob-stepper__label"><?php echo esc_html($step['label']); ?></span></li>
        <?php endforeach; ?>
    </ol>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ob-form" data-ob-form novalidate>
        <input type="hidden" name="action" value="haptic_onboarding_save">
        <?php wp_nonce_field('haptic_onboarding'); ?>

        <?php foreach (array_keys($steps) as $i => $slug) : ?>
            <section class="ob-step<?php echo $i === 0 ? ' is-active' : ''; ?>" data-step="<?php echo esc_attr($slug); ?>" data-module="<?php echo esc_attr($steps[$slug]['module']); ?>" aria-labelledby="ob-title-<?php echo esc_attr($slug); ?>">
                <?php
                call_user_func($steps[$slug]['render'] ?? 'haptic_onboarding_step_' . $slug, $modules);
                // Champs ou remarques ajoutés par une extension à la fin de l'étape
                do_action('haptic_onboarding_step_after', $slug, $modules);
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

function haptic_onboarding_step_head($slug, $eyebrow, $title, $intro)
{
    ?>
    <p class="ob-step__eyebrow"><?php echo esc_html($eyebrow); ?></p>
    <h1 class="ob-step__title" id="ob-title-<?php echo esc_attr($slug); ?>" tabindex="-1"><?php echo esc_html($title); ?></h1>
    <p class="ob-step__intro"><?php echo esc_html($intro); ?></p>
    <?php
}

function haptic_onboarding_step_welcome($modules)
{
    $user = wp_get_current_user();
    $name = $user->first_name ?: $user->display_name;
    haptic_onboarding_step_head(
        'welcome',
        /* translators: %s: user's first name or display name */
        $name ? sprintf(__('Hello %s', 'optimizr'), $name) : __('Welcome', 'optimizr'),
        __('Let\'s set up Optimizr in a few minutes', 'optimizr'),
        /* translators: %s: site name */
        sprintf(__('A few questions to turn on the essentials on %s. Settings are applied at the last step and can be changed later.', 'optimizr'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES))
    );
    ?>
    <div class="ob-pillar-cards">
        <div class="ob-pillar-card ob-pillar-card--security">
            <span class="ob-pillar-card__icon"><?php echo wp_kses(haptic_icon('shield', 22), haptic_kses_ui()); ?></span>
            <h2 class="ob-pillar-card__title"><?php esc_html_e('Security', 'optimizr'); ?></h2>
            <p class="ob-pillar-card__text"><?php esc_html_e('Protected login, reduced attack surface and GDPR consent.', 'optimizr'); ?></p>
        </div>
        <div class="ob-pillar-card ob-pillar-card--performance">
            <span class="ob-pillar-card__icon"><?php echo wp_kses(haptic_onboarding_icon('gauge', 22), haptic_kses_ui()); ?></span>
            <h2 class="ob-pillar-card__title"><?php esc_html_e('Performance', 'optimizr'); ?></h2>
            <p class="ob-pillar-card__text"><?php esc_html_e('Meta tags, redirects and indexing for a site Google understands quickly.', 'optimizr'); ?></p>
        </div>
        <div class="ob-pillar-card ob-pillar-card--optimization">
            <span class="ob-pillar-card__icon"><?php echo wp_kses(haptic_icon('sparkles', 22), haptic_kses_ui()); ?></span>
            <h2 class="ob-pillar-card__title"><?php esc_html_e('Optimization', 'optimizr'); ?></h2>
            <p class="ob-pillar-card__text"><?php esc_html_e('Content types, featured images and modules enabled as you need them.', 'optimizr'); ?></p>
        </div>
    </div>
    <p class="ob-note"><?php echo wp_kses(haptic_icon('info', 16), haptic_kses_ui()); ?><?php esc_html_e('About 3 minutes. You can exit the wizard at any time and reopen it from the Optimizr › Wizard menu.', 'optimizr'); ?></p>
    <?php
}

function haptic_onboarding_step_modules($modules)
{
    haptic_onboarding_step_head('modules', __('Modules', 'optimizr'), __('Choose your modules', 'optimizr'), __('Turn on what your site needs. The next steps adapt to your choice.', 'optimizr'));

    $grouped = [];
    foreach (haptic_onboarding_module_groups() as $group) {
        $grouped = array_merge($grouped, $group['modules']);
    }
    $groups = haptic_onboarding_module_groups();
    // Modules ajoutés par le filtre haptic_modules : dans le dernier groupe
    $groups['optimization']['modules'] = array_merge($groups['optimization']['modules'], array_diff(array_keys($modules), $grouped));

    foreach ($groups as $key => $group) :
        $slugs = array_values(array_filter($group['modules'], function ($slug) use ($modules) {
            return isset($modules[$slug]);
        }));
        if (!$slugs) {
            continue;
        }
        ?>
        <fieldset class="ob-group ob-group--<?php echo esc_attr($key); ?>">
            <legend class="ob-group__title"><?php echo esc_html($group['label']); ?></legend>
            <div class="ob-modules">
                <?php foreach ($slugs as $slug) :
                    $module = $modules[$slug];
                    ?>
                    <label class="ob-module" for="ob-module-<?php echo esc_attr($slug); ?>">
                        <span class="ob-module__icon"><?php echo wp_kses(haptic_icon($module['icon'], 20), haptic_kses_ui()); ?></span>
                        <span class="ob-module__text">
                            <span class="ob-module__title"><?php echo esc_html($module['label']); ?></span>
                            <span class="ob-module__desc"><?php echo esc_html($module['desc']); ?></span>
                        </span>
                        <input type="checkbox" role="switch" class="ob-switch" id="ob-module-<?php echo esc_attr($slug); ?>" name="haptic_modules[<?php echo esc_attr($slug); ?>]" value="1" data-module-toggle="<?php echo esc_attr($slug); ?>" data-summary="<?php echo esc_attr($module['label']); ?>" <?php checked(haptic_is_module_active($slug)); ?>>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php
    endforeach;
}

function haptic_onboarding_step_security($modules)
{
    haptic_onboarding_step_head('security', __('Security', 'optimizr'), __('Lock down access', 'optimizr'), __('A login URL unique to the site stops most bots. Recommended protections are preselected and do not affect visitors.', 'optimizr'));

    $locked = (defined('OPTIMIZR_DISABLE_CUSTOM_LOGIN') && OPTIMIZR_DISABLE_CUSTOM_LOGIN) || is_multisite();
    $prefix = get_option('permalink_structure') ? home_url('/') : home_url('/') . '?';
    // Même adresse que l'onglet Sécurité (proxys de confiance pris en compte quand le module est actif)
    $ip = function_exists('haptic_sec_client_ip') ? haptic_sec_client_ip() : sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
    $ip = $ip !== '0.0.0.0' && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    ?>
    <div class="ob-fields">
        <?php if (!$locked) : ?>
            <div class="ob-field ob-field--wide">
                <span class="ob-field__label">
                    <label for="ob-login-slug"><?php esc_html_e('Login URL', 'optimizr'); ?></label> <span class="ob-tag"><?php esc_html_e('Recommended', 'optimizr'); ?></span>
                    <button type="button" class="ob-field__link" data-ob-suggest-slug><?php esc_html_e('Suggest a URL', 'optimizr'); ?></button>
                </span>
                <div class="ob-prefixed">
                    <span class="ob-prefixed__prefix" title="<?php echo esc_attr($prefix); ?>"><?php echo esc_html(preg_replace('#^https?://#', '', $prefix)); ?></span>
                    <input type="text" id="ob-login-slug" name="haptic_sec_login_slug" value="<?php echo esc_attr(get_option('haptic_sec_login_slug', '')); ?>" placeholder="<?php esc_attr_e('e.g. admin-area', 'optimizr'); ?>" pattern="[A-Za-z0-9\-]{4,}" title="<?php esc_attr_e('At least 4 characters: letters, numbers and hyphens.', 'optimizr'); ?>" spellcheck="false" autocomplete="off" data-summary-value="<?php esc_attr_e('Login URL', 'optimizr'); ?>" data-summary-prefix="/">
                </div>
                <span class="ob-field__help"><?php
                    /* translators: %s: administrator email address */
                    echo esc_html(sprintf(__('wp-login.php and /wp-admin/ will show a not found page to logged-out visitors. The new URL is emailed to %s. Leave empty to keep wp-login.php.', 'optimizr'), get_option('admin_email')));
                ?></span>
            </div>
        <?php endif; ?>
        <label class="ob-field">
            <span class="ob-field__label"><?php esc_html_e('Lock out after (failures)', 'optimizr'); ?></span>
            <input type="number" name="haptic_sec_login_max" min="2" max="20" value="<?php echo esc_attr(get_option('haptic_sec_login_max', 5)); ?>">
        </label>
        <label class="ob-field">
            <span class="ob-field__label"><?php esc_html_e('Lockout duration (minutes)', 'optimizr'); ?></span>
            <input type="number" name="haptic_sec_login_lockout" min="1" max="1440" value="<?php echo esc_attr(get_option('haptic_sec_login_lockout', 15)); ?>">
        </label>
        <div class="ob-field ob-field--wide">
            <span class="ob-field__label">
                <label for="ob-login-whitelist"><?php esc_html_e('IP addresses never locked out', 'optimizr'); ?> <span class="ob-field__opt"><?php esc_html_e('one per line', 'optimizr'); ?></span></label>
                <?php if ($ip) : ?><button type="button" class="ob-field__link" data-ob-add-ip="<?php echo esc_attr($ip); ?>"><?php
                    /* translators: %s: current visitor IP address */
                    echo esc_html(sprintf(__('Add my address (%s)', 'optimizr'), $ip));
                ?></button><?php endif; ?>
            </span>
            <textarea id="ob-login-whitelist" name="haptic_sec_login_whitelist" rows="2" spellcheck="false" data-summary-value="<?php esc_attr_e('IPs never locked out', 'optimizr'); ?>"><?php echo esc_textarea(get_option('haptic_sec_login_whitelist', '')); ?></textarea>
            <span class="ob-field__help"><?php esc_html_e('Add yours if it is static (office, fiber) so you never lock yourself out.', 'optimizr'); ?></span>
        </div>
    </div>
    <h2 class="ob-subtitle"><?php esc_html_e('Protections', 'optimizr'); ?></h2>
    <div class="ob-options">
        <?php foreach (haptic_onboarding_security_toggles() as $key => $toggle) {
            haptic_onboarding_switch('haptic_sec_' . $key, get_option('haptic_sec_' . $key, $toggle[2] ? 1 : 0), $toggle[0], $toggle[1], $toggle[2] ? __('Recommended', 'optimizr') : '');
        } ?>
    </div>
    <?php
}

function haptic_onboarding_step_seo($modules)
{
    haptic_onboarding_step_head('seo', _x('SEO', 'onboarding step', 'optimizr'), __('Get visible and indexed quickly', 'optimizr'), __('The site name and the patterns below are used as title and description in Google for any content that has none.', 'optimizr'));
    ?>
    <div class="ob-fields">
        <label class="ob-field">
            <span class="ob-field__label"><?php esc_html_e('Site name', 'optimizr'); ?></span>
            <input type="text" name="blogname" value="<?php echo esc_attr(get_option('blogname')); ?>" required data-summary-value="<?php esc_attr_e('Site name', 'optimizr'); ?>">
        </label>
        <label class="ob-field">
            <span class="ob-field__label"><?php esc_html_e('Tagline', 'optimizr'); ?> <span class="ob-field__opt"><?php esc_html_e('optional', 'optimizr'); ?></span></span>
            <input type="text" name="blogdescription" value="<?php echo esc_attr(get_option('blogdescription')); ?>" placeholder="<?php esc_attr_e('Your business in a few words', 'optimizr'); ?>" data-summary-value="<?php esc_attr_e('Tagline', 'optimizr'); ?>">
        </label>
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label"><?php esc_html_e('Page title in Google', 'optimizr'); ?></span>
            <input type="text" name="haptic_seo_title_pattern" value="<?php echo esc_attr(get_option('haptic_seo_title_pattern', '{titre_page} - {nom_site}')); ?>" data-ob-pattern data-summary-value="<?php esc_attr_e('Title', 'optimizr'); ?>">
        </label>
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label"><?php esc_html_e('Default description', 'optimizr'); ?></span>
            <input type="text" name="haptic_seo_desc_pattern" value="<?php echo esc_attr(get_option('haptic_seo_desc_pattern', '{resume} - {nom_site}')); ?>" data-ob-pattern data-summary-value="<?php esc_attr_e('Description', 'optimizr'); ?>">
        </label>
        <div class="ob-field ob-field--wide">
            <span class="ob-field__help"><?php esc_html_e('Insert a variable into the last edited pattern:', 'optimizr'); ?></span>
            <div class="ob-vars">
                <?php foreach (haptic_onboarding_seo_variables() as $var => $label) : ?>
                    <button type="button" class="ob-var" data-ob-var="<?php echo esc_attr($var); ?>"><?php echo esc_html($label); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php do_action('haptic_onboarding_seo_fields'); ?>
    </div>

    <h2 class="ob-subtitle"><?php esc_html_e('Indexing', 'optimizr'); ?></h2>
    <div class="ob-options">
        <?php
        if (!get_option('blog_public')) {
            haptic_onboarding_switch('blog_public', 0, __('Allow search engines to index the site', 'optimizr'), __('The site currently asks search engines not to index it (Settings › Reading). Leave off on a site still in preparation.', 'optimizr'), __('Warning', 'optimizr'));
        }
        foreach (haptic_onboarding_seo_toggles() as $option => $toggle) {
            haptic_onboarding_switch($option, get_option($option, $toggle[2]), $toggle[0], $toggle[1]);
        }
        ?>
    </div>
    <?php
}

function haptic_onboarding_step_cookies($modules)
{
    haptic_onboarding_step_head('cookies', __('Cookies & GDPR', 'optimizr'), __('Respect your visitors\' privacy', 'optimizr'), __('Enter the site\'s analytics tools: the Tarteaucitron banner only loads them once the visitor agrees.', 'optimizr'));
    $style = get_option('haptic_option_tarteaucitron_banner_style', 'middle');
    ?>
    <div class="ob-options">
        <?php haptic_onboarding_switch('haptic_option_enable_tarteaucitron', get_option('haptic_option_enable_tarteaucitron', 1), __('Show the consent banner', 'optimizr'), __('Required as soon as an analytics or advertising service is used.', 'optimizr'), __('Recommended', 'optimizr')); ?>
    </div>

    <div class="ob-fields">
        <?php foreach (haptic_onboarding_cookie_fields() as $field => list($service, $key, $label, $example)) :
            $saved = (array) get_option('haptic_option_service_' . $service, []);
            ?>
            <label class="ob-field">
                <span class="ob-field__label"><?php echo esc_html($label); ?> <span class="ob-field__opt"><?php esc_html_e('optional', 'optimizr'); ?></span></span>
                <input type="text" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($saved[$key] ?? ''); ?>" placeholder="<?php echo esc_attr($example); ?>" spellcheck="false" autocomplete="off" data-summary-value="<?php echo esc_attr($label); ?>">
            </label>
        <?php endforeach; ?>
    </div>

    <fieldset class="ob-fieldset">
        <legend class="ob-fieldset__title"><?php esc_html_e('Banner position', 'optimizr'); ?></legend>
        <div class="ob-positions">
            <?php foreach (['top' => __('Top', 'optimizr'), 'middle' => __('Center', 'optimizr'), 'bottom' => __('Bottom', 'optimizr')] as $value => $label) : ?>
                <label class="ob-position ob-position--<?php echo esc_attr($value); ?>">
                    <input type="radio" name="haptic_option_tarteaucitron_banner_style" value="<?php echo esc_attr($value); ?>" data-summary="<?php
                        /* translators: %s: banner position (top, center, bottom), lowercased */
                        echo esc_attr(sprintf(__('Banner: %s', 'optimizr'), strtolower($label)));
                    ?>" <?php checked($style, $value); ?>>
                    <span class="ob-position__preview" aria-hidden="true"><span class="ob-position__banner"></span></span>
                    <span class="ob-position__label"><?php echo esc_html($label); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <?php
}

function haptic_onboarding_step_summary($modules)
{
    haptic_onboarding_step_head('summary', __('Summary', 'optimizr'), __('Everything is ready to apply', 'optimizr'), __('Here is what will be saved. Go back to change a choice.', 'optimizr'));
    ?>
    <div class="ob-summary" data-ob-summary></div>
    <noscript><p class="ob-note"><?php esc_html_e('Check the choices above before applying the settings.', 'optimizr'); ?></p></noscript>
    <?php
}

function haptic_onboarding_render_done()
{
    $tabs = haptic_admin_tabs();
    $next = [];
    foreach (haptic_modules() as $slug => $module) {
        if (haptic_is_module_active($slug) && isset($tabs[$module['tab']]) && !isset($next[$module['tab']])) {
            $next[$module['tab']] = $module;
        }
    }

    $notices_key = 'haptic_onboarding_notices_' . get_current_user_id();
    $notices = (array) get_transient($notices_key);
    delete_transient($notices_key);

    $login_url = haptic_is_module_active('security') && function_exists('haptic_sec_login_slug') && haptic_sec_login_slug() ? haptic_sec_login_url() : '';
    ?>
    <div class="ob-done">
        <p class="ob-step__eyebrow"><?php esc_html_e('Setup complete', 'optimizr'); ?></p>
        <h1 class="ob-step__title" tabindex="-1"><?php esc_html_e('Optimizr is watching over your site', 'optimizr'); ?></h1>
        <p class="ob-step__intro"><?php esc_html_e('Settings are saved and active. Fine-tune each module from its tab whenever you like.', 'optimizr'); ?></p>

        <?php foreach (array_filter($notices) as $notice) : ?>
            <p class="ob-alert" role="alert"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><?php echo esc_html($notice); ?></p>
        <?php endforeach; ?>

        <?php if ($login_url) : ?>
            <div class="ob-login-card">
                <span class="ob-login-card__icon"><?php echo wp_kses(haptic_icon('lock', 20), haptic_kses_ui()); ?></span>
                <div>
                    <p class="ob-login-card__title"><?php esc_html_e('Your login URL', 'optimizr'); ?></p>
                    <a class="ob-login-card__url" href="<?php echo esc_url($login_url); ?>"><?php echo esc_html($login_url); ?></a>
                    <p class="ob-login-card__help"><?php
                        /* translators: %s: administrator email address */
                        echo esc_html(sprintf(__('Bookmark it: wp-login.php is no longer accessible. It was also sent to %s.', 'optimizr'), get_option('admin_email')));
                    ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($next) : ?>
            <h2 class="ob-fieldset__title"><?php esc_html_e('Going further', 'optimizr'); ?></h2>
            <ul class="ob-next">
                <?php foreach ($next as $tab => $module) : ?>
                    <li>
                        <a class="ob-next__item" href="<?php echo esc_url(haptic_admin_tab_url($tab)); ?>">
                            <span class="ob-module__icon"><?php echo wp_kses(haptic_icon($module['icon'], 18), haptic_kses_ui()); ?></span>
                            <span class="ob-next__label"><?php echo esc_html($tabs[$tab]['label']); ?></span>
                            <?php echo wp_kses(haptic_icon('chevron-right', 16), haptic_kses_ui()); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="ob-nav ob-nav--done">
            <a class="ob-btn ob-btn--ghost" href="<?php echo esc_url(haptic_onboarding_url()); ?>"><?php esc_html_e('Restart the wizard', 'optimizr'); ?></a>
            <a class="ob-btn ob-btn--primary" href="<?php echo esc_url(admin_url('admin.php?page=haptic-options')); ?>"><?php esc_html_e('Open the dashboard', 'optimizr'); ?></a>
        </div>
    </div>
    <?php
}

/* ---------- Illustrations (SVG inline, sans ressource externe) ---------- */

/** Icône « jauge » (absente de haptic_icon()). */
function haptic_onboarding_icon($name, $size = 16)
{
    $paths = [
        'gauge'   => '<path d="M12 14l4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
        'sliders' => '<path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/><path d="M1 14h6"/><path d="M9 8h6"/><path d="M17 16h6"/>',
        'bolt'    => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
    ];
    return sprintf(
        '<svg class="optimizr-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
        (int) $size,
        $paths[$name]
    );
}

/** Dégradés et filtres partagés par toutes les illustrations. */
function haptic_onboarding_svg_defs()
{
    ?>
    <svg class="ob-defs" width="0" height="0" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="ob-g-accent" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8aa4ff"/><stop offset="1" stop-color="#3858e9"/></linearGradient>
            <linearGradient id="ob-g-cyan" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8af0ff"/><stop offset="1" stop-color="#19b5d6"/></linearGradient>
            <linearGradient id="ob-g-green" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7ef0c4"/><stop offset="1" stop-color="#0ea371"/></linearGradient>
            <linearGradient id="ob-g-violet" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#d2c4ff"/><stop offset="1" stop-color="#7c5cf0"/></linearGradient>
            <linearGradient id="ob-g-glass" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff" stop-opacity=".16"/><stop offset="1" stop-color="#ffffff" stop-opacity=".04"/></linearGradient>
            <linearGradient id="ob-g-arc" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#19b5d6"/><stop offset=".55" stop-color="#5b7bff"/><stop offset="1" stop-color="#2fd39a"/></linearGradient>
            <radialGradient id="ob-g-halo"><stop offset="0" stop-color="#4a6bff" stop-opacity=".55"/><stop offset="1" stop-color="#4a6bff" stop-opacity="0"/></radialGradient>
            <radialGradient id="ob-g-halo-violet"><stop offset="0" stop-color="#8b5cf6" stop-opacity=".5"/><stop offset="1" stop-color="#8b5cf6" stop-opacity="0"/></radialGradient>
            <radialGradient id="ob-g-halo-green"><stop offset="0" stop-color="#10b981" stop-opacity=".5"/><stop offset="1" stop-color="#10b981" stop-opacity="0"/></radialGradient>
            <filter id="ob-f-glow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
            <clipPath id="ob-clip-shield"><path d="M240 62 322 94v78c0 62-40 98-82 118-42-20-82-56-82-118V94z"/></clipPath>
        </defs>
    </svg>
    <?php
}

/** Pastille ronde avec une icône 24×24 (chemins de haptic_icon() ou haptic_onboarding_icon()). */
function haptic_onboarding_badge($x, $y, $icon_svg, $gradient, $class = '')
{
    // Couleur unie pour l'icône : un dégradé ne s'applique pas à un trait droit (boîte englobante sans largeur)
    $colors = ['ob-g-cyan' => '#8af0ff', 'ob-g-green' => '#7ef0c4', 'ob-g-violet' => '#d2c4ff', 'ob-g-accent' => '#a9baff'];
    // L'icône est extraite de son <svg> pour être replacée dans l'illustration
    $inner = preg_replace('#^<svg[^>]*>|</svg>$#', '', $icon_svg);
    return sprintf(
        '<g transform="translate(%1$d %2$d)"><g class="%5$s"><circle r="31" fill="#101a45" stroke="url(#%3$s)" stroke-width="2"/><circle r="31" fill="url(#%3$s)" opacity=".14"/><g transform="translate(-14 -14) scale(1.1667)" fill="none" stroke="%6$s" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">%4$s</g></g></g>',
        $x,
        $y,
        $gradient,
        $inner,
        esc_attr($class),
        $colors[$gradient] ?? '#ffffff'
    );
}

function haptic_onboarding_illustration($name)
{

    switch ($name) {
        case 'welcome':
            ?>
            <svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <g class="ob-a-spin"><circle cx="240" cy="180" r="158" fill="none" stroke="#fff" stroke-opacity=".14" stroke-dasharray="2 9"/></g>
                <circle cx="240" cy="180" r="118" fill="none" stroke="#fff" stroke-opacity=".07"/>
                <g class="ob-a-float">
                    <rect x="130" y="100" width="220" height="156" rx="14" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".22"/>
                    <path d="M130 126h220" stroke="#fff" stroke-opacity=".14"/>
                    <circle cx="146" cy="113" r="3.5" fill="#ff8a8a" opacity=".8"/><circle cx="158" cy="113" r="3.5" fill="#ffd27a" opacity=".8"/><circle cx="170" cy="113" r="3.5" fill="#7ef0c4" opacity=".8"/>
                    <rect x="186" y="107" width="146" height="12" rx="6" fill="#fff" fill-opacity=".08"/>
                    <path d="M194 113h3" stroke="#7ef0c4" stroke-width="3" stroke-linecap="round"/>
                    <rect x="150" y="142" width="92" height="10" rx="5" fill="#fff" fill-opacity=".55"/>
                    <rect x="150" y="160" width="136" height="6" rx="3" fill="#fff" fill-opacity=".18"/>
                    <rect x="150" y="172" width="116" height="6" rx="3" fill="#fff" fill-opacity=".18"/>
                    <polyline points="150,236 172,222 194,228 216,206 238,212 258,194" fill="none" stroke="url(#ob-g-cyan)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" pathLength="100" class="ob-a-draw"/>
                    <g class="ob-bars">
                        <rect x="282" y="206" width="12" height="32" rx="3" fill="url(#ob-g-accent)" class="ob-a-grow"/>
                        <rect x="300" y="192" width="12" height="46" rx="3" fill="url(#ob-g-accent)" class="ob-a-grow" style="animation-delay:.12s"/>
                        <rect x="318" y="176" width="12" height="62" rx="3" fill="url(#ob-g-green)" class="ob-a-grow" style="animation-delay:.24s"/>
                    </g>
                </g>
                <?php
                echo wp_kses(haptic_onboarding_badge(108, 96, haptic_icon('shield', 24), 'ob-g-cyan', 'ob-a-float ob-delay-1'), haptic_kses_ui());
                echo wp_kses(haptic_onboarding_badge(382, 120, haptic_onboarding_icon('gauge', 24), 'ob-g-green', 'ob-a-float ob-delay-2'), haptic_kses_ui());
                echo wp_kses(haptic_onboarding_badge(366, 282, haptic_onboarding_icon('sliders', 24), 'ob-g-violet', 'ob-a-float ob-delay-3'), haptic_kses_ui());
                echo wp_kses(haptic_onboarding_badge(104, 268, haptic_icon('sparkles', 24), 'ob-g-accent', 'ob-a-float'), haptic_kses_ui());
                ?>
                <circle cx="70" cy="180" r="2.5" fill="#8af0ff" class="ob-a-twinkle"/>
                <circle cx="420" cy="208" r="2" fill="#d2c4ff" class="ob-a-twinkle ob-delay-2"/>
                <circle cx="300" cy="44" r="2.5" fill="#7ef0c4" class="ob-a-twinkle ob-delay-1"/>
                <circle cx="190" cy="320" r="2" fill="#8aa4ff" class="ob-a-twinkle ob-delay-3"/>
            </svg>
            <?php
            break;

        case 'modules':
            $icons = ['shield', 'search', 'sparkles', 'redirect', 'lock', 'pin', 'file', 'layers', 'image'];
            $lit = [0, 1, 2, 4];
            echo '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <path d="M240 180 L142 82 M240 180 L338 82 M240 180 L142 278 M240 180 L338 278" stroke="#8af0ff" stroke-opacity=".35" stroke-dasharray="4 8" class="ob-a-flow"/>
                <?php foreach ($icons as $i => $icon) :
                    $x = 126 + ($i % 3) * 82;
                    $y = 66 + intdiv($i, 3) * 82;
                    $on = in_array($i, $lit, true);
                    $inner = preg_replace('#^<svg[^>]*>|</svg>$#', '', haptic_icon($icon, 24));
                    ?>
                    <g transform="translate(<?php echo esc_attr($x); ?> <?php echo esc_attr($y); ?>)">
                        <g class="ob-a-tile" style="animation-delay:<?php echo esc_attr(0.08 * $i); ?>s">
                            <rect width="64" height="64" rx="14" fill="<?php echo $on ? 'url(#ob-g-accent)' : 'url(#ob-g-glass)'; ?>" stroke="#fff" stroke-opacity="<?php echo $on ? '.35' : '.16'; ?>"<?php echo $on ? ' filter="url(#ob-f-glow)"' : ''; ?>/>
                            <g transform="translate(18 18)" fill="none" stroke="#fff" stroke-opacity="<?php echo esc_attr($on ? '1' : '.55'); ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo wp_kses($inner, haptic_kses_ui()); ?></g>
                            <circle cx="54" cy="10" r="4" fill="<?php echo $on ? '#7ef0c4' : '#fff'; ?>" fill-opacity="<?php echo $on ? '1' : '.18'; ?>"/>
                        </g>
                    </g>
                <?php endforeach; ?>
            <?php
            echo '</svg>';
            break;

        case 'security':
            echo '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <circle cx="240" cy="176" r="96" fill="none" stroke="#8af0ff" stroke-width="1.5" class="ob-a-ping"/>
                <circle cx="240" cy="176" r="96" fill="none" stroke="#8af0ff" stroke-width="1.5" class="ob-a-ping ob-delay-2"/>
                <g class="ob-a-float">
                    <path d="M240 62 322 94v78c0 62-40 98-82 118-42-20-82-56-82-118V94z" fill="url(#ob-g-accent)"/>
                    <path d="M240 78 308 104v68c0 52-33 82-68 100-35-18-68-48-68-100v-68z" fill="none" stroke="#fff" stroke-opacity=".35"/>
                    <g clip-path="url(#ob-clip-shield)">
                        <rect x="150" y="62" width="180" height="3" fill="#8af0ff" filter="url(#ob-f-glow)" class="ob-a-scan"/>
                    </g>
                    <path d="M220 168v-14a20 20 0 0 1 40 0v14" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round"/>
                    <rect x="208" y="166" width="64" height="52" rx="10" fill="#fff"/>
                    <circle cx="240" cy="187" r="6" fill="#1d35b4"/>
                    <rect x="237" y="190" width="6" height="14" rx="3" fill="#1d35b4"/>
                    <g transform="translate(310 252)"><g class="ob-a-pop ob-delay-1">
                        <circle r="22" fill="url(#ob-g-green)"/>
                        <path d="M-9 0l6 6 12-12" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    </g></g>
                </g>
                <g class="ob-a-float ob-delay-2">
                    <rect x="22" y="100" width="128" height="40" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <circle cx="44" cy="120" r="10" fill="#7ef0c4" fill-opacity=".2"/><path d="M39 120l4 4 7-7" fill="none" stroke="#7ef0c4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <text x="62" y="124" class="ob-svg-text"><?php esc_html_e('HTTPS on', 'optimizr'); ?></text>
                </g>
                <g class="ob-a-float ob-delay-3">
                    <rect x="336" y="196" width="128" height="40" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <circle cx="358" cy="216" r="10" fill="#ff8a8a" fill-opacity=".2"/><path d="M354 212l8 8m0-8-8 8" stroke="#ff8a8a" stroke-width="2.2" stroke-linecap="round"/>
                    <text x="376" y="220" class="ob-svg-text"><?php esc_html_e('Bot blocked', 'optimizr'); ?></text>
                </g>
            <?php
            echo '</svg>';
            break;

        case 'seo':
            echo '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
            ?>
                <circle cx="240" cy="190" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <path d="M110 220a130 130 0 0 1 260 0" fill="none" stroke="#fff" stroke-opacity=".1" stroke-width="18" stroke-linecap="round"/>
                <path d="M110 220a130 130 0 0 1 260 0" fill="none" stroke="url(#ob-g-arc)" stroke-width="18" stroke-linecap="round" pathLength="100" stroke-dasharray="100" class="ob-a-gauge"/>
                <?php for ($i = 0; $i <= 10; $i++) :
                    $a = deg2rad(180 + $i * 18);
                    printf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#fff" stroke-opacity="%s" stroke-width="2" stroke-linecap="round"/>',
                        (float) (240 + cos($a) * 100), (float) (220 + sin($a) * 100), (float) (240 + cos($a) * ($i % 5 ? 108 : 112)), (float) (220 + sin($a) * ($i % 5 ? 108 : 112)), $i % 5 ? '.25' : '.5');
                endfor; ?>
                <g transform="translate(240 220)">
                    <g class="ob-a-needle">
                        <path d="M-5 0 0-96 5 0z" fill="#fff"/>
                    </g>
                    <circle r="13" fill="#fff"/>
                    <circle r="5" fill="#3858e9"/>
                </g>
                <text x="240" y="276" text-anchor="middle" class="ob-svg-score">98</text>
                <text x="240" y="298" text-anchor="middle" class="ob-svg-text ob-svg-text--muted"><?php esc_html_e('SEO score', 'optimizr'); ?></text>
                <g class="ob-a-speed" stroke="#8af0ff" stroke-linecap="round" stroke-width="3">
                    <path d="M36 110h44" stroke-opacity=".7"/><path d="M20 128h52" stroke-opacity=".4"/><path d="M44 146h30" stroke-opacity=".55"/>
                </g>
                <g class="ob-a-float ob-delay-1">
                    <rect x="330" y="54" width="132" height="40" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <circle cx="352" cy="74" r="10" fill="#7ef0c4" fill-opacity=".2"/><path d="M347 74l4 4 7-7" fill="none" stroke="#7ef0c4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <text x="370" y="78" class="ob-svg-text">og:title</text>
                </g>
                <g class="ob-a-float ob-delay-3">
                    <rect x="22" y="250" width="124" height="40" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <g transform="translate(34 260)" fill="none" stroke="#ffd27a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2 3 12h7l-1 6 8-10h-7z"/></g>
                    <text x="58" y="274" class="ob-svg-text"><?php esc_html_e('Indexing', 'optimizr'); ?></text>
                </g>
            <?php
            echo '</svg>';
            break;

        case 'cookies':
            echo '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <g class="ob-a-float">
                    <rect x="120" y="70" width="240" height="220" rx="18" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".22"/>
                    <rect x="144" y="94" width="110" height="10" rx="5" fill="#fff" fill-opacity=".6"/>
                    <rect x="144" y="112" width="170" height="6" rx="3" fill="#fff" fill-opacity=".2"/>
                    <?php foreach ([0, 1, 2] as $i) :
                        $y = 140 + $i * 40;
                        ?>
                        <rect x="144" y="<?php echo esc_attr($y + 8); ?>" width="<?php echo esc_attr([96, 120, 80][$i]); ?>" height="7" rx="3.5" fill="#fff" fill-opacity=".35"/>
                        <g transform="translate(296 <?php echo esc_attr($y); ?>)">
                            <rect width="40" height="22" rx="11" class="ob-a-track" style="animation-delay:<?php echo esc_attr(0.5 + 0.45 * $i); ?>s"/>
                            <circle cx="29" cy="11" r="8" fill="#fff" class="ob-a-knob" style="animation-delay:<?php echo esc_attr(0.5 + 0.45 * $i); ?>s"/>
                        </g>
                    <?php endforeach; ?>
                    <rect x="144" y="256" width="92" height="18" rx="9" fill="none" stroke="#fff" stroke-opacity=".35"/>
                    <rect x="244" y="256" width="92" height="18" rx="9" fill="url(#ob-g-accent)"/>
                </g>
                <?php echo wp_kses(haptic_onboarding_badge(366, 82, haptic_icon('shield', 24), 'ob-g-green', 'ob-a-pop ob-delay-1'), haptic_kses_ui()); ?>
                <g transform="translate(104 268)"><g class="ob-a-float ob-delay-2">
                    <circle r="30" fill="#f2b56b"/>
                    <circle cx="22" cy="-18" r="10" fill="#0b1437"/><circle cx="28" cy="-4" r="7" fill="#0b1437"/>
                    <circle cx="-10" cy="-8" r="4" fill="#8a5a22"/><circle cx="6" cy="10" r="4.5" fill="#8a5a22"/><circle cx="-12" cy="14" r="3" fill="#8a5a22"/><circle cx="4" cy="-16" r="3" fill="#8a5a22"/>
                </g></g>
            <?php
            echo '</svg>';
            break;

        case 'summary':
            echo '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <g class="ob-a-spin"><circle cx="240" cy="180" r="150" fill="none" stroke="#fff" stroke-opacity=".14" stroke-dasharray="2 9"/></g>
                <g class="ob-a-float">
                    <rect x="150" y="70" width="180" height="220" rx="18" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".22"/>
                    <rect x="210" y="60" width="60" height="22" rx="8" fill="url(#ob-g-accent)"/>
                    <?php foreach ([0, 1, 2, 3] as $i) :
                        $y = 110 + $i * 42;
                        $grad = ['ob-g-cyan', 'ob-g-green', 'ob-g-accent', 'ob-g-violet'][$i];
                        ?>
                        <g transform="translate(176 <?php echo esc_attr($y); ?>)"><g class="ob-a-tile" style="animation-delay:<?php echo esc_attr(0.15 * $i); ?>s">
                            <circle cx="11" cy="11" r="11" fill="url(#<?php echo esc_attr($grad); ?>)"/>
                            <path d="M6 11l3.5 3.5L16 8" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="32" y="4" width="<?php echo esc_attr([92, 76, 100, 64][$i]); ?>" height="7" rx="3.5" fill="#fff" fill-opacity=".55"/>
                            <rect x="32" y="15" width="<?php echo esc_attr([60, 84, 52, 70][$i]); ?>" height="5" rx="2.5" fill="#fff" fill-opacity=".2"/>
                        </g></g>
                    <?php endforeach; ?>
                </g>
                <?php
                echo wp_kses(haptic_onboarding_badge(110, 110, haptic_icon('shield', 24), 'ob-g-cyan', 'ob-a-float ob-delay-1'), haptic_kses_ui());
                echo wp_kses(haptic_onboarding_badge(372, 140, haptic_onboarding_icon('gauge', 24), 'ob-g-green', 'ob-a-float ob-delay-2'), haptic_kses_ui());
                echo wp_kses(haptic_onboarding_badge(120, 262, haptic_icon('sparkles', 24), 'ob-g-violet', 'ob-a-float ob-delay-3'), haptic_kses_ui());
                ?>
            <?php
            echo '</svg>';
            break;

        case 'done':
            $confetti = [[-120, -70, '#8af0ff'], [110, -90, '#7ef0c4'], [-90, 100, '#d2c4ff'], [130, 60, '#ffd27a'], [-150, 10, '#8aa4ff'], [150, -20, '#ff8a8a'],
                [-40, -130, '#7ef0c4'], [50, 130, '#8af0ff'], [-130, -120, '#ffd27a'], [90, -140, '#d2c4ff'], [-60, 140, '#8aa4ff'], [160, 120, '#7ef0c4']];
            echo '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo-green)" class="ob-a-breathe"/>
                <circle cx="240" cy="180" r="80" fill="none" stroke="#7ef0c4" stroke-width="1.5" class="ob-a-ping"/>
                <circle cx="240" cy="180" r="80" fill="none" stroke="#7ef0c4" stroke-width="1.5" class="ob-a-ping ob-delay-2"/>
                <?php foreach ($confetti as $i => $c) : ?>
                    <g transform="translate(240 180)"><rect x="-4" y="-4" width="8" height="<?php echo esc_attr($i % 2 ? 8 : 14); ?>" rx="2" fill="<?php echo esc_attr($c[2]); ?>" class="ob-a-burst" style="--dx:<?php echo esc_attr($c[0]); ?>px;--dy:<?php echo esc_attr($c[1]); ?>px;--r:<?php echo esc_attr(($i * 47) % 360); ?>deg"/></g>
                <?php endforeach; ?>
                <g class="ob-a-pop">
                    <circle cx="240" cy="180" r="76" fill="url(#ob-g-green)" filter="url(#ob-f-glow)"/>
                    <path d="M204 182l24 24 50-52" fill="none" stroke="#fff" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" pathLength="100" class="ob-a-draw ob-delay-1"/>
                </g>
            <?php
            echo '</svg>';
            break;

        default:
            // Étape d'une extension (Optimizr Pro) : son illustration
            do_action('haptic_onboarding_illustration', $name);
    }
}
