<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', function () {
    if (!hpt_is_module_active('devtools')) {
        return;
    }

    register_setting(hpt_settings_group('advanced'), 'hpt_debug_mode', ['sanitize_callback' => 'absint']);
    register_setting(hpt_settings_group('advanced'), 'hpt_dev_mode', ['sanitize_callback' => 'absint']);

    add_settings_section(
        'hpt_debug_section',
        'Outils développeur',
        function () {
            echo '<p>Visibles uniquement par les administrateurs. À désactiver sur un site en production.</p>';
        },
        hpt_settings_page('advanced')
    );

    add_settings_field(
        'hpt_debug_mode',
        'Mode debug',
        function () {
            echo hpt_switch_input('hpt_debug_mode', get_option('hpt_debug_mode'), 'Mode debug');
        },
        hpt_settings_page('advanced'),
        'hpt_debug_section',
        ['description' => 'Journal dans wp-content/uploads/optimizr-logs/ (requêtes des admins, tâches planifiées et WP-CLI) et alerte des erreurs PHP dans l\'admin.']
    );

    add_settings_field(
        'hpt_dev_mode',
        'Mode développeur',
        function () {
            echo hpt_switch_input('hpt_dev_mode', get_option('hpt_dev_mode'), 'Mode développeur');
        },
        hpt_settings_page('advanced'),
        'hpt_debug_section',
        ['description' => 'Active la fonction vd() pour inspecter des variables dans une fenêtre.']
    );
});
