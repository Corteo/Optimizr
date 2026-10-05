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
        wp_die('Accès refusé');
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

    haptic_redirects_notice('ok', 'Redirection enregistrée.');
    wp_safe_redirect(haptic_redirects_tab_url());
    exit;
});

add_action('admin_post_haptic_redirect_delete', function () {
    haptic_redirects_check_cap();
    check_admin_referer('haptic_redirect_delete');
    haptic_redirects_delete(absint($_GET['id'] ?? 0));
    haptic_redirects_notice('ok', 'Redirection supprimée.');
    wp_safe_redirect(haptic_redirects_tab_url());
    exit;
});

add_action('admin_post_haptic_404_delete', function () {
    haptic_redirects_check_cap();
    check_admin_referer('haptic_404_delete');
    global $wpdb;
    if (!empty($_GET['all'])) {
        $wpdb->query($wpdb->prepare('TRUNCATE TABLE %i', haptic_404_table()));
        haptic_redirects_notice('ok', 'Journal des pages introuvables vidé.');
    } else {
        $wpdb->delete(haptic_404_table(), ['id' => absint($_GET['id'] ?? 0)]);
        haptic_redirects_notice('ok', 'Adresse retirée du journal.');
    }
    wp_safe_redirect(haptic_redirects_tab_url());
    exit;
});

add_action('admin_post_haptic_redirects_settings', function () {
    haptic_redirects_check_cap();
    check_admin_referer('haptic_redirects_settings');
    update_option('haptic_redir_auto', empty($_POST['haptic_redir_auto']) ? 0 : 1);
    update_option('haptic_redir_log404', empty($_POST['haptic_redir_log404']) ? 0 : 1);
    haptic_redirects_notice('ok', 'Réglages enregistrés.');
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
        haptic_redirects_notice('warn', 'Aucun fichier CSV reçu (2 Mo maximum).');
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
        $result = haptic_redirects_save($row[0], $row[1] ?? '', (int) ($row[2] ?? 301) ?: 301, $row[3] ?? 'Import CSV');
        if (is_wp_error($result)) {
            $errors[] = sprintf('ligne %d : %s', $line, $result->get_error_message());
        } else {
            $added++;
        }
    }
    $csv = null;

    $message = sprintf('%d redirection(s) importée(s).', $added);
    if ($errors) {
        $message .= ' ' . count($errors) . ' ligne(s) ignorée(s) — ' . implode(' ; ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '…' : '');
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
        <h2 class="optimizr-page-head__title">Redirections &amp; liens</h2>
        <p class="optimizr-page-head__intro">Redirigez les anciennes adresses, surveillez les pages introuvables et repérez les liens cassés.</p>
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
                        <h2 class="optimizr-card__title"><?php echo $edit ? 'Modifier la redirection' : 'Ajouter une redirection'; ?></h2>
                        <p class="optimizr-card__desc">Adresse relative (<code>/ancienne-page</code>) ou complète. Terminez la source par <code>*</code> pour rediriger tout un dossier : <code>/blog/*</code> → <code>/actualites/*</code>.</p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <div class="optimizr-redirect-form">
                        <label>
                            <span class="optimizr-field__label">Ancienne adresse</span>
                            <input type="text" name="source" value="<?php echo esc_attr($prefill); ?>" placeholder="/ancienne-page" required spellcheck="false">
                        </label>
                        <span class="optimizr-redirect-form__arrow" aria-hidden="true">→</span>
                        <label>
                            <span class="optimizr-field__label">Destination</span>
                            <input type="text" name="target" value="<?php echo esc_attr($edit ? $edit->target : ''); ?>" placeholder="/nouvelle-page ou https://…" spellcheck="false" data-redirect-target>
                        </label>
                        <label>
                            <span class="optimizr-field__label">Type</span>
                            <select name="code" data-redirect-code>
                                <?php foreach (HAPTIC_REDIRECT_CODES as $code => $label) : ?>
                                    <option value="<?php echo (int) $code; ?>" <?php selected($edit ? (int) $edit->code : 301, $code); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label>
                        <span class="optimizr-field__label">Note <span class="optimizr-muted">(facultative)</span></span>
                        <input type="text" name="note" value="<?php echo esc_attr($edit ? $edit->note : ''); ?>" maxlength="255">
                    </label>
                    <div class="optimizr-inline">
                        <button type="submit" class="optimizr-btn optimizr-btn--primary"><?php echo $edit ? 'Enregistrer' : 'Ajouter la redirection'; ?></button>
                        <?php if ($edit || $prefill) : ?>
                            <a class="optimizr-btn optimizr-btn--ghost" href="<?php echo esc_url(haptic_redirects_tab_url()); ?>">Annuler</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title">Redirections <span class="optimizr-badge"><?php echo (int) $total; ?></span></h2>
                    </div>
                    <form method="get" class="optimizr-search">
                        <input type="hidden" name="page" value="haptic-options">
                        <input type="hidden" name="tab" value="redirects">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Rechercher" aria-label="Rechercher une redirection">
                    </form>
                </div>
                <?php if (!$rules) : ?>
                    <p class="optimizr-card__empty"><?php echo $search !== '' ? 'Aucune redirection ne correspond.' : 'Aucune redirection pour l\'instant.'; ?></p>
                <?php else : ?>
                    <div class="optimizr-table-wrap optimizr-table-wrap--flush">
                        <table class="optimizr-table optimizr-table--left">
                            <thead><tr><th scope="col">Ancienne adresse</th><th scope="col">Destination</th><th scope="col">Type</th><th scope="col">Visites</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead>
                            <tbody>
                                <?php foreach ($rules as $rule) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html($rule->source); ?></code><?php if ($rule->note) : ?><br><span class="optimizr-muted"><?php echo esc_html($rule->note); ?></span><?php endif; ?></td>
                                        <td><?php echo (int) $rule->code === 410 ? '<span class="optimizr-muted">—</span>' : '<code>' . esc_html($rule->target) . '</code>'; ?></td>
                                        <td><?php echo (int) $rule->code; ?></td>
                                        <td><?php echo (int) $rule->hits; ?><?php if ($rule->last_hit) : ?><br><span class="optimizr-muted"><?php echo esc_html(mysql2date('j M Y', $rule->last_hit)); ?></span><?php endif; ?></td>
                                        <td class="optimizr-table__actions">
                                            <a class="optimizr-link" href="<?php echo esc_url(haptic_redirects_tab_url(['edit' => $rule->id])); ?>#haptic-redirect-form">Modifier</a>
                                            <a class="optimizr-link optimizr-link--danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=haptic_redirect_delete&id=' . $rule->id), 'haptic_redirect_delete')); ?>" onclick="return confirm('Supprimer cette redirection ?');">Supprimer</a>
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
                        <h2 class="optimizr-card__title">Pages introuvables <span class="optimizr-badge"><?php echo (int) $not_found_total; ?></span></h2>
                        <p class="optimizr-card__desc">Adresses demandées par les visiteurs ou les robots qui aboutissent à une erreur 404, les plus fréquentes en premier.</p>
                    </div>
                    <?php if ($not_found_total) : ?>
                        <a class="optimizr-btn optimizr-btn--ghost" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=haptic_404_delete&all=1'), 'haptic_404_delete')); ?>" onclick="return confirm('Vider le journal des pages introuvables ?');">Vider</a>
                    <?php endif; ?>
                </div>
                <?php if (!get_option('haptic_redir_log404', 1)) : ?>
                    <p class="optimizr-card__empty">Le journal est désactivé dans les réglages ci-dessous.</p>
                <?php elseif (!$not_found) : ?>
                    <p class="optimizr-card__empty">Aucune page introuvable enregistrée.</p>
                <?php else : ?>
                    <div class="optimizr-table-wrap optimizr-table-wrap--flush">
                        <table class="optimizr-table optimizr-table--left">
                            <thead><tr><th scope="col">Adresse</th><th scope="col">Visites</th><th scope="col">Dernière</th><th scope="col">Provenance</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead>
                            <tbody>
                                <?php foreach ($not_found as $row) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html($row->path); ?></code></td>
                                        <td><?php echo (int) $row->hits; ?></td>
                                        <td><?php echo esc_html(mysql2date('j M, H:i', $row->last_seen)); ?></td>
                                        <td class="optimizr-muted"><?php echo $row->referrer !== '' ? esc_html($row->referrer) : '—'; ?></td>
                                        <td class="optimizr-table__actions">
                                            <a class="optimizr-link" href="<?php echo esc_url(haptic_redirects_tab_url(['source' => rawurlencode($row->path)])); ?>#haptic-redirect-form">Rediriger</a>
                                            <a class="optimizr-link optimizr-link--danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=haptic_404_delete&id=' . $row->id), 'haptic_404_delete')); ?>">Ignorer</a>
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
                        <h2 class="optimizr-card__title">Liens cassés</h2>
                        <p class="optimizr-card__desc">Vérifie chaque lien et chaque image des contenus publiés. Gardez la page ouverte pendant l'analyse.</p>
                    </div>
                    <button type="button" class="optimizr-btn optimizr-btn--secondary" data-links-start><?php echo wp_kses(haptic_icon('search', 16), haptic_kses_ui()); ?>Analyser les liens</button>
                </div>
                <div class="optimizr-card__body">
                    <div class="optimizr-bulk__progress" data-links-progress hidden>
                        <div class="optimizr-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Analyse des liens"><span></span></div>
                        <span class="optimizr-muted" data-links-status role="status"></span>
                    </div>
                    <p class="optimizr-muted" data-links-summary>
                        <?php if ($report && empty($report['running'])) : ?>
                            <?php echo esc_html(sprintf('Dernière analyse le %s : %d lien(s) vérifié(s), %d problème(s).', mysql2date('j F Y à H:i', $report['date']), (int) $report['checked'], count($report['issues']))); ?>
                        <?php elseif ($report && !empty($report['running'])) : ?>
                            La dernière analyse a été interrompue : relancez-la.
                        <?php else : ?>
                            Aucune analyse pour l'instant.
                        <?php endif; ?>
                    </p>
                    <div class="optimizr-table-wrap optimizr-table-wrap--flush" data-links-results <?php echo empty($report['issues']) ? 'hidden' : ''; ?>>
                        <table class="optimizr-table optimizr-table--left">
                            <thead><tr><th scope="col">Lien</th><th scope="col">Problème</th><th scope="col">Présent dans</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead>
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
                <div class="optimizr-card__header"><h2 class="optimizr-card__title">Réglages</h2></div>
                <div class="optimizr-card__body">
                    <div class="optimizr-check-row">
                        <?php echo wp_kses(haptic_switch_input('haptic_redir_auto', get_option('haptic_redir_auto', 1), 'Redirection automatique'), haptic_kses_ui()); ?>
                        <span><strong>Redirection automatique</strong><br><span class="optimizr-muted">Quand l'adresse d'un contenu publié change, l'ancienne redirige vers la nouvelle (301).</span></span>
                    </div>
                    <div class="optimizr-check-row">
                        <?php echo wp_kses(haptic_switch_input('haptic_redir_log404', get_option('haptic_redir_log404', 1), 'Journal des pages introuvables'), haptic_kses_ui()); ?>
                        <span><strong>Journal des pages introuvables</strong><br><span class="optimizr-muted">Sans adresse IP : seules l'adresse demandée et la page de provenance sont conservées.</span></span>
                    </div>
                    <div><button type="submit" class="optimizr-btn optimizr-btn--secondary">Enregistrer</button></div>
                </div>
            </form>

            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title">Import / export CSV</h2>
                        <p class="optimizr-card__desc">Colonnes : ancienne adresse ; destination ; type (301 par défaut) ; note. Pratique pour une refonte de site.</p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="optimizr-inline">
                        <input type="hidden" name="action" value="haptic_redirects_import">
                        <?php wp_nonce_field('haptic_redirects_import'); ?>
                        <label class="optimizr-file"><span class="screen-reader-text">Fichier CSV</span><input type="file" name="haptic_redirects_file" accept=".csv,text/csv" required></label>
                        <button type="submit" class="optimizr-btn optimizr-btn--secondary">Importer</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="haptic_redirects_export">
                        <?php wp_nonce_field('haptic_redirects_export'); ?>
                        <button type="submit" class="optimizr-btn optimizr-btn--ghost" <?php disabled(!$total); ?>>Exporter les redirections</button>
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
            <?php if (count($issue['sources']) > 3) : ?><span class="optimizr-muted">+ <?php echo count($issue['sources']) - 3; ?> autre(s)</span><?php endif; ?>
        </td>
        <td class="optimizr-table__actions">
            <?php if (!empty($issue['internal']) && in_array($issue['state'], ['broken'], true)) : ?>
                <a class="optimizr-link" href="<?php echo esc_url(haptic_redirects_tab_url(['source' => rawurlencode(haptic_redirects_normalize($issue['url']))])); ?>#haptic-redirect-form">Rediriger</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}
