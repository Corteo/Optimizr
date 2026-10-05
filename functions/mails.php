<?php

if (!defined('ABSPATH')) {
    exit;
}

// Enregistrement des options et des champs liés aux mails
function haptic_render_mail_settings()
{
    register_setting(haptic_settings_group('advanced'), 'haptic_disable_plugin_update_emails', ['sanitize_callback' => 'absint']);
    register_setting(haptic_settings_group('advanced'), 'haptic_disable_core_update_emails', ['sanitize_callback' => 'absint']);

    add_settings_section('haptic-email-settings', __('Emails', 'optimizr'), function () {
        echo '<p>' . esc_html__('Emails sent to the administrator after automatic updates.', 'optimizr') . '</p>';
    }, haptic_settings_page('advanced'));

    add_settings_field('haptic_disable_plugin_update_emails', __('Turn off plugin emails', 'optimizr'), function () {
        echo wp_kses(haptic_switch_input('haptic_disable_plugin_update_emails', get_option('haptic_disable_plugin_update_emails', false), __('Turn off plugin update emails', 'optimizr')), haptic_kses_ui());
    }, haptic_settings_page('advanced'), 'haptic-email-settings', ['description' => __('No email after a plugin is updated automatically.', 'optimizr')]);

    add_settings_field('haptic_disable_core_update_emails', __('Turn off WordPress emails', 'optimizr'), function () {
        echo wp_kses(haptic_switch_input('haptic_disable_core_update_emails', get_option('haptic_disable_core_update_emails', false), __('Turn off WordPress update emails', 'optimizr')), haptic_kses_ui());
    }, haptic_settings_page('advanced'), 'haptic-email-settings', ['description' => __('No email after an automatic WordPress core update.', 'optimizr')]);
}
add_action('admin_init', 'haptic_render_mail_settings');

// Application des filtres de désactivation d'emails
function haptic_filter_update_emails()
{
    if (get_option('haptic_disable_plugin_update_emails')) {
        add_filter('auto_plugin_update_send_email', '__return_false');
    }
    if (get_option('haptic_disable_core_update_emails')) {
        add_filter('auto_core_update_send_email', '__return_false');
    }
}
add_action('init', 'haptic_filter_update_emails');
