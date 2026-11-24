<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Multisite : écran « Optimizr » de l'administration du réseau (chargé seulement sur un multisite).
 *
 * Optimizr se règle site par site : chaque site du réseau a ses modules, ses réglages et ses données. Cet écran
 * donne à l'administrateur du réseau une vue de tous les sites, et de quoi reproduire les réglages d'un site
 * modèle sur les autres, tout de suite ou à la création de chaque nouveau site.
 */

const HAPTIC_NETWORK_PAGE = 'haptic-network';
/** Nombre de sites affichés et traités d'un coup : au-delà, l'écran le dit. */
const HAPTIC_NETWORK_MAX_SITES = 200;

function haptic_network_url($args = [])
{
    return add_query_arg($args, network_admin_url('admin.php?page=' . HAPTIC_NETWORK_PAGE));
}

function haptic_network_can()
{
    return current_user_can('manage_network_options');
}

add_action('network_admin_menu', function () {
    add_menu_page(
        __('Optimizr - Network', 'optimizr'),
        'Optimizr',
        'manage_network_options',
        HAPTIC_NETWORK_PAGE,
        'haptic_network_page',
        haptic_menu_icon(),
        81
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_' . HAPTIC_NETWORK_PAGE . '-network' && $hook !== 'toplevel_page_' . HAPTIC_NETWORK_PAGE) {
        return;
    }
    $css = HAPTIC_OPTIMIZR_PATH . 'css/optimizr-admin.css';
    wp_enqueue_style('optimizr-admin', HAPTIC_OPTIMIZR_URL . 'css/optimizr-admin.css', [haptic_tokens_style()], filemtime($css));
});

/** Identifiants des sites du réseau (au plus HAPTIC_NETWORK_MAX_SITES), le site principal en premier. */
function haptic_network_site_ids()
{
    return array_map('intval', get_sites(['fields' => 'ids', 'number' => HAPTIC_NETWORK_MAX_SITES, 'orderby' => 'id', 'deleted' => 0, 'archived' => 0, 'spam' => 0]));
}

/** Optimizr est-il actif sur le site courant (activé pour le réseau, ou sur ce site) ? */
function haptic_network_plugin_active_here()
{
    $basename = plugin_basename(HAPTIC_OPTIMIZR_FILE);
    return isset(((array) get_site_option('active_sitewide_plugins', []))[$basename]) || in_array($basename, (array) get_option('active_plugins', []), true);
}

/**
 * Ce qu'Optimizr fait sur un site du réseau, lu dans ses réglages.
 *
 * @return array name, url, settings_url, active, modules (libellés), cache, login
 */
function haptic_network_site_summary($site_id)
{
    switch_to_blog($site_id);

    $modules = [];
    foreach (haptic_modules() as $slug => $module) {
        if (haptic_is_module_active($slug)) {
            $modules[] = wp_strip_all_tags((string) ($module['label'] ?? $slug));
        }
    }
    $state = get_option('haptic_cache_state');
    $summary = [
        'name'         => (string) get_option('blogname'),
        'url'          => home_url('/'),
        'settings_url' => admin_url('admin.php?page=haptic-options'),
        'active'       => haptic_network_plugin_active_here(),
        'modules'      => $modules,
        'cache'        => haptic_is_module_active('cache') && get_option('haptic_cache_page') && is_array($state) && !empty($state['page']),
        'login'        => haptic_is_module_active('security') && (string) get_option('haptic_sec_login_slug', '') !== '',
    ];

    restore_current_blog();
    return $summary;
}

/* ---------- Copie des réglages d'un site vers les autres ---------- */

/**
 * Réglages jamais copiés d'un site à l'autre, en plus de ceux que l'export écarte déjà
 * (cf. haptic_settings_internal_options()) : ce qui décrit un site en particulier, ou ses connexions à des services.
 */
function haptic_network_copy_excluded()
{
    return apply_filters('haptic_network_copy_excluded', [
        // Établissement (SEO local) et avis Google de ce site
        'haptic_local', 'haptic_reviews', 'haptic_reviews_cache',
        // Titre, description et image de la page d'accueil
        'haptic_seo_home_title', 'haptic_seo_home_desc', 'haptic_seo_home_image',
        // Connexions Google (Search Console, Analytics) et accès accordés aux assistants IA et à la console
        'haptic_gsc_credentials', 'haptic_gsc_property', 'haptic_gsc_cache', 'haptic_gsc_gen', 'haptic_ga4_property', 'haptic_ga4_gen',
        'haptic_mcp_oauth', 'haptic_mcp_oauth_clients', 'haptic_mcp_oauth_grants',
        // États internes, déclarés par des modules qui peuvent ne pas être chargés sur le site principal
        'haptic_tracking_db', 'haptic_tracking_since', 'haptic_cache_state', 'haptic_cache_last_purge', 'haptic_cache_preload_queue',
        'haptic_cache_cf_status', 'haptic_cpts_rewrite',
    ]);
}

/**
 * Copie les réglages d'Optimizr d'un site vers d'autres, comme un export suivi d'un import (onglet Outils) :
 * les adresses du site d'origine sont remplacées par celles de chaque site.
 *
 * @param int   $from Site modèle.
 * @param int[] $to   Sites à régler.
 * @return int Nombre de sites réglés.
 */
function haptic_network_copy_settings($from, array $to)
{
    $from = (int) $from;
    if (!get_site($from)) {
        return 0;
    }

    switch_to_blog($from);
    $data = [
        'plugin'   => 'optimizr',
        'format'   => HAPTIC_EXPORT_FORMAT,
        'site'     => home_url(),
        // Clés API comprises : même installation, mêmes administrateurs du réseau
        'settings' => array_diff_key(haptic_exportable_settings(true), array_flip(haptic_network_copy_excluded())),
    ];
    restore_current_blog();

    $done = 0;
    foreach (array_unique(array_map('intval', $to)) as $site_id) {
        if ($site_id === $from || !get_site($site_id)) {
            continue;
        }
        switch_to_blog($site_id);
        if (!is_wp_error(haptic_import_settings($data, true))) {
            $done++;
            // Fichiers du cache de ce site : la synchronisation de fin de requête ne vaut que pour le site courant
            haptic_cache_sync();
        }
        restore_current_blog();
    }
    return $done;
}

/** Site modèle des nouveaux sites du réseau (0 : aucun). */
function haptic_network_template_site()
{
    $site_id = (int) get_site_option('haptic_network_template', 0);
    return $site_id && get_site($site_id) ? $site_id : 0;
}

// Nouveau site du réseau : il reçoit les réglages du site modèle, une fois ses tables créées
add_action('wp_initialize_site', function ($site) {
    $template = haptic_network_template_site();
    if ($template && (int) $site->id !== $template) {
        haptic_network_copy_settings($template, [(int) $site->id]);
    }
}, 100);

add_action('network_admin_edit_haptic_network_save', function () {
    if (!haptic_network_can()) {
        wp_die(esc_html__('Access denied', 'optimizr'));
    }
    check_admin_referer('haptic_network_save');

    $source = absint($_POST['haptic_network_source'] ?? 0);
    $args = [];

    if (isset($_POST['haptic_network_template'])) {
        // Site modèle des nouveaux sites
        $template = !empty($_POST['haptic_network_new_sites']) && $source && get_site($source) ? $source : 0;
        update_site_option('haptic_network_template', $template);
        $args['haptic_message'] = 'template';
    } elseif (isset($_POST['haptic_network_copy']) && $source && get_site($source)) {
        // Copie immédiate vers les sites cochés
        $targets = array_intersect(array_map('absint', (array) ($_POST['haptic_network_targets'] ?? [])), haptic_network_site_ids());
        $args['haptic_message'] = 'copied';
        $args['haptic_count'] = haptic_network_copy_settings($source, $targets);
    }

    wp_safe_redirect(haptic_network_url($args));
    exit;
});

/* ---------- Écran ---------- */

function haptic_network_page()
{
    if (!haptic_network_can()) {
        return;
    }

    $site_ids = haptic_network_site_ids();
    $sites = [];
    foreach ($site_ids as $site_id) {
        $sites[$site_id] = haptic_network_site_summary($site_id);
    }
    $total = (int) get_sites(['count' => true, 'deleted' => 0, 'archived' => 0, 'spam' => 0]);
    $template = haptic_network_template_site();
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- message affiché après une action déjà vérifiée par nonce
    $message = isset($_GET['haptic_message']) ? sanitize_key($_GET['haptic_message']) : '';
    $count = isset($_GET['haptic_count']) ? absint($_GET['haptic_count']) : 0;
    // phpcs:enable
    ?>
    <div class="wrap optimizr">
        <header class="optimizr-header">
            <div class="optimizr-header__top">
                <img class="optimizr-logo" src="<?php echo esc_url(HAPTIC_OPTIMIZR_URL . 'images/optimizr-icon.svg'); ?>" width="36" height="36" alt="" />
                <h1 class="optimizr-header__title"><?php esc_html_e('Optimizr on the network', 'optimizr'); ?></h1>
                <span class="optimizr-badge">v<?php echo esc_html(HAPTIC_OPTIMIZR_VERSION); ?></span>
            </div>
        </header>

        <hr class="wp-header-end">

        <?php if ($message === 'copied') : ?>
            <div class="notice notice-success is-dismissible"><p><?php
            /* translators: %s: number of sites */
            echo esc_html(sprintf(_n('Settings copied to %s site.', 'Settings copied to %s sites.', $count, 'optimizr'), number_format_i18n($count)));
            ?></p></div>
        <?php elseif ($message === 'template') : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo $template ? esc_html__('New sites of the network will receive the settings of the model site.', 'optimizr') : esc_html__('New sites of the network will start with the default settings.', 'optimizr'); ?></p></div>
        <?php endif; ?>

        <main class="optimizr-main">
            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title"><?php esc_html_e('Sites of the network', 'optimizr'); ?></h2>
                        <p class="optimizr-card__desc"><?php esc_html_e('Optimizr is set site by site: each site has its own modules, settings and data. Open a site to change its settings.', 'optimizr'); ?></p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <div class="optimizr-table-wrap">
                        <table class="optimizr-table">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e('Site', 'optimizr'); ?></th>
                                    <th scope="col"><?php esc_html_e('Active modules', 'optimizr'); ?></th>
                                    <th scope="col"><?php esc_html_e('Page cache', 'optimizr'); ?></th>
                                    <th scope="col"><?php esc_html_e('Login address', 'optimizr'); ?></th>
                                    <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'optimizr'); ?></span></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sites as $site_id => $site) : ?>
                                <tr>
                                    <th scope="row">
                                        <strong><?php echo esc_html($site['name'] !== '' ? $site['name'] : $site['url']); ?></strong><?php if ($site_id === $template) : ?> <span class="optimizr-badge"><?php esc_html_e('Model', 'optimizr'); ?></span><?php endif; ?><br>
                                        <a href="<?php echo esc_url($site['url']); ?>" class="optimizr-muted"><?php echo esc_html(preg_replace('#^https?://#', '', untrailingslashit($site['url']))); ?></a>
                                    </th>
                                    <?php if (!$site['active']) : ?>
                                        <td colspan="3"><span class="optimizr-status optimizr-status--off"><?php esc_html_e('Optimizr is not activated on this site', 'optimizr'); ?></span></td>
                                        <td></td>
                                    <?php else : ?>
                                        <td><?php echo $site['modules'] ? esc_html(implode(', ', $site['modules'])) : '<span class="optimizr-muted">' . esc_html__('None', 'optimizr') . '</span>'; ?></td>
                                        <td><span class="optimizr-status optimizr-status--<?php echo $site['cache'] ? 'ok' : 'off'; ?>"><?php echo $site['cache'] ? esc_html_x('Active', 'cache status', 'optimizr') : esc_html_x('Off', 'cache status', 'optimizr'); ?></span></td>
                                        <td><span class="optimizr-status optimizr-status--<?php echo $site['login'] ? 'ok' : 'off'; ?>"><?php echo $site['login'] ? esc_html__('Hidden', 'optimizr') : 'wp-login.php'; ?></span></td>
                                        <td><a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url($site['settings_url']); ?>"><?php esc_html_e('Settings', 'optimizr'); ?></a></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total > count($sites)) : ?>
                        <p class="optimizr-card__hint"><?php
                        /* translators: 1: number of sites shown, 2: number of sites in the network */
                        echo esc_html(sprintf(__('The first %1$s sites of %2$s are shown.', 'optimizr'), number_format_i18n(count($sites)), number_format_i18n($total)));
                        ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <form class="optimizr-card" method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=haptic_network_save')); ?>">
                <?php wp_nonce_field('haptic_network_save'); ?>
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title"><?php esc_html_e('Same settings on several sites', 'optimizr'); ?></h2>
                        <p class="optimizr-card__desc"><?php esc_html_e('Set one site the way you want, then copy its Optimizr settings to the others, like an export followed by an import (Tools tab).', 'optimizr'); ?></p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <p>
                        <label for="haptic_network_source"><strong><?php esc_html_e('Model site', 'optimizr'); ?></strong></label><br>
                        <select name="haptic_network_source" id="haptic_network_source">
                            <?php foreach ($sites as $site_id => $site) : ?>
                                <?php if ($site['active']) : ?>
                                    <option value="<?php echo esc_attr($site_id); ?>" <?php selected($site_id, $template ?: get_main_site_id()); ?>><?php echo esc_html(($site['name'] !== '' ? $site['name'] . ' — ' : '') . preg_replace('#^https?://#', '', untrailingslashit($site['url']))); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <label class="optimizr-check">
                        <input type="checkbox" name="haptic_network_new_sites" value="1" <?php checked((bool) $template); ?>>
                        <span><?php esc_html_e('Give these settings to every new site of the network', 'optimizr'); ?></span>
                    </label>
                    <div><button type="submit" name="haptic_network_template" value="1" class="optimizr-btn optimizr-btn--secondary"><?php esc_html_e('Save for new sites', 'optimizr'); ?></button></div>

                    <p><strong><?php esc_html_e('Copy now to:', 'optimizr'); ?></strong></p>
                    <?php foreach ($sites as $site_id => $site) : ?>
                        <?php if ($site['active']) : ?>
                            <label class="optimizr-check">
                                <input type="checkbox" name="haptic_network_targets[]" value="<?php echo esc_attr($site_id); ?>">
                                <span><?php echo esc_html($site['name'] !== '' ? $site['name'] : $site['url']); ?> <span class="optimizr-muted">(<?php echo esc_html(preg_replace('#^https?://#', '', untrailingslashit($site['url']))); ?>)</span></span>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <p class="optimizr-field__help"><?php esc_html_e('The settings of the chosen sites are replaced. What belongs to one site is never copied: its login address, business details and reviews, home page SEO, Google connections, access granted to AI assistants and to the console, maintenance mode, logs and statistics. Content, redirects and the SEO of each page are not touched.', 'optimizr'); ?></p>
                    <div><button type="submit" name="haptic_network_copy" value="1" class="optimizr-btn optimizr-btn--primary"><?php esc_html_e('Copy the settings', 'optimizr'); ?></button></div>
                </div>
            </form>
        </main>
    </div>
    <?php
}

// Lien vers l'écran du réseau dans la liste des extensions du réseau
add_filter('network_admin_plugin_action_links_' . plugin_basename(HAPTIC_OPTIMIZR_FILE), function ($links) {
    if (haptic_network_can()) {
        array_unshift($links, '<a href="' . esc_url(haptic_network_url()) . '">' . esc_html__('Network', 'optimizr') . '</a>');
    }
    return $links;
});
