<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modules activables depuis le tableau de bord.
 * Chaque module est rattaché à l'onglet qui contient ses réglages.
 */
function hpt_modules()
{
    return apply_filters('hpt_modules', [
        'cookies' => [
            'label' => 'Cookies & RGPD',
            'desc'  => 'Bandeau Tarteaucitron, Google Consent Mode v2 et services de mesure d\'audience.',
            'icon'  => 'shield',
            'tab'   => 'cookies',
        ],
        'seo' => [
            'label' => 'SEO',
            'desc'  => 'Balises meta et OpenGraph, sitemap XML, fichier robots.txt.',
            'icon'  => 'search',
            'tab'   => 'seo',
        ],
        'ai' => [
            'label' => 'Assistant IA',
            'desc'  => 'Meta descriptions et balises ALT générées avec OpenAI, Claude ou Gemini, à partir du contexte du site.',
            'icon'  => 'sparkles',
            'tab'   => 'ai',
        ],
        'cpts' => [
            'label' => 'Types de contenu',
            'desc'  => 'CPT Évènements prêt à l\'emploi et types personnalisés créés depuis l\'admin.',
            'icon'  => 'layers',
            'tab'   => 'content',
        ],
        'thumbnails' => [
            'label' => 'Images à la une',
            'desc'  => 'Choisissez les types de contenu qui acceptent une image de couverture.',
            'icon'  => 'image',
            'tab'   => 'content',
        ],
        'emails' => [
            'label' => 'Emails',
            'desc'  => 'Coupez les emails envoyés après les mises à jour automatiques.',
            'icon'  => 'mail',
            'tab'   => 'advanced',
        ],
        'security' => [
            'label' => 'Sécurité',
            'desc'  => 'Adresse de connexion personnalisée, limitation des tentatives, version masquée, XML-RPC et en-têtes de sécurité.',
            'icon'  => 'lock',
            'tab'   => 'security',
        ],
        'devtools' => [
            'label' => 'Outils développeur',
            'desc'  => 'Mode debug, inspection vd() et journal des erreurs PHP, réservés aux admins.',
            'icon'  => 'code',
            'tab'   => 'advanced',
        ],
    ]);
}

/**
 * Un module est actif tant que le tableau de bord n'a jamais été enregistré.
 */
function hpt_is_module_active($slug)
{
    $modules = get_option('hpt_modules');

    if (!is_array($modules)) {
        return true;
    }

    return !empty($modules[$slug]);
}

// Sans le module, les options de debug sont forcées à 0 partout où elles sont lues
add_action('plugins_loaded', function () {
    if (hpt_is_module_active('devtools')) {
        return;
    }
    add_filter('pre_option_hpt_debug_mode', '__return_zero');
    add_filter('pre_option_hpt_dev_mode', '__return_zero');
}, 0);

/**
 * Onglets de la page d'options, filtrés selon les modules actifs.
 */
function hpt_admin_tabs()
{
    $tabs = [
        'dashboard' => ['label' => 'Tableau de bord', 'modules' => []],
        'cookies'   => ['label' => 'Cookies & RGPD', 'modules' => ['cookies']],
        'seo'       => ['label' => 'SEO', 'modules' => ['seo']],
        'ai'        => ['label' => 'Assistant IA', 'modules' => ['ai']],
        'content'   => ['label' => 'Contenus', 'modules' => ['cpts', 'thumbnails']],
        'security'  => ['label' => 'Sécurité', 'modules' => ['security']],
        'advanced'  => ['label' => 'Avancé', 'modules' => ['emails', 'devtools']],
        // Onglet sans réglages : ses propres formulaires, pas de barre d'enregistrement
        'tools'     => ['label' => 'Outils', 'modules' => [], 'render' => 'hpt_render_tools_tab'],
    ];

    $tabs = array_filter($tabs, function ($tab) {
        if (empty($tab['modules'])) {
            return true;
        }
        foreach ($tab['modules'] as $module) {
            if (hpt_is_module_active($module)) {
                return true;
            }
        }
        return false;
    });

    return apply_filters('hpt_admin_tabs', $tabs);
}

function hpt_current_tab()
{
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
    return array_key_exists($tab, hpt_admin_tabs()) ? $tab : 'dashboard';
}

/** Page Settings API d'un onglet (add_settings_section / add_settings_field). */
function hpt_settings_page($tab)
{
    return 'hpt-options-' . $tab;
}

/** Groupe Settings API d'un onglet (register_setting / settings_fields). */
function hpt_settings_group($tab)
{
    return 'hpt_group_' . $tab;
}

function hpt_admin_tab_url($tab)
{
    return admin_url('admin.php?page=hpt-options&tab=' . $tab);
}

/**
 * Interrupteur (case à cocher stylée) conforme à l'interface du plugin.
 */
function hpt_switch_input($name, $checked, $label, $id = '')
{
    $id = $id ?: sanitize_html_class(str_replace(['[', ']'], '-', $name));

    return sprintf(
        '<label class="optimizr-switch" for="%1$s"><input type="checkbox" role="switch" id="%1$s" name="%2$s" value="1" %3$s><span class="optimizr-switch__track" aria-hidden="true"></span><span class="screen-reader-text">%4$s</span></label>',
        esc_attr($id),
        esc_attr($name),
        checked(1, (int) $checked, false),
        esc_html($label)
    );
}

/**
 * Icônes au trait (24×24) utilisées dans l'interface.
 */
function hpt_icon($name, $size = 20)
{
    $paths = [
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'sparkles' => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 17v4"/><path d="M17 19h4"/>',
        'layers'   => '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'code'     => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'alert'    => '<path d="M12 8v5"/><path d="M12 17h.01"/>',
        'refresh'  => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
        'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ];

    if (!isset($paths[$name])) {
        return '';
    }

    return sprintf(
        '<svg class="optimizr-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
        (int) $size,
        $paths[$name]
    );
}

add_action('admin_menu', 'hpt_options_admin_menu');

function hpt_options_admin_menu()
{
    add_menu_page(
        'Réglages - Optimizr',
        'Optimizr',
        'manage_options',
        'hpt-options',
        'hpt_options_settings_page',
        'dashicons-admin-generic',
        81
    );
}

// Réglages du tableau de bord : liste des modules actifs
add_action('admin_init', function () {
    register_setting(hpt_settings_group('dashboard'), 'hpt_modules', [
        'type'              => 'array',
        'sanitize_callback' => 'hpt_sanitize_modules',
    ]);
});

function hpt_sanitize_modules($input)
{
    $input = is_array($input) ? $input : [];
    // Marqueur toujours envoyé : distingue « tout désactivé » de « jamais enregistré »
    $clean = ['_saved' => 1];

    foreach (array_keys(hpt_modules()) as $slug) {
        $clean[$slug] = empty($input[$slug]) ? 0 : 1;
    }

    return $clean;
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== HPT_OPTIMIZR_ADMIN_HOOK) {
        return;
    }

    $css = HPT_OPTIMIZR_PATH . 'css/optimizr-admin.css';
    $js  = HPT_OPTIMIZR_PATH . 'js/optimizr-admin.js';

    wp_enqueue_style('optimizr-admin', HPT_OPTIMIZR_URL . 'css/optimizr-admin.css', [], filemtime($css));
    wp_enqueue_script('optimizr-admin', HPT_OPTIMIZR_URL . 'js/optimizr-admin.js', [], filemtime($js), true);
});

function hpt_options_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $tabs    = hpt_admin_tabs();
    $current = hpt_current_tab();
    ?>
    <div class="wrap optimizr">
        <header class="optimizr-header">
            <div class="optimizr-header__top">
                <span class="optimizr-logo" aria-hidden="true">O</span>
                <h1 class="optimizr-header__title">Optimizr</h1>
                <span class="optimizr-badge">v<?php echo esc_html(HPT_OPTIMIZR_VERSION); ?></span>
                <div class="optimizr-header__actions">
                    <span id="hpt-update-status" class="optimizr-header__status" role="status"></span>
                    <button type="button" class="optimizr-btn optimizr-btn--secondary" id="hpt-check-update-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('hpt_check_update_action')); ?>">
                        <?php echo hpt_icon('refresh', 16); ?>Vérifier les mises à jour
                    </button>
                </div>
            </div>
            <nav class="optimizr-tabs" aria-label="Sections d'Optimizr">
                <?php foreach ($tabs as $slug => $tab) : ?>
                    <a href="<?php echo esc_url(hpt_admin_tab_url($slug)); ?>" class="optimizr-tabs__link" <?php echo $slug === $current ? 'aria-current="page"' : ''; ?>><?php echo esc_html($tab['label']); ?></a>
                <?php endforeach; ?>
            </nav>
        </header>

        <hr class="wp-header-end">

        <?php settings_errors(); ?>

        <?php if (!empty($tabs[$current]['render']) && is_callable($tabs[$current]['render'])) : ?>
            <main class="optimizr-main">
                <?php call_user_func($tabs[$current]['render']); ?>
            </main>
        <?php else : ?>

        <form method="post" action="options.php" class="optimizr-form" data-optimizr-form>
            <?php settings_fields(hpt_settings_group($current)); ?>

            <main class="optimizr-main">
                <?php
                if ($current === 'dashboard') {
                    hpt_render_dashboard();
                } else {
                    hpt_render_settings_tab($current, $tabs[$current]);
                }
                ?>
            </main>

            <div class="optimizr-savebar" data-optimizr-savebar>
                <span class="optimizr-savebar__dot" aria-hidden="true"></span>
                <span class="optimizr-savebar__text" data-optimizr-savebar-text>Toutes les modifications sont enregistrées</span>
                <button type="reset" class="optimizr-btn optimizr-btn--ghost" data-optimizr-reset>Annuler</button>
                <button type="submit" class="optimizr-btn optimizr-btn--primary">Enregistrer les modifications</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Onglet de réglages : sections du Settings API en colonne principale,
 * panneau latéral optionnel via l'action hpt_admin_aside_{onglet}.
 */
function hpt_render_settings_tab($slug, $tab)
{
    $intros = [
        'cookies'  => 'Consentement des visiteurs et services tiers chargés uniquement après autorisation.',
        'seo'      => 'Titres et descriptions par défaut, plan du site et consignes pour les robots d\'indexation.',
        'ai'       => 'Génération de contenus avec OpenAI, Claude ou Gemini : meta descriptions et balises ALT des images.',
        'content'  => 'Types de contenu personnalisés et images de couverture.',
        'security' => 'Protections activables une à une. Aucune n\'est active tant que vous ne l\'activez pas.',
        'advanced' => 'Notifications et outils réservés aux développeurs.',
    ];

    $has_aside = has_action("hpt_admin_aside_{$slug}");
    ?>
    <div class="optimizr-page-head">
        <h2 class="optimizr-page-head__title"><?php echo esc_html($tab['label']); ?></h2>
        <?php if (!empty($intros[$slug])) : ?>
            <p class="optimizr-page-head__intro"><?php echo esc_html($intros[$slug]); ?></p>
        <?php endif; ?>
    </div>
    <div class="optimizr-layout<?php echo $has_aside ? ' optimizr-layout--aside' : ''; ?>">
        <div class="optimizr-layout__main">
            <?php hpt_do_settings_sections(hpt_settings_page($slug)); ?>
        </div>
        <?php if ($has_aside) : ?>
            <aside class="optimizr-layout__aside">
                <?php do_action("hpt_admin_aside_{$slug}"); ?>
            </aside>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Vérifications rapides affichées sur le tableau de bord (aucune requête HTTP).
 *
 * @return array[] Liste de ['status' => ok|warn, 'label' => ..., 'link' => ?, 'link_label' => ?]
 */
function hpt_site_health_checks()
{
    $checks = [];

    if (!get_option('blog_public')) {
        $checks[] = [
            'status' => 'warn',
            'label' => 'Les moteurs de recherche sont invités à ne pas indexer le site',
            'link' => admin_url('options-reading.php'),
            'link_label' => 'Régler',
        ];
    }

    if (hpt_is_module_active('seo')) {
        $sitemap = file_exists(ABSPATH . 'sitemap.xml') && !get_option('hpt_sitemap_error');
        $checks[] = [
            'status' => $sitemap ? 'ok' : 'warn',
            'label' => $sitemap ? 'Sitemap XML généré' : (get_option('hpt_sitemap_error') ? 'Échec d\'écriture du sitemap XML' : 'Sitemap XML absent'),
            'link' => $sitemap ? home_url('/sitemap.xml') : hpt_admin_tab_url('seo'),
            'link_label' => $sitemap ? 'Voir' : 'Générer',
        ];
        $checks[] = [
            'status' => 'ok',
            'label' => get_option('hpt_enable_robots') ? 'robots.txt personnalisé' : 'robots.txt géré par WordPress',
            'link' => home_url('/robots.txt'),
            'link_label' => 'Voir',
        ];
    }

    if (hpt_is_module_active('cookies')) {
        $tac = get_option('hpt_option_enable_tarteaucitron');
        $checks[] = [
            'status' => $tac ? 'ok' : 'warn',
            'label' => $tac ? 'Bandeau cookies actif' : 'Bandeau cookies désactivé',
            'link' => $tac ? '' : hpt_admin_tab_url('cookies'),
            'link_label' => 'Activer',
        ];
    }

    if (hpt_is_module_active('ai') && function_exists('hpt_ai_api_key') && hpt_ai_api_key() === '') {
        $checks[] = [
            'status' => 'warn',
            'label' => sprintf('Clé API %s manquante', hpt_ai_provider_config()['label']),
            'link' => hpt_admin_tab_url('ai'),
            'link_label' => 'Ajouter',
        ];
    }

    if ((get_option('hpt_debug_mode') || get_option('hpt_dev_mode')) && wp_get_environment_type() === 'production') {
        $checks[] = [
            'status' => 'warn',
            'label' => 'Mode debug activé en production',
            'link' => hpt_admin_tab_url('advanced'),
            'link_label' => 'Régler',
        ];
    }

    return apply_filters('hpt_site_health_checks', $checks);
}

/**
 * Statut affiché en pied de carte d'un module.
 *
 * @return array ['status' => ok|warn|off, 'label' => ...]
 */
function hpt_module_status($slug)
{
    if (!hpt_is_module_active($slug)) {
        return ['status' => 'off', 'label' => 'Désactivé'];
    }

    if ($slug === 'ai' && function_exists('hpt_ai_api_key') && hpt_ai_api_key() === '') {
        return ['status' => 'warn', 'label' => 'Clé API manquante'];
    }

    if ($slug === 'cookies' && !get_option('hpt_option_enable_tarteaucitron')) {
        return ['status' => 'warn', 'label' => 'Bandeau désactivé'];
    }

    return ['status' => 'ok', 'label' => 'Actif'];
}

function hpt_render_dashboard()
{
    $checks = hpt_site_health_checks();
    ?>
    <div class="optimizr-page-head">
        <h2 class="optimizr-page-head__title">Vue d'ensemble</h2>
        <p class="optimizr-page-head__intro">Activez les modules dont votre site a besoin. Chaque module actif ajoute son onglet de réglages.</p>
    </div>

    <div class="optimizr-layout optimizr-layout--aside">
        <div class="optimizr-layout__main">
            <input type="hidden" name="hpt_modules[_saved]" value="1">
            <div class="optimizr-modules">
                <?php foreach (hpt_modules() as $slug => $module) :
                    $active = hpt_is_module_active($slug);
                    $status = hpt_module_status($slug);
                    ?>
                    <article class="optimizr-module<?php echo $active ? '' : ' is-off'; ?>">
                        <div class="optimizr-module__top">
                            <span class="optimizr-module__icon"><?php echo hpt_icon($module['icon']); ?></span>
                            <?php echo hpt_switch_input("hpt_modules[{$slug}]", $active, 'Module ' . $module['label'], "hpt-module-{$slug}"); ?>
                        </div>
                        <h3 class="optimizr-module__title"><?php echo esc_html($module['label']); ?></h3>
                        <p class="optimizr-module__desc"><?php echo esc_html($module['desc']); ?></p>
                        <div class="optimizr-module__foot">
                            <span class="optimizr-status optimizr-status--<?php echo esc_attr($status['status']); ?>"><?php echo esc_html($status['label']); ?></span>
                            <?php if ($active) : ?>
                                <a href="<?php echo esc_url(hpt_admin_tab_url($module['tab'])); ?>" class="optimizr-link">Configurer →</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="optimizr-layout__aside">
            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title">État du site</h2>
                        <p class="optimizr-card__desc">Vérifié à l'ouverture de cette page.</p>
                    </div>
                </div>
                <?php if (empty($checks)) : ?>
                    <p class="optimizr-card__empty">Rien à signaler.</p>
                <?php else : ?>
                    <ul class="optimizr-checks">
                        <?php foreach ($checks as $check) : ?>
                            <li class="optimizr-checks__item">
                                <span class="optimizr-checks__icon optimizr-checks__icon--<?php echo esc_attr($check['status']); ?>"><?php echo hpt_icon($check['status'] === 'ok' ? 'check' : 'alert', 14); ?></span>
                                <span class="optimizr-checks__label"><?php echo esc_html($check['label']); ?></span>
                                <?php if (!empty($check['link'])) : ?>
                                    <a href="<?php echo esc_url($check['link']); ?>" class="optimizr-link"><?php echo esc_html($check['link_label']); ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <h2 class="optimizr-card__title">Mises à jour</h2>
                </div>
                <div class="optimizr-card__body">
                    <dl class="optimizr-dl">
                        <dt>Version installée</dt>
                        <dd><?php echo esc_html(HPT_OPTIMIZR_VERSION); ?></dd>
                        <dt>Canal</dt>
                        <dd>stable</dd>
                    </dl>
                    <p class="optimizr-card__hint">Le bouton « Vérifier les mises à jour » en haut de page interroge GitHub immédiatement.</p>
                </div>
            </section>
        </aside>
    </div>
    <?php
}
