<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Sitemap XML d'Optimizr.
 *
 * Écrit dans des fichiers statiques à la racine du site (sitemap.xml, et sitemap-N.xml au-delà de
 * 50 000 URL) : ils fonctionnent aussi avec les permaliens simples, sans règle de réécriture.
 * Sur un multisite, la racine est commune à tous les sites : chaque site sert le sien par WordPress,
 * depuis un cache. Ce cache sert aussi de secours quand la racine n'est pas accessible en écriture.
 * Régénéré en arrière-plan une minute après une modification de contenu, et une fois par jour.
 */

/** Nombre maximal d'URL par fichier (limite Google : 50 000). */
function haptic_sitemap_max_urls()
{
    return (int) apply_filters('haptic_sitemap_max_urls', 50000);
}

function haptic_sitemap_post_types()
{
    return array_values(array_filter((array) get_option('haptic_sitemap_post_types', ['post', 'page'])));
}

/**
 * Contenus à ne jamais envoyer aux moteurs : pages privées de WooCommerce, page d'accueil
 * statique (ajoutée séparément sous l'URL du site), et exclusions ajoutées par filtre.
 */
function haptic_sitemap_excluded_ids()
{
    $ids = [(int) get_option('page_on_front')];

    foreach (['woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id'] as $option) {
        $ids[] = (int) get_option($option);
    }

    return array_values(array_filter(array_unique(apply_filters('haptic_sitemap_excluded_ids', $ids))));
}

/**
 * Parcourt les contenus du sitemap par lots, pour ne jamais tout charger en mémoire.
 *
 * @param callable $callback Reçoit chaque WP_Post
 */
function haptic_sitemap_each_post(callable $callback)
{
    $post_types = haptic_sitemap_post_types();
    if (!$post_types) {
        return;
    }

    $page = 1;
    do {
        $query = new WP_Query([
            'post_type'              => $post_types,
            'post_status'            => 'publish',
            'has_password'           => false,
            'post__not_in'           => haptic_sitemap_excluded_ids(),
            'posts_per_page'         => 500,
            'paged'                  => $page,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'ignore_sticky_posts'    => true,
            // Contenus marqués « ne pas indexer » dans la metabox SEO
            'meta_query'             => [
                'relation' => 'OR',
                ['key' => '_haptic_noindex', 'compare' => 'NOT EXISTS'],
                ['key' => '_haptic_noindex', 'value' => '1', 'compare' => '!='],
            ],
        ]);

        foreach ($query->posts as $post) {
            $callback($post);
        }

        $page++;
        $count = count($query->posts);

        // Gros sites : libère le cache mémoire entre deux lots
        if ($count === 500 && function_exists('wp_cache_flush_runtime')) {
            wp_cache_flush_runtime();
        }
    } while ($count === 500);
}

/**
 * Liste des URL : page d'accueil en premier, puis les contenus.
 *
 * @return array[] ['loc' => ..., 'lastmod' => ...]
 */
function haptic_sitemap_urls()
{
    $urls = [];
    $latest = '';

    haptic_sitemap_each_post(function ($post) use (&$urls, &$latest) {
        $lastmod = get_post_modified_time('c', false, $post);
        $latest = max($latest, (string) $lastmod);
        $urls[] = ['loc' => get_permalink($post), 'lastmod' => $lastmod];
    });

    $front = (int) get_option('page_on_front');
    if ($front) {
        $latest = max($latest, (string) get_post_modified_time('c', false, $front));
    }

    array_unshift($urls, ['loc' => home_url('/'), 'lastmod' => $latest]);

    return $urls;
}

function haptic_sitemap_urlset_xml(array $urls)
{
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $xml->appendChild($xml->createComment(' Généré par Optimizr '));
    $urlset = $xml->appendChild($xml->createElement('urlset'));
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    foreach ($urls as $url) {
        $node = $urlset->appendChild($xml->createElement('url'));
        $node->appendChild($xml->createElement('loc'))->appendChild($xml->createTextNode($url['loc']));
        if (!empty($url['lastmod'])) {
            $node->appendChild($xml->createElement('lastmod', $url['lastmod']));
        }
    }

    return $xml->saveXML();
}

function haptic_sitemap_index_xml(array $files)
{
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $xml->appendChild($xml->createComment(' Généré par Optimizr '));
    $index = $xml->appendChild($xml->createElement('sitemapindex'));
    $index->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    foreach ($files as $file) {
        $node = $index->appendChild($xml->createElement('sitemap'));
        $node->appendChild($xml->createElement('loc'))->appendChild($xml->createTextNode(home_url('/' . $file)));
        $node->appendChild($xml->createElement('lastmod', current_time('c')));
    }

    return $xml->saveXML();
}

/**
 * Écriture atomique : fichier temporaire puis renommage, pour ne jamais servir un fichier à moitié écrit.
 */
function haptic_sitemap_write_file($name, $content)
{
    $path = ABSPATH . $name;
    $tmp = $path . '.tmp';

    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        return false;
    }

    if (!haptic_filesystem()->move($tmp, $path, true)) {
        wp_delete_file($tmp);
        return false;
    }

    return true;
}

/**
 * Contenu des fichiers du sitemap, dans l'ordre d'écriture : au-delà de haptic_sitemap_max_urls() URL,
 * les fichiers de découpage (sitemap-N.xml) puis leur index (sitemap.xml).
 *
 * @param int $count Reçoit le nombre d'URL
 * @return array nom du fichier => XML
 */
function haptic_sitemap_build(&$count = 0)
{
    $urls = haptic_sitemap_urls();
    $count = count($urls);
    $chunks = array_chunk($urls, max(1, haptic_sitemap_max_urls()));

    if (count($chunks) === 1) {
        return ['sitemap.xml' => haptic_sitemap_urlset_xml($chunks[0])];
    }

    $files = [];
    foreach ($chunks as $i => $chunk) {
        $files['sitemap-' . ($i + 1) . '.xml'] = haptic_sitemap_urlset_xml($chunk);
    }
    $files['sitemap.xml'] = haptic_sitemap_index_xml(array_keys($files));

    return $files;
}

/** Copie servie par WordPress (multisite, ou racine non accessible en écriture). */
function haptic_sitemap_cache_set(array $files)
{
    // Renouvelée à chaque génération : la tâche quotidienne passe avant l'expiration
    set_transient('haptic_sitemap_cache', $files, 2 * DAY_IN_SECONDS);
}

/**
 * Le sitemap est-il publié ? Fichier à la racine sur un site simple, servi par WordPress sur un multisite.
 */
function haptic_sitemap_is_published()
{
    return is_multisite() || is_file(ABSPATH . 'sitemap.xml');
}

/**
 * Publie le sitemap : fichiers à la racine sur un site simple, cache servi par WordPress sur un multisite.
 * Si l'écriture échoue, WordPress sert le cache en attendant (permaliens personnalisés).
 *
 * @param array $files nom du fichier => XML (cf. haptic_sitemap_build())
 * @return bool false si l'écriture des fichiers a échoué
 */
function haptic_sitemap_publish(array $files)
{
    // Multisite : jamais de fichier à la racine, commune à tous les sites du réseau
    if (is_multisite()) {
        // Fichier commun écrit par une version précédente : il masquerait le sitemap de chaque site
        haptic_sitemap_delete_physical_files();
        haptic_sitemap_cache_set($files);
        return true;
    }

    $ok = true;
    foreach ($files as $name => $xml) {
        $ok = haptic_sitemap_write_file($name, $xml) && $ok;
    }

    // Fichiers de découpage devenus inutiles (le site a perdu des contenus)
    foreach ((array) glob(ABSPATH . 'sitemap-*.xml') as $path) {
        if (!isset($files[basename($path)]) && haptic_sitemap_is_ours($path)) {
            wp_delete_file($path);
        }
    }

    if ($ok) {
        delete_transient('haptic_sitemap_cache');
    } else {
        haptic_sitemap_cache_set($files);
    }

    return $ok;
}

/**
 * Régénère les fichiers du sitemap.
 *
 * @param string $origin Déclencheur affiché dans l'historique (manuel, automatique, quotidien…)
 * @return bool
 */
function haptic_write_sitemap_to_file($origin = 'manuel')
{
    $ok = haptic_sitemap_publish(haptic_sitemap_build($count));
    haptic_sitemap_record($origin, $count, $ok);

    // L'onglet SEO refait son test d'accessibilité
    haptic_seo_http_check_reset('sitemap');

    return $ok;
}

/** Résultat d'une génération : date, message d'erreur et historique. */
function haptic_sitemap_record($origin, $count, $ok)
{
    if ($ok) {
        update_option('haptic_sitemap_last_generated', current_time('mysql'));
        delete_option('haptic_sitemap_error');
    } else {
        update_option('haptic_sitemap_error', sprintf(
            'Impossible d\'écrire dans %s. Vérifiez les droits d\'écriture du dossier racine du site.',
            untrailingslashit(ABSPATH)
        ), false);
    }

    haptic_sitemap_add_history($origin, $count, $ok);
}

/** Historique des 20 dernières générations, affiché dans l'onglet SEO. */
function haptic_sitemap_add_history($origin, $count, $ok)
{
    $history = (array) get_option('haptic_sitemap_history', []);
    array_unshift($history, [
        'time'   => current_time('mysql'),
        'origin' => $origin,
        'count'  => (int) $count,
        'ok'     => (bool) $ok,
    ]);
    update_option('haptic_sitemap_history', array_slice($history, 0, 20), false);
}

/* ---------- Déclencheurs ---------- */

// Modification d'un contenu : régénération en arrière-plan une minute plus tard.
// Plusieurs modifications rapprochées ne déclenchent qu'une seule régénération.
function haptic_maybe_write_sitemap_on_post_change($post_id)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!in_array(get_post_type($post_id), haptic_sitemap_post_types(), true)) {
        return;
    }

    if (!wp_next_scheduled('haptic_sitemap_regenerate')) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'haptic_sitemap_regenerate');
    }
}
add_action('save_post', 'haptic_maybe_write_sitemap_on_post_change');
add_action('delete_post', 'haptic_maybe_write_sitemap_on_post_change');

add_action('haptic_sitemap_regenerate', function () {
    haptic_write_sitemap_to_file('automatique');
});

// Tâche quotidienne
if (!wp_next_scheduled('haptic_daily_sitemap_cron')) {
    wp_schedule_event(time(), 'daily', 'haptic_daily_sitemap_cron');
}
add_action('haptic_daily_sitemap_cron', function () {
    haptic_write_sitemap_to_file('quotidien');
});

// Un seul sitemap publié : celui de WordPress (wp-sitemap.xml) est désactivé
add_filter('wp_sitemaps_enabled', '__return_false');

// Sans fichier à la racine (multisite, fichier disparu ou non inscriptible), WordPress sert le sitemap (permaliens personnalisés)
function haptic_add_sitemap_endpoint()
{
    add_rewrite_rule('^sitemap\.xml$', 'index.php?haptic_sitemap=1', 'top');
    add_rewrite_rule('^sitemap-([0-9]+)\.xml$', 'index.php?haptic_sitemap=1&haptic_sitemap_part=$matches[1]', 'top');

    // Règles enregistrées sans celles-ci (mise à jour, activation) : WordPress les recalcule à la prochaine requête
    $rules = get_option('rewrite_rules');
    if (is_array($rules) && $rules && !isset($rules['^sitemap-([0-9]+)\.xml$'])) {
        delete_option('rewrite_rules');
    }
}
add_action('init', 'haptic_add_sitemap_endpoint');

function haptic_sitemap_query_var($vars)
{
    $vars[] = 'haptic_sitemap';
    $vars[] = 'haptic_sitemap_part';
    return $vars;
}
add_filter('query_vars', 'haptic_sitemap_query_var');

/**
 * Sert le sitemap depuis le cache. Une seule génération par requête quand il est absent ou expiré,
 * et aucun historique en cas d'échec : chaque visite la répéterait.
 */
function haptic_sitemap_serve()
{
    if (!get_query_var('haptic_sitemap')) {
        return;
    }

    // /sitemap-0.xml ne doit pas servir l'index
    $part = (string) get_query_var('haptic_sitemap_part');
    $name = $part === '' ? 'sitemap.xml' : 'sitemap-' . absint($part) . '.xml';

    // Site simple : fichier présent, mais la requête arrive tout de même à WordPress (configuration du serveur)
    if (!is_multisite() && is_file(ABSPATH . $name)) {
        header('Content-Type: application/xml; charset=utf-8');
        echo haptic_filesystem()->get_contents(ABSPATH . $name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sitemap XML écrit par haptic_sitemap_write_file().
        exit;
    }

    $files = get_transient('haptic_sitemap_cache');
    if (!is_array($files) && !is_multisite() && is_file(ABSPATH . 'sitemap.xml')) {
        // Site simple, sitemap publié : ce fichier de découpage n'existe pas, rien à régénérer
        $files = [];
    } elseif (!is_array($files)) {
        $files = haptic_sitemap_build($count);
        // Site simple : le fichier disparu est réécrit ; en cas d'échec, le cache prend le relais
        if (haptic_sitemap_publish($files) && !is_multisite()) {
            haptic_sitemap_record('à la demande', $count, true);
        }
    }

    if (!isset($files[$name])) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }

    header('Content-Type: application/xml; charset=utf-8');
    echo $files[$name]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML construit par haptic_sitemap_build(), valeurs échappées à la construction.
    exit;
}
// Avant redirect_canonical(), qui redirigerait vers /sitemap.xml/
add_action('template_redirect', 'haptic_sitemap_serve', 1);

// Le robots.txt indique l'adresse du sitemap
function haptic_add_robots_sitemap($output, $public)
{
    // Le robots.txt personnalisé peut déjà contenir la directive
    if (stripos($output, 'sitemap:') === false) {
        $output .= "\nSitemap: " . home_url('/sitemap.xml');
    }
    return $output;
}
add_filter('robots_txt', 'haptic_add_robots_sitemap', 20, 2);

/* ---------- Plan du site pour les visiteurs ---------- */

/**
 * [optimizr_plan_du_site recent="6" types="post,page"]
 * Liste les contenus du sitemap, avec le style du thème. recent="0" masque les dernières mises à jour.
 */
function haptic_sitemap_shortcode($atts)
{
    $atts = shortcode_atts([
        'recent' => 6,
        'types'  => '',
    ], $atts, 'optimizr_plan_du_site');

    $allowed = haptic_sitemap_post_types();
    $types = $atts['types'] !== '' ? array_intersect(array_map('trim', explode(',', $atts['types'])), $allowed) : $allowed;

    $by_type = [];
    $recent = [];
    haptic_sitemap_each_post(function ($post) use (&$by_type, &$recent, $types, $atts) {
        if (!in_array($post->post_type, $types, true)) {
            return;
        }
        $item = ['url' => get_permalink($post), 'title' => get_the_title($post), 'date' => get_the_modified_date('', $post)];
        $by_type[$post->post_type][] = $item;
        if (count($recent) < (int) $atts['recent']) {
            $recent[] = $item;
        }
    });

    ob_start();
    echo '<div class="optimizr-plan-du-site">';

    if ($recent) {
        echo '<section class="optimizr-plan-du-site__recent"><h2>Dernières mises à jour</h2><ul>';
        foreach ($recent as $item) {
            printf('<li><a href="%s">%s</a> <time>%s</time></li>', esc_url($item['url']), esc_html($item['title']), esc_html($item['date']));
        }
        echo '</ul></section>';
    }

    foreach ($types as $type) {
        if (empty($by_type[$type])) {
            continue;
        }
        usort($by_type[$type], function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });
        $object = get_post_type_object($type);
        echo '<section class="optimizr-plan-du-site__type"><h2>' . esc_html($object ? $object->labels->name : $type) . '</h2><ul>';
        foreach ($by_type[$type] as $item) {
            printf('<li><a href="%s">%s</a></li>', esc_url($item['url']), esc_html($item['title']));
        }
        echo '</ul></section>';
    }

    echo '</div>';
    return ob_get_clean();
}
add_shortcode('optimizr_plan_du_site', 'haptic_sitemap_shortcode');
