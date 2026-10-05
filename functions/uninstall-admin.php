<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Choix de ce qui est effacé à la suppression du plugin (cf. functions/uninstall.php) :
 * demandés au clic sur « Désactiver » dans la liste des extensions (un plugin désactivé n'exécute plus rien,
 * la question ne peut pas attendre le clic sur « Supprimer »), et modifiables dans l'onglet Outils.
 */

function hpt_uninstall_plugin_basename()
{
    return plugin_basename(HPT_OPTIMIZR_PATH . 'hpt_options.php');
}

/**
 * Enregistre les choix. Depuis l'administration du réseau, ils s'appliquent à tous les sites.
 */
function hpt_uninstall_save_choices($value, $network = false)
{
    $value = hpt_uninstall_sanitize($value);

    if ($network && is_multisite()) {
        foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $site_id) {
            switch_to_blog($site_id);
            update_option('hpt_uninstall', $value, false);
            restore_current_blog();
        }
    } else {
        update_option('hpt_uninstall', $value, false);
    }
}

/** Droit requis : le même que pour les réglages d'Optimizr. */
function hpt_uninstall_can_edit()
{
    return current_user_can('manage_options');
}

/* ---------- Onglet Outils ---------- */

add_action('admin_post_hpt_save_uninstall', function () {
    if (!hpt_uninstall_can_edit()) {
        wp_die('Accès refusé');
    }
    check_admin_referer('hpt_save_uninstall');

    hpt_uninstall_save_choices(map_deep(wp_unslash($_POST['hpt_uninstall'] ?? []), 'sanitize_key'));

    wp_safe_redirect(add_query_arg('hpt_message', 'uninstall', hpt_admin_tab_url('tools')));
    exit;
});

function hpt_render_uninstall_card()
{
    $choices = hpt_uninstall_choices();
    ?>
    <form class="optimizr-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="hpt_save_uninstall">
        <?php wp_nonce_field('hpt_save_uninstall'); ?>
        <div class="optimizr-card__header">
            <div>
                <h2 class="optimizr-card__title">Suppression du plugin</h2>
                <p class="optimizr-card__desc">Ce qui est effacé quand Optimizr est supprimé depuis la liste des extensions. Ces choix sont aussi proposés quand vous le désactivez.</p>
            </div>
        </div>
        <div class="optimizr-card__body">
            <?php foreach (hpt_uninstall_groups() as $key => $group) : ?>
                <label class="optimizr-check">
                    <input type="checkbox" name="hpt_uninstall[<?php echo esc_attr($key); ?>]" value="1" <?php checked($choices[$key]); ?>>
                    <span>Effacer : <?php echo esc_html($group['label']); ?> <span class="optimizr-muted">(<?php echo esc_html($group['help']); ?>)</span></span>
                </label>
            <?php endforeach; ?>
            <p class="optimizr-field__help">Les tâches planifiées, les caches et les fichiers générés (sitemap, clé IndexNow, journal de debug, robots.txt écrit par Optimizr s'il n'a pas été modifié) sont toujours supprimés. Les pages, médias et textes alternatifs ne sont jamais touchés. La simple désactivation ne supprime aucune donnée.</p>
            <div><button type="submit" class="optimizr-btn optimizr-btn--primary">Enregistrer</button></div>
        </div>
    </form>
    <?php
}

/* ---------- Liste des extensions : question au clic sur « Désactiver » ---------- */

add_action('wp_ajax_hpt_save_uninstall', function () {
    check_ajax_referer('hpt_save_uninstall');
    if (!hpt_uninstall_can_edit()) {
        wp_send_json_error('Accès refusé', 403);
    }

    $choices = json_decode(sanitize_text_field(wp_unslash($_POST['choices'] ?? '')), true);
    hpt_uninstall_save_choices($choices, !empty($_POST['network']) && current_user_can('manage_network_plugins'));
    wp_send_json_success();
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'plugins.php' || !hpt_uninstall_can_edit()) {
        return;
    }

    wp_enqueue_script(
        'optimizr-uninstall',
        HPT_OPTIMIZR_URL . 'js/optimizr-uninstall.js',
        [],
        HPT_OPTIMIZR_VERSION,
        true
    );
    wp_localize_script('optimizr-uninstall', 'optimizrUninstall', [
        'plugin'  => hpt_uninstall_plugin_basename(),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('hpt_save_uninstall'),
        'network' => is_network_admin() ? 1 : 0,
        'groups'  => hpt_uninstall_groups(),
        'choices' => hpt_uninstall_choices(),
    ]);
});
