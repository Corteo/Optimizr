<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Onglet Outils : export et import des réglages d'Optimizr (fichier JSON),
 * pour configurer un site puis reproduire ses réglages sur les suivants.
 */

/** Format du fichier d'export, à incrémenter si sa structure change. */
const HAPTIC_EXPORT_FORMAT = 1;

/**
 * Options internes, propres à chaque site : jamais exportées ni importées.
 */
function haptic_settings_internal_options()
{
    return apply_filters('haptic_settings_internal_options', [
        'haptic_optimizr_version',
        'haptic_sitemap_history',
        'haptic_sitemap_error',
        'haptic_sitemap_last_generated',
        'haptic_ai_usage',
        'haptic_security_log',
        'haptic_links_queue',
        'haptic_links_report',
        'haptic_indexnow_key',
        'haptic_indexnow_last',
        // Propre à chaque site, et à ne pas diffuser
        'haptic_sec_login_slug',
        // Proxys de confiance : propres à l'hébergement de chaque site (importés ailleurs, ils pourraient falsifier X-Forwarded-For)
        'haptic_sec_trusted_proxies',
        // Identifiants de pages propres à chaque site ; un import ne doit pas fermer le site
        'haptic_404_page',
        'haptic_maintenance',
        'haptic_maintenance_page',
        'haptic_maintenance_until',
        // Ce qu'efface la suppression du plugin sur ce site (cf. functions/uninstall.php)
        'haptic_uninstall',
        // Canal de mise à jour : propre à chaque installation (un site client ne doit pas hériter du canal beta)
        'haptic_update_channel',
        // Assistant de configuration déjà passé sur ce site
        'haptic_onboarding_done',
        // Empreinte du robots.txt écrit par Optimizr (cf. functions/activation.php)
        'haptic_robots_txt_hash',
        // Réglages importés en attente de nettoyage (cf. haptic_import_sanitize_pending())
        'haptic_import_unsanitized',
    ]);
}

function haptic_is_exportable_option($name)
{
    return (bool) preg_match('/^haptic_[a-z0-9_]+$/', $name) && !in_array($name, haptic_settings_internal_options(), true);
}

/**
 * Réglages exportables : toutes les options haptic_* du site, y compris celles des modules désactivés.
 *
 * @return array nom => valeur
 */
function haptic_exportable_settings($include_api_key = false)
{
    global $wpdb;

    $names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'haptic\\_%' ORDER BY option_name");
    $settings = [];

    foreach ($names as $name) {
        // Clés API (OpenAI, Anthropic, Gemini…) : seulement sur demande
        if (!haptic_is_exportable_option($name) || (!$include_api_key && substr($name, -8) === '_api_key')) {
            continue;
        }
        $settings[$name] = get_option($name);
    }

    return $settings;
}

/** Valeur importable : scalaires et tableaux de scalaires uniquement (jamais d'objet). */
function haptic_is_importable_value($value, $depth = 0)
{
    if (is_null($value) || is_scalar($value)) {
        return true;
    }
    if (is_array($value) && $depth < 5) {
        foreach ($value as $key => $item) {
            if (!is_scalar($key) || !haptic_is_importable_value($item, $depth + 1)) {
                return false;
            }
        }
        return true;
    }
    return false;
}

/** Remplace l'adresse du site d'origine par celle du site courant dans les textes (robots.txt…). */
function haptic_replace_site_url($value, $from, $to)
{
    if (is_string($value)) {
        return str_replace($from, $to, $value);
    }
    if (is_array($value)) {
        return array_map(function ($item) use ($from, $to) {
            return haptic_replace_site_url($item, $from, $to);
        }, $value);
    }
    return $value;
}

/* ---------- Export ---------- */

add_action('admin_post_haptic_export_settings', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }
    check_admin_referer('haptic_export_settings');

    $export = [
        'plugin'      => 'optimizr',
        'format'      => HAPTIC_EXPORT_FORMAT,
        'version'     => HAPTIC_OPTIMIZR_VERSION,
        'exported_at' => current_time('c'),
        'site'        => home_url(),
        'settings'    => haptic_exportable_settings(!empty($_POST['include_api_key'])),
    ];

    $host = sanitize_file_name((string) wp_parse_url(home_url(), PHP_URL_HOST));
    $filename = 'optimizr-' . ($host ?: 'site') . '-' . current_time('Y-m-d') . '.json';

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo wp_json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

/* ---------- Import ---------- */

/**
 * Importe un fichier d'export déjà décodé.
 *
 * @return array|WP_Error ['imported' => string[], 'ignored' => string[]]
 */
function haptic_import_settings(array $data, $adapt_urls = true)
{
    if (($data['plugin'] ?? '') !== 'optimizr' || !isset($data['settings']) || !is_array($data['settings'])) {
        return new WP_Error('haptic_import_invalid', 'Ce fichier n\'est pas un export d\'Optimizr.');
    }
    if ((int) ($data['format'] ?? 0) > HAPTIC_EXPORT_FORMAT) {
        return new WP_Error('haptic_import_format', 'Ce fichier vient d\'une version plus récente d\'Optimizr. Mettez d\'abord le plugin à jour.');
    }

    $from = untrailingslashit((string) ($data['site'] ?? ''));
    $to = untrailingslashit(home_url());
    $result = ['imported' => [], 'ignored' => []];
    $unsanitized = [];

    foreach ($data['settings'] as $name => $value) {
        // Export d'une version 2.x : réglages sous l'ancien préfixe
        if (is_string($name) && strpos($name, 'hpt_') === 0) {
            $name = 'haptic_' . substr($name, 4);
        }
        if (!is_string($name) || !haptic_is_exportable_option($name) || !haptic_is_importable_value($value)) {
            $result['ignored'][] = (string) $name;
            continue;
        }

        if ($adapt_urls && $from !== '' && $from !== $to) {
            $value = haptic_replace_site_url($value, $from, $to);
        }

        // Les fonctions de nettoyage des réglages enregistrés (register_setting) s'appliquent ici ;
        // celles d'un module désactivé ne sont pas chargées : le réglage sera nettoyé à son activation
        if (!has_filter("sanitize_option_{$name}")) {
            $unsanitized[] = $name;
        }
        update_option($name, $value);
        $result['imported'][] = $name;
    }

    // Un réglage réimporté avec sa fonction de nettoyage n'est plus en attente
    $pending = array_diff((array) get_option('haptic_import_unsanitized', []), array_diff($result['imported'], $unsanitized));
    $pending = array_values(array_unique(array_merge($pending, $unsanitized)));
    if ($pending) {
        update_option('haptic_import_unsanitized', $pending);
    } else {
        delete_option('haptic_import_unsanitized');
    }

    // Le sitemap suit les nouveaux réglages
    if (!wp_next_scheduled('haptic_sitemap_regenerate')) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'haptic_sitemap_regenerate');
    }

    return $result;
}

/**
 * Réglages importés sans fonction de nettoyage (module désactivé à l'import) : dès qu'elle est enregistrée,
 * c'est-à-dire à la première page d'administration après l'activation du module, le réglage repasse par update_option().
 */
function haptic_import_sanitize_pending()
{
    $pending = get_option('haptic_import_unsanitized');
    if (!$pending || !is_array($pending)) {
        return;
    }

    $left = [];
    foreach ($pending as $name) {
        $value = get_option($name, null);
        if ($value === null) {
            continue; // Réglage supprimé depuis
        }
        if (!has_filter("sanitize_option_{$name}")) {
            $left[] = $name;
            continue;
        }
        update_option($name, $value);
    }

    // Tous les modules actifs : ce qui reste n'aura jamais de fonction de nettoyage (réglage enregistré hors Settings API)
    $inactive = array_filter(array_keys(haptic_modules()), function ($slug) {
        return !haptic_is_module_active($slug);
    });
    if (!$inactive) {
        $left = [];
    }

    if ($left !== $pending) {
        if ($left) {
            update_option('haptic_import_unsanitized', $left);
        } else {
            delete_option('haptic_import_unsanitized');
        }
    }
}
// Après les register_setting des modules (admin_init, priorité 10)
add_action('admin_init', 'haptic_import_sanitize_pending', 1000);

add_action('admin_post_haptic_import_settings', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }
    check_admin_referer('haptic_import_settings');

    $file = $_FILES['haptic_import_file'] ?? null;
    $result = null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $result = new WP_Error('haptic_import_upload', 'Aucun fichier reçu. Choisissez un fichier .json exporté depuis Optimizr.');
    } elseif ($file['size'] > MB_IN_BYTES) {
        $result = new WP_Error('haptic_import_size', 'Fichier trop volumineux (1 Mo maximum).');
    } else {
        $data = json_decode((string) file_get_contents($file['tmp_name']), true);
        $result = is_array($data)
            ? haptic_import_settings($data, !empty($_POST['adapt_urls']))
            : new WP_Error('haptic_import_json', 'Le fichier n\'est pas un JSON valide.');
    }

    set_transient('haptic_import_result_' . get_current_user_id(), is_wp_error($result)
        ? ['error' => $result->get_error_message()]
        : $result, 5 * MINUTE_IN_SECONDS);

    wp_safe_redirect(add_query_arg('haptic_message', 'import', haptic_admin_tab_url('tools')));
    exit;
});

/* ---------- Onglet Outils ---------- */

function haptic_render_tools_tab()
{
    $result = get_transient('haptic_import_result_' . get_current_user_id());
    if ($result && sanitize_key(wp_unslash($_GET['haptic_message'] ?? '')) === 'import') {
        delete_transient('haptic_import_result_' . get_current_user_id());
    } else {
        $result = null;
    }

    $count = count(haptic_exportable_settings());
    ?>
    <div class="optimizr-page-head">
        <h2 class="optimizr-page-head__title">Outils</h2>
        <p class="optimizr-page-head__intro">Reproduisez la configuration de ce site sur un autre en quelques secondes.</p>
    </div>

    <?php if ($result) : ?>
        <?php if (!empty($result['error'])) : ?>
            <div class="optimizr-notice optimizr-notice--warn"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><span><strong>Import impossible.</strong> <?php echo esc_html($result['error']); ?></span></div>
        <?php else : ?>
            <div class="optimizr-notice optimizr-notice--ok"><?php echo wp_kses(haptic_icon('check', 18), haptic_kses_ui()); ?><span><strong><?php echo esc_html(sprintf('%d réglage(s) importé(s).', count($result['imported']))); ?></strong>
                <?php if ($result['ignored']) : ?>
                    <?php echo esc_html(sprintf('%d ignoré(s) : %s.', count($result['ignored']), implode(', ', $result['ignored']))); ?>
                <?php endif; ?>
                Vérifiez les onglets concernés.</span></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (sanitize_key(wp_unslash($_GET['haptic_message'] ?? '')) === 'uninstall') : ?>
        <div class="optimizr-notice optimizr-notice--ok"><?php echo wp_kses(haptic_icon('check', 18), haptic_kses_ui()); ?><span><strong>Choix enregistrés.</strong> Ils seront appliqués si le plugin est supprimé.</span></div>
    <?php endif; ?>

    <div class="optimizr-tools">
        <form class="optimizr-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="haptic_export_settings">
            <?php wp_nonce_field('haptic_export_settings'); ?>
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title">Exporter les réglages</h2>
                    <p class="optimizr-card__desc"><?php echo esc_html(sprintf('Télécharge un fichier JSON avec les %d réglages d\'Optimizr de ce site, modules désactivés compris.', $count)); ?></p>
                </div>
            </div>
            <div class="optimizr-card__body">
                <label class="optimizr-check">
                    <input type="checkbox" name="include_api_key" value="1">
                    <span>Inclure les clés API <span class="optimizr-muted">(à ne transmettre qu'à un site de confiance)</span></span>
                </label>
                <div><button type="submit" class="optimizr-btn optimizr-btn--primary">Télécharger le fichier</button></div>
            </div>
        </form>

        <form class="optimizr-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="haptic_import_settings">
            <?php wp_nonce_field('haptic_import_settings'); ?>
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title">Importer des réglages</h2>
                    <p class="optimizr-card__desc">Les réglages du fichier remplacent ceux de ce site ; les autres ne changent pas.</p>
                </div>
            </div>
            <div class="optimizr-card__body">
                <label class="optimizr-file">
                    <span class="screen-reader-text">Fichier d'export</span>
                    <input type="file" name="haptic_import_file" accept=".json,application/json" required>
                </label>
                <label class="optimizr-check">
                    <input type="checkbox" name="adapt_urls" value="1" checked>
                    <span>Remplacer l'adresse du site d'origine par <code><?php echo esc_html(home_url()); ?></code> <span class="optimizr-muted">(robots.txt…)</span></span>
                </label>
                <div><button type="submit" class="optimizr-btn optimizr-btn--primary">Importer</button></div>
            </div>
        </form>

        <?php haptic_render_uninstall_card(); ?>
    </div>

    <p class="optimizr-field__help">Pensez à vérifier ensuite les identifiants des services (Google Analytics, Clarity…) : ils sont propres à chaque site.</p>
    <?php
}
