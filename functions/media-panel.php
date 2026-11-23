<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Panneau « Optimizr » de la médiathèque : dans la fiche d'une image (fenêtre de la médiathèque, modale média
 * de l'éditeur, écran « Modifier le média »), l'état de l'image pour Optimizr et ce qu'on peut en faire.
 * Rendu par le filtre attachment_fields_to_edit, comme les champs de WordPress : la fiche le recharge à chaque ouverture.
 *
 * Points d'extension (Optimizr Pro y ajoute la conversion de l'image et le texte alternatif par IA) :
 * - haptic_media_panel_items   : lignes d'état [clé => ['label', 'status' (ok|warn|off|info), 'text', 'link', 'link_label']] ;
 * - haptic_media_panel_actions : boutons (HTML) affichés sous les lignes ;
 * - haptic_media_panel_enabled : false masque le panneau.
 */

/** Libellé court d'un type MIME d'image : image/jpeg → JPEG, image/webp → WebP. */
function haptic_media_format_label($mime)
{
    $labels = ['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/gif' => 'GIF', 'image/webp' => 'WebP', 'image/avif' => 'AVIF', 'image/svg+xml' => 'SVG', 'image/heic' => 'HEIC'];
    if (isset($labels[$mime])) {
        return $labels[$mime];
    }

    $parts = explode('/', (string) $mime);
    return strtoupper(end($parts));
}

/**
 * État d'une image vis-à-vis des formats d'image d'Optimizr.
 *
 * @return array state : converted (convertie par Optimizr ou WordPress), modern (importée en WebP/AVIF),
 *               pending (à convertir), off (conversion désactivée), gif, cmyk, excluded (format exclu des réglages),
 *               module_off (module Images désactivé), other ; current/original : types MIME ; size/original_size : octets.
 */
function haptic_media_image_format($attachment_id)
{
    $file = get_attached_file($attachment_id);
    $type = $file ? wp_check_filetype($file) : [];
    $current = !empty($type['type']) ? $type['type'] : get_post_mime_type($attachment_id);
    $size = $file && file_exists($file) ? (int) wp_filesize($file) : 0;

    $original_path = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($attachment_id) : false;
    $original_type = $original_path ? wp_check_filetype($original_path) : [];
    $original = !empty($original_type['type']) ? $original_type['type'] : $current;

    $info = [
        'state'         => 'other',
        'current'       => $current,
        'original'      => $original,
        'size'          => $size,
        'original_size' => $original_path && $original_path !== $file && file_exists($original_path) ? (int) wp_filesize($original_path) : 0,
    ];
    $modern = ['image/webp', 'image/avif'];

    if (in_array($current, $modern, true)) {
        $info['state'] = $original !== $current && !in_array($original, $modern, true) ? 'converted' : 'modern';
    } elseif ($current === 'image/gif') {
        $info['state'] = 'gif';
    } elseif (!function_exists('haptic_image_target_mime')) {
        $info['state'] = 'module_off';
    } elseif (!in_array($current, haptic_image_source_mimes(), true)) {
        $info['state'] = in_array($current, wp_list_pluck(haptic_image_source_formats(), 'mime'), true) ? 'excluded' : 'other';
    } elseif (haptic_image_target_mime() === '') {
        $info['state'] = 'off';
    } elseif (haptic_image_is_unconvertible_cmyk($file, $current)) {
        $info['state'] = 'cmyk';
    } else {
        $info['state'] = 'pending';
    }

    return $info;
}

/** Lignes d'état du panneau pour une image. */
function haptic_media_panel_items($post)
{
    $items = [];
    $format = haptic_media_image_format($post->ID);
    $label = haptic_media_format_label($format['current']);
    $weight = $format['size'] ? size_format($format['size'], 1) : '';
    $current_text = $weight ? sprintf('%s · %s', $label, $weight) : $label;
    $settings_link = current_user_can('manage_options') ? haptic_admin_tab_url('images') : '';
    $target_label = function_exists('haptic_image_target_mime') && haptic_image_target_mime() !== '' ? haptic_media_format_label(haptic_image_target_mime()) : '';

    switch ($format['state']) {
        case 'converted':
            $text = $format['original_size']
                ? sprintf(
                    /* translators: 1: current format and weight (WebP · 84 KB), 2: original format, 3: original weight, 4: weight saved in percent */
                    __('%1$s, converted from %2$s (%3$s, %4$s%% lighter).', 'optimizr'),
                    $current_text,
                    haptic_media_format_label($format['original']),
                    size_format($format['original_size'], 1),
                    number_format_i18n(max(0, round(100 - $format['size'] * 100 / $format['original_size'])))
                )
                /* translators: 1: current format and weight (WebP · 84 KB), 2: original format */
                : sprintf(__('%1$s, converted from %2$s.', 'optimizr'), $current_text, haptic_media_format_label($format['original']));
            $item = ['status' => 'ok', 'text' => $text];
            break;
        case 'modern':
            /* translators: %s: format and weight (WebP · 84 KB) */
            $item = ['status' => 'ok', 'text' => sprintf(__('%s: already a light format.', 'optimizr'), $current_text)];
            break;
        case 'pending':
            $item = [
                'status' => 'warn',
                /* translators: 1: current format and weight (JPEG · 412 KB), 2: target format (WebP) */
                'text'   => sprintf(__('%1$s, not converted to %2$s yet: it was uploaded before the conversion was turned on.', 'optimizr'), $current_text, $target_label),
            ];
            break;
        case 'off':
            $item = [
                'status'     => 'off',
                /* translators: %s: format and weight (JPEG · 412 KB) */
                'text'       => sprintf(__('%s. Converting images to WebP or AVIF is turned off.', 'optimizr'), $current_text),
                'link'       => $settings_link,
                'link_label' => __('Turn it on', 'optimizr'),
            ];
            break;
        case 'gif':
            /* translators: %s: format and weight (GIF · 1.2 MB) */
            $item = ['status' => 'info', 'text' => sprintf(__('%s: GIF images are never converted, so they keep their animation.', 'optimizr'), $current_text)];
            break;
        case 'cmyk':
            /* translators: %s: format and weight (JPEG · 412 KB) */
            $item = ['status' => 'info', 'text' => sprintf(__('%s: print (CMYK) image, kept as JPEG because this server cannot convert its colours faithfully.', 'optimizr'), $current_text)];
            break;
        case 'excluded':
            $item = [
                'status'     => 'off',
                /* translators: %s: format and weight (PNG · 412 KB) */
                'text'       => sprintf(__('%s: this format is excluded from conversion in the settings.', 'optimizr'), $current_text),
                'link'       => $settings_link,
                'link_label' => __('Settings', 'optimizr'),
            ];
            break;
        default:
            $item = ['status' => 'info', 'text' => $current_text];
    }
    $items['format'] = ['label' => __('Format', 'optimizr')] + $item;

    $has_alt = trim((string) get_post_meta($post->ID, '_wp_attachment_image_alt', true)) !== '';
    $items['alt'] = [
        'label'  => __('Alternative text', 'optimizr'),
        'status' => $has_alt ? 'ok' : 'warn',
        'text'   => $has_alt
            ? __('Filled in.', 'optimizr')
            : __('Missing. It describes the image to search engines and screen readers.', 'optimizr'),
    ];

    return apply_filters('haptic_media_panel_items', $items, $post, $format);
}

/** HTML du panneau, ou '' si rien à afficher. */
function haptic_media_panel_html($post)
{
    $items = haptic_media_panel_items($post);
    if (!$items) {
        return '';
    }

    $icons = ['ok' => 'check', 'warn' => 'alert', 'off' => 'info', 'info' => 'info'];
    $kses = haptic_kses_ui();

    ob_start();
    ?>
    <div class="optimizr-media-panel" data-attachment-id="<?php echo esc_attr($post->ID); ?>">
        <ul class="optimizr-media-panel__items">
            <?php foreach ($items as $key => $item) :
                $status = isset($icons[$item['status'] ?? '']) ? $item['status'] : 'info'; ?>
                <li class="optimizr-media-panel__item is-<?php echo esc_attr($status); ?>" data-item="<?php echo esc_attr($key); ?>">
                    <span class="optimizr-media-panel__icon"><?php echo wp_kses(haptic_icon($icons[$status], 14), $kses); ?></span>
                    <span class="optimizr-media-panel__body">
                        <strong><?php echo esc_html($item['label'] ?? ''); ?></strong>
                        <span data-item-text><?php echo esc_html($item['text'] ?? ''); ?></span>
                        <?php if (!empty($item['link'])) : ?>
                            <a href="<?php echo esc_url($item['link']); ?>"><?php echo esc_html($item['link_label'] ?? __('Settings', 'optimizr')); ?></a>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $actions = array_filter((array) apply_filters('haptic_media_panel_actions', [], $post, $items));
        if ($actions) :
            ?>
            <div class="optimizr-media-panel__actions">
                <?php foreach ($actions as $action) {
                    echo wp_kses($action, haptic_media_panel_kses());
                } ?>
            </div>
            <p class="optimizr-media-panel__status" role="status" aria-live="polite" hidden></p>
        <?php elseif (!haptic_is_pro()) : ?>
            <p class="optimizr-media-panel__pro">
                <?php esc_html_e('With Optimizr Pro: convert this image in one click and write its alternative text with AI.', 'optimizr'); ?>
                <a href="<?php echo esc_url(HAPTIC_OPTIMIZR_PRO_INFO_URL); ?>" target="_blank" rel="noopener"><?php esc_html_e('Learn more', 'optimizr'); ?></a>
            </p>
        <?php endif; ?>
    </div>
    <?php
    return trim(ob_get_clean());
}

/** Balises autorisées dans les boutons d'action ajoutés par haptic_media_panel_actions. */
function haptic_media_panel_kses()
{
    $kses = haptic_kses_ui();
    $attrs = ['class' => true, 'id' => true, 'type' => true, 'disabled' => true, 'title' => true, 'href' => true, 'target' => true, 'rel' => true, 'aria-label' => true, 'data-*' => true];
    $kses['button'] = $attrs;
    $kses['a'] = $attrs;
    $kses['span'] = ($kses['span'] ?? []) + $attrs;

    return $kses;
}

add_filter('attachment_fields_to_edit', 'haptic_media_panel_field', 20, 2);
function haptic_media_panel_field($form_fields, $post)
{
    if (!$post || !wp_attachment_is_image($post->ID) || !current_user_can('upload_files')
        || !apply_filters('haptic_media_panel_enabled', true, $post)) {
        return $form_fields;
    }

    $html = haptic_media_panel_html($post);
    if ($html === '') {
        return $form_fields;
    }

    $form_fields['optimizr'] = [
        'label' => 'Optimizr',
        'input' => 'html',
        'html'  => $html,
    ];

    return $form_fields;
}

// Styles et script du panneau : médiathèque, écran « Modifier le média » et toute page qui charge la modale média
add_action('wp_enqueue_media', 'haptic_media_panel_enqueue');
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'upload.php' || ($hook === 'post.php' && get_post_type() === 'attachment')) {
        haptic_media_panel_enqueue();
    }
});
function haptic_media_panel_enqueue()
{
    if (!is_admin() || !current_user_can('upload_files')) {
        return;
    }

    $css = HAPTIC_OPTIMIZR_PATH . 'css/optimizr-media.css';
    $js  = HAPTIC_OPTIMIZR_PATH . 'js/optimizr-media.js';
    wp_enqueue_style('optimizr-media', HAPTIC_OPTIMIZR_URL . 'css/optimizr-media.css', [haptic_tokens_style()], filemtime($css));
    wp_enqueue_script('optimizr-media', HAPTIC_OPTIMIZR_URL . 'js/optimizr-media.js', ['jquery', 'wp-i18n'], filemtime($js), true);
    wp_set_script_translations('optimizr-media', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
}
