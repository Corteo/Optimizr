<?php

/**
 * Barre d'administration : menu « Optimizr » (front et administration) qui mène au tableau de bord du plugin,
 * avec un lien vers chaque onglet affiché (modules actifs, onglets d'Optimizr Pro), l'assistant de configuration
 * et le suivi des campagnes. Réservé aux comptes qui ont accès à la page d'options (manage_options).
 *
 * Les autres fichiers ajoutent leurs entrées sous le nœud d'un onglet (« optimizr-{onglet} ») : le module Cache y
 * range « Vider tout le cache » et « Vider cette page » (functions/cache.php).
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Le menu est-il affiché pour le compte connecté ? Filtre `haptic_admin_bar_show` pour le masquer. */
function haptic_admin_bar_enabled()
{
    return is_admin_bar_showing()
        && current_user_can('manage_options')
        && apply_filters('haptic_admin_bar_show', true);
}

add_action('admin_bar_menu', 'haptic_admin_bar_menu', 80);
function haptic_admin_bar_menu($bar)
{
    if (!haptic_admin_bar_enabled()) {
        return;
    }

    $bar->add_node([
        'id'    => 'optimizr',
        'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">Optimizr</span>',
        'href'  => haptic_admin_tab_url('dashboard'),
        'meta'  => ['title' => 'Optimizr'],
    ]);

    foreach (haptic_admin_tabs() as $slug => $tab) {
        if (empty($tab['label'])) {
            continue;
        }
        $bar->add_node([
            'parent' => 'optimizr',
            'id'     => 'optimizr-' . sanitize_key($slug),
            'title'  => esc_html(wp_strip_all_tags($tab['label'])),
            'href'   => haptic_admin_tab_url($slug),
        ]);
    }

    // Parcours guidés, séparés des onglets
    $bar->add_group([
        'parent' => 'optimizr',
        'id'     => 'optimizr-guides',
        'meta'   => ['class' => 'ab-sub-secondary'],
    ]);
    $bar->add_node([
        'parent' => 'optimizr-guides',
        'id'     => 'optimizr-onboarding',
        'title'  => esc_html__('Setup wizard', 'optimizr'),
        'href'   => haptic_onboarding_url(),
    ]);
    $bar->add_node([
        'parent' => 'optimizr-guides',
        'id'     => 'optimizr-campaigns',
        'title'  => esc_html__('Campaign tracking', 'optimizr'),
        'href'   => haptic_campaigns_url(),
    ]);
}

/** Icône du menu : le symbole d'Optimizr en masque, coloré comme les autres icônes de la barre (survol compris). */
function haptic_admin_bar_style()
{
    if (!haptic_admin_bar_enabled()) {
        return;
    }
    $icon = haptic_menu_icon();
    if (strpos($icon, 'data:') !== 0) {
        return;
    }
    $mask = 'url("' . $icon . '") no-repeat center / contain';
    $css  = '#wpadminbar #wp-admin-bar-optimizr > .ab-item .ab-icon{width:20px;height:20px;padding:6px 0;margin-right:6px}'
        . '#wpadminbar #wp-admin-bar-optimizr > .ab-item .ab-icon:before{content:"";display:block;width:20px;height:20px;'
        . 'background-color:currentColor;-webkit-mask:' . $mask . ';mask:' . $mask . '}'
        . '@media screen and (max-width:782px){'
        . '#wpadminbar li#wp-admin-bar-optimizr{display:block}'
        . '#wpadminbar #wp-admin-bar-optimizr > .ab-item{width:52px;padding:0;text-align:center}'
        . '#wpadminbar #wp-admin-bar-optimizr > .ab-item .ab-icon{display:flex;align-items:center;justify-content:center;width:52px;height:46px;padding:0;margin:0}'
        . '#wpadminbar #wp-admin-bar-optimizr > .ab-item .ab-icon:before{width:26px;height:26px;top:0}'
        . '#wpadminbar #wp-admin-bar-optimizr > .ab-item .ab-label{display:none}}';
    wp_add_inline_style('admin-bar', $css);
}
add_action('wp_enqueue_scripts', 'haptic_admin_bar_style');
add_action('admin_enqueue_scripts', 'haptic_admin_bar_style');
