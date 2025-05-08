<?php

// Ajouter les paramètres SEO dans les options du plugin
function hpt_options_register_seo_settings()
{
    register_setting('hpt-options-settings-group', 'hpt_enable_seo');
    register_setting('hpt-options-settings-group', 'hpt_enable_opengraph');
    register_setting('hpt-seo-settings-group', 'hpt_seo_title_pattern');
    register_setting('hpt-seo-settings-group', 'hpt_seo_desc_pattern');

    add_settings_section(
        'hpt_seo_settings_section',
        'Optimisation SEO',
        'hpt_seo_section_callback',
        'hpt-options'
    );

    add_settings_field(
        'hpt_enable_seo',
        'Activer l’optimisation SEO',
        'hpt_enable_seo_callback',
        'hpt-options',
        'hpt_seo_settings_section'
    );

    add_settings_field(
        'hpt_enable_opengraph',
        'Activer la personnalisation des balises OpenGraph',
        'hpt_enable_opengraph_callback',
        'hpt-options',
        'hpt_seo_settings_section'
    );


    add_settings_field(
        'hpt_seo_title_pattern',
        'Motif du titre SEO',
        'hpt_seo_title_pattern_callback',
        'hpt-options',
        'hpt_seo_settings_section'
    );

    add_settings_field(
        'hpt_seo_desc_pattern',
        'Motif de la description SEO',
        'hpt_seo_desc_pattern_callback',
        'hpt-options',
        'hpt_seo_settings_section'
    );
}
add_action('admin_init', 'hpt_options_register_seo_settings');

// Callback pour la section SEO
function hpt_seo_section_callback()
{
    echo '<p>Activez ou désactivez l’optimisation SEO pour votre site.</p>';
}

// Callback pour l'option SEO
function hpt_enable_seo_callback()
{
    $option = get_option('hpt_enable_seo', 0);
    echo '<input type="checkbox" name="hpt_enable_seo" value="1" ' . checked(1, $option, false) . '>';
}

// Callback pour l'option OpenGraph
function hpt_enable_opengraph_callback()
{
    $option = get_option('hpt_enable_opengraph', 0);
    echo '<input type="checkbox" name="hpt_enable_opengraph" value="1" ' . checked(1, $option, false) . '>';
}



// Fonction d'affichage du champ de motif pour le titre
// function hpt_seo_title_pattern_callback()
// {
//     $value = get_option('hpt_seo_title_pattern', '{titre_page} - {nom_site}');
//     echo '<input type="text" id="hpt_seo_title_pattern" name="hpt_seo_title_pattern" value="' . esc_attr($value) . '" class="widefat">';
//     echo '<p class="description">Utilisez les variables : {titre_page}, {nom_site}, {slogan}, {categorie}, {resume}, {auteur}, {type_contenu}, {date_publication}.</p>';
// }

function hpt_seo_title_pattern_callback()
{
    $value = get_option('hpt_seo_title_pattern', '{titre_page} - {nom_site}');
?>
    <!-- Conteneur éditable pour l'affichage des badges -->
    <div
        id="hpt_seo_title_pattern_editable"
        class="form-control"
        contenteditable="true"
        data-hidden-input="hpt_seo_title_pattern"
        style="min-height: 40px; overflow:auto;"></div>

    <!-- Champ caché pour stocker la valeur réelle à enregistrer en base -->
    <input
        type="hidden"
        id="hpt_seo_title_pattern"
        name="hpt_seo_title_pattern"
        value="<?php echo esc_attr($value); ?>" />

    <p class="description">
        Utilisez les variables : {titre_page}, {nom_site}, {slogan}, {categorie}, {resume}, {auteur}, {type_contenu}, {date_publication}.
    </p>
<?php
}

// Fonction d'affichage du champ de motif pour la description
// function hpt_seo_desc_pattern_callback()
// {
//     $value = get_option('hpt_seo_desc_pattern', '{resume} - {nom_site}');
//     echo '<input type="text" id="hpt_seo_desc_pattern" name="hpt_seo_desc_pattern" value="' . esc_attr($value) . '" class="widefat">';
//     echo '<p class="description">Utilisez les variables : {titre_page}, {nom_site}, {slogan}, {categorie}, {resume}, {auteur}, {type_contenu}, {date_publication}.</p>';
// }

function hpt_seo_desc_pattern_callback()
{
    $value = get_option('hpt_seo_desc_pattern', '{resume} - {nom_site}');
?>
    <div
        id="hpt_seo_desc_pattern_editable"
        class="form-control"
        contenteditable="true"
        data-hidden-input="hpt_seo_desc_pattern"
        style="min-height: 40px; overflow:auto;"></div>

    <input
        type="hidden"
        id="hpt_seo_desc_pattern"
        name="hpt_seo_desc_pattern"
        value="<?php echo esc_attr($value); ?>" />

    <p class="description">
        Utilisez les variables : {titre_page}, {nom_site}, {slogan}, {categorie}, {resume}, {auteur}, {type_contenu}, {date_publication}.
    </p>
<?php
}

function hpt_enqueue_seo_admin_scripts($hook)
{
    if ($hook !== 'settings_page_hpt-options') {
        return;
    }

    $file_path = plugin_dir_path(__FILE__) . 'seo-dynamic-input.js';
    $script_url = plugin_dir_url(__FILE__) . 'seo-dynamic-input.js';

    if (file_exists($file_path)) {
        wp_enqueue_script('hpt-seo-dynamic-input', $script_url, [], filemtime($file_path), true);
    } else {
        wp_enqueue_script('hpt-seo-dynamic-input', $script_url, [], '1.0.0', true);
    }
}
add_action('admin_enqueue_scripts', 'hpt_enqueue_seo_admin_scripts');

require_once('seo-meta.php');
require_once('seo-output.php');
