<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Codes des variables de motif, dans l'ordre où ils sont remplacés. */
function haptic_seo_variable_codes()
{
    return ['{titre_page}', '{nom_site}', '{slogan}', '{categorie}', '{resume}', '{auteur}', '{type_contenu}', '{date_publication}'];
}

/**
 * Valeur d'une variable de motif pour un contenu.
 * Calculée une seule fois par contenu et par requête : titre, description, JSON-LD et en-tête
 * des thèmes classiques résolvent le même contenu plusieurs fois, et le résumé d'un contenu
 * sans extrait manuel exécute les filtres the_content.
 */
function haptic_seo_variable($code, $post_id)
{
    $post = get_post($post_id);
    // Multisite : un même identifiant existe sur chaque site du réseau
    $key = $post ? get_current_blog_id() . '-' . $post->ID : null;
    $cached = $key ? ($GLOBALS['haptic_seo_variables'][$key] ?? []) : [];

    if (array_key_exists($code, $cached)) {
        return $cached[$code];
    }

    switch ($code) {
        case '{titre_page}':
            $value = get_the_title($post_id);
            break;
        case '{nom_site}':
            $value = get_bloginfo('name');
            break;
        case '{slogan}':
            $value = get_bloginfo('description');
            break;
        case '{categorie}':
            $categories = get_the_category($post_id);
            $value = (!empty($categories)) ? implode(', ', wp_list_pluck($categories, 'name')) : __('Uncategorized', 'optimizr');
            break;
        case '{resume}':
            $value = html_entity_decode(get_the_excerpt($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            break;
        case '{auteur}':
            $value = get_the_author_meta('display_name', $post->post_author);
            break;
        case '{type_contenu}':
            $value = get_post_type_object(get_post_type($post_id))->labels->singular_name;
            break;
        case '{date_publication}':
            $value = get_the_date('d/m/Y', $post_id);
            break;
        default:
            $value = '';
    }

    if ($key) {
        $GLOBALS['haptic_seo_variables'][$key][$code] = $value;
    }

    return $value;
}

/** Contenu modifié pendant la requête (enregistrement, catégories) : ses variables sont recalculées. */
function haptic_seo_forget_variables($post_id)
{
    unset($GLOBALS['haptic_seo_variables'][get_current_blog_id() . '-' . $post_id]);
}
add_action('clean_post_cache', 'haptic_seo_forget_variables');
// L'API REST (éditeur de blocs) enregistre les catégories après le contenu, sans vider son cache
add_action('set_object_terms', 'haptic_seo_forget_variables');
add_action('deleted_term_relationships', 'haptic_seo_forget_variables');

/**
 * Valeurs des variables de motif pour un contenu donné.
 *
 * @return array code de variable => valeur
 */
function haptic_seo_variables($post_id)
{
    $values = [];
    foreach (haptic_seo_variable_codes() as $code) {
        $values[$code] = haptic_seo_variable($code, $post_id);
    }

    return $values;
}

function haptic_generate_seo_content($pattern, $post_id)
{
    // Même résultat qu'un str_replace() de toutes les variables dans cet ordre (une valeur peut
    // elle-même contenir une variable), mais seules les variables présentes sont calculées
    $text = $pattern;
    foreach (haptic_seo_variable_codes() as $code) {
        if (strpos((string) $text, $code) !== false) {
            $text = str_replace($code, haptic_seo_variable($code, $post_id), $text);
        }
    }

    return haptic_seo_clean_text($text);
}

/**
 * Texte brut sur une ligne, sans séparateur orphelin en début ou en fin
 * (ex. « {resume} - {nom_site} » avec un résumé vide donne « cobalt » et non « - cobalt »).
 */
function haptic_seo_clean_text($text)
{
    $text = html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim($text, " \t\n\r\0\x0B-–—|*·•:,;/");
}

/**
 * Titre, description et image SEO d'un contenu.
 * Une valeur saisie dans la metabox prime ; sinon les motifs de l'onglet SEO s'appliquent.
 *
 * @return array{title: string, description: string, image: string, pattern_title: string, pattern_description: string, default_image: string}
 */
function haptic_seo_resolve($post_id)
{
    $pattern_title = haptic_generate_seo_content(get_option('haptic_seo_title_pattern', '{titre_page} - {nom_site}'), $post_id);
    $pattern_description = haptic_generate_seo_content(get_option('haptic_seo_desc_pattern', '{resume} - {nom_site}'), $post_id);

    $custom_title = haptic_seo_clean_text(get_post_meta($post_id, '_haptic_og_title', true));
    $custom_description = haptic_seo_clean_text(get_post_meta($post_id, '_haptic_og_desc', true));
    $custom_image = get_post_meta($post_id, '_haptic_og_image', true);

    return [
        'title' => $custom_title !== '' ? $custom_title : $pattern_title,
        'description' => $custom_description !== '' ? $custom_description : $pattern_description,
        'image' => $custom_image ?: haptic_seo_default_image($post_id),
        'pattern_title' => $pattern_title,
        'pattern_description' => $pattern_description,
        'default_image' => haptic_seo_default_image($post_id),
    ];
}

/**
 * Image de partage par défaut : image à la une, sinon celle de la page d'accueil, sinon l'icône du site.
 */
function haptic_seo_default_image($post_id)
{
    $image = get_the_post_thumbnail_url($post_id, 'full');

    if (!$image && get_option('page_on_front')) {
        $image = get_the_post_thumbnail_url((int) get_option('page_on_front'), 'full');
    }

    return $image ?: get_site_icon_url(512);
}

/**
 * Titre SEO de la page courante, ou null si le plugin ne doit pas le modifier.
 */
function haptic_seo_current_title()
{
    if (!get_option('haptic_enable_seo') || !is_singular()) {
        return null;
    }

    $seo = haptic_seo_resolve(get_queried_object_id());

    return $seo['title'] !== '' ? $seo['title'] : null;
}

// Thèmes modernes (add_theme_support('title-tag')) : balise <title> produite par WordPress
add_filter('pre_get_document_title', function ($title) {
    $seo_title = haptic_seo_current_title();
    return $seo_title !== null ? esc_html($seo_title) : $title;
}, 20);

// Thèmes qui écrivent eux-mêmes <title> dans header.php (ex. wp_title() suivi de bloginfo('name')) :
// l'en-tête est mis en mémoire jusqu'à wp_head(), puis sa balise <title> est remplacée.
add_action('get_header', function () {
    if (current_theme_supports('title-tag') || haptic_seo_current_title() === null) {
        return;
    }
    $GLOBALS['haptic_seo_title_buffer'] = true;
    ob_start();
}, PHP_INT_MAX);

add_action('wp_head', function () {
    if (empty($GLOBALS['haptic_seo_title_buffer'])) {
        return;
    }
    $GLOBALS['haptic_seo_title_buffer'] = false;

    $title = haptic_seo_current_title();
    $html = ob_get_clean();

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- <head> déjà produit par WordPress et le thème, seul le <title> est remplacé (échappé ci-dessous).
    echo preg_replace_callback('#<title\b[^>]*>.*?</title>#is', function () use ($title) {
        return '<title>' . esc_html($title) . '</title>';
    }, $html, 1);
}, 0);

// Une seule source de balises : on coupe celles du socle de thème Haptic quand le module SEO est actif
add_action('wp_head', function () {
    $active = get_option('haptic_enable_seo') || get_option('haptic_enable_opengraph');

    if ($active && function_exists('wdm_add_social_tags') && apply_filters('haptic_seo_disable_theme_tags', true)) {
        remove_action('wp_head', 'wdm_add_social_tags');
    }
}, 0);

function haptic_generate_meta_tags()
{
    if (!is_singular()) {
        return;
    }

    $post_id = get_queried_object_id();

    // Le « ne pas indexer » de la metabox passe par le filtre wp_robots (functions/seo/seo-extras.php)

    $meta_enabled = (bool) get_option('haptic_enable_seo');
    $og_enabled = (bool) get_option('haptic_enable_opengraph');

    if (!$meta_enabled && !$og_enabled) {
        return;
    }

    $seo = haptic_seo_resolve($post_id);

    if ($meta_enabled && $seo['description'] !== '') {
        echo '<meta name="description" content="' . esc_attr($seo['description']) . '">' . PHP_EOL;
    }

    if ($og_enabled) {
        $tags = [
            'og:locale'      => get_locale(),
            'og:type'        => get_post_type($post_id) === 'post' ? 'article' : 'website',
            'og:title'       => $seo['title'],
            'og:description' => $seo['description'],
            'og:url'         => get_permalink($post_id),
            'og:site_name'   => get_bloginfo('name'),
            'og:image'       => $seo['image'],
        ];

        foreach ($tags as $property => $content) {
            if ($content === '' || $content === false || $content === null) {
                continue;
            }
            if (in_array($property, ['og:url', 'og:image'], true)) {
                echo '<meta property="' . esc_attr($property) . '" content="' . esc_url($content) . '">' . PHP_EOL;
            } else {
                echo '<meta property="' . esc_attr($property) . '" content="' . esc_attr($content) . '">' . PHP_EOL;
            }
        }

        echo '<meta name="twitter:card" content="' . ($seo['image'] ? 'summary_large_image' : 'summary') . '">' . PHP_EOL;
    }
}
add_action('wp_head', 'haptic_generate_meta_tags');

// Contenu marqué « ne pas indexer » dans la metabox : ni indexé ni ses liens suivis (filtre de WordPress,
// une seule balise meta robots par page)
add_filter('wp_robots', function ($robots) {
    if (is_singular() && get_post_meta(get_queried_object_id(), '_haptic_noindex', true)) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        unset($robots['follow'], $robots['max-image-preview']);
    }
    return $robots;
});
