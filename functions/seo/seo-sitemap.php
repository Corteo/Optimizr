<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Sitemap XML d'Optimizr.
 *
 * Écrit dans des fichiers statiques à la racine du site (sitemap.xml, et sitemap-N.xml au-delà de
 * 50 000 URL) : ils fonctionnent aussi avec les permaliens simples, sans règle de réécriture.
 * Régénéré en arrière-plan une minute après une modification de contenu, et une fois par jour.
 */

/** Nombre maximal d'URL par fichier (limite Google : 50 000). */
function hpt_sitemap_max_urls()
{
    return (int) apply_filters('hpt_sitemap_max_urls', 50000);
}

function hpt_sitemap_post_types()
{
    return array_values(array_filter((array) get_option('hpt_sitemap_post_types', ['post', 'page'])));
}

/**
 * Contenus à ne jamais envoyer aux moteurs : pages privées de WooCommerce, page d'accueil
 * statique (ajoutée séparément sous l'URL du site), et exclusions ajoutées par filtre.
 */
function hpt_sitemap_excluded_ids()
{
    $ids = [(int) get_option('page_on_front')];

    foreach (['woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id'] as $option) {
        $ids[] = (int) get_option($option);
    }

    return array_values(array_filter(array_unique(apply_filters('hpt_sitemap_excluded_ids', $ids))));
}

/**
 * Parcourt les contenus du sitemap par lots, pour ne jamais tout charger en mémoire.
 *
 * @param callable $callback Reçoit chaque WP_Post
 */
function hpt_sitemap_each_post(callable $callback)
{
    $post_types = hpt_sitemap_post_types();
    if (!$post_types) {
        return;
    }

    $page = 1;
    do {
        $query = new WP_Query([
            'post_type'              => $post_types,
            'post_status'            => 'publish',
            'has_password'           => false,
            'post__not_in'           => hpt_sitemap_excluded_ids(),
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
                ['key' => '_hpt_noindex', 'compare' => 'NOT EXISTS'],
                ['key' => '_hpt_noindex', 'value' => '1', 'compare' => '!='],
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
function hpt_sitemap_urls()
{
    $urls = [];
    $latest = '';

    hpt_sitemap_each_post(function ($post) use (&$urls, &$latest) {
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

function hpt_sitemap_urlset_xml(array $urls)
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

function hpt_sitemap_index_xml(array $files)
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
function hpt_sitemap_write_file($name, $content)
{
    $path = ABSPATH . $name;
    $tmp = $path . '.tmp';

    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        return false;
    }

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }

    return true;
}

/**
 * Régénère les fichiers du sitemap.
 *
 * @param string $origin Déclencheur affiché dans l'historique (manuel, automatique, quotidien…)
 * @return bool
 */
function haptic_GPT_write_sitemap_to_file($origin = 'manuel')
{
    $urls = hpt_sitemap_urls();
    $chunks = array_chunk($urls, max(1, hpt_sitemap_max_urls()));
    $ok = true;

    if (count($chunks) === 1) {
        $ok = hpt_sitemap_write_file('sitemap.xml', hpt_sitemap_urlset_xml($chunks[0]));
        $files = ['sitemap.xml'];
    } else {
        $files = [];
        foreach ($chunks as $i => $chunk) {
            $name = 'sitemap-' . ($i + 1) . '.xml';
            $ok = hpt_sitemap_write_file($name, hpt_sitemap_urlset_xml($chunk)) && $ok;
            $files[] = $name;
        }
        $ok = hpt_sitemap_write_file('sitemap.xml', hpt_sitemap_index_xml($files)) && $ok;
        $files[] = 'sitemap.xml';
    }

    // Fichiers de découpage devenus inutiles (le site a perdu des contenus)
    foreach ((array) glob(ABSPATH . 'sitemap-*.xml') as $path) {
        if (!in_array(basename($path), $files, true) && hpt_sitemap_is_ours($path)) {
            @unlink($path);
        }
    }

    if ($ok) {
        update_option('hpt_sitemap_last_generated', current_time('mysql'));
        delete_option('hpt_sitemap_error');
    } else {
        update_option('hpt_sitemap_error', sprintf(
            'Impossible d\'écrire dans %s. Vérifiez les droits d\'écriture du dossier racine du site.',
            untrailingslashit(ABSPATH)
        ), false);
    }

    hpt_sitemap_add_history($origin, count($urls), $ok);

    return $ok;
}

/** Historique des 20 dernières générations, affiché dans l'onglet SEO. */
function hpt_sitemap_add_history($origin, $count, $ok)
{
    $history = (array) get_option('hpt_sitemap_history', []);
    array_unshift($history, [
        'time'   => current_time('mysql'),
        'origin' => $origin,
        'count'  => (int) $count,
        'ok'     => (bool) $ok,
    ]);
    update_option('hpt_sitemap_history', array_slice($history, 0, 20), false);
}

/* ---------- Déclencheurs ---------- */

// Modification d'un contenu : régénération en arrière-plan une minute plus tard.
// Plusieurs modifications rapprochées ne déclenchent qu'une seule régénération.
function haptic_GPT_maybe_write_sitemap_on_post_change($post_id)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!in_array(get_post_type($post_id), hpt_sitemap_post_types(), true)) {
        return;
    }

    if (!wp_next_scheduled('hpt_sitemap_regenerate')) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'hpt_sitemap_regenerate');
    }
}
add_action('save_post', 'haptic_GPT_maybe_write_sitemap_on_post_change');
add_action('delete_post', 'haptic_GPT_maybe_write_sitemap_on_post_change');

add_action('hpt_sitemap_regenerate', function () {
    haptic_GPT_write_sitemap_to_file('automatique');
});

// Tâche quotidienne
if (!wp_next_scheduled('haptic_GPT_daily_sitemap_cron')) {
    wp_schedule_event(time(), 'daily', 'haptic_GPT_daily_sitemap_cron');
}
add_action('haptic_GPT_daily_sitemap_cron', function () {
    haptic_GPT_write_sitemap_to_file('quotidien');
});

// Un seul sitemap publié : celui de WordPress (wp-sitemap.xml) est désactivé
add_filter('wp_sitemaps_enabled', '__return_false');

// Si le fichier a disparu, /sitemap.xml est régénéré à la demande (permaliens personnalisés)
function haptic_GPT_add_sitemap_endpoint()
{
    add_rewrite_rule('^sitemap\.xml$', 'index.php?hpt_sitemap=1', 'top');
}
add_action('init', 'haptic_GPT_add_sitemap_endpoint');

function haptic_GPT_sitemap_query_var($vars)
{
    $vars[] = 'hpt_sitemap';
    return $vars;
}
add_filter('query_vars', 'haptic_GPT_sitemap_query_var');

add_action('template_redirect', function () {
    if (!get_query_var('hpt_sitemap')) {
        return;
    }

    $path = ABSPATH . 'sitemap.xml';
    if (!file_exists($path)) {
        haptic_GPT_write_sitemap_to_file('à la demande');
    }

    header('Content-Type: application/xml; charset=utf-8');
    if (file_exists($path)) {
        readfile($path);
    } else {
        echo hpt_sitemap_urlset_xml(array_slice(hpt_sitemap_urls(), 0, hpt_sitemap_max_urls()));
    }
    exit;
});

// Le robots.txt indique l'adresse du sitemap
function haptic_GPT_add_robots_sitemap($output, $public)
{
    // Le robots.txt personnalisé peut déjà contenir la directive
    if (stripos($output, 'sitemap:') === false) {
        $output .= "\nSitemap: " . home_url('/sitemap.xml');
    }
    return $output;
}
add_filter('robots_txt', 'haptic_GPT_add_robots_sitemap', 20, 2);

/* ---------- Plan du site pour les visiteurs ---------- */

/**
 * [optimizr_plan_du_site recent="6" types="post,page"]
 * Liste les contenus du sitemap, avec le style du thème. recent="0" masque les dernières mises à jour.
 */
function hpt_sitemap_shortcode($atts)
{
    $atts = shortcode_atts([
        'recent' => 6,
        'types'  => '',
    ], $atts, 'optimizr_plan_du_site');

    $allowed = hpt_sitemap_post_types();
    $types = $atts['types'] !== '' ? array_intersect(array_map('trim', explode(',', $atts['types'])), $allowed) : $allowed;

    $by_type = [];
    $recent = [];
    hpt_sitemap_each_post(function ($post) use (&$by_type, &$recent, $types, $atts) {
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
add_shortcode('optimizr_plan_du_site', 'hpt_sitemap_shortcode');
