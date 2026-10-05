<?php

if (!defined('ABSPATH')) {
    exit;
}

// Enregistrement des options et des champs liés aux mails
function haptic_GPT_render_mail_settings()
{
    register_setting(hpt_settings_group('advanced'), 'hpt_disable_plugin_update_emails', ['sanitize_callback' => 'absint']);
    register_setting(hpt_settings_group('advanced'), 'hpt_disable_core_update_emails', ['sanitize_callback' => 'absint']);

    add_settings_section('hpt-email-settings', 'Emails', function () {
        echo '<p>Emails envoyés à l\'administrateur après les mises à jour automatiques.</p>';
    }, hpt_settings_page('advanced'));

    add_settings_field('hpt_disable_plugin_update_emails', 'Couper les emails des extensions', function () {
        echo wp_kses(hpt_switch_input('hpt_disable_plugin_update_emails', get_option('hpt_disable_plugin_update_emails', false), 'Couper les emails de mise à jour des extensions'), hpt_kses_ui());
    }, hpt_settings_page('advanced'), 'hpt-email-settings', ['description' => 'Aucun email après la mise à jour automatique d\'une extension.']);

    add_settings_field('hpt_disable_core_update_emails', 'Couper les emails de WordPress', function () {
        echo wp_kses(hpt_switch_input('hpt_disable_core_update_emails', get_option('hpt_disable_core_update_emails', false), 'Couper les emails de mise à jour de WordPress'), hpt_kses_ui());
    }, hpt_settings_page('advanced'), 'hpt-email-settings', ['description' => 'Aucun email après une mise à jour automatique du cœur de WordPress.']);
}
add_action('admin_init', 'haptic_GPT_render_mail_settings');

// Application des filtres de désactivation d'emails
function haptic_GPT_filter_update_emails()
{
    if (get_option('hpt_disable_plugin_update_emails')) {
        add_filter('auto_plugin_update_send_email', '__return_false');
    }
    if (get_option('hpt_disable_core_update_emails')) {
        add_filter('auto_core_update_send_email', '__return_false');
    }
}
add_action('init', 'haptic_GPT_filter_update_emails');
