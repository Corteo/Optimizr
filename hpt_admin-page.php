<?php

// Add a callback function to the 'admin_init' action hook
add_action('admin_init', 'hpt_options_admin_init');


function hpt_options_admin_init()
{
    // Code existant
    do_action('before_hpt_options_admin_init');

    add_settings_section(
        'hpt-options-settings-section',
        'Réglages',
        'hpt_options_settings_section_callback',
        'hpt-options'
    );


    // Ajouter des onglets via des hooks
    do_action('add_hpt_options_tabs');

    // Plus de code existant
    do_action('after_hpt_options_admin_init');
}

// Callback function for the settings section
function hpt_options_settings_section_callback()
{
    // echo '<p>Configure the settings for Haptic Options below.</p>';
    return;
}
