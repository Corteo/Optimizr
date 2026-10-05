<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Onglet Redirections : formulaires (admin-post), listes et analyse des liens cassés.
 */

function haptic_redirects_tab_url(array $args = [])
{
    return add_query_arg($args, haptic_admin_tab_url('redirects'));
}

/** Message affiché au retour d'une action. */
function haptic_redirects_notice($type, $message)
{
    set_transient('haptic_redirects_notice_' . get_current_user_id(), ['type' => $type, 'message' => $message], MINUTE_IN_SECONDS);
}

function haptic_redirects_check_cap()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Access denied', 'optimizr'));
    }
}

/* ---------- Actions ---------- */

add_action('admin_post_haptic_redirect_save', function () {
    haptic_redirects_check_cap();
    check_admin_referer('haptic_redirect_save');
    $result = haptic_redirects_save(wp_strip_all_tags(wp_unslash($_POST['source'] ?? '')), wp_strip_all_tags(wp_unslash($_POST['target'] ?? '')), absint($_POST['code'] ?? 301), sanitize_text_field(wp_unslash($_POST['note'] ?? '')), absint($_POST['id'] ?? 0));

    if (is_wp_error($result)) {
        haptic_redirects_notice('warn', $result->get_error_message());
        wp_safe_redirect(haptic_redirects_tab_url([
            'source' => rawurlencode(wp_strip_all_tags(wp_unslash($_POST['source'] ?? ''))),
            'edit'   => absint($_POST['id'] ?? 0) ?: null,
        ]));
        exit;
    }

    // Une adresse qui redirige ne doit plus figurer parmi les pages introuvables
    global $wpdb;
    $wpdb->delete(haptic_404_table(), ['path' => haptic_redirects_normalize(wp_strip_all_tags(wp_unslash($_POST['source'] ?? '')))]);

    haptic_redirects_notice('ok', __('Redirect saved.', 'optimizr'));
    wp_safe_redirect(haptic_redirects_tab_url());
    exit;
});

add_action('admin_post_haptic_redirect_delete', function () {
    haptic_redirects_check_cap();
    check_admin_referer('haptic_redirect_delete');
    haptic_redirects_delete(absint($_GET['id'] ?? 0));
    haptic_redirects_notice('ok', __('Redirect deleted.', 'optimizr'));
    wp_safe_redirect(haptic_redirects_tab_url());
    exit;
});

add_action('admin_post_haptic_404_delete', function () {
    haptic_redirects_check_cap();
    check_admin_referer('haptic_404_delete');
    global $wpdb;
    if (!empty($_GET['all'])) {
        $wpdb->query($wpdb->prepare('TRUNCATE TABLE %i', haptic_404_table()));
        haptic_redirects_notice('ok', __('Not-found pages log cleared.', 'optimizr'));
    } else {
        $wpdb->delete(haptic_404_table(), ['id' => absint($_GET['id'] ?? 0)]);
        haptic_redirects_notice('ok', __('Address removed from the log.', 'optimizr'));
    }
    wp_safe_redirect(haptic_redirects_tab_url());
    exit;
});

add_action('admin_post_haptic_redirects_settings', function () {
    haptic_redirects_check_cap();
    check_admin_referer('haptic_redirects_settings');
    update_option('haptic_redir_auto', empty($_POST['haptic_redir_auto']) ? 0 : 1);
    update_option('haptic_redir_log404', empty($_POST['haptic_redir_log404']) ? 0 : 1);
    haptic_redirects_notice('ok', __('Settings saved.', 'optimizr'));
    wp_safe_redirect(haptic_redirects_tab_url());
    exit;
});

/** Valeur qu'un tableur lirait comme une formule (« = + - @ », tabulation, retour chariot), éventuellement déjà précédée d'apostrophes. */
const HAPTIC_REDIRECTS_CSV_FORMULA = "/^'*[=+\\-@\\t\\r]/";

/** Cellule exportée : une apostrophe devant une formule potentielle (titre de contenu dans une note…) la garde en texte. */
function haptic_redirects_csv_cell($value)
{
    $value = (string) $value;
    return preg_match(HAPTIC_REDIRECTS_CSV_FORMULA, $value) ? "'" . $value : $value;
}

add_action('admin_post_haptic_redirects_export', function () {
    haptic_redirects_check_cap();
    check_admin_referer('haptic_redirects_export');
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare('SELECT source, target, code, note, hits FROM %i ORDER BY source', haptic_redirects_table()), ARRAY_A);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="redirections-' . sanitize_file_name((string) wp_parse_url(home_url(), PHP_URL_HOST)) . '-' . current_time('Y-m-d') . '.csv"');
    $csv = new SplTempFileObject();
    $csv->fputcsv(['source', 'destination', 'type', 'note', 'visites'], ';', '"', '\\');
    foreach ($rows as $row) {
        $csv->fputcsv(array_map('haptic_redirects_csv_cell', array_values($row)), ';', '"', '\\');
    }
    $csv->rewind();
    // Indicateur UTF-8 : accents corrects à l'ouverture dans Excel
    $content = "\xEF\xBB\xBF";
    while (!$csv->eof()) {
        $content .= $csv->fgets();
    }
    echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fichier CSV téléchargé (pas de HTML), cellules neutralisées par haptic_redirects_csv_cell().
    exit;
});

add_action('admin_post_haptic_redirects_import', function () {
    haptic_redirects_check_cap();
    check_admin_referer('haptic_redirects_import');
    $file = $_FILES['haptic_redirects_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 2 * MB_IN_BYTES) {
        haptic_redirects_notice('warn', __('No CSV file received (2 MB maximum).', 'optimizr'));
        wp_safe_redirect(haptic_redirects_tab_url());
        exit;
    }

    $csv = new SplFileObject($file['tmp_name'], 'r');
    $first = (string) $csv->fgets();
    $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    $csv->rewind();

    $added = 0;
    $errors = [];
    $line = 0;
    while (!$csv->eof()) {
        $row = $csv->fgetcsv($delimiter, '"', '\\');
        if (!is_array($row)) {
            break;
        }
        $line++;
        $row = array_map(function ($cell) {
            $cell = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell));
            // Apostrophe ajoutée à l'export devant une formule potentielle (haptic_redirects_csv_cell()) : retirée
            return preg_match(HAPTIC_REDIRECTS_CSV_FORMULA, $cell) && $cell[0] === "'" ? substr($cell, 1) : $cell;
        }, $row);
        // En-tête et lignes vides ignorés
        if (empty($row[0]) || in_array(strtolower($row[0]), ['source', 'from', 'ancienne adresse'], true)) {
            continue;
        }
        $result = haptic_redirects_save($row[0], $row[1] ?? '', (int) ($row[2] ?? 301) ?: 301, $row[3] ?? __('CSV import', 'optimizr'));
        if (is_wp_error($result)) {
            /* translators: 1: CSV line number, 2: error message */
            $errors[] = sprintf(__('line %1$d: %2$s', 'optimizr'), $line, $result->get_error_message());
        } else {
            $added++;
        }
    }
    $csv = null;

    /* translators: %s: number of imported redirects */
    $message = sprintf(_n('%s redirect imported.', '%s redirects imported.', $added, 'optimizr'), number_format_i18n($added));
    if ($errors) {
        /* translators: 1: number of skipped lines, 2: list of errors */
        $message .= ' ' . sprintf(_n('%1$s line skipped — %2$s', '%1$s lines skipped — %2$s', count($errors), 'optimizr'), number_format_i18n(count($errors)), implode(_x('; ', 'separator between CSV import errors', 'optimizr'), array_slice($errors, 0, 5)) . (count($errors) > 5 ? '…' : ''));
    }
    haptic_redirects_notice($errors ? 'warn' : 'ok', $message);
    wp_safe_redirect(haptic_redirects_tab_url());
    exit;
});

/* ---------- Onglet ---------- */

function haptic_render_redirects_tab()
{
    global $wpdb;
    $table = haptic_redirects_table();

    $notice = get_transient('haptic_redirects_notice_' . get_current_user_id());
    delete_transient('haptic_redirects_notice_' . get_current_user_id());

    // Cache des jokers resynchronisé à chaque affichage : table modifiée hors de l'extension (restauration, phpMyAdmin)
    haptic_redirects_refresh_cache();

    // Modification d'une règle, ou création préremplie depuis le journal des 404 / les liens cassés
    $edit = !empty($_GET['edit']) ? $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, (int) $_GET['edit'])) : null;
    $prefill = $edit ? $edit->source : (isset($_GET['source']) ? haptic_redirects_normalize(wp_strip_all_tags(rawurldecode(wp_unslash($_GET['source'])))) : '');

    // Liste paginée et recherche
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $paged = max(1, absint($_GET['paged'] ?? 1));
    $per_page = 50;
    $offset = ($paged - 1) * $per_page;
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE source LIKE %s OR target LIKE %s', $table, $like, $like));
        $rules = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE source LIKE %s OR target LIKE %s ORDER BY created DESC LIMIT %d OFFSET %d', $table, $like, $like, $per_page, $offset));
    } else {
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
        $rules = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY created DESC LIMIT %d OFFSET %d', $table, $per_page, $offset));
    }

    $not_found = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY hits DESC, last_seen DESC LIMIT 50', haptic_404_table()));
    $not_found_total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', haptic_404_table()));
    $report = get_option('haptic_links_report');
    ?>
    <div class="optimizr-page-head">
        <h2 class="optimizr-page-head__title"><?php esc_html_e('Redirects & links', 'optimizr'); ?></h2>
        <p class="optimizr-page-head__intro"><?php esc_html_e('Redirect old addresses, monitor not-found pages and spot broken links.', 'optimizr'); ?></p>
    </div>

    <?php if ($notice) : ?>
        <div class="optimizr-notice optimizr-notice--<?php echo esc_attr($notice['type']); ?>"><?php echo wp_kses(haptic_icon($notice['type'] === 'ok' ? 'check' : 'alert', 18), haptic_kses_ui()); ?><span><?php echo esc_html($notice['message']); ?></span></div>
    <?php endif; ?>

    <div class="optimizr-layout optimizr-layout--aside">
        <div class="optimizr-layout__main">

            <form class="optimizr-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="haptic-redirect-form">
                <input type="hidden" name="action" value="haptic_redirect_save">
                <input type="hidden" name="id" value="<?php echo esc_attr($edit ? $edit->id : 0); ?>">
                <?php wp_nonce_field('haptic_redirect_save'); ?>
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title"><?php echo $edit ? esc_html__('Edit redirect', 'optimizr') : esc_html__('Add a redirect', 'optimizr'); ?></h2>
                        <p class="optimizr-card__desc"><?php echo wp_kses_post(__('Relative (<code>/old-page</code>) or full address. End the source with <code>*</code> to redirect a whole folder: <code>/blog/*</code> → <code>/news/*</code>.', 'optimizr')); ?></p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <div class="optimizr-redirect-form">
                        <label>
                            <span class="optimizr-field__label"><?php esc_html_e('Old address', 'optimizr'); ?></span>
                            <input type="text" name="source" value="<?php echo esc_attr($prefill); ?>" placeholder="<?php esc_attr_e('/old-page', 'optimizr'); ?>" required spellcheck="false">
                        </label>
                        <span class="optimizr-redirect-form__arrow" aria-hidden="true">→</span>
                        <label>
                            <span class="optimizr-field__label"><?php esc_html_e('Destination', 'optimizr'); ?></span>
                            <input type="text" name="target" value="<?php echo esc_attr($edit ? $edit->target : ''); ?>" placeholder="<?php esc_attr_e('/new-page or https://…', 'optimizr'); ?>" spellcheck="false" data-redirect-target>
                        </label>
                        <label>
                            <span class="optimizr-field__label"><?php echo esc_html_x('Type', 'redirect type', 'optimizr'); ?></span>
                            <select name="code" data-redirect-code>
                                <?php foreach (haptic_redirect_codes() as $code => $label) : ?>
                                    <option value="<?php echo (int) $code; ?>" <?php selected($edit ? (int) $edit->code : 301, $code); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label>
                        <span class="optimizr-field__label"><?php echo esc_html_x('Note', 'redirect note field', 'optimizr'); ?> <span class="optimizr-muted"><?php esc_html_e('(optional)', 'optimizr'); ?></span></span>
                        <input type="text" name="note" value="<?php echo esc_attr($edit ? $edit->note : ''); ?>" maxlength="255">
                    </label>
                    <div class="optimizr-inline">
                        <button type="submit" class="optimizr-btn optimizr-btn--primary"><?php echo $edit ? esc_html__('Save', 'optimizr') : esc_html__('Add redirect', 'optimizr'); ?></button>
                        <?php if ($edit || $prefill) : ?>
                            <a class="optimizr-btn optimizr-btn--ghost" href="<?php echo esc_url(haptic_redirects_tab_url()); ?>"><?php esc_html_e('Cancel', 'optimizr'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title"><?php esc_html_e('Redirects', 'optimizr'); ?> <span class="optimizr-badge"><?php echo (int) $total; ?></span></h2>
                    </div>
                    <form method="get" class="optimizr-search">
                        <input type="hidden" name="page" value="haptic-options">
                        <input type="hidden" name="tab" value="redirects">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search', 'optimizr'); ?>" aria-label="<?php esc_attr_e('Search redirects', 'optimizr'); ?>">
                    </form>
                </div>
                <?php if (!$rules) : ?>
                    <p class="optimizr-card__empty"><?php echo $search !== '' ? esc_html__('No matching redirects.', 'optimizr') : esc_html__('No redirects yet.', 'optimizr'); ?></p>
                <?php else : ?>
                    <div class="optimizr-table-wrap optimizr-table-wrap--flush">
                        <table class="optimizr-table optimizr-table--left">
                            <thead><tr><th scope="col"><?php esc_html_e('Old address', 'optimizr'); ?></th><th scope="col"><?php esc_html_e('Destination', 'optimizr'); ?></th><th scope="col"><?php echo esc_html_x('Type', 'redirect type', 'optimizr'); ?></th><th scope="col"><?php esc_html_e('Visits', 'optimizr'); ?></th><th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'optimizr'); ?></span></th></tr></thead>
                            <tbody>
                                <?php foreach ($rules as $rule) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html($rule->source); ?></code><?php if ($rule->note) : ?><br><span class="optimizr-muted"><?php echo esc_html($rule->note); ?></span><?php endif; ?></td>
                                        <td><?php echo (int) $rule->code === 410 ? '<span class="optimizr-muted">—</span>' : '<code>' . esc_html($rule->target) . '</code>'; ?></td>
                                        <td><?php echo (int) $rule->code; ?></td>
                                        <td><?php echo (int) $rule->hits; ?><?php if ($rule->last_hit) : ?><br><span class="optimizr-muted"><?php /* translators: date format for the last redirect hit, see https://www.php.net/manual/datetime.format.php */ echo esc_html(mysql2date(__('M j, Y', 'optimizr'), $rule->last_hit)); ?></span><?php endif; ?></td>
                                        <td class="optimizr-table__actions">
                                            <a class="optimizr-link" href="<?php echo esc_url(haptic_redirects_tab_url(['edit' => $rule->id])); ?>#haptic-redirect-form"><?php esc_html_e('Edit', 'optimizr'); ?></a>
                                            <a class="optimizr-link optimizr-link--danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=haptic_redirect_delete&id=' . $rule->id), 'haptic_redirect_delete')); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this redirect?', 'optimizr')); ?>');"><?php esc_html_e('Delete', 'optimizr'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total > $per_page) : ?>
                        <div class="optimizr-pagination">
                            <?php echo wp_kses_post(paginate_links(['base' => haptic_redirects_tab_url(['s' => $search ?: null]) . '%_%', 'format' => '&paged=%#%', 'current' => $paged, 'total' => (int) ceil($total / $per_page)])); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title"><?php esc_html_e('Not-found pages', 'optimizr'); ?> <span class="optimizr-badge"><?php echo (int) $not_found_total; ?></span></h2>
                        <p class="optimizr-card__desc"><?php esc_html_e('Addresses requested by visitors or bots that end in a 404 error, most frequent first.', 'optimizr'); ?></p>
                    </div>
                    <?php if ($not_found_total) : ?>
                        <a class="optimizr-btn optimizr-btn--ghost" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=haptic_404_delete&all=1'), 'haptic_404_delete')); ?>" onclick="return confirm('<?php echo esc_js(__('Clear the not-found pages log?', 'optimizr')); ?>');"><?php esc_html_e('Clear', 'optimizr'); ?></a>
                    <?php endif; ?>
                </div>
                <?php if (!get_option('haptic_redir_log404', 1)) : ?>
                    <p class="optimizr-card__empty"><?php esc_html_e('The log is disabled in the settings below.', 'optimizr'); ?></p>
                <?php elseif (!$not_found) : ?>
                    <p class="optimizr-card__empty"><?php esc_html_e('No not-found pages recorded.', 'optimizr'); ?></p>
                <?php else : ?>
                    <div class="optimizr-table-wrap optimizr-table-wrap--flush">
                        <table class="optimizr-table optimizr-table--left">
                            <thead><tr><th scope="col"><?php esc_html_e('Address', 'optimizr'); ?></th><th scope="col"><?php esc_html_e('Visits', 'optimizr'); ?></th><th scope="col"><?php echo esc_html_x('Last', 'last visit column', 'optimizr'); ?></th><th scope="col"><?php esc_html_e('Referrer', 'optimizr'); ?></th><th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'optimizr'); ?></span></th></tr></thead>
                            <tbody>
                                <?php foreach ($not_found as $row) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html($row->path); ?></code></td>
                                        <td><?php echo (int) $row->hits; ?></td>
                                        <td><?php /* translators: date format for the last visit of a not-found page, see https://www.php.net/manual/datetime.format.php */ echo esc_html(mysql2date(__('M j, H:i', 'optimizr'), $row->last_seen)); ?></td>
                                        <td class="optimizr-muted"><?php echo $row->referrer !== '' ? esc_html($row->referrer) : '—'; ?></td>
                                        <td class="optimizr-table__actions">
                                            <a class="optimizr-link" href="<?php echo esc_url(haptic_redirects_tab_url(['source' => rawurlencode($row->path)])); ?>#haptic-redirect-form"><?php echo esc_html_x('Redirect', 'action: create a redirect', 'optimizr'); ?></a>
                                            <a class="optimizr-link optimizr-link--danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=haptic_404_delete&id=' . $row->id), 'haptic_404_delete')); ?>"><?php esc_html_e('Ignore', 'optimizr'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="optimizr-card" data-links-scan data-nonce="<?php echo esc_attr(wp_create_nonce('haptic_links_scan')); ?>" data-redirect-url="<?php echo esc_url(haptic_redirects_tab_url()); ?>">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title"><?php esc_html_e('Broken links', 'optimizr'); ?></h2>
                        <p class="optimizr-card__desc"><?php esc_html_e('Checks every link and image in published content. Keep the page open during the scan.', 'optimizr'); ?></p>
                    </div>
                    <button type="button" class="optimizr-btn optimizr-btn--secondary" data-links-start><?php echo wp_kses(haptic_icon('search', 16), haptic_kses_ui()); ?><?php esc_html_e('Scan links', 'optimizr'); ?></button>
                </div>
                <div class="optimizr-card__body">
                    <div class="optimizr-bulk__progress" data-links-progress hidden>
                        <div class="optimizr-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e('Link scan', 'optimizr'); ?>"><span></span></div>
                        <span class="optimizr-muted" data-links-status role="status"></span>
                    </div>
                    <p class="optimizr-muted" data-links-summary>
                        <?php if ($report && empty($report['running'])) : ?>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: 1: date of the last scan, 2: number of links checked (e.g. "12 links checked"), 3: number of issues (e.g. "2 issues") */
                                __('Last scan on %1$s: %2$s, %3$s.', 'optimizr'),
                                /* translators: date format for the last link scan, see https://www.php.net/manual/datetime.format.php */
                                mysql2date(__('F j, Y \a\t H:i', 'optimizr'), $report['date']),
                                /* translators: %s: number of links checked */
                                sprintf(_n('%s link checked', '%s links checked', (int) $report['checked'], 'optimizr'), number_format_i18n((int) $report['checked'])),
                                /* translators: %s: number of issues found */
                                sprintf(_n('%s issue', '%s issues', count($report['issues']), 'optimizr'), number_format_i18n(count($report['issues'])))
                            ));
                            ?>
                        <?php elseif ($report && !empty($report['running'])) : ?>
                            <?php esc_html_e('The last scan was interrupted: run it again.', 'optimizr'); ?>
                        <?php else : ?>
                            <?php esc_html_e('No scan yet.', 'optimizr'); ?>
                        <?php endif; ?>
                    </p>
                    <div class="optimizr-table-wrap optimizr-table-wrap--flush" data-links-results <?php echo empty($report['issues']) ? 'hidden' : ''; ?>>
                        <table class="optimizr-table optimizr-table--left">
                            <thead><tr><th scope="col"><?php esc_html_e('Link', 'optimizr'); ?></th><th scope="col"><?php esc_html_e('Issue', 'optimizr'); ?></th><th scope="col"><?php esc_html_e('Found in', 'optimizr'); ?></th><th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'optimizr'); ?></span></th></tr></thead>
                            <tbody data-links-rows>
                                <?php foreach ((array) ($report['issues'] ?? []) as $issue) : ?>
                                    <?php haptic_links_render_row($issue); ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <aside class="optimizr-layout__aside">
            <form class="optimizr-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="haptic_redirects_settings">
                <?php wp_nonce_field('haptic_redirects_settings'); ?>
                <div class="optimizr-card__header"><h2 class="optimizr-card__title"><?php esc_html_e('Settings', 'optimizr'); ?></h2></div>
                <div class="optimizr-card__body">
                    <div class="optimizr-check-row">
                        <?php echo wp_kses(haptic_switch_input('haptic_redir_auto', get_option('haptic_redir_auto', 1), __('Automatic redirect', 'optimizr')), haptic_kses_ui()); ?>
                        <span><strong><?php esc_html_e('Automatic redirect', 'optimizr'); ?></strong><br><span class="optimizr-muted"><?php esc_html_e('When the address of published content changes, the old one redirects to the new one (301).', 'optimizr'); ?></span></span>
                    </div>
                    <div class="optimizr-check-row">
                        <?php echo wp_kses(haptic_switch_input('haptic_redir_log404', get_option('haptic_redir_log404', 1), __('Not-found pages log', 'optimizr')), haptic_kses_ui()); ?>
                        <span><strong><?php esc_html_e('Not-found pages log', 'optimizr'); ?></strong><br><span class="optimizr-muted"><?php esc_html_e('No IP address: only the requested address and the referring page are kept.', 'optimizr'); ?></span></span>
                    </div>
                    <div><button type="submit" class="optimizr-btn optimizr-btn--secondary"><?php esc_html_e('Save', 'optimizr'); ?></button></div>
                </div>
            </form>

            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title"><?php esc_html_e('CSV import / export', 'optimizr'); ?></h2>
                        <p class="optimizr-card__desc"><?php esc_html_e('Columns: old address; destination; type (301 by default); note. Handy for a site redesign.', 'optimizr'); ?></p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="optimizr-inline">
                        <input type="hidden" name="action" value="haptic_redirects_import">
                        <?php wp_nonce_field('haptic_redirects_import'); ?>
                        <label class="optimizr-file"><span class="screen-reader-text"><?php esc_html_e('CSV file', 'optimizr'); ?></span><input type="file" name="haptic_redirects_file" accept=".csv,text/csv" required></label>
                        <button type="submit" class="optimizr-btn optimizr-btn--secondary"><?php esc_html_e('Import', 'optimizr'); ?></button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="haptic_redirects_export">
                        <?php wp_nonce_field('haptic_redirects_export'); ?>
                        <button type="submit" class="optimizr-btn optimizr-btn--ghost" <?php disabled(!$total); ?>><?php esc_html_e('Export redirects', 'optimizr'); ?></button>
                    </form>
                </div>
            </section>
        </aside>
    </div>
    <?php
}

/** Ligne du tableau des liens cassés (aussi utilisée pour le gabarit JavaScript). */
function haptic_links_render_row(array $issue)
{
    $states = ['broken' => 'warn', 'error' => 'warn', 'blocked' => 'off'];
    ?>
    <tr>
        <td><a href="<?php echo esc_url($issue['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(wp_html_excerpt($issue['url'], 70, '…')); ?></a></td>
        <td><span class="optimizr-status optimizr-status--<?php echo esc_attr($states[$issue['state']] ?? 'warn'); ?>"><?php echo esc_html($issue['label']); ?></span></td>
        <td>
            <?php foreach (array_slice($issue['sources'], 0, 3) as $post_id) : ?>
                <a class="optimizr-link" href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php echo esc_html(get_the_title($post_id) ?: '#' . $post_id); ?></a><br>
            <?php endforeach; ?>
            <?php if (count($issue['sources']) > 3) : ?><span class="optimizr-muted"><?php /* translators: %s: number of other content items containing the link */ echo esc_html(sprintf(_n('+ %s other', '+ %s others', count($issue['sources']) - 3, 'optimizr'), number_format_i18n(count($issue['sources']) - 3))); ?></span><?php endif; ?>
        </td>
        <td class="optimizr-table__actions">
            <?php if (!empty($issue['internal']) && in_array($issue['state'], ['broken'], true)) : ?>
                <a class="optimizr-link" href="<?php echo esc_url(haptic_redirects_tab_url(['source' => rawurlencode(haptic_redirects_normalize($issue['url']))])); ?>#haptic-redirect-form"><?php echo esc_html_x('Redirect', 'action: create a redirect', 'optimizr'); ?></a>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}
