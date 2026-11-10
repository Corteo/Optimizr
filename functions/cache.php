<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Module Cache : écriture des pages en cache, vidage automatique et manuel, réglages et état (onglet Cache).
 * Les fichiers hors du plugin (advanced-cache.php, wp-config.php, .htaccess) sont gérés par functions/cache-files.php ;
 * les pages sont servies par functions/cache-serve.php, avant le chargement de WordPress.
 */

/** État des fichiers après la dernière synchronisation (cf. haptic_cache_sync()). */
function haptic_cache_state()
{
    $state = get_option('haptic_cache_state');
    return is_array($state) ? $state + ['page' => false, 'browser' => false, 'error' => '', 'browser_error' => ''] : ['page' => false, 'browser' => false, 'error' => '', 'browser_error' => ''];
}

/** Le cache de pages est-il en service (fichiers en place, constante WP_CACHE active) ? */
function haptic_cache_page_active()
{
    $state = haptic_cache_state();
    return !empty($state['page']) && get_option('haptic_cache_page') && defined('WP_CACHE') && WP_CACHE;
}

/*
 * Plugin mis à jour ou déplacé (advanced-cache.php pointe vers l'ancien dossier), ou fichiers écrits depuis WP-CLI,
 * qui ne sait pas que le serveur lit le .htaccess : on les réécrit.
 */
add_action('admin_init', function () {
    $state = get_option('haptic_cache_state');
    if ((get_option('haptic_cache_page') || get_option('haptic_cache_browser'))
        && (!is_array($state) || ($state['version'] ?? '') !== HAPTIC_OPTIMIZR_VERSION || ($state['path'] ?? '') !== HAPTIC_OPTIMIZR_PATH
            || ($state['apache'] ?? null) !== haptic_cache_server_reads_htaccess())) {
        haptic_cache_sync();
    }
});

/* ---------- Écriture des pages ---------- */

// Au plus tôt dans template_redirect : redirections et pages de maintenance passent aussi par le tampon (et sont écartées par leur code HTTP)
add_action('template_redirect', 'haptic_cache_start_buffer', 0);
function haptic_cache_start_buffer()
{
    if (!haptic_cache_page_active() || !haptic_cache_is_cacheable_request()) {
        return;
    }
    ob_start('haptic_cache_end_buffer');
}

/** La page demandée peut-elle être mise en cache, d'après WordPress ? */
function haptic_cache_is_cacheable_request()
{
    $config = haptic_cache_config();
    if (!$config || haptic_cache_request_file($config) === '') {
        return false;
    }
    if (is_user_logged_in() || is_admin() || is_search() || is_preview() || is_feed() || is_trackback() || is_robots() || is_customize_preview() || is_404()) {
        return false;
    }
    if (is_singular() && post_password_required()) {
        return false;
    }
    // Panier, commande et compte des boutiques : toujours personnalisés
    if ((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout()) || (function_exists('is_account_page') && is_account_page())) {
        return false;
    }
    if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
        return false;
    }
    return !apply_filters('haptic_cache_skip_request', false);
}

/** Fin du tampon : la page complète est enregistrée si c'est une page HTML en 200 sans cookie posé. */
function haptic_cache_end_buffer($html)
{
    if (!is_string($html) || strlen($html) < 255 || stripos($html, '</html>') === false || http_response_code() !== 200) {
        return $html;
    }
    if ((defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) || is_404()) {
        return $html;
    }
    foreach (headers_list() as $header) {
        $header = strtolower($header);
        if (strpos($header, 'set-cookie:') === 0 || (strpos($header, 'content-type:') === 0 && strpos($header, 'text/html') === false)) {
            return $html;
        }
    }

    $config = haptic_cache_config();
    $file = $config ? haptic_cache_request_file($config) : '';
    if ($file === '') {
        return $html;
    }

    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- fin de requête, WP_Filesystem n'est plus fiable dans un tampon de sortie
        return $html;
    }
    // Écriture dans un fichier temporaire puis renommage : un visiteur ne lit jamais une page à moitié écrite
    $tmp = $dir . '/.' . uniqid('tmp', true);
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- idem
    if (file_put_contents($tmp, $html . "\n<!-- Cached by Optimizr on " . gmdate('Y-m-d H:i:s') . " UTC -->\n") !== false) {
        @rename($tmp, $file); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- un autre visiteur a pu écrire la même page au même instant
        if (is_file($tmp)) {
            @unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- idem
        }
    }

    return $html;
}

/* ---------- Vidage ---------- */

/** Vide tout le cache de pages du site. */
function haptic_cache_purge_all()
{
    haptic_cache_delete_dir(haptic_cache_pages_dir());
    update_option('haptic_cache_last_purge', time(), false);
    haptic_cache_purge_host();

    /**
     * Cache vidé. $urls : adresses vidées, vide pour tout le site.
     * Optimizr Pro s'y accroche pour vider Cloudflare et précharger les pages.
     */
    do_action('haptic_cache_purged', []);
}

/**
 * Vide le cache de quelques adresses du site. $recursive : avec les pages en dessous (pagination d'une archive).
 */
function haptic_cache_purge_urls(array $urls, $recursive = false)
{
    $home = haptic_cache_site_host();
    $done = [];

    foreach (array_unique(array_filter($urls)) as $url) {
        $parts = wp_parse_url($url);
        $host = strtolower($parts['host'] ?? '') . (!empty($parts['port']) ? ':' . $parts['port'] : '');
        $path = trim($parts['path'] ?? '/', '/');
        if ($host !== $home || strpos($path, '..') !== false) {
            continue;
        }

        $dir = haptic_cache_pages_dir() . ($path !== '' ? '/' . $path : '');
        if ($recursive && $path !== '') {
            haptic_cache_delete_dir($dir);
        } elseif (is_dir($dir)) {
            foreach ((array) glob($dir . '/index*.html') as $file) {
                wp_delete_file($file);
            }
            // Pagination de la page d'accueil ou d'une archive : /page/2/…
            if ($recursive) {
                haptic_cache_delete_dir($dir . '/page');
            }
        }
        $done[] = $url;
    }

    if ($done) {
        do_action('haptic_cache_purged', $done);
    }
}

/** Cache de l'hébergeur vidé en même temps que celui d'Optimizr, quand il en offre le moyen. */
function haptic_cache_purge_host()
{
    if (class_exists('WpeCommon', false)) {
        if (method_exists('WpeCommon', 'purge_memcached')) {
            WpeCommon::purge_memcached();
        }
        if (method_exists('WpeCommon', 'purge_varnish_cache')) {
            WpeCommon::purge_varnish_cache();
        }
    }
    if (function_exists('pantheon_wp_clear_edge_all')) {
        pantheon_wp_clear_edge_all();
    }
    // Extension Nginx Helper (cache FastCGI ou Redis de l'hébergeur)
    do_action('rt_nginx_helper_purge_all'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- action de l'extension Nginx Helper
}

/*
 * Vidages demandés pendant la requête, exécutés à la fin : après l'enregistrement des métadonnées (SEO…)
 * et une seule fois, quel que soit le nombre de contenus modifiés.
 */
function haptic_cache_queue($urls = null, $recursive = false)
{
    static $queue = null;
    static $hooked = false;

    if ($queue === null) {
        $queue = ['all' => false, 'urls' => [], 'tree' => []];
    }
    if ($urls === null) {
        $queue['all'] = true;
    } else {
        $queue[$recursive ? 'tree' : 'urls'] = array_merge($queue[$recursive ? 'tree' : 'urls'], (array) $urls);
    }

    if (!$hooked) {
        $hooked = true;
        add_action('shutdown', function () use (&$queue) {
            // Cache de pages désactivé entre-temps : la synchronisation a déjà tout retiré
            if (!haptic_cache_page_active()) {
                return;
            }
            if ($queue['all']) {
                haptic_cache_purge_all();
                return;
            }
            haptic_cache_purge_urls($queue['urls']);
            haptic_cache_purge_urls($queue['tree'], true);
        }, 10);
    }
}

/** Le contenu est-il affiché sur le site (type public, ni révision ni sauvegarde automatique) ? */
function haptic_cache_is_public_post($post)
{
    $post = get_post($post);
    return $post && !wp_is_post_revision($post) && !wp_is_post_autosave($post) && is_post_type_viewable($post->post_type) && $post->post_type !== 'attachment';
}

/** Pages où apparaît un contenu publié : la sienne, l'accueil, ses archives (type, catégories, auteur). */
function haptic_cache_post_urls($post)
{
    $post = get_post($post);
    $urls = [get_permalink($post)];
    $tree = [home_url('/')];

    if ($post->post_type === 'post' && get_option('page_for_posts')) {
        $tree[] = get_permalink((int) get_option('page_for_posts'));
    }
    $archive = get_post_type_archive_link($post->post_type);
    if ($archive) {
        $tree[] = $archive;
    }
    foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy) {
        if (!$taxonomy->public) {
            continue;
        }
        foreach ((array) wp_get_post_terms($post->ID, $taxonomy->name) as $term) {
            if ($term instanceof WP_Term) {
                $link = get_term_link($term);
                if (!is_wp_error($link)) {
                    $tree[] = $link;
                }
            }
        }
    }
    if (post_type_supports($post->post_type, 'author')) {
        $tree[] = get_author_posts_url((int) $post->post_author);
    }

    return apply_filters('haptic_cache_post_urls', ['urls' => $urls, 'tree' => $tree], $post);
}

// Contenu publié, dépublié, mis à la corbeille : il entre ou sort des listes de tout le site
add_action('transition_post_status', function ($new, $old, $post) {
    if (($new === 'publish' || $old === 'publish') && haptic_cache_is_public_post($post)) {
        if ($new === 'publish' && $old === 'publish') {
            $urls = haptic_cache_post_urls($post);
            haptic_cache_queue($urls['urls']);
            haptic_cache_queue($urls['tree'], true);
        } else {
            haptic_cache_queue();
        }
    }
}, 10, 3);

// Adresse d'un contenu publié changée : l'ancienne page n'existe plus
add_action('post_updated', function ($post_id, $after, $before) {
    if ($before->post_status === 'publish' && haptic_cache_is_public_post($after)
        && ($before->post_name !== $after->post_name || $before->post_parent !== $after->post_parent)) {
        haptic_cache_queue();
    }
}, 10, 3);

// Commentaires : la page du contenu commenté
function haptic_cache_purge_comment($comment)
{
    $comment = get_comment($comment);
    if ($comment && $comment->comment_post_ID && get_post_status($comment->comment_post_ID) === 'publish') {
        haptic_cache_queue([get_permalink($comment->comment_post_ID)], true);
    }
}
add_action('comment_post', function ($comment_id, $approved) {
    if ((int) $approved === 1) {
        haptic_cache_purge_comment($comment_id);
    }
}, 10, 2);
add_action('edit_comment', 'haptic_cache_purge_comment');
add_action('transition_comment_status', function ($new, $old, $comment) {
    haptic_cache_purge_comment($comment);
}, 10, 3);

// Changements visibles sur tout le site : thème, menus, widgets, catégories, extensions, réglages
foreach (['switch_theme', 'customize_save_after', 'wp_update_nav_menu', 'wp_delete_nav_menu', 'created_term', 'edited_term', 'delete_term',
    'activated_plugin', 'deactivated_plugin', 'upgrader_process_complete', 'haptic_settings_imported'] as $haptic_hook) {
    add_action($haptic_hook, function () {
        haptic_cache_queue();
    });
}
unset($haptic_hook);

add_action('updated_option', 'haptic_cache_option_changed');
add_action('added_option', 'haptic_cache_option_changed');
function haptic_cache_option_changed($option)
{
    global $pagenow;

    $site_options = ['blogname', 'blogdescription', 'permalink_structure', 'category_base', 'tag_base', 'show_on_front', 'page_on_front',
        'page_for_posts', 'posts_per_page', 'date_format', 'time_format', 'WPLANG', 'site_icon', 'sidebars_widgets', 'blog_public', 'home', 'siteurl'];
    if (in_array($option, $site_options, true) || strpos($option, 'theme_mods_') === 0 || strpos($option, 'widget_') === 0) {
        haptic_cache_queue();
        return;
    }

    // Réglages d'Optimizr enregistrés depuis l'administration (pas les journaux ni les compteurs mis à jour en arrière-plan)
    if (strpos($option, 'haptic_') === 0 && is_admin() && in_array($pagenow, ['options.php', 'admin-post.php'], true)
        && !in_array($option, haptic_settings_internal_options(), true)) {
        haptic_cache_queue();
    }
}

/* ---------- Vidage manuel ---------- */

/** Droit de vider le cache (barre d'administration, onglet Cache). */
function haptic_cache_can_purge()
{
    return current_user_can(apply_filters('haptic_cache_purge_capability', 'manage_options'));
}

function haptic_cache_purge_url($url = '')
{
    $args = ['action' => 'haptic_cache_purge'];
    if ($url !== '') {
        $args['url'] = rawurlencode($url);
    }
    return wp_nonce_url(add_query_arg($args, admin_url('admin-post.php')), 'haptic_cache_purge');
}

add_action('admin_post_haptic_cache_purge', function () {
    if (!haptic_cache_can_purge()) {
        wp_die(esc_html__('Access denied', 'optimizr'));
    }
    check_admin_referer('haptic_cache_purge');

    $url = isset($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- adresse nettoyée par esc_url_raw()
    if ($url !== '') {
        haptic_cache_purge_urls([$url]);
        // Retour sur la page, régénérée par cette visite
        wp_safe_redirect($url);
        exit;
    }

    haptic_cache_purge_all();
    $back = wp_get_referer();
    wp_safe_redirect(add_query_arg('haptic_cache_purged', 1, $back ? $back : haptic_admin_tab_url('cache')));
    exit;
});

add_action('admin_notices', function () {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message affiché après une action déjà vérifiée par nonce
    if (empty($_GET['haptic_cache_purged']) || !haptic_cache_can_purge()) {
        return;
    }
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache cleared.', 'optimizr') . '</p></div>';
});

// Barre d'administration : vider tout le cache, ou la page affichée
add_action('admin_bar_menu', function ($bar) {
    if (!haptic_cache_page_active() || !haptic_cache_can_purge()) {
        return;
    }
    // Sous l'onglet Cache du menu Optimizr (functions/admin-bar.php) ; seul dans la barre pour un compte qui peut
    // vider le cache sans accéder aux réglages (filtre haptic_cache_purge_capability)
    if (!$bar->get_node('optimizr-cache')) {
        $bar->add_node([
            'id'    => 'optimizr-cache',
            'title' => esc_html__('Cache', 'optimizr'),
            'href'  => haptic_admin_tab_url('cache'),
        ]);
    }
    $bar->add_node([
        'parent' => 'optimizr-cache',
        'id'     => 'optimizr-cache-purge',
        'title'  => esc_html__('Clear all cache', 'optimizr'),
        'href'   => haptic_cache_purge_url(),
    ]);
    if (!is_admin()) {
        global $wp;
        $bar->add_node([
            'parent' => 'optimizr-cache',
            'id'     => 'optimizr-cache-purge-page',
            'title'  => esc_html__('Clear this page', 'optimizr'),
            'href'   => haptic_cache_purge_url(home_url(trailingslashit($wp->request ?? ''))),
        ]);
    }
}, 90);

/* ---------- Réglages : onglet Cache ---------- */

add_action('admin_init', 'haptic_cache_register_settings');
function haptic_cache_register_settings()
{
    $group = haptic_settings_group('cache');
    $page = haptic_settings_page('cache');

    register_setting($group, 'haptic_cache_page', ['sanitize_callback' => function ($value) {
        // Une autre extension de cache est active : on ne l'active pas, pour ne pas servir deux caches
        return haptic_cache_blocker() === '' ? absint($value) : 0;
    }]);
    register_setting($group, 'haptic_cache_ttl', ['sanitize_callback' => function ($value) {
        return max(1, min(720, (int) $value));
    }]);
    register_setting($group, 'haptic_cache_mobile', ['sanitize_callback' => 'absint']);
    register_setting($group, 'haptic_cache_exclude', ['sanitize_callback' => 'sanitize_textarea_field']);
    register_setting($group, 'haptic_cache_browser', ['sanitize_callback' => 'absint']);
    register_setting($group, 'haptic_cache_gzip', ['sanitize_callback' => 'absint']);

    add_settings_section('haptic-cache-page', __('Page cache', 'optimizr'), 'haptic_cache_page_section', $page,
        ['toggle' => 'haptic_cache_page', 'toggle_label' => __('Enable the page cache', 'optimizr')]);
    add_settings_field('haptic-cache-ttl', __('Lifespan', 'optimizr'), 'haptic_cache_ttl_callback', $page, 'haptic-cache-page', [
        'label_for'   => 'haptic_cache_ttl',
        'description' => __('A page is rebuilt after this delay, even if nothing changed. 10 hours keeps forms working.', 'optimizr'),
    ]);
    add_settings_field('haptic-cache-mobile', __('Mobile', 'optimizr'), 'haptic_cache_mobile_callback', $page, 'haptic-cache-page', [
        'description' => __('Only if your theme shows different HTML on phones (not just a responsive layout).', 'optimizr'),
    ]);
    add_settings_field('haptic-cache-exclude', __('Never cache', 'optimizr'), 'haptic_cache_exclude_callback', $page, 'haptic-cache-page', [
        'label_for'   => 'haptic_cache_exclude',
        'layout'      => 'stacked',
        'description' => __('One address per line, such as /contact/ or /shop/*. Logged-in users, searches and shopping carts are never cached.', 'optimizr'),
    ]);

    // Serveur sans .htaccess (Nginx…) : seulement les règles à transmettre à l'hébergeur
    $htaccess = haptic_cache_server_reads_htaccess();
    add_settings_section('haptic-cache-browser', __('Browser cache', 'optimizr'), 'haptic_cache_browser_section', $page,
        $htaccess ? ['toggle' => 'haptic_cache_browser', 'toggle_label' => __('Enable the browser cache rules', 'optimizr')] : []);
    if ($htaccess) {
        add_settings_field('haptic-cache-gzip', __('Compression', 'optimizr'), 'haptic_cache_gzip_callback', $page, 'haptic-cache-browser');
    }
}

function haptic_cache_page_section()
{
    echo '<p>' . esc_html__('Pages are saved as HTML files and sent to visitors without loading WordPress: much faster. The cache is cleared automatically when content, comments, menus, the theme or settings change.', 'optimizr') . '</p>';

    $blocker = haptic_cache_blocker();
    $state = haptic_cache_state();
    $host = haptic_cache_host_cache();

    if ($blocker === 'multisite') {
        echo '<p><strong>' . esc_html__('The page cache is not available on a multisite network.', 'optimizr') . '</strong></p>';
    } elseif ($blocker === 'plugin') {
        /* translators: %s: names of the active cache plugins */
        echo '<p><strong>' . esc_html(sprintf(__('%s already caches pages: two caches would conflict. Keep it, or deactivate it to use this one.', 'optimizr'), implode(', ', haptic_cache_conflicting_plugins()))) . '</strong></p>';
    } elseif ($blocker === 'dropin') {
        echo '<p><strong>' . wp_kses_post(sprintf(
            /* translators: %s: file name */
            __('Another cache plugin left its %s file. Deactivate that plugin, or delete the file if it is no longer installed.', 'optimizr'),
            '<code>wp-content/advanced-cache.php</code>'
        )) . '</strong></p>';
    } elseif ($host !== '') {
        /* translators: %s: hosting company or caching proxy */
        echo '<p>' . esc_html(sprintf(__('%s already caches your pages: this cache is usually not needed. Optimizr also clears it when it clears its own.', 'optimizr'), $host)) . '</p>';
    }

    if (get_option('haptic_cache_page') && $state['error'] === 'wp_config') {
        echo '<p><strong>' . wp_kses_post(sprintf(
            /* translators: 1: PHP line to add, 2: file name */
            __('Add %1$s at the top of %2$s (Optimizr cannot write to it) to turn the cache on.', 'optimizr'),
            "<code>define('WP_CACHE', true);</code>",
            '<code>wp-config.php</code>'
        )) . '</strong></p>';
    } elseif (get_option('haptic_cache_page') && $state['error'] === 'files') {
        echo '<p><strong>' . esc_html__('Optimizr cannot write to the wp-content folder: the page cache is off. Ask your host to make it writable.', 'optimizr') . '</strong></p>';
    }
}

function haptic_cache_ttl_callback()
{
    printf(
        '<span class="optimizr-inline"><input type="number" class="optimizr-input-small" name="haptic_cache_ttl" id="haptic_cache_ttl" min="1" max="720" value="%s"> %s</span>',
        esc_attr(haptic_cache_ttl_hours()),
        esc_html__('hours', 'optimizr')
    );
}

function haptic_cache_mobile_callback()
{
    echo '<label class="optimizr-check"><input type="checkbox" name="haptic_cache_mobile" value="1" ' . checked(get_option('haptic_cache_mobile'), 1, false) . '> ' . esc_html__('Separate cache for phones and tablets', 'optimizr') . '</label>';
}

function haptic_cache_exclude_callback()
{
    echo '<textarea name="haptic_cache_exclude" id="haptic_cache_exclude" rows="3" class="optimizr-code" spellcheck="false" placeholder="/contact/">' . esc_textarea(get_option('haptic_cache_exclude', '')) . '</textarea>';
}

function haptic_cache_browser_section()
{
    echo '<p>' . esc_html__('Visitors\' browsers keep images and fonts for a year, styles and scripts for a month, instead of downloading them on every page.', 'optimizr') . '</p>';

    $state = haptic_cache_state();
    if (!haptic_cache_server_reads_htaccess()) {
        echo '<p>' . esc_html__('Your server (Nginx or another) does not read the .htaccess file, where Optimizr writes these rules. Ask your host to add them to its configuration:', 'optimizr') . '</p>';
        echo '<pre class="optimizr-code">' . esc_html(haptic_cache_nginx_rules((bool) get_option('haptic_cache_gzip', 1))) . '</pre>';
    } elseif (get_option('haptic_cache_browser') && $state['browser_error'] === 'htaccess') {
        echo '<p><strong>' . esc_html__('Optimizr cannot write to the .htaccess file at the root of the site: the rules are not in place.', 'optimizr') . '</strong></p>';
    }
    if (haptic_cache_conflicting_plugins()) {
        echo '<p>' . esc_html__('If your cache plugin already sets these rules, leave this off.', 'optimizr') . '</p>';
    }
}

function haptic_cache_gzip_callback()
{
    echo '<label class="optimizr-check"><input type="checkbox" name="haptic_cache_gzip" value="1" ' . checked(get_option('haptic_cache_gzip', 1), 1, false) . '> ' . esc_html__('Compress pages, styles and scripts (gzip)', 'optimizr') . '</label>';
}

/**
 * Pages en cache : nombre et poids (parcours limité, pour rester rapide sur un gros site).
 *
 * @return array ['count' => int, 'size' => int, 'more' => bool]
 */
function haptic_cache_stats()
{
    $stats = ['count' => 0, 'size' => 0, 'more' => false];
    $dir = haptic_cache_pages_dir();
    if (!is_dir($dir)) {
        return $stats;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (substr($file->getFilename(), -5) !== '.html') {
            continue;
        }
        if (++$stats['count'] > 20000) {
            $stats['more'] = true;
            break;
        }
        $stats['size'] += $file->getSize();
    }
    return $stats;
}

// Panneau latéral : état du cache, vidage, autres caches détectés
add_action('haptic_admin_aside_cache', function () {
    $state = haptic_cache_state();
    $active = haptic_cache_page_active();
    $stats = haptic_cache_stats();
    $last = (int) get_option('haptic_cache_last_purge');
    $plugins = haptic_cache_conflicting_plugins();
    $host = haptic_cache_host_cache();

    $checks = [
        __('Page cache', 'optimizr') => $active,
        __('Browser cache', 'optimizr') => !empty($state['browser']),
        __('Compression', 'optimizr') => !empty($state['browser']) && get_option('haptic_cache_gzip', 1),
    ];
    ?>
    <div class="optimizr-sticky">
        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title"><?php esc_html_e('Cache status', 'optimizr'); ?></h2>
                    <p class="optimizr-card__desc"><?php
                    if ($active) {
                        /* translators: 1: number of cached pages, 2: size such as "2 MB" */
                        echo esc_html(sprintf(_n('%1$s page cached (%2$s).', '%1$s pages cached (%2$s).', $stats['count'], 'optimizr'), number_format_i18n($stats['count']) . ($stats['more'] ? '+' : ''), size_format($stats['size'])));
                    } else {
                        esc_html_e('The page cache is off.', 'optimizr');
                    }
                    ?></p>
                </div>
            </div>
            <ul class="optimizr-checks">
                <?php foreach ($checks as $label => $on) : ?>
                    <li class="optimizr-checks__item">
                        <span class="optimizr-checks__icon optimizr-checks__icon--<?php echo $on ? 'ok' : 'off'; ?>"><?php echo wp_kses(haptic_icon($on ? 'check' : 'alert', 14), haptic_kses_ui()); ?></span>
                        <span class="optimizr-checks__label"><?php echo esc_html($label); ?></span>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($plugins as $plugin) : ?>
                    <li class="optimizr-checks__item">
                        <span class="optimizr-checks__icon optimizr-checks__icon--warn"><?php echo wp_kses(haptic_icon('alert', 14), haptic_kses_ui()); ?></span>
                        <span class="optimizr-checks__label"><?php /* translators: %s: cache plugin name */ echo esc_html(sprintf(__('%s is active', 'optimizr'), $plugin)); ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if ($host !== '') : ?>
                    <li class="optimizr-checks__item">
                        <span class="optimizr-checks__icon optimizr-checks__icon--warn"><?php echo wp_kses(haptic_icon('info', 14), haptic_kses_ui()); ?></span>
                        <span class="optimizr-checks__label"><?php /* translators: %s: hosting company or caching proxy */ echo esc_html(sprintf(__('Server cache: %s', 'optimizr'), $host)); ?></span>
                    </li>
                <?php endif; ?>
            </ul>
            <?php if ($active) : ?>
                <div class="optimizr-card__body">
                    <p><a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url(haptic_cache_purge_url()); ?>"><?php echo wp_kses(haptic_icon('refresh', 16), haptic_kses_ui()); ?><?php esc_html_e('Clear all cache', 'optimizr'); ?></a></p>
                    <?php if ($last) : ?>
                        <p class="optimizr-card__hint"><?php /* translators: %s: date and time */ echo esc_html(sprintf(__('Last cleared: %s', 'optimizr'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last))); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php do_action('haptic_cache_aside'); ?>
    </div>
    <?php
});

// Tableau de bord : pastille du module et état du site
add_filter('haptic_module_status', function ($status, $slug) {
    if ($slug !== 'cache' || $status['status'] !== 'ok') {
        return $status;
    }
    if (haptic_cache_blocker() === 'plugin') {
        return ['status' => 'warn', 'label' => __('Another cache plugin', 'optimizr')];
    }
    return haptic_cache_page_active() ? $status : ['status' => 'warn', 'label' => __('Page cache off', 'optimizr')];
}, 10, 2);

add_filter('haptic_site_health_checks', function ($checks) {
    $active = haptic_cache_page_active();
    $checks[] = [
        'module'     => 'cache',
        'status'     => $active || haptic_cache_blocker() === 'plugin' ? 'ok' : 'warn',
        'label'      => $active ? __('Page cache active', 'optimizr') : (haptic_cache_blocker() === 'plugin' ? __('Pages cached by another plugin', 'optimizr') : __('Page cache off', 'optimizr')),
        'link'       => $active ? '' : haptic_admin_tab_url('cache'),
        'link_label' => _x('Enable', 'site health check link', 'optimizr'),
    ];
    return $checks;
});
