<?php

if (!defined('ABSPATH')) {
    exit;
}

function hpt_generate_meta_tags()
{
    $meta_enabled = (bool) get_option('hpt_enable_seo');
    $og_enabled = (bool) get_option('hpt_enable_opengraph');

    if (is_singular()) {
        global $post;

        // Le noindex est un choix par contenu : toujours respecté
        if (get_post_meta($post->ID, '_hpt_noindex', true)) {
            echo '<meta name="robots" content="noindex, nofollow">' . PHP_EOL;
        }

        if (!$meta_enabled && !$og_enabled) {
            return;
        }


        $title_pattern = get_option('hpt_seo_title_pattern', '{titre_page} - {nom_site}');
        $desc_pattern = get_option('hpt_seo_desc_pattern', '{resume} - {nom_site}');

        $seo_title = hpt_generate_seo_content($title_pattern, $post->ID);
        $seo_desc = hpt_generate_seo_content($desc_pattern, $post->ID);

        $og_title = get_post_meta($post->ID, '_hpt_og_title', true) ?: $seo_title;
        $og_desc = get_post_meta($post->ID, '_hpt_og_desc', true) ?: $seo_desc;
        $og_desc = html_entity_decode($og_desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $og_image = get_post_meta($post->ID, '_hpt_og_image', true) ?: get_the_post_thumbnail_url($post, 'full');

        if ($meta_enabled) {
            echo '<meta name="description" content="' . esc_attr($og_desc) . '">' . PHP_EOL;
        }

        if ($og_enabled) {
            echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . PHP_EOL;
            echo '<meta property="og:description" content="' . esc_attr($og_desc) . '">' . PHP_EOL;
            if ($og_image) {
                echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . PHP_EOL;
            }
        }
    }
}
add_action('wp_head', 'hpt_generate_meta_tags');


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

    return str_replace(array_keys($replacements), array_values($replacements), $pattern);
}
