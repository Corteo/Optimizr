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
    register_setting($group, 'hpt_ai_alt_on_upload', ['sanitize_callback' => 'absint']);

    add_settings_section(
        'hpt_ai_settings_section',
        'Connexion à OpenAI',
        'hpt_ai_section_callback',
        $page
    );

    add_settings_field('hpt_ai_provider', 'Fournisseur', 'hpt_ai_provider_callback', $page, 'hpt_ai_settings_section', ['label_for' => 'hpt_ai_provider']);
    add_settings_field('hpt_openai_model', 'Modèle', 'hpt_openai_model_callback', $page, 'hpt_ai_settings_section', [
        'label_for' => 'hpt_openai_model',
        'description' => 'Tous les modèles proposés savent décrire les images.',
    ]);
    add_settings_field('hpt_openai_api_key', 'Clé API', 'hpt_openai_api_key_callback', $page, 'hpt_ai_settings_section', [
        'label_for' => 'hpt_openai_api_key',
        'description' => 'À créer sur platform.openai.com. Elle n\'est jamais affichée en clair.',
    ]);
    add_settings_field('hpt_ai_alt_on_upload', 'ALT automatique à l\'import', function () {
        echo hpt_switch_input('hpt_ai_alt_on_upload', get_option('hpt_ai_alt_on_upload', 1), 'Générer la balise ALT à l\'import des images');
    }, $page, 'hpt_ai_settings_section', [
        'description' => 'Génère la balise ALT de chaque image importée dans la médiathèque (une requête facturée par image).',
    ]);

    add_settings_section(
        'hpt_ai_costs_section',
        'Coûts',
        function () {
            printf(
                '<p>Estimations calculées sur les images et les contenus de ce site, aux tarifs OpenAI relevés le %s (<a href="%s" target="_blank" rel="noopener">grille officielle</a>), en dollars US hors taxes.</p>',
                esc_html(date_i18n('j F Y', strtotime(HPT_AI_PRICING_DATE))),
                esc_url(HPT_AI_PRICING_SOURCE)
            );
        },
        $page
    );
    add_settings_field('hpt_ai_usage', 'Consommation réelle', 'hpt_ai_usage_callback', $page, 'hpt_ai_costs_section');
    add_settings_field('hpt_ai_costs', 'Comparatif des modèles', 'hpt_ai_costs_callback', $page, 'hpt_ai_costs_section', ['layout' => 'stacked']);

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
    $current = hpt_ai_current_model();
    $models = hpt_ai_models();

    echo '<select name="hpt_openai_model" id="hpt_openai_model">';
    // Ancien modèle enregistré (GPT-4, GPT-3.5…) : conservé tant qu'on n'en choisit pas un autre
    if (!isset($models[$current])) {
        echo '<option value="' . esc_attr($current) . '" selected>' . esc_html($current . ' — ancien modèle, tarif non suivi') . '</option>';
    }
    foreach ($models as $value => $model) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($value),
            selected($current, $value, false),
            esc_html(sprintf('%s · %s $ / %s $ par million de tokens', $model['label'], number_format_i18n($model['input'], 2), number_format_i18n($model['output'], 2)))
        );
    }
    echo '</select>';

    if (!isset($models[$current])) {
        echo '<p class="optimizr-field__help">Ce modèle n\'est plus proposé. Les balises ALT utilisent ' . esc_html(HPT_AI_DEFAULT_MODEL) . ' ; choisissez un modèle de la liste pour suivre les coûts.</p>';
    }
}

function hpt_ai_usage_callback()
{
    $month = hpt_ai_usage_this_month();
    $all = (array) get_option('hpt_ai_usage', []);

    printf(
        '<p class="optimizr-ai-usage"><strong>%s</strong> ce mois-ci <span class="optimizr-muted">(%s)</span></p>',
        esc_html(hpt_ai_format_cost($month['cost'])),
        esc_html(sprintf('%d requête(s), %s tokens envoyés, %s reçus', $month['requests'], number_format_i18n($month['input']), number_format_i18n($month['output'])))
    );

    $previous = array_slice($all, 1, 5, true);
    if ($previous) {
        echo '<details class="optimizr-details"><summary>Mois précédents</summary><ul>';
        foreach ($previous as $key => $entry) {
            printf('<li>%s : %s · %d requête(s)</li>', esc_html(date_i18n('F Y', strtotime($key . '-01'))), esc_html(hpt_ai_format_cost($entry['cost'])), (int) $entry['requests']);
        }
        echo '</ul></details>';
    }

    echo '<p class="optimizr-field__help">Mesurée à partir des réponses d\'OpenAI pour les requêtes envoyées par ce site. La facture fait foi : <a href="https://platform.openai.com/usage" target="_blank" rel="noopener">tableau de bord OpenAI</a>.</p>';
}

/**
 * Comparatif : coût d'une image, d'une description, et de ce qui reste à traiter sur le site, pour chaque modèle.
 */
function hpt_ai_costs_callback()
{
    $models = array_keys(hpt_ai_models());
    $alt_ids = function_exists('hpt_ai_bulk_ids') ? hpt_ai_bulk_ids('alt') : [];
    $desc_ids = function_exists('hpt_ai_bulk_ids') && hpt_is_module_active('seo') ? hpt_ai_bulk_ids('descriptions') : [];

    // Unités chiffrées sur un échantillon représentatif du site, même si tout est déjà traité
    $alt_sample = $alt_ids ?: array_map('intval', get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 50, 'fields' => 'ids']));
    $desc_sample = $desc_ids ?: array_map('intval', get_posts(['post_type' => (array) get_option('hpt_sitemap_post_types', ['post', 'page']), 'numberposts' => 50, 'fields' => 'ids']));

    $alt = hpt_ai_estimate('alt', $alt_sample, $models);
    $desc = hpt_ai_estimate('descriptions', $desc_sample, $models);
    $current = hpt_ai_current_model();
    ?>
    <div class="optimizr-table-wrap">
        <table class="optimizr-table">
            <thead>
                <tr>
                    <th scope="col">Modèle</th>
                    <th scope="col">1 balise ALT</th>
                    <th scope="col">1 description</th>
                    <?php if ($alt_ids) : ?><th scope="col"><?php echo esc_html(sprintf('%d ALT manquantes', count($alt_ids))); ?></th><?php endif; ?>
                    <?php if ($desc_ids) : ?><th scope="col"><?php echo esc_html(sprintf('%d descriptions manquantes', count($desc_ids))); ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($models as $model) :
                    $a = $alt['per_model'][$model];
                    $d = $desc['per_model'][$model];
                    ?>
                    <tr<?php echo $model === $current ? ' class="is-current"' : ''; ?>>
                        <th scope="row"><?php echo esc_html($model); ?><?php echo $model === $current ? ' <span class="optimizr-badge">actuel</span>' : ''; ?></th>
                        <td><?php echo esc_html(hpt_ai_format_unit($a['unit'])); ?></td>
                        <td><?php echo esc_html(hpt_ai_format_unit($d['unit'])); ?></td>
                        <?php if ($alt_ids) : ?><td><?php echo esc_html(hpt_ai_format_cost($a['unit'] === null ? null : $a['unit'] * count($alt_ids))); ?></td><?php endif; ?>
                        <?php if ($desc_ids) : ?><td><?php echo esc_html(hpt_ai_format_cost($d['unit'] === null ? null : $d['unit'] * count($desc_ids))); ?></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="optimizr-field__help">Estimation prudente : un texte français est compté à 1 token pour 3,5 caractères, et chaque image selon ses dimensions réelles et la méthode de décompte du modèle. Changez de modèle ci-dessus puis enregistrez pour l'appliquer.</p>
    <?php
}

/** Coût unitaire lisible, avec plus de décimales : « 0,0004 $ ». */
function hpt_ai_format_unit($amount)
{
    if ($amount === null) {
        return 'tarif inconnu';
    }
    return number_format_i18n($amount, $amount < 0.01 ? 4 : 3) . ' $';
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
            'model' => $model ?: hpt_ai_current_model(),
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

    // Consommation réelle, facturée même si la réponse est inutilisable
    hpt_ai_record_usage($model ?: hpt_ai_current_model(), (array) ($body['usage'] ?? []));

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
 * Version de l'image envoyée à OpenAI : la première taille disponible parmi large, medium_large
 * et l'original, de 4 Mo au plus. Sert à la génération comme à l'estimation du coût.
 *
 * @return array ['path' => string (vide si aucune version locale utilisable), 'width' => int, 'height' => int]
 */
function hpt_ai_alt_image_source($attachment_id)
{
    $meta = wp_get_attachment_metadata($attachment_id);
    $original = get_attached_file($attachment_id);

    foreach (['large', 'medium_large', 'full'] as $size) {
        if ($size === 'full') {
            $path = $original;
            $width = (int) ($meta['width'] ?? 0);
            $height = (int) ($meta['height'] ?? 0);
        } elseif (!empty($meta['sizes'][$size]['file'])) {
            $path = path_join(dirname($original), $meta['sizes'][$size]['file']);
            $width = (int) $meta['sizes'][$size]['width'];
            $height = (int) $meta['sizes'][$size]['height'];
        } else {
            continue;
        }

        if ($path && is_readable($path) && filesize($path) <= 4 * MB_IN_BYTES) {
            return ['path' => $path, 'width' => $width, 'height' => $height];
        }
    }

    // Image envoyée par son adresse : dimensions de l'original
    return ['path' => '', 'width' => (int) ($meta['width'] ?? 1024), 'height' => (int) ($meta['height'] ?? 1024)];
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

    $source = hpt_ai_alt_image_source($attachment_id);
    if ($source['path']) {
        $mime = wp_check_filetype($source['path'])['type'] ?: 'image/jpeg';
        $image_url = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($source['path']));
    } else {
        $image_url = wp_get_attachment_url($attachment_id);
    }

    $site_context = trim(get_option('hpt_ai_context', ''));
    $model = hpt_ai_vision_model();

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
