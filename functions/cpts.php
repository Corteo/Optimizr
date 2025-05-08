<?php
// chemin: /functions/cpts.php

if (! defined('ABSPATH')) {
    exit; // Interdire l'accès direct
}

/**
 * Enregistre le CPT prédéfini "Évènements" si le toggle est activé dans les réglages.
 */
function haptic_GPT_register_predefined_cpts()
{
    $options = get_option('hpt_options_settings');
    if (isset($options['enable_evenements']) && $options['enable_evenements']) {
        $labels = array(
            'name'                  => _x('Évènements', 'Post Type General Name', 'hpt-options'),
            'singular_name'         => _x('Évènement', 'Post Type Singular Name', 'hpt-options'),
            'menu_name'             => __('Évènements', 'hpt-options'),
            'name_admin_bar'        => __('Évènement', 'hpt-options'),
            'add_new'               => __('Ajouter nouveau', 'hpt-options'),
            'add_new_item'          => __('Ajouter un nouvel évènement', 'hpt-options'),
            'new_item'              => __('Nouvel évènement', 'hpt-options'),
            'edit_item'             => __('Modifier l\'évènement', 'hpt-options'),
            'view_item'             => __('Voir l\'évènement', 'hpt-options'),
            'all_items'             => __('Tous les évènements', 'hpt-options'),
            'search_items'          => __('Rechercher des évènements', 'hpt-options'),
            'not_found'             => __('Aucun évènement trouvé', 'hpt-options'),
            'not_found_in_trash'    => __('Aucun évènement dans la corbeille', 'hpt-options'),
        );
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'evenements'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => null,
            'show_in_rest'        => true, // Active Gutenberg (nouvel éditeur)
            'supports'            => array('title', 'editor', 'thumbnail', 'revisions'),
        );
        register_post_type('evenements', $args);
    }
}
add_action('init', 'haptic_GPT_register_predefined_cpts');

/**
 * Ajoute les champs personnalisés via ACF Pro pour le CPT "Évènements".
 * - Date de début (journée entière) obligatoire
 * - Date de fin
 * - Lieu de l'évènement
 *
 * Voir la documentation officielle d'ACF Pro pour plus de détails .
 */
function haptic_GPT_acf_evenements_fields()
{
    if (function_exists('acf_add_local_field_group')):
        acf_add_local_field_group(array(
            'key' => 'group_evenements',
            'title' => 'Champs supplémentaires pour Évènements',
            'fields' => array(
                array(
                    'key' => 'field_date_debut_evenement',
                    'label' => 'Date de début (journée entière)',
                    'name' => 'date_debut_evenement',
                    'type' => 'date_picker',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_date_fin_evenement',
                    'label' => 'Date de fin',
                    'name' => 'date_fin_evenement',
                    'type' => 'date_picker',
                    'required' => 0,
                ),
                array(
                    'key' => 'field_lieu_evenement',
                    'label' => 'Lieu de l\'évènement',
                    'name' => 'lieu_evenement',
                    'type' => 'text',
                    'required' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'evenements',
                    ),
                ),
            ),
        ));
    endif;
}
add_action('acf/init', 'haptic_GPT_acf_evenements_fields');

/**
 * Enregistre les CPT personnalisés créés via l'interface.
 */
function haptic_GPT_register_custom_cpts()
{
    $custom_cpts = get_option('hpt_custom_cpts', array());
    if (! empty($custom_cpts)) {
        foreach ($custom_cpts as $cpt) {
            if (! empty($cpt['name'])) {
                $slug = sanitize_title($cpt['name']);
                $labels = array(
                    'name'              => $cpt['name'],
                    'singular_name'     => $cpt['name'],
                    'menu_name'         => $cpt['name'],
                    'name_admin_bar'    => $cpt['name'],
                );
                $args = array(
                    'labels'              => $labels,
                    'public'              => true,
                    'publicly_queryable'  => true,
                    'show_ui'             => true,
                    'show_in_menu'        => true,
                    'query_var'           => true,
                    'rewrite'             => array('slug' => $slug),
                    'capability_type'     => 'post',
                    'has_archive'         => true,
                    'hierarchical'        => false,
                    'menu_position'       => null,
                    'menu_icon'           => ! empty($cpt['icon']) ? $cpt['icon'] : 'dashicons-admin-post',
                    'show_in_rest'        => true, // Active Gutenberg
                    'supports'            => array('title', 'editor', 'thumbnail', 'revisions'),
                );
                register_post_type($slug, $args);
            }
        }
    }
}
add_action('init', 'haptic_GPT_register_custom_cpts');

/**
 * Ajoute un nouvel onglet dans la page des réglages pour la gestion des CPTs.
 *
 * Cet onglet permet d'activer le CPT prédéfini "Évènements" et de créer de nouveaux CPTs via l'interface.
 */
function haptic_GPT_add_cpt_settings_fields()
{
    add_settings_section(
        'hpt_cpt_section',
        'Gestion des CPT personnalisés',
        'haptic_GPT_cpt_section_callback',
        'hpt-options'
    );

    // Toggle pour activer le CPT prédéfini "Évènements"
    add_settings_field(
        'enable_evenements',
        'Activer Évènements',
        'haptic_GPT_enable_evenements_callback',
        'hpt-options',
        'hpt_cpt_section'
    );

    // Interface pour la création de nouveaux CPTs (Nom et Icône)
    add_settings_field(
        'custom_cpts',
        'CPTs personnalisés',
        'haptic_GPT_custom_cpts_callback',
        'hpt-options',
        'hpt_cpt_section'
    );

    register_setting('hpt-options-settings-group', 'hpt_options_settings');
    register_setting('hpt-options-settings-group', 'hpt_custom_cpts');
}
add_action('admin_init', 'haptic_GPT_add_cpt_settings_fields');

/**
 * Callback de la section "Gestion des CPT personnalisés".
 */
function haptic_GPT_cpt_section_callback()
{
    echo '<p>Configurez et gérez vos Custom Post Types (CPT). Activez le CPT "Évènements" et créez-en de nouveaux via ce formulaire.</p>';
}

/**
 * Callback pour le toggle d'activation du CPT "Évènements".
 */
function haptic_GPT_enable_evenements_callback()
{
    $options = get_option('hpt_options_settings');
    $checked = (isset($options['enable_evenements']) && $options['enable_evenements']) ? 'checked' : '';
    echo '<input type="checkbox" name="hpt_options_settings[enable_evenements]" value="1" ' . $checked . ' />';
}

/**
 * Callback pour l'interface de création des CPTs personnalisés.
 */
function haptic_GPT_custom_cpts_callback()
{
    $custom_cpts = get_option('hpt_custom_cpts', array());
    echo '<div id="hpt_custom_cpts_container">';
    if (! empty($custom_cpts)) {
        foreach ($custom_cpts as $index => $cpt) {
            echo '<div class="hpt_custom_cpt">';
            echo '<input type="text" name="hpt_custom_cpts[' . $index . '][name]" placeholder="Nom du CPT" value="' . esc_attr($cpt['name']) . '" />';
            echo '<input type="text" name="hpt_custom_cpts[' . $index . '][icon]" placeholder="Icône (dashicon)" value="' . esc_attr($cpt['icon']) . '" />';
            echo '<button class="remove-cpt">Supprimer</button>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '<button id="add_custom_cpt" type="button">Ajouter un nouveau CPT</button>';
?>
    <script>
        document.getElementById('add_custom_cpt').addEventListener('click', function() {
            var container = document.getElementById('hpt_custom_cpts_container');
            var index = container.children.length;
            var div = document.createElement('div');
            div.className = 'hpt_custom_cpt';
            div.innerHTML = '<input type="text" name="hpt_custom_cpts[' + index + '][name]" placeholder="Nom du CPT" />' +
                '<input type="text" name="hpt_custom_cpts[' + index + '][icon]" placeholder="Icône (dashicon)" />' +
                '<button class="remove-cpt">Supprimer</button>';
            container.appendChild(div);
        });
        document.getElementById('hpt_custom_cpts_container').addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-cpt')) {
                e.preventDefault();
                e.target.parentElement.remove();
            }
        });
    </script>
<?php
}
