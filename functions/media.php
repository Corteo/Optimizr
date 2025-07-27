<?php

// Fichier pour gérer les fonctionalités liées à la galerie WP

add_action('add_attachment', 'haptic_GPT_generate_image_metadata_on_upload');
function haptic_GPT_generate_image_metadata_on_upload($post_ID)
{
    $post = get_post($post_ID);
    if ($post->post_mime_type && strpos($post->post_mime_type, 'image/') === 0) {
        $site_context = trim(get_option('hpt_ai_context', ''));
        $alt = haptic_GPT_generate_alt_with_ai($post->post_title, $post_ID);
        if ($alt) {
            update_post_meta($post_ID, '_wp_attachment_image_alt', $alt);
        }
    }
}

add_filter('attachment_fields_to_edit', 'haptic_GPT_add_ia_button_to_media_fields', 10, 2);

function haptic_GPT_add_ia_button_to_media_fields($form_fields, $post)
{
    if (strpos($post->post_mime_type, 'image/') !== 0) {
        return $form_fields;
    }

    $form_fields['hpt_ai_generate'] = [
        'label' => 'Générer la balise ALT',
        'input' => 'html',
        'html'  => '<button type="button" class="button hpt-ai-alt" data-post-id="' . esc_attr($post->ID) . '" style="margin-top:5px;"><i class="fa fa-magic"></i> Générer avec IA</button>',
        'helps' => 'Génère automatiquement une balise ALT optimisée grâce à l’IA à partir du contenu visuel de l’image.',
    ];

    return $form_fields;
}
