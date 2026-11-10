<?php

if (!defined('ABSPATH')) {
    exit;
}

// Réglages : types de contenu qui acceptent une image à la une
function haptic_options_thumbnail_init()
{
    register_setting(haptic_settings_group('images'), 'haptic_option_featured_image_post_types', [
        'type' => 'array',
        'sanitize_callback' => function ($input) {
            return is_array($input) ? array_values(array_map('sanitize_key', $input)) : [];
        },
    ]);

    add_settings_section(
        'haptic-thumbnails-section',
        __('Featured images', 'optimizr'),
        function () {
            echo '<p>' . esc_html__('Content types that offer a cover image in the editor.', 'optimizr') . '</p>';
        },
        haptic_settings_page('images')
    );

    add_settings_field(
        'haptic-options-featured-image-post-types',
        __('Content types', 'optimizr'),
        'haptic_options_featured_image_post_types_callback',
        haptic_settings_page('images'),
        'haptic-thumbnails-section',
        ['layout' => 'stacked']
    );
}
add_action('admin_init', 'haptic_options_thumbnail_init');

function haptic_options_featured_image_post_types_callback()
{
    $option = get_option('haptic_option_featured_image_post_types');
    $post_types = get_post_types(array('public' => true), 'objects');

    echo '<div class="optimizr-pills">';
    foreach ($post_types as $post_type) {
        $checked = is_array($option) && in_array($post_type->name, $option, true);
        echo '<label class="optimizr-pill"><input type="checkbox" name="haptic_option_featured_image_post_types[]" value="' . esc_attr($post_type->name) . '" ' . checked($checked, true, false) . ' /> ' . esc_html($post_type->label) . '</label>';
    }
    echo '</div>';
}

// Applique le réglage à chaque chargement (tant que l'option n'a jamais été enregistrée, le thème décide)
function haptic_options_apply_settings()
{
    $post_types_option = get_option('haptic_option_featured_image_post_types');

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
add_action('init', 'haptic_options_apply_settings');
