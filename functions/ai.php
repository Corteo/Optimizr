<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'hpt_register_ai_settings');

function hpt_register_ai_settings()
{
    $group = hpt_settings_group('ai');
    $page  = hpt_settings_page('ai');

    register_setting($group, 'hpt_ai_provider', ['sanitize_callback' => 'sanitize_key']);
    register_setting($group, 'hpt_openai_api_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting($group, 'hpt_openai_model', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting($group, 'hpt_ai_context', ['sanitize_callback' => 'sanitize_textarea_field']);

    add_settings_section(
        'hpt_ai_settings_section',
        'Connexion à OpenAI',
        'hpt_ai_section_callback',
        $page
    );

    add_settings_field('hpt_ai_provider', 'Fournisseur', 'hpt_ai_provider_callback', $page, 'hpt_ai_settings_section', ['label_for' => 'hpt_ai_provider']);
    add_settings_field('hpt_openai_model', 'Modèle', 'hpt_openai_model_callback', $page, 'hpt_ai_settings_section', [
        'label_for' => 'hpt_openai_model',
        'description' => 'GPT-4o est recommandé : il sait aussi décrire les images.',
    ]);
    add_settings_field('hpt_openai_api_key', 'Clé API', 'hpt_openai_api_key_callback', $page, 'hpt_ai_settings_section', [
        'label_for' => 'hpt_openai_api_key',
        'description' => 'À créer sur platform.openai.com. Elle n\'est jamais affichée en clair.',
    ]);

    add_settings_section(
        'hpt_ai_context_section',
        'Contexte du site',
        function () {
            echo '<p>Ajouté à chaque demande pour que les textes générés collent à votre activité.</p>';
        },
        $page
    );
    add_settings_field('hpt_ai_context', 'Description de l\'activité', 'hpt_ai_context_callback', $page, 'hpt_ai_context_section', [
        'label_for' => 'hpt_ai_context',
        'layout' => 'stacked',
    ]);
}

function hpt_ai_section_callback()
{
    echo '<p>Utilisée pour les meta descriptions (metabox SEO) et les balises ALT (médiathèque).</p>';
}

function hpt_ai_provider_callback()
{
    $option = get_option('hpt_ai_provider', 'openai');
    echo '<select name="hpt_ai_provider" id="hpt_ai_provider">
        <option value="openai" ' . selected($option, 'openai', false) . '>OpenAI</option>
    </select>';
}

function hpt_openai_model_callback()
{
    $model = get_option('hpt_openai_model', 'gpt-4o');
    echo '<select name="hpt_openai_model" id="hpt_openai_model">';
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
    echo '<input type="password" name="hpt_openai_api_key" id="hpt_openai_api_key" value="' . esc_attr($key) . '" autocomplete="off" spellcheck="false" />';
}

function hpt_ai_context_callback()
{
    $context = get_option('hpt_ai_context', '');
    echo '<textarea name="hpt_ai_context" id="hpt_ai_context" rows="4" placeholder="Décrivez ici le contexte global de votre site (ex. : école de céramique, atelier photo, agence de voyage, etc.)">' . esc_textarea($context) . '</textarea>';
}

add_action('wp_ajax_hpt_generate_ai_seo_description', 'hpt_generate_ai_seo_description');

/**
 * Appel à l'API OpenAI (chat completions).
 *
 * @return string|WP_Error Texte généré, ou erreur (hpt_ai_no_key, hpt_ai_http avec le code HTTP, hpt_ai_empty)
 */
function hpt_openai_chat(array $messages, $max_tokens, $temperature = 0.7, $model = null)
{
    $api_key = get_option('hpt_openai_api_key');
    if (empty($api_key)) {
        return new WP_Error('hpt_ai_no_key', 'Clé API OpenAI manquante (onglet Assistant IA).');
    }

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'timeout' => 45,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'model' => $model ?: get_option('hpt_openai_model', 'gpt-4o'),
            'messages' => $messages,
            'temperature' => $temperature,
            'max_completion_tokens' => (int) $max_tokens,
        ]),
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('hpt_ai_http', 'Connexion à OpenAI impossible : ' . $response->get_error_message(), ['status' => 0]);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code !== 200) {
        $message = $body['error']['message'] ?? 'réponse HTTP ' . $code;
        return new WP_Error('hpt_ai_http', 'OpenAI : ' . $message, ['status' => $code]);
    }

    $text = trim((string) ($body['choices'][0]['message']['content'] ?? ''), "\"'«» \t\n\r\0\x0B");
    if ($text === '') {
        return new WP_Error('hpt_ai_empty', 'OpenAI n\'a renvoyé aucun texte.');
    }

    return $text;
}

/**
 * Meta description d'un contenu, générée à partir de son texte.
 *
 * @return string|WP_Error
 */
function hpt_ai_generate_description($post_id)
{
    $content = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes(get_post_field('post_content', $post_id)))));
    if ($content === '') {
        return new WP_Error('hpt_ai_no_content', 'Le contenu ne contient pas de texte.');
    }

    $site_context = trim(get_option('hpt_ai_context', ''));
    $prompt = 'Rédige une meta description optimisée SEO de 160 caractères maximum (espaces compris) pour la page « ' . get_the_title($post_id) . " », à partir du texte suivant. Réponds uniquement par la description.\n\n"
        . mb_substr($content, 0, 6000)
        . ($site_context ? "\n\nContexte du site : " . $site_context : '');

    $text = hpt_openai_chat([
        ['role' => 'system', 'content' => 'Tu es un assistant expert en rédaction SEO.'],
        ['role' => 'user', 'content' => $prompt],
    ], 120);

    if (is_wp_error($text)) {
        return $text;
    }

    // Garde-fou : 160 caractères, coupés sur un mot
    if (mb_strlen($text) > 160) {
        $text = mb_substr($text, 0, 159);
        $text = mb_substr($text, 0, (int) mb_strrpos($text, ' ')) . '…';
    }

    return $text;
}

/**
 * Balise ALT d'une image. L'image est envoyée en pièce jointe (base64) quand c'est possible :
 * fonctionne aussi en local et sur les sites protégés, que les serveurs d'OpenAI ne peuvent pas lire.
 *
 * @return string|WP_Error
 */
function hpt_ai_generate_alt($attachment_id)
{
    if (!wp_attachment_is_image($attachment_id)) {
        return new WP_Error('hpt_ai_not_image', 'Ce fichier n\'est pas une image.');
    }

    $image_url = '';
    $original = get_attached_file($attachment_id);
    foreach (['large', 'medium_large', 'full'] as $size) {
        $src = image_get_intermediate_size($attachment_id, $size);
        $path = $src ? path_join(dirname($original), $src['file']) : ($size === 'full' ? $original : '');
        if ($path && is_readable($path) && filesize($path) <= 4 * MB_IN_BYTES) {
            $mime = wp_check_filetype($path)['type'] ?: 'image/jpeg';
            $image_url = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            break;
        }
    }
    $image_url = $image_url ?: wp_get_attachment_url($attachment_id);

    $site_context = trim(get_option('hpt_ai_context', ''));
    $model = get_option('hpt_openai_model', 'gpt-4o');
    // Les modèles texte seuls ne lisent pas les images
    if (!in_array($model, ['gpt-4o', 'gpt-4.1', 'gpt-4.1-mini', 'gpt-4-turbo'], true)) {
        $model = 'gpt-4o';
    }

    return hpt_openai_chat([
        ['role' => 'system', 'content' => 'Tu es un assistant expert en accessibilité, SEO et description d’images.'],
        ['role' => 'user', 'content' => [
            [
                'type' => 'text',
                'text' => 'Décris cette image de façon concise (125 caractères maximum) pour une balise ALT accessible et optimisée SEO. Réponds uniquement par le contenu de la balise.' . ($site_context ? "\n\nContexte du site : " . $site_context : ''),
            ],
            ['type' => 'image_url', 'image_url' => ['url' => $image_url]],
        ]],
    ], 80, 0.6, $model);
}

// Metabox SEO : bouton « Générer la description avec l'IA »
function hpt_generate_ai_seo_description()
{
    check_ajax_referer('hpt_ai_nonce', 'nonce');

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Permissions insuffisantes');
    }

    $desc = hpt_ai_generate_description($post_id);
    if (is_wp_error($desc)) {
        wp_send_json_error($desc->get_error_message());
    }

    wp_send_json_success(['description' => $desc]);
}

// Compatibilité : ancienne signature, renvoie une chaîne vide en cas d'erreur
function haptic_GPT_generate_alt_with_ai($title, $post_ID)
{
    $alt = hpt_ai_generate_alt($post_ID);
    return is_wp_error($alt) ? '' : $alt;
}

add_action('wp_ajax_haptic_GPT_generate_image_meta', 'haptic_GPT_generate_image_meta');
function haptic_GPT_generate_image_meta()
{
    check_ajax_referer('hpt_ai_nonce', 'nonce');

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) {
        wp_send_json_error('ID de fichier manquant');
    }

    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Permissions insuffisantes');
    }

    $post = get_post($post_id);
    if (!$post || strpos($post->post_mime_type, 'image/') !== 0) {
        wp_send_json_error('Ce n’est pas une image valide');
    }

    $alt = hpt_ai_generate_alt($post_id);
    if (is_wp_error($alt)) {
        wp_send_json_error($alt->get_error_message());
    }

    update_post_meta($post_id, '_wp_attachment_image_alt', $alt);

    wp_send_json_success([
        'alt' => $alt,
    ]);
}
