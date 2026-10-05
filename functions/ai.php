<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'haptic_register_ai_settings');

function haptic_register_ai_settings()
{
    $group = haptic_settings_group('ai');
    $page  = haptic_settings_page('ai');
    $current = haptic_ai_provider();

    register_setting($group, 'haptic_ai_provider', [
        'sanitize_callback' => function ($value) {
            return isset(haptic_ai_providers()[$value]) ? $value : HAPTIC_AI_DEFAULT_PROVIDER;
        },
    ]);
    register_setting($group, 'haptic_ai_context', ['sanitize_callback' => 'sanitize_textarea_field']);
    register_setting($group, 'haptic_ai_alt_on_upload', ['sanitize_callback' => 'absint']);

    add_settings_section(
        'haptic_ai_settings_section',
        'Fournisseur d\'IA',
        'haptic_ai_section_callback',
        $page
    );

    add_settings_field('haptic_ai_provider', 'Fournisseur', 'haptic_ai_provider_callback', $page, 'haptic_ai_settings_section', ['label_for' => 'haptic_ai_provider']);

    // Un modèle et une clé par fournisseur : seuls ceux du fournisseur choisi sont affichés (js/optimizr-admin.js)
    foreach (haptic_ai_providers() as $slug => $provider) {
        register_setting($group, $provider['model_option'], ['sanitize_callback' => 'sanitize_text_field']);
        $key_option = $provider['key_option'];
        register_setting($group, $key_option, [
            'sanitize_callback' => function ($value) use ($key_option) {
                return haptic_ai_sanitize_api_key($value, $key_option);
            },
        ]);

        $class = 'haptic-ai-provider-row haptic-ai-provider-' . $slug . ($slug === $current ? '' : ' optimizr-field--hidden');

        add_settings_field($provider['model_option'], 'Modèle', 'haptic_ai_model_callback', $page, 'haptic_ai_settings_section', [
            'label_for'   => $provider['model_option'],
            'provider'    => $slug,
            'class'       => $class,
            'description' => 'Tous les modèles proposés savent décrire les images.',
        ]);
        add_settings_field($provider['key_option'], 'Clé API', 'haptic_ai_api_key_callback', $page, 'haptic_ai_settings_section', [
            'label_for'   => $provider['key_option'],
            'provider'    => $slug,
            'class'       => $class,
        ]);
    }

    add_settings_field('haptic_ai_alt_on_upload', 'ALT automatique à l\'import', function () {
        echo wp_kses(haptic_switch_input('haptic_ai_alt_on_upload', get_option('haptic_ai_alt_on_upload', 1), 'Générer la balise ALT à l\'import des images'), haptic_kses_ui());
    }, $page, 'haptic_ai_settings_section', [
        'description' => 'Génère la balise ALT de chaque image importée dans la médiathèque, en arrière-plan quelques secondes après l\'import (une requête facturée par image).',
    ]);

    add_settings_section(
        'haptic_ai_context_section',
        'Contexte du site',
        function () {
            echo '<p>Ajouté à chaque demande pour que les textes générés collent à votre activité.</p>';
        },
        $page
    );
    add_settings_field('haptic_ai_context', 'Description de l\'activité', 'haptic_ai_context_callback', $page, 'haptic_ai_context_section', [
        'label_for' => 'haptic_ai_context',
        'layout' => 'stacked',
    ]);
}

function haptic_ai_section_callback()
{
    echo '<p>Utilisé pour les meta descriptions (metabox SEO) et les balises ALT (médiathèque). Chaque fournisseur garde sa propre clé et son propre modèle.</p>';
}

function haptic_ai_provider_callback()
{
    $current = haptic_ai_provider();
    echo '<select name="haptic_ai_provider" id="haptic_ai_provider" data-ai-provider-select>';
    foreach (haptic_ai_providers() as $slug => $provider) {
        printf('<option value="%s" %s>%s</option>', esc_attr($slug), selected($current, $slug, false), esc_html($provider['label']));
    }
    echo '</select>';
    echo '<p class="optimizr-field__help" data-ai-provider-notice hidden>Enregistrez pour utiliser ce fournisseur : les estimations de coût se mettront à jour.</p>';
}

function haptic_ai_model_callback($args)
{
    $provider = $args['provider'];
    $config = haptic_ai_provider_config($provider);
    $current = haptic_ai_current_model($provider);

    echo '<select name="' . esc_attr($config['model_option']) . '" id="' . esc_attr($config['model_option']) . '">';
    // Ancien modèle enregistré (GPT-4, GPT-3.5…) : conservé tant qu'on n'en choisit pas un autre
    if (!isset($config['models'][$current])) {
        echo '<option value="' . esc_attr($current) . '" selected>' . esc_html($current . ' — retiré, remplacé par ' . $config['default']) . '</option>';
    }
    foreach ($config['models'] as $value => $model) {
        printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($current, $value, false), esc_html($model['label']));
    }
    echo '</select>';

    if (!isset($config['models'][$current])) {
        echo '<p class="optimizr-field__help">' . esc_html(sprintf('Ce modèle n\'est plus proposé par le fournisseur : %s est utilisé à la place. Choisissez un modèle de la liste et enregistrez.', $config['default'])) . '</p>';
    }
}

/**
 * Clé API enregistrée : le champ n'étant jamais prérempli, un champ laissé vide conserve la clé,
 * sauf si « Supprimer la clé enregistrée » est cochée. Une valeur non vide (formulaire, import des réglages) la remplace.
 */
function haptic_ai_sanitize_api_key($value, $option)
{
    $value = sanitize_text_field($value);
    if ($value !== '') {
        return $value;
    }

    // Rappel de sanitize_option() pendant l'enregistrement par options.php, qui a déjà vérifié le nonce du formulaire
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $delete = sanitize_text_field(wp_unslash($_POST['option_page'] ?? '')) === haptic_settings_group('ai')
        && in_array($option, array_map('sanitize_key', (array) wp_unslash($_POST['haptic_ai_key_delete'] ?? [])), true);
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    return $delete ? '' : (string) get_option($option, '');
}

function haptic_ai_api_key_callback($args)
{
    $config = haptic_ai_provider_config($args['provider']);
    $saved = trim((string) get_option($config['key_option'], ''));

    // Jamais de clé dans la page : seuls ses 4 derniers caractères sont rappelés (clé assez longue)
    printf(
        '<input type="password" name="%1$s" id="%1$s" value="" placeholder="%2$s" autocomplete="new-password" spellcheck="false" />',
        esc_attr($config['key_option']),
        esc_attr($saved !== '' ? 'Clé enregistrée : laissez vide pour la conserver' : 'Collez la clé de votre compte')
    );
    if ($saved !== '') {
        printf(
            '<p class="optimizr-field__help">%s</p>',
            esc_html(mb_strlen($saved) >= 12 ? sprintf('Clé enregistrée, se terminant par « %s ».', mb_substr($saved, -4)) : 'Clé enregistrée.')
        );
        printf(
            '<label class="optimizr-check"><input type="checkbox" name="haptic_ai_key_delete[]" value="%s"><span>Supprimer la clé enregistrée</span></label>',
            esc_attr($config['key_option'])
        );
    }
    printf(
        '<p class="optimizr-field__help">À créer sur <a href="%s" target="_blank" rel="noopener">%s</a>. Elle n\'est jamais affichée en clair.</p>',
        esc_url($config['key_url']),
        esc_html(wp_parse_url($config['key_url'], PHP_URL_HOST))
    );
}

function haptic_ai_context_callback()
{
    $context = get_option('haptic_ai_context', '');
    echo '<textarea name="haptic_ai_context" id="haptic_ai_context" rows="4" placeholder="Décrivez ici le contexte global de votre site (ex. : école de céramique, atelier photo, agence de voyage, etc.)">' . esc_textarea($context) . '</textarea>';
}

/* ---------- Détail des coûts (bouton « ? » du traitement en masse) ---------- */

/**
 * Tarifs, comparatif des modèles sur les données du site et consommation réelle.
 */
function haptic_ai_costs_panel()
{
    $links = [];
    foreach (haptic_ai_providers() as $provider) {
        $links[] = sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($provider['pricing_url']), esc_html($provider['label']));
    }
    printf(
        '<p class="optimizr-field__help">Tarifs relevés le %s sur les grilles officielles (%s), en dollars US hors taxes.</p>',
        esc_html(date_i18n('j F Y', strtotime(HAPTIC_AI_PRICING_DATE))),
        wp_kses_post(implode(', ', $links))
    );

    haptic_ai_costs_table();
    haptic_ai_usage_summary();
}

function haptic_ai_usage_summary()
{
    $month = haptic_ai_usage_this_month();
    $previous = array_slice((array) get_option('haptic_ai_usage', []), 1, 5, true);

    printf(
        '<p class="optimizr-ai-usage"><strong>Consommation réelle ce mois-ci : %s</strong> <span class="optimizr-muted">(%s)</span></p>',
        esc_html(haptic_ai_format_cost($month['cost'])),
        esc_html(sprintf('%d requête(s), %s tokens envoyés, %s reçus', $month['requests'], number_format_i18n($month['input']), number_format_i18n($month['output'])))
    );

    if ($previous) {
        echo '<ul class="optimizr-ai-usage__history">';
        foreach ($previous as $key => $entry) {
            printf('<li>%s : %s · %d requête(s)</li>', esc_html(date_i18n('F Y', strtotime($key . '-01'))), esc_html(haptic_ai_format_cost($entry['cost'])), (int) $entry['requests']);
        }
        echo '</ul>';
    }

    printf(
        '<p class="optimizr-field__help">Mesurée à partir des réponses des API pour les requêtes envoyées par ce site, tous fournisseurs confondus. La facture fait foi : <a href="%s" target="_blank" rel="noopener">tableau de bord %s</a>.</p>',
        esc_url(haptic_ai_provider_config()['usage_url']),
        esc_html(haptic_ai_provider_config()['label'])
    );
}

/**
 * Comparatif de tous les modèles de tous les fournisseurs : tarif, coût d'une image,
 * d'une description, et de ce qui reste à traiter sur le site.
 */
function haptic_ai_costs_table()
{
    $alt_ids = function_exists('haptic_ai_bulk_ids') ? haptic_ai_bulk_ids('alt') : [];
    $desc_ids = function_exists('haptic_ai_bulk_ids') && haptic_is_module_active('seo') ? haptic_ai_bulk_ids('descriptions') : [];

    // Unités chiffrées sur un échantillon représentatif du site, même si tout est déjà traité
    $alt_sample = $alt_ids ?: array_map('intval', get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 50, 'fields' => 'ids']));
    $desc_sample = $desc_ids ?: array_map('intval', get_posts(['post_type' => (array) get_option('haptic_sitemap_post_types', ['post', 'page']), 'numberposts' => 50, 'fields' => 'ids']));

    // Coût d'un élément pour chaque modèle (mis en cache, cf. haptic_ai_estimate_units())
    $alt = haptic_ai_estimate_units('alt', $alt_sample);
    $desc = haptic_ai_estimate_units('descriptions', $desc_sample);
    $current = haptic_ai_provider() . ':' . haptic_ai_request_model();
    $columns = 4 + ($alt_ids ? 1 : 0) + ($desc_ids ? 1 : 0);
    ?>
    <div class="optimizr-table-wrap">
        <table class="optimizr-table">
            <thead>
                <tr>
                    <th scope="col">Modèle</th>
                    <th scope="col">Tarif (1 M tokens)</th>
                    <th scope="col">1 balise ALT</th>
                    <th scope="col">1 description</th>
                    <?php if ($alt_ids) : ?><th scope="col"><?php echo esc_html(sprintf('%d ALT manquantes', count($alt_ids))); ?></th><?php endif; ?>
                    <?php if ($desc_ids) : ?><th scope="col"><?php echo esc_html(sprintf('%d descriptions manquantes', count($desc_ids))); ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (haptic_ai_providers() as $slug => $provider) : ?>
                    <tr class="optimizr-table__group"><th scope="rowgroup" colspan="<?php echo (int) $columns; ?>"><?php echo esc_html($provider['label']); ?></th></tr>
                    <?php foreach ($provider['models'] as $model => $config) :
                        $key = $slug . ':' . $model;
                        $a = $alt[$key] ?? null;
                        $d = $desc[$key] ?? null;
                        ?>
                        <tr<?php echo $key === $current ? ' class="is-current"' : ''; ?>>
                            <th scope="row"><?php echo esc_html($model); ?><?php echo $key === $current ? ' <span class="optimizr-badge">actuel</span>' : ''; ?></th>
                            <td>
                                <?php
                                $price = haptic_ai_model_price($config);
                                echo esc_html(sprintf('%s $ / %s $', number_format_i18n($price['input'], 2), number_format_i18n($price['output'], 2)));
                                foreach ((array) ($config['price_changes'] ?? []) as $date => $change) {
                                    if (current_time('Y-m-d') < $date) {
                                        printf('<br><span class="optimizr-muted">%s : %s $ / %s $</span>', esc_html(date_i18n('j M Y', strtotime($date))), esc_html(number_format_i18n($change['input'], 2)), esc_html(number_format_i18n($change['output'], 2)));
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(haptic_ai_format_unit($a)); ?></td>
                            <td><?php echo esc_html(haptic_ai_format_unit($d)); ?></td>
                            <?php if ($alt_ids) : ?><td><?php echo esc_html(haptic_ai_format_cost($a === null ? null : $a * count($alt_ids))); ?></td><?php endif; ?>
                            <?php if ($desc_ids) : ?><td><?php echo esc_html(haptic_ai_format_cost($d === null ? null : $d * count($desc_ids))); ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="optimizr-field__help">Tarif : entrée / sortie. Estimation prudente : un texte français est compté à 1 token pour 3,5 caractères, chaque image selon ses dimensions réelles et la méthode de décompte du modèle, et la réflexion des modèles qui en font est estimée pour une tâche courte. Le fournisseur et le modèle se changent dans « Fournisseur d'IA ».</p>
    <?php
}

/* ---------- Génération ---------- */

/**
 * Droit de lancer une génération (requête facturée) : publish_posts par défaut, soit les auteurs,
 * éditeurs et administrateurs. Modifiable par le filtre haptic_ai_capability.
 * S'ajoute au droit de modifier le contenu concerné.
 */
function haptic_ai_user_can()
{
    return current_user_can(apply_filters('haptic_ai_capability', 'publish_posts'));
}

add_action('wp_ajax_haptic_generate_ai_seo_description', 'haptic_generate_ai_seo_description');

/**
 * Meta description d'un contenu, générée à partir de son texte.
 *
 * @return string|WP_Error
 */
function haptic_ai_generate_description($post_id)
{
    $content = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes(get_post_field('post_content', $post_id)))));
    if ($content === '') {
        return new WP_Error('haptic_ai_no_content', 'Le contenu ne contient pas de texte.');
    }

    $site_context = trim(get_option('haptic_ai_context', ''));
    $prompt = 'Rédige une meta description optimisée SEO de 160 caractères maximum (espaces compris) pour la page « ' . get_the_title($post_id) . " », à partir du texte suivant. Réponds uniquement par la description.\n\n"
        . mb_substr($content, 0, 6000)
        . ($site_context ? "\n\nContexte du site : " . $site_context : '');

    $text = haptic_ai_chat('Tu es un assistant expert en rédaction SEO.', [
        ['type' => 'text', 'text' => $prompt],
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
 * Version de l'image envoyée à l'IA : la première taille disponible parmi large, medium_large
 * et l'original, de 4 Mo au plus. Sert à la génération comme à l'estimation du coût.
 *
 * @return array ['path' => string (vide si aucune version locale utilisable), 'width' => int, 'height' => int]
 */
function haptic_ai_alt_image_source($attachment_id)
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
 * fonctionne aussi en local et sur les sites protégés, que les serveurs des fournisseurs ne peuvent pas lire.
 *
 * @return string|WP_Error
 */
function haptic_ai_generate_alt($attachment_id)
{
    if (!wp_attachment_is_image($attachment_id)) {
        return new WP_Error('haptic_ai_not_image', 'Ce fichier n\'est pas une image.');
    }

    $source = haptic_ai_alt_image_source($attachment_id);
    $image = $source['path'] ? ['type' => 'image', 'path' => $source['path']] : ['type' => 'image', 'url' => wp_get_attachment_url($attachment_id)];
    $site_context = trim(get_option('haptic_ai_context', ''));

    return haptic_ai_chat('Tu es un assistant expert en accessibilité, SEO et description d’images.', [
        [
            'type' => 'text',
            'text' => 'Décris cette image de façon concise (125 caractères maximum) pour une balise ALT accessible et optimisée SEO. Réponds uniquement par le contenu de la balise.' . ($site_context ? "\n\nContexte du site : " . $site_context : ''),
        ],
        $image,
    ], 80, 0.6, haptic_ai_vision_model());
}

// Metabox SEO : bouton « Générer la description avec l'IA »
function haptic_generate_ai_seo_description()
{
    check_ajax_referer('haptic_ai_nonce', 'nonce');

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id || !haptic_ai_user_can() || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Permissions insuffisantes');
    }

    $desc = haptic_ai_generate_description($post_id);
    if (is_wp_error($desc)) {
        wp_send_json_error($desc->get_error_message());
    }

    wp_send_json_success(['description' => $desc]);
}

// Compatibilité : ancienne signature, renvoie une chaîne vide en cas d'erreur
function haptic_generate_alt_with_ai($title, $post_ID)
{
    $alt = haptic_ai_generate_alt($post_ID);
    return is_wp_error($alt) ? '' : $alt;
}

add_action('wp_ajax_haptic_generate_image_meta', 'haptic_generate_image_meta');
function haptic_generate_image_meta()
{
    check_ajax_referer('haptic_ai_nonce', 'nonce');

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) {
        wp_send_json_error('ID de fichier manquant');
    }

    if (!haptic_ai_user_can() || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Permissions insuffisantes');
    }

    $post = get_post($post_id);
    if (!$post || strpos($post->post_mime_type, 'image/') !== 0) {
        wp_send_json_error('Ce n’est pas une image valide');
    }

    $alt = haptic_ai_generate_alt($post_id);
    if (is_wp_error($alt)) {
        wp_send_json_error($alt->get_error_message());
    }

    update_post_meta($post_id, '_wp_attachment_image_alt', $alt);

    wp_send_json_success([
        'alt' => $alt,
    ]);
}
