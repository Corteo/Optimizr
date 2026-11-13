<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modules activables depuis le tableau de bord.
 * Chaque module est rattaché à l'onglet qui contient ses réglages.
 */
function haptic_modules()
{
    return apply_filters('haptic_modules', [
        'cookies' => [
            'label' => __('Cookies & GDPR', 'optimizr'),
            'desc'  => __('Tarteaucitron banner, Google Consent Mode v2, Google Analytics and Google Tag Manager.', 'optimizr'),
            'icon'  => 'shield',
            'tab'   => 'cookies',
        ],
        'seo' => [
            'label' => __('SEO', 'optimizr'),
            'desc'  => __('Title, description and OpenGraph tags, with a SEO box in the editor.', 'optimizr'),
            'icon'  => 'search',
            'tab'   => 'seo',
        ],
        'thumbnails' => [
            'label' => __('Images', 'optimizr'),
            'desc'  => __('Content types that accept a cover image, and conversion of uploaded images to WebP or AVIF.', 'optimizr'),
            'icon'  => 'image',
            'tab'   => 'images',
        ],
        'cache' => [
            'label' => __('Cache', 'optimizr'),
            'desc'  => __('Page cache, browser cache and compression, cleared automatically when content changes.', 'optimizr'),
            'icon'  => 'zap',
            'tab'   => 'cache',
        ],
        'emails' => [
            'label' => __('Emails', 'optimizr'),
            'desc'  => __('Turn off the emails sent after automatic updates.', 'optimizr'),
            'icon'  => 'mail',
            'tab'   => 'advanced',
        ],
        'security' => [
            'label' => __('Security', 'optimizr'),
            'desc'  => __('Custom login URL, login attempt limits, neutral error messages, login alerts and comments turned off.', 'optimizr'),
            'icon'  => 'lock',
            'tab'   => 'security',
        ],
    ]);
}

/**
 * Un module est actif tant que le tableau de bord n'a jamais été enregistré.
 */
function haptic_is_module_active($slug)
{
    // Redirections d'Optimizr Pro antérieur à 1.2.0 : elles s'appuient sur le module retiré d'Optimizr 5.0 et ne doivent pas se charger
    if ($slug === 'redirects' && haptic_is_pro() && version_compare(HAPTIC_OPTIMIZR_PRO_VERSION, '1.2.0', '<')) {
        return false;
    }

    $modules = get_option('haptic_modules');

    if (!is_array($modules)) {
        return true;
    }

    return !empty($modules[$slug]);
}

/**
 * Onglets de la page d'options, filtrés selon les modules actifs.
 * 'modules' : modules dont l'un au moins doit être actif (et exister) ; 'intro' : texte sous le titre ;
 * 'render' : fonction d'affichage propre (sinon les sections du Settings API) ;
 * 'group' : rubrique du menu latéral (thème de haptic_dashboard_groups(), '' pour un lien en tête du menu).
 * Sans 'group', la rubrique est celle du premier module de l'onglet (haptic_admin_tab_group()).
 */
function haptic_admin_tabs()
{
    $tabs = [
        // Sans barre d'enregistrement : les modules s'activent directement (functions/dashboard.php)
        'dashboard' => ['label' => __('Dashboard', 'optimizr'), 'modules' => [], 'group' => '', 'render' => 'haptic_render_dashboard'],
        'cookies'   => ['label' => __('Cookies & GDPR', 'optimizr'), 'modules' => ['cookies'],
            'intro' => __('Visitor consent, with third-party services loaded only once allowed.', 'optimizr')],
        'seo'       => ['label' => __('SEO', 'optimizr'), 'modules' => ['seo'],
            'intro' => __('Default titles and descriptions, used for any content that has none.', 'optimizr')],
        // Types de contenu d'Optimizr Pro (module retiré d'Optimizr 7.0) : affiché seulement avec lui
        'content'   => ['label' => __('Content types', 'optimizr'), 'modules' => ['cpts'],
            'intro' => __('Ready-to-use Events type and custom post types.', 'optimizr')],
        'images'    => ['label' => __('Images', 'optimizr'), 'modules' => ['thumbnails'],
            'intro' => __('Content types that accept a cover image, and the format of uploaded images.', 'optimizr')],
        'cache'     => ['label' => __('Cache', 'optimizr'), 'modules' => ['cache'],
            'intro' => __('Faster pages for visitors, without changing anything for logged-in users.', 'optimizr')],
        'security'  => ['label' => __('Security', 'optimizr'), 'modules' => ['security'],
            'intro' => __('Protections you can enable one by one. None is active until you turn it on.', 'optimizr')],
        'advanced'  => ['label' => __('Emails', 'optimizr'), 'modules' => ['emails'],
            'intro' => __('Notifications sent by WordPress after automatic updates.', 'optimizr')],
        // Onglets sans barre d'enregistrement : leurs propres formulaires
        'audit'     => ['label' => _x('Audit', 'admin tab', 'optimizr'), 'modules' => [], 'group' => 'performance', 'render' => 'haptic_render_audit_tab'],
        'tools'     => ['label' => _x('Tools', 'admin tab', 'optimizr'), 'modules' => [], 'group' => 'content', 'render' => 'haptic_render_tools_tab'],
    ];

    // Onglets ajoutés par une extension (Optimizr Pro) : masqués eux aussi quand leur module est désactivé
    $tabs = apply_filters('haptic_admin_tabs', $tabs);
    $modules = haptic_modules();

    return array_filter($tabs, function ($tab) use ($modules) {
        if (empty($tab['modules'])) {
            return true;
        }
        foreach ($tab['modules'] as $module) {
            if (isset($modules[$module]) && haptic_is_module_active($module)) {
                return true;
            }
        }
        return false;
    });
}

/**
 * Anciennes adresses d'onglets, redirigées vers leur nouvelle place quand l'onglet n'existe plus :
 * avant 7.17.0, l'onglet « Contenus » portait les images à la une et les formats d'image.
 */
function haptic_admin_tab_aliases()
{
    return apply_filters('haptic_admin_tab_aliases', ['content' => 'images']);
}

/** Rubrique du menu latéral d'un onglet : 'group' s'il est donné, sinon le thème du tableau de bord de son premier module. */
function haptic_admin_tab_group(array $tab, array $groups)
{
    if (isset($tab['group'])) {
        return ($tab['group'] === '' || isset($groups[$tab['group']])) ? $tab['group'] : array_key_last($groups);
    }
    foreach ((array) ($tab['modules'] ?? []) as $module) {
        foreach ($groups as $key => $group) {
            if (in_array($module, (array) $group['modules'], true)) {
                return $key;
            }
        }
    }
    return array_key_last($groups);
}

/**
 * Menu latéral : liens de tête (tableau de bord, suivi) puis rubriques dans l'ordre du tableau de bord.
 * Dans une rubrique, les onglets suivent l'ordre des modules du thème ; ceux sans module viennent ensuite.
 *
 * @return array[] Liste de ['key' => thème ou '', 'group' => définition du thème ou null, 'tabs' => [slug => onglet]]
 */
function haptic_admin_nav($tabs)
{
    $groups = haptic_dashboard_groups();
    $head = [];
    $by_group = array_fill_keys(array_keys($groups), []);

    foreach ($tabs as $slug => $tab) {
        $key = haptic_admin_tab_group($tab, $groups);
        if ($key === '' || $key === null) {
            $head[$slug] = $tab;
        } else {
            $by_group[$key][$slug] = $tab;
        }
    }

    $sections = [];
    foreach ($head as $slug => $tab) {
        $sections[] = ['key' => '', 'group' => null, 'tabs' => [$slug => $tab]];
    }

    foreach ($by_group as $key => $group_tabs) {
        if (empty($group_tabs)) {
            continue;
        }
        $order = array_flip(array_values((array) $groups[$key]['modules']));
        $position = 0;
        $rank = [];
        foreach ($group_tabs as $slug => $tab) {
            $best = PHP_INT_MAX;
            foreach ((array) ($tab['modules'] ?? []) as $module) {
                if (isset($order[$module])) {
                    $best = min($best, $order[$module]);
                }
            }
            $rank[$slug] = [$best, $position++];
        }
        uksort($group_tabs, function ($a, $b) use ($rank) {
            return $rank[$a] <=> $rank[$b];
        });
        $sections[] = ['key' => $key, 'group' => $groups[$key], 'tabs' => $group_tabs];
    }

    return $sections;
}

/**
 * Insère des éléments dans un tableau associatif après une clé donnée (à la fin si elle est absente).
 * Utilisé par les extensions pour placer leurs modules, onglets ou étapes.
 */
function haptic_array_insert_after(array $array, $key, array $items)
{
    $position = array_search($key, array_keys($array), true);
    if ($position === false) {
        return $array + $items;
    }
    return array_slice($array, 0, $position + 1, true) + $items + array_slice($array, $position + 1, null, true);
}

/** Optimizr Pro est-il actif ? */
function haptic_is_pro()
{
    return defined('HAPTIC_OPTIMIZR_PRO_VERSION');
}

/**
 * Version minimale d'Optimizr Pro : la première qui contient le module Types de contenu, retiré d'Optimizr 7.0
 * (et le module Redirections, retiré d'Optimizr 5.0).
 */
define('HAPTIC_OPTIMIZR_PRO_MIN_VERSION', '1.3.0');

/** Optimizr Pro actif mais antérieur à HAPTIC_OPTIMIZR_PRO_MIN_VERSION ? */
function haptic_pro_is_outdated()
{
    return haptic_is_pro() && version_compare(HAPTIC_OPTIMIZR_PRO_VERSION, HAPTIC_OPTIMIZR_PRO_MIN_VERSION, '<');
}

// Optimizr Pro trop ancien : types de contenu (et redirections avant 1.2.0) conservés, mais inactifs jusqu'à sa mise à jour
add_action('admin_notices', function () {
    if (!haptic_pro_is_outdated() || !current_user_can('update_plugins')) {
        return;
    }
    $message = sprintf(
        /* translators: 1: required Optimizr Pro version, 2: installed Optimizr Pro version */
        __('Content types and redirects are now part of Optimizr Pro %1$s (version %2$s installed). Update Optimizr Pro to use them again: your content types, their content and your redirects are kept in the meantime.', 'optimizr'),
        HAPTIC_OPTIMIZR_PRO_MIN_VERSION,
        HAPTIC_OPTIMIZR_PRO_VERSION
    );
    echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
});

function haptic_current_tab()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- onglet affiché, lecture seule
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'dashboard';
    $tabs = haptic_admin_tabs();
    if (!array_key_exists($tab, $tabs)) {
        $aliases = haptic_admin_tab_aliases();
        $tab = isset($aliases[$tab]) ? $aliases[$tab] : 'dashboard';
    }
    return array_key_exists($tab, $tabs) ? $tab : 'dashboard';
}

/** Page Settings API d'un onglet (add_settings_section / add_settings_field). */
function haptic_settings_page($tab)
{
    return 'haptic-options-' . $tab;
}

/** Groupe Settings API d'un onglet (register_setting / settings_fields). */
function haptic_settings_group($tab)
{
    return 'haptic_group_' . $tab;
}

function haptic_admin_tab_url($tab)
{
    return admin_url('admin.php?page=haptic-options&tab=' . $tab);
}

/**
 * Interrupteur (case à cocher stylée) conforme à l'interface du plugin.
 */
function haptic_switch_input($name, $checked, $label, $id = '')
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
 * Balises autorisées à la sortie du HTML construit par le plugin (icônes SVG, interrupteurs, boutons) :
 * echo wp_kses(haptic_icon('check'), haptic_kses_ui()).
 */
function haptic_kses_ui()
{
    static $allowed = null;
    if ($allowed !== null) {
        return $allowed;
    }

    $common = ['class' => true, 'id' => true, 'role' => true, 'aria-hidden' => true, 'aria-label' => true, 'aria-controls' => true, 'aria-expanded' => true, 'data-*' => true];
    $shape  = ['d' => true, 'x' => true, 'y' => true, 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'cx' => true, 'cy' => true, 'r' => true, 'rx' => true, 'ry' => true, 'width' => true, 'height' => true, 'points' => true, 'fill' => true, 'fill-opacity' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-opacity' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-dasharray' => true, 'opacity' => true, 'transform' => true, 'class' => true];

    $allowed = [
        'svg'      => $common + ['width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'focusable' => true, 'xmlns' => true],
        'g'        => $shape,
        'path'     => $shape,
        'circle'   => $shape,
        'rect'     => $shape,
        'line'     => $shape,
        'polyline' => $shape,
        'polygon'  => $shape,
        'ellipse'  => $shape,
        'label'    => $common + ['for' => true],
        'input'    => $common + ['type' => true, 'name' => true, 'value' => true, 'checked' => true, 'disabled' => true],
        'span'     => $common,
        'button'   => $common + ['type' => true, 'disabled' => true],
        'select'   => $common + ['name' => true, 'disabled' => true],
        'option'   => ['class' => true, 'value' => true, 'selected' => true],
    ];
    return $allowed;
}

/**
 * Tracés des icônes au trait (24×24) utilisées dans l'interface, sans la balise <svg> (illustrations du tableau de bord).
 */
function haptic_icon_paths($name)
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
        'redirect' => '<path d="M15 14l5-5-5-5"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/>',
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15h6"/>',
        'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'pin'      => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
        'phone'    => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>',
        'zap'      => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
        'key'      => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
        'gauge'    => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
        'plus'     => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'close'    => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'cart'     => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2 2h3l2.7 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
        'share'    => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4"/><path d="m15.4 6.5-6.8 4"/>',
        'star'     => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'home'     => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'chart'    => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
    ];

    return $paths[$name] ?? '';
}

/**
 * Icônes au trait (24×24) utilisées dans l'interface.
 */
function haptic_icon($name, $size = 20)
{
    $paths = haptic_icon_paths($name);
    if ($paths === '') {
        return '';
    }

    return sprintf(
        '<svg class="optimizr-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
        (int) $size,
        $paths
    );
}

add_action('admin_menu', 'haptic_options_admin_menu');

// Adresses des versions 2.x (favoris, liens enregistrés) : page renommée en 3.0. Sur admin_menu, avant que
// WordPress refuse l'accès à une page inconnue.
add_action('admin_menu', function () {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (strpos($page, 'hpt-') === 0) {
        wp_safe_redirect(add_query_arg('page', 'haptic-' . substr($page, 4)));
        exit;
    }
}, 0);

/** Icône du menu : SVG monochrome en data URI, recoloré par WordPress selon le jeu de couleurs de l'administration. */
function haptic_menu_icon()
{
    $svg = file_get_contents(HAPTIC_OPTIMIZR_PATH . 'images/optimizr-menu.svg'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du plugin
    return $svg ? 'data:image/svg+xml;base64,' . base64_encode($svg) : 'dashicons-admin-generic'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- format attendu par add_menu_page()
}

function haptic_options_admin_menu()
{
    add_menu_page(
        __('Settings - Optimizr', 'optimizr'),
        'Optimizr',
        'manage_options',
        'haptic-options',
        'haptic_options_settings_page',
        haptic_menu_icon(),
        81
    );
}

// Réglages du tableau de bord : liste des modules actifs
add_action('admin_init', function () {
    register_setting(haptic_settings_group('dashboard'), 'haptic_modules', [
        'type'              => 'array',
        'sanitize_callback' => 'haptic_sanitize_modules',
    ]);
});

function haptic_sanitize_modules($input)
{
    $input = is_array($input) ? $input : [];
    // Modules d'une extension désactivée (Optimizr Pro) : leur état est conservé pour sa réactivation
    $saved = get_option('haptic_modules');
    $clean = is_array($saved) ? array_map(function ($value) {
        return empty($value) ? 0 : 1;
    }, $saved) : [];
    // Marqueur toujours envoyé : distingue « tout désactivé » de « jamais enregistré »
    $clean['_saved'] = 1;

    foreach (array_keys(haptic_modules()) as $slug) {
        $clean[$slug] = empty($input[$slug]) ? 0 : 1;
    }

    return $clean;
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== HAPTIC_OPTIMIZR_ADMIN_HOOK) {
        return;
    }

    $css = HAPTIC_OPTIMIZR_PATH . 'css/optimizr-admin.css';
    $js  = HAPTIC_OPTIMIZR_PATH . 'js/optimizr-admin.js';

    wp_enqueue_style('optimizr-admin', HAPTIC_OPTIMIZR_URL . 'css/optimizr-admin.css', [], filemtime($css));
    wp_enqueue_script('optimizr-admin', HAPTIC_OPTIMIZR_URL . 'js/optimizr-admin.js', ['wp-i18n'], filemtime($js), true);
    wp_set_script_translations('optimizr-admin', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
});

function haptic_options_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $tabs    = haptic_admin_tabs();
    $current = haptic_current_tab();
    ?>
    <div class="wrap optimizr">
        <header class="optimizr-header">
            <div class="optimizr-header__top">
                <img class="optimizr-logo" src="<?php echo esc_url(HAPTIC_OPTIMIZR_URL . 'images/optimizr-icon.svg'); ?>" width="36" height="36" alt="" />
                <h1 class="optimizr-header__title">Optimizr</h1>
                <span class="optimizr-badge">v<?php echo esc_html(HAPTIC_OPTIMIZR_VERSION); ?></span>
                <div class="optimizr-header__actions">
                    <a href="<?php echo esc_url(haptic_onboarding_url()); ?>" class="optimizr-btn optimizr-btn--ghost"><?php echo wp_kses(haptic_icon('sparkles', 16), haptic_kses_ui()); ?><?php esc_html_e('Setup wizard', 'optimizr'); ?></a>
                    <?php do_action('haptic_header_actions'); ?>
                </div>
            </div>
        </header>

        <hr class="wp-header-end">

        <?php settings_errors(); ?>

        <div class="optimizr-shell">
            <nav class="optimizr-nav" aria-label="<?php esc_attr_e('Optimizr sections', 'optimizr'); ?>" data-optimizr-tabs-nav>
                <?php haptic_render_tabs_nav($tabs, $current); ?>
            </nav>

            <div class="optimizr-shell__content">
            <?php if (!empty($tabs[$current]['render']) && is_callable($tabs[$current]['render'])) : ?>
                <main class="optimizr-main">
                    <?php call_user_func($tabs[$current]['render']); ?>
                </main>
            <?php else : ?>

            <form method="post" action="options.php" class="optimizr-form" data-optimizr-form>
                <?php settings_fields(haptic_settings_group($current)); ?>

                <main class="optimizr-main">
                    <?php haptic_render_settings_tab($current, $tabs[$current]); ?>
                </main>

                <div class="optimizr-savebar" data-optimizr-savebar>
                    <span class="optimizr-savebar__dot" aria-hidden="true"></span>
                    <span class="optimizr-savebar__text" data-optimizr-savebar-text><?php esc_html_e('All changes saved', 'optimizr'); ?></span>
                    <button type="reset" class="optimizr-btn optimizr-btn--ghost" data-optimizr-reset><?php esc_html_e('Cancel', 'optimizr'); ?></button>
                    <button type="submit" class="optimizr-btn optimizr-btn--primary"><?php esc_html_e('Save changes', 'optimizr'); ?></button>
                </div>
            </form>
            <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Menu latéral des onglets (aussi renvoyé après l'activation d'un module depuis le tableau de bord) :
 * liste déroulante sur petit écran, liens groupés par rubrique ailleurs.
 */
function haptic_render_tabs_nav($tabs, $current)
{
    $sections = haptic_admin_nav($tabs);
    $head_icons = apply_filters('haptic_admin_nav_icons', ['dashboard' => 'home', 'tracking' => 'chart']);
    ?>
    <label class="screen-reader-text" for="optimizr-nav-select"><?php esc_html_e('Section', 'optimizr'); ?></label>
    <select id="optimizr-nav-select" class="optimizr-nav__select" data-optimizr-nav-select>
        <?php foreach ($sections as $section) : ?>
            <?php if ($section['group']) : ?><optgroup label="<?php echo esc_attr($section['group']['label']); ?>"><?php endif; ?>
            <?php foreach ($section['tabs'] as $slug => $tab) : ?>
                <option value="<?php echo esc_url(haptic_admin_tab_url($slug)); ?>" <?php selected($slug, $current); ?>><?php echo esc_html(wp_strip_all_tags($tab['label'])); ?></option>
            <?php endforeach; ?>
            <?php if ($section['group']) : ?></optgroup><?php endif; ?>
        <?php endforeach; ?>
    </select>

    <div class="optimizr-nav__sections">
        <?php foreach ($sections as $section) :
            $is_current = isset($section['tabs'][$current]);
            ?>
            <?php if ($section['group']) : ?>
                <div class="optimizr-nav__group<?php echo $is_current ? ' is-current' : ''; ?>" style="<?php echo esc_attr(haptic_dashboard_group_style($section['group'])); ?>">
                    <p class="optimizr-nav__heading"><span class="optimizr-nav__icon" aria-hidden="true"><?php echo wp_kses(haptic_icon($section['group']['icon'] ?? 'layers', 12), haptic_kses_ui()); ?></span><?php echo esc_html($section['group']['label']); ?></p>
                    <ul class="optimizr-nav__list">
                        <?php foreach ($section['tabs'] as $slug => $tab) : ?>
                            <li><a class="optimizr-nav__link" href="<?php echo esc_url(haptic_admin_tab_url($slug)); ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html(wp_strip_all_tags($tab['label'])); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else : ?>
                <?php foreach ($section['tabs'] as $slug => $tab) : ?>
                    <a class="optimizr-nav__link optimizr-nav__link--top" href="<?php echo esc_url(haptic_admin_tab_url($slug)); ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>><?php echo wp_kses(haptic_icon($head_icons[$slug] ?? 'layers', 16), haptic_kses_ui()); ?><?php echo esc_html(wp_strip_all_tags($tab['label'])); ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Onglet de réglages : sections du Settings API en colonne principale,
 * panneau latéral optionnel via l'action haptic_admin_aside_{onglet}.
 */
function haptic_render_settings_tab($slug, $tab)
{
    $has_aside = has_action("haptic_admin_aside_{$slug}");
    ?>
    <div class="optimizr-page-head">
        <h2 class="optimizr-page-head__title"><?php echo esc_html($tab['label']); ?></h2>
        <?php if (!empty($tab['intro'])) : ?>
            <p class="optimizr-page-head__intro"><?php echo esc_html($tab['intro']); ?></p>
        <?php endif; ?>
    </div>
    <div class="optimizr-layout<?php echo $has_aside ? ' optimizr-layout--aside' : ''; ?>">
        <div class="optimizr-layout__main">
            <?php haptic_do_settings_sections(haptic_settings_page($slug)); ?>
        </div>
        <?php if ($has_aside) : ?>
            <aside class="optimizr-layout__aside">
                <?php do_action("haptic_admin_aside_{$slug}"); ?>
            </aside>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Vérifications rapides affichées sur le tableau de bord (aucune requête HTTP).
 *
 * @return array[] Liste de ['status' => ok|warn, 'label' => ..., 'link' => ?, 'link_label' => ?, 'module' => ?, 'group' => ?]
 *                 'module' ou 'group' rattachent la vérification à un thème du tableau de bord (haptic_dashboard_groups()),
 *                 sinon le thème est déduit de l'onglet du lien.
 */
function haptic_site_health_checks()
{
    $checks = [];

    if (!get_option('blog_public')) {
        $checks[] = [
            'status' => 'warn',
            'group' => 'visibility',
            'label' => __('Search engines are discouraged from indexing the site', 'optimizr'),
            'link' => admin_url('options-reading.php'),
            'link_label' => _x('Adjust', 'site health check link', 'optimizr'),
        ];
    }

    if (haptic_is_module_active('cookies')) {
        $tac = get_option('haptic_option_enable_tarteaucitron');
        $checks[] = [
            'status' => $tac ? 'ok' : 'warn',
            'module' => 'cookies',
            'label' => $tac ? __('Cookie banner active', 'optimizr') : __('Cookie banner disabled', 'optimizr'),
            'link' => $tac ? '' : haptic_admin_tab_url('cookies'),
            'link_label' => _x('Enable', 'site health check link', 'optimizr'),
        ];
    }

    return apply_filters('haptic_site_health_checks', $checks);
}

/**
 * Statut affiché sur la ligne d'un module du tableau de bord.
 *
 * @return array ['status' => ok|warn|off, 'label' => ...]
 */
function haptic_module_status($slug)
{
    if (!haptic_is_module_active($slug)) {
        return ['status' => 'off', 'label' => _x('Disabled', 'module status', 'optimizr')];
    }

    if ($slug === 'cookies' && !get_option('haptic_option_enable_tarteaucitron')) {
        return ['status' => 'warn', 'label' => __('Banner disabled', 'optimizr')];
    }

    return apply_filters('haptic_module_status', ['status' => 'ok', 'label' => _x('Active', 'module status', 'optimizr')], $slug);
}
