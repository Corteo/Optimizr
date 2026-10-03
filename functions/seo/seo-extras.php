<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Module SEO, compléments : indexation, vérification des moteurs, IndexNow et données structurées.
 * Toutes ces options sont désactivées par défaut.
 */

add_action('admin_init', function () {
    $group = hpt_settings_group('seo');
    $page = hpt_settings_page('seo');

    foreach (['noindex_search', 'noindex_date', 'noindex_tag', 'attachment_redirect', 'indexnow', 'schema'] as $toggle) {
        register_setting($group, 'hpt_seo_' . $toggle, ['sanitize_callback' => 'absint']);
    }
    foreach (['verify_google', 'verify_bing'] as $code) {
        register_setting($group, 'hpt_seo_' . $code, ['sanitize_callback' => 'hpt_seo_sanitize_verification']);
    }

    add_settings_section('hpt_seo_indexing_section', 'Indexation', function () {
        echo '<p>Évite que Google indexe des pages sans valeur, qui diluent le référencement du site.</p>';
    }, $page);
    hpt_seo_toggle('noindex_search', 'Résultats de recherche interne', 'Les pages de résultats du moteur de recherche du site ne sont pas indexées (recommandation de Google).', 'hpt_seo_indexing_section');
    hpt_seo_toggle('noindex_date', 'Archives par date', 'Les listes d\'articles par jour, mois ou année ne sont pas indexées.', 'hpt_seo_indexing_section');
    hpt_seo_toggle('noindex_tag', 'Archives d\'étiquettes', 'Les listes d\'articles par étiquette ne sont pas indexées : utile si les étiquettes sont nombreuses et peu fournies.', 'hpt_seo_indexing_section');
    hpt_seo_toggle('attachment_redirect', 'Pages des pièces jointes', 'WordPress crée une page par image importée. Elle redirige vers le contenu où l\'image est utilisée, ou vers le fichier.', 'hpt_seo_indexing_section');

    add_settings_section('hpt_seo_engines_section', 'Moteurs de recherche', function () {
        echo '<p>Prouve à Google et Bing que vous êtes propriétaire du site, et signale les nouveaux contenus.</p>';
    }, $page);
    add_settings_field('hpt_seo_verify_google', 'Google Search Console', 'hpt_seo_verification_callback', $page, 'hpt_seo_engines_section', [
        'label_for' => 'hpt_seo_verify_google',
        'option' => 'hpt_seo_verify_google',
        'description' => 'Méthode « Balise HTML » : collez la balise ou seulement son code.',
    ]);
    add_settings_field('hpt_seo_verify_bing', 'Bing Webmaster Tools', 'hpt_seo_verification_callback', $page, 'hpt_seo_engines_section', [
        'label_for' => 'hpt_seo_verify_bing',
        'option' => 'hpt_seo_verify_bing',
        'description' => 'Méthode « Balise méta » : collez la balise ou seulement son code.',
    ]);
    add_settings_field('hpt_seo_indexnow', 'IndexNow', 'hpt_seo_indexnow_callback', $page, 'hpt_seo_engines_section', [
        'description' => 'Signale chaque publication ou mise à jour à Bing, Yandex, Seznam et Naver en quelques secondes. Google n\'utilise pas IndexNow.',
    ]);

    add_settings_section('hpt_seo_schema_section', 'Données structurées', function () {
        echo '<p>Informations lisibles par Google (JSON-LD) : elles permettent les résultats enrichis.</p>';
    }, $page);
    add_settings_field('hpt_seo_schema', 'Activer', 'hpt_seo_schema_callback', $page, 'hpt_seo_schema_section', [
        'description' => 'Site et organisation sur l\'accueil, fil d\'Ariane sur chaque contenu, Article pour les articles, Évènement pour le type Évènements.',
    ]);
});

function hpt_seo_toggle($name, $label, $description, $section)
{
    add_settings_field('hpt_seo_' . $name, $label, function () use ($name, $label) {
        echo hpt_switch_input('hpt_seo_' . $name, get_option('hpt_seo_' . $name), $label);
    }, hpt_settings_page('seo'), $section, ['description' => $description]);
}

/** Garde le code de vérification seul, même si la balise complète est collée. */
function hpt_seo_sanitize_verification($value)
{
    $value = trim(wp_unslash((string) $value));
    if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $value, $match)) {
        $value = $match[1];
    }
    return preg_replace('/[^A-Za-z0-9_\-]/', '', $value);
}

function hpt_seo_verification_callback($args)
{
    printf('<input type="text" name="%1$s" id="%1$s" value="%2$s" spellcheck="false" autocomplete="off">', esc_attr($args['option']), esc_attr(get_option($args['option'], '')));
}

function hpt_seo_indexnow_callback()
{
    echo hpt_switch_input('hpt_seo_indexnow', get_option('hpt_seo_indexnow'), 'IndexNow');
    $key = get_option('hpt_indexnow_key');
    if (get_option('hpt_seo_indexnow') && $key) {
        $last = get_option('hpt_indexnow_last');
        printf(
            '<p class="optimizr-field__help">Clé : <a href="%s" target="_blank" rel="noopener">%s</a>%s</p>',
            esc_url(hpt_indexnow_key_url()),
            esc_html($key),
            $last ? esc_html(sprintf(' · dernier signalement : %s', mysql2date('j F Y à H:i', $last['time']) . ' (' . $last['url'] . ')')) : ''
        );
    }
    if (hpt_indexnow_is_local()) {
        echo '<p class="optimizr-field__help">Site local : aucun signalement n\'est envoyé tant que le site n\'est pas en ligne.</p>';
    }
}

function hpt_seo_schema_callback()
{
    echo hpt_switch_input('hpt_seo_schema', get_option('hpt_seo_schema'), 'Données structurées');
    if (get_option('hpt_seo_schema')) {
        printf('<p class="optimizr-field__help"><a href="%s" target="_blank" rel="noopener">Tester la page d\'accueil avec Google</a></p>', esc_url('https://search.google.com/test/rich-results?url=' . rawurlencode(home_url('/'))));
    }
}

/* ---------- Indexation ---------- */

// Consignes robots : filtre de WordPress (une seule balise meta robots par page)
add_filter('wp_robots', function ($robots) {
    $noindex = (is_search() && get_option('hpt_seo_noindex_search'))
        || (is_date() && get_option('hpt_seo_noindex_date'))
        || (is_tag() && get_option('hpt_seo_noindex_tag'))
        || (is_singular() && get_post_meta(get_queried_object_id(), '_hpt_noindex', true));

    if ($noindex) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
        unset($robots['max-image-preview']);
    }
    // Contenu marqué « ne pas indexer » dans la metabox : liens non suivis non plus
    if (is_singular() && get_post_meta(get_queried_object_id(), '_hpt_noindex', true)) {
        unset($robots['follow']);
        $robots['nofollow'] = true;
    }
    return $robots;
});

add_action('template_redirect', function () {
    if (!get_option('hpt_seo_attachment_redirect') || !is_attachment()) {
        return;
    }
    $attachment = get_queried_object();
    $parent = $attachment->post_parent ? get_post($attachment->post_parent) : null;
    $url = $parent && $parent->post_status === 'publish' ? get_permalink($parent) : wp_get_attachment_url($attachment->ID);
    if ($url) {
        wp_safe_redirect($url, 301, 'Optimizr');
        exit;
    }
}, 1);

/* ---------- Vérification des moteurs ---------- */

add_action('wp_head', function () {
    if (!is_front_page()) {
        return;
    }
    if ($code = get_option('hpt_seo_verify_google')) {
        echo '<meta name="google-site-verification" content="' . esc_attr($code) . '">' . PHP_EOL;
    }
    if ($code = get_option('hpt_seo_verify_bing')) {
        echo '<meta name="msvalidate.01" content="' . esc_attr($code) . '">' . PHP_EOL;
    }
}, 2);

/* ---------- IndexNow ---------- */

/** Site non accessible depuis Internet (local, préproduction en .test / .local) : rien à signaler. */
function hpt_indexnow_is_local()
{
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    return $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) || preg_match('/\.(test|local|localhost|invalid|example)$/', $host);
}

function hpt_indexnow_key_url()
{
    return home_url('/' . get_option('hpt_indexnow_key') . '.txt');
}

/** Clé créée à l'activation, publiée dans un fichier texte à la racine du site (exigence du protocole). */
function hpt_indexnow_setup($enabled)
{
    $key = get_option('hpt_indexnow_key');
    if ($enabled && !$key) {
        $key = wp_generate_password(32, false, false);
        update_option('hpt_indexnow_key', $key, false);
    }
    if (!$key) {
        return;
    }
    $file = ABSPATH . $key . '.txt';
    if ($enabled) {
        @file_put_contents($file, $key);
    } elseif (is_file($file) && trim((string) file_get_contents($file)) === $key) {
        @unlink($file);
    }
}
add_action('update_option_hpt_seo_indexnow', function ($old, $new) {
    hpt_indexnow_setup((bool) $new);
}, 10, 2);
add_action('add_option_hpt_seo_indexnow', function ($option, $value) {
    hpt_indexnow_setup((bool) $value);
}, 10, 2);

function hpt_indexnow_submit($url)
{
    if (!get_option('hpt_seo_indexnow') || !get_option('hpt_indexnow_key') || hpt_indexnow_is_local()) {
        return;
    }
    // Un même contenu n'est pas signalé plus d'une fois toutes les 10 minutes
    $lock = 'hpt_indexnow_' . md5($url);
    if (get_transient($lock)) {
        return;
    }
    set_transient($lock, 1, 10 * MINUTE_IN_SECONDS);

    wp_remote_post('https://api.indexnow.org/indexnow', [
        'blocking' => false,
        'timeout'  => 5,
        'headers'  => ['Content-Type' => 'application/json; charset=utf-8'],
        'body'     => wp_json_encode([
            'host'        => wp_parse_url(home_url(), PHP_URL_HOST),
            'key'         => get_option('hpt_indexnow_key'),
            'keyLocation' => hpt_indexnow_key_url(),
            'urlList'     => [$url],
        ]),
    ]);
    update_option('hpt_indexnow_last', ['time' => current_time('mysql'), 'url' => $url], false);
}

// Publication, mise à jour ou dépublication : l'adresse est signalée (les moteurs constatent eux-mêmes la suppression)
add_action('transition_post_status', function ($new, $old, $post) {
    if (wp_is_post_revision($post) || wp_is_post_autosave($post) || !is_post_type_viewable($post->post_type)) {
        return;
    }
    if ($new === 'publish' || $old === 'publish') {
        hpt_indexnow_submit(get_permalink($post));
    }
}, 10, 3);

/* ---------- Données structurées ---------- */

function hpt_schema_enabled()
{
    return (bool) get_option('hpt_seo_schema');
}

/** Organisation qui publie le site (référencée par les articles). */
function hpt_schema_organization()
{
    $logo = function_exists('hpt_local') ? hpt_local('logo', get_site_icon_url(512)) : get_site_icon_url(512);
    return array_filter([
        '@type' => 'Organization',
        '@id'   => home_url('/#organisation'),
        'name'  => function_exists('hpt_local') ? hpt_local('name', get_bloginfo('name')) : get_bloginfo('name'),
        'url'   => home_url('/'),
        'logo'  => $logo ?: null,
    ]);
}

/** Fil d'Ariane du contenu affiché : accueil, parents ou catégorie, contenu. */
function hpt_schema_breadcrumb($post_id)
{
    $items = [['name' => get_bloginfo('name'), 'url' => home_url('/')]];
    $post = get_post($post_id);

    if ($post->post_type === 'page') {
        foreach (array_reverse(get_post_ancestors($post)) as $ancestor) {
            $items[] = ['name' => get_the_title($ancestor), 'url' => get_permalink($ancestor)];
        }
    } elseif ($post->post_type === 'post') {
        $categories = get_the_category($post_id);
        if ($categories) {
            $items[] = ['name' => $categories[0]->name, 'url' => get_category_link($categories[0])];
        }
    } else {
        $archive = get_post_type_archive_link($post->post_type);
        $object = get_post_type_object($post->post_type);
        if ($archive && $object) {
            $items[] = ['name' => $object->labels->name, 'url' => $archive];
        }
    }
    $items[] = ['name' => get_the_title($post_id), 'url' => get_permalink($post_id)];

    $list = [];
    foreach ($items as $position => $item) {
        $list[] = ['@type' => 'ListItem', 'position' => $position + 1, 'name' => wp_strip_all_tags($item['name']), 'item' => $item['url']];
    }
    return ['@type' => 'BreadcrumbList', '@id' => get_permalink($post_id) . '#fil-ariane', 'itemListElement' => $list];
}

function hpt_schema_article($post_id)
{
    $seo = hpt_seo_resolve($post_id);
    $author_id = (int) get_post_field('post_author', $post_id);

    return array_filter([
        '@type'            => 'Article',
        '@id'              => get_permalink($post_id) . '#article',
        'headline'         => mb_substr($seo['title'], 0, 110),
        'description'      => $seo['description'],
        'image'            => $seo['image'] ?: null,
        'datePublished'    => get_post_time('c', false, $post_id),
        'dateModified'     => get_post_modified_time('c', false, $post_id),
        'author'           => ['@type' => 'Person', 'name' => get_the_author_meta('display_name', $author_id), 'url' => get_author_posts_url($author_id)],
        'publisher'        => ['@id' => home_url('/#organisation')],
        'mainEntityOfPage' => get_permalink($post_id),
        'inLanguage'       => get_bloginfo('language'),
    ]);
}

/** Évènement (type Évènements du module Contenus, champs ACF date_debut_evenement, date_fin_evenement, lieu_evenement). */
function hpt_schema_event($post_id)
{
    $field = function ($name) use ($post_id) {
        return function_exists('get_field') ? get_field($name, $post_id, false) : get_post_meta($post_id, $name, true);
    };
    // ACF enregistre les dates au format AAAAMMJJ
    $date = function ($value) {
        $value = preg_replace('/\D/', '', (string) $value);
        return strlen($value) === 8 ? substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2) : '';
    };

    $start = $date($field('date_debut_evenement'));
    if (!$start) {
        return null;
    }
    $seo = hpt_seo_resolve($post_id);
    $place = trim((string) $field('lieu_evenement'));
    $local_address = function_exists('hpt_local') ? trim(hpt_local('street') . ', ' . hpt_local('postal_code') . ' ' . hpt_local('city'), ', ') : '';

    return array_filter([
        '@type'               => 'Event',
        '@id'                 => get_permalink($post_id) . '#evenement',
        'name'                => get_the_title($post_id),
        'description'         => $seo['description'],
        'image'               => $seo['image'] ?: null,
        'startDate'           => $start,
        'endDate'             => $date($field('date_fin_evenement')) ?: $start,
        'eventStatus'         => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location'            => [
            '@type'   => 'Place',
            'name'    => $place ?: get_bloginfo('name'),
            'address' => $place ?: ($local_address ?: get_bloginfo('name')),
        ],
        'organizer'           => ['@id' => home_url('/#organisation')],
        'url'                 => get_permalink($post_id),
    ]);
}

/** Toutes les données de la page affichée, en un seul graphe JSON-LD. */
function hpt_schema_graph()
{
    $graph = [];
    $local = function_exists('hpt_local_schema') ? hpt_local_schema() : null;
    $scope = function_exists('hpt_local') ? hpt_local('schema_scope', 'home') : 'off';

    // L'établissement local fait office d'organisation quand il est renseigné
    $organization = hpt_schema_organization();
    if ($local && $scope !== 'off' && (is_front_page() || $scope === 'all')) {
        $local['@id'] = home_url('/#organisation');
        $organization = $local;
    }

    if (is_front_page()) {
        $graph[] = [
            '@type'      => 'WebSite',
            '@id'        => home_url('/#site'),
            'url'        => home_url('/'),
            'name'       => get_bloginfo('name'),
            'inLanguage' => get_bloginfo('language'),
            'publisher'  => ['@id' => home_url('/#organisation')],
        ];
    }

    if (is_singular() && !is_front_page()) {
        $post_id = get_queried_object_id();
        $graph[] = hpt_schema_breadcrumb($post_id);

        $type = get_post_type($post_id);
        if ($type === 'post') {
            $graph[] = hpt_schema_article($post_id);
        } elseif ($type === 'evenements' && ($event = hpt_schema_event($post_id))) {
            $graph[] = $event;
        }
    }

    if ($graph || $organization !== hpt_schema_organization()) {
        // Organisation référencée par le site, les articles et les évènements
        $graph[] = $organization;
    }

    return apply_filters('hpt_schema_graph', $graph);
}

add_action('wp_head', function () {
    if (!hpt_schema_enabled()) {
        return;
    }
    $graph = hpt_schema_graph();
    if ($graph) {
        // JSON_HEX_TAG : un « </script> » dans un titre ou un résumé ne peut pas fermer la balise
        echo '<script type="application/ld+json">' . wp_json_encode(['@context' => 'https://schema.org', '@graph' => array_values($graph)], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . PHP_EOL;
    }
}, 20);
