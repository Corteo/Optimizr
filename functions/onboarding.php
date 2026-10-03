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

define('HPT_ONBOARDING_SLUG', 'hpt-onboarding');

function hpt_onboarding_url($args = [])
{
    return add_query_arg($args, admin_url('admin.php?page=' . HPT_ONBOARDING_SLUG));
}

/* ---------- Ouverture : à l'activation, puis à la demande ---------- */

/** Appelé par hpt_options_activate() : l'assistant s'ouvre tant qu'il n'a été ni terminé ni écarté. */
function hpt_onboarding_schedule_redirect()
{
    if (!get_option('hpt_onboarding_done')) {
        set_transient('hpt_onboarding_redirect', 1, MINUTE_IN_SECONDS);
    }
}

add_action('admin_init', function () {
    if (!get_transient('hpt_onboarding_redirect')) {
        return;
    }
    delete_transient('hpt_onboarding_redirect');

    // Pas pendant une activation groupée, une requête AJAX ou depuis l'administration du réseau
    if (wp_doing_ajax() || is_network_admin() || isset($_GET['activate-multi']) || !current_user_can('manage_options')) {
        return;
    }

    wp_safe_redirect(hpt_onboarding_url());
    exit;
});

// Lien dans la liste des extensions
add_filter('plugin_action_links_' . plugin_basename(HPT_OPTIMIZR_PATH . 'hpt_options.php'), function ($links) {
    if (current_user_can('manage_options')) {
        array_unshift($links, '<a href="' . esc_url(hpt_onboarding_url()) . '">Assistant de configuration</a>');
    }
    return $links;
});

// Page du menu Optimizr, affichée sans l'interface de l'administration
add_action('admin_menu', function () {
    $hook = add_submenu_page('hpt-options', 'Assistant de configuration - Optimizr', 'Assistant', 'manage_options', HPT_ONBOARDING_SLUG, '__return_null');
    if ($hook) {
        add_action('load-' . $hook, 'hpt_onboarding_render');
    }
}, 20);

/* ---------- Contenu des étapes ---------- */

/**
 * Étapes de l'assistant. 'module' : étape affichée seulement si ce module est choisi.
 * 'pillar' : thème mis en avant dans le panneau visuel (security, performance, optimization ou all).
 */
function hpt_onboarding_steps()
{
    return [
        'welcome'  => ['label' => 'Bienvenue', 'module' => '', 'pillar' => 'all',
            'kicker' => 'Sécurité · Performance · Optimisation', 'tagline' => 'Un seul plugin pour un site sûr, rapide et bien référencé.'],
        'modules'  => ['label' => 'Modules', 'module' => '', 'pillar' => 'all',
            'kicker' => 'Sur mesure', 'tagline' => 'Seuls les modules activés chargent leur code : rien d\'inutile ne ralentit le site.'],
        'security' => ['label' => 'Sécurité', 'module' => 'security', 'pillar' => 'security',
            'kicker' => 'Sécurité', 'tagline' => 'Des robots testent des mots de passe en continu : fermez-leur la porte.'],
        'seo'      => ['label' => 'Référencement', 'module' => 'seo', 'pillar' => 'performance',
            'kicker' => 'Performance', 'tagline' => 'Des balises propres et un sitemap à jour : Google indexe plus vite et mieux.'],
        'cookies'  => ['label' => 'Cookies & RGPD', 'module' => 'cookies', 'pillar' => 'security',
            'kicker' => 'Confidentialité', 'tagline' => 'Aucun traceur avant l\'accord du visiteur, Google Consent Mode v2 inclus.'],
        'ai'       => ['label' => 'Assistant IA', 'module' => 'ai', 'pillar' => 'optimization',
            'kicker' => 'Optimisation', 'tagline' => 'Meta descriptions et balises ALT rédigées en quelques secondes.'],
        'summary'  => ['label' => 'Récapitulatif', 'module' => '', 'pillar' => 'all',
            'kicker' => 'Dernière étape', 'tagline' => 'Vérifiez, puis appliquez. Tout reste modifiable dans Optimizr.'],
    ];
}

/** Regroupement des modules par thème dans l'étape « Modules ». */
function hpt_onboarding_module_groups()
{
    return [
        'security'     => ['label' => 'Sécurité & conformité', 'modules' => ['security', 'cookies']],
        'performance'  => ['label' => 'Performance & visibilité', 'modules' => ['seo', 'redirects', 'local', 'pages']],
        'optimization' => ['label' => 'Optimisation & contenus', 'modules' => ['ai', 'cpts', 'thumbnails', 'emails', 'devtools']],
    ];
}

/**
 * Protections proposées par l'étape Sécurité (options hpt_sec_*).
 * 'recommended' : cochée par défaut tant que l'option n'a jamais été enregistrée.
 */
function hpt_onboarding_security_toggles()
{
    return [
        'limit_login'       => ['Limiter les tentatives de connexion', 'Une adresse IP est bloquée 15 minutes après 5 mots de passe erronés.', true],
        'generic_errors'    => ['Messages d\'erreur neutres', 'La page de connexion ne dit plus si c\'est l\'identifiant ou le mot de passe qui est faux.', true],
        'hide_version'      => ['Masquer la version de WordPress', 'Les robots ne peuvent plus cibler les failles connues de votre version.', true],
        'block_enumeration' => ['Bloquer la liste des comptes', 'Empêche de découvrir les identifiants par ?author=1 et l\'API REST.', true],
        'disable_xmlrpc'    => ['Désactiver XML-RPC', class_exists('Jetpack')
            ? 'Attention : Jetpack est actif et a besoin de XML-RPC.'
            : 'Ancienne interface, cible fréquente des attaques par mot de passe.', !class_exists('Jetpack')],
        'disable_file_edit' => ['Désactiver l\'éditeur de fichiers', 'Un compte piraté ne peut plus injecter de code dans les thèmes et extensions.', true],
        'headers'           => ['En-têtes de sécurité', 'X-Content-Type-Options, X-Frame-Options, Referrer-Policy et Permissions-Policy.', true],
        'login_alert'       => ['Alerte de connexion', 'Un email prévient l\'administrateur qui se connecte depuis une adresse IP inconnue.', false],
    ];
}

/** Réglages proposés par l'étape Référencement : option => [titre, description, valeur par défaut]. */
function hpt_onboarding_seo_toggles()
{
    return [
        'hpt_enable_seo'              => ['Balises meta automatiques', 'Titre et description générés pour chaque contenu qui n\'en a pas.', 1],
        'hpt_enable_opengraph'        => ['Aperçus de partage (OpenGraph)', 'Titre, description et image lors d\'un partage sur les réseaux sociaux.', 1],
        'hpt_seo_schema'              => ['Données structurées schema.org', 'Site, organisation, fil d\'Ariane et articles décrits pour les résultats enrichis.', 1],
        'hpt_seo_noindex_search'      => ['Ne pas indexer la recherche interne', 'Les pages de résultats de recherche du site restent hors de Google.', 1],
        'hpt_seo_attachment_redirect' => ['Rediriger les pages des médias', 'Les pages créées pour chaque image renvoient vers leur contenu.', 1],
    ];
}

function hpt_onboarding_ai_providers()
{
    if (!function_exists('hpt_ai_providers')) {
        require_once HPT_OPTIMIZR_PATH . 'functions/ai-pricing.php';
    }
    return hpt_ai_providers();
}

/* ---------- Enregistrement ---------- */

add_action('admin_post_hpt_onboarding_save', 'hpt_onboarding_save');

function hpt_onboarding_save()
{
    if (!current_user_can('manage_options')) {
        wp_die('Permissions insuffisantes.', 403);
    }
    check_admin_referer('hpt_onboarding');

    $post = wp_unslash($_POST);

    $modules = hpt_sanitize_modules(isset($post['hpt_modules']) ? $post['hpt_modules'] : []);
    update_option('hpt_modules', $modules);

    $flag = function ($name) use ($post) {
        return empty($post[$name]) ? 0 : 1;
    };

    if (!empty($modules['security'])) {
        foreach (array_keys(hpt_onboarding_security_toggles()) as $key) {
            update_option('hpt_sec_' . $key, $flag('hpt_sec_' . $key));
        }
    }

    if (!empty($modules['seo'])) {
        foreach (array_keys(hpt_onboarding_seo_toggles()) as $option) {
            update_option($option, $flag($option));
        }
        $public = array_keys(get_post_types(['public' => true]));
        $types = isset($post['hpt_sitemap_post_types']) ? array_map('sanitize_key', (array) $post['hpt_sitemap_post_types']) : [];
        update_option('hpt_sitemap_post_types', array_values(array_intersect($types, $public)));
        if (isset($post['blog_public'])) {
            update_option('blog_public', $flag('blog_public'));
        }
    }

    if (!empty($modules['cookies'])) {
        update_option('hpt_option_enable_tarteaucitron', $flag('hpt_option_enable_tarteaucitron'));
        $style = isset($post['hpt_option_tarteaucitron_banner_style']) ? (string) $post['hpt_option_tarteaucitron_banner_style'] : 'middle';
        update_option('hpt_option_tarteaucitron_banner_style', in_array($style, ['top', 'middle', 'bottom'], true) ? $style : 'middle');

        foreach (['google_analytics', 'google_tags'] as $service) {
            $field = 'hpt_ob_service_' . $service;
            if (!isset($post[$field])) {
                continue;
            }
            $id = sanitize_text_field(trim((string) $post[$field]));
            $value = get_option('hpt_option_service_' . $service, null);
            // Service jamais configuré et laissé vide : rien à enregistrer
            if ($id === '' && $value === null) {
                continue;
            }
            $value = (array) $value;
            $value['ID'] = $id;
            update_option('hpt_option_service_' . $service, $value);
        }
    }

    if (!empty($modules['ai'])) {
        $providers = hpt_onboarding_ai_providers();
        $provider = isset($post['hpt_ai_provider']) ? (string) $post['hpt_ai_provider'] : '';
        if (isset($providers[$provider])) {
            update_option('hpt_ai_provider', $provider);
            $key = isset($post['hpt_ob_ai_key']) ? sanitize_text_field(trim((string) $post['hpt_ob_ai_key'])) : '';
            // Champ vide : on garde la clé déjà enregistrée
            if ($key !== '') {
                update_option($providers[$provider]['key_option'], $key);
            }
        }
        update_option('hpt_ai_alt_on_upload', $flag('hpt_ai_alt_on_upload'));
    }

    update_option('hpt_onboarding_done', time());

    wp_safe_redirect(hpt_onboarding_url(['step' => 'done']));
    exit;
}

// « Quitter l'assistant » : il ne s'ouvrira plus de lui-même
add_action('admin_post_hpt_onboarding_skip', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Permissions insuffisantes.', 403);
    }
    check_admin_referer('hpt_onboarding_skip');

    if (!get_option('hpt_onboarding_done')) {
        update_option('hpt_onboarding_done', time());
    }

    wp_safe_redirect(admin_url('admin.php?page=hpt-options'));
    exit;
});

/* ---------- Affichage ---------- */

/** Interrupteur de l'assistant. Le champ caché envoie 0 quand la case est décochée. */
function hpt_onboarding_switch($name, $checked, $title, $desc = '', $tag = '')
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

function hpt_onboarding_render()
{
    if (!current_user_can('manage_options')) {
        wp_die('Permissions insuffisantes.', 403);
    }

    $css = HPT_OPTIMIZR_PATH . 'css/optimizr-onboarding.css';
    $js  = HPT_OPTIMIZR_PATH . 'js/optimizr-onboarding.js';
    wp_register_style('optimizr-onboarding', HPT_OPTIMIZR_URL . 'css/optimizr-onboarding.css', [], filemtime($css));
    wp_register_script('optimizr-onboarding', HPT_OPTIMIZR_URL . 'js/optimizr-onboarding.js', [], filemtime($js), true);

    $done = isset($_GET['step']) && $_GET['step'] === 'done';
    $steps = hpt_onboarding_steps();

    nocache_headers();
    header('Content-Type: text/html; charset=' . get_option('blog_charset'));
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
    <meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $done ? 'Configuration terminée' : 'Assistant de configuration'; ?> - Optimizr</title>
    <script>document.documentElement.className = 'js';</script>
    <?php wp_print_styles('optimizr-onboarding'); ?>
</head>
<body class="ob-body">
    <?php hpt_onboarding_svg_defs(); ?>
    <div class="ob<?php echo $done ? ' ob--done' : ''; ?>">
        <aside class="ob-visual" aria-hidden="true">
            <div class="ob-brand">
                <span class="ob-brand__logo">O</span>
                <span class="ob-brand__name">Optimizr</span>
            </div>
            <div class="ob-stage">
                <?php if ($done) : ?>
                    <div class="ob-illu is-active"><?php hpt_onboarding_illustration('done'); ?></div>
                <?php else : ?>
                    <?php foreach (array_keys($steps) as $i => $slug) : ?>
                        <div class="ob-illu<?php echo $i === 0 ? ' is-active' : ''; ?>" data-illu="<?php echo esc_attr($slug); ?>"><?php hpt_onboarding_illustration($slug); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="ob-caption">
                <?php if ($done) : ?>
                    <p class="ob-caption__kicker">C'est parti</p>
                    <p class="ob-caption__text">Votre site est protégé, visible et prêt à être optimisé.</p>
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
                <li class="ob-pillars__item" data-pillar-item="security"><?php echo hpt_icon('shield', 16); ?>Sécurité</li>
                <li class="ob-pillars__item" data-pillar-item="performance"><?php echo hpt_onboarding_icon('gauge'); ?>Performance</li>
                <li class="ob-pillars__item" data-pillar-item="optimization"><?php echo hpt_icon('sparkles', 16); ?>Optimisation</li>
            </ul>
        </aside>

        <main class="ob-panel">
            <?php $done ? hpt_onboarding_render_done() : hpt_onboarding_render_form($steps); ?>
        </main>
    </div>
    <?php wp_print_scripts('optimizr-onboarding'); ?>
</body>
</html>
    <?php
    exit;
}

function hpt_onboarding_render_form($steps)
{
    $modules = hpt_modules();
    $skip_url = wp_nonce_url(admin_url('admin-post.php?action=hpt_onboarding_skip'), 'hpt_onboarding_skip');
    ?>
    <div class="ob-top">
        <div class="ob-progress" aria-hidden="true"><span class="ob-progress__bar" data-ob-progress></span></div>
        <p class="ob-top__count" data-ob-count aria-live="polite"></p>
        <a class="ob-top__skip" href="<?php echo esc_url($skip_url); ?>">Quitter l'assistant</a>
    </div>

    <ol class="ob-stepper" data-ob-stepper>
        <?php foreach ($steps as $slug => $step) : ?>
            <li class="ob-stepper__item" data-stepper="<?php echo esc_attr($slug); ?>" data-module="<?php echo esc_attr($step['module']); ?>"><span class="ob-stepper__dot" aria-hidden="true"></span><span class="ob-stepper__label"><?php echo esc_html($step['label']); ?></span></li>
        <?php endforeach; ?>
    </ol>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ob-form" data-ob-form novalidate>
        <input type="hidden" name="action" value="hpt_onboarding_save">
        <?php wp_nonce_field('hpt_onboarding'); ?>

        <?php foreach (array_keys($steps) as $i => $slug) : ?>
            <section class="ob-step<?php echo $i === 0 ? ' is-active' : ''; ?>" data-step="<?php echo esc_attr($slug); ?>" data-module="<?php echo esc_attr($steps[$slug]['module']); ?>" aria-labelledby="ob-title-<?php echo esc_attr($slug); ?>">
                <?php call_user_func('hpt_onboarding_step_' . $slug, $modules); ?>
            </section>
        <?php endforeach; ?>

        <div class="ob-nav">
            <button type="button" class="ob-btn ob-btn--ghost" data-ob-prev hidden>Retour</button>
            <button type="button" class="ob-btn ob-btn--primary" data-ob-next>Commencer</button>
            <button type="submit" class="ob-btn ob-btn--primary" data-ob-submit>Appliquer la configuration</button>
        </div>
    </form>
    <?php
}

function hpt_onboarding_step_head($slug, $eyebrow, $title, $intro)
{
    ?>
    <p class="ob-step__eyebrow"><?php echo esc_html($eyebrow); ?></p>
    <h1 class="ob-step__title" id="ob-title-<?php echo esc_attr($slug); ?>" tabindex="-1"><?php echo esc_html($title); ?></h1>
    <p class="ob-step__intro"><?php echo esc_html($intro); ?></p>
    <?php
}

function hpt_onboarding_step_welcome($modules)
{
    $user = wp_get_current_user();
    $name = $user->first_name ?: $user->display_name;
    hpt_onboarding_step_head('welcome', $name ? 'Bonjour ' . $name : 'Bienvenue', 'Configurons Optimizr en quelques minutes', 'Quelques questions pour activer l\'essentiel sur ' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES) . '. Les réglages sont appliqués à la dernière étape, et restent modifiables ensuite.');
    ?>
    <div class="ob-pillar-cards">
        <div class="ob-pillar-card ob-pillar-card--security">
            <span class="ob-pillar-card__icon"><?php echo hpt_icon('shield', 22); ?></span>
            <h2 class="ob-pillar-card__title">Sécurité</h2>
            <p class="ob-pillar-card__text">Connexion protégée, surface d'attaque réduite et consentement RGPD.</p>
        </div>
        <div class="ob-pillar-card ob-pillar-card--performance">
            <span class="ob-pillar-card__icon"><?php echo hpt_onboarding_icon('gauge', 22); ?></span>
            <h2 class="ob-pillar-card__title">Performance</h2>
            <p class="ob-pillar-card__text">Balises, sitemap et indexation pour un site que Google comprend vite.</p>
        </div>
        <div class="ob-pillar-card ob-pillar-card--optimization">
            <span class="ob-pillar-card__icon"><?php echo hpt_icon('sparkles', 22); ?></span>
            <h2 class="ob-pillar-card__title">Optimisation</h2>
            <p class="ob-pillar-card__text">Textes et balises ALT générés par l'IA, modules activés à la carte.</p>
        </div>
    </div>
    <p class="ob-note"><?php echo hpt_icon('info', 16); ?>Environ 3 minutes. Vous pouvez quitter l'assistant à tout moment et le rouvrir depuis le menu Optimizr › Assistant.</p>
    <?php
}

function hpt_onboarding_step_modules($modules)
{
    hpt_onboarding_step_head('modules', 'Modules', 'Choisissez vos modules', 'Activez ce dont votre site a besoin. Les étapes suivantes s\'adaptent à votre choix.');

    $grouped = [];
    foreach (hpt_onboarding_module_groups() as $group) {
        $grouped = array_merge($grouped, $group['modules']);
    }
    $groups = hpt_onboarding_module_groups();
    // Modules ajoutés par le filtre hpt_modules : dans le dernier groupe
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
                        <span class="ob-module__icon"><?php echo hpt_icon($module['icon'], 20); ?></span>
                        <span class="ob-module__text">
                            <span class="ob-module__title"><?php echo esc_html($module['label']); ?></span>
                            <span class="ob-module__desc"><?php echo esc_html($module['desc']); ?></span>
                        </span>
                        <input type="checkbox" role="switch" class="ob-switch" id="ob-module-<?php echo esc_attr($slug); ?>" name="hpt_modules[<?php echo esc_attr($slug); ?>]" value="1" data-module-toggle="<?php echo esc_attr($slug); ?>" data-summary="<?php echo esc_attr($module['label']); ?>" <?php checked(hpt_is_module_active($slug)); ?>>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php
    endforeach;
}

function hpt_onboarding_step_security($modules)
{
    hpt_onboarding_step_head('security', 'Sécurité', 'Verrouillez les accès', 'Les protections recommandées sont présélectionnées. Elles n\'affectent pas les visiteurs du site.');
    ?>
    <div class="ob-options">
        <?php foreach (hpt_onboarding_security_toggles() as $key => $toggle) {
            hpt_onboarding_switch('hpt_sec_' . $key, get_option('hpt_sec_' . $key, $toggle[2] ? 1 : 0), $toggle[0], $toggle[1], $toggle[2] ? 'Recommandé' : '');
        } ?>
    </div>
    <p class="ob-note"><?php echo hpt_icon('lock', 16); ?>L'adresse de connexion personnalisée, le blocage du PHP dans les médias et HSTS se règlent dans l'onglet Sécurité, une fois le site vérifié.</p>
    <?php
}

function hpt_onboarding_step_seo($modules)
{
    hpt_onboarding_step_head('seo', 'Référencement', 'Soyez visible et indexé rapidement', 'Des balises cohérentes et un plan du site à jour aident Google à parcourir le site sans gaspiller son temps d\'exploration.');
    $selected = (array) get_option('hpt_sitemap_post_types', ['post', 'page']);
    ?>
    <div class="ob-options">
        <?php
        if (!get_option('blog_public')) {
            hpt_onboarding_switch('blog_public', 0, 'Autoriser l\'indexation du site', 'Le site demande actuellement aux moteurs de ne pas l\'indexer (Réglages › Lecture). À laisser désactivé sur un site en préparation.', 'Attention');
        }
        foreach (hpt_onboarding_seo_toggles() as $option => $toggle) {
            hpt_onboarding_switch($option, get_option($option, $toggle[2]), $toggle[0], $toggle[1]);
        }
        ?>
    </div>
    <fieldset class="ob-fieldset">
        <legend class="ob-fieldset__title">Contenus du sitemap XML</legend>
        <div class="ob-chips">
            <?php foreach (get_post_types(['public' => true], 'objects') as $type) :
                if ($type->name === 'attachment') {
                    continue;
                }
                ?>
                <label class="ob-chip">
                    <input type="checkbox" name="hpt_sitemap_post_types[]" value="<?php echo esc_attr($type->name); ?>" data-summary="Sitemap : <?php echo esc_attr($type->labels->name); ?>" <?php checked(in_array($type->name, $selected, true)); ?>>
                    <span><?php echo esc_html($type->labels->name); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <?php
}

function hpt_onboarding_step_cookies($modules)
{
    hpt_onboarding_step_head('cookies', 'Cookies & RGPD', 'Respectez la vie privée de vos visiteurs', 'Le bandeau Tarteaucitron ne charge les services de mesure qu\'après accord du visiteur.');
    $style = get_option('hpt_option_tarteaucitron_banner_style', 'middle');
    $ga = (array) get_option('hpt_option_service_google_analytics', []);
    $gtm = (array) get_option('hpt_option_service_google_tags', []);
    ?>
    <div class="ob-options">
        <?php hpt_onboarding_switch('hpt_option_enable_tarteaucitron', get_option('hpt_option_enable_tarteaucitron', 1), 'Afficher le bandeau de consentement', 'Obligatoire dès qu\'un service de mesure d\'audience ou de publicité est utilisé.', 'Recommandé'); ?>
    </div>

    <fieldset class="ob-fieldset">
        <legend class="ob-fieldset__title">Position du bandeau</legend>
        <div class="ob-positions">
            <?php foreach (['top' => 'En haut', 'middle' => 'Au centre', 'bottom' => 'En bas'] as $value => $label) : ?>
                <label class="ob-position ob-position--<?php echo esc_attr($value); ?>">
                    <input type="radio" name="hpt_option_tarteaucitron_banner_style" value="<?php echo esc_attr($value); ?>" <?php checked($style, $value); ?>>
                    <span class="ob-position__preview" aria-hidden="true"><span class="ob-position__banner"></span></span>
                    <span class="ob-position__label"><?php echo esc_html($label); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="ob-fields">
        <label class="ob-field">
            <span class="ob-field__label">Google Analytics 4 <span class="ob-field__opt">facultatif</span></span>
            <input type="text" name="hpt_ob_service_google_analytics" value="<?php echo esc_attr($ga['ID'] ?? ''); ?>" placeholder="G-XXXXXXXXXX" spellcheck="false" autocomplete="off" data-summary-value="Google Analytics">
        </label>
        <label class="ob-field">
            <span class="ob-field__label">Google Tag Manager <span class="ob-field__opt">facultatif</span></span>
            <input type="text" name="hpt_ob_service_google_tags" value="<?php echo esc_attr($gtm['ID'] ?? ''); ?>" placeholder="GTM-XXXXXXX" spellcheck="false" autocomplete="off" data-summary-value="Google Tag Manager">
        </label>
    </div>
    <p class="ob-note"><?php echo hpt_icon('info', 16); ?>Couleurs du bandeau et autres services (Clarity, Matomo, Meta, reCAPTCHA…) : onglet Cookies & RGPD.</p>
    <?php
}

function hpt_onboarding_step_ai($modules)
{
    hpt_onboarding_step_head('ai', 'Assistant IA', 'Laissez l\'IA rédiger pour vous', 'Meta descriptions et balises ALT générées à partir du contenu et du contexte du site. Chaque génération est facturée par le fournisseur choisi.');
    $providers = hpt_onboarding_ai_providers();
    $current = get_option('hpt_ai_provider', HPT_AI_DEFAULT_PROVIDER);
    $current = isset($providers[$current]) ? $current : HPT_AI_DEFAULT_PROVIDER;
    ?>
    <fieldset class="ob-fieldset">
        <legend class="ob-fieldset__title">Fournisseur</legend>
        <div class="ob-providers">
            <?php foreach ($providers as $slug => $provider) :
                $has_key = trim((string) get_option($provider['key_option'], '')) !== '';
                ?>
                <label class="ob-provider">
                    <input type="radio" name="hpt_ai_provider" value="<?php echo esc_attr($slug); ?>" data-has-key="<?php echo $has_key ? '1' : '0'; ?>" data-key-url="<?php echo esc_url($provider['key_url'] ?? ''); ?>" data-summary="Fournisseur : <?php echo esc_attr($provider['label']); ?>" <?php checked($current, $slug); ?>>
                    <span class="ob-provider__name"><?php echo esc_html($provider['label']); ?></span>
                    <span class="ob-provider__state"><?php echo $has_key ? 'Clé enregistrée' : 'Clé à ajouter'; ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <div class="ob-fields">
        <label class="ob-field ob-field--wide">
            <span class="ob-field__label">Clé API <a class="ob-field__link" href="#" target="_blank" rel="noopener noreferrer" data-ob-key-link>Obtenir une clé</a></span>
            <input type="password" name="hpt_ob_ai_key" value="" autocomplete="off" spellcheck="false" data-ob-key placeholder="Collez la clé de votre compte" data-placeholder-saved="Clé déjà enregistrée : laissez vide pour la conserver" data-placeholder-empty="Collez la clé de votre compte">
        </label>
    </div>
    <div class="ob-options">
        <?php hpt_onboarding_switch('hpt_ai_alt_on_upload', get_option('hpt_ai_alt_on_upload', 1), 'ALT automatique à l\'import', 'Chaque image ajoutée à la médiathèque reçoit une description (une requête facturée par image).'); ?>
    </div>
    <?php
}

function hpt_onboarding_step_summary($modules)
{
    hpt_onboarding_step_head('summary', 'Récapitulatif', 'Tout est prêt à être appliqué', 'Voici ce qui sera enregistré. Revenez en arrière pour changer un choix.');
    ?>
    <div class="ob-summary" data-ob-summary></div>
    <noscript><p class="ob-note">Vérifiez les choix ci-dessus avant d'appliquer la configuration.</p></noscript>
    <?php
}

function hpt_onboarding_render_done()
{
    $tabs = hpt_admin_tabs();
    $next = [];
    foreach (hpt_modules() as $slug => $module) {
        if (hpt_is_module_active($slug) && isset($tabs[$module['tab']]) && !isset($next[$module['tab']])) {
            $next[$module['tab']] = $module;
        }
    }
    ?>
    <div class="ob-done">
        <p class="ob-step__eyebrow">Configuration terminée</p>
        <h1 class="ob-step__title" tabindex="-1">Optimizr veille sur votre site</h1>
        <p class="ob-step__intro">Les réglages sont enregistrés et actifs. Affinez chaque module depuis son onglet quand vous le souhaitez.</p>

        <?php if ($next) : ?>
            <h2 class="ob-fieldset__title">Pour aller plus loin</h2>
            <ul class="ob-next">
                <?php foreach ($next as $tab => $module) : ?>
                    <li>
                        <a class="ob-next__item" href="<?php echo esc_url(hpt_admin_tab_url($tab)); ?>">
                            <span class="ob-module__icon"><?php echo hpt_icon($module['icon'], 18); ?></span>
                            <span class="ob-next__label"><?php echo esc_html($tabs[$tab]['label']); ?></span>
                            <?php echo hpt_icon('chevron-right', 16); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="ob-nav ob-nav--done">
            <a class="ob-btn ob-btn--ghost" href="<?php echo esc_url(hpt_onboarding_url()); ?>">Relancer l'assistant</a>
            <a class="ob-btn ob-btn--primary" href="<?php echo esc_url(admin_url('admin.php?page=hpt-options')); ?>">Ouvrir le tableau de bord</a>
        </div>
    </div>
    <?php
}

/* ---------- Illustrations (SVG inline, sans ressource externe) ---------- */

/** Icône « jauge » (absente de hpt_icon()). */
function hpt_onboarding_icon($name, $size = 16)
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
function hpt_onboarding_svg_defs()
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

/** Pastille ronde avec une icône 24×24 (chemins de hpt_icon() ou hpt_onboarding_icon()). */
function hpt_onboarding_badge($x, $y, $icon_svg, $gradient, $class = '')
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

function hpt_onboarding_illustration($name)
{
    $open = '<svg class="ob-svg" viewBox="0 0 480 360" aria-hidden="true" focusable="false">';
    $close = '</svg>';

    switch ($name) {
        case 'welcome':
            ?>
            <?php echo $open; ?>
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
                echo hpt_onboarding_badge(108, 96, hpt_icon('shield', 24), 'ob-g-cyan', 'ob-a-float ob-delay-1');
                echo hpt_onboarding_badge(382, 120, hpt_onboarding_icon('gauge', 24), 'ob-g-green', 'ob-a-float ob-delay-2');
                echo hpt_onboarding_badge(366, 282, hpt_onboarding_icon('sliders', 24), 'ob-g-violet', 'ob-a-float ob-delay-3');
                echo hpt_onboarding_badge(104, 268, hpt_icon('sparkles', 24), 'ob-g-accent', 'ob-a-float');
                ?>
                <circle cx="70" cy="180" r="2.5" fill="#8af0ff" class="ob-a-twinkle"/>
                <circle cx="420" cy="208" r="2" fill="#d2c4ff" class="ob-a-twinkle ob-delay-2"/>
                <circle cx="300" cy="44" r="2.5" fill="#7ef0c4" class="ob-a-twinkle ob-delay-1"/>
                <circle cx="190" cy="320" r="2" fill="#8aa4ff" class="ob-a-twinkle ob-delay-3"/>
            <?php echo $close; ?>
            <?php
            break;

        case 'modules':
            $icons = ['shield', 'search', 'sparkles', 'redirect', 'lock', 'pin', 'file', 'layers', 'image'];
            $lit = [0, 1, 2, 4];
            echo $open;
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <path d="M240 180 L142 82 M240 180 L338 82 M240 180 L142 278 M240 180 L338 278" stroke="#8af0ff" stroke-opacity=".35" stroke-dasharray="4 8" class="ob-a-flow"/>
                <?php foreach ($icons as $i => $icon) :
                    $x = 126 + ($i % 3) * 82;
                    $y = 66 + intdiv($i, 3) * 82;
                    $on = in_array($i, $lit, true);
                    $inner = preg_replace('#^<svg[^>]*>|</svg>$#', '', hpt_icon($icon, 24));
                    ?>
                    <g transform="translate(<?php echo $x; ?> <?php echo $y; ?>)">
                        <g class="ob-a-tile" style="animation-delay:<?php echo 0.08 * $i; ?>s">
                            <rect width="64" height="64" rx="14" fill="<?php echo $on ? 'url(#ob-g-accent)' : 'url(#ob-g-glass)'; ?>" stroke="#fff" stroke-opacity="<?php echo $on ? '.35' : '.16'; ?>"<?php echo $on ? ' filter="url(#ob-f-glow)"' : ''; ?>/>
                            <g transform="translate(18 18)" fill="none" stroke="#fff" stroke-opacity="<?php echo $on ? '1' : '.55'; ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $inner; ?></g>
                            <circle cx="54" cy="10" r="4" fill="<?php echo $on ? '#7ef0c4' : '#fff'; ?>" fill-opacity="<?php echo $on ? '1' : '.18'; ?>"/>
                        </g>
                    </g>
                <?php endforeach; ?>
            <?php
            echo $close;
            break;

        case 'security':
            echo $open;
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
                    <text x="62" y="124" class="ob-svg-text">HTTPS actif</text>
                </g>
                <g class="ob-a-float ob-delay-3">
                    <rect x="336" y="196" width="128" height="40" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <circle cx="358" cy="216" r="10" fill="#ff8a8a" fill-opacity=".2"/><path d="M354 212l8 8m0-8-8 8" stroke="#ff8a8a" stroke-width="2.2" stroke-linecap="round"/>
                    <text x="376" y="220" class="ob-svg-text">Robot bloqué</text>
                </g>
            <?php
            echo $close;
            break;

        case 'seo':
            echo $open;
            ?>
                <circle cx="240" cy="190" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <path d="M110 220a130 130 0 0 1 260 0" fill="none" stroke="#fff" stroke-opacity=".1" stroke-width="18" stroke-linecap="round"/>
                <path d="M110 220a130 130 0 0 1 260 0" fill="none" stroke="url(#ob-g-arc)" stroke-width="18" stroke-linecap="round" pathLength="100" stroke-dasharray="100" class="ob-a-gauge"/>
                <?php for ($i = 0; $i <= 10; $i++) :
                    $a = deg2rad(180 + $i * 18);
                    printf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#fff" stroke-opacity="%s" stroke-width="2" stroke-linecap="round"/>',
                        240 + cos($a) * 100, 220 + sin($a) * 100, 240 + cos($a) * ($i % 5 ? 108 : 112), 220 + sin($a) * ($i % 5 ? 108 : 112), $i % 5 ? '.25' : '.5');
                endfor; ?>
                <g transform="translate(240 220)">
                    <g class="ob-a-needle">
                        <path d="M-5 0 0-96 5 0z" fill="#fff"/>
                    </g>
                    <circle r="13" fill="#fff"/>
                    <circle r="5" fill="#3858e9"/>
                </g>
                <text x="240" y="276" text-anchor="middle" class="ob-svg-score">98</text>
                <text x="240" y="298" text-anchor="middle" class="ob-svg-text ob-svg-text--muted">Score SEO</text>
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
                    <text x="58" y="274" class="ob-svg-text">Indexation</text>
                </g>
            <?php
            echo $close;
            break;

        case 'cookies':
            echo $open;
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo)" class="ob-a-breathe"/>
                <g class="ob-a-float">
                    <rect x="120" y="70" width="240" height="220" rx="18" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".22"/>
                    <rect x="144" y="94" width="110" height="10" rx="5" fill="#fff" fill-opacity=".6"/>
                    <rect x="144" y="112" width="170" height="6" rx="3" fill="#fff" fill-opacity=".2"/>
                    <?php foreach ([0, 1, 2] as $i) :
                        $y = 140 + $i * 40;
                        ?>
                        <rect x="144" y="<?php echo $y + 8; ?>" width="<?php echo [96, 120, 80][$i]; ?>" height="7" rx="3.5" fill="#fff" fill-opacity=".35"/>
                        <g transform="translate(296 <?php echo $y; ?>)">
                            <rect width="40" height="22" rx="11" class="ob-a-track" style="animation-delay:<?php echo 0.5 + 0.45 * $i; ?>s"/>
                            <circle cx="29" cy="11" r="8" fill="#fff" class="ob-a-knob" style="animation-delay:<?php echo 0.5 + 0.45 * $i; ?>s"/>
                        </g>
                    <?php endforeach; ?>
                    <rect x="144" y="256" width="92" height="18" rx="9" fill="none" stroke="#fff" stroke-opacity=".35"/>
                    <rect x="244" y="256" width="92" height="18" rx="9" fill="url(#ob-g-accent)"/>
                </g>
                <?php echo hpt_onboarding_badge(366, 82, hpt_icon('shield', 24), 'ob-g-green', 'ob-a-pop ob-delay-1'); ?>
                <g transform="translate(104 268)"><g class="ob-a-float ob-delay-2">
                    <circle r="30" fill="#f2b56b"/>
                    <circle cx="22" cy="-18" r="10" fill="#0b1437"/><circle cx="28" cy="-4" r="7" fill="#0b1437"/>
                    <circle cx="-10" cy="-8" r="4" fill="#8a5a22"/><circle cx="6" cy="10" r="4.5" fill="#8a5a22"/><circle cx="-12" cy="14" r="3" fill="#8a5a22"/><circle cx="4" cy="-16" r="3" fill="#8a5a22"/>
                </g></g>
            <?php
            echo $close;
            break;

        case 'ai':
            $nodes = [[110, 92], [96, 220], [160, 300], [370, 86], [392, 196], [330, 292]];
            echo $open;
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo-violet)" class="ob-a-breathe"/>
                <?php foreach ($nodes as $i => $n) : ?>
                    <path d="M240 180 L<?php echo $n[0]; ?> <?php echo $n[1]; ?>" stroke="#fff" stroke-opacity=".14" stroke-width="1.5"/>
                    <path d="M240 180 L<?php echo $n[0]; ?> <?php echo $n[1]; ?>" stroke="#8af0ff" stroke-width="2" stroke-linecap="round" stroke-dasharray="8 160" class="ob-a-pulse" style="animation-delay:<?php echo 0.3 * $i; ?>s"/>
                    <circle cx="<?php echo $n[0]; ?>" cy="<?php echo $n[1]; ?>" r="9" fill="#101a45" stroke="url(#ob-g-cyan)" stroke-width="2"/>
                    <circle cx="<?php echo $n[0]; ?>" cy="<?php echo $n[1]; ?>" r="3.5" fill="#8af0ff" class="ob-a-twinkle" style="animation-delay:<?php echo 0.3 * $i; ?>s"/>
                <?php endforeach; ?>
                <g class="ob-a-float">
                    <?php foreach ([-24, -8, 8, 24] as $o) : ?>
                        <path d="M<?php echo 240 + $o; ?> 126v-12M<?php echo 240 + $o; ?> 234v12M186 <?php echo 180 + $o; ?>h-12M294 <?php echo 180 + $o; ?>h12" stroke="#d2c4ff" stroke-opacity=".6" stroke-width="3" stroke-linecap="round"/>
                    <?php endforeach; ?>
                    <rect x="186" y="126" width="108" height="108" rx="24" fill="url(#ob-g-violet)" filter="url(#ob-f-glow)"/>
                    <rect x="198" y="138" width="84" height="84" rx="16" fill="none" stroke="#fff" stroke-opacity=".4"/>
                    <g transform="translate(212 152) scale(2.333)"><g fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="ob-a-twinkle-soft">
                        <path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 17v4"/><path d="M17 19h4"/>
                    </g></g>
                </g>
                <g class="ob-a-float ob-delay-2">
                    <rect x="14" y="132" width="140" height="56" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <text x="28" y="152" class="ob-svg-text ob-svg-text--muted">Meta description</text>
                    <rect x="28" y="162" width="108" height="6" rx="3" fill="#fff" fill-opacity=".55" class="ob-a-type"/>
                    <rect x="28" y="173" width="72" height="6" rx="3" fill="#fff" fill-opacity=".35" class="ob-a-type ob-delay-1"/>
                </g>
                <g class="ob-a-float ob-delay-3">
                    <rect x="334" y="128" width="132" height="44" rx="12" fill="url(#ob-g-glass)" stroke="#fff" stroke-opacity=".2"/>
                    <g transform="translate(346 138)" fill="none" stroke="#d2c4ff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></g>
                    <text x="378" y="155" class="ob-svg-text">ALT généré</text>
                </g>
            <?php
            echo $close;
            break;

        case 'summary':
            echo $open;
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
                        <g transform="translate(176 <?php echo $y; ?>)"><g class="ob-a-tile" style="animation-delay:<?php echo 0.15 * $i; ?>s">
                            <circle cx="11" cy="11" r="11" fill="url(#<?php echo $grad; ?>)"/>
                            <path d="M6 11l3.5 3.5L16 8" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="32" y="4" width="<?php echo [92, 76, 100, 64][$i]; ?>" height="7" rx="3.5" fill="#fff" fill-opacity=".55"/>
                            <rect x="32" y="15" width="<?php echo [60, 84, 52, 70][$i]; ?>" height="5" rx="2.5" fill="#fff" fill-opacity=".2"/>
                        </g></g>
                    <?php endforeach; ?>
                </g>
                <?php
                echo hpt_onboarding_badge(110, 110, hpt_icon('shield', 24), 'ob-g-cyan', 'ob-a-float ob-delay-1');
                echo hpt_onboarding_badge(372, 140, hpt_onboarding_icon('gauge', 24), 'ob-g-green', 'ob-a-float ob-delay-2');
                echo hpt_onboarding_badge(120, 262, hpt_icon('sparkles', 24), 'ob-g-violet', 'ob-a-float ob-delay-3');
                ?>
            <?php
            echo $close;
            break;

        case 'done':
            $confetti = [[-120, -70, '#8af0ff'], [110, -90, '#7ef0c4'], [-90, 100, '#d2c4ff'], [130, 60, '#ffd27a'], [-150, 10, '#8aa4ff'], [150, -20, '#ff8a8a'],
                [-40, -130, '#7ef0c4'], [50, 130, '#8af0ff'], [-130, -120, '#ffd27a'], [90, -140, '#d2c4ff'], [-60, 140, '#8aa4ff'], [160, 120, '#7ef0c4']];
            echo $open;
            ?>
                <circle cx="240" cy="180" r="170" fill="url(#ob-g-halo-green)" class="ob-a-breathe"/>
                <circle cx="240" cy="180" r="80" fill="none" stroke="#7ef0c4" stroke-width="1.5" class="ob-a-ping"/>
                <circle cx="240" cy="180" r="80" fill="none" stroke="#7ef0c4" stroke-width="1.5" class="ob-a-ping ob-delay-2"/>
                <?php foreach ($confetti as $i => $c) : ?>
                    <g transform="translate(240 180)"><rect x="-4" y="-4" width="8" height="<?php echo $i % 2 ? 8 : 14; ?>" rx="2" fill="<?php echo $c[2]; ?>" class="ob-a-burst" style="--dx:<?php echo $c[0]; ?>px;--dy:<?php echo $c[1]; ?>px;--r:<?php echo ($i * 47) % 360; ?>deg"/></g>
                <?php endforeach; ?>
                <g class="ob-a-pop">
                    <circle cx="240" cy="180" r="76" fill="url(#ob-g-green)" filter="url(#ob-f-glow)"/>
                    <path d="M204 182l24 24 50-52" fill="none" stroke="#fff" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" pathLength="100" class="ob-a-draw ob-delay-1"/>
                </g>
            <?php
            echo $close;
            break;
    }
}
