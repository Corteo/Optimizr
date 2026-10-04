<?php

if (!defined('ABSPATH')) {
    exit;
}

// Fichier pour gérer les fonctionalités liées à la galerie WP

// La modale média peut s'ouvrir sur n'importe quel écran d'admin
add_action('admin_enqueue_scripts', 'haptic_GPT_enqueue_media_ia_script');
function haptic_GPT_enqueue_media_ia_script()
{
    if (!current_user_can('upload_files') || !hpt_ai_user_can()) {
        return;
    }

    wp_enqueue_script('hpt-media-ia', plugin_dir_url(__DIR__) . 'assets/js/media-ia.js', ['jquery'], HPT_OPTIMIZR_VERSION, true);
    wp_localize_script('hpt-media-ia', 'hptMediaIA', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('hpt_ai_nonce'),
    ]);
}

// ALT automatique à l'import : programmé une fois les tailles d'image générées, pour envoyer une version
// allégée plutôt que l'original (moins de tokens facturés). La génération se fait en tâche de fond (WP-Cron) :
// l'import n'attend pas la réponse de l'IA. Rien pendant un import de contenus (WP_IMPORTING), qui serait facturé image par image.
add_filter('wp_generate_attachment_metadata', 'haptic_GPT_generate_image_metadata_on_upload', 20, 3);
function haptic_GPT_generate_image_metadata_on_upload($metadata, $post_ID, $context = 'create')
{
    if ($context !== 'create' || defined('WP_IMPORTING') || !get_option('hpt_ai_alt_on_upload', 1) || !wp_attachment_is_image($post_ID)) {
        return $metadata;
    }

    // Pas de clé : rien à programmer. Image envoyée par un visiteur ou un compte sans le droit d'utiliser l'IA
    // (formulaire public, import programmé…) : pas de requête facturée. WP-CLI (wp media import) reste traité.
    if (hpt_ai_api_key() === '' || (!(defined('WP_CLI') && WP_CLI) && !hpt_ai_user_can())) {
        return $metadata;
    }

    if (get_post_meta($post_ID, '_wp_attachment_image_alt', true) !== '') {
        return $metadata;
    }

    if (!wp_next_scheduled('hpt_ai_alt_on_upload', [$post_ID])) {
        wp_schedule_single_event(time(), 'hpt_ai_alt_on_upload', [$post_ID]);
    }

    return $metadata;
}

add_action('hpt_ai_alt_on_upload', 'hpt_ai_alt_on_upload_cron');
function hpt_ai_alt_on_upload_cron($attachment_id)
{
    $attachment_id = (int) $attachment_id;

    // Image supprimée, réglage désactivé ou balise ALT saisie entre-temps : rien à faire
    if (!get_post($attachment_id) || !get_option('hpt_ai_alt_on_upload', 1) || get_post_meta($attachment_id, '_wp_attachment_image_alt', true) !== '') {
        return;
    }

    $alt = haptic_GPT_generate_alt_with_ai('', $attachment_id);

    // Balise saisie pendant la requête (dans une autre requête PHP, d'où le cache vidé) : elle est conservée
    wp_cache_delete($attachment_id, 'post_meta');
    if ($alt && get_post_meta($attachment_id, '_wp_attachment_image_alt', true) === '') {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
    }
}

add_filter('attachment_fields_to_edit', 'haptic_GPT_add_ia_button_to_media_fields', 10, 2);

function haptic_GPT_add_ia_button_to_media_fields($form_fields, $post)
{
    if (strpos($post->post_mime_type, 'image/') !== 0 || !hpt_ai_user_can()) {
        return $form_fields;
    }

    $form_fields['hpt_ai_generate'] = [
        'label' => 'Générer la balise ALT',
        'input' => 'html',
        'html'  => '<button type="button" class="button hpt-ai-alt" data-post-id="' . esc_attr($post->ID) . '" style="margin-top:5px;">Générer avec l\'IA</button>',
        'helps' => 'Génère automatiquement une balise ALT optimisée grâce à l’IA à partir du contenu visuel de l’image.',
    ];

    return $form_fields;
}
