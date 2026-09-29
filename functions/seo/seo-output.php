<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Valeurs des variables de motif pour un contenu donné.
 *
 * @return array code de variable => valeur
 */
function hpt_seo_variables($post_id)
{
    $post = get_post($post_id);
    $site_name = get_bloginfo('name');
    $site_slogan = get_bloginfo('description');
    $post_title = get_the_title($post_id);
    $post_excerpt = get_the_excerpt($post_id);
    $post_excerpt = html_entity_decode($post_excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $author_name = get_the_author_meta('display_name', $post->post_author);
    $categories = get_the_category($post_id);
    $category_names = (!empty($categories)) ? implode(', ', wp_list_pluck($categories, 'name')) : 'Sans catégorie';
    $post_type = get_post_type_object(get_post_type($post_id))->labels->singular_name;
    $post_date = get_the_date('d/m/Y', $post_id);

    return [
        '{titre_page}' => $post_title,
        '{nom_site}' => $site_name,
        '{slogan}' => $site_slogan,
        '{categorie}' => $category_names,
        '{resume}' => $post_excerpt,
        '{auteur}' => $author_name,
        '{type_contenu}' => $post_type,
        '{date_publication}' => $post_date,
    ];
}

function hpt_generate_seo_content($pattern, $post_id)
{
    $replacements = hpt_seo_variables($post_id);
    $text = str_replace(array_keys($replacements), array_values($replacements), $pattern);

    return hpt_seo_clean_text($text);
}

/**
 * Texte brut sur une ligne, sans séparateur orphelin en début ou en fin
 * (ex. « {resume} - {nom_site} » avec un résumé vide donne « cobalt » et non « - cobalt »).
 */
function hpt_seo_clean_text($text)
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
function hpt_seo_resolve($post_id)
{
    $pattern_title = hpt_generate_seo_content(get_option('hpt_seo_title_pattern', '{titre_page} - {nom_site}'), $post_id);
    $pattern_description = hpt_generate_seo_content(get_option('hpt_seo_desc_pattern', '{resume} - {nom_site}'), $post_id);

    $custom_title = hpt_seo_clean_text(get_post_meta($post_id, '_hpt_og_title', true));
    $custom_description = hpt_seo_clean_text(get_post_meta($post_id, '_hpt_og_desc', true));
    $custom_image = get_post_meta($post_id, '_hpt_og_image', true);

    return [
        'title' => $custom_title !== '' ? $custom_title : $pattern_title,
        'description' => $custom_description !== '' ? $custom_description : $pattern_description,
        'image' => $custom_image ?: hpt_seo_default_image($post_id),
        'pattern_title' => $pattern_title,
        'pattern_description' => $pattern_description,
        'default_image' => hpt_seo_default_image($post_id),
    ];
}

/**
 * Image de partage par défaut : image à la une, sinon celle de la page d'accueil, sinon l'icône du site.
 */
function hpt_seo_default_image($post_id)
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
function hpt_seo_current_title()
{
    if (!get_option('hpt_enable_seo') || !is_singular()) {
        return null;
    }

    $seo = hpt_seo_resolve(get_queried_object_id());

    return $seo['title'] !== '' ? $seo['title'] : null;
}

// Thèmes modernes (add_theme_support('title-tag')) : balise <title> produite par WordPress
add_filter('pre_get_document_title', function ($title) {
    $seo_title = hpt_seo_current_title();
    return $seo_title !== null ? esc_html($seo_title) : $title;
}, 20);

// Thèmes qui écrivent eux-mêmes <title> dans header.php (ex. wp_title() suivi de bloginfo('name')) :
// l'en-tête est mis en mémoire jusqu'à wp_head(), puis sa balise <title> est remplacée.
add_action('get_header', function () {
    if (current_theme_supports('title-tag') || hpt_seo_current_title() === null) {
        return;
    }
    $GLOBALS['hpt_seo_title_buffer'] = true;
    ob_start();
}, PHP_INT_MAX);

add_action('wp_head', function () {
    if (empty($GLOBALS['hpt_seo_title_buffer'])) {
        return;
    }
    $GLOBALS['hpt_seo_title_buffer'] = false;

    $title = hpt_seo_current_title();
    $html = ob_get_clean();

    echo preg_replace_callback('#<title\b[^>]*>.*?</title>#is', function () use ($title) {
        return '<title>' . esc_html($title) . '</title>';
    }, $html, 1);
}, 0);

// Une seule source de balises : on coupe celles du socle de thème Haptic quand le module SEO est actif
add_action('wp_head', function () {
    $active = get_option('hpt_enable_seo') || get_option('hpt_enable_opengraph');

    if ($active && function_exists('wdm_add_social_tags') && apply_filters('hpt_seo_disable_theme_tags', true)) {
        remove_action('wp_head', 'wdm_add_social_tags');
    }
}, 0);

function hpt_generate_meta_tags()
{
    if (!is_singular()) {
        return;
    }

    $post_id = get_queried_object_id();

    // Le noindex est un choix par contenu : toujours respecté
    if (get_post_meta($post_id, '_hpt_noindex', true)) {
        echo '<meta name="robots" content="noindex, nofollow">' . PHP_EOL;
    }

    $meta_enabled = (bool) get_option('hpt_enable_seo');
    $og_enabled = (bool) get_option('hpt_enable_opengraph');

    if (!$meta_enabled && !$og_enabled) {
        return;
    }

    $seo = hpt_seo_resolve($post_id);

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
            $value = in_array($property, ['og:url', 'og:image'], true) ? esc_url($content) : esc_attr($content);
            echo '<meta property="' . esc_attr($property) . '" content="' . $value . '">' . PHP_EOL;
        }

        echo '<meta name="twitter:card" content="' . ($seo['image'] ? 'summary_large_image' : 'summary') . '">' . PHP_EOL;
    }
}
add_action('wp_head', 'hpt_generate_meta_tags');
