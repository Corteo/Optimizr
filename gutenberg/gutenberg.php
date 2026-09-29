<?php

if (!defined('ABSPATH')) {
    exit;
}

// Charger le bloc Gutenberg SEO
function hpt_enqueue_gutenberg_assets()
{
    $script_path = plugin_dir_path(__FILE__) . 'seo-preview.js';
    $css_path = plugin_dir_path(__FILE__) . 'seo-preview.css';

    wp_enqueue_script(
        'hpt-seo-preview',
        plugin_dir_url(__FILE__) . 'seo-preview.js',
        ['wp-plugins', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-element', 'wp-api'],
        file_exists($script_path) ? filemtime($script_path) : '1.0.0',
        true
    );

    wp_enqueue_style(
        'hpt-seo-preview-style',
        plugin_dir_url(__FILE__) . 'seo-preview.css',
        [],
        file_exists($css_path) ? filemtime($css_path) : '1.0.0'
    );
}
add_action('enqueue_block_editor_assets', 'hpt_enqueue_gutenberg_assets');
