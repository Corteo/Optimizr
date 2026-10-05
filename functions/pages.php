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
    $group = haptic_settings_group('pages');
    $page = haptic_settings_page('pages');

    foreach (['haptic_404_page', 'haptic_404_suggestions', 'haptic_maintenance', 'haptic_maintenance_page'] as $option) {
        register_setting($group, $option, ['sanitize_callback' => 'absint']);
    }
    register_setting($group, 'haptic_maintenance_until', ['sanitize_callback' => 'haptic_maintenance_sanitize_until']);

    add_settings_section('haptic_404_section', __('Page not found (404)', 'optimizr'), function () {
        echo '<p>' . esc_html__('Shown when a URL does not exist. It keeps the 404 status code: Google does not index it or mistake it for a real page.', 'optimizr') . '</p>';
    }, $page);
    add_settings_field('haptic_404_page', __('Content', 'optimizr'), function () {
        haptic_pages_dropdown('haptic_404_page', __('Theme 404 page', 'optimizr'));
        echo '<p class="optimizr-field__help">' . esc_html__('The chosen page is displayed with the theme layout. It is removed from the sitemap and not indexed.', 'optimizr') . '</p>';
        haptic_pages_edit_link((int) get_option('haptic_404_page'));
    }, $page, 'haptic_404_section', ['label_for' => 'haptic_404_page']);
    add_settings_field('haptic_404_suggestions', __('Suggestions', 'optimizr'), function () {
        echo wp_kses(haptic_switch_input('haptic_404_suggestions', get_option('haptic_404_suggestions', 1), __('Suggestions', 'optimizr')), haptic_kses_ui());
    }, $page, 'haptic_404_section', [
        /* translators: %s: shortcode */
        'description' => sprintf(__('Suggests content close to the requested URL and a search field, below the page text. To place them elsewhere: shortcode %s.', 'optimizr'), '[optimizr_suggestions]'),
    ]);

    add_settings_section('haptic_maintenance_section', __('Maintenance mode', 'optimizr'), function () {
        echo '<p>' . esc_html__('During a redesign or before launch: visitors see a holding page, while logged-in users who can edit content see the site normally.', 'optimizr') . '</p>';
    }, $page);
    add_settings_field('haptic_maintenance', __('Enable', 'optimizr'), function () {
        echo wp_kses(haptic_switch_input('haptic_maintenance', get_option('haptic_maintenance'), __('Maintenance mode', 'optimizr')), haptic_kses_ui());
        if (haptic_maintenance_active()) {
            echo '<p class="optimizr-field__help"><strong>' . esc_html__('The site is currently closed to visitors.', 'optimizr') . '</strong></p>';
        }
    }, $page, 'haptic_maintenance_section', [
        'description' => __('503 “service unavailable” status code: Google waits without removing the pages from its results. After a few days, it may start doing so.', 'optimizr'),
    ]);
    add_settings_field('haptic_maintenance_page', __('Content', 'optimizr'), function () {
        haptic_pages_dropdown('haptic_maintenance_page', __('Default message', 'optimizr'));
        echo '<p class="optimizr-field__help">' . esc_html__('Displayed on its own, without the theme menu or footer. The page title and text are used.', 'optimizr') . '</p>';
        haptic_pages_edit_link((int) get_option('haptic_maintenance_page'));
    }, $page, 'haptic_maintenance_section', ['label_for' => 'haptic_maintenance_page']);
    add_settings_field('haptic_maintenance_until', __('Reopening', 'optimizr'), function () {
        printf('<input type="datetime-local" name="haptic_maintenance_until" id="haptic_maintenance_until" value="%s">', esc_attr(get_option('haptic_maintenance_until', '')));
    }, $page, 'haptic_maintenance_section', [
        'label_for' => 'haptic_maintenance_until',
        'description' => __('Optional. The site reopens automatically on this date, which is announced to visitors and search engines.', 'optimizr'),
    ]);
});

function haptic_maintenance_sanitize_until($value)
{
    $value = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) ? $value : '';
}

/** Liste des pages publiées, avec un choix « aucune ». */
function haptic_pages_dropdown($option, $none)
{
    wp_dropdown_pages([
        'name'              => esc_attr($option),
        'id'                => esc_attr($option),
        'selected'          => (int) get_option($option),
        'show_option_none'  => esc_html($none),
        'option_none_value' => 0,
        'post_status'       => ['publish', 'private', 'draft'],
    ]);
}

function haptic_pages_edit_link($page_id)
{
    if ($page_id && get_post($page_id)) {
        printf(
            '<p class="optimizr-field__help"><a href="%s">%s</a>%s</p>',
            esc_url(get_edit_post_link($page_id)),
            esc_html__('Edit page', 'optimizr'),
            get_post_status($page_id) !== 'publish' ? ' · ' . wp_kses_post(__('<strong>the page is not published</strong>: it is still used.', 'optimizr')) : ''
        );
    }
}

/** Page choisie pour un rôle, ou null si aucune (ou supprimée). */
function haptic_pages_get($option)
{
    $id = (int) get_option($option);
    $page = $id ? get_post($id) : null;
    return $page && $page->post_type === 'page' && $page->post_status !== 'trash' ? $page : null;
}

/* ---------- Pages réservées : hors index et repérables ---------- */

add_filter('haptic_sitemap_excluded_ids', function ($ids) {
    $ids[] = (int) get_option('haptic_404_page');
    $ids[] = (int) get_option('haptic_maintenance_page');
    return $ids;
});

add_filter('wp_robots', function ($robots) {
    $reserved = array_filter([(int) get_option('haptic_404_page'), (int) get_option('haptic_maintenance_page')]);
    if (is_404() || ($reserved && is_page($reserved))) {
        $robots['noindex'] = true;
        unset($robots['max-image-preview']);
    }
    return $robots;
});

// Mention dans la liste des pages, comme « Page d'accueil » ou « Page de politique de confidentialité »
add_filter('display_post_states', function ($states, $post) {
    if ($post->ID === (int) get_option('haptic_404_page')) {
        $states['haptic_404'] = __('404 Page', 'optimizr');
    }
    if ($post->ID === (int) get_option('haptic_maintenance_page')) {
        $states['haptic_maintenance'] = __('Maintenance Page', 'optimizr');
    }
    return $states;
}, 10, 2);

/* ---------- Page 404 ---------- */

// Le contenu de la page choisie remplace celui du gabarit 404, sans changer le code HTTP ni is_404()
add_filter('template_include', function ($template) {
    if (!is_404()) {
        return $template;
    }
    $page = haptic_pages_get('haptic_404_page');
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
    $GLOBALS['haptic_404_rendering'] = true;

    $custom = get_page_template_slug($page);
    $templates = array_filter([$custom ?: null, 'page-' . $page->post_name . '.php', 'page.php']);
    $page_template = get_query_template('page', $templates);

    return $page_template ?: $template;
}, 99);

// Suggestions sous le texte de la page 404, sauf si le shortcode les place déjà
add_filter('the_content', function ($content) {
    if (empty($GLOBALS['haptic_404_rendering']) || !in_the_loop() || !get_option('haptic_404_suggestions', 1) || has_shortcode($content, 'optimizr_suggestions')) {
        return $content;
    }
    return $content . haptic_404_suggestions_html();
}, 20);

add_shortcode('optimizr_suggestions', function () {
    return is_404() ? haptic_404_suggestions_html() : '';
});

/**
 * Contenus proches de l'adresse demandée : les mots du dernier segment (« /atelier-tournage-2019 »)
 * servent de recherche, tous ensemble puis un par un.
 *
 * @return WP_Post[]
 */
function haptic_404_suggestions($limit = 5)
{
    $path = (string) wp_parse_url(wp_strip_all_tags(wp_unslash($_SERVER['REQUEST_URI'] ?? '')), PHP_URL_PATH);
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
    $exclude = array_filter([(int) get_option('haptic_404_page'), (int) get_option('haptic_maintenance_page')]);
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

function haptic_404_suggestions_html()
{
    $posts = haptic_404_suggestions();

    $html = '<div class="optimizr-suggestions">';
    if ($posts) {
        $html .= '<p class="optimizr-suggestions__title">' . esc_html__('Maybe you were looking for:', 'optimizr') . '</p><ul>';
        foreach ($posts as $item) {
            $html .= '<li><a href="' . esc_url(get_permalink($item)) . '">' . esc_html(get_the_title($item)) . '</a></li>';
        }
        $html .= '</ul>';
    }
    $html .= get_search_form(['echo' => false]);
    $html .= '</div>';

    return apply_filters('haptic_404_suggestions_html', $html, $posts);
}

/* ---------- Mode maintenance ---------- */

/** Maintenance en cours : activée et date de réouverture non atteinte. */
function haptic_maintenance_active()
{
    if (!get_option('haptic_maintenance')) {
        return false;
    }
    $until = haptic_maintenance_until_timestamp();
    return !$until || $until > time();
}

function haptic_maintenance_until_timestamp()
{
    $until = get_option('haptic_maintenance_until', '');
    if ($until === '') {
        return 0;
    }
    $date = date_create_immutable_from_format('Y-m-d\TH:i', $until, wp_timezone());
    return $date ? $date->getTimestamp() : 0;
}

/** Personnes qui voient le site pendant la maintenance. */
function haptic_maintenance_can_bypass()
{
    return current_user_can(apply_filters('haptic_maintenance_capability', 'edit_posts'));
}

// Les requêtes d'administration, de connexion, AJAX, cron et REST ne passent pas par template_redirect (REST : voir plus bas)
add_action('template_redirect', function () {
    if (!haptic_maintenance_active() || haptic_maintenance_can_bypass()) {
        return;
    }
    if (is_robots() || is_favicon()) {
        return;
    }

    $until = haptic_maintenance_until_timestamp();
    $retry = $until ? max(60, $until - time()) : HOUR_IN_SECONDS;

    status_header(503);
    nocache_headers();
    header('Retry-After: ' . $retry);
    header('X-Robots-Tag: noindex');

    // Gabarit du thème s'il en fournit un (maintenance.php), sinon page autonome
    $template = apply_filters('haptic_maintenance_template', locate_template('maintenance.php'));
    if ($template) {
        include $template;
    } else {
        haptic_maintenance_render($until);
    }
    exit;
}, 1);

// API REST fermée aux mêmes personnes : /wp-json/wp/v2/posts exposerait le contenu du site.
// Après la vérification du cookie et du nonce de WordPress (priorité 100) : l'éditeur des personnes connectées fonctionne toujours.
add_filter('rest_authentication_errors', function ($result) {
    if (is_wp_error($result) || !haptic_maintenance_active() || haptic_maintenance_can_bypass()) {
        return $result;
    }
    return new WP_Error('haptic_maintenance', __('Site under maintenance.', 'optimizr'), ['status' => 503]);
}, 110);

function haptic_maintenance_render($until)
{
    $page = haptic_pages_get('haptic_maintenance_page');
    $title = $page ? get_the_title($page) : __('Site under maintenance', 'optimizr');
    $content = $page
        ? apply_filters('the_content', $page->post_content) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre du cœur
        : '<p>' . esc_html__('The site is being updated. Please come back a little later.', 'optimizr') . '</p>';
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
    <div class="brand"><?php echo $logo ? wp_kses_post($logo) : esc_html($name); ?></div>
    <h1><?php echo esc_html($title); ?></h1>
    <div class="content"><?php echo wp_kses_post($content); ?></div>
    <?php if ($until) : ?>
        <p class="until"><?php
        /* translators: %s: reopening date and time */
        $haptic_reopen_text = __('Scheduled to reopen on %s.', 'optimizr');
        /* translators: reopening date and time format, see https://www.php.net/manual/datetime.format.php */
        $haptic_reopen_format = _x('F j, Y \a\t g:i a', 'maintenance reopening date format', 'optimizr');
        echo esc_html(sprintf($haptic_reopen_text, wp_date($haptic_reopen_format, $until)));
        ?></p>
    <?php endif; ?>
</main>
</body>
</html>
    <?php
}

// Rappel permanent dans la barre d'administration tant que le site est fermé
add_action('admin_bar_menu', function ($bar) {
    if (!haptic_maintenance_active() || !current_user_can('manage_options')) {
        return;
    }
    $bar->add_node([
        'id'    => 'haptic-maintenance',
        'title' => __('Maintenance on', 'optimizr'),
        'href'  => haptic_admin_tab_url('pages'),
        'meta'  => ['class' => 'haptic-maintenance-flag', 'title' => __('The site is closed to visitors', 'optimizr')],
    ]);
}, 100);

$haptic_maintenance_flag_css = function () {
    if (haptic_maintenance_active() && is_admin_bar_showing()) {
        wp_add_inline_style('admin-bar', '#wpadminbar .haptic-maintenance-flag > .ab-item { background: #b32d2e; color: #fff; }');
    }
};
add_action('wp_enqueue_scripts', $haptic_maintenance_flag_css);
add_action('admin_enqueue_scripts', $haptic_maintenance_flag_css);

// Panneau latéral : rappel de l'état et des shortcodes
add_action('haptic_admin_aside_pages', function () {
    $page_404 = haptic_pages_get('haptic_404_page');
    ?>
    <div class="optimizr-sticky">
        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title"><?php esc_html_e('Check', 'optimizr'); ?></h2>
                    <p class="optimizr-card__desc"><?php esc_html_e('Open a URL that does not exist to see the 404 page as visitors see it.', 'optimizr'); ?></p>
                </div>
            </div>
            <div class="optimizr-card__body">
                <a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url(home_url('/page-introuvable-' . wp_rand(100, 999))); ?>" target="_blank" rel="noopener"><?php esc_html_e('View the 404 page', 'optimizr'); ?></a>
                <?php if ($page_404 === null && get_option('haptic_404_page')) : ?>
                    <p class="optimizr-field__help"><?php esc_html_e('The chosen 404 page has been deleted: the theme page is used.', 'optimizr'); ?></p>
                <?php endif; ?>
            </div>
        </section>
        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title"><?php esc_html_e('For the theme', 'optimizr'); ?></h2>
                    <p class="optimizr-card__desc"><?php
                    /* translators: 1: maintenance.php file name, 2: 404.php file name, 3: PHP code snippet */
                    echo wp_kses_post(sprintf(__('A %1$s file in the theme replaces the standalone maintenance page. Suggestions are also available in %2$s with %3$s', 'optimizr'), '<code>maintenance.php</code>', '<code>404.php</code>', '<code>' . esc_html("echo do_shortcode('[optimizr_suggestions]');") . '</code>'));
                    ?></p>
                </div>
            </div>
        </section>
    </div>
    <?php
});
