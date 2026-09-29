<?php

if (!defined('ABSPATH')) {
    exit;
}

// Ajouter une meta box SEO dans l'éditeur de page/post
function hpt_add_seo_metabox()
{
    $post_types = get_option('hpt_sitemap_post_types', ['post', 'page']);
    foreach ((array) $post_types as $post_type) {
        add_meta_box(
            'hpt_seo_meta',
            'SEO & partage',
            'hpt_seo_meta_callback',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'hpt_add_seo_metabox');

function hpt_enqueue_seo_scripts($hook)
{
    if ('post.php' !== $hook && 'post-new.php' !== $hook) {
        return;
    }

    $dir = plugin_dir_path(dirname(__FILE__, 2)) . 'gutenberg/';
    $url = plugin_dir_url(dirname(__FILE__, 2)) . 'gutenberg/';

    wp_enqueue_script('hpt-seo-preview', $url . 'seo-preview.js', [], filemtime($dir . 'seo-preview.js'), true);
    wp_enqueue_style('hpt-seo-preview-style', $url . 'seo-preview.css', [], filemtime($dir . 'seo-preview.css'));
}
add_action('admin_enqueue_scripts', 'hpt_enqueue_seo_scripts');

/**
 * Metabox : un champ vide applique le motif de l'onglet SEO (affiché en exemple grisé).
 */
function hpt_seo_meta_callback($post)
{
    $seo = hpt_seo_resolve($post->ID);
    $noindex = get_post_meta($post->ID, '_hpt_noindex', true);
    $custom_title = get_post_meta($post->ID, '_hpt_og_title', true);
    $custom_desc = get_post_meta($post->ID, '_hpt_og_desc', true);
    $custom_image = get_post_meta($post->ID, '_hpt_og_image', true);

    wp_nonce_field('hpt_seo_meta_nonce', 'hpt_seo_meta_nonce_field');
    ?>
    <p>
        <label>
            <input type="checkbox" name="hpt_noindex" value="1" <?php checked($noindex, 1); ?>>
            Ne pas indexer ce contenu dans les moteurs de recherche
        </label>
    </p>

    <p class="hpt-seo-hint">Laissez un champ vide pour utiliser le motif défini dans <a href="<?php echo esc_url(admin_url('admin.php?page=hpt-options&tab=seo')); ?>">Optimizr › SEO</a> (affiché en gris).</p>

    <p>
        <label for="hpt_og_title"><strong>Titre SEO</strong> <span class="hpt-seo-hint">(onglet du navigateur, Google, partages)</span></label><br>
        <input type="text" id="hpt_og_title" name="hpt_og_title" value="<?php echo esc_attr($custom_title); ?>" placeholder="<?php echo esc_attr($seo['pattern_title']); ?>" class="widefat">
        <span class="hpt-char-count" id="hpt_title_count">0/60</span>
        <small class="hpt-seo-hint">Idéalement entre 50 et 60 caractères.</small>
    </p>

    <p>
        <label for="hpt_og_desc"><strong>Description SEO</strong> <span class="hpt-seo-hint">(Google, partages)</span></label><br>
        <textarea id="hpt_og_desc" name="hpt_og_desc" class="widefat" rows="3" placeholder="<?php echo esc_attr($seo['pattern_description']); ?>"><?php echo esc_textarea($custom_desc); ?></textarea>
        <span class="hpt-char-count" id="hpt_desc_count">0/160</span>
        <small class="hpt-seo-hint">Idéalement entre 120 et 160 caractères.</small>
    </p>

    <?php if (hpt_is_module_active('ai')) : ?>
        <p>
            <button type="button" class="button" id="generate-seo-desc" data-post-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('hpt_ai_nonce')); ?>">
                Générer la description avec l'IA
            </button>
        </p>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const button = document.getElementById('generate-seo-desc');
                const textarea = document.getElementById('hpt_og_desc');
                if (!button || !textarea) return;

                button.addEventListener('click', function() {
                    button.disabled = true;
                    button.innerText = 'Génération...';

                    fetch(ajaxurl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'hpt_generate_ai_seo_description',
                                post_id: this.dataset.postId,
                                nonce: this.dataset.nonce
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                textarea.value = data.data.description;
                                textarea.dispatchEvent(new Event('input'));
                            } else {
                                alert('Erreur : ' + data.data);
                            }
                            button.disabled = false;
                            button.innerText = 'Générer la description avec l\'IA';
                        });
                });
            });
        </script>
    <?php endif; ?>

    <p>
        <label for="hpt_og_image"><strong>Image de partage</strong> <span class="hpt-seo-hint">(URL)</span></label><br>
        <input type="url" id="hpt_og_image" name="hpt_og_image" value="<?php echo esc_attr($custom_image); ?>" placeholder="<?php echo esc_attr($seo['default_image'] ?: 'Image à la une du contenu'); ?>" class="widefat">
        <small class="hpt-seo-hint">Par défaut : l'image à la une. Format idéal : 1200 × 630 px.</small>
    </p>

    <h3>Aperçu dans Google</h3>
    <div id="hpt_seo_preview" class="hpt-seo-preview-box">
        <p class="seo-title"><?php echo esc_html($seo['title']); ?></p>
        <p class="seo-url"><?php echo esc_url(get_permalink($post->ID)); ?></p>
        <p class="seo-desc"><?php echo esc_html($seo['description']); ?></p>
        <?php if ($seo['image']) : ?>
            <img src="<?php echo esc_url($seo['image']); ?>" alt="">
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Enregistre uniquement les valeurs saisies volontairement : un champ vide, ou identique
 * au résultat du motif, supprime la personnalisation pour que le motif continue de s'appliquer.
 */
function hpt_save_seo_metadata($post_id)
{
    if (!isset($_POST['hpt_seo_meta_nonce_field']) || !wp_verify_nonce($_POST['hpt_seo_meta_nonce_field'], 'hpt_seo_meta_nonce')) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['hpt_noindex'])) {
        update_post_meta($post_id, '_hpt_noindex', 1);
    } else {
        delete_post_meta($post_id, '_hpt_noindex');
    }

    $seo = hpt_seo_resolve($post_id);

    $fields = [
        '_hpt_og_title' => [sanitize_text_field(wp_unslash($_POST['hpt_og_title'] ?? '')), $seo['pattern_title']],
        '_hpt_og_desc'  => [sanitize_textarea_field(wp_unslash($_POST['hpt_og_desc'] ?? '')), $seo['pattern_description']],
        '_hpt_og_image' => [esc_url_raw(wp_unslash($_POST['hpt_og_image'] ?? '')), $seo['default_image']],
    ];

    foreach ($fields as $meta_key => $values) {
        list($value, $default) = $values;
        $value = trim($value);

        if ($value === '' || hpt_seo_clean_text($value) === hpt_seo_clean_text($default)) {
            delete_post_meta($post_id, $meta_key);
        } else {
            update_post_meta($post_id, $meta_key, $value);
        }
    }
}
add_action('save_post', 'hpt_save_seo_metadata');

function hpt_register_seo_meta()
{
    foreach ((array) get_option('hpt_sitemap_post_types', ['post', 'page']) as $post_type) {
        foreach (['_hpt_og_title', '_hpt_og_desc', '_hpt_og_image'] as $meta_key) {
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
add_action('init', 'hpt_register_seo_meta');

/**
 * Migration 2.0.0-beta.3 : l'ancienne metabox pré-remplissait ses champs avec le titre, l'extrait
 * et l'image à la une, puis les enregistrait. Ces valeurs jamais choisies bloquaient les motifs
 * (et le titre provisoire « Brouillon auto » s'affichait dans les partages). Elles sont supprimées ;
 * les personnalisations réelles sont conservées.
 *
 * @return int Nombre de valeurs supprimées
 */
function hpt_seo_cleanup_saved_meta()
{
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
         WHERE meta_key IN ('_hpt_og_title', '_hpt_og_desc', '_hpt_og_image')"
    );

    $removed = 0;

    foreach ($rows as $row) {
        $post = get_post($row->post_id);
        if (!$post) {
            continue;
        }

        if ($row->meta_key === '_hpt_og_image') {
            $defaults = [get_the_post_thumbnail_url($post, 'large'), get_the_post_thumbnail_url($post, 'full')];
            $is_default = $row->meta_value === '' || in_array($row->meta_value, array_filter($defaults), true);
        } else {
            $defaults = $row->meta_key === '_hpt_og_title'
                ? [$post->post_title, get_the_title($post), 'Brouillon auto', 'Auto Draft']
                : [$post->post_excerpt, get_the_excerpt($post)];
            $value = hpt_seo_clean_text($row->meta_value);
            $is_default = $value === '' || in_array($value, array_map('hpt_seo_clean_text', $defaults), true);
        }

        if ($is_default) {
            delete_post_meta($row->post_id, $row->meta_key);
            $removed++;
        }
    }

    return $removed;
}
