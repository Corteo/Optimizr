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
 */
function haptic_onboarding_steps()
{
    return [
        'welcome'  => ['label' => __('Welcome', 'optimizr'), 'module' => '', 'pillar' => 'all',
            'kicker' => __('Security · Performance · Optimization', 'optimizr'), 'tagline' => __('One plugin for a safe, fast and well-ranked site.', 'optimizr')],
        'modules'  => ['label' => __('Modules', 'optimizr'), 'module' => '', 'pillar' => 'all',
            'kicker' => __('Tailor-made', 'optimizr'), 'tagline' => __('Only enabled modules load their code: nothing unnecessary slows the site down.', 'optimizr')],
        'security' => ['label' => __('Security', 'optimizr'), 'module' => 'security', 'pillar' => 'security',
            'kicker' => __('Security', 'optimizr'), 'tagline' => __('Bots try passwords around the clock: shut the door on them.', 'optimizr')],
        'seo'      => ['label' => _x('SEO', 'onboarding step', 'optimizr'), 'module' => 'seo', 'pillar' => 'performance',
            'kicker' => __('Performance', 'optimizr'), 'tagline' => __('Clean tags and an up-to-date sitemap: Google indexes faster and better.', 'optimizr')],
        'local'    => ['label' => __('Business', 'optimizr'), 'module' => 'local', 'pillar' => 'performance',
            'kicker' => __('Local SEO', 'optimizr'), 'tagline' => __('Address, phone and business type picked up by Google Maps and local search.', 'optimizr')],
        'pages'    => ['label' => __('404 page', 'optimizr'), 'module' => 'pages', 'pillar' => 'optimization',
            'kicker' => __('Experience', 'optimizr'), 'tagline' => __('A missing URL suggests related content instead of a dead end.', 'optimizr')],
        'cookies'  => ['label' => __('Cookies & GDPR', 'optimizr'), 'module' => 'cookies', 'pillar' => 'security',
            'kicker' => __('Privacy', 'optimizr'), 'tagline' => __('No tracker before the visitor agrees, Google Consent Mode v2 included.', 'optimizr')],
        'ai'       => ['label' => __('AI assistant', 'optimizr'), 'module' => 'ai', 'pillar' => 'optimization',
            'kicker' => __('Optimization', 'optimizr'), 'tagline' => __('Meta descriptions and ALT tags written in seconds.', 'optimizr')],
        'summary'  => ['label' => __('Summary', 'optimizr'), 'module' => '', 'pillar' => 'all',
            'kicker' => __('Last step', 'optimizr'), 'tagline' => __('Review, then apply. Everything can still be changed in Optimizr.', 'optimizr')],
    ];
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
    return [
        'limit_login'       => [__('Limit login attempts', 'optimizr'), __('An IP address is blocked for 15 minutes after 5 wrong passwords.', 'optimizr'), true],
        'generic_errors'    => [__('Neutral error messages', 'optimizr'), __('The login page no longer says whether the username or the password is wrong.', 'optimizr'), true],
        'hide_version'      => [__('Hide the WordPress version', 'optimizr'), __('Bots can no longer target known flaws of your version.', 'optimizr'), true],
        'block_enumeration' => [__('Block user enumeration', 'optimizr'), __('Prevents discovering usernames through ?author=1 and the REST API.', 'optimizr'), true],
        'disable_xmlrpc'    => [__('Disable XML-RPC', 'optimizr'), class_exists('Jetpack')
            ? __('Warning: Jetpack is active and needs XML-RPC.', 'optimizr')
            : __('Legacy interface, a frequent target of password attacks.', 'optimizr'), !class_exists('Jetpack')],
        'disable_file_edit' => [__('Disable the file editor', 'optimizr'), __('A hacked account can no longer inject code into themes and plugins.', 'optimizr'), true],
        'headers'           => [__('Security headers', 'optimizr'), __('X-Content-Type-Options, X-Frame-Options, Referrer-Policy and Permissions-Policy.', 'optimizr'), true],
        'login_alert'       => [__('Login alert', 'optimizr'), __('An email alerts an administrator who logs in from an unknown IP address.', 'optimizr'), false],
    ];
}

/** Réglages proposés par l'étape Référencement : option => [titre, description, valeur par défaut]. */
function haptic_onboarding_seo_toggles()
{
    return [
        'haptic_enable_seo'              => [__('Automatic meta tags', 'optimizr'), __('Title and description generated for any content that has none.', 'optimizr'), 1],
        'haptic_enable_opengraph'        => [__('Share previews (OpenGraph)', 'optimizr'), __('Title, description and image when shared on social networks.', 'optimizr'), 1],
        'haptic_seo_schema'              => [__('schema.org structured data', 'optimizr'), __('Site, organization, breadcrumbs and articles described for rich results.', 'optimizr'), 1],
        'haptic_seo_noindex_search'      => [__('Do not index internal search', 'optimizr'), __('The site\'s search results pages stay out of Google.', 'optimizr'), 1],
        'haptic_seo_attachment_redirect' => [__('Redirect attachment pages', 'optimizr'), __('The pages created for each image point to their content.', 'optimizr'), 1],
    ];
}

function haptic_onboarding_ai_providers()
{
    if (!function_exists('haptic_ai_providers')) {
        require_once HAPTIC_OPTIMIZR_PATH . 'functions/ai-pricing.php';
    }
    return haptic_ai_providers();
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

/** Code de vérification Google ou Bing : accepte la balise meta entière ou le seul code. */
function haptic_onboarding_verification_code($value)
{
    $value = trim((string) $value);
    if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $value, $match)) {
        $value = $match[1];
    }
    return preg_replace('/[^A-Za-z0-9_\-]/', '', $value);
}

/** Services de mesure proposés à l'étape Cookies : champ du formulaire => [service, clé, libellé, exemple]. */
function haptic_onboarding_cookie_fields()
{
    return [
        'haptic_ob_service_google_analytics' => ['google_analytics', 'ID', 'Google Analytics 4', 'G-XXXXXXXXXX'],
        'haptic_ob_service_google_tags'      => ['google_tags', 'ID', 'Google Tag Manager', 'GTM-XXXXXXX'],
        'haptic_ob_service_clarity'          => ['clarity', 'ID', 'Microsoft Clarity', __('Project ID', 'optimizr')],
        'haptic_ob_service_facebook_pixel'   => ['facebook_pixel', 'Pixel ID', __('Meta Pixel (Facebook)', 'optimizr'), __('Pixel ID', 'optimizr')],
    ];
}

/** Champs de l'étape Établissement : clé de haptic_local => [libellé, type, exemple, autocomplete]. */
function haptic_onboarding_local_fields()
{
    return [
        'name'        => [__('Business name', 'optimizr'), 'text', get_bloginfo('name'), 'organization'],
        'street'      => [__('Address', 'optimizr'), 'text', _x('123 Main Street', 'placeholder example', 'optimizr'), 'street-address'],
        'postal_code' => [__('Postal code', 'optimizr'), 'text', _x('10001', 'placeholder example', 'optimizr'), 'postal-code'],
        'city'        => [__('City', 'optimizr'), 'text', _x('New York', 'placeholder example', 'optimizr'), 'address-level2'],
        'country'     => [__('Country (two-letter code)', 'optimizr'), 'text', 'FR', 'country'],
        'phone'       => [__('Phone', 'optimizr'), 'tel', _x('+1 212 555 0100', 'placeholder example', 'optimizr'), 'tel'],
        'email'       => [__('Contact email', 'optimizr'), 'email', _x('contact@example.com', 'placeholder example', 'optimizr'), 'email'],
    ];
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
        $public = array_keys(get_post_types(['public' => true]));
        $types = isset($post['haptic_sitemap_post_types']) ? array_map('sanitize_key', (array) $post['haptic_sitemap_post_types']) : [];
        update_option('haptic_sitemap_post_types', array_values(array_intersect($types, $public)));
        if (isset($post['blog_public'])) {
            update_option('blog_public', $flag('blog_public'));
        }

        foreach (['blogname', 'blogdescription', 'haptic_seo_title_pattern', 'haptic_seo_desc_pattern'] as $option) {
            $value = $text($option);
            if ($value !== null && ($value !== '' || $option === 'blogdescription')) {
                update_option($option, $value);
            }
        }
        foreach (['haptic_seo_verify_google', 'haptic_seo_verify_bing'] as $option) {
            if (isset($post[$option])) {
                update_option($option, haptic_onboarding_verification_code($post[$option]));
            }
        }
    }

    if (!empty($modules['local']) && isset($post['haptic_ob_local']) && is_array($post['haptic_ob_local'])) {
        haptic_onboarding_require('haptic_local_types', 'local-seo.php');
        $local = (array) get_option('haptic_local', []);
        $input = $post['haptic_ob_local'];
        foreach (array_keys(haptic_onboarding_local_fields()) as $key) {
            if (isset($input[$key])) {
                $local[$key] = $key === 'email' ? sanitize_email($input[$key]) : sanitize_text_field(trim((string) $input[$key]));
            }
        }
        $local['country'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($local['country'] ?? '')), 0, 2)) ?: 'FR';
        $local['type'] = isset($input['type']) && array_key_exists($input['type'], haptic_local_types()) ? $input['type'] : ($local['type'] ?? 'LocalBusiness');
        update_option('haptic_local', $local);
    }

    if (!empty($modules['pages'])) {
        if (isset($post['haptic_404_page'])) {
            $page = absint($post['haptic_404_page']);
            update_option('haptic_404_page', $page && get_post_type($page) === 'page' ? $page : 0);
        }
        update_option('haptic_404_suggestions', $flag('haptic_404_suggestions'));
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

    if (!empty($modules['ai'])) {
        $providers = haptic_onboarding_ai_providers();
        $provider = isset($post['haptic_ai_provider']) ? (string) $post['haptic_ai_provider'] : '';
        if (isset($providers[$provider])) {
            update_option('haptic_ai_provider', $provider);
            $key = isset($post['haptic_ob_ai_key']) ? sanitize_text_field(trim((string) $post['haptic_ob_ai_key'])) : '';
            // Champ vide : on garde la clé déjà enregistrée
            if ($key !== '') {
                update_option($providers[$provider]['key_option'], $key);
            }
            $model_option = $providers[$provider]['model_option'];
            $model = isset($post[$model_option]) ? (string) $post[$model_option] : '';
            if (isset($providers[$provider]['models'][$model])) {
                update_option($model_option, $model);
            }
        }
        if (isset($post['haptic_ai_context'])) {
            update_option('haptic_ai_context', sanitize_textarea_field((string) $post['haptic_ai_context']));
        }
        update_option('haptic_ai_alt_on_upload', $flag('haptic_ai_alt_on_upload'));
    }

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
                <?php call_user_func('haptic_onboarding_step_' . $slug, $modules); ?>
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
            <p class="ob-pillar-card__text"><?php esc_html_e('Tags, sitemap and indexing for a site Google understands quickly.', 'optimizr'); ?></p>
        </div>
        <div class="ob-pillar-card ob-pillar-card--optimization">
            <span class="ob-pillar-card__icon"><?php echo wp_kses(haptic_icon('sparkles', 22), haptic_kses_ui()); ?></span>
            <h2 class="ob-pillar-card__title"><?php esc_html_e('Optimization', 'optimizr'); ?></h2>
            <p class="ob-pillar-card__text"><?php esc_html_e('AI-generated text and ALT tags, modules enabled as you need them.', 'optimizr'); ?></p>
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
    <p class="ob-note"><?php echo wp_kses(haptic_icon('lock', 16), haptic_kses_ui()); ?><?php esc_html_e('Blocking PHP in uploads and HSTS are set in the Security tab, once the site has been checked.', 'optimizr'); ?></p>
    <?php
}

function haptic_onboarding_step_seo($modules)
{
    haptic_onboarding_step_head('seo', _x('SEO', 'onboarding step', 'optimizr'), __('Get visible and indexed quickly', 'optimizr'), __('The site name and the patterns below are used as title and description in Google for any content that has none.', 'optimizr'));
    $selected = (array) get_option('haptic_sitemap_post_types', ['post', 'page']);
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
        <label class="ob-field">
            <span class="ob-field__label">Google Search Console <span class="ob-field__opt"><?php esc_html_e('optional', 'optimizr'); ?></span></span>
            <input type="text" name="haptic_seo_verify_google" value="<?php echo esc_attr(get_option('haptic_seo_verify_google', '')); ?>" placeholder="<?php esc_attr_e('Code or meta tag', 'optimizr'); ?>" spellcheck="false" autocomplete="off" data-summary-value="<?php esc_attr_e('Google verification', 'optimizr'); ?>">
        </label>
        <label class="ob-field">
            <span class="ob-field__label">Bing Webmaster Tools <span class="ob-field__opt"><?php esc_html_e('optional', 'optimizr'); ?></span></span>
            <input type="text" name="haptic_seo_verify_bing" value="<?php echo esc_attr(get_option('haptic_seo_verify_bing', '')); ?>" placeholder="<?php esc_attr_e('Code or meta tag', 'optimizr'); ?>" spellcheck="false" autocomplete="off" data-summary-value="<?php esc_attr_e('Bing verification', 'optimizr'); ?>">
        </label>
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
    <fieldset class="ob-fieldset">
        <legend class="ob-fieldset__title"><?php esc_html_e('XML sitemap content', 'optimizr'); ?></legend>
        <div class="ob-chips">
            <?php foreach (get_post_types(['public' => true], 'objects') as $type) :
                if ($type->name === 'attachment') {
                    continue;
                }
                ?>
                <label class="ob-chip">
                    <input type="checkbox" name="haptic_sitemap_post_types[]" value="<?php echo esc_attr($type->name); ?>" data-summary="<?php
                        /* translators: %s: post type name */
                        echo esc_attr(sprintf(__('Sitemap: %s', 'optimizr'), $type->labels->name));
                    ?>" <?php checked(in_array($type->name, $selected, true)); ?>>
                    <span><?php echo esc_html($type->labels->name); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <?php
}

function haptic_onboarding_step_local($modules)
{
    haptic_onboarding_step_head('local', __('Local SEO', 'optimizr'), __('Introduce your business', 'optimizr'), __('This information is published for Google (Maps, local search) and can be reused on the site with the [optimizr_adresse] or [optimizr_telephone] shortcodes. It must match your Google Business Profile listing.', 'optimizr'));
    haptic_onboarding_require('haptic_local_types', 'local-seo.php');
    $local = (array) get_option('haptic_local', []);
    $type = $local['type'] ?? 'LocalBusiness';
    ?>
    <div class="ob-fields">
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label"><?php esc_html_e('Business type', 'optimizr'); ?></span>
            <select name="haptic_ob_local[type]" data-summary-value="<?php esc_attr_e('Business type', 'optimizr'); ?>">
                <?php foreach (haptic_local_types() as $schema => $label) : ?>
                    <option value="<?php echo esc_attr($schema); ?>" <?php selected($type, $schema); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php foreach (haptic_onboarding_local_fields() as $key => $field) :
            $value = $local[$key] ?? ($key === 'country' ? 'FR' : '');
            ?>
            <label class="ob-field<?php echo $key === 'street' ? ' ob-field--wide' : ''; ?>">
                <span class="ob-field__label"><?php echo esc_html($field[0]); ?></span>
                <input type="<?php echo esc_attr($field[1]); ?>" name="haptic_ob_local[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($field[2]); ?>" autocomplete="<?php echo esc_attr($field[3]); ?>"<?php echo $key === 'country' ? ' maxlength="2" pattern="[A-Za-z]{2}"' : ''; ?> data-summary-value="<?php echo esc_attr($field[0]); ?>">
            </label>
        <?php endforeach; ?>
    </div>
    <p class="ob-note"><?php echo wp_kses(haptic_icon('pin', 16), haptic_kses_ui()); ?><?php esc_html_e('Opening hours, GPS coordinates, Google place ID and online profiles: Local SEO tab.', 'optimizr'); ?></p>
    <?php
}

function haptic_onboarding_step_pages($modules)
{
    haptic_onboarding_step_head('pages', __('Special pages', 'optimizr'), __('Polish the not found page', 'optimizr'), __('Choose a page written in the editor: it is displayed with the theme layout, keeps the 404 status code and is not indexed.', 'optimizr'));
    ?>
    <div class="ob-fields">
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label"><?php esc_html_e('404 page content', 'optimizr'); ?></span>
            <?php
            $dropdown = wp_dropdown_pages([
                'name'              => 'haptic_404_page',
                'id'                => 'ob-404-page',
                'selected'          => (int) get_option('haptic_404_page'),
                'show_option_none'  => esc_html__('Theme 404 page', 'optimizr'),
                'option_none_value' => 0,
                'post_status'       => ['publish', 'private', 'draft'],
                'echo'              => 0,
            ]);
            echo $dropdown
                ? wp_kses(str_replace('<select ', '<select data-summary-value="' . esc_attr__('404 page', 'optimizr') . '" ', $dropdown), haptic_kses_ui())
                : '<span class="ob-field__help">' . esc_html__('No pages on the site: create one ("Page not found") then choose it in the Special pages tab.', 'optimizr') . '</span><input type="hidden" name="haptic_404_page" value="0">';
            ?>
        </label>
    </div>
    <div class="ob-options">
        <?php haptic_onboarding_switch('haptic_404_suggestions', get_option('haptic_404_suggestions', 1), __('Suggest related content', 'optimizr'), __('Links to pages whose URL looks like the requested one, plus a search field.', 'optimizr'), __('Recommended', 'optimizr')); ?>
    </div>
    <p class="ob-note"><?php echo wp_kses(haptic_icon('info', 16), haptic_kses_ui()); ?><?php esc_html_e('Maintenance mode is set in the Special pages tab, whenever you need it.', 'optimizr'); ?></p>
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
    <p class="ob-note"><?php echo wp_kses(haptic_icon('info', 16), haptic_kses_ui()); ?><?php esc_html_e('Banner colors and other services (Matomo, Hotjar, Google Ads, reCAPTCHA…): Cookies & GDPR tab.', 'optimizr'); ?></p>
    <?php
}

function haptic_onboarding_step_ai($modules)
{
    haptic_onboarding_step_head('ai', __('AI assistant', 'optimizr'), __('Let AI write for you', 'optimizr'), __('Meta descriptions and ALT tags generated from the content and your business description. Each generation is billed by the chosen provider.', 'optimizr'));
    $providers = haptic_onboarding_ai_providers();
    $current = get_option('haptic_ai_provider', HAPTIC_AI_DEFAULT_PROVIDER);
    $current = isset($providers[$current]) ? $current : HAPTIC_AI_DEFAULT_PROVIDER;
    ?>
    <fieldset class="ob-fieldset ob-fieldset--first">
        <legend class="ob-fieldset__title"><?php esc_html_e('Provider', 'optimizr'); ?></legend>
        <div class="ob-providers">
            <?php foreach ($providers as $slug => $provider) :
                $has_key = trim((string) get_option($provider['key_option'], '')) !== '';
                ?>
                <label class="ob-provider">
                    <input type="radio" name="haptic_ai_provider" value="<?php echo esc_attr($slug); ?>" data-has-key="<?php echo $has_key ? '1' : '0'; ?>" data-key-url="<?php echo esc_url($provider['key_url'] ?? ''); ?>" data-summary="<?php
                        /* translators: %s: AI provider name */
                        echo esc_attr(sprintf(__('Provider: %s', 'optimizr'), $provider['label']));
                    ?>" <?php checked($current, $slug); ?>>
                    <span class="ob-provider__name"><?php echo esc_html($provider['label']); ?></span>
                    <span class="ob-provider__state"><?php echo $has_key ? esc_html__('Key saved', 'optimizr') : esc_html__('Key needed', 'optimizr'); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <div class="ob-fields">
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label"><?php esc_html_e('API key', 'optimizr'); ?> <a class="ob-field__link" href="#" target="_blank" rel="noopener noreferrer" data-ob-key-link><?php esc_html_e('Get a key', 'optimizr'); ?></a></span>
            <input type="password" name="haptic_ob_ai_key" value="" autocomplete="off" spellcheck="false" data-ob-key placeholder="<?php esc_attr_e('Paste your account key', 'optimizr'); ?>" data-placeholder-saved="<?php esc_attr_e('Key already saved: leave empty to keep it', 'optimizr'); ?>" data-placeholder-empty="<?php esc_attr_e('Paste your account key', 'optimizr'); ?>">
        </label>
        <?php foreach ($providers as $slug => $provider) :
            $model = get_option($provider['model_option'], $provider['default'] ?? '');
            ?>
            <label class="ob-field ob-field--wide" data-ob-provider-row="<?php echo esc_attr($slug); ?>"<?php echo $slug === $current ? '' : ' hidden'; ?>>
                <span class="ob-field__label"><?php esc_html_e('Model', 'optimizr'); ?></span>
                <select name="<?php echo esc_attr($provider['model_option']); ?>" data-summary-value="<?php esc_attr_e('Model', 'optimizr'); ?>">
                    <?php foreach ($provider['models'] as $id => $info) : ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($model, $id); ?>><?php echo esc_html($info['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endforeach; ?>
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label"><?php esc_html_e('Your business description', 'optimizr'); ?></span>
            <textarea name="haptic_ai_context" rows="3" placeholder="<?php esc_attr_e('E.g. pottery school in Lyon, classes for adults and children, weekend workshops.', 'optimizr'); ?>" data-summary-value="<?php echo esc_attr_x('Business description', 'onboarding summary', 'optimizr'); ?>"><?php echo esc_textarea(get_option('haptic_ai_context', '')); ?></textarea>
            <span class="ob-field__help"><?php esc_html_e('Sent with every request: the AI writes with the right vocabulary and tone.', 'optimizr'); ?></span>
        </label>
    </div>
    <div class="ob-options">
        <?php haptic_onboarding_switch('haptic_ai_alt_on_upload', get_option('haptic_ai_alt_on_upload', 1), __('Automatic ALT on upload', 'optimizr'), __('Every image added to the media library gets a description (one billed request per image).', 'optimizr')); ?>
    </div>
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
                    <text x="370" y="78" class="ob-svg-text">sitemap.xml</text>
                </g>
                <g class="ob-a-float ob-delay-3">
                    <rect x="22" y="250" width="124" height="40" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <g transform="translate(34 260)" fill="none" stroke="#ffd27a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2 3 12h7l-1 6 8-10h-7z"/></g>
                    <text x="58" y="274" class="ob-svg-text"><?php esc_html_e('Indexing', 'optimizr'); ?></text>
                </g>
            <?php
            echo '</svg>';
            break;

        case 'local':
            echo '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <g class="ob-a-float">
                    <rect x="100" y="70" width="280" height="210" rx="20" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".22"/>
                    <path d="M100 150h280M100 220h280M180 70v210M300 70v210" stroke="#fff" stroke-opacity=".08" stroke-width="8"/>
                    <path d="M120 250c40-10 60-60 110-70s70 40 120 20" fill="none" stroke="url(#ob-g-cyan)" stroke-width="4" stroke-linecap="round" stroke-dasharray="2 10" class="ob-a-flow"/>
                    <circle cx="120" cy="250" r="7" fill="#8af0ff"/>
                </g>
                <ellipse cx="240" cy="196" rx="26" ry="8" fill="#000" opacity=".25"/>
                <circle cx="240" cy="190" r="30" fill="none" stroke="#7ef0c4" stroke-width="1.5" class="ob-a-ping"/>
                <g transform="translate(240 190)"><g class="ob-a-pop">
                    <path d="M0 0c-26-34-40-52-40-72a40 40 0 0 1 80 0c0 20-14 38-40 72z" fill="url(#ob-g-green)" filter="url(#ob-f-glow)"/>
                    <circle cy="-72" r="15" fill="#fff"/>
                </g></g>
                <g class="ob-a-float ob-delay-2">
                    <rect x="300" y="226" width="150" height="56" rx="12" fill="#101a45" stroke="#fff" stroke-opacity=".2"/>
                    <text x="316" y="248" class="ob-svg-text"><?php esc_html_e('Open · 9 AM–7 PM', 'optimizr'); ?></text>
                    <g fill="#ffd27a"><?php for ($i = 0; $i < 5; $i++) : ?><path transform="translate(<?php echo esc_attr(316 + $i * 14); ?> 258) scale(.5)" d="M12 2l3 6.5 7 .8-5.2 4.8 1.4 7L12 17.6 5.8 21l1.4-7L2 9.3l7-.8z"/><?php endfor; ?></g>
                </g>
                <g class="ob-a-float ob-delay-1">
                    <rect x="30" y="92" width="134" height="40" rx="12" fill="#101a45" stroke="#fff" stroke-opacity=".2"/>
                    <g transform="translate(42 102)" fill="none" stroke="#8af0ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 14.5v2.6a1.7 1.7 0 0 1-1.9 1.7 17 17 0 0 1-7.4-2.6 16.8 16.8 0 0 1-5.2-5.2A17 17 0 0 1 .9 3.6 1.7 1.7 0 0 1 2.6 1.8h2.6a1.7 1.7 0 0 1 1.7 1.5c.1.8.3 1.6.6 2.4a1.7 1.7 0 0 1-.4 1.8L6 8.6a14 14 0 0 0 5.2 5.2l1.1-1.1a1.7 1.7 0 0 1 1.8-.4c.8.3 1.6.5 2.4.6a1.7 1.7 0 0 1 1.5 1.7z"/></g>
                    <text x="68" y="117" class="ob-svg-text"><?php esc_html_e('Call', 'optimizr'); ?></text>
                </g>
            <?php
            echo '</svg>';
            break;

        case 'pages':
            echo '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo-violet)" class="ob-a-breathe"/>
                <g class="ob-a-float">
                    <rect x="120" y="64" width="240" height="232" rx="18" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".22"/>
                    <path d="M120 92h240" stroke="#fff" stroke-opacity=".14"/>
                    <circle cx="138" cy="78" r="3.5" fill="#fff" fill-opacity=".35"/><circle cx="150" cy="78" r="3.5" fill="#fff" fill-opacity=".35"/><circle cx="162" cy="78" r="3.5" fill="#fff" fill-opacity=".35"/>
                    <text x="240" y="158" text-anchor="middle" class="ob-svg-score" style="font-size:52px">404</text>
                    <rect x="170" y="176" width="140" height="7" rx="3.5" fill="#fff" fill-opacity=".3"/>
                    <?php foreach ([0, 1, 2] as $i) : ?>
                        <g transform="translate(150 <?php echo esc_attr(198 + $i * 28); ?>)"><g class="ob-a-tile" style="animation-delay:<?php echo esc_attr(0.4 + 0.15 * $i); ?>s">
                            <rect width="180" height="20" rx="10" fill="#fff" fill-opacity=".1" stroke="#d2c4ff" stroke-opacity=".5"/>
                            <path d="M12 10h<?php echo esc_attr([90, 120, 70][$i]); ?>" stroke="#fff" stroke-opacity=".6" stroke-width="5" stroke-linecap="round"/>
                            <path d="M162 6l4 4-4 4" fill="none" stroke="#d2c4ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </g></g>
                    <?php endforeach; ?>
                </g>
                <?php echo wp_kses(haptic_onboarding_badge(372, 106, haptic_icon('search', 24), 'ob-g-violet', 'ob-a-float ob-delay-2'), haptic_kses_ui()); ?>
                <?php echo wp_kses(haptic_onboarding_badge(108, 250, haptic_icon('redirect', 24), 'ob-g-cyan', 'ob-a-float ob-delay-1'), haptic_kses_ui()); ?>
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

        case 'ai':
            $nodes = [[110, 92], [96, 220], [160, 300], [370, 86], [392, 196], [330, 292]];
            echo '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo-violet)" class="ob-a-breathe"/>
                <?php foreach ($nodes as $i => $n) : ?>
                    <path d="M240 180 L<?php echo esc_attr($n[0]); ?> <?php echo esc_attr($n[1]); ?>" stroke="#fff" stroke-opacity=".14" stroke-width="1.5"/>
                    <path d="M240 180 L<?php echo esc_attr($n[0]); ?> <?php echo esc_attr($n[1]); ?>" stroke="#8af0ff" stroke-width="2" stroke-linecap="round" stroke-dasharray="8 160" class="ob-a-pulse" style="animation-delay:<?php echo esc_attr(0.3 * $i); ?>s"/>
                    <circle cx="<?php echo esc_attr($n[0]); ?>" cy="<?php echo esc_attr($n[1]); ?>" r="9" fill="#101a45" stroke="url(#ob-g-cyan)" stroke-width="2"/>
                    <circle cx="<?php echo esc_attr($n[0]); ?>" cy="<?php echo esc_attr($n[1]); ?>" r="3.5" fill="#8af0ff" class="ob-a-twinkle" style="animation-delay:<?php echo esc_attr(0.3 * $i); ?>s"/>
                <?php endforeach; ?>
                <g class="ob-a-float">
                    <?php foreach ([-24, -8, 8, 24] as $o) : ?>
                        <path d="M<?php echo esc_attr(240 + $o); ?> 126v-12M<?php echo esc_attr(240 + $o); ?> 234v12M186 <?php echo esc_attr(180 + $o); ?>h-12M294 <?php echo esc_attr(180 + $o); ?>h12" stroke="#d2c4ff" stroke-opacity=".6" stroke-width="3" stroke-linecap="round"/>
                    <?php endforeach; ?>
                    <rect x="186" y="126" width="108" height="108" rx="24" fill="url(#ob-g-violet)" filter="url(#ob-f-glow)"/>
                    <rect x="198" y="138" width="84" height="84" rx="16" fill="none" stroke="#fff" stroke-opacity=".4"/>
                    <g transform="translate(212 152) scale(2.333)"><g fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="ob-a-twinkle-soft">
                        <path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 17v4"/><path d="M17 19h4"/>
                    </g></g>
                </g>
                <g class="ob-a-float ob-delay-2">
                    <rect x="14" y="132" width="140" height="56" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <text x="28" y="152" class="ob-svg-text ob-svg-text--muted"><?php esc_html_e('Meta description', 'optimizr'); ?></text>
                    <rect x="28" y="162" width="108" height="6" rx="3" fill="#fff" fill-opacity=".55" class="ob-a-type"/>
                    <rect x="28" y="173" width="72" height="6" rx="3" fill="#fff" fill-opacity=".35" class="ob-a-type ob-delay-1"/>
                </g>
                <g class="ob-a-float ob-delay-3">
                    <rect x="334" y="128" width="132" height="44" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <g transform="translate(346 138)" fill="none" stroke="#d2c4ff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></g>
                    <text x="378" y="155" class="ob-svg-text"><?php esc_html_e('ALT generated', 'optimizr'); ?></text>
                </g>
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
    }
}
