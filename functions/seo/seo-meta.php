<?php

if (!defined('ABSPATH')) {
    exit;
}

// Ajouter une meta box SEO dans l'éditeur de page/post
function haptic_add_seo_metabox()
{
    foreach (haptic_seo_post_types() as $post_type) {
        add_meta_box(
            'haptic_seo_meta',
            __('SEO & sharing', 'optimizr'),
            'haptic_seo_meta_callback',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'haptic_add_seo_metabox');

function haptic_enqueue_seo_scripts($hook)
{
    if ('post.php' !== $hook && 'post-new.php' !== $hook) {
        return;
    }

    $dir = plugin_dir_path(dirname(__FILE__, 2)) . 'gutenberg/';
    $url = plugin_dir_url(dirname(__FILE__, 2)) . 'gutenberg/';

    wp_enqueue_script('haptic-seo-preview', $url . 'seo-preview.js', ['wp-i18n'], filemtime($dir . 'seo-preview.js'), true);
    wp_set_script_translations('haptic-seo-preview', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
    // Les couleurs viennent des jetons du système de design : la feuille est chargée avant celle de la boîte
    wp_enqueue_style('haptic-seo-preview-style', $url . 'seo-preview.css', [haptic_tokens_style()], filemtime($dir . 'seo-preview.css'));
}
add_action('admin_enqueue_scripts', 'haptic_enqueue_seo_scripts');

/**
 * Metabox : un champ vide applique le motif de l'onglet SEO (affiché en exemple grisé).
 */
function haptic_seo_meta_callback($post)
{
    $seo = haptic_seo_resolve($post->ID);
    $noindex = get_post_meta($post->ID, '_haptic_noindex', true);
    $custom_title = get_post_meta($post->ID, '_haptic_og_title', true);
    $custom_desc = get_post_meta($post->ID, '_haptic_og_desc', true);
    $custom_image = get_post_meta($post->ID, '_haptic_og_image', true);

    wp_nonce_field('haptic_seo_meta_nonce', 'haptic_seo_meta_nonce_field');

    $permalink = get_permalink($post->ID);
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $path = trim((string) wp_parse_url($permalink, PHP_URL_PATH), '/');
    // Fil d'Ariane façon Google : « exemple.fr › page › sous-page »
    $breadcrumb = $path === '' ? $host : $host . ' › ' . implode(' › ', array_map('urldecode', explode('/', $path)));
    $site_icon = get_site_icon_url(64);
    ?>
    <div class="optimizr-seo">
        <label class="optimizr-seo__check">
            <input type="checkbox" name="haptic_noindex" value="1" <?php checked($noindex, 1); ?>>
            <span><?php esc_html_e('Do not index this content in search engines', 'optimizr'); ?></span>
        </label>

        <p class="optimizr-seo__intro"><?php
            /* translators: %s: URL of the Optimizr SEO settings tab */
            echo wp_kses(sprintf(__('Leave a field empty to use the pattern set in <a href="%s">Optimizr › SEO</a> (shown in grey).', 'optimizr'), esc_url(admin_url('admin.php?page=haptic-options&tab=seo'))), ['a' => ['href' => []]]);
        ?></p>

        <div class="optimizr-seo__field">
            <label class="optimizr-seo__label" for="haptic_og_title"><?php esc_html_e('SEO title', 'optimizr'); ?> <span class="haptic-seo-hint"><?php esc_html_e('(browser tab, Google, shares)', 'optimizr'); ?></span></label>
            <input type="text" id="haptic_og_title" name="haptic_og_title" value="<?php echo esc_attr($custom_title); ?>" placeholder="<?php echo esc_attr($seo['pattern_title']); ?>" class="widefat" aria-describedby="haptic_title_meter">
            <div class="optimizr-seo__meter" id="haptic_title_meter" data-min="50" data-max="60">
                <span class="optimizr-seo__bar" aria-hidden="true"><span class="optimizr-seo__ideal"></span><span class="optimizr-seo__fill"></span></span>
                <span class="haptic-char-count" id="haptic_title_count">0/60</span>
                <small class="haptic-seo-hint"><?php esc_html_e('Ideally between 50 and 60 characters.', 'optimizr'); ?></small>
            </div>
        </div>

        <div class="optimizr-seo__field">
            <label class="optimizr-seo__label" for="haptic_og_desc"><?php esc_html_e('SEO description', 'optimizr'); ?> <span class="haptic-seo-hint"><?php esc_html_e('(Google, shares)', 'optimizr'); ?></span></label>
            <textarea id="haptic_og_desc" name="haptic_og_desc" class="widefat" rows="3" placeholder="<?php echo esc_attr($seo['pattern_description']); ?>" aria-describedby="haptic_desc_meter"><?php echo esc_textarea($custom_desc); ?></textarea>
            <div class="optimizr-seo__meter" id="haptic_desc_meter" data-min="120" data-max="160">
                <span class="optimizr-seo__bar" aria-hidden="true"><span class="optimizr-seo__ideal"></span><span class="optimizr-seo__fill"></span></span>
                <span class="haptic-char-count" id="haptic_desc_count">0/160</span>
                <small class="haptic-seo-hint"><?php esc_html_e('Ideally between 120 and 160 characters.', 'optimizr'); ?></small>
            </div>
            <?php
            // Optimizr Pro : génération de la description par l'Assistant IA
            do_action('haptic_seo_metabox_after_description', $post);
            ?>
        </div>

        <div class="optimizr-seo__field">
            <label class="optimizr-seo__label" for="haptic_og_image"><?php esc_html_e('Share image', 'optimizr'); ?> <span class="haptic-seo-hint"><?php esc_html_e('(URL)', 'optimizr'); ?></span></label>
            <input type="url" id="haptic_og_image" name="haptic_og_image" value="<?php echo esc_attr($custom_image); ?>" placeholder="<?php echo esc_attr($seo['default_image'] ?: __('Content featured image', 'optimizr')); ?>" class="widefat">
            <small class="haptic-seo-hint"><?php esc_html_e('Default: the featured image. Ideal size: 1200 × 630 px.', 'optimizr'); ?></small>
        </div>

        <div class="optimizr-seo__preview">
            <h3 class="optimizr-seo__preview-title"><?php esc_html_e('Google preview', 'optimizr'); ?></h3>
            <div id="haptic_seo_preview" class="haptic-seo-preview-box">
                <div class="seo-text">
                    <div class="seo-site">
                        <span class="seo-favicon"><?php if ($site_icon) : ?><img src="<?php echo esc_url($site_icon); ?>" alt=""><?php else : echo esc_html(mb_strtoupper(mb_substr(wp_strip_all_tags(get_bloginfo('name')), 0, 1))); endif; ?></span>
                        <span class="seo-site-text">
                            <span class="seo-site-name"><?php echo esc_html(get_bloginfo('name')); ?></span>
                            <span class="seo-url" title="<?php echo esc_url($permalink); ?>"><?php echo esc_html($breadcrumb); ?></span>
                        </span>
                    </div>
                    <p class="seo-title"><?php echo esc_html($seo['title']); ?></p>
                    <p class="seo-desc"><?php echo esc_html($seo['description']); ?></p>
                </div>
                <div class="seo-thumb"<?php echo $seo['image'] ? '' : ' hidden'; ?>>
                    <img<?php echo $seo['image'] ? ' src="' . esc_url($seo['image']) . '"' : ''; ?> alt="">
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Enregistre uniquement les valeurs saisies volontairement : un champ vide, ou identique
 * au résultat du motif, supprime la personnalisation pour que le motif continue de s'appliquer.
 */
function haptic_save_seo_metadata($post_id)
{
    if (!isset($_POST['haptic_seo_meta_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['haptic_seo_meta_nonce_field'])), 'haptic_seo_meta_nonce')) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['haptic_noindex'])) {
        update_post_meta($post_id, '_haptic_noindex', 1);
    } else {
        delete_post_meta($post_id, '_haptic_noindex');
    }

    $seo = haptic_seo_resolve($post_id);

    $fields = [
        '_haptic_og_title' => [sanitize_text_field(wp_unslash($_POST['haptic_og_title'] ?? '')), $seo['pattern_title']],
        '_haptic_og_desc'  => [sanitize_textarea_field(wp_unslash($_POST['haptic_og_desc'] ?? '')), $seo['pattern_description']],
        '_haptic_og_image' => [esc_url_raw(wp_unslash($_POST['haptic_og_image'] ?? '')), $seo['default_image']],
    ];

    foreach ($fields as $meta_key => $values) {
        list($value, $default) = $values;
        $value = trim($value);

        if ($value === '' || haptic_seo_clean_text($value) === haptic_seo_clean_text($default)) {
            delete_post_meta($post_id, $meta_key);
        } else {
            update_post_meta($post_id, $meta_key, $value);
        }
    }
}
add_action('save_post', 'haptic_save_seo_metadata');

function haptic_register_seo_meta()
{
    foreach (haptic_seo_post_types() as $post_type) {
        foreach (['_haptic_og_title', '_haptic_og_desc', '_haptic_og_image'] as $meta_key) {
            register_post_meta($post_type, $meta_key, [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'auth_callback' => function ($allowed, $meta_key, $post_id) {
                    return current_user_can('edit_post', $post_id);
                },
            ]);
        }
    }
}
// Tard : tous les types de contenu sont déclarés
add_action('init', 'haptic_register_seo_meta', 99);

/**
 * Migration 2.0.0-beta.3 : l'ancienne metabox pré-remplissait ses champs avec le titre, l'extrait
 * et l'image à la une, puis les enregistrait. Ces valeurs jamais choisies bloquaient les motifs
 * (et le titre provisoire « Brouillon auto » s'affichait dans les partages). Elles sont supprimées ;
 * les personnalisations réelles sont conservées.
 *
 * @return int Nombre de valeurs supprimées
 */
function haptic_seo_cleanup_saved_meta()
{
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- nettoyage ponctuel sur toutes les publications, résultat non réutilisé
    $rows = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
         WHERE meta_key IN ('_haptic_og_title', '_haptic_og_desc', '_haptic_og_image')"
    );

    $removed = 0;

    foreach ($rows as $row) {
        $post = get_post($row->post_id);
        if (!$post) {
            continue;
        }

        if ($row->meta_key === '_haptic_og_image') {
            $defaults = [get_the_post_thumbnail_url($post, 'large'), get_the_post_thumbnail_url($post, 'full')];
            $is_default = $row->meta_value === '' || in_array($row->meta_value, array_filter($defaults), true);
        } else {
            $defaults = $row->meta_key === '_haptic_og_title'
                ? [$post->post_title, get_the_title($post), 'Brouillon auto', 'Auto Draft']
                : [$post->post_excerpt, get_the_excerpt($post)];
            $value = haptic_seo_clean_text($row->meta_value);
            $is_default = $value === '' || in_array($value, array_map('haptic_seo_clean_text', $defaults), true);
        }

        if ($is_default) {
            delete_post_meta($row->post_id, $row->meta_key);
            $removed++;
        }
    }

    return $removed;
}
