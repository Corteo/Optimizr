<?php

if (!defined('ABSPATH')) {
    exit;
}

// Fichier pour gérer les fonctionalités liées à la galerie WP

// La modale média peut s'ouvrir sur n'importe quel écran d'admin
add_action('admin_enqueue_scripts', 'haptic_enqueue_media_ia_script');
function haptic_enqueue_media_ia_script()
{
    if (!current_user_can('upload_files') || !haptic_ai_user_can()) {
        return;
    }

    wp_enqueue_script('haptic-media-ia', plugin_dir_url(__DIR__) . 'assets/js/media-ia.js', ['jquery', 'wp-i18n'], HAPTIC_OPTIMIZR_VERSION, true);
    wp_set_script_translations('haptic-media-ia', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
    wp_localize_script('haptic-media-ia', 'hapticMediaIA', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('haptic_ai_nonce'),
    ]);
}

// ALT automatique à l'import : programmé une fois les tailles d'image générées, pour envoyer une version
// allégée plutôt que l'original (moins de tokens facturés). La génération se fait en tâche de fond (WP-Cron) :
// l'import n'attend pas la réponse de l'IA. Rien pendant un import de contenus (WP_IMPORTING), qui serait facturé image par image.
add_filter('wp_generate_attachment_metadata', 'haptic_generate_image_metadata_on_upload', 20, 3);
function haptic_generate_image_metadata_on_upload($metadata, $post_ID, $context = 'create')
{
    if ($context !== 'create' || defined('WP_IMPORTING') || !get_option('haptic_ai_alt_on_upload', 1) || !wp_attachment_is_image($post_ID)) {
        return $metadata;
    }

    // Pas de clé : rien à programmer. Image envoyée par un visiteur ou un compte sans le droit d'utiliser l'IA
    // (formulaire public, import programmé…) : pas de requête facturée. WP-CLI (wp media import) reste traité.
    if (haptic_ai_api_key() === '' || (!(defined('WP_CLI') && WP_CLI) && !haptic_ai_user_can())) {
        return $metadata;
    }

    if (get_post_meta($post_ID, '_wp_attachment_image_alt', true) !== '') {
        return $metadata;
    }

    if (!wp_next_scheduled('haptic_ai_alt_on_upload', [$post_ID])) {
        wp_schedule_single_event(time(), 'haptic_ai_alt_on_upload', [$post_ID]);
    }

    return $metadata;
}

add_action('haptic_ai_alt_on_upload', 'haptic_ai_alt_on_upload_cron');
function haptic_ai_alt_on_upload_cron($attachment_id)
{
    $attachment_id = (int) $attachment_id;

    // Image supprimée, réglage désactivé ou balise ALT saisie entre-temps : rien à faire
    if (!get_post($attachment_id) || !get_option('haptic_ai_alt_on_upload', 1) || get_post_meta($attachment_id, '_wp_attachment_image_alt', true) !== '') {
        return;
    }

    $alt = haptic_generate_alt_with_ai('', $attachment_id);

    // Balise saisie pendant la requête (dans une autre requête PHP, d'où le cache vidé) : elle est conservée
    wp_cache_delete($attachment_id, 'post_meta');
    if ($alt && get_post_meta($attachment_id, '_wp_attachment_image_alt', true) === '') {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
    }
}

add_filter('attachment_fields_to_edit', 'haptic_add_ia_button_to_media_fields', 10, 2);

function haptic_add_ia_button_to_media_fields($form_fields, $post)
{
    if (strpos($post->post_mime_type, 'image/') !== 0 || !haptic_ai_user_can()) {
        return $form_fields;
    }

    $form_fields['haptic_ai_generate'] = [
        'label' => __('Generate ALT text', 'optimizr'),
        'input' => 'html',
        'html'  => '<button type="button" class="button haptic-ai-alt" data-post-id="' . esc_attr($post->ID) . '" style="margin-top:5px;">' . esc_html__('Generate with AI', 'optimizr') . '</button>',
        'helps' => __('Automatically generates optimized ALT text with AI from the visual content of the image.', 'optimizr'),
    ];

    return $form_fields;
}
