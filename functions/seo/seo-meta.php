<?php

if (!defined('ABSPATH')) {
    exit;
}

// Ajouter une meta box SEO dans l'éditeur de page/post
function hpt_add_seo_metabox()
{
    $post_types = get_option('hpt_sitemap_post_types', ['post', 'page']);
    foreach ($post_types as $post_type) {
        add_meta_box(
            'hpt_seo_meta',
            'SEO & OpenGraph',
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


    $file_path = plugin_dir_path(dirname(__FILE__, 2)) . '/gutenberg/seo-preview.js';

    wp_enqueue_script(
        'hpt-seo-preview',
        plugin_dir_url(dirname(__FILE__, 2)) . '/gutenberg/seo-preview.js',
        [],
        filemtime(plugin_dir_path(dirname(__FILE__, 2)) . '/gutenberg/seo-preview.js'),
        true
    );

    wp_enqueue_style(
        'hpt-seo-preview-style',
        plugin_dir_url(dirname(__FILE__, 2)) . '/gutenberg/seo-preview.css',
        [],
        filemtime(plugin_dir_path(dirname(__FILE__, 2)) . '/gutenberg/seo-preview.css')
    );
}
add_action('admin_enqueue_scripts', 'hpt_enqueue_seo_scripts');

// Callback pour afficher la meta box
function hpt_seo_meta_callback($post)
{

    // Récupérer le titre et l'extrait du post par défaut
    $default_title = get_the_title($post->ID);
    $default_excerpt = get_the_excerpt($post->ID);
    $default_image = get_the_post_thumbnail_url($post->ID, 'large');

    // Récupération des valeurs enregistrées
    $noindex = get_post_meta($post->ID, '_hpt_noindex', true);

    // Récupération des valeurs enregistrées
    $og_title = get_post_meta($post->ID, '_hpt_og_title', true) ?: $default_title;
    $og_desc = get_post_meta($post->ID, '_hpt_og_desc', true) ?: $default_excerpt;
    $og_image = get_post_meta($post->ID, '_hpt_og_image', true) ?: $default_image;

    wp_nonce_field('hpt_seo_meta_nonce', 'hpt_seo_meta_nonce_field');

?>
    <p>
        <label>
            <input type="checkbox" name="hpt_noindex" value="1" <?php checked($noindex, 1); ?>>
            Désactiver l'indexation par Google
        </label>
    </p>

    <!-- Champ Titre SEO -->
    <p>
        <label for="hpt_og_title">Titre OpenGraph :</label><br>
        <input type="text" id="hpt_og_title" name="hpt_og_title" value="<?php echo esc_attr($og_title); ?>" class="widefat">
        <span class="hpt-char-count" id="hpt_title_count">0/60</span>
        <small class="hpt-seo-hint">🔹 Idéalement entre 50 et 60 caractères.</small>
    </p>

    <!-- Champ Description SEO -->
    <p>
        <label for="hpt_og_desc">Description OpenGraph :</label><br>
        <textarea id="hpt_og_desc" name="hpt_og_desc" class="widefat"><?php echo esc_textarea($og_desc); ?></textarea>
    <?php if (hpt_is_module_active('ai')) : ?>
    <p>
        <button type="button" class="button" id="generate-seo-desc" data-post-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('hpt_ai_nonce')); ?>">
            Générer avec l'IA
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
                        } else {
                            textarea.value = 'Erreur : ' + data.data;
                        }
                        button.disabled = false;
                        button.innerText = 'Générer avec l\'IA';
                    });
            });
        });
    </script>
    <?php endif; ?>
    <span class="hpt-char-count" id="hpt_desc_count">0/160</span>
    <small class="hpt-seo-hint">🔹 Idéalement entre 120 et 160 caractères.</small>
    </p>

    <!-- Champ Image OpenGraph -->
    <p>
        <label for="hpt_og_image">URL de l’image OpenGraph :</label><br>
        <input type="text" id="hpt_og_image" name="hpt_og_image" value="<?php echo esc_url($og_image); ?>" class="widefat">
        <small class="hpt-seo-hint">🔹 Utilisez une image de **1200x630px** pour un affichage optimal.</small>
    </p>

    <!-- Conteneur de prévisualisation -->
    <h3>Prévisualisation SEO</h3>
    <div id="hpt_seo_preview" class="hpt-seo-preview-box">
        <p class="seo-title"><?php echo esc_html($og_title ?: 'Exemple de titre SEO'); ?></p>
        <p class="seo-url"><?php echo esc_url(get_permalink($post->ID)); ?></p>
        <p class="seo-desc"><?php echo esc_html($og_desc ?: 'Exemple de description SEO générée automatiquement.'); ?></p>
        <?php if ($og_image) : ?>
            <img src="<?php echo esc_url($og_image); ?>" alt="Prévisualisation SEO">
        <?php endif; ?>
    </div>
<?php
}

// Sauvegarder les métadonnées
function hpt_save_seo_metadata($post_id)
{
    if (!isset($_POST['hpt_seo_meta_nonce_field']) || !wp_verify_nonce($_POST['hpt_seo_meta_nonce_field'], 'hpt_seo_meta_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    update_post_meta($post_id, '_hpt_noindex', isset($_POST['hpt_noindex']) ? 1 : 0);
    update_post_meta($post_id, '_hpt_og_title', sanitize_text_field($_POST['hpt_og_title']));
    update_post_meta($post_id, '_hpt_og_desc', sanitize_textarea_field($_POST['hpt_og_desc']));
    update_post_meta($post_id, '_hpt_og_image', esc_url($_POST['hpt_og_image']));
}
add_action('save_post', 'hpt_save_seo_metadata');


function hpt_register_seo_meta()
{
    register_post_meta('post', '_hpt_og_title', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ]);

    register_post_meta('post', '_hpt_og_desc', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ]);

    register_post_meta('post', '_hpt_og_image', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ]);
}
add_action('init', 'hpt_register_seo_meta');
