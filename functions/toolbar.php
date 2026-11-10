<?php

/**
 * Barre flottante : sur le site, remplace la barre d'administration de WordPress par un module discret qui ne garde
 * que l'essentiel (modifier la page, créer un contenu, SEO et cache de la page, éléments à traiter, liens d'Optimizr).
 *
 * Désactivée par défaut (option `haptic_toolbar`), réglée dans l'onglet Outils : design (pastille, dock ou fiche
 * de la page), côté de l'écran et rôles concernés. L'administration garde la barre de WordPress et son menu Optimizr
 * (functions/admin-bar.php). Un compte qui a décoché « Afficher la barre d'outils » dans son profil n'a ni l'une ni l'autre.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Designs proposés : clé => libellé. */
function haptic_toolbar_styles()
{
    return [
        'bubble' => __('Bubble', 'optimizr'),
        'dock'   => __('Dock', 'optimizr'),
        'sheet'  => __('Page panel', 'optimizr'),
    ];
}

/** Rôles concernés : clé => libellé. */
function haptic_toolbar_role_choices()
{
    return [
        'all'     => __('Everyone who sees the toolbar', 'optimizr'),
        'editors' => __('Editors and administrators', 'optimizr'),
        'admins'  => __('Administrators only', 'optimizr'),
    ];
}

/** Réglages enregistrés, avec leurs valeurs par défaut. */
function haptic_toolbar_settings()
{
    $style = get_option('haptic_toolbar_style', 'bubble');
    $side  = get_option('haptic_toolbar_side', 'right');
    $roles = get_option('haptic_toolbar_roles', 'all');

    return [
        'enabled' => (bool) get_option('haptic_toolbar', 0),
        'style'   => array_key_exists($style, haptic_toolbar_styles()) ? $style : 'bubble',
        'side'    => in_array($side, ['left', 'right'], true) ? $side : 'right',
        'roles'   => array_key_exists($roles, haptic_toolbar_role_choices()) ? $roles : 'all',
    ];
}

/** Le compte connecté est-il concerné par la barre flottante ? */
function haptic_toolbar_user_matches($roles)
{
    if ($roles === 'admins') {
        return current_user_can('manage_options');
    }
    if ($roles === 'editors') {
        return current_user_can('edit_others_posts');
    }
    return true;
}

/**
 * La barre flottante s'affiche-t-elle sur la page demandée ? Sur le site seulement, pour un compte connecté du rôle
 * choisi qui n'a pas décoché « Afficher la barre d'outils » dans son profil.
 *
 * Ne dépend pas du filtre `show_admin_bar` : un thème ou une extension qui masque la barre de WordPress
 * (add_filter('show_admin_bar', '__return_false'), show_admin_bar(false)) n'empêche pas la barre flottante.
 */
function haptic_toolbar_should_show()
{
    if (is_admin() || !is_user_logged_in() || wp_doing_ajax() || wp_is_json_request() || is_customize_preview()) {
        return false;
    }
    if (did_action('wp') && is_embed()) {
        return false;
    }
    $settings = haptic_toolbar_settings();
    if (!$settings['enabled'] || !haptic_toolbar_user_matches($settings['roles'])) {
        return false;
    }
    // Préférence du profil (la même que celle de la barre de WordPress)
    if (get_user_option('show_admin_bar_front') === 'false') {
        return false;
    }
    return (bool) apply_filters('haptic_toolbar_show', true);
}

// Barre de WordPress masquée sur le site quand la barre flottante la remplace (en dernier, après les autres filtres)
add_filter('show_admin_bar', function ($show) {
    return $show && haptic_toolbar_should_show() ? false : $show;
}, PHP_INT_MAX);

add_action('wp_enqueue_scripts', function () {
    if (!haptic_toolbar_should_show()) {
        return;
    }
    wp_enqueue_style('optimizr-toolbar', HAPTIC_OPTIMIZR_URL . 'css/optimizr-toolbar.css', [], HAPTIC_OPTIMIZR_VERSION);
    wp_enqueue_script('optimizr-toolbar', HAPTIC_OPTIMIZR_URL . 'js/optimizr-toolbar.js', [], HAPTIC_OPTIMIZR_VERSION, true);
});

add_action('wp_footer', function () {
    if (!haptic_toolbar_should_show()) {
        return;
    }
    $settings = haptic_toolbar_settings();
    $ctx = haptic_toolbar_context();
    echo wp_kses(haptic_toolbar_html($settings, $ctx), haptic_toolbar_kses());
}, 5); // avant les scripts du pied de page (priorité 20), qui cherchent le module dès leur chargement

/* ---------- Contenu ---------- */

/**
 * Ce que le module affiche pour la page en cours : chaque entrée n'est remplie que si le module d'Optimizr
 * correspondant est actif et si le compte a le droit de s'en servir.
 */
function haptic_toolbar_context()
{
    $object = get_queried_object();
    // Adresse de la page affichée, comme pour « Vider cette page » de la barre de WordPress (functions/cache.php)
    $url = home_url(trailingslashit($GLOBALS['wp']->request ?? ''));

    $ctx = [
        'title'       => '',
        'meta'        => '',
        'edit'        => null,
        'new'         => [],
        'seo'         => null,
        'site_hidden' => !get_option('blog_public'),
        'cache'       => null,
        'audit'       => '',
        'comments'    => null,
        'updates'     => null,
        'maintenance' => '',
        'admin'       => current_user_can('read') ? admin_url() : '',
        'optimizr'    => [],
        'others'      => [],
        'user'        => wp_get_current_user(),
        'profile'     => get_edit_profile_url(),
        'logout'      => wp_logout_url($url),
        'url'         => $url,
    ];

    // Page affichée et lien de modification
    if (is_singular() && $object instanceof WP_Post) {
        $type   = get_post_type_object($object->post_type);
        $status = get_post_status_object(get_post_status($object));
        $ctx['title'] = get_the_title($object) !== '' ? get_the_title($object) : __('(no title)', 'optimizr');
        $ctx['meta']  = trim(($type ? $type->labels->singular_name : '') . ($status ? ' · ' . $status->label : ''), ' ·');
        if (current_user_can('edit_post', $object->ID)) {
            $ctx['edit'] = ['url' => get_edit_post_link($object->ID, 'raw'), 'label' => $type && !empty($type->labels->edit_item) ? $type->labels->edit_item : __('Edit', 'optimizr')];
        }
    } elseif (is_home() && !is_front_page() && get_option('page_for_posts')) {
        $page_id = (int) get_option('page_for_posts');
        $ctx['title'] = get_the_title($page_id);
        $ctx['meta']  = __('Posts page', 'optimizr');
        if (current_user_can('edit_post', $page_id)) {
            $ctx['edit'] = ['url' => get_edit_post_link($page_id, 'raw'), 'label' => __('Edit page', 'optimizr')];
        }
    } elseif ((is_category() || is_tag() || is_tax()) && $object instanceof WP_Term) {
        $tax = get_taxonomy($object->taxonomy);
        $ctx['title'] = $object->name;
        $ctx['meta']  = $tax ? $tax->labels->singular_name : '';
        if ($tax && current_user_can('edit_term', $object->term_id)) {
            $ctx['edit'] = ['url' => get_edit_term_link($object->term_id, $object->taxonomy), 'label' => $tax->labels->edit_item];
        }
    } elseif (is_author() && $object instanceof WP_User) {
        $ctx['title'] = $object->display_name;
        $ctx['meta']  = __('Author', 'optimizr');
        if (current_user_can('edit_user', $object->ID)) {
            $ctx['edit'] = ['url' => get_edit_user_link($object->ID), 'label' => __('Edit user', 'optimizr')];
        }
    } else {
        $ctx['title'] = is_front_page() ? get_bloginfo('name') : wp_strip_all_tags(wp_get_document_title());
        $ctx['meta']  = is_front_page() ? __('Home page', 'optimizr') : (is_search() ? __('Search results', 'optimizr') : (is_404() ? __('Page not found', 'optimizr') : __('Archive', 'optimizr')));
    }
    // Autres pages d'un thème de blocs (archives, 404, recherche) : leur modèle se modifie dans l'éditeur du site
    if (!$ctx['edit'] && wp_is_block_theme() && current_user_can('edit_theme_options')) {
        $ctx['edit'] = ['url' => admin_url('site-editor.php'), 'label' => __('Edit template', 'optimizr')];
    }

    // Créer : les mêmes types de contenu que le menu « Créer » de WordPress
    foreach (get_post_types(['show_in_admin_bar' => true], 'objects') as $type) {
        if (current_user_can($type->cap->create_posts)) {
            $ctx['new'][] = ['label' => $type->labels->name_admin_bar, 'url' => admin_url($type->name === 'post' ? 'post-new.php' : 'post-new.php?post_type=' . $type->name)];
        }
    }

    // SEO de la page (module SEO)
    if (is_singular() && $object instanceof WP_Post && function_exists('haptic_seo_resolve') && function_exists('haptic_seo_post_types')
        && in_array($object->post_type, haptic_seo_post_types(), true) && current_user_can('edit_post', $object->ID)) {
        $seo = haptic_seo_resolve($object->ID);
        $ctx['seo'] = [
            'title'       => wp_strip_all_tags((string) $seo['title']),
            'description' => wp_strip_all_tags((string) $seo['description']),
            'noindex'     => (bool) get_post_meta($object->ID, '_haptic_noindex', true),
            'url'         => get_edit_post_link($object->ID, 'raw') . '#haptic_seo_meta',
        ];
    }

    // Cache (module Cache)
    if (function_exists('haptic_cache_page_active') && haptic_cache_page_active() && haptic_cache_can_purge()) {
        $ctx['cache'] = ['page' => haptic_cache_purge_url($url), 'all' => haptic_cache_purge_url()];
    }

    // Audit Lighthouse de la page (onglet Audit, clé Google renseignée)
    if (current_user_can('manage_options') && function_exists('haptic_audit_api_key') && haptic_audit_api_key() !== '' && !is_404()) {
        $ctx['audit'] = add_query_arg('audit_url', rawurlencode($url), haptic_admin_tab_url('audit'));
    }

    // À traiter
    if (current_user_can('moderate_comments')) {
        $pending = (int) wp_count_comments()->moderated;
        if ($pending > 0) {
            $ctx['comments'] = ['count' => $pending, 'url' => admin_url('edit-comments.php?comment_status=moderated')];
        }
    }
    $updates = wp_get_update_data();
    if (!empty($updates['counts']['total'])) {
        $ctx['updates'] = ['count' => (int) $updates['counts']['total'], 'url' => admin_url('update-core.php')];
    }

    // Maintenance d'Optimizr Pro
    if (function_exists('haptic_maintenance_active') && haptic_maintenance_active() && current_user_can('manage_options')) {
        $ctx['maintenance'] = haptic_admin_tab_url('pages');
    }

    // Onglets d'Optimizr (comme le menu de la barre d'administration)
    if (current_user_can('manage_options')) {
        foreach (haptic_admin_tabs() as $slug => $tab) {
            if (!empty($tab['label'])) {
                $ctx['optimizr'][] = ['label' => wp_strip_all_tags($tab['label']), 'url' => haptic_admin_tab_url($slug)];
            }
        }
    }

    $ctx['others'] = haptic_toolbar_other_nodes();

    return apply_filters('haptic_toolbar_context', $ctx);
}

/**
 * Raccourcis que les autres extensions ajoutent à la barre de WordPress (WooCommerce, formulaires…), recueillis
 * dans une barre à part : seuls les rappels des extensions s'exécutent, pas les menus de WordPress lui-même.
 * Filtre `haptic_toolbar_other_nodes` (false pour ne rien recueillir).
 */
function haptic_toolbar_other_nodes()
{
    if (!apply_filters('haptic_toolbar_collect_nodes', true)) {
        return [];
    }
    if (!class_exists('WP_Admin_Bar')) {
        require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
    }

    $items = [];
    try {
        $bar = new WP_Admin_Bar();
        ob_start();
        do_action('admin_bar_menu', $bar);
        ob_end_clean();
        $nodes = (array) $bar->get_nodes();
    } catch (Throwable $e) {
        return [];
    }

    // Nœuds de WordPress (créés par ses propres menus) et ceux qu'Optimizr affiche déjà dans le module
    $core = ['', 'root', 'top-secondary', 'wp-logo', 'site-name', 'my-account', 'user-actions', 'new-content', 'comments', 'updates', 'edit', 'search', 'customize', 'appearance', 'menu-toggle'];
    foreach ($nodes as $id => $node) {
        if (strpos($id, 'optimizr') === 0 || strpos($id, 'haptic') === 0 || in_array($id, $core, true) || !empty($node->group)) {
            continue;
        }
        $top = in_array((string) $node->parent, $core, true) || !isset($nodes[$node->parent]);
        if (!$top) {
            continue;
        }
        $label = trim(wp_strip_all_tags((string) $node->title));
        $url = (string) $node->href;
        if ($url === '') {
            // Menu sans lien : son premier sous-élément qui en a un
            foreach ($nodes as $child) {
                if ($child->parent === $id && !empty($child->href)) {
                    $url = (string) $child->href;
                    break;
                }
            }
        }
        if ($label === '' || $url === '') {
            continue;
        }
        $items[] = ['label' => $label, 'url' => $url];
    }

    return array_slice(apply_filters('haptic_toolbar_other_nodes', $items), 0, 12);
}

/* ---------- Affichage ---------- */

/** Icônes au trait (24×24) du module. */
function haptic_toolbar_icon($name)
{
    $paths = [
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'plus'     => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'zap'      => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'gauge'    => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
        'comment'  => '<path d="M5 5h14v10h-9l-5 4z"/>',
        'refresh'  => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
        'grid'     => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
        'eye-off'  => '<path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6z"/><circle cx="12" cy="12" r="2.5"/><path d="m4 4 16 16"/>',
        'logout'   => '<path d="M14 4h5v16h-5"/><path d="m10 8-4 4 4 4"/><path d="M6 12h10"/>',
        'lock'     => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'close'    => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'more'     => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
        'chevron'  => '<path d="m9 6 6 6-6 6"/>',
        'alert'    => '<path d="M12 8v5"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="otb-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/** Symbole d'Optimizr, coloré par la couleur d'accent du module. */
function haptic_toolbar_logo()
{
    return '<svg class="otb-logo" viewBox="84 74 356 356" aria-hidden="true" focusable="false"><path d="M342.3 246.2A116 116 0 1 1 215.9 167.1" fill="none" stroke="currentColor" stroke-width="60" stroke-linecap="round"/><path d="M374 78C384.54 129.46 384.54 129.46 436 140C384.54 150.54 384.54 150.54 374 202C363.46 150.54 363.46 150.54 312 140C363.46 129.46 363.46 129.46 374 78Z" fill="currentColor"/></svg>';
}

/** Lien en ligne du module : icône, libellé, complément à droite. */
function haptic_toolbar_row($url, $icon, $label, $extra = '', $class = '')
{
    return '<a class="otb-row' . ($class ? ' ' . esc_attr($class) : '') . '" href="' . esc_url($url) . '">' . haptic_toolbar_icon($icon)
        . '<span class="otb-row__label">' . esc_html($label) . '</span>' . ($extra !== '' ? '<span class="otb-row__extra">' . $extra . '</span>' : '') . '</a>';
}

function haptic_toolbar_badge($count)
{
    return '<span class="otb-badge">' . esc_html(number_format_i18n($count)) . '</span>';
}

/** Pastille d'état SEO : indexée, non indexée (noindex) ou site masqué aux moteurs. */
function haptic_toolbar_seo_pill(array $ctx)
{
    if ($ctx['site_hidden']) {
        return '<span class="otb-pill otb-pill--bad">' . esc_html__('Site hidden', 'optimizr') . '</span>';
    }
    if (!empty($ctx['seo']['noindex'])) {
        return '<span class="otb-pill otb-pill--bad">' . esc_html__('Not indexed', 'optimizr') . '</span>';
    }
    return '<span class="otb-pill otb-pill--ok">' . esc_html__('Indexed', 'optimizr') . '</span>';
}

/** Point d'état du bouton du module : rouge si la page ou le site n'est pas visible (maintenance, noindex). */
function haptic_toolbar_alert(array $ctx)
{
    return $ctx['maintenance'] !== '' || $ctx['site_hidden'] || !empty($ctx['seo']['noindex']);
}

function haptic_toolbar_maintenance(array $ctx)
{
    if ($ctx['maintenance'] === '') {
        return '';
    }
    return '<a class="otb-maint" href="' . esc_url($ctx['maintenance']) . '">' . haptic_toolbar_icon('lock') . '<span>' . esc_html__('Site in maintenance', 'optimizr') . '</span></a>';
}

/** Bouton « Modifier » et menu « Créer ». */
function haptic_toolbar_actions(array $ctx, $compact = false)
{
    $html = '<div class="otb-actions">';
    if ($ctx['edit']) {
        $html .= '<a class="otb-primary" href="' . esc_url($ctx['edit']['url']) . '">' . haptic_toolbar_icon('edit') . '<span>' . esc_html($ctx['edit']['label']) . '</span></a>';
    }
    if ($ctx['new']) {
        $html .= '<details class="otb-create"><summary class="otb-secondary" aria-label="' . esc_attr__('New', 'optimizr') . '">' . haptic_toolbar_icon('plus')
            . ($compact ? '' : '<span>' . esc_html__('New', 'optimizr') . '</span>') . '</summary><div class="otb-create__menu">';
        foreach ($ctx['new'] as $item) {
            $html .= '<a class="otb-row" href="' . esc_url($item['url']) . '">' . haptic_toolbar_icon('plus') . '<span class="otb-row__label">' . esc_html($item['label']) . '</span></a>';
        }
        $html .= '</div></details>';
    }
    return $html . '</div>';
}

/** Lignes « Cette page » : SEO, cache, audit. */
function haptic_toolbar_page_rows(array $ctx)
{
    $html = '';
    if ($ctx['seo']) {
        $html .= haptic_toolbar_row($ctx['seo']['url'], 'search', __('SEO', 'optimizr'), haptic_toolbar_seo_pill($ctx));
    } elseif ($ctx['site_hidden'] && current_user_can('manage_options')) {
        $html .= haptic_toolbar_row(admin_url('options-reading.php'), 'alert', __('Search engines', 'optimizr'), haptic_toolbar_seo_pill($ctx));
    }
    if ($ctx['cache']) {
        $html .= '<div class="otb-row otb-row--static">' . haptic_toolbar_icon('zap') . '<span class="otb-row__label">' . esc_html__('Cache', 'optimizr') . '</span><span class="otb-row__extra">'
            . '<a class="otb-mini" href="' . esc_url($ctx['cache']['page']) . '">' . esc_html__('This page', 'optimizr') . '</a>'
            . '<a class="otb-mini" href="' . esc_url($ctx['cache']['all']) . '">' . esc_html__('All', 'optimizr') . '</a></span></div>';
    }
    if ($ctx['audit'] !== '') {
        $html .= haptic_toolbar_row($ctx['audit'], 'gauge', __('Audit this page', 'optimizr'));
    }
    return $html === '' ? '' : '<div class="otb-label">' . esc_html__('This page', 'optimizr') . '</div>' . $html;
}

/** Lignes « À traiter » : commentaires et mises à jour, seulement s'il y en a. */
function haptic_toolbar_inbox_rows(array $ctx)
{
    $html = '';
    if ($ctx['comments']) {
        $html .= haptic_toolbar_row($ctx['comments']['url'], 'comment', __('Comments', 'optimizr'), haptic_toolbar_badge($ctx['comments']['count']));
    }
    if ($ctx['updates']) {
        $html .= haptic_toolbar_row($ctx['updates']['url'], 'refresh', __('Updates', 'optimizr'), haptic_toolbar_badge($ctx['updates']['count']));
    }
    return $html;
}

/** Sous-menu repliable (onglets d'Optimizr, autres extensions). */
function haptic_toolbar_submenu($icon_html, $label, array $items)
{
    if (!$items) {
        return '';
    }
    $html = '<details class="otb-sub"><summary class="otb-row">' . $icon_html . '<span class="otb-row__label">' . esc_html($label) . '</span><span class="otb-row__extra">' . haptic_toolbar_icon('chevron') . '</span></summary><div class="otb-sub__items">';
    foreach ($items as $item) {
        $html .= '<a class="otb-row otb-row--sub" href="' . esc_url($item['url']) . '"><span class="otb-row__label">' . esc_html($item['label']) . '</span></a>';
    }
    return $html . '</div></details>';
}

/** Liens de navigation : administration, Optimizr, autres extensions. */
function haptic_toolbar_nav_rows(array $ctx)
{
    $html = '';
    if ($ctx['admin'] !== '') {
        $html .= haptic_toolbar_row($ctx['admin'], 'grid', __('Dashboard', 'optimizr'));
    }
    if ($ctx['optimizr']) {
        $html .= haptic_toolbar_submenu(haptic_toolbar_logo(), 'Optimizr', $ctx['optimizr']);
    }
    return $html . haptic_toolbar_submenu(haptic_toolbar_icon('more'), __('Other plugins', 'optimizr'), $ctx['others']);
}

/** Pied : compte, voir comme un visiteur, déconnexion. */
function haptic_toolbar_account(array $ctx, $with_hide = true)
{
    $user = $ctx['user'];
    $name = $user && $user->exists() ? $user->display_name : '';
    $html = '<div class="otb-foot"><a class="otb-who" href="' . esc_url($ctx['profile']) . '">' . haptic_toolbar_avatar($ctx) . '<span>' . esc_html($name) . '</span></a>';
    if ($with_hide) {
        $html .= '<button type="button" class="otb-icon-btn" data-otb-hide aria-label="' . esc_attr__('View as a visitor', 'optimizr') . '" title="' . esc_attr__('View as a visitor (Esc to bring it back)', 'optimizr') . '">' . haptic_toolbar_icon('eye-off') . '</button>';
    }
    return $html . '<a class="otb-icon-btn" href="' . esc_url($ctx['logout']) . '" aria-label="' . esc_attr__('Log out', 'optimizr') . '" title="' . esc_attr__('Log out', 'optimizr') . '">' . haptic_toolbar_icon('logout') . '</a></div>';
}

/** Initiale du compte (pas d'appel à Gravatar). */
function haptic_toolbar_avatar(array $ctx)
{
    $user = $ctx['user'];
    $name = $user && $user->exists() ? $user->display_name : '?';
    $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($name, 0, 1)) : strtoupper(substr($name, 0, 1));
    return '<span class="otb-avatar" aria-hidden="true">' . esc_html($initial) . '</span>';
}

/** En-tête : titre et nature de la page affichée. */
function haptic_toolbar_page_head(array $ctx)
{
    return '<div class="otb-page"><span class="otb-page__title">' . esc_html($ctx['title']) . '</span>'
        . ($ctx['meta'] !== '' ? '<span class="otb-page__meta">' . esc_html($ctx['meta']) . '</span>' : '') . '</div>';
}

/** Aperçu du résultat Google (fiche de la page). */
function haptic_toolbar_serp(array $ctx)
{
    if (!$ctx['seo']) {
        return '';
    }
    $display = preg_replace('#^https?://#', '', untrailingslashit($ctx['url']));
    $display = str_replace('/', ' › ', $display);
    return '<a class="otb-serp" href="' . esc_url($ctx['seo']['url']) . '"><span class="otb-serp__url">' . esc_html($display) . '</span>'
        . '<span class="otb-serp__title">' . esc_html($ctx['seo']['title']) . '</span>'
        . '<span class="otb-serp__desc">' . esc_html($ctx['seo']['description']) . '</span></a>';
}

function haptic_toolbar_html(array $settings, array $ctx)
{
    $style = $settings['style'];
    $classes = 'otb otb--' . $style . ' otb--' . $settings['side'];
    $state = haptic_toolbar_alert($ctx) ? '<span class="otb-state otb-state--bad"></span>' : '';

    $html = '<div id="optimizr-toolbar" class="' . esc_attr($classes) . '" data-otb role="region" aria-label="' . esc_attr__('Optimizr toolbar', 'optimizr') . '">';

    if ($style === 'dock') {
        $html .= haptic_toolbar_dock($ctx);
    } elseif ($style === 'sheet') {
        $html .= '<button type="button" class="otb-tab otb-glass" data-otb-toggle aria-expanded="false" aria-controls="otb-panel" aria-label="' . esc_attr__('Open the page panel', 'optimizr') . '">' . haptic_toolbar_logo() . $state . '</button>'
            . '<div class="otb-panel otb-glass" id="otb-panel" hidden>'
            . '<div class="otb-top">' . haptic_toolbar_logo() . '<strong>Optimizr</strong>'
            . '<button type="button" class="otb-icon-btn" data-otb-hide aria-label="' . esc_attr__('View as a visitor', 'optimizr') . '" title="' . esc_attr__('View as a visitor (Esc to bring it back)', 'optimizr') . '">' . haptic_toolbar_icon('eye-off') . '</button>'
            . '<button type="button" class="otb-icon-btn" data-otb-toggle aria-label="' . esc_attr__('Close', 'optimizr') . '">' . haptic_toolbar_icon('close') . '</button></div>'
            . haptic_toolbar_maintenance($ctx)
            . haptic_toolbar_page_head($ctx)
            . haptic_toolbar_actions($ctx, true)
            . haptic_toolbar_serp($ctx)
            . haptic_toolbar_page_rows($ctx)
            . (haptic_toolbar_inbox_rows($ctx) !== '' ? '<div class="otb-label">' . esc_html__('To do', 'optimizr') . '</div>' . haptic_toolbar_inbox_rows($ctx) : '')
            . '<div class="otb-sep"></div>' . haptic_toolbar_nav_rows($ctx)
            . haptic_toolbar_account($ctx, false)
            . '</div>';
    } else {
        $html .= '<div class="otb-panel otb-glass" id="otb-panel" hidden>'
            . haptic_toolbar_maintenance($ctx)
            . haptic_toolbar_page_head($ctx)
            . haptic_toolbar_actions($ctx)
            . haptic_toolbar_page_rows($ctx)
            . (haptic_toolbar_inbox_rows($ctx) !== '' ? '<div class="otb-sep"></div>' . haptic_toolbar_inbox_rows($ctx) : '')
            . '<div class="otb-sep"></div>' . haptic_toolbar_nav_rows($ctx)
            . '<div class="otb-sep"></div>' . haptic_toolbar_account($ctx)
            . '</div>'
            . '<button type="button" class="otb-bubble otb-glass" data-otb-toggle aria-expanded="false" aria-controls="otb-panel" aria-label="' . esc_attr__('Open the Optimizr toolbar', 'optimizr') . '">' . haptic_toolbar_logo() . $state . '</button>';
    }

    return $html . '</div>';
}

/** Dock : capsule d'actions, chaque groupe ouvre une fenêtre au-dessus. */
function haptic_toolbar_dock(array $ctx)
{
    $pop = function ($id, $content) {
        return '<div class="otb-pop otb-glass" id="otb-pop-' . esc_attr($id) . '" hidden>' . $content . '</div>';
    };
    $trigger = function ($id, $class, $inner, $label) {
        return '<button type="button" class="' . esc_attr($class) . '" data-otb-pop="' . esc_attr($id) . '" aria-expanded="false" aria-controls="otb-pop-' . esc_attr($id) . '" aria-label="' . esc_attr($label) . '">' . $inner . '</button>';
    };

    $pops = '';
    $dock = '<a class="otb-icon-btn otb-dock__logo" href="' . esc_url($ctx['optimizr'] ? $ctx['optimizr'][0]['url'] : $ctx['admin']) . '" aria-label="Optimizr" title="Optimizr">' . haptic_toolbar_logo() . '</a><span class="otb-vsep"></span>';

    if ($ctx['edit']) {
        $dock .= '<a class="otb-primary" href="' . esc_url($ctx['edit']['url']) . '" title="' . esc_attr($ctx['edit']['label']) . '">' . haptic_toolbar_icon('edit') . '<span class="otb-hide-sm">' . esc_html__('Edit', 'optimizr') . '</span></a>';
    }
    if ($ctx['new']) {
        $items = '';
        foreach ($ctx['new'] as $item) {
            $items .= haptic_toolbar_row($item['url'], 'plus', $item['label']);
        }
        $dock .= $trigger('new', 'otb-icon-btn', haptic_toolbar_icon('plus'), __('New', 'optimizr'));
        $pops .= $pop('new', $items);
    }

    $chips = '';
    if ($ctx['seo'] || $ctx['site_hidden']) {
        $bad = $ctx['site_hidden'] || !empty($ctx['seo']['noindex']);
        $content = '<div class="otb-label">' . esc_html__('Google preview', 'optimizr') . '</div>' . haptic_toolbar_serp($ctx) . haptic_toolbar_page_rows($ctx);
        $chips .= $trigger('seo', 'otb-chip', '<span class="otb-dot' . ($bad ? ' otb-dot--bad' : '') . '"></span><span>' . esc_html__('SEO', 'optimizr') . '</span>', __('SEO', 'optimizr'));
        $pops .= $pop('seo', $content);
    }
    if ($ctx['cache']) {
        $chips .= $trigger('cache', 'otb-chip', '<span class="otb-dot"></span><span>' . esc_html__('Cache', 'optimizr') . '</span>', __('Cache', 'optimizr'));
        $pops .= $pop('cache', '<div class="otb-label">' . esc_html__('Cache', 'optimizr') . '</div>'
            . haptic_toolbar_row($ctx['cache']['page'], 'zap', __('Clear this page', 'optimizr'))
            . haptic_toolbar_row($ctx['cache']['all'], 'refresh', __('Clear all cache', 'optimizr'))
            . ($ctx['audit'] !== '' && !$ctx['seo'] ? haptic_toolbar_row($ctx['audit'], 'gauge', __('Audit this page', 'optimizr')) : ''));
    }
    if ($chips !== '') {
        $dock .= '<span class="otb-vsep"></span>' . $chips;
    }

    $count = ($ctx['comments']['count'] ?? 0) + ($ctx['updates']['count'] ?? 0);
    $dock .= '<span class="otb-vsep"></span>' . $trigger('me', 'otb-icon-btn otb-me', haptic_toolbar_avatar($ctx) . ($count ? haptic_toolbar_badge($count) : '') . (haptic_toolbar_alert($ctx) ? '<span class="otb-state otb-state--bad"></span>' : ''), __('Menu', 'optimizr'));
    $inbox = haptic_toolbar_inbox_rows($ctx);
    $pops .= $pop('me', ($inbox !== '' ? $inbox . '<div class="otb-sep"></div>' : '') . haptic_toolbar_nav_rows($ctx) . '<div class="otb-sep"></div>' . haptic_toolbar_account($ctx));

    return haptic_toolbar_maintenance($ctx) . $pops . '<div class="otb-dock otb-glass" role="toolbar" aria-label="' . esc_attr__('Optimizr toolbar', 'optimizr') . '">' . $dock . '</div>';
}

/** Balises du module autorisées à la sortie. */
function haptic_toolbar_kses()
{
    $common = ['class' => true, 'id' => true, 'role' => true, 'aria-hidden' => true, 'aria-label' => true, 'aria-controls' => true, 'aria-expanded' => true, 'title' => true, 'hidden' => true,
        'data-otb' => true, 'data-otb-toggle' => true, 'data-otb-hide' => true, 'data-otb-pop' => true];
    $shape = ['d' => true, 'x' => true, 'y' => true, 'cx' => true, 'cy' => true, 'r' => true, 'rx' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true];

    return [
        'div'     => $common,
        'span'    => $common,
        'strong'  => $common,
        'a'       => $common + ['href' => true],
        'button'  => $common + ['type' => true],
        'details' => $common,
        'summary' => $common,
        'svg'     => $common + ['width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'focusable' => true],
        'path'    => $shape,
        'circle'  => $shape,
        'rect'    => $shape,
    ];
}

/* ---------- Réglages : onglet Outils ---------- */

add_action('admin_post_haptic_save_toolbar', function () {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Access denied', 'optimizr'));
    }
    check_admin_referer('haptic_save_toolbar');

    $style = sanitize_key(wp_unslash($_POST['haptic_toolbar_style'] ?? 'bubble'));
    $side  = sanitize_key(wp_unslash($_POST['haptic_toolbar_side'] ?? 'right'));
    $roles = sanitize_key(wp_unslash($_POST['haptic_toolbar_roles'] ?? 'all'));

    update_option('haptic_toolbar', empty($_POST['haptic_toolbar']) ? 0 : 1);
    update_option('haptic_toolbar_style', array_key_exists($style, haptic_toolbar_styles()) ? $style : 'bubble');
    update_option('haptic_toolbar_side', in_array($side, ['left', 'right'], true) ? $side : 'right');
    update_option('haptic_toolbar_roles', array_key_exists($roles, haptic_toolbar_role_choices()) ? $roles : 'all');

    wp_safe_redirect(add_query_arg('haptic_message', 'toolbar', haptic_admin_tab_url('tools')));
    exit;
});

function haptic_render_toolbar_card()
{
    $settings = haptic_toolbar_settings();
    $helps = [
        'bubble' => __('A round button in a corner that opens a compact menu.', 'optimizr'),
        'dock'   => __('A bar at the bottom center with the main actions in view.', 'optimizr'),
        'sheet'  => __('A tab on the edge that opens a panel about the page: Google preview, SEO, cache.', 'optimizr'),
    ];
    ?>
    <form class="optimizr-card optimizr-toolbar-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="haptic_save_toolbar">
        <?php wp_nonce_field('haptic_save_toolbar'); ?>
        <div class="optimizr-card__header">
            <div>
                <h2 class="optimizr-card__title"><?php esc_html_e('Floating toolbar', 'optimizr'); ?></h2>
                <p class="optimizr-card__desc"><?php esc_html_e('On the site, replaces the WordPress toolbar with a small floating module: edit the page, create content, SEO and cache of the page. The administration keeps the WordPress toolbar.', 'optimizr'); ?></p>
            </div>
            <?php echo wp_kses(haptic_switch_input('haptic_toolbar', $settings['enabled'], __('Enable the floating toolbar', 'optimizr')), haptic_kses_ui()); ?>
        </div>
        <div class="optimizr-card__body">
            <fieldset class="optimizr-toolbar-styles">
                <legend><?php esc_html_e('Design', 'optimizr'); ?></legend>
                <?php foreach (haptic_toolbar_styles() as $key => $label) : ?>
                    <label class="optimizr-toolbar-style">
                        <input type="radio" name="haptic_toolbar_style" value="<?php echo esc_attr($key); ?>" <?php checked($settings['style'], $key); ?>>
                        <span class="optimizr-toolbar-style__preview optimizr-toolbar-style__preview--<?php echo esc_attr($key); ?>" aria-hidden="true"><i></i><b></b></span>
                        <span class="optimizr-toolbar-style__label"><?php echo esc_html($label); ?></span>
                        <span class="optimizr-toolbar-style__help"><?php echo esc_html($helps[$key]); ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <div class="optimizr-field">
                <div class="optimizr-field__label"><label for="haptic-toolbar-side"><?php esc_html_e('Position', 'optimizr'); ?></label>
                    <p class="optimizr-field__help"><?php esc_html_e('Bubble and page panel. The dock stays centered.', 'optimizr'); ?></p></div>
                <div class="optimizr-field__control">
                    <select id="haptic-toolbar-side" name="haptic_toolbar_side">
                        <option value="right" <?php selected($settings['side'], 'right'); ?>><?php esc_html_e('Bottom right', 'optimizr'); ?></option>
                        <option value="left" <?php selected($settings['side'], 'left'); ?>><?php esc_html_e('Bottom left', 'optimizr'); ?></option>
                    </select>
                </div>
            </div>
            <div class="optimizr-field">
                <div class="optimizr-field__label"><label for="haptic-toolbar-roles"><?php esc_html_e('Show to', 'optimizr'); ?></label>
                    <p class="optimizr-field__help"><?php esc_html_e('Other accounts keep the WordPress toolbar.', 'optimizr'); ?></p></div>
                <div class="optimizr-field__control">
                    <select id="haptic-toolbar-roles" name="haptic_toolbar_roles">
                        <?php foreach (haptic_toolbar_role_choices() as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['roles'], $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div><button type="submit" class="optimizr-btn optimizr-btn--primary"><?php esc_html_e('Save', 'optimizr'); ?></button></div>
        </div>
    </form>
    <?php
}
