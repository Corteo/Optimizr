<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Choix de ce qui est effacé à la suppression du plugin (cf. functions/uninstall.php) :
 * demandés au clic sur « Désactiver » dans la liste des extensions (un plugin désactivé n'exécute plus rien,
 * la question ne peut pas attendre le clic sur « Supprimer »), et modifiables dans l'onglet Outils.
 */

function haptic_uninstall_plugin_basename()
{
    return plugin_basename(HAPTIC_OPTIMIZR_FILE);
}

/**
 * Enregistre les choix. Depuis l'administration du réseau, ils s'appliquent à tous les sites.
 */
function haptic_uninstall_save_choices($value, $network = false)
{
    $value = haptic_uninstall_sanitize($value);

    if ($network && is_multisite()) {
        foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $site_id) {
            switch_to_blog($site_id);
            update_option('haptic_uninstall', $value, false);
            restore_current_blog();
        }
    } else {
        update_option('haptic_uninstall', $value, false);
    }
}

/** Droit requis : le même que pour les réglages d'Optimizr. */
function haptic_uninstall_can_edit()
{
    return current_user_can('manage_options');
}

/* ---------- Onglet Outils ---------- */

add_action('admin_post_haptic_save_uninstall', function () {
    if (!haptic_uninstall_can_edit()) {
        wp_die(esc_html__('Access denied', 'optimizr'));
    }
    check_admin_referer('haptic_save_uninstall');

    haptic_uninstall_save_choices(map_deep(wp_unslash($_POST['haptic_uninstall'] ?? []), 'sanitize_key'));

    wp_safe_redirect(add_query_arg('haptic_message', 'uninstall', haptic_admin_tab_url('tools')));
    exit;
});

function haptic_render_uninstall_card()
{
    $choices = haptic_uninstall_choices();
    ?>
    <form class="optimizr-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="haptic_save_uninstall">
        <?php wp_nonce_field('haptic_save_uninstall'); ?>
        <div class="optimizr-card__header">
            <div>
                <h2 class="optimizr-card__title"><?php esc_html_e('Plugin deletion', 'optimizr'); ?></h2>
                <p class="optimizr-card__desc"><?php esc_html_e('What is erased when Optimizr is deleted from the plugins list. These choices are also offered when you deactivate it.', 'optimizr'); ?></p>
            </div>
        </div>
        <div class="optimizr-card__body">
            <?php foreach (haptic_uninstall_groups() as $key => $group) : ?>
                <label class="optimizr-check">
                    <input type="checkbox" name="haptic_uninstall[<?php echo esc_attr($key); ?>]" value="1" <?php checked($choices[$key]); ?>>
                    <span><?php
                    /* translators: %s: name of a group of data (settings, logs…) */
                    echo esc_html(sprintf(__('Erase: %s', 'optimizr'), $group['label']));
                    ?> <span class="optimizr-muted">(<?php echo esc_html($group['help']); ?>)</span></span>
                </label>
            <?php endforeach; ?>
            <p class="optimizr-field__help"><?php esc_html_e('Scheduled tasks, caches and generated files (sitemap, IndexNow key, robots.txt written by Optimizr if it has not been modified) are always deleted. Pages, media and alt texts are never touched. Simply deactivating the plugin deletes no data.', 'optimizr'); ?></p>
            <div><button type="submit" class="optimizr-btn optimizr-btn--primary"><?php esc_html_e('Save', 'optimizr'); ?></button></div>
        </div>
    </form>
    <?php
}

/* ---------- Liste des extensions : question au clic sur « Désactiver » ---------- */

add_action('wp_ajax_haptic_save_uninstall', function () {
    check_ajax_referer('haptic_save_uninstall');
    if (!haptic_uninstall_can_edit()) {
        wp_send_json_error(__('Access denied', 'optimizr'), 403);
    }

    $choices = json_decode(sanitize_text_field(wp_unslash($_POST['choices'] ?? '')), true);
    haptic_uninstall_save_choices($choices, !empty($_POST['network']) && current_user_can('manage_network_plugins'));
    wp_send_json_success();
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'plugins.php' || !haptic_uninstall_can_edit()) {
        return;
    }

    wp_enqueue_script(
        'optimizr-uninstall',
        HAPTIC_OPTIMIZR_URL . 'js/optimizr-uninstall.js',
        ['wp-i18n'],
        HAPTIC_OPTIMIZR_VERSION,
        true
    );
    wp_set_script_translations('optimizr-uninstall', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
    wp_localize_script('optimizr-uninstall', 'optimizrUninstall', [
        'plugin'  => haptic_uninstall_plugin_basename(),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('haptic_save_uninstall'),
        'network' => is_network_admin() ? 1 : 0,
        'groups'  => haptic_uninstall_groups(),
        'choices' => haptic_uninstall_choices(),
    ]);
});
