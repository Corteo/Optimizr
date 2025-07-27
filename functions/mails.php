<?php

// Enregistrement des options liées aux mails
function haptic_GPT_register_mail_options()
{
    register_setting('hpt-options-settings-group', 'hpt_disable_plugin_update_emails');
    register_setting('hpt-options-settings-group', 'hpt_disable_core_update_emails');
}
add_action('admin_init', 'haptic_GPT_register_mail_options');

// Ajout des sections et champs pour les réglages
function haptic_GPT_render_mail_settings()
{
    add_settings_section('hpt-email-settings', 'Notifications par mail', null, 'hpt-options');

    add_settings_field('hpt_disable_plugin_update_emails', 'Désactiver les emails de mise à jour de plugin', function () {
        $value = get_option('hpt_disable_plugin_update_emails', false);
        echo '<input type="checkbox" name="hpt_disable_plugin_update_emails" value="1" ' . checked(1, $value, false) . '> Ne pas recevoir d\'emails lors de la mise à jour automatique des plugins.';
    }, 'hpt-options', 'hpt-email-settings');

    add_settings_field('hpt_disable_core_update_emails', 'Désactiver les emails de mise à jour du cœur WordPress', function () {
        $value = get_option('hpt_disable_core_update_emails', false);
        echo '<input type="checkbox" name="hpt_disable_core_update_emails" value="1" ' . checked(1, $value, false) . '> Ne pas recevoir d\'emails lors des mises à jour automatiques de WordPress.';
    }, 'hpt-options', 'hpt-email-settings');
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
