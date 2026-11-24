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

    // Médiathèque de WordPress pour le bouton « Choisir… » de l'image de partage
    wp_enqueue_media();
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
    $site_name = wp_strip_all_tags(get_bloginfo('name'));
    $initial = mb_strtoupper(mb_substr($site_name, 0, 1));
    $default_image_label = $seo['default_image'] ?: __('Content featured image', 'optimizr');

    // Libellés des pastilles du résumé, lus par gutenberg/seo-preview.js
    $chips = [
        /* translators: state of the SEO title: "ideal", "too short" or "too long" */
        'title' => __('Title: %s', 'optimizr'),
        /* translators: state of the SEO description: "ideal", "too short" or "too long" */
        'desc' => __('Description: %s', 'optimizr'),
        'ok' => __('ideal', 'optimizr'),
        'warn' => __('too short', 'optimizr'),
        'bad' => __('too long', 'optimizr'),
        'imageDefault' => __('Image: featured', 'optimizr'),
        'imageCustom' => __('Image: chosen', 'optimizr'),
        'imageNone' => __('Image: none', 'optimizr'),
        'visible' => __('Visible in Google', 'optimizr'),
        'hidden' => __('Hidden from Google (noindex)', 'optimizr'),
        'mediaTitle' => __('Share image', 'optimizr'),
        'mediaButton' => __('Use this image', 'optimizr'),
    ];
    ?>
    <div class="optimizr-seo" data-labels="<?php echo esc_attr(wp_json_encode($chips)); ?>">
        <div class="optimizr-seo__summary<?php echo $noindex ? ' is-hidden' : ''; ?>">
            <div class="optimizr-seo__chips" aria-live="polite">
                <span class="optimizr-seo__chip" data-chip="title"></span>
                <span class="optimizr-seo__chip" data-chip="desc"></span>
                <span class="optimizr-seo__chip" data-chip="image"></span>
            </div>
            <?php // L'interrupteur dit « visible » ; le marqueur indique au formulaire d'enregistrement de le lire ainsi ?>
            <input type="hidden" name="haptic_seo_visibility" value="1">
            <label class="optimizr-seo__switch">
                <input type="checkbox" id="haptic_seo_visible" name="haptic_seo_visible" value="1" <?php checked(!$noindex); ?>>
                <span class="optimizr-seo__track" aria-hidden="true"></span>
                <span data-visibility-label><?php echo esc_html($noindex ? $chips['hidden'] : $chips['visible']); ?></span>
            </label>
        </div>

        <div class="optimizr-seo__layout">
            <div class="optimizr-seo__fields">
                <div class="optimizr-seo__field">
                    <div class="optimizr-seo__label-row">
                        <label class="optimizr-seo__label" for="haptic_og_title"><?php esc_html_e('SEO title', 'optimizr'); ?> <span class="haptic-seo-hint"><?php esc_html_e('(browser tab, Google, shares)', 'optimizr'); ?></span></label>
                        <button type="button" class="optimizr-seo__reset" data-reset="haptic_og_title" hidden><?php esc_html_e('Back to the pattern', 'optimizr'); ?></button>
                    </div>
                    <input type="text" id="haptic_og_title" name="haptic_og_title" value="<?php echo esc_attr($custom_title); ?>" placeholder="<?php echo esc_attr($seo['pattern_title']); ?>" class="widefat" aria-describedby="haptic_title_meter">
                    <div class="optimizr-seo__meter" id="haptic_title_meter" data-min="50" data-max="60">
                        <span class="optimizr-seo__bar" aria-hidden="true"><span class="optimizr-seo__ideal"></span><span class="optimizr-seo__fill"></span></span>
                        <span class="haptic-char-count" id="haptic_title_count">0/60</span>
                        <small class="haptic-seo-hint"><?php esc_html_e('Ideally between 50 and 60 characters.', 'optimizr'); ?></small>
                    </div>
                </div>

                <div class="optimizr-seo__field">
                    <div class="optimizr-seo__label-row">
                        <label class="optimizr-seo__label" for="haptic_og_desc"><?php esc_html_e('SEO description', 'optimizr'); ?> <span class="haptic-seo-hint"><?php esc_html_e('(Google, shares)', 'optimizr'); ?></span></label>
                        <button type="button" class="optimizr-seo__reset" data-reset="haptic_og_desc" hidden><?php esc_html_e('Back to the pattern', 'optimizr'); ?></button>
                    </div>
                    <textarea id="haptic_og_desc" name="haptic_og_desc" class="widefat" rows="3" placeholder="<?php echo esc_attr($seo['pattern_description']); ?>" aria-describedby="haptic_desc_meter"><?php echo esc_textarea($custom_desc); ?></textarea>
                    <div class="optimizr-seo__meter-row">
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
                </div>

                <div class="optimizr-seo__field">
                    <div class="optimizr-seo__label-row">
                        <label class="optimizr-seo__label" for="haptic_og_image"><?php esc_html_e('Share image', 'optimizr'); ?></label>
                        <button type="button" class="optimizr-seo__reset" data-reset="haptic_og_image" hidden><?php esc_html_e('Back to the featured image', 'optimizr'); ?></button>
                    </div>
                    <div class="optimizr-seo__image">
                        <span class="optimizr-seo__image-thumb" data-image-thumb<?php echo $seo['image'] ? '' : ' data-empty'; ?>><img<?php echo $seo['image'] ? ' src="' . esc_url($seo['image']) . '"' : ''; ?> alt=""></span>
                        <input type="url" id="haptic_og_image" name="haptic_og_image" value="<?php echo esc_attr($custom_image); ?>" placeholder="<?php echo esc_attr($default_image_label); ?>" data-default="<?php echo esc_attr($seo['default_image']); ?>">
                        <button type="button" class="optimizr-seo__button" data-media-pick><?php esc_html_e('Choose…', 'optimizr'); ?></button>
                    </div>
                    <small class="haptic-seo-hint"><?php esc_html_e('Default: the featured image. Ideal size: 1200 × 630 px.', 'optimizr'); ?></small>
                </div>
            </div>

            <div class="optimizr-seo__aside">
                <div class="optimizr-seo__preview-head">
                    <h3 class="optimizr-seo__preview-title"><?php esc_html_e('Preview', 'optimizr'); ?></h3>
                    <div class="optimizr-seo__segmented" role="group" aria-label="<?php esc_attr_e('Preview', 'optimizr'); ?>">
                        <button type="button" data-preview="google" aria-pressed="true"><?php esc_html_e('Google', 'optimizr'); ?></button>
                        <button type="button" data-preview="share" aria-pressed="false"><?php esc_html_e('Sharing', 'optimizr'); ?></button>
                    </div>
                </div>

                <div id="haptic_seo_preview" class="haptic-seo-preview-box<?php echo $noindex ? ' is-noindex' : ''; ?>" data-preview-panel="google" data-noindex-label="<?php esc_attr_e('Hidden: will not appear in Google', 'optimizr'); ?>">
                    <div class="seo-text">
                        <div class="seo-site">
                            <span class="seo-favicon"><?php if ($site_icon) : ?><img src="<?php echo esc_url($site_icon); ?>" alt=""><?php else : echo esc_html($initial); endif; ?></span>
                            <span class="seo-site-text">
                                <span class="seo-site-name"><?php echo esc_html($site_name); ?></span>
                                <span class="seo-url" title="<?php echo esc_url($permalink); ?>"><?php echo esc_html($breadcrumb); ?></span>
                            </span>
                        </div>
                        <p class="seo-title" data-out="title"><?php echo esc_html($seo['title']); ?></p>
                        <p class="seo-desc" data-out="desc"><?php echo esc_html($seo['description']); ?></p>
                    </div>
                    <div class="seo-thumb" data-image-thumb<?php echo $seo['image'] ? '' : ' hidden'; ?>>
                        <img<?php echo $seo['image'] ? ' src="' . esc_url($seo['image']) . '"' : ''; ?> alt="">
                    </div>
                </div>

                <div class="optimizr-seo__share" data-preview-panel="share" hidden>
                    <div class="optimizr-seo__share-image" data-image-thumb<?php echo $seo['image'] ? '' : ' data-empty'; ?>>
                        <img<?php echo $seo['image'] ? ' src="' . esc_url($seo['image']) . '"' : ''; ?> alt="">
                        <span><?php esc_html_e('No share image', 'optimizr'); ?></span>
                    </div>
                    <div class="optimizr-seo__share-text">
                        <small><?php echo esc_html($host); ?></small>
                        <b data-out="title"><?php echo esc_html($seo['title']); ?></b>
                        <span data-out="desc"><?php echo esc_html($seo['description']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <p class="optimizr-seo__foot"><?php
            /* translators: %s: URL of the Optimizr SEO settings tab */
            echo wp_kses(sprintf(__('Leave a field empty to use the pattern set in <a href="%s">Optimizr › SEO</a> (shown in grey).', 'optimizr'), esc_url(admin_url('admin.php?page=haptic-options&tab=seo'))), ['a' => ['href' => []]]);
        ?></p>
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

    // Depuis la 7.21.0, un interrupteur « Visible dans Google » (marqueur haptic_seo_visibility) ;
    // l'ancienne case « Ne pas indexer » reste comprise pour un formulaire mis en cache
    $noindex = isset($_POST['haptic_seo_visibility']) ? !isset($_POST['haptic_seo_visible']) : isset($_POST['haptic_noindex']);
    if ($noindex) {
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
