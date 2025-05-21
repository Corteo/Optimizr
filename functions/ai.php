<?php
add_action('admin_init', 'hpt_register_ai_settings');

function hpt_register_ai_settings()
{
    register_setting('hpt-options-settings-group', 'hpt_ai_provider');
    register_setting('hpt-options-settings-group', 'hpt_openai_api_key');
    register_setting('hpt-options-settings-group', 'hpt_openai_model');

    add_settings_section(
        'hpt_ai_settings_section',
        'Assistant IA',
        'hpt_ai_section_callback',
        'hpt-options'
    );

    add_settings_field('hpt_ai_provider', 'Fournisseur IA', 'hpt_ai_provider_callback', 'hpt-options', 'hpt_ai_settings_section');
    add_settings_field('hpt_openai_model', 'Modèle OpenAI', 'hpt_openai_model_callback', 'hpt-options', 'hpt_ai_settings_section');
    add_settings_field('hpt_openai_api_key', 'Clé API OpenAI', 'hpt_openai_api_key_callback', 'hpt-options', 'hpt_ai_settings_section');
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
    $model = get_option('hpt_openai_model', 'gpt-4');
    echo '<input type="text" name="hpt_openai_model" value="' . esc_attr($model) . '" class="regular-text" />';
}

function hpt_openai_api_key_callback()
{
    $key = get_option('hpt_openai_api_key', '');
    echo '<input type="password" name="hpt_openai_api_key" value="' . esc_attr($key) . '" class="regular-text" />';
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

    $prompt = "Rédige une meta description optimisée SEO de 160 caractères maximum (espaces compris) à partir du texte suivant. :\n\n$content";

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
