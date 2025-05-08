<?php

// Function to update the settings based on the current state
function hpt_options_update_settings()
{
    // $global_option = get_option('hpt_option_featured_image_global');
    $post_types_option = get_option('hpt_option_featured_image_post_types');

    // Check if featured images are enabled globally or for specific post types
    $post_types = get_post_types(array('public' => true), 'objects');
    $all_post_types_support_thumbnails = true;

    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type->name, 'thumbnail')) {
            if (!is_array($post_types_option)) {
                $post_types_option = array();
            }
            if (!in_array($post_type->name, $post_types_option)) {
                $post_types_option[] = $post_type->name;
            }
        } else {
            $all_post_types_support_thumbnails = false;
        }
    }

    // Update the options based on the current state
    update_option('hpt_option_featured_image_post_types', $post_types_option);

    if ($all_post_types_support_thumbnails) {
        update_option('hpt_option_featured_image_global', 1);
    } else {
        update_option('hpt_option_featured_image_global', 0);
    }
}


// 
// 
// Add settings fields to the settings page
// 
// 


// Function to initialize the settings
function hpt_options_thumbnail_init()
{
    // register_setting('hpt-options-settings-group', 'hpt_option_featured_image_global');
    register_setting('hpt-options-settings-group', 'hpt_option_featured_image_post_types');

    // add_settings_field(
    //     'hpt-options-featured-image-glob al',
    //     'Enable Featured Images Globally',
    //     'hpt_options_featured_image_global_callback',
    //     'hpt-options',
    //     'hpt-options-settings-section'
    // );

    add_settings_field(
        'hpt-options-featured-image-post-types',
        'Activer les images de couverture pour les types de contenu spécifiques',
        'hpt_options_featured_image_post_types_callback',
        'hpt-options',
        'hpt-options-settings-section'
    );
}

// Add a callback function to the 'admin_init' action hook
add_action('add_hpt_options_tabs', 'hpt_options_thumbnail_init');



// // Callback function for the global featured image setting
// function hpt_options_featured_image_global_callback()
// {
//     $option = get_option('hpt_option_featured_image_global');
//     echo '<input type="checkbox" name="hpt_option_featured_image_global" value="1"' . checked(1, $option, false) . ' />';
// }

// Callback function for the post types featured image setting
function hpt_options_featured_image_post_types_callback()
{
    $option = get_option('hpt_option_featured_image_post_types');
    $post_types = get_post_types(array('public' => true), 'objects');
    foreach ($post_types as $post_type) {
        $checked = is_array($option) && in_array($post_type->name, $option) ? 'checked="checked"' : '';
        echo '<label><input type="checkbox" name="hpt_option_featured_image_post_types[]" value="' . esc_attr($post_type->name) . '" ' . $checked . ' /> ' . esc_html($post_type->label) . '</label><br>';
    }
}





// Attach the function to the 'init' hook
add_action('init', 'hpt_options_apply_settings');

// Function to apply the settings
function hpt_options_apply_settings()
{
    // $global_option = get_option('hpt_option_featured_image_global');
    $post_types_option = get_option('hpt_option_featured_image_post_types');

    // // Apply the settings
    // if ($global_option) {
    //     add_theme_support('post-thumbnails');
    // } else {
    //     remove_theme_support('post-thumbnails');
    // }

    if (is_array($post_types_option)) {
        $post_types = get_post_types(array('public' => true), 'objects');
        foreach ($post_types as $post_type) {
            if (in_array($post_type->name, $post_types_option)) {
                add_post_type_support($post_type->name, 'thumbnail');
            } else {
                remove_post_type_support($post_type->name, 'thumbnail');
            }
        }
    }
}
