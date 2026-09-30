<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Assistant IA : traitement en masse.
 *
 * Le navigateur demande les éléments un par un (action AJAX hpt_ai_bulk_next) : une requête = un élément,
 * donc jamais de délai d'exécution dépassé, et un arrêt possible à tout moment sans rien perdre.
 * L'interface est dans l'onglet Assistant IA, son script dans js/optimizr-admin.js.
 */

/**
 * Tâches disponibles.
 *
 * @return array[] slug => ['label', 'unit', 'empty']
 */
function hpt_ai_bulk_tasks()
{
    $tasks = [
        'alt' => [
            'label' => 'Balises ALT des images',
            'unit'  => 'image(s) sans balise ALT',
            'empty' => 'Toutes les images ont une balise ALT.',
        ],
    ];

    // Les descriptions ne servent que si le module SEO les affiche
    if (hpt_is_module_active('seo')) {
        $tasks['descriptions'] = [
            'label' => 'Meta descriptions des contenus',
            'unit'  => 'contenu(s) sans description personnalisée',
            'empty' => 'Tous les contenus ont une description personnalisée.',
        ];
    }

    return $tasks;
}

/**
 * Requête SQL des éléments à traiter (sans SELECT), ou null si la tâche n'a rien à traiter.
 *
 * @param int[] $skip Éléments ignorés pendant ce traitement (erreur non bloquante)
 */
function hpt_ai_bulk_sql($task, array $skip = [])
{
    global $wpdb;

    $skip_sql = $skip ? ' AND p.ID NOT IN (' . implode(',', array_map('intval', $skip)) . ')' : '';

    if ($task === 'alt') {
        return "FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
              AND p.post_mime_type LIKE 'image/%'
              AND p.post_mime_type <> 'image/svg+xml'
              AND (m.meta_value IS NULL OR TRIM(m.meta_value) = '')" . $skip_sql;
    }

    if ($task === 'descriptions') {
        $types = array_filter((array) get_option('hpt_sitemap_post_types', ['post', 'page']));
        if (!$types) {
            return null;
        }
        $types_sql = implode(',', array_map(function ($type) use ($wpdb) {
            return $wpdb->prepare('%s', $type);
        }, $types));

        // Pages privées de WooCommerce : pas de description utile
        $excluded = array_filter(array_map('intval', [
            get_option('woocommerce_cart_page_id'),
            get_option('woocommerce_checkout_page_id'),
            get_option('woocommerce_myaccount_page_id'),
        ]));
        $excluded_sql = $excluded ? ' AND p.ID NOT IN (' . implode(',', $excluded) . ')' : '';

        return "FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_hpt_og_desc'
            WHERE p.post_type IN ($types_sql)
              AND p.post_status = 'publish'
              AND p.post_content <> ''
              AND (m.meta_value IS NULL OR TRIM(m.meta_value) = '')" . $excluded_sql . $skip_sql;
    }

    return null;
}

function hpt_ai_bulk_count($task, array $skip = [])
{
    global $wpdb;
    $sql = hpt_ai_bulk_sql($task, $skip);
    return $sql ? (int) $wpdb->get_var('SELECT COUNT(*) ' . $sql) : 0;
}

/** Tous les éléments restant à traiter (pour l'estimation du coût). */
function hpt_ai_bulk_ids($task)
{
    global $wpdb;
    $sql = hpt_ai_bulk_sql($task);
    return $sql ? array_map('intval', $wpdb->get_col('SELECT p.ID ' . $sql . ' ORDER BY p.ID DESC')) : [];
}

function hpt_ai_bulk_next_id($task, array $skip = [])
{
    global $wpdb;
    $sql = hpt_ai_bulk_sql($task, $skip);
    return $sql ? (int) $wpdb->get_var('SELECT p.ID ' . $sql . ' ORDER BY p.ID DESC LIMIT 1') : 0;
}

/**
 * Traite l'élément suivant d'une tâche.
 */
function hpt_ai_bulk_next()
{
    check_ajax_referer('hpt_ai_bulk', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['status' => 'fatal', 'message' => 'Permissions insuffisantes.']);
    }

    $task = sanitize_key($_POST['task'] ?? '');
    if (!isset(hpt_ai_bulk_tasks()[$task])) {
        wp_send_json_error(['status' => 'fatal', 'message' => 'Tâche inconnue.']);
    }

    $skip = array_slice(array_filter(array_map('intval', (array) ($_POST['skip'] ?? []))), 0, 1000);
    $id = hpt_ai_bulk_next_id($task, $skip);

    if (!$id) {
        wp_send_json_success(['status' => 'done', 'remaining' => 0]);
    }

    $result = $task === 'alt' ? hpt_ai_generate_alt($id) : hpt_ai_generate_description($id);
    $item = [
        'id'    => $id,
        'title' => get_the_title($id) ?: '#' . $id,
        'link'  => get_edit_post_link($id, 'raw'),
    ];

    if (is_wp_error($result)) {
        $status = (int) ($result->get_error_data()['status'] ?? -1);
        // Clé absente ou refusée, quota atteint, OpenAI injoignable : inutile de continuer
        $fatal = $result->get_error_code() === 'hpt_ai_no_key'
            || ($result->get_error_code() === 'hpt_ai_http' && in_array($status, [0, 401, 403, 429], true));

        wp_send_json_success($item + [
            'status'    => $fatal ? 'fatal' : 'skipped',
            'message'   => $result->get_error_message(),
            'remaining' => hpt_ai_bulk_count($task, array_merge($skip, [$id])),
        ]);
    }

    if ($task === 'alt') {
        update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($result));
    } else {
        update_post_meta($id, '_hpt_og_desc', sanitize_textarea_field($result));
    }

    wp_send_json_success($item + [
        'status'    => 'ok',
        'text'      => $result,
        'cost'      => hpt_ai_last_cost(),
        'remaining' => hpt_ai_bulk_count($task, $skip),
    ]);
}
add_action('wp_ajax_hpt_ai_bulk_next', 'hpt_ai_bulk_next');

// Carte « Traitement en masse » de l'onglet Assistant IA
add_action('admin_init', function () {
    add_settings_section(
        'hpt_ai_bulk_section',
        'Traitement en masse',
        function () {
            echo '<p>Complète en une fois ce qui manque sur le site. Les éléments déjà renseignés ne sont jamais modifiés.</p>';
        },
        hpt_settings_page('ai')
    );
    add_settings_field('hpt_ai_bulk', '', 'hpt_ai_bulk_callback', hpt_settings_page('ai'), 'hpt_ai_bulk_section', ['layout' => 'stacked']);
});

function hpt_ai_bulk_callback()
{
    $has_key = (bool) get_option('hpt_openai_api_key');

    if (!$has_key) {
        echo '<div class="optimizr-notice optimizr-notice--warn">' . hpt_icon('alert', 18) . '<span>Renseignez et enregistrez d\'abord la clé API ci-dessus.</span></div>';
    }

    echo '<div class="optimizr-bulk" data-optimizr-bulk data-nonce="' . esc_attr(wp_create_nonce('hpt_ai_bulk')) . '">';

    foreach (hpt_ai_bulk_tasks() as $slug => $task) {
        $ids = hpt_ai_bulk_ids($slug);
        $count = count($ids);
        $model = $slug === 'alt' ? hpt_ai_vision_model() : hpt_ai_current_model();
        $estimate = $count ? hpt_ai_estimate($slug, $ids)['per_model'][$model] : null;
        ?>
        <div class="optimizr-bulk__task" data-task="<?php echo esc_attr($slug); ?>" data-count="<?php echo esc_attr($count); ?>" data-unit="<?php echo esc_attr($task['unit']); ?>" data-empty="<?php echo esc_attr($task['empty']); ?>">
            <div class="optimizr-bulk__head">
                <div class="optimizr-bulk__text">
                    <strong><?php echo esc_html($task['label']); ?></strong>
                    <span class="optimizr-field__help" data-bulk-summary><?php echo esc_html($count ? $count . ' ' . $task['unit'] . '.' : $task['empty']); ?></span>
                    <?php if ($estimate) : ?>
                        <span class="optimizr-bulk__estimate" data-bulk-estimate>
                            <?php
                            echo esc_html(sprintf(
                                'Coût estimé : %s pour l\'ensemble (≈ %s par élément) avec %s.',
                                hpt_ai_format_cost($estimate['total']),
                                hpt_ai_format_unit($estimate['unit']),
                                $model
                            ));
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
                <button type="button" class="optimizr-btn optimizr-btn--secondary" data-bulk-start <?php disabled(!$has_key || !$count); ?>>
                    <?php echo hpt_icon('sparkles', 16); ?>Générer
                </button>
                <button type="button" class="optimizr-btn optimizr-btn--ghost" data-bulk-stop hidden>Arrêter</button>
            </div>
            <div class="optimizr-bulk__progress" data-bulk-progress hidden>
                <div class="optimizr-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php echo esc_attr($task['label']); ?>"><span></span></div>
                <span class="optimizr-muted" data-bulk-status role="status"></span>
            </div>
            <ol class="optimizr-bulk__log" data-bulk-log hidden></ol>
        </div>
        <?php
    }

    echo '</div>';
    echo '<p class="optimizr-field__help">Chaque élément correspond à une requête facturée par OpenAI. Gardez cette page ouverte pendant le traitement ; vous pouvez l\'arrêter à tout moment, ce qui a été généré reste enregistré. Les textes générés sont modifiables ensuite dans la médiathèque ou dans la metabox SEO de chaque contenu.</p>';
}
