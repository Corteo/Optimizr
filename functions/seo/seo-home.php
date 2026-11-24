<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Carte « Page d'accueil » de l'onglet SEO (depuis la 7.22.0).
 *
 * Quand l'accueil affiche les derniers articles (Réglages › Lecture), aucune page ne porte sa boîte
 * « SEO & partage » : son titre, sa description et son image de partage se règlent ici.
 * Quand l'accueil est une page statique, la carte renvoie vers la boîte de cette page,
 * pour qu'il n'existe qu'un seul endroit où les régler.
 */

/**
 * Identifiant de la page statique servant d'accueil, ou 0 si l'accueil affiche les derniers articles.
 * Même règle que WordPress : « page statique » sans page choisie revient aux derniers articles.
 */
function haptic_seo_static_front_page_id()
{
    $page_id = (int) get_option('page_on_front');

    return get_option('show_on_front') === 'page' && $page_id && get_post($page_id) ? $page_id : 0;
}

/** Page affichée : l'accueil « derniers articles » (y compris ses pages suivantes /page/2/…). */
function haptic_seo_is_blog_front()
{
    return is_front_page() && is_home();
}

/**
 * Titre, description et image de l'accueil « derniers articles ».
 * Un champ vide laisse la valeur de WordPress : titre du site et slogan, pas de description, icône du site.
 *
 * @return array{title: string, description: string, image: string, custom_title: string, default_title: string, default_image: string}
 */
function haptic_seo_home_resolve()
{
    $name = wp_strip_all_tags(get_bloginfo('name'));
    $tagline = wp_strip_all_tags(get_bloginfo('description'));
    // Titre que WordPress donne à l'accueil sans réglage : « Nom du site – Slogan »
    $default_title = haptic_seo_clean_text($tagline !== '' ? $name . ' – ' . $tagline : $name);
    $default_image = (string) get_site_icon_url(512);

    $title = haptic_seo_clean_text(get_option('haptic_seo_home_title', ''));
    $image = (string) get_option('haptic_seo_home_image', '');

    return [
        'title' => $title !== '' ? $title : $default_title,
        'description' => haptic_seo_clean_text(get_option('haptic_seo_home_desc', '')),
        'image' => $image !== '' ? $image : $default_image,
        'custom_title' => $title,
        'default_title' => $default_title,
        'default_image' => $default_image,
    ];
}

/**
 * Carte « Page d'accueil », ajoutée sous « Balises meta » par haptic_options_register_seo_settings().
 * Les champs ne sont enregistrés que lorsque l'accueil affiche les derniers articles : avec une page
 * statique, le formulaire ne les contient pas et leurs valeurs restent intactes.
 */
function haptic_seo_home_register_settings($group, $page)
{
    $static = haptic_seo_static_front_page_id();

    add_settings_section('haptic_seo_home_section', __('Home page', 'optimizr'), function () use ($static) {
        echo '<p>' . esc_html($static
            ? __('Your home page is a page of the site: its SEO is set in its own “SEO & sharing” box.', 'optimizr')
            : __('Your home page shows your latest posts, so no page holds its “SEO & sharing” box. Set what Google and social networks show here.', 'optimizr')) . '</p>';
    }, $page);

    if ($static) {
        add_settings_field('haptic_seo_home_static', '', 'haptic_seo_home_static_callback', $page, 'haptic_seo_home_section', ['layout' => 'stacked', 'page_id' => $static]);
        return;
    }

    register_setting($group, 'haptic_seo_home_title', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting($group, 'haptic_seo_home_desc', ['sanitize_callback' => 'sanitize_textarea_field']);
    register_setting($group, 'haptic_seo_home_image', ['sanitize_callback' => 'esc_url_raw']);

    add_settings_field('haptic_seo_home_title', __('SEO title', 'optimizr'), 'haptic_seo_home_title_callback', $page, 'haptic_seo_home_section', ['layout' => 'stacked', 'label_for' => 'haptic_seo_home_title']);
    add_settings_field('haptic_seo_home_desc', __('SEO description', 'optimizr'), 'haptic_seo_home_desc_callback', $page, 'haptic_seo_home_section', ['layout' => 'stacked', 'label_for' => 'haptic_seo_home_desc']);
    add_settings_field('haptic_seo_home_image', __('Share image', 'optimizr'), 'haptic_seo_home_image_callback', $page, 'haptic_seo_home_section', ['layout' => 'stacked', 'label_for' => 'haptic_seo_home_image']);
}

/** Compteur de caractères sous un champ, mis à jour par js/optimizr-admin.js (initHomeSeo). */
function haptic_seo_home_counter($id, $min, $max, $hint)
{
    ?>
    <p class="optimizr-field__help optimizr-seo-home__count">
        <span data-seo-home-count="<?php echo esc_attr($id); ?>" data-min="<?php echo (int) $min; ?>" data-max="<?php echo (int) $max; ?>"></span>
        <span><?php echo esc_html($hint); ?></span>
    </p>
    <?php
}

function haptic_seo_home_title_callback()
{
    $seo = haptic_seo_home_resolve();
    ?>
    <input type="text" id="haptic_seo_home_title" name="haptic_seo_home_title" value="<?php echo esc_attr($seo['custom_title']); ?>" placeholder="<?php echo esc_attr($seo['default_title']); ?>" class="widefat">
    <?php
    haptic_seo_home_counter('haptic_seo_home_title', 50, 60, __('Ideally between 50 and 60 characters. Empty: the site title and tagline, as WordPress does.', 'optimizr'));
}

function haptic_seo_home_desc_callback()
{
    ?>
    <textarea id="haptic_seo_home_desc" name="haptic_seo_home_desc" rows="3" class="widefat" placeholder="<?php esc_attr_e('No description: Google picks a passage of the page.', 'optimizr'); ?>"><?php echo esc_textarea(get_option('haptic_seo_home_desc', '')); ?></textarea>
    <?php
    haptic_seo_home_counter('haptic_seo_home_desc', 120, 160, __('Ideally between 120 and 160 characters.', 'optimizr'));
}

function haptic_seo_home_image_callback()
{
    $seo = haptic_seo_home_resolve();
    $labels = [
        'title' => __('Share image', 'optimizr'),
        'button' => __('Use this image', 'optimizr'),
    ];
    ?>
    <div class="optimizr-seo-home__image" data-seo-home-media="<?php echo esc_attr(wp_json_encode($labels)); ?>">
        <span class="optimizr-seo-home__thumb" data-seo-home-thumb data-default="<?php echo esc_attr($seo['default_image']); ?>"<?php echo $seo['image'] ? '' : ' data-empty'; ?>><img<?php echo $seo['image'] ? ' src="' . esc_url($seo['image']) . '"' : ''; ?> alt=""></span>
        <input type="url" id="haptic_seo_home_image" name="haptic_seo_home_image" value="<?php echo esc_attr(get_option('haptic_seo_home_image', '')); ?>" placeholder="<?php echo esc_attr($seo['default_image'] ?: __('No image', 'optimizr')); ?>">
        <button type="button" class="optimizr-btn optimizr-btn--secondary" data-seo-home-pick><?php esc_html_e('Choose…', 'optimizr'); ?></button>
    </div>
    <p class="optimizr-field__help"><?php esc_html_e('Default: the site icon. Ideal size: 1200 × 630 px.', 'optimizr'); ?></p>
    <?php
}

/** Accueil en page statique : lien vers la boîte « SEO & partage » de cette page. */
function haptic_seo_home_static_callback($args)
{
    $page_id = (int) $args['page_id'];
    $edit = get_edit_post_link($page_id, 'raw');
    ?>
    <div class="optimizr-callout">
        <?php echo wp_kses(haptic_icon('home', 18), haptic_kses_ui()); ?>
        <div>
            <p><?php
                /* translators: %s: title of the page used as the home page */
                echo esc_html(sprintf(__('Home page: “%s”. Its title, description and share image are set in the “SEO & sharing” box below its content.', 'optimizr'), get_the_title($page_id) ?: __('(no title)', 'optimizr')));
            ?></p>
            <?php if ($edit && current_user_can('edit_post', $page_id)) : ?>
                <p><a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url($edit . '#haptic_seo_meta'); ?>"><?php esc_html_e('Edit the home page SEO', 'optimizr'); ?></a></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Médiathèque de WordPress pour le bouton « Choisir… » de l'image de partage de l'accueil
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === HAPTIC_OPTIMIZR_ADMIN_HOOK && haptic_current_tab() === 'seo' && !haptic_seo_static_front_page_id()) {
        wp_enqueue_media();
    }
});
