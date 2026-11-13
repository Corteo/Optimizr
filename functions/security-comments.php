<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Onglet Sécurité, carte « Commentaires » : désactivation complète des commentaires et des pings.
 *
 * Tout passe par des filtres : rien n'est modifié en base (état des contenus, commentaires existants).
 * Désactiver le réglage rend au site ses commentaires tels qu'ils étaient.
 */

/** Commentaires désactivés sur tout le site (réglage de l'onglet Sécurité). */
function haptic_comments_disabled()
{
    return (bool) apply_filters('haptic_comments_disabled', (bool) get_option('haptic_sec_disable_comments'));
}

add_action('admin_init', 'haptic_comments_register_settings');

function haptic_comments_register_settings()
{
    $page = haptic_settings_page('security');

    register_setting(haptic_settings_group('security'), 'haptic_sec_disable_comments', ['sanitize_callback' => 'absint']);

    add_settings_section('haptic_sec_comments_section', __('Comments', 'optimizr'), function () {
        echo '<p>' . esc_html__('Turns off comments and pings everywhere on the site: forms and existing comments are hidden, comment feeds and the comments REST API are removed, and the Comments menu disappears from the dashboard and the toolbar. Nothing is deleted: turning this off brings comments back as they were.', 'optimizr') . '</p>';
    }, $page, [
        'toggle'       => 'haptic_sec_disable_comments',
        'toggle_label' => __('Turn off comments', 'optimizr'),
    ]);
}

if (!haptic_comments_disabled()) {
    return;
}

/* Contenus : commentaires et pings fermés partout, compteurs à zéro */
add_filter('comments_open', '__return_false', 20);
add_filter('pings_open', '__return_false', 20);
add_filter('get_comments_number', '__return_zero', 20);
add_filter('comments_array', '__return_empty_array', 20);
add_filter('pre_option_default_comment_status', 'haptic_comments_closed_status');
add_filter('pre_option_default_ping_status', 'haptic_comments_closed_status');
add_filter('pre_option_default_pingback_flag', '__return_zero');

function haptic_comments_closed_status()
{
    return 'closed';
}

// Après l'enregistrement des types de contenu (thème, extensions) : plus de boîte Discussion ni de colonne Commentaires
add_action('init', function () {
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
        }
        if (post_type_supports($post_type, 'trackbacks')) {
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}, 100);

/* Site : modèle de commentaires vide, blocs de commentaires masqués, script de réponse retiré */
add_filter('comments_template', function () {
    return HAPTIC_OPTIMIZR_PATH . 'functions/comments-template.php';
}, 20);

add_filter('render_block', function ($content, $block) {
    $blocks = ['core/comments', 'core/comments-query-loop', 'core/comment-template', 'core/comments-title', 'core/comments-pagination',
        'core/post-comments', 'core/post-comments-form', 'core/post-comments-count', 'core/post-comments-link', 'core/latest-comments'];
    return in_array($block['blockName'] ?? '', $blocks, true) ? '' : $content;
}, 10, 2);

add_action('wp_enqueue_scripts', function () {
    wp_dequeue_script('comment-reply');
}, 100);

add_action('widgets_init', function () {
    unregister_widget('WP_Widget_Recent_Comments');
}, 20);
add_filter('show_recent_comments_widget_style', '__return_false');

/* Flux : plus de liens vers les flux de commentaires ; un flux demandé renvoie vers la page ou l'accueil */
add_filter('feed_links_show_comments_feed', '__return_false');
add_filter('feed_links_extra_show_post_comments_feed', '__return_false');
add_filter('post_comments_feed_link', '__return_empty_string');

add_action('template_redirect', function () {
    if (!is_comment_feed()) {
        return;
    }
    $target = is_singular() ? get_permalink(get_queried_object_id()) : home_url('/');
    wp_safe_redirect($target ?: home_url('/'), 301);
    exit;
}, 9);

/* Pings : ni en-tête X-Pingback, ni méthodes pingback de XML-RPC */
add_filter('wp_headers', function ($headers) {
    unset($headers['X-Pingback']);
    return $headers;
});
add_filter('xmlrpc_methods', function ($methods) {
    unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
    return $methods;
});

/* API REST : routes des commentaires retirées */
add_filter('rest_endpoints', function ($endpoints) {
    foreach (array_keys($endpoints) as $route) {
        if (strpos($route, '/wp/v2/comments') === 0) {
            unset($endpoints[$route]);
        }
    }
    return $endpoints;
});

/* Administration : menu, écrans, widgets du tableau de bord */
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');
}, 999);

add_action('admin_init', function () {
    global $pagenow;
    if (in_array($pagenow, ['edit-comments.php', 'comment.php'], true)) {
        wp_safe_redirect(admin_url());
        exit;
    }
});

add_action('wp_dashboard_setup', function () {
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
}, 20);

// Le widget « Activité » et « D'un coup d'œil » listent encore les commentaires : aucun filtre ne permet de les retirer
add_action('admin_head-index.php', function () {
    echo '<style>#dashboard_right_now .comment-count,#dashboard_right_now .comment-mod-count,#latest-comments,#welcome-panel .welcome-comments{display:none!important}</style>';
});

/* Barre d'administration de WordPress (la barre flottante d'Optimizr lit haptic_comments_disabled()) */
add_action('admin_bar_menu', function ($bar) {
    $bar->remove_node('comments');
}, 999);
