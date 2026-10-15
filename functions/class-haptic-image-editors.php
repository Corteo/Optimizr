<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Éditeurs d'image de WordPress adaptés aux images CMJN (JPEG préparé pour l'impression), enregistrés par
 * haptic_image_editors() (functions/images.php) tant que la conversion est active.
 */

/**
 * Éditeur Imagick : conversion des images CMJN en sRGB à l'ouverture.
 *
 * WebP et AVIF n'acceptent que le RVB : ImageMagick y convertit une image CMJN (JPEG préparé pour l'impression)
 * sans tenir compte de son profil ICC, d'où des couleurs très différentes de l'original. On convertit ici l'image
 * avec son profil vers sRGB (assets/icc/sRGB-v2-magic.icc, CC0), comme le fait le navigateur pour afficher le JPEG.
 */
class Haptic_Image_Editor_Imagick extends WP_Image_Editor_Imagick
{
    public function load()
    {
        $loaded = parent::load();

        if ($loaded === true && in_array($this->mime_type, haptic_image_source_mimes(), true)) {
            $this->haptic_cmyk_to_srgb();
        }

        return $loaded;
    }

    protected function haptic_cmyk_to_srgb()
    {
        try {
            if ($this->image->getImageColorspace() !== Imagick::COLORSPACE_CMYK) {
                return;
            }

            $profiles = $this->image->getImageProfiles('icc', true);
            $srgb = file_get_contents(HAPTIC_OPTIMIZR_PATH . 'assets/icc/sRGB-v2-magic.icc'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du plugin

            if (!empty($profiles['icc']) && $srgb) {
                // Conversion colorimétrique (LCMS) : le profil sRGB remplace celui d'impression
                $this->image->profileImage('icc', $srgb);
            } else {
                $this->image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            }
        } catch (Exception $e) {
            // ImageMagick sans LCMS : conversion simple, sans profil
            try {
                $this->image->profileImage('icc', null);
                $this->image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            } catch (Exception $e) {
                return;
            }
        }
    }
}

/**
 * Éditeur GD : il ignore les profils ICC et ne peut pas convertir fidèlement une image CMJN.
 * Une telle image garde son format d'origine (JPEG) au lieu de passer en WebP ou AVIF.
 */
class Haptic_Image_Editor_GD extends WP_Image_Editor_GD
{
    protected $haptic_cmyk = false;

    public function load()
    {
        $loaded = parent::load();

        if ($loaded === true && $this->mime_type === 'image/jpeg') {
            $size = wp_getimagesize($this->file);
            $this->haptic_cmyk = is_array($size) && isset($size['channels']) && (int) $size['channels'] === 4;
        }

        return $loaded;
    }

    protected function get_output_format($filename = null, $mime_type = null)
    {
        if (!$this->haptic_cmyk) {
            return parent::get_output_format($filename, $mime_type);
        }

        remove_filter('image_editor_output_format', 'haptic_image_output_format', 10);
        $format = parent::get_output_format($filename, $mime_type);
        add_filter('image_editor_output_format', 'haptic_image_output_format', 10, 3);

        return $format;
    }
}
