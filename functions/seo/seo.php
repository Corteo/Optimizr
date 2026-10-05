<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Variables disponibles dans les motifs de titre et de description.
 */
function haptic_seo_pattern_variables()
{
    return [
        '{titre_page}'       => __('Page title', 'optimizr'),
        '{nom_site}'         => __('Site name', 'optimizr'),
        '{slogan}'           => __('Tagline', 'optimizr'),
        '{categorie}'        => __('Category', 'optimizr'),
        '{resume}'           => __('Excerpt', 'optimizr'),
        '{auteur}'           => __('Author', 'optimizr'),
        '{type_contenu}'     => __('Content type', 'optimizr'),
        '{date_publication}' => __('Publication date', 'optimizr'),
    ];
}

/**
 * Types de contenu qui ont la metabox SEO et servent d'exemple aux aperçus : tous les types publics.
 * Optimizr Pro les limite aux types choisis pour le sitemap.
 *
 * @return string[]
 */
function haptic_seo_post_types()
{
    $types = array_values(array_diff(get_post_types(['public' => true], 'names'), ['attachment']));
    return (array) apply_filters('haptic_seo_post_types', $types);
}

// Ajouter les paramètres SEO dans les options du plugin
function haptic_options_register_seo_settings()
{
    $group = haptic_settings_group('seo');
    $page  = haptic_settings_page('seo');

    register_setting($group, 'haptic_enable_seo', ['sanitize_callback' => 'absint']);
    register_setting($group, 'haptic_enable_opengraph', ['sanitize_callback' => 'absint']);
    register_setting($group, 'haptic_seo_title_pattern', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting($group, 'haptic_seo_desc_pattern', ['sanitize_callback' => 'sanitize_text_field']);

    // Carte « Balises meta »
    add_settings_section(
        'haptic_seo_meta_section',
        __('Meta tags', 'optimizr'),
        function () {
            echo '<p>' . esc_html__('Used when a piece of content has no custom title or description.', 'optimizr') . '</p>';
        },
        $page,
        ['toggle' => 'haptic_enable_seo', 'toggle_label' => __('Generate meta tags', 'optimizr')]
    );
    add_settings_field('haptic_seo_title_pattern', __('Title', 'optimizr'), 'haptic_seo_title_pattern_callback', $page, 'haptic_seo_meta_section', ['layout' => 'stacked']);
    add_settings_field('haptic_seo_desc_pattern', __('Description', 'optimizr'), 'haptic_seo_desc_pattern_callback', $page, 'haptic_seo_meta_section', ['layout' => 'stacked']);
    add_settings_field('haptic_enable_opengraph', __('OpenGraph tags', 'optimizr'), 'haptic_enable_opengraph_callback', $page, 'haptic_seo_meta_section', [
        'description' => __('Title, description and image when shared on social networks.', 'optimizr'),
    ]);
}
add_action('admin_init', 'haptic_options_register_seo_settings');

// Callback pour l'option OpenGraph
function haptic_enable_opengraph_callback()
{
    echo wp_kses(haptic_switch_input('haptic_enable_opengraph', get_option('haptic_enable_opengraph', 0), __('Generate OpenGraph tags', 'optimizr')), haptic_kses_ui());
}

function haptic_enqueue_seo_admin_scripts($hook)
{
    if ($hook !== HAPTIC_OPTIMIZR_ADMIN_HOOK || haptic_current_tab() !== 'seo') {
        return;
    }

    $file_path = plugin_dir_path(__FILE__) . 'seo-dynamic-input.js';
    wp_enqueue_script('haptic-seo-dynamic-input', plugin_dir_url(__FILE__) . 'seo-dynamic-input.js', ['wp-i18n'], filemtime($file_path), true);
    wp_set_script_translations('haptic-seo-dynamic-input', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
    wp_localize_script('haptic-seo-dynamic-input', 'hapticSeoVariables', haptic_seo_pattern_variables());
}
add_action('admin_enqueue_scripts', 'haptic_enqueue_seo_admin_scripts');

/**
 * Éditeur de motif : variables en étiquettes (js : seo-dynamic-input.js).
 */
function haptic_seo_pattern_editor($option, $default, $label)
{
    $value = get_option($option, $default);
    $editable_id = $option . '_editable';
    ?>
    <div id="<?php echo esc_attr($editable_id); ?>"
        class="optimizr-token-input"
        contenteditable="true"
        role="textbox"
        aria-multiline="false"
        spellcheck="false"
        aria-label="<?php echo esc_attr($label); ?>"
        data-hidden-input="<?php echo esc_attr($option); ?>"><?php echo esc_html($value); ?></div>

    <input type="hidden" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr($value); ?>" data-seo-pattern />

    <div class="optimizr-token-picker">
        <span class="optimizr-muted"><?php esc_html_e('Click or drag:', 'optimizr'); ?></span>
        <?php foreach (haptic_seo_pattern_variables() as $code => $var_label) : ?>
            <button type="button" class="optimizr-token-add" draggable="true"
                data-seo-variable="<?php echo esc_attr($code); ?>"
                data-target="<?php echo esc_attr($editable_id); ?>">+ <?php echo esc_html($var_label); ?></button>
        <?php endforeach; ?>
    </div>
    <?php
}

function haptic_seo_title_pattern_callback()
{
    haptic_seo_pattern_editor('haptic_seo_title_pattern', '{titre_page} - {nom_site}', __('Title pattern', 'optimizr'));
    echo '<p class="optimizr-field__help">' . esc_html__('Type your text freely and “{” to pick a variable. Ideally between 50 and 60 characters once the variables are replaced.', 'optimizr') . '</p>';
}

function haptic_seo_desc_pattern_callback()
{
    haptic_seo_pattern_editor('haptic_seo_desc_pattern', '{resume} - {nom_site}', __('Description pattern', 'optimizr'));
    echo '<p class="optimizr-field__help">' . esc_html__('Type your text freely and “{” to pick a variable. Ideally between 120 and 160 characters.', 'optimizr') . '</p>';
}

// Panneau latéral : aperçus Google et réseaux sociaux, mis à jour en direct par js/optimizr-admin.js
add_action('haptic_admin_aside_seo', function () {
    $post_types = haptic_seo_post_types();
    $latest = get_posts([
        'post_type' => $post_types ?: ['post'],
        'post_status' => 'publish',
        'numberposts' => 1,
    ]);

    if (empty($latest)) {
        return;
    }

    $post = $latest[0];
    $vars = haptic_seo_variables($post->ID);
    $title = haptic_generate_seo_content(get_option('haptic_seo_title_pattern', '{titre_page} - {nom_site}'), $post->ID);
    $desc = haptic_generate_seo_content(get_option('haptic_seo_desc_pattern', '{resume} - {nom_site}'), $post->ID);
    $url = get_permalink($post);
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $image = get_the_post_thumbnail_url($post, 'large');
    ?>
    <div class="optimizr-sticky" data-seo-preview data-seo-vars="<?php echo esc_attr(wp_json_encode($vars)); ?>">
        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <h2 class="optimizr-card__title"><?php esc_html_e('Google preview', 'optimizr'); ?></h2>
            </div>
            <div class="optimizr-card__body">
                <div class="optimizr-serp">
                    <div class="optimizr-serp__site">
                        <span class="optimizr-serp__favicon" aria-hidden="true"></span>
                        <span>
                            <span class="optimizr-serp__name"><?php echo esc_html(get_bloginfo('name')); ?></span>
                            <span class="optimizr-serp__url"><?php echo esc_html(untrailingslashit(preg_replace('#^https?://#', '', $url))); ?></span>
                        </span>
                    </div>
                    <span class="optimizr-serp__title" data-seo-preview-title><?php echo esc_html($title); ?></span>
                    <span class="optimizr-serp__desc" data-seo-preview-desc><?php echo esc_html($desc); ?></span>
                </div>
                <dl class="optimizr-dl optimizr-dl--compact">
                    <dt><?php esc_html_e('Title', 'optimizr'); ?></dt>
                    <dd data-seo-count-title></dd>
                    <dt><?php esc_html_e('Description', 'optimizr'); ?></dt>
                    <dd data-seo-count-desc></dd>
                </dl>
                <p class="optimizr-card__hint"><?php
                    /* translators: %s: title of the latest published content */
                    echo esc_html(sprintf(__('Example with “%s”, the latest published content.', 'optimizr'), get_the_title($post)));
                ?></p>
            </div>
        </section>

        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <h2 class="optimizr-card__title"><?php esc_html_e('Share preview', 'optimizr'); ?></h2>
            </div>
            <div class="optimizr-card__body">
                <div class="optimizr-share">
                    <div class="optimizr-share__image">
                        <?php if ($image) : ?>
                            <img src="<?php echo esc_url($image); ?>" alt="">
                        <?php else : ?>
                            <?php echo wp_kses(haptic_icon('image', 18), haptic_kses_ui()); ?><span><?php esc_html_e('No featured image', 'optimizr'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="optimizr-share__text">
                        <span class="optimizr-share__host"><?php echo esc_html($host); ?></span>
                        <span class="optimizr-share__title" data-seo-preview-title><?php echo esc_html($title); ?></span>
                        <span class="optimizr-share__desc" data-seo-preview-desc><?php echo esc_html($desc); ?></span>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php
});

require_once(__DIR__ . '/seo-meta.php');
require_once(__DIR__ . '/seo-output.php');
