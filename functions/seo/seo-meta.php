<?php

if (!defined('ABSPATH')) {
    exit;
}

// Ajouter une meta box SEO dans l'éditeur de page/post
function haptic_add_seo_metabox()
{
    $post_types = get_option('haptic_sitemap_post_types', ['post', 'page']);
    foreach ((array) $post_types as $post_type) {
        add_meta_box(
            'haptic_seo_meta',
            'SEO & partage',
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

    wp_enqueue_script('haptic-seo-preview', $url . 'seo-preview.js', [], filemtime($dir . 'seo-preview.js'), true);
    wp_enqueue_style('haptic-seo-preview-style', $url . 'seo-preview.css', [], filemtime($dir . 'seo-preview.css'));
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
    ?>
    <p>
        <label>
            <input type="checkbox" name="haptic_noindex" value="1" <?php checked($noindex, 1); ?>>
            Ne pas indexer ce contenu dans les moteurs de recherche
        </label>
    </p>

    <p class="haptic-seo-hint">Laissez un champ vide pour utiliser le motif défini dans <a href="<?php echo esc_url(admin_url('admin.php?page=haptic-options&tab=seo')); ?>">Optimizr › SEO</a> (affiché en gris).</p>

    <p>
        <label for="haptic_og_title"><strong>Titre SEO</strong> <span class="haptic-seo-hint">(onglet du navigateur, Google, partages)</span></label><br>
        <input type="text" id="haptic_og_title" name="haptic_og_title" value="<?php echo esc_attr($custom_title); ?>" placeholder="<?php echo esc_attr($seo['pattern_title']); ?>" class="widefat">
        <span class="haptic-char-count" id="haptic_title_count">0/60</span>
        <small class="haptic-seo-hint">Idéalement entre 50 et 60 caractères.</small>
    </p>

    <p>
        <label for="haptic_og_desc"><strong>Description SEO</strong> <span class="haptic-seo-hint">(Google, partages)</span></label><br>
        <textarea id="haptic_og_desc" name="haptic_og_desc" class="widefat" rows="3" placeholder="<?php echo esc_attr($seo['pattern_description']); ?>"><?php echo esc_textarea($custom_desc); ?></textarea>
        <span class="haptic-char-count" id="haptic_desc_count">0/160</span>
        <small class="haptic-seo-hint">Idéalement entre 120 et 160 caractères.</small>
    </p>

    <?php if (haptic_is_module_active('ai') && function_exists('haptic_ai_user_can') && haptic_ai_user_can()) : ?>
        <p>
            <button type="button" class="button" id="generate-seo-desc" data-post-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('haptic_ai_nonce')); ?>">
                Générer la description avec l'IA
            </button>
        </p>
        <?php
        wp_print_inline_script_tag(<<<'JS'
            document.addEventListener('DOMContentLoaded', function() {
                const button = document.getElementById('generate-seo-desc');
                const textarea = document.getElementById('haptic_og_desc');
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
                                action: 'haptic_generate_ai_seo_description',
                                post_id: this.dataset.postId,
                                nonce: this.dataset.nonce
                            })
                        })
                        .then(res => {
                            if (!res.ok) {
                                throw new Error(res.status);
                            }
                            return res.json();
                        })
                        .then(data => {
                            if (data.success) {
                                textarea.value = data.data.description;
                                textarea.dispatchEvent(new Event('input'));
                            } else {
                                alert('Erreur : ' + data.data);
                            }
                        })
                        // Réseau coupé, délai dépassé ou réponse invalide : le bouton redevient utilisable
                        .catch(() => {
                            alert('La requête a échoué. Vérifiez votre connexion.');
                        })
                        .finally(() => {
                            button.disabled = false;
                            button.innerText = 'Générer la description avec l\'IA';
                        });
                });
            });
        JS);
        ?>
    <?php endif; ?>

    <p>
        <label for="haptic_og_image"><strong>Image de partage</strong> <span class="haptic-seo-hint">(URL)</span></label><br>
        <input type="url" id="haptic_og_image" name="haptic_og_image" value="<?php echo esc_attr($custom_image); ?>" placeholder="<?php echo esc_attr($seo['default_image'] ?: 'Image à la une du contenu'); ?>" class="widefat">
        <small class="haptic-seo-hint">Par défaut : l'image à la une. Format idéal : 1200 × 630 px.</small>
    </p>

    <h3>Aperçu dans Google</h3>
    <div id="haptic_seo_preview" class="haptic-seo-preview-box">
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
    foreach ((array) get_option('haptic_sitemap_post_types', ['post', 'page']) as $post_type) {
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
add_action('init', 'haptic_register_seo_meta');

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
