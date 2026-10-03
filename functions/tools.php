<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Onglet Outils : export et import des réglages d'Optimizr (fichier JSON),
 * pour configurer un site puis reproduire ses réglages sur les suivants.
 */

/** Format du fichier d'export, à incrémenter si sa structure change. */
const HPT_EXPORT_FORMAT = 1;

/**
 * Options internes, propres à chaque site : jamais exportées ni importées.
 */
function hpt_settings_internal_options()
{
    return apply_filters('hpt_settings_internal_options', [
        'hpt_optimizr_version',
        'hpt_sitemap_history',
        'hpt_sitemap_error',
        'hpt_sitemap_last_generated',
        'hpt_ai_usage',
        'hpt_security_log',
        'hpt_links_queue',
        'hpt_links_report',
        'hpt_indexnow_key',
        'hpt_indexnow_last',
        // Propre à chaque site, et à ne pas diffuser
        'hpt_sec_login_slug',
        // Identifiants de pages propres à chaque site ; un import ne doit pas fermer le site
        'hpt_404_page',
        'hpt_maintenance',
        'hpt_maintenance_page',
        'hpt_maintenance_until',
        // Ce qu'efface la suppression du plugin sur ce site (cf. functions/uninstall.php)
        'hpt_uninstall',
        // Canal de mise à jour : propre à chaque installation (un site client ne doit pas hériter du canal bêta)
        'hpt_update_channel',
    ]);
}

function hpt_is_exportable_option($name)
{
    return (bool) preg_match('/^hpt_[a-z0-9_]+$/', $name) && !in_array($name, hpt_settings_internal_options(), true);
}

/**
 * Réglages exportables : toutes les options hpt_* du site, y compris celles des modules désactivés.
 *
 * @return array nom => valeur
 */
function hpt_exportable_settings($include_api_key = false)
{
    global $wpdb;

    $names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'hpt\\_%' ORDER BY option_name");
    $settings = [];

    foreach ($names as $name) {
        // Clés API (OpenAI, Anthropic, Gemini…) : seulement sur demande
        if (!hpt_is_exportable_option($name) || (!$include_api_key && substr($name, -8) === '_api_key')) {
            continue;
        }
        $settings[$name] = get_option($name);
    }

    return $settings;
}

/** Valeur importable : scalaires et tableaux de scalaires uniquement (jamais d'objet). */
function hpt_is_importable_value($value, $depth = 0)
{
    if (is_null($value) || is_scalar($value)) {
        return true;
    }
    if (is_array($value) && $depth < 5) {
        foreach ($value as $key => $item) {
            if (!is_scalar($key) || !hpt_is_importable_value($item, $depth + 1)) {
                return false;
            }
        }
        return true;
    }
    return false;
}

/** Remplace l'adresse du site d'origine par celle du site courant dans les textes (robots.txt…). */
function hpt_replace_site_url($value, $from, $to)
{
    if (is_string($value)) {
        return str_replace($from, $to, $value);
    }
    if (is_array($value)) {
        return array_map(function ($item) use ($from, $to) {
            return hpt_replace_site_url($item, $from, $to);
        }, $value);
    }
    return $value;
}

/* ---------- Export ---------- */

add_action('admin_post_hpt_export_settings', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }
    check_admin_referer('hpt_export_settings');

    $export = [
        'plugin'      => 'optimizr',
        'format'      => HPT_EXPORT_FORMAT,
        'version'     => HPT_OPTIMIZR_VERSION,
        'exported_at' => current_time('c'),
        'site'        => home_url(),
        'settings'    => hpt_exportable_settings(!empty($_POST['include_api_key'])),
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
function hpt_import_settings(array $data, $adapt_urls = true)
{
    if (($data['plugin'] ?? '') !== 'optimizr' || !isset($data['settings']) || !is_array($data['settings'])) {
        return new WP_Error('hpt_import_invalid', 'Ce fichier n\'est pas un export d\'Optimizr.');
    }
    if ((int) ($data['format'] ?? 0) > HPT_EXPORT_FORMAT) {
        return new WP_Error('hpt_import_format', 'Ce fichier vient d\'une version plus récente d\'Optimizr. Mettez d\'abord le plugin à jour.');
    }

    $from = untrailingslashit((string) ($data['site'] ?? ''));
    $to = untrailingslashit(home_url());
    $result = ['imported' => [], 'ignored' => []];

    foreach ($data['settings'] as $name => $value) {
        if (!is_string($name) || !hpt_is_exportable_option($name) || !hpt_is_importable_value($value)) {
            $result['ignored'][] = (string) $name;
            continue;
        }

        if ($adapt_urls && $from !== '' && $from !== $to) {
            $value = hpt_replace_site_url($value, $from, $to);
        }

        // Les fonctions de nettoyage des réglages enregistrés (register_setting) s'appliquent ici
        update_option($name, $value);
        $result['imported'][] = $name;
    }

    // Le sitemap suit les nouveaux réglages
    if (!wp_next_scheduled('hpt_sitemap_regenerate')) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'hpt_sitemap_regenerate');
    }

    return $result;
}

add_action('admin_post_hpt_import_settings', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }
    check_admin_referer('hpt_import_settings');

    $file = $_FILES['hpt_import_file'] ?? null;
    $result = null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $result = new WP_Error('hpt_import_upload', 'Aucun fichier reçu. Choisissez un fichier .json exporté depuis Optimizr.');
    } elseif ($file['size'] > MB_IN_BYTES) {
        $result = new WP_Error('hpt_import_size', 'Fichier trop volumineux (1 Mo maximum).');
    } else {
        $data = json_decode((string) file_get_contents($file['tmp_name']), true);
        $result = is_array($data)
            ? hpt_import_settings($data, !empty($_POST['adapt_urls']))
            : new WP_Error('hpt_import_json', 'Le fichier n\'est pas un JSON valide.');
    }

    set_transient('hpt_import_result_' . get_current_user_id(), is_wp_error($result)
        ? ['error' => $result->get_error_message()]
        : $result, 5 * MINUTE_IN_SECONDS);

    wp_safe_redirect(add_query_arg('hpt_message', 'import', hpt_admin_tab_url('tools')));
    exit;
});

/* ---------- Onglet Outils ---------- */

function hpt_render_tools_tab()
{
    $result = get_transient('hpt_import_result_' . get_current_user_id());
    if ($result && ($_GET['hpt_message'] ?? '') === 'import') {
        delete_transient('hpt_import_result_' . get_current_user_id());
    } else {
        $result = null;
    }

    $count = count(hpt_exportable_settings());
    ?>
    <div class="optimizr-page-head">
        <h2 class="optimizr-page-head__title">Outils</h2>
        <p class="optimizr-page-head__intro">Reproduisez la configuration de ce site sur un autre en quelques secondes.</p>
    </div>

    <?php if ($result) : ?>
        <?php if (!empty($result['error'])) : ?>
            <div class="optimizr-notice optimizr-notice--warn"><?php echo hpt_icon('alert', 18); ?><span><strong>Import impossible.</strong> <?php echo esc_html($result['error']); ?></span></div>
        <?php else : ?>
            <div class="optimizr-notice optimizr-notice--ok"><?php echo hpt_icon('check', 18); ?><span><strong><?php echo esc_html(sprintf('%d réglage(s) importé(s).', count($result['imported']))); ?></strong>
                <?php if ($result['ignored']) : ?>
                    <?php echo esc_html(sprintf('%d ignoré(s) : %s.', count($result['ignored']), implode(', ', $result['ignored']))); ?>
                <?php endif; ?>
                Vérifiez les onglets concernés.</span></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (($_GET['hpt_message'] ?? '') === 'uninstall') : ?>
        <div class="optimizr-notice optimizr-notice--ok"><?php echo hpt_icon('check', 18); ?><span><strong>Choix enregistrés.</strong> Ils seront appliqués si le plugin est supprimé.</span></div>
    <?php endif; ?>

    <div class="optimizr-tools">
        <form class="optimizr-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="hpt_export_settings">
            <?php wp_nonce_field('hpt_export_settings'); ?>
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
            <input type="hidden" name="action" value="hpt_import_settings">
            <?php wp_nonce_field('hpt_import_settings'); ?>
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title">Importer des réglages</h2>
                    <p class="optimizr-card__desc">Les réglages du fichier remplacent ceux de ce site ; les autres ne changent pas.</p>
                </div>
            </div>
            <div class="optimizr-card__body">
                <label class="optimizr-file">
                    <span class="screen-reader-text">Fichier d'export</span>
                    <input type="file" name="hpt_import_file" accept=".json,application/json" required>
                </label>
                <label class="optimizr-check">
                    <input type="checkbox" name="adapt_urls" value="1" checked>
                    <span>Remplacer l'adresse du site d'origine par <code><?php echo esc_html(home_url()); ?></code> <span class="optimizr-muted">(robots.txt…)</span></span>
                </label>
                <div><button type="submit" class="optimizr-btn optimizr-btn--primary">Importer</button></div>
            </div>
        </form>

        <?php hpt_render_uninstall_card(); ?>
    </div>

    <p class="optimizr-field__help">Pensez à vérifier ensuite les identifiants des services (Google Analytics, Clarity…) : ils sont propres à chaque site.</p>
    <?php
}
