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
                    'instructions' => 'Sélectionnez la date de début de l\'évènement. Cette date est obligatoire et doit être au format "journée entière". (slug : date_debut_evenement)',
                    'name' => 'date_debut_evenement',
                    'type' => 'date_picker',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_date_fin_evenement',
                    'label' => 'Date de fin',
                    'instructions' => 'Sélectionnez la date de fin de l\'évènement. Cette date est facultative et doit être au format "journée entière". (slug : date_fin_evenement)',
                    'name' => 'date_fin_evenement',
                    'type' => 'date_picker',
                    'required' => 0,
                ),
                array(
                    'key' => 'field_lieu_evenement',
                    'label' => 'Lieu de l\'évènement',
                    'instructions' => 'Entrez le lieu de l\'évènement. (slug : lieu_evenement)',
                    'name' => 'lieu_evenement',
                    'type' => 'text',
                    'required' => 0,
                ),
                array(
                    'key' => 'field_horaires_evenement',
                    'label' => 'Horaires de l\'évènement',
                    'instructions' => 'Entrez les horaires de l\'évènement. (slug : horaires_evenement)',
                    'name' => 'horaires_evenement',
                    'type' => 'text',
                    'required' => 0,
                )
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
 * Types ajoutés, renommés ou retirés : les règles de réécriture sont recalculées à la requête suivante,
 * sinon les adresses des nouveaux types renvoient une page introuvable jusqu'au réenregistrement des permaliens.
 */
function haptic_GPT_reset_rewrite_rules()
{
    delete_option('rewrite_rules');
}
foreach (['hpt_custom_cpts', 'hpt_options_settings'] as $hpt_cpt_option) {
    add_action('update_option_' . $hpt_cpt_option, 'haptic_GPT_reset_rewrite_rules');
    add_action('add_option_' . $hpt_cpt_option, 'haptic_GPT_reset_rewrite_rules');
}
unset($hpt_cpt_option);

/**
 * Ajoute un nouvel onglet dans la page des réglages pour la gestion des CPTs.
 *
 * Cet onglet permet d'activer le CPT prédéfini "Évènements" et de créer de nouveaux CPTs via l'interface.
 */
function haptic_GPT_add_cpt_settings_fields()
{
    add_settings_section(
        'hpt_cpt_section',
        'Types de contenu personnalisés',
        'haptic_GPT_cpt_section_callback',
        hpt_settings_page('content')
    );

    // Toggle pour activer le CPT prédéfini "Évènements"
    add_settings_field(
        'enable_evenements',
        'Évènements',
        'haptic_GPT_enable_evenements_callback',
        hpt_settings_page('content'),
        'hpt_cpt_section',
        ['description' => 'Dates de début et de fin, lieu et horaires (champs ACF Pro).']
    );

    // Interface pour la création de nouveaux CPTs (Nom et Icône)
    add_settings_field(
        'custom_cpts',
        'Types personnalisés',
        'haptic_GPT_custom_cpts_callback',
        hpt_settings_page('content'),
        'hpt_cpt_section',
        ['description' => 'Nom affiché et icône Dashicons (ex. dashicons-portfolio).']
    );

    register_setting(hpt_settings_group('content'), 'hpt_options_settings');
    register_setting(hpt_settings_group('content'), 'hpt_custom_cpts', [
        'type' => 'array',
        'sanitize_callback' => 'haptic_GPT_sanitize_custom_cpts',
    ]);
}
add_action('admin_init', 'haptic_GPT_add_cpt_settings_fields');

/**
 * Nettoie la liste des CPT : lignes vides retirées, index renumérotés.
 */
function haptic_GPT_sanitize_custom_cpts($input)
{
    $clean = [];
    foreach ((array) $input as $cpt) {
        $name = isset($cpt['name']) ? sanitize_text_field($cpt['name']) : '';
        if ($name === '') {
            continue;
        }
        $clean[] = [
            'name' => $name,
            'icon' => isset($cpt['icon']) ? sanitize_html_class($cpt['icon']) : '',
        ];
    }
    return $clean;
}

/**
 * Callback de la section "Types de contenu personnalisés".
 */
function haptic_GPT_cpt_section_callback()
{
    echo '<p>Activez le type Évènements ou créez vos propres types de contenu.</p>';
}

/**
 * Callback pour le toggle d'activation du CPT "Évènements".
 */
function haptic_GPT_enable_evenements_callback()
{
    $options = get_option('hpt_options_settings');
    $enabled = isset($options['enable_evenements']) && $options['enable_evenements'];
    echo hpt_switch_input('hpt_options_settings[enable_evenements]', $enabled, 'Activer le type Évènements');
}

/**
 * Callback pour l'interface de création des CPTs personnalisés.
 */
function haptic_GPT_custom_cpts_callback()
{
    $custom_cpts = get_option('hpt_custom_cpts', array());
    echo '<div id="hpt_custom_cpts_container" class="optimizr-repeater">';
    if (! empty($custom_cpts)) {
        foreach ($custom_cpts as $index => $cpt) {
            echo '<div class="hpt_custom_cpt optimizr-repeater__row">';
            echo '<input type="text" name="hpt_custom_cpts[' . (int) $index . '][name]" placeholder="Nom du type" aria-label="Nom du type" value="' . esc_attr($cpt['name']) . '" />';
            echo '<input type="text" name="hpt_custom_cpts[' . (int) $index . '][icon]" placeholder="dashicons-admin-post" aria-label="Icône Dashicons" value="' . esc_attr($cpt['icon']) . '" />';
            echo '<button type="button" class="remove-cpt optimizr-btn optimizr-btn--ghost">Supprimer</button>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '<button id="add_custom_cpt" type="button" class="optimizr-btn optimizr-btn--secondary">+ Ajouter un type</button>';
?>
    <script>
        document.getElementById('add_custom_cpt').addEventListener('click', function() {
            var container = document.getElementById('hpt_custom_cpts_container');
            // Index unique : les lignes supprimées ne doivent pas provoquer de collision
            var index = Date.now();
            var div = document.createElement('div');
            div.className = 'hpt_custom_cpt optimizr-repeater__row';
            div.innerHTML = '<input type="text" name="hpt_custom_cpts[' + index + '][name]" placeholder="Nom du type" aria-label="Nom du type" />' +
                '<input type="text" name="hpt_custom_cpts[' + index + '][icon]" placeholder="dashicons-admin-post" aria-label="Icône Dashicons" />' +
                '<button type="button" class="remove-cpt optimizr-btn optimizr-btn--ghost">Supprimer</button>';
            container.appendChild(div);
            div.querySelector('input').focus();
            container.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.getElementById('hpt_custom_cpts_container').addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-cpt')) {
                e.preventDefault();
                var form = e.target.closest('form');
                e.target.parentElement.remove();
                if (form) form.dispatchEvent(new Event('change'));
            }
        });
    </script>
<?php
}
