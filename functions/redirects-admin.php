<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Onglet Redirections : formulaires (admin-post) et listes.
 * Optimizr Pro y ajoute ses cartes (liens cassés, import CSV) et ses réglages par les actions haptic_redirects_*.
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
    update_option('haptic_redir_log404', empty($_POST['haptic_redir_log404']) ? 0 : 1);
    // Réglages ajoutés par une extension (redirection automatique d'Optimizr Pro)
    do_action('haptic_redirects_settings_save');
    haptic_redirects_notice('ok', __('Settings saved.', 'optimizr'));
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

    // Extensions : resynchronisation à chaque affichage (cache des jokers d'Optimizr Pro)
    do_action('haptic_redirects_before_render');

    // Modification d'une règle, ou création préremplie depuis le journal des 404 / les liens cassés
    $edit = !empty($_GET['edit']) ? $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, (int) $_GET['edit'])) : null;
    $prefill = $edit ? $edit->source : (isset($_GET['source']) ? haptic_redirects_normalize(wp_strip_all_tags(rawurldecode(wp_unslash($_GET['source'])))) : '');

    // Liste paginée et recherche
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $paged = max(1, absint($_GET['paged'] ?? 1));
    $per_page = 50;
    $offset = ($paged - 1) * $per_page;
    $total_all = haptic_redirects_count();
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
    $limit = haptic_redirects_limit();
    $limit_reached = !$edit && $limit > 0 && $total_all >= $limit;
    ?>
    <div class="optimizr-page-head">
        <h2 class="optimizr-page-head__title"><?php echo esc_html(haptic_admin_tabs()['redirects']['label']); ?></h2>
        <p class="optimizr-page-head__intro"><?php echo esc_html(apply_filters('haptic_redirects_intro', __('Redirect old addresses and monitor not-found pages.', 'optimizr'))); ?></p>
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
                        <p class="optimizr-card__desc"><?php echo wp_kses_post(apply_filters('haptic_redirects_form_help', __('Relative (<code>/old-page</code>) or full address.', 'optimizr'))); ?></p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <?php if ($limit_reached) : ?>
                        <div class="optimizr-notice optimizr-notice--warn"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><span><?php
                            /* translators: %s: maximum number of redirects */
                            echo esc_html(sprintf(_n('The free version is limited to %s redirect. Optimizr Pro removes this limit.', 'The free version is limited to %s redirects. Optimizr Pro removes this limit.', $limit, 'optimizr'), number_format_i18n($limit)));
                        ?></span></div>
                    <?php endif; ?>
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
                        <button type="submit" class="optimizr-btn optimizr-btn--primary" <?php disabled($limit_reached); ?>><?php echo $edit ? esc_html__('Save', 'optimizr') : esc_html__('Add redirect', 'optimizr'); ?></button>
                        <?php if ($edit || $prefill) : ?>
                            <a class="optimizr-btn optimizr-btn--ghost" href="<?php echo esc_url(haptic_redirects_tab_url()); ?>"><?php esc_html_e('Cancel', 'optimizr'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title"><?php esc_html_e('Redirects', 'optimizr'); ?> <span class="optimizr-badge"><?php echo $limit > 0 ? esc_html(sprintf('%d / %d', $total_all, $limit)) : (int) $total; ?></span></h2>
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

            <?php do_action('haptic_redirects_tab_main'); ?>
        </div>

        <aside class="optimizr-layout__aside">
            <form class="optimizr-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="haptic_redirects_settings">
                <?php wp_nonce_field('haptic_redirects_settings'); ?>
                <div class="optimizr-card__header"><h2 class="optimizr-card__title"><?php esc_html_e('Settings', 'optimizr'); ?></h2></div>
                <div class="optimizr-card__body">
                    <?php do_action('haptic_redirects_settings_fields'); ?>
                    <div class="optimizr-check-row">
                        <?php echo wp_kses(haptic_switch_input('haptic_redir_log404', get_option('haptic_redir_log404', 1), __('Not-found pages log', 'optimizr')), haptic_kses_ui()); ?>
                        <span><strong><?php esc_html_e('Not-found pages log', 'optimizr'); ?></strong><br><span class="optimizr-muted"><?php esc_html_e('No IP address: only the requested address and the referring page are kept.', 'optimizr'); ?></span></span>
                    </div>
                    <div><button type="submit" class="optimizr-btn optimizr-btn--secondary"><?php esc_html_e('Save', 'optimizr'); ?></button></div>
                </div>
            </form>

            <?php do_action('haptic_redirects_tab_aside', $total); ?>
        </aside>
    </div>
    <?php
}
