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
        $registered = array();
        foreach ($custom_cpts as $cpt) {
            // Chaîne attendue : une liste importée sans nettoyage (module désactivé à l'import) est lue ici avant d'être nettoyée
            if (! empty($cpt['name']) && is_string($cpt['name'])) {
                $slug = haptic_GPT_custom_cpt_key($cpt);
                // Identifiant refusé par register_post_type() (vide, plus de 20 caractères) ou qui écraserait
                // un type déjà enregistré (post, page, Évènements, autre plugin, doublon de la liste)
                $key = sanitize_key($slug);
                if ($key === '' || strlen($key) > 20 || post_type_exists($key)) {
                    continue;
                }
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
                $registered[] = $key;
            }
        }
        // Types enregistrés par Optimizr : à ne pas confondre avec ceux d'un autre plugin à l'enregistrement des réglages
        $GLOBALS['hpt_custom_cpt_keys'] = $registered;
    }
}
add_action('init', 'haptic_GPT_register_custom_cpts');

/**
 * Identifiant (post type) d'un type personnalisé : fixé à sa création, il ne change pas quand le type est renommé.
 * Types enregistrés avant l'identifiant fixe : déduit du nom, comme alors.
 */
function haptic_GPT_custom_cpt_key($cpt)
{
    return ! empty($cpt['key']) && is_string($cpt['key']) ? sanitize_key($cpt['key']) : sanitize_title((string) ($cpt['name'] ?? ''));
}

/**
 * Identifiant d'un nouveau type, déduit de son nom : 20 caractères au plus (limite de register_post_type()).
 */
function haptic_GPT_new_cpt_key($name)
{
    $key = sanitize_key(sanitize_title($name));
    if (strlen($key) > 20) {
        // Coupé entre deux mots si possible
        $cut = strrpos(substr($key, 0, 21), '-');
        $key = substr($key, 0, $cut ?: 20);
    }
    return trim($key, '-_');
}

/**
 * Identifiants interdits : types de WordPress, termes réservés et variables de requête (un type du même nom
 * détournerait les adresses du site).
 */
function haptic_GPT_reserved_cpt_keys()
{
    // Valeurs par défaut de la classe : $wp contient aussi les variables ajoutées par les types déjà enregistrés
    $wp_defaults = new WP();

    return array_merge(
        array('post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'action', 'author', 'order', 'theme', 'type', 'types', 'status', 'terms', 'custom', 'nonce', 'debug'),
        $wp_defaults->public_query_vars,
        $wp_defaults->private_query_vars
    );
}

/**
 * Motif de refus d'un identifiant de type, ou chaîne vide s'il est utilisable.
 *
 * @param string $key   Identifiant (post type)
 * @param array  $taken Identifiants déjà retenus pour les types précédents de la liste
 */
function haptic_GPT_custom_cpt_key_error($key, $taken = array())
{
    // Comme register_post_type()
    $key = sanitize_key($key);

    if ($key === '') {
        return 'son nom doit contenir au moins une lettre ou un chiffre';
    }
    if (strlen($key) > 20) {
        return sprintf('son identifiant « %s » dépasse 20 caractères', $key);
    }
    if (in_array($key, $taken, true)) {
        return sprintf('son identifiant « %s » est déjà utilisé par un autre type de contenu', $key);
    }
    // Type existant, enregistré par Optimizr : conservé même si son identifiant ne serait plus accepté (ses contenus en dépendent)
    $ours = isset($GLOBALS['hpt_custom_cpt_keys']) ? (array) $GLOBALS['hpt_custom_cpt_keys'] : array();
    if (in_array($key, $ours, true)) {
        return '';
    }
    if (strpos($key, 'wp_') === 0 || in_array($key, haptic_GPT_reserved_cpt_keys(), true)) {
        return sprintf('son identifiant « %s » est réservé par WordPress', $key);
    }
    // Type Évènements d'Optimizr (même désactivé), taxonomies, types d'un autre plugin
    if ($key === 'evenements' || taxonomy_exists($key) || post_type_exists($key)) {
        return sprintf('son identifiant « %s » est déjà utilisé par un autre type de contenu', $key);
    }

    return '';
}

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
        ['description' => 'Nom affiché et icône Dashicons (ex. dashicons-portfolio). Renommer un type ne change ni ses contenus ni ses adresses.']
    );

    register_setting(hpt_settings_group('content'), 'hpt_options_settings', [
        'type' => 'array',
        'sanitize_callback' => 'haptic_GPT_sanitize_options_settings',
    ]);
    register_setting(hpt_settings_group('content'), 'hpt_custom_cpts', [
        'type' => 'array',
        'sanitize_callback' => 'haptic_GPT_sanitize_custom_cpts',
    ]);
}
add_action('admin_init', 'haptic_GPT_add_cpt_settings_fields');

/**
 * Nettoie les réglages des types prédéfinis : seul l'interrupteur du type Évènements est conservé.
 */
function haptic_GPT_sanitize_options_settings($input)
{
    $input = is_array($input) ? $input : [];
    return [
        'enable_evenements' => empty($input['enable_evenements']) ? 0 : 1,
    ];
}

/**
 * Nettoie la liste des CPT : lignes vides retirées, index renumérotés, identifiant fixé à la création.
 * Un nouveau type dont l'identifiant est inutilisable (réservé, déjà pris) n'est pas enregistré et un message l'explique.
 */
function haptic_GPT_sanitize_custom_cpts($input)
{
    $clean = [];
    $taken = [];
    foreach ((array) $input as $cpt) {
        $name = isset($cpt['name']) ? sanitize_text_field($cpt['name']) : '';
        if ($name === '') {
            continue;
        }

        // Identifiant transmis par le champ caché (type existant) ; nouveau type ou identifiant invalide : déduit du nom
        $key = isset($cpt['key']) && is_string($cpt['key']) ? $cpt['key'] : '';
        if ($key === '' || $key !== sanitize_key($key) || strlen($key) > 20) {
            $key = haptic_GPT_new_cpt_key($name);
        }

        $error = haptic_GPT_custom_cpt_key_error($key, $taken);
        if ($error !== '') {
            // Fonction de l'administration : absente si l'option est enregistrée depuis un autre contexte
            if (function_exists('add_settings_error')) {
                add_settings_error('hpt_custom_cpts', 'hpt_custom_cpts_' . md5($name), sprintf('Le type « %s » n\'a pas été enregistré : %s. Choisissez un autre nom.', esc_html($name), $error));
            }
            continue;
        }

        $taken[] = $key;
        $clean[] = [
            'name' => $name,
            'icon' => isset($cpt['icon']) ? sanitize_html_class($cpt['icon']) : '',
            'key'  => $key,
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
    echo wp_kses(hpt_switch_input('hpt_options_settings[enable_evenements]', $enabled, 'Activer le type Évènements'), hpt_kses_ui());
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
            // Identifiant fixé à la création : conservé quand le nom change (les nouvelles lignes n'en ont pas)
            echo '<input type="hidden" name="hpt_custom_cpts[' . (int) $index . '][key]" value="' . esc_attr(haptic_GPT_custom_cpt_key($cpt)) . '" />';
            echo '<button type="button" class="remove-cpt optimizr-btn optimizr-btn--ghost">Supprimer</button>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '<button id="add_custom_cpt" type="button" class="optimizr-btn optimizr-btn--secondary">+ Ajouter un type</button>';
?>
    <?php
    wp_print_inline_script_tag(<<<'JS'
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
    JS);
    ?>
<?php
}
