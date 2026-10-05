<?php
// chemin: /functions/cpts.php

if (! defined('ABSPATH')) {
    exit; // Interdire l'accès direct
}

/**
 * Enregistre le CPT prédéfini "Évènements" si le toggle est activé dans les réglages.
 */
function haptic_register_predefined_cpts()
{
    $options = get_option('haptic_options_settings');
    if (isset($options['enable_evenements']) && $options['enable_evenements']) {
        $labels = array(
            'name'                  => _x('Events', 'Post Type General Name', 'optimizr'),
            'singular_name'         => _x('Event', 'Post Type Singular Name', 'optimizr'),
            'menu_name'             => __('Events', 'optimizr'),
            'name_admin_bar'        => __('Event', 'optimizr'),
            'add_new'               => __('Add New', 'optimizr'),
            'add_new_item'          => __('Add New Event', 'optimizr'),
            'new_item'              => __('New Event', 'optimizr'),
            'edit_item'             => __('Edit Event', 'optimizr'),
            'view_item'             => __('View Event', 'optimizr'),
            'all_items'             => __('All Events', 'optimizr'),
            'search_items'          => __('Search Events', 'optimizr'),
            'not_found'             => __('No events found', 'optimizr'),
            'not_found_in_trash'    => __('No events found in Trash', 'optimizr'),
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
add_action('init', 'haptic_register_predefined_cpts');

/**
 * Ajoute les champs personnalisés via ACF Pro pour le CPT "Évènements".
 * - Date de début (journée entière) obligatoire
 * - Date de fin
 * - Lieu de l'évènement
 *
 * Voir la documentation officielle d'ACF Pro pour plus de détails .
 */
function haptic_acf_evenements_fields()
{
    if (function_exists('acf_add_local_field_group')):
        acf_add_local_field_group(array(
            'key' => 'group_evenements',
            'title' => __('Additional fields for Events', 'optimizr'),
            'fields' => array(
                array(
                    'key' => 'field_date_debut_evenement',
                    'label' => __('Start date (all day)', 'optimizr'),
                    /* translators: %s: field name (slug) */
                    'instructions' => sprintf(__('Select the event start date. This date is required and must be in "all day" format. (slug: %s)', 'optimizr'), 'date_debut_evenement'),
                    'name' => 'date_debut_evenement',
                    'type' => 'date_picker',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_date_fin_evenement',
                    'label' => __('End date', 'optimizr'),
                    /* translators: %s: field name (slug) */
                    'instructions' => sprintf(__('Select the event end date. This date is optional and must be in "all day" format. (slug: %s)', 'optimizr'), 'date_fin_evenement'),
                    'name' => 'date_fin_evenement',
                    'type' => 'date_picker',
                    'required' => 0,
                ),
                array(
                    'key' => 'field_lieu_evenement',
                    'label' => __('Event location', 'optimizr'),
                    /* translators: %s: field name (slug) */
                    'instructions' => sprintf(__('Enter the event location. (slug: %s)', 'optimizr'), 'lieu_evenement'),
                    'name' => 'lieu_evenement',
                    'type' => 'text',
                    'required' => 0,
                ),
                array(
                    'key' => 'field_horaires_evenement',
                    'label' => __('Event times', 'optimizr'),
                    /* translators: %s: field name (slug) */
                    'instructions' => sprintf(__('Enter the event times. (slug: %s)', 'optimizr'), 'horaires_evenement'),
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
add_action('acf/init', 'haptic_acf_evenements_fields');

/**
 * Enregistre les CPT personnalisés créés via l'interface.
 */
function haptic_register_custom_cpts()
{
    $custom_cpts = get_option('haptic_custom_cpts', array());
    if (! empty($custom_cpts)) {
        $registered = array();
        foreach ($custom_cpts as $cpt) {
            // Chaîne attendue : une liste importée sans nettoyage (module désactivé à l'import) est lue ici avant d'être nettoyée
            if (! empty($cpt['name']) && is_string($cpt['name'])) {
                $slug = haptic_custom_cpt_key($cpt);
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
        $GLOBALS['haptic_custom_cpt_keys'] = $registered;
    }
}
add_action('init', 'haptic_register_custom_cpts');

/**
 * Identifiant (post type) d'un type personnalisé : fixé à sa création, il ne change pas quand le type est renommé.
 * Types enregistrés avant l'identifiant fixe : déduit du nom, comme alors.
 */
function haptic_custom_cpt_key($cpt)
{
    return ! empty($cpt['key']) && is_string($cpt['key']) ? sanitize_key($cpt['key']) : sanitize_title((string) ($cpt['name'] ?? ''));
}

/**
 * Identifiant d'un nouveau type, déduit de son nom : 20 caractères au plus (limite de register_post_type()).
 */
function haptic_new_cpt_key($name)
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
function haptic_reserved_cpt_keys()
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
function haptic_custom_cpt_key_error($key, $taken = array())
{
    // Comme register_post_type()
    $key = sanitize_key($key);

    if ($key === '') {
        return __('its name must contain at least one letter or digit', 'optimizr');
    }
    if (strlen($key) > 20) {
        /* translators: %s: post type key */
        return sprintf(__('its key “%s” is longer than 20 characters', 'optimizr'), $key);
    }
    if (in_array($key, $taken, true)) {
        /* translators: %s: post type key */
        return sprintf(__('its key “%s” is already used by another content type', 'optimizr'), $key);
    }
    // Type existant, enregistré par Optimizr : conservé même si son identifiant ne serait plus accepté (ses contenus en dépendent)
    $ours = isset($GLOBALS['haptic_custom_cpt_keys']) ? (array) $GLOBALS['haptic_custom_cpt_keys'] : array();
    if (in_array($key, $ours, true)) {
        return '';
    }
    if (strpos($key, 'wp_') === 0 || in_array($key, haptic_reserved_cpt_keys(), true)) {
        /* translators: %s: post type key */
        return sprintf(__('its key “%s” is reserved by WordPress', 'optimizr'), $key);
    }
    // Type Évènements d'Optimizr (même désactivé), taxonomies, types d'un autre plugin
    if ($key === 'evenements' || taxonomy_exists($key) || post_type_exists($key)) {
        /* translators: %s: post type key */
        return sprintf(__('its key “%s” is already used by another content type', 'optimizr'), $key);
    }

    return '';
}

/**
 * Types ajoutés, renommés ou retirés : les règles de réécriture sont recalculées à la requête suivante,
 * sinon les adresses des nouveaux types renvoient une page introuvable jusqu'au réenregistrement des permaliens.
 */
function haptic_reset_rewrite_rules()
{
    delete_option('rewrite_rules');
}
foreach (['haptic_custom_cpts', 'haptic_options_settings'] as $haptic_cpt_option) {
    add_action('update_option_' . $haptic_cpt_option, 'haptic_reset_rewrite_rules');
    add_action('add_option_' . $haptic_cpt_option, 'haptic_reset_rewrite_rules');
}
unset($haptic_cpt_option);

/**
 * Ajoute un nouvel onglet dans la page des réglages pour la gestion des CPTs.
 *
 * Cet onglet permet d'activer le CPT prédéfini "Évènements" et de créer de nouveaux CPTs via l'interface.
 */
function haptic_add_cpt_settings_fields()
{
    add_settings_section(
        'haptic_cpt_section',
        __('Custom content types', 'optimizr'),
        'haptic_cpt_section_callback',
        haptic_settings_page('content')
    );

    // Toggle pour activer le CPT prédéfini "Évènements"
    add_settings_field(
        'enable_evenements',
        __('Events', 'optimizr'),
        'haptic_enable_evenements_callback',
        haptic_settings_page('content'),
        'haptic_cpt_section',
        ['description' => __('Start and end dates, location and times (ACF Pro fields).', 'optimizr')]
    );

    // Interface pour la création de nouveaux CPTs (Nom et Icône)
    add_settings_field(
        'custom_cpts',
        __('Custom types', 'optimizr'),
        'haptic_custom_cpts_callback',
        haptic_settings_page('content'),
        'haptic_cpt_section',
        ['description' => __('Display name and Dashicons icon (e.g. dashicons-portfolio). Renaming a type changes neither its content nor its URLs.', 'optimizr')]
    );

    register_setting(haptic_settings_group('content'), 'haptic_options_settings', [
        'type' => 'array',
        'sanitize_callback' => 'haptic_sanitize_options_settings',
    ]);
    register_setting(haptic_settings_group('content'), 'haptic_custom_cpts', [
        'type' => 'array',
        'sanitize_callback' => 'haptic_sanitize_custom_cpts',
    ]);
}
add_action('admin_init', 'haptic_add_cpt_settings_fields');

/**
 * Nettoie les réglages des types prédéfinis : seul l'interrupteur du type Évènements est conservé.
 */
function haptic_sanitize_options_settings($input)
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
function haptic_sanitize_custom_cpts($input)
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
            $key = haptic_new_cpt_key($name);
        }

        $error = haptic_custom_cpt_key_error($key, $taken);
        if ($error !== '') {
            // Fonction de l'administration : absente si l'option est enregistrée depuis un autre contexte
            if (function_exists('add_settings_error')) {
                /* translators: 1: content type name, 2: reason it was refused */
                add_settings_error('haptic_custom_cpts', 'haptic_custom_cpts_' . md5($name), sprintf(__('The type “%1$s” was not saved: %2$s. Choose another name.', 'optimizr'), esc_html($name), $error));
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
function haptic_cpt_section_callback()
{
    echo '<p>' . esc_html__('Enable the Events type or create your own content types.', 'optimizr') . '</p>';
}

/**
 * Callback pour le toggle d'activation du CPT "Évènements".
 */
function haptic_enable_evenements_callback()
{
    $options = get_option('haptic_options_settings');
    $enabled = isset($options['enable_evenements']) && $options['enable_evenements'];
    echo wp_kses(haptic_switch_input('haptic_options_settings[enable_evenements]', $enabled, __('Enable the Events type', 'optimizr')), haptic_kses_ui());
}

/**
 * Callback pour l'interface de création des CPTs personnalisés.
 */
function haptic_custom_cpts_callback()
{
    $custom_cpts = get_option('haptic_custom_cpts', array());
    echo '<div id="haptic_custom_cpts_container" class="optimizr-repeater">';
    if (! empty($custom_cpts)) {
        foreach ($custom_cpts as $index => $cpt) {
            echo '<div class="haptic_custom_cpt optimizr-repeater__row">';
            echo '<input type="text" name="haptic_custom_cpts[' . (int) $index . '][name]" placeholder="' . esc_attr__('Type name', 'optimizr') . '" aria-label="' . esc_attr__('Type name', 'optimizr') . '" value="' . esc_attr($cpt['name']) . '" />';
            echo '<input type="text" name="haptic_custom_cpts[' . (int) $index . '][icon]" placeholder="dashicons-admin-post" aria-label="' . esc_attr__('Dashicons icon', 'optimizr') . '" value="' . esc_attr($cpt['icon']) . '" />';
            // Identifiant fixé à la création : conservé quand le nom change (les nouvelles lignes n'en ont pas)
            echo '<input type="hidden" name="haptic_custom_cpts[' . (int) $index . '][key]" value="' . esc_attr(haptic_custom_cpt_key($cpt)) . '" />';
            echo '<button type="button" class="remove-cpt optimizr-btn optimizr-btn--ghost">' . esc_html__('Delete', 'optimizr') . '</button>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '<button id="add_custom_cpt" type="button" class="optimizr-btn optimizr-btn--secondary">' . esc_html__('+ Add a type', 'optimizr') . '</button>';
?>
    <?php
    // Libellés traduits, déjà échappés pour être insérés dans le HTML des nouvelles lignes
    $haptic_cpt_l10n = [
        'name'   => esc_attr__('Type name', 'optimizr'),
        'icon'   => esc_attr__('Dashicons icon', 'optimizr'),
        'delete' => esc_html__('Delete', 'optimizr'),
    ];
    wp_print_inline_script_tag('var hapticCptL10n = ' . wp_json_encode($haptic_cpt_l10n) . ";\n" . <<<'JS'
        document.getElementById('add_custom_cpt').addEventListener('click', function() {
            var container = document.getElementById('haptic_custom_cpts_container');
            // Index unique : les lignes supprimées ne doivent pas provoquer de collision
            var index = Date.now();
            var div = document.createElement('div');
            div.className = 'haptic_custom_cpt optimizr-repeater__row';
            div.innerHTML = '<input type="text" name="haptic_custom_cpts[' + index + '][name]" placeholder="' + hapticCptL10n.name + '" aria-label="' + hapticCptL10n.name + '" />' +
                '<input type="text" name="haptic_custom_cpts[' + index + '][icon]" placeholder="dashicons-admin-post" aria-label="' + hapticCptL10n.icon + '" />' +
                '<button type="button" class="remove-cpt optimizr-btn optimizr-btn--ghost">' + hapticCptL10n.delete + '</button>';
            container.appendChild(div);
            div.querySelector('input').focus();
            container.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.getElementById('haptic_custom_cpts_container').addEventListener('click', function(e) {
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
