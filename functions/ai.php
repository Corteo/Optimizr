<?php
add_action('admin_init', 'hpt_register_ai_settings');

function hpt_register_ai_settings()
{
    register_setting('hpt-options-settings-group', 'hpt_ai_provider');
    register_setting('hpt-options-settings-group', 'hpt_openai_api_key');
    register_setting('hpt-options-settings-group', 'hpt_openai_model');
    register_setting('hpt-options-settings-group', 'hpt_ai_context');

    add_settings_section(
        'hpt_ai_settings_section',
        'Assistant IA',
        'hpt_ai_section_callback',
        'hpt-options'
    );

    add_settings_field('hpt_ai_provider', 'Fournisseur IA', 'hpt_ai_provider_callback', 'hpt-options', 'hpt_ai_settings_section');
    add_settings_field('hpt_openai_model', 'Modèle OpenAI', 'hpt_openai_model_callback', 'hpt-options', 'hpt_ai_settings_section');
    add_settings_field('hpt_openai_api_key', 'Clé API OpenAI', 'hpt_openai_api_key_callback', 'hpt-options', 'hpt_ai_settings_section');
    add_settings_field('hpt_ai_context', 'Contexte global IA', 'hpt_ai_context_callback', 'hpt-options', 'hpt_ai_settings_section');
}

function hpt_ai_section_callback()
{
    echo '<p>Configuration de l\'assistant IA pour la génération de descriptions SEO.</p>';
}

function hpt_ai_provider_callback()
{
    $option = get_option('hpt_ai_provider', 'openai');
    echo '<select name="hpt_ai_provider">
        <option value="openai" ' . selected($option, 'openai', false) . '>OpenAI</option>
    </select>';
}

function hpt_openai_model_callback()
{
    $model = get_option('hpt_openai_model', 'gpt-4o');
    echo '<select name="hpt_openai_model" class="regular-text">';
    echo '<p class="description" style="margin-bottom:6px;">Choisissez un modèle adapté à vos besoins. GPT-4o est recommandé pour les performances et la compréhension multimodale.</p>';
    $models = [
        'gpt-4.1' => 'GPT-4.1 (performances améliorées)',
        'gpt-4.1-mini' => 'GPT-4.1 Mini (léger, rapide)',
        'gpt-4.1-nano' => 'GPT-4.1 Nano (ultra-léger)',
        'gpt-4o' => 'GPT-4o (multimodal, rapide, recommandé)',
        'gpt-4-turbo' => 'GPT-4 Turbo (performant, context long)',
        'gpt-4' => 'GPT-4 (standard)',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo (économique)',
    ];
    foreach ($models as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($model, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

function hpt_openai_api_key_callback()
{
    $key = get_option('hpt_openai_api_key', '');
    echo '<input type="password" name="hpt_openai_api_key" value="' . esc_attr($key) . '" class="regular-text" />';
}

function hpt_ai_context_callback()
{
    $context = get_option('hpt_ai_context', '');
    echo '<textarea name="hpt_ai_context" rows="4" class="large-text" placeholder="Décrivez ici le contexte global de votre site (ex. : école de céramique, atelier photo, agence de voyage, etc.)">' . esc_textarea($context) . '</textarea>';
}

add_action('wp_ajax_hpt_generate_ai_seo_description', 'hpt_generate_ai_seo_description');

function hpt_generate_ai_seo_description()
{
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissions insuffisantes');
    }

    $post_id = intval($_POST['post_id']);
    $content = wp_strip_all_tags(get_post_field('post_content', $post_id));

    $api_key = get_option('hpt_openai_api_key');
    $model = get_option('hpt_openai_model', 'gpt-4');

    if (empty($api_key)) {
        wp_send_json_error('Clé API manquante');
    }

    $site_context = trim(get_option('hpt_ai_context', ''));

    $prompt = "Rédige une meta description optimisée SEO de 160 caractères maximum (espaces compris) à partir du texte suivant. :\n\n$content" . ($site_context ? "\n\n Voici le contexte du site web :" . $site_context : '');

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Tu es un assistant expert en rédaction SEO.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 100,
        ]),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Erreur API : ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $desc = trim($body['choices'][0]['message']['content'] ?? '', "\"' \t\n\r\0\x0B");

    wp_send_json_success(['description' => $desc]);
}

function haptic_GPT_generate_alt_with_ai($title, $post_ID)
{
    $api_key = get_option('hpt_openai_api_key');
    $model = get_option('hpt_openai_model', 'gpt-4.1');

    if (!in_array($model, ['gpt-4o', 'gpt-4-vision-preview'])) {
        $model = 'gpt-4o';
    }

    if (empty($api_key)) return '';

    $site_context = trim(get_option('hpt_ai_context', ''));

    $image_url = wp_get_attachment_url($post_ID);
    if (!$image_url) return '';

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un assistant expert en accessibilité, SEO et description d’images.'
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Décris cette image de façon concise pour une balise ALT accessible et optimisée SEO. Le resulat doit être uniquement le contenu de la balise' . ($site_context ? "\n\nContexte : " . $site_context : '')
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $image_url
                            ]
                        ]
                    ]
                ]
            ],
            'temperature' => 0.6,
            'max_tokens' => 80,
        ]),
    ]);

    if (is_wp_error($response)) return '';
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return trim($body['choices'][0]['message']['content'] ?? '', "\"' \t\n\r\0\x0B");
}

add_action('wp_ajax_haptic_GPT_generate_image_meta', 'haptic_GPT_generate_image_meta');
function haptic_GPT_generate_image_meta()
{
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissions insuffisantes');
    }

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) {
        wp_send_json_error('ID de fichier manquant');
    }

    $post = get_post($post_id);
    if (!$post || strpos($post->post_mime_type, 'image/') !== 0) {
        wp_send_json_error('Ce n’est pas une image valide');
    }

    $alt = haptic_GPT_generate_alt_with_ai($post->post_title, $post_id);

    if ($alt) {
        update_post_meta($post_id, '_wp_attachment_image_alt', $alt);
    }

    wp_send_json_success([
        'alt' => $alt,
    ]);
}
