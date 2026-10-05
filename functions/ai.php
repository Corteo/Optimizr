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
        __('AI provider', 'optimizr'),
        'haptic_ai_section_callback',
        $page
    );

    add_settings_field('haptic_ai_provider', __('Provider', 'optimizr'), 'haptic_ai_provider_callback', $page, 'haptic_ai_settings_section', ['label_for' => 'haptic_ai_provider']);

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

        add_settings_field($provider['model_option'], __('Model', 'optimizr'), 'haptic_ai_model_callback', $page, 'haptic_ai_settings_section', [
            'label_for'   => $provider['model_option'],
            'provider'    => $slug,
            'class'       => $class,
            'description' => __('All the models offered can describe images.', 'optimizr'),
        ]);
        add_settings_field($provider['key_option'], __('API key', 'optimizr'), 'haptic_ai_api_key_callback', $page, 'haptic_ai_settings_section', [
            'label_for'   => $provider['key_option'],
            'provider'    => $slug,
            'class'       => $class,
        ]);
    }

    add_settings_field('haptic_ai_alt_on_upload', __('Automatic ALT on upload', 'optimizr'), function () {
        echo wp_kses(haptic_switch_input('haptic_ai_alt_on_upload', get_option('haptic_ai_alt_on_upload', 1), __('Generate the ALT text when images are uploaded', 'optimizr')), haptic_kses_ui());
    }, $page, 'haptic_ai_settings_section', [
        'description' => __('Generates the ALT text of every image uploaded to the media library, in the background a few seconds after the upload (one billed request per image).', 'optimizr'),
    ]);

    add_settings_section(
        'haptic_ai_context_section',
        __('Site context', 'optimizr'),
        function () {
            echo '<p>' . esc_html__('Added to every request so that the generated texts match your business.', 'optimizr') . '</p>';
        },
        $page
    );
    add_settings_field('haptic_ai_context', __('Business description', 'optimizr'), 'haptic_ai_context_callback', $page, 'haptic_ai_context_section', [
        'label_for' => 'haptic_ai_context',
        'layout' => 'stacked',
    ]);
}

function haptic_ai_section_callback()
{
    echo '<p>' . esc_html__('Used for meta descriptions (SEO metabox) and ALT text (media library). Each provider keeps its own key and model.', 'optimizr') . '</p>';
}

function haptic_ai_provider_callback()
{
    $current = haptic_ai_provider();
    echo '<select name="haptic_ai_provider" id="haptic_ai_provider" data-ai-provider-select>';
    foreach (haptic_ai_providers() as $slug => $provider) {
        printf('<option value="%s" %s>%s</option>', esc_attr($slug), selected($current, $slug, false), esc_html($provider['label']));
    }
    echo '</select>';
    echo '<p class="optimizr-field__help" data-ai-provider-notice hidden>' . esc_html__('Save to use this provider: cost estimates will update.', 'optimizr') . '</p>';
}

function haptic_ai_model_callback($args)
{
    $provider = $args['provider'];
    $config = haptic_ai_provider_config($provider);
    $current = haptic_ai_current_model($provider);

    echo '<select name="' . esc_attr($config['model_option']) . '" id="' . esc_attr($config['model_option']) . '">';
    // Ancien modèle enregistré (GPT-4, GPT-3.5…) : conservé tant qu'on n'en choisit pas un autre
    if (!isset($config['models'][$current])) {
        echo '<option value="' . esc_attr($current) . '" selected>' . esc_html(sprintf(
            /* translators: 1: retired AI model name, 2: replacement AI model name */
            __('%1$s — retired, replaced by %2$s', 'optimizr'),
            $current,
            $config['default']
        )) . '</option>';
    }
    foreach ($config['models'] as $value => $model) {
        printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($current, $value, false), esc_html($model['label']));
    }
    echo '</select>';

    if (!isset($config['models'][$current])) {
        /* translators: %s: replacement AI model name */
        echo '<p class="optimizr-field__help">' . esc_html(sprintf(__('This model is no longer offered by the provider: %s is used instead. Choose a model from the list and save.', 'optimizr'), $config['default'])) . '</p>';
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
        $saved !== '' ? esc_attr__('Key saved: leave empty to keep it', 'optimizr') : esc_attr__('Paste your account key', 'optimizr')
    );
    if ($saved !== '') {
        printf(
            '<p class="optimizr-field__help">%s</p>',
            /* translators: %s: last 4 characters of the saved API key */
            esc_html(mb_strlen($saved) >= 12 ? sprintf(__('Key saved, ending in “%s”.', 'optimizr'), mb_substr($saved, -4)) : __('Key saved.', 'optimizr'))
        );
        printf(
            '<label class="optimizr-check"><input type="checkbox" name="haptic_ai_key_delete[]" value="%1$s"><span>%2$s</span></label>',
            esc_attr($config['key_option']),
            esc_html__('Delete the saved key', 'optimizr')
        );
    }
    printf(
        '<p class="optimizr-field__help">%s</p>',
        wp_kses_post(sprintf(
            /* translators: %s: link to the provider's API keys page */
            __('Create it at %s. It is never displayed in clear text.', 'optimizr'),
            sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($config['key_url']), esc_html(wp_parse_url($config['key_url'], PHP_URL_HOST)))
        ))
    );
}

function haptic_ai_context_callback()
{
    $context = get_option('haptic_ai_context', '');
    echo '<textarea name="haptic_ai_context" id="haptic_ai_context" rows="4" placeholder="' . esc_attr__('Describe here the overall context of your site (e.g. ceramics school, photo studio, travel agency, etc.)', 'optimizr') . '">' . esc_textarea($context) . '</textarea>';
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
        '<p class="optimizr-field__help">%s</p>',
        wp_kses_post(sprintf(
            /* translators: 1: date the prices were recorded, 2: links to the providers' price lists */
            __('Prices recorded on %1$s from the official price lists (%2$s), in US dollars excluding tax.', 'optimizr'),
            esc_html(date_i18n('j F Y', strtotime(HAPTIC_AI_PRICING_DATE))),
            implode(', ', $links)
        ))
    );

    haptic_ai_costs_table();
    haptic_ai_usage_summary();
}

function haptic_ai_usage_summary()
{
    $month = haptic_ai_usage_this_month();
    $previous = array_slice((array) get_option('haptic_ai_usage', []), 1, 5, true);

    printf(
        '<p class="optimizr-ai-usage"><strong>%1$s</strong> <span class="optimizr-muted">(%2$s)</span></p>',
        /* translators: %s: cost in US dollars */
        esc_html(sprintf(__('Actual usage this month: %s', 'optimizr'), haptic_ai_format_cost($month['cost']))),
        esc_html(sprintf(
            /* translators: 1: number of requests, 2: number of tokens sent, 3: number of tokens received */
            _n('%1$d request, %2$s tokens sent, %3$s received', '%1$d requests, %2$s tokens sent, %3$s received', (int) $month['requests'], 'optimizr'),
            $month['requests'],
            number_format_i18n($month['input']),
            number_format_i18n($month['output'])
        ))
    );

    if ($previous) {
        echo '<ul class="optimizr-ai-usage__history">';
        foreach ($previous as $key => $entry) {
            printf('<li>%s</li>', esc_html(sprintf(
                /* translators: 1: month and year, 2: cost in US dollars, 3: number of requests */
                _n('%1$s: %2$s · %3$d request', '%1$s: %2$s · %3$d requests', (int) $entry['requests'], 'optimizr'),
                date_i18n('F Y', strtotime($key . '-01')),
                haptic_ai_format_cost($entry['cost']),
                (int) $entry['requests']
            )));
        }
        echo '</ul>';
    }

    printf(
        '<p class="optimizr-field__help">%s</p>',
        wp_kses_post(sprintf(
            /* translators: %s: link to the provider's usage dashboard */
            __('Measured from the API responses for the requests sent by this site, all providers combined. The invoice is authoritative: %s.', 'optimizr'),
            sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url(haptic_ai_provider_config()['usage_url']),
                /* translators: %s: AI provider name */
                esc_html(sprintf(__('%s dashboard', 'optimizr'), haptic_ai_provider_config()['label']))
            )
        ))
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
                    <th scope="col"><?php esc_html_e('Model', 'optimizr'); ?></th>
                    <th scope="col"><?php esc_html_e('Price (1M tokens)', 'optimizr'); ?></th>
                    <th scope="col"><?php esc_html_e('1 ALT text', 'optimizr'); ?></th>
                    <th scope="col"><?php esc_html_e('1 description', 'optimizr'); ?></th>
                    <?php if ($alt_ids) : ?><th scope="col"><?php
                        /* translators: %d: number of images without ALT text */
                        echo esc_html(sprintf(_n('%d missing ALT text', '%d missing ALT texts', count($alt_ids), 'optimizr'), count($alt_ids)));
                    ?></th><?php endif; ?>
                    <?php if ($desc_ids) : ?><th scope="col"><?php
                        /* translators: %d: number of content items without a meta description */
                        echo esc_html(sprintf(_n('%d missing description', '%d missing descriptions', count($desc_ids), 'optimizr'), count($desc_ids)));
                    ?></th><?php endif; ?>
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
                            <th scope="row"><?php echo esc_html($model); ?><?php echo $key === $current ? ' <span class="optimizr-badge">' . esc_html_x('current', 'AI model currently in use', 'optimizr') . '</span>' : ''; ?></th>
                            <td>
                                <?php
                                $price = haptic_ai_model_price($config);
                                /* translators: 1: input price in US dollars, 2: output price in US dollars */
                                echo esc_html(sprintf(__('$%1$s / $%2$s', 'optimizr'), number_format_i18n($price['input'], 2), number_format_i18n($price['output'], 2)));
                                foreach ((array) ($config['price_changes'] ?? []) as $date => $change) {
                                    if (current_time('Y-m-d') < $date) {
                                        printf('<br><span class="optimizr-muted">%s</span>', esc_html(sprintf(
                                            /* translators: 1: date of the price change, 2: new input price in US dollars, 3: new output price in US dollars */
                                            __('%1$s: $%2$s / $%3$s', 'optimizr'),
                                            date_i18n('j M Y', strtotime($date)),
                                            number_format_i18n($change['input'], 2),
                                            number_format_i18n($change['output'], 2)
                                        )));
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
    <p class="optimizr-field__help"><?php esc_html_e('Price: input / output. Conservative estimate: a text is counted as 1 token per 3.5 characters, each image according to its actual dimensions and the model’s counting method, and the reasoning of models that use it is estimated for a short task. The provider and the model can be changed in “AI provider”.', 'optimizr'); ?></p>
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
        return new WP_Error('haptic_ai_no_content', __('The content contains no text.', 'optimizr'));
    }

    $site_context = trim(get_option('haptic_ai_context', ''));
    /* translators: Instruction sent to the AI model, not shown to users. %s: page title */
    $prompt = sprintf(__('Write an SEO-optimized meta description of 160 characters maximum (spaces included) for the page “%s”, based on the following text. Reply with the description only.', 'optimizr'), get_the_title($post_id)) . "\n\n"
        . mb_substr($content, 0, 6000)
        /* translators: Instruction sent to the AI model, not shown to users. %s: site context entered in the settings */
        . ($site_context ? "\n\n" . sprintf(__('Site context: %s', 'optimizr'), $site_context) : '');

    /* translators: Instruction sent to the AI model (system prompt), not shown to users. */
    $text = haptic_ai_chat(__('You are an assistant specialized in SEO copywriting.', 'optimizr'), [
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
        return new WP_Error('haptic_ai_not_image', __('This file is not an image.', 'optimizr'));
    }

    $source = haptic_ai_alt_image_source($attachment_id);
    $image = $source['path'] ? ['type' => 'image', 'path' => $source['path']] : ['type' => 'image', 'url' => wp_get_attachment_url($attachment_id)];
    $site_context = trim(get_option('haptic_ai_context', ''));

    /* translators: Instruction sent to the AI model (system prompt), not shown to users. */
    return haptic_ai_chat(__('You are an assistant specialized in accessibility, SEO and image description.', 'optimizr'), [
        [
            'type' => 'text',
            /* translators: Instruction sent to the AI model, not shown to users. */
            'text' => __('Describe this image concisely (125 characters maximum) for an accessible, SEO-optimized ALT text. Reply with the content of the ALT text only.', 'optimizr')
                /* translators: Instruction sent to the AI model, not shown to users. %s: site context entered in the settings */
                . ($site_context ? "\n\n" . sprintf(__('Site context: %s', 'optimizr'), $site_context) : ''),
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
        wp_send_json_error(__('Insufficient permissions', 'optimizr'));
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
        wp_send_json_error(__('Missing file ID', 'optimizr'));
    }

    if (!haptic_ai_user_can() || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(__('Insufficient permissions', 'optimizr'));
    }

    $post = get_post($post_id);
    if (!$post || strpos($post->post_mime_type, 'image/') !== 0) {
        wp_send_json_error(__('This is not a valid image', 'optimizr'));
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
