<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Module Pages spéciales : page 404 et mode maintenance.
 *
 * Le plugin gère le contenu et le comportement (codes HTTP, indexation, suggestions) ;
 * la mise en forme reste celle du thème. Les deux pages sont des pages WordPress ordinaires,
 * rédigées dans l'éditeur.
 */

/* ---------- Réglages ---------- */

add_action('admin_init', function () {
    $group = hpt_settings_group('pages');
    $page = hpt_settings_page('pages');

    foreach (['hpt_404_page', 'hpt_404_suggestions', 'hpt_maintenance', 'hpt_maintenance_page'] as $option) {
        register_setting($group, $option, ['sanitize_callback' => 'absint']);
    }
    register_setting($group, 'hpt_maintenance_until', ['sanitize_callback' => 'hpt_maintenance_sanitize_until']);

    add_settings_section('hpt_404_section', 'Page introuvable (404)', function () {
        echo '<p>Affichée quand une adresse n\'existe pas. Elle garde le code 404 : Google ne l\'indexe pas et ne la confond pas avec une vraie page.</p>';
    }, $page);
    add_settings_field('hpt_404_page', 'Contenu', function () {
        hpt_pages_dropdown('hpt_404_page', 'Page 404 du thème');
        echo '<p class="optimizr-field__help">La page choisie s\'affiche avec la mise en page du thème. Elle est retirée du sitemap et n\'est pas indexée.</p>';
        hpt_pages_edit_link((int) get_option('hpt_404_page'));
    }, $page, 'hpt_404_section', ['label_for' => 'hpt_404_page']);
    add_settings_field('hpt_404_suggestions', 'Suggestions', function () {
        echo hpt_switch_input('hpt_404_suggestions', get_option('hpt_404_suggestions', 1), 'Suggestions');
    }, $page, 'hpt_404_section', [
        'description' => 'Propose les contenus proches de l\'adresse demandée et un champ de recherche, sous le texte de la page. Pour les placer ailleurs : shortcode [optimizr_suggestions].',
    ]);

    add_settings_section('hpt_maintenance_section', 'Mode maintenance', function () {
        echo '<p>Pendant une refonte ou avant le lancement : les visiteurs voient une page d\'attente, les personnes connectées qui peuvent modifier le contenu voient le site normalement.</p>';
    }, $page);
    add_settings_field('hpt_maintenance', 'Activer', function () {
        echo hpt_switch_input('hpt_maintenance', get_option('hpt_maintenance'), 'Mode maintenance');
        if (hpt_maintenance_active()) {
            echo '<p class="optimizr-field__help"><strong>Le site est actuellement fermé aux visiteurs.</strong></p>';
        }
    }, $page, 'hpt_maintenance_section', [
        'description' => 'Code 503 « service indisponible » : Google patiente sans retirer les pages de ses résultats. Au-delà de quelques jours, il peut commencer à le faire.',
    ]);
    add_settings_field('hpt_maintenance_page', 'Contenu', function () {
        hpt_pages_dropdown('hpt_maintenance_page', 'Message par défaut');
        echo '<p class="optimizr-field__help">Affichée seule, sans menu ni pied de page du thème. Le titre et le texte de la page sont repris.</p>';
        hpt_pages_edit_link((int) get_option('hpt_maintenance_page'));
    }, $page, 'hpt_maintenance_section', ['label_for' => 'hpt_maintenance_page']);
    add_settings_field('hpt_maintenance_until', 'Réouverture', function () {
        printf('<input type="datetime-local" name="hpt_maintenance_until" id="hpt_maintenance_until" value="%s">', esc_attr(get_option('hpt_maintenance_until', '')));
    }, $page, 'hpt_maintenance_section', [
        'label_for' => 'hpt_maintenance_until',
        'description' => 'Facultatif. Le site rouvre automatiquement à cette date, annoncée aux visiteurs et aux moteurs de recherche.',
    ]);
});

function hpt_maintenance_sanitize_until($value)
{
    $value = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) ? $value : '';
}

/** Liste des pages publiées, avec un choix « aucune ». */
function hpt_pages_dropdown($option, $none)
{
    wp_dropdown_pages([
        'name'              => $option,
        'id'                => $option,
        'selected'          => (int) get_option($option),
        'show_option_none'  => $none,
        'option_none_value' => 0,
        'post_status'       => ['publish', 'private', 'draft'],
    ]);
}

function hpt_pages_edit_link($page_id)
{
    if ($page_id && get_post($page_id)) {
        printf(
            '<p class="optimizr-field__help"><a href="%s">Modifier la page</a>%s</p>',
            esc_url(get_edit_post_link($page_id)),
            get_post_status($page_id) !== 'publish' ? ' · <strong>la page n\'est pas publiée</strong> : elle est tout de même utilisée.' : ''
        );
    }
}

/** Page choisie pour un rôle, ou null si aucune (ou supprimée). */
function hpt_pages_get($option)
{
    $id = (int) get_option($option);
    $page = $id ? get_post($id) : null;
    return $page && $page->post_type === 'page' && $page->post_status !== 'trash' ? $page : null;
}

/* ---------- Pages réservées : hors index et repérables ---------- */

add_filter('hpt_sitemap_excluded_ids', function ($ids) {
    $ids[] = (int) get_option('hpt_404_page');
    $ids[] = (int) get_option('hpt_maintenance_page');
    return $ids;
});

add_filter('wp_robots', function ($robots) {
    $reserved = array_filter([(int) get_option('hpt_404_page'), (int) get_option('hpt_maintenance_page')]);
    if (is_404() || ($reserved && is_page($reserved))) {
        $robots['noindex'] = true;
        unset($robots['max-image-preview']);
    }
    return $robots;
});

// Mention dans la liste des pages, comme « Page d'accueil » ou « Page de politique de confidentialité »
add_filter('display_post_states', function ($states, $post) {
    if ($post->ID === (int) get_option('hpt_404_page')) {
        $states['hpt_404'] = 'Page 404';
    }
    if ($post->ID === (int) get_option('hpt_maintenance_page')) {
        $states['hpt_maintenance'] = 'Page de maintenance';
    }
    return $states;
}, 10, 2);

/* ---------- Page 404 ---------- */

// Le contenu de la page choisie remplace celui du gabarit 404, sans changer le code HTTP ni is_404()
add_filter('template_include', function ($template) {
    if (!is_404()) {
        return $template;
    }
    $page = hpt_pages_get('hpt_404_page');
    if (!$page) {
        return $template;
    }

    global $wp_query, $post;
    $wp_query->posts = [$page];
    $wp_query->post = $page;
    $wp_query->post_count = 1;
    $wp_query->current_post = -1;
    $wp_query->queried_object = $page;
    $wp_query->queried_object_id = $page->ID;
    $post = $page;
    $GLOBALS['hpt_404_rendering'] = true;

    $custom = get_page_template_slug($page);
    $templates = array_filter([$custom ?: null, 'page-' . $page->post_name . '.php', 'page.php']);
    $page_template = get_query_template('page', $templates);

    return $page_template ?: $template;
}, 99);

// Suggestions sous le texte de la page 404, sauf si le shortcode les place déjà
add_filter('the_content', function ($content) {
    if (empty($GLOBALS['hpt_404_rendering']) || !in_the_loop() || !get_option('hpt_404_suggestions', 1) || has_shortcode($content, 'optimizr_suggestions')) {
        return $content;
    }
    return $content . hpt_404_suggestions_html();
}, 20);

add_shortcode('optimizr_suggestions', function () {
    return is_404() ? hpt_404_suggestions_html() : '';
});

/**
 * Contenus proches de l'adresse demandée : les mots du dernier segment (« /atelier-tournage-2019 »)
 * servent de recherche, tous ensemble puis un par un.
 *
 * @return WP_Post[]
 */
function hpt_404_suggestions($limit = 5)
{
    $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    // Chemin relatif au site (installation dans un sous-dossier)
    $home = rtrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home !== '' && stripos($path, $home) === 0) {
        $path = substr($path, strlen($home));
    }
    $segments = array_values(array_filter(explode('/', urldecode($path))));
    $last = preg_replace('/\.[a-z0-9]{2,4}$/i', '', (string) end($segments));
    $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', remove_accents(mb_strtolower($last))), function ($word) {
        return mb_strlen($word) > 2 && !ctype_digit($word) && !in_array($word, ['les', 'des', 'une', 'pour', 'avec', 'dans', 'sur', 'aux', 'par', 'the', 'and', 'html', 'php'], true);
    });
    if (!$words) {
        return [];
    }

    $types = array_values(array_diff(get_post_types(['public' => true]), ['attachment']));
    $exclude = array_filter([(int) get_option('hpt_404_page'), (int) get_option('hpt_maintenance_page')]);
    $found = [];

    foreach (array_merge([implode(' ', $words)], count($words) > 1 ? $words : []) as $search) {
        $query = new WP_Query([
            's'                   => $search,
            'post_type'           => $types,
            'post_status'         => 'publish',
            'posts_per_page'      => $limit,
            'post__not_in'        => array_merge($exclude, array_keys($found)),
            'has_password'        => false,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ]);
        foreach ($query->posts as $item) {
            $found[$item->ID] = $item;
        }
        if (count($found) >= $limit) {
            break;
        }
    }

    return array_slice(array_values($found), 0, $limit);
}

function hpt_404_suggestions_html()
{
    $posts = hpt_404_suggestions();

    $html = '<div class="optimizr-suggestions">';
    if ($posts) {
        $html .= '<p class="optimizr-suggestions__title">' . esc_html__('Vous cherchiez peut-être :', 'optimizr') . '</p><ul>';
        foreach ($posts as $item) {
            $html .= '<li><a href="' . esc_url(get_permalink($item)) . '">' . esc_html(get_the_title($item)) . '</a></li>';
        }
        $html .= '</ul>';
    }
    $html .= get_search_form(['echo' => false]);
    $html .= '</div>';

    return apply_filters('hpt_404_suggestions_html', $html, $posts);
}

/* ---------- Mode maintenance ---------- */

/** Maintenance en cours : activée et date de réouverture non atteinte. */
function hpt_maintenance_active()
{
    if (!get_option('hpt_maintenance')) {
        return false;
    }
    $until = hpt_maintenance_until_timestamp();
    return !$until || $until > time();
}

function hpt_maintenance_until_timestamp()
{
    $until = get_option('hpt_maintenance_until', '');
    if ($until === '') {
        return 0;
    }
    $date = date_create_immutable_from_format('Y-m-d\TH:i', $until, wp_timezone());
    return $date ? $date->getTimestamp() : 0;
}

/** Personnes qui voient le site pendant la maintenance. */
function hpt_maintenance_can_bypass()
{
    return current_user_can(apply_filters('hpt_maintenance_capability', 'edit_posts'));
}

// Les requêtes d'administration, de connexion, AJAX, cron et REST ne passent pas par template_redirect
add_action('template_redirect', function () {
    if (!hpt_maintenance_active() || hpt_maintenance_can_bypass()) {
        return;
    }
    if (is_robots() || is_favicon()) {
        return;
    }

    $until = hpt_maintenance_until_timestamp();
    $retry = $until ? max(60, $until - time()) : HOUR_IN_SECONDS;

    status_header(503);
    nocache_headers();
    header('Retry-After: ' . $retry);
    header('X-Robots-Tag: noindex');

    // Gabarit du thème s'il en fournit un (maintenance.php), sinon page autonome
    $template = apply_filters('hpt_maintenance_template', locate_template('maintenance.php'));
    if ($template) {
        include $template;
    } else {
        hpt_maintenance_render($until);
    }
    exit;
}, 1);

function hpt_maintenance_render($until)
{
    $page = hpt_pages_get('hpt_maintenance_page');
    $title = $page ? get_the_title($page) : 'Site en maintenance';
    $content = $page
        ? apply_filters('the_content', $page->post_content)
        : '<p>Le site est en cours de mise à jour. Merci de revenir un peu plus tard.</p>';
    $name = get_bloginfo('name');
    $logo = get_custom_logo();
    $icon = get_site_icon_url(64);
    ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html($title . ' – ' . $name); ?></title>
<?php if ($icon) : ?><link rel="icon" href="<?php echo esc_url($icon); ?>"><?php endif; ?>
<style>
    :root { color-scheme: light dark; --bg: #f6f7f7; --surface: #fff; --text: #1e1e1e; --muted: #50575e; }
    @media (prefers-color-scheme: dark) { :root { --bg: #16181a; --surface: #1f2226; --text: #f0f0f1; --muted: #a7aaad; } }
    * { box-sizing: border-box; }
    body { display: grid; min-height: 100vh; margin: 0; padding: 24px 16px; place-items: center; background: var(--bg); color: var(--text); font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    main { width: 100%; max-width: 560px; padding: 40px 32px; border-radius: 16px; background: var(--surface); text-align: center; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
    .brand { margin-bottom: 24px; color: var(--muted); font-weight: 600; }
    .brand img { max-width: 180px; height: auto; }
    h1 { margin: 0 0 12px; font-size: 1.6rem; line-height: 1.25; }
    .content { color: var(--muted); }
    .content img { max-width: 100%; height: auto; }
    .until { margin: 24px 0 0; font-weight: 600; }
</style>
</head>
<body>
<main>
    <div class="brand"><?php echo $logo ?: esc_html($name); ?></div>
    <h1><?php echo esc_html($title); ?></h1>
    <div class="content"><?php echo $content; ?></div>
    <?php if ($until) : ?>
        <p class="until"><?php echo esc_html(sprintf('Réouverture prévue le %s.', wp_date('j F Y à H\hi', $until))); ?></p>
    <?php endif; ?>
</main>
</body>
</html>
    <?php
}

// Rappel permanent dans la barre d'administration tant que le site est fermé
add_action('admin_bar_menu', function ($bar) {
    if (!hpt_maintenance_active() || !current_user_can('manage_options')) {
        return;
    }
    $bar->add_node([
        'id'    => 'hpt-maintenance',
        'title' => 'Maintenance active',
        'href'  => hpt_admin_tab_url('pages'),
        'meta'  => ['class' => 'hpt-maintenance-flag', 'title' => 'Le site est fermé aux visiteurs'],
    ]);
}, 100);

$hpt_maintenance_flag_css = function () {
    if (hpt_maintenance_active() && is_admin_bar_showing()) {
        echo '<style>#wpadminbar .hpt-maintenance-flag > .ab-item { background: #b32d2e; color: #fff; }</style>';
    }
};
add_action('wp_head', $hpt_maintenance_flag_css);
add_action('admin_head', $hpt_maintenance_flag_css);

// Panneau latéral : rappel de l'état et des shortcodes
add_action('hpt_admin_aside_pages', function () {
    $page_404 = hpt_pages_get('hpt_404_page');
    ?>
    <div class="optimizr-sticky">
        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title">Vérifier</h2>
                    <p class="optimizr-card__desc">Ouvrez une adresse qui n'existe pas pour voir la page 404 telle que les visiteurs la voient.</p>
                </div>
            </div>
            <div class="optimizr-card__body">
                <a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url(home_url('/page-introuvable-' . wp_rand(100, 999))); ?>" target="_blank" rel="noopener">Voir la page 404</a>
                <?php if ($page_404 === null && get_option('hpt_404_page')) : ?>
                    <p class="optimizr-field__help">La page 404 choisie a été supprimée : la page du thème est utilisée.</p>
                <?php endif; ?>
            </div>
        </section>
        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title">Pour le thème</h2>
                    <p class="optimizr-card__desc">Un fichier <code>maintenance.php</code> dans le thème remplace la page de maintenance autonome. Les suggestions sont aussi disponibles dans <code>404.php</code> avec <code>echo do_shortcode('[optimizr_suggestions]');</code></p>
                </div>
            </div>
        </section>
    </div>
    <?php
});
