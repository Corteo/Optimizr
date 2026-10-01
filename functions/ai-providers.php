<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Assistant IA : appels aux API d'OpenAI, d'Anthropic (Claude) et de Google (Gemini),
 * en requêtes HTTP directes (wp_remote_post), sans SDK.
 *
 * Toutes les fonctions renvoient ['text' => string, 'input' => int, 'output' => int] ou un WP_Error :
 * - hpt_ai_no_key     : clé absente ;
 * - hpt_ai_http       : erreur de l'API, avec ['status' => code HTTP] (0 si connexion impossible) ;
 * - hpt_ai_refusal    : le modèle a refusé de répondre (filtre de sécurité) ;
 * - hpt_ai_empty      : réponse sans texte ;
 * - hpt_ai_image_size : image trop lourde pour être envoyée.
 */

/**
 * Envoie une demande au fournisseur actif et enregistre la consommation réelle.
 *
 * @param string $system Consigne générale
 * @param array  $parts  Contenu : ['type' => 'text', 'text' => …] et ['type' => 'image', 'path' => …] ou ['type' => 'image', 'url' => …]
 * @return string|WP_Error Texte généré
 */
function hpt_ai_chat($system, array $parts, $max_tokens, $temperature = 0.7, $model = null)
{
    $provider = hpt_ai_provider();
    $model = $model ?: hpt_ai_request_model();

    if (hpt_ai_api_key() === '') {
        return new WP_Error('hpt_ai_no_key', sprintf('Clé API %s manquante (onglet Assistant IA).', hpt_ai_provider_config()['label']));
    }

    $options = (array) (hpt_ai_model_config($model)['options'] ?? []);
    $adapter = 'hpt_ai_request_' . $provider;
    $result = $adapter($model, $system, $parts, (int) $max_tokens, (float) $temperature, $options);

    if (is_wp_error($result)) {
        return $result;
    }

    // Consommation réelle, facturée même si la réponse est inutilisable
    hpt_ai_record_usage($provider, $model, $result['input'], $result['output']);

    $text = trim($result['text'], "\"'«» \t\n\r\0\x0B");
    if ($text === '') {
        return new WP_Error('hpt_ai_empty', 'Le modèle n\'a renvoyé aucun texte.');
    }

    return $text;
}

/** Contenu binaire d'une image, encodé en base64, avec son type MIME. */
function hpt_ai_image_payload(array $part)
{
    if (empty($part['path']) || !is_readable($part['path'])) {
        return null;
    }
    return [
        'mime' => wp_check_filetype($part['path'])['type'] ?: 'image/jpeg',
        'data' => base64_encode(file_get_contents($part['path'])),
    ];
}

/** Envoi HTTP commun : JSON en entrée et en sortie, erreurs de connexion et d'API uniformisées. */
function hpt_ai_post_json($url, array $headers, array $body, $label)
{
    $response = wp_remote_post($url, [
        'timeout' => 60,
        'headers' => $headers + ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode($body),
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('hpt_ai_http', sprintf('Connexion à %s impossible : %s', $label, $response->get_error_message()), ['status' => 0]);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if ($code !== 200) {
        $message = $data['error']['message'] ?? 'réponse HTTP ' . $code;
        return new WP_Error('hpt_ai_http', $label . ' : ' . $message, ['status' => $code]);
    }

    return is_array($data) ? $data : new WP_Error('hpt_ai_http', $label . ' : réponse illisible.', ['status' => $code]);
}

/* ---------- OpenAI : Chat Completions ---------- */

function hpt_ai_request_openai($model, $system, array $parts, $max_tokens, $temperature, array $options)
{
    $content = [];
    foreach ($parts as $part) {
        if ($part['type'] === 'text') {
            $content[] = ['type' => 'text', 'text' => $part['text']];
            continue;
        }
        $image = hpt_ai_image_payload($part);
        $url = $image ? 'data:' . $image['mime'] . ';base64,' . $image['data'] : ($part['url'] ?? '');
        $content[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
    }

    $body = [
        'model'    => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $content],
        ],
    ];

    if (!empty($options['reasoning_effort'])) {
        // Modèles à raisonnement (GPT-5) : température imposée ; la limite inclut les tokens de réflexion
        $body['reasoning_effort'] = $options['reasoning_effort'];
        $body['max_completion_tokens'] = max($max_tokens, 2000);
    } else {
        $body['temperature'] = $temperature;
        $body['max_completion_tokens'] = $max_tokens;
    }

    $data = hpt_ai_post_json('https://api.openai.com/v1/chat/completions', [
        'Authorization' => 'Bearer ' . hpt_ai_api_key('openai'),
    ], $body, 'OpenAI');

    if (is_wp_error($data)) {
        return $data;
    }

    if (($data['choices'][0]['finish_reason'] ?? '') === 'content_filter') {
        return new WP_Error('hpt_ai_refusal', 'OpenAI : réponse bloquée par le filtre de contenu.');
    }

    return [
        'text'   => (string) ($data['choices'][0]['message']['content'] ?? ''),
        'input'  => (int) ($data['usage']['prompt_tokens'] ?? 0),
        'output' => (int) ($data['usage']['completion_tokens'] ?? 0),
    ];
}

/* ---------- Anthropic : Messages API ---------- */

function hpt_ai_request_anthropic($model, $system, array $parts, $max_tokens, $temperature, array $options)
{
    // Images avant le texte, comme le recommande la documentation d'Anthropic
    usort($parts, function ($a, $b) {
        return ($a['type'] === 'image' ? 0 : 1) - ($b['type'] === 'image' ? 0 : 1);
    });

    $content = [];
    foreach ($parts as $part) {
        if ($part['type'] === 'text') {
            $content[] = ['type' => 'text', 'text' => $part['text']];
            continue;
        }
        $image = hpt_ai_image_payload($part);
        $content[] = $image
            ? ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => $image['data']]]
            : ['type' => 'image', 'source' => ['type' => 'url', 'url' => $part['url'] ?? '']];
    }

    $body = [
        'model'      => $model,
        // Marge pour la réflexion éventuelle du modèle ; seuls les tokens produits sont facturés
        'max_tokens' => 2048,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $content]],
    ];
    $headers = [
        'x-api-key'         => hpt_ai_api_key('anthropic'),
        'anthropic-version' => '2023-06-01',
    ];

    if (!empty($options['effort'])) {
        // Modèles récents : réflexion pilotée par l'effort, température non réglable
        $body['output_config'] = ['effort' => $options['effort']];
        if (!empty($options['thinking'])) {
            $body['thinking'] = ['type' => $options['thinking']];
        }
    } else {
        $body['temperature'] = $temperature;
    }

    if (!empty($options['fallbacks'])) {
        // Si le modèle refuse la demande, Anthropic la relance sur le modèle de repli recommandé
        $body['fallbacks'] = 'default';
        $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
    }

    $data = hpt_ai_post_json('https://api.anthropic.com/v1/messages', $headers, $body, 'Anthropic');

    if (is_wp_error($data)) {
        return $data;
    }

    $usage = [
        'input'  => (int) ($data['usage']['input_tokens'] ?? 0),
        'output' => (int) ($data['usage']['output_tokens'] ?? 0),
    ];

    if (($data['stop_reason'] ?? '') === 'refusal') {
        hpt_ai_record_usage('anthropic', $model, $usage['input'], $usage['output']);
        return new WP_Error('hpt_ai_refusal', 'Claude a refusé de traiter cet élément.');
    }

    $text = '';
    foreach ((array) ($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }

    return ['text' => $text] + $usage;
}

/* ---------- Google : Gemini generateContent ---------- */

function hpt_ai_request_gemini($model, $system, array $parts, $max_tokens, $temperature, array $options)
{
    $content = [];
    foreach ($parts as $part) {
        if ($part['type'] === 'text') {
            $content[] = ['text' => $part['text']];
            continue;
        }
        $image = hpt_ai_image_payload($part);
        if (!$image) {
            return new WP_Error('hpt_ai_image_size', 'Gemini : aucune version de l\'image assez légère pour être envoyée (4 Mo maximum).');
        }
        $content[] = ['inlineData' => ['mimeType' => $image['mime'], 'data' => $image['data']]];
    }

    $data = hpt_ai_post_json(
        'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent',
        ['x-goog-api-key' => hpt_ai_api_key('gemini')],
        [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => [['role' => 'user', 'parts' => $content]],
            'generationConfig'  => [
                'temperature'     => $temperature,
                // Marge pour la réflexion des modèles Gemini 3.x, facturée comme de la sortie
                'maxOutputTokens' => max($max_tokens, 1024),
            ],
        ],
        'Gemini'
    );

    if (is_wp_error($data)) {
        // Clé invalide : Google répond 400 au lieu de 401
        if ((int) ($data->get_error_data()['status'] ?? 0) === 400 && stripos($data->get_error_message(), 'API key') !== false) {
            return new WP_Error('hpt_ai_http', $data->get_error_message(), ['status' => 401]);
        }
        return $data;
    }

    $usage = [
        'input'  => (int) ($data['usageMetadata']['promptTokenCount'] ?? 0),
        'output' => (int) ($data['usageMetadata']['candidatesTokenCount'] ?? 0) + (int) ($data['usageMetadata']['thoughtsTokenCount'] ?? 0),
    ];

    $finish = $data['candidates'][0]['finishReason'] ?? '';
    if (!empty($data['promptFeedback']['blockReason']) || in_array($finish, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION'], true)) {
        hpt_ai_record_usage('gemini', $model, $usage['input'], $usage['output']);
        return new WP_Error('hpt_ai_refusal', 'Gemini a refusé de traiter cet élément.');
    }

    $text = '';
    foreach ((array) ($data['candidates'][0]['content']['parts'] ?? []) as $part) {
        // Les parties « pensée » ne font pas partie de la réponse
        if (isset($part['text']) && empty($part['thought'])) {
            $text .= $part['text'];
        }
    }

    return ['text' => $text] + $usage;
}
