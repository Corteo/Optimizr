<?php

if (!defined('ABSPATH')) {
    exit;
}

// Réglages : types de contenu qui acceptent une image à la une
function hpt_options_thumbnail_init()
{
    register_setting(hpt_settings_group('content'), 'hpt_option_featured_image_post_types', [
        'type' => 'array',
        'sanitize_callback' => function ($input) {
            return is_array($input) ? array_values(array_map('sanitize_key', $input)) : [];
        },
    ]);

    add_settings_section(
        'hpt-thumbnails-section',
        'Images à la une',
        function () {
            echo '<p>Types de contenu qui proposent une image de couverture dans l\'éditeur.</p>';
        },
        hpt_settings_page('content')
    );

    add_settings_field(
        'hpt-options-featured-image-post-types',
        'Types de contenu',
        'hpt_options_featured_image_post_types_callback',
        hpt_settings_page('content'),
        'hpt-thumbnails-section',
        ['layout' => 'stacked']
    );
}
add_action('admin_init', 'hpt_options_thumbnail_init');

function hpt_options_featured_image_post_types_callback()
{
    $option = get_option('hpt_option_featured_image_post_types');
    $post_types = get_post_types(array('public' => true), 'objects');

    echo '<div class="optimizr-pills">';
    foreach ($post_types as $post_type) {
        $checked = is_array($option) && in_array($post_type->name, $option) ? 'checked="checked"' : '';
        echo '<label class="optimizr-pill"><input type="checkbox" name="hpt_option_featured_image_post_types[]" value="' . esc_attr($post_type->name) . '" ' . $checked . ' /> ' . esc_html($post_type->label) . '</label>';
    }
    echo '</div>';
}

// Applique le réglage à chaque chargement (tant que l'option n'a jamais été enregistrée, le thème décide)
function hpt_options_apply_settings()
{
    $post_types_option = get_option('hpt_option_featured_image_post_types');

    if (!is_array($post_types_option)) {
        return;
    }

    $post_types = get_post_types(array('public' => true), 'objects');
    foreach ($post_types as $post_type) {
        if (in_array($post_type->name, $post_types_option)) {
            add_post_type_support($post_type->name, 'thumbnail');
        } else {
            remove_post_type_support($post_type->name, 'thumbnail');
        }
    }
}
add_action('init', 'hpt_options_apply_settings');
