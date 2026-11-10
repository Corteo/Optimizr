<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Conversion des images importées en WebP ou AVIF, par l'éditeur d'images de WordPress (GD ou Imagick) :
 * aucun service externe. Le fichier d'origine est conservé (« original_image » des métadonnées) ;
 * la taille « full » et toutes les tailles intermédiaires sont enregistrées au nouveau format.
 * Optimizr Pro s'appuie sur ces fonctions pour convertir la médiathèque existante.
 */

/** Formats de sortie proposés, par clé de réglage. */
function haptic_image_formats()
{
    return [
        'webp' => ['label' => 'WebP', 'mime' => 'image/webp'],
        'avif' => ['label' => 'AVIF', 'mime' => 'image/avif'],
    ];
}

/** Formats d'origine convertibles, par clé de réglage. GIF exclu : ses animations seraient perdues. */
function haptic_image_source_formats()
{
    return [
        'jpeg' => ['label' => 'JPEG', 'mime' => 'image/jpeg'],
        'png'  => ['label' => 'PNG', 'mime' => 'image/png'],
    ];
}

/** Le serveur sait-il écrire ce format ? (AVIF : WordPress 6.5+ et GD ou Imagick compilés avec AVIF) */
function haptic_image_format_supported($format)
{
    static $cache = [];
    $formats = haptic_image_formats();

    if (!isset($formats[$format])) {
        return false;
    }
    if (!isset($cache[$format])) {
        $cache[$format] = wp_image_editor_supports(['mime_type' => $formats[$format]['mime'], 'methods' => ['save']]);
    }

    return $cache[$format];
}

/**
 * Type MIME des images converties, ou '' si la conversion est désactivée ou impossible sur ce serveur.
 * AVIF choisi mais plus disponible (changement d'hébergement…) : WebP prend le relais.
 */
function haptic_image_target_mime()
{
    if (!get_option('haptic_image_convert')) {
        return '';
    }

    $format = get_option('haptic_image_format', 'webp');
    if (!haptic_image_format_supported($format)) {
        $format = 'webp';
    }
    if (!haptic_image_format_supported($format)) {
        return '';
    }

    return haptic_image_formats()[$format]['mime'];
}

/** Types MIME d'origine à convertir. */
function haptic_image_source_mimes()
{
    $selected = get_option('haptic_image_sources', array_keys(haptic_image_source_formats()));
    $mimes = [];

    foreach (haptic_image_source_formats() as $key => $source) {
        if (is_array($selected) && in_array($key, $selected, true)) {
            $mimes[] = $source['mime'];
        }
    }

    return $mimes;
}

function haptic_image_quality()
{
    $quality = (int) get_option('haptic_image_quality', 82);
    return $quality >= 1 && $quality <= 100 ? $quality : 82;
}

// Correspondance des formats utilisée par l'éditeur d'images : tailles intermédiaires, taille « full » (WordPress 6.7+)
add_filter('image_editor_output_format', 'haptic_image_output_format', 10, 3);
function haptic_image_output_format($formats, $filename = null, $mime_type = null)
{
    $target = haptic_image_target_mime();
    if ($target === '' || haptic_image_is_unconvertible_cmyk($filename, $mime_type)) {
        return $formats;
    }

    foreach (haptic_image_source_mimes() as $mime) {
        $formats[$mime] = $target;
    }
    // HEIC, que WordPress convertit en JPEG par défaut : directement au format choisi si le JPEG est converti
    foreach ($formats as $mime => $output) {
        if ($output === 'image/jpeg' && in_array('image/jpeg', haptic_image_source_mimes(), true)) {
            $formats[$mime] = $target;
        }
    }

    return $formats;
}

/*
 * Images CMJN (JPEG préparé pour l'impression) : WebP et AVIF n'acceptent que le RVB. Les éditeurs d'Optimizr
 * (functions/class-haptic-image-editors.php) remplacent ceux de WordPress tant que la conversion est active :
 * avec Imagick, l'image passe en sRGB avec son profil ICC ; avec GD, qui ignore les profils, elle reste en JPEG.
 */
add_filter('wp_image_editors', 'haptic_image_editors');
function haptic_image_editors($editors)
{
    // Pas haptic_image_target_mime() : elle interroge les éditeurs, donc ce filtre (récursion)
    if (!get_option('haptic_image_convert') || !class_exists('WP_Image_Editor_Imagick', false) || !class_exists('WP_Image_Editor_GD', false)) {
        return $editors;
    }

    require_once __DIR__ . '/class-haptic-image-editors.php';
    $replace = ['WP_Image_Editor_Imagick' => 'Haptic_Image_Editor_Imagick', 'WP_Image_Editor_GD' => 'Haptic_Image_Editor_GD'];

    return array_map(function ($editor) use ($replace) {
        return is_string($editor) && isset($replace[$editor]) ? $replace[$editor] : $editor;
    }, (array) $editors);
}

/**
 * Image CMJN que le serveur ne sait pas convertir fidèlement (GD, qui ignore les profils ICC) : elle garde son format.
 */
function haptic_image_is_unconvertible_cmyk($filename, $mime_type)
{
    static $cache = [];

    if ($mime_type !== 'image/jpeg' || !$filename || !is_string($filename) || !file_exists($filename)) {
        return false;
    }
    if (!isset($cache[$filename])) {
        $size = wp_getimagesize($filename);
        $cmyk = is_array($size) && isset($size['channels']) && (int) $size['channels'] === 4;
        $cache[$filename] = $cmyk && _wp_image_editor_choose(['path' => $filename, 'mime_type' => $mime_type]) !== 'Haptic_Image_Editor_Imagick';
    }

    return $cache[$filename];
}

// Qualité des images converties (n'agit pas sur les JPEG et PNG non convertis)
add_filter('wp_editor_set_quality', 'haptic_image_editor_quality', 10, 2);
function haptic_image_editor_quality($quality, $mime_type)
{
    $target = haptic_image_target_mime();
    return ($target !== '' && $mime_type === $target) ? haptic_image_quality() : $quality;
}

/*
 * Avant WordPress 6.7, seules les images redimensionnées (« -scaled ») ou pivotées passent par le format choisi :
 * une image plus petite que le seuil garde son fichier d'origine en taille « full ». On en enregistre ici une copie
 * convertie, comme le fait WordPress 6.7, l'original restant référencé dans « original_image ».
 */
add_filter('wp_generate_attachment_metadata', 'haptic_image_convert_full_size', 10, 3);
function haptic_image_convert_full_size($metadata, $attachment_id, $context = 'create')
{
    if ($context !== 'create' || version_compare(get_bloginfo('version'), '6.7', '>=')) {
        return $metadata;
    }

    return haptic_image_convert_attachment_file($metadata, $attachment_id);
}

/**
 * Remplace le fichier « full » d'une pièce jointe par une copie convertie, si elle n'en a pas encore.
 * Renvoie les métadonnées mises à jour (inchangées si rien n'est à faire ou en cas d'échec).
 */
function haptic_image_convert_attachment_file($metadata, $attachment_id)
{
    $target = haptic_image_target_mime();
    if ($target === '' || !is_array($metadata) || empty($metadata['file']) || !empty($metadata['original_image'])) {
        return $metadata;
    }

    $file = get_attached_file($attachment_id);
    $type = $file ? wp_check_filetype($file) : [];
    if (!$file || !file_exists($file) || empty($type['type']) || !in_array($type['type'], haptic_image_source_mimes(), true)
        || haptic_image_is_unconvertible_cmyk($file, $type['type'])) {
        return $metadata;
    }

    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return $metadata;
    }

    $extension = wp_get_default_extension_for_mime_type($target);
    $dir = pathinfo($file, PATHINFO_DIRNAME);
    // Même nom que l'original, comme WordPress (wp_unique_filename() ajouterait « -1 » à cause de l'original)
    $destination = trailingslashit($dir) . pathinfo($file, PATHINFO_FILENAME) . '.' . $extension;
    if (file_exists($destination)) {
        $destination = trailingslashit($dir) . wp_unique_filename($dir, wp_basename($destination));
    }
    $saved = $editor->save($destination, $target);

    if (is_wp_error($saved) || empty($saved['path'])) {
        return $metadata;
    }

    update_attached_file($attachment_id, $saved['path']);
    $metadata['width'] = $saved['width'];
    $metadata['height'] = $saved['height'];
    $metadata['file'] = _wp_relative_upload_path($saved['path']);
    $metadata['filesize'] = wp_filesize($saved['path']);
    $metadata['original_image'] = wp_basename($file);

    return $metadata;
}

// Réglages : carte « Formats d'image » de l'onglet Images, sous les images à la une
add_action('admin_init', 'haptic_image_register_settings');
function haptic_image_register_settings()
{
    $group = haptic_settings_group('images');
    $page = haptic_settings_page('images');

    register_setting($group, 'haptic_image_convert', ['sanitize_callback' => 'absint']);
    register_setting($group, 'haptic_image_format', ['sanitize_callback' => function ($value) {
        $value = sanitize_key($value);
        return array_key_exists($value, haptic_image_formats()) ? $value : 'webp';
    }]);
    register_setting($group, 'haptic_image_quality', ['sanitize_callback' => function ($value) {
        return max(1, min(100, (int) $value));
    }]);
    register_setting($group, 'haptic_image_sources', ['sanitize_callback' => function ($value) {
        $value = is_array($value) ? array_map('sanitize_key', $value) : [];
        return array_values(array_intersect($value, array_keys(haptic_image_source_formats())));
    }]);

    add_settings_section(
        'haptic-images-section',
        __('Image formats', 'optimizr'),
        'haptic_image_section_callback',
        $page,
        ['toggle' => 'haptic_image_convert', 'toggle_label' => __('Convert uploaded images', 'optimizr')]
    );

    add_settings_field('haptic-image-format', __('Format', 'optimizr'), 'haptic_image_format_callback', $page, 'haptic-images-section', [
        'description' => __('WebP is read by every current browser. AVIF is lighter still, but slower to create.', 'optimizr'),
    ]);
    add_settings_field('haptic-image-quality', __('Quality', 'optimizr'), 'haptic_image_quality_callback', $page, 'haptic-images-section', [
        'label_for'   => 'haptic_image_quality',
        'description' => __('From 1 to 100. Around 80 keeps images sharp while making them much lighter.', 'optimizr'),
    ]);
    add_settings_field('haptic-image-sources', __('Images to convert', 'optimizr'), 'haptic_image_sources_callback', $page, 'haptic-images-section', [
        'description' => __('GIF images are never converted, so they keep their animation.', 'optimizr'),
    ]);
}

function haptic_image_section_callback()
{
    echo '<p>' . esc_html__('New JPEG and PNG images are saved as WebP or AVIF, which are much lighter, by your server: no external service. The original file is kept in the media library folder.', 'optimizr') . '</p>';

    if (!haptic_image_format_supported('webp') && !haptic_image_format_supported('avif')) {
        echo '<p><strong>' . esc_html__('Your server cannot create WebP or AVIF images (GD or Imagick library without these formats). Ask your host to enable them: images are uploaded unchanged in the meantime.', 'optimizr') . '</strong></p>';
    }
}

function haptic_image_format_callback()
{
    $selected = get_option('haptic_image_format', 'webp');

    echo '<div class="optimizr-segmented" role="radiogroup" aria-label="' . esc_attr__('Format', 'optimizr') . '">';
    foreach (haptic_image_formats() as $key => $format) {
        $supported = haptic_image_format_supported($key);
        printf(
            '<label class="optimizr-segmented__option"><input type="radio" name="haptic_image_format" value="%s" %s %s><span>%s</span></label>',
            esc_attr($key),
            checked($selected, $key, false),
            disabled(!$supported, true, false),
            esc_html($format['label'])
        );
    }
    echo '</div>';

    if (!haptic_image_format_supported('avif')) {
        $message = version_compare(get_bloginfo('version'), '6.5', '<')
            ? __('AVIF requires WordPress 6.5 or later.', 'optimizr')
            : __('AVIF is not available on this server (GD or Imagick library without AVIF).', 'optimizr');
        if ($selected === 'avif') {
            $message .= ' ' . __('Images are converted to WebP instead.', 'optimizr');
        }
        echo '<p class="optimizr-field__help">' . esc_html($message) . '</p>';
    }
}

function haptic_image_quality_callback()
{
    printf(
        '<input type="number" class="optimizr-input-small" name="haptic_image_quality" id="haptic_image_quality" min="1" max="100" value="%s">',
        esc_attr(haptic_image_quality())
    );
}

function haptic_image_sources_callback()
{
    $selected = get_option('haptic_image_sources', array_keys(haptic_image_source_formats()));

    echo '<div class="optimizr-pills">';
    foreach (haptic_image_source_formats() as $key => $source) {
        $checked = is_array($selected) && in_array($key, $selected, true);
        echo '<label class="optimizr-pill"><input type="checkbox" name="haptic_image_sources[]" value="' . esc_attr($key) . '" ' . checked($checked, true, false) . ' /> ' . esc_html($source['label']) . '</label>';
    }
    echo '</div>';
}
