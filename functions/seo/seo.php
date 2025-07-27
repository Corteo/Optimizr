<?php

// Ajouter les paramètres SEO dans les options du plugin
function hpt_options_register_seo_settings()
{
    register_setting('hpt-options-settings-group', 'hpt_enable_seo');
    register_setting('hpt-options-settings-group', 'hpt_enable_opengraph');
    register_setting('hpt-seo-settings-group', 'hpt_seo_title_pattern');
    register_setting('hpt-seo-settings-group', 'hpt_seo_desc_pattern');
    register_setting('hpt-options-settings-group', 'hpt_sitemap_post_types', [
        'type' => 'array',
        'sanitize_callback' => 'hpt_sanitize_sitemap_post_types',
        'default' => ['post', 'page']
    ]);
    register_setting('hpt-options-settings-group', 'hpt_enable_robots');
    register_setting('hpt-options-settings-group', 'hpt_custom_robots_txt', ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field']);

    function hpt_sanitize_sitemap_post_types($input)
    {
        // S'assure que c’est un tableau et filtre les post types valides
        $post_types = get_post_types(['public' => true], 'names');
        $input = is_array($input) ? $input : [];
        return array_values(array_intersect($input, $post_types));
    }

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

    // Ajout du champ pour sélectionner les types de contenus du sitemap
    add_settings_field(
        'hpt_sitemap_post_types',
        'Types de contenus à inclure dans le Sitemap',
        'hpt_sitemap_post_types_callback',
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

    // Ajout du bouton de régénération du sitemap
    add_settings_field(
        'hpt_generate_sitemap_now',
        ' ',
        'hpt_generate_sitemap_button_callback',
        'hpt-options',
        'hpt_seo_settings_section'
    );

    add_settings_field(
        'hpt_enable_robots',
        'Activer la personnalisation du robots.txt',
        'hpt_enable_robots_callback',
        'hpt-options',
        'hpt_seo_settings_section'
    );

    add_settings_field(
        'hpt_custom_robots_txt',
        'Contenu personnalisé du fichier robots.txt',
        'hpt_custom_robots_txt_callback',
        'hpt-options',
        'hpt_seo_settings_section'
    );
    // Callback pour l'option robots.txt
    function hpt_enable_robots_callback()
    {
        $option = get_option('hpt_enable_robots', 0);
        echo '<input type="checkbox" name="hpt_enable_robots" value="1" ' . checked(1, $option, false) . '>';
    }

    function hpt_custom_robots_txt_callback()
    {
        $db_value = get_option('hpt_custom_robots_txt', '');
        $file_path = ABSPATH . 'robots.txt';

        // Enregistre en base le contenu actuel du fichier robots.txt, s’il existe
        if (file_exists($file_path)) {
            $robots_content = file_get_contents($file_path);
            update_option('hpt_custom_robots_txt', $robots_content);
        }

        if (file_exists($file_path)) {
            $value = file_get_contents($file_path);
        } elseif (!empty($db_value)) {
            $value = $db_value;
        } else {
            $value = "User-agent: *\nDisallow:\nSitemap: " . home_url('/sitemap.xml');
        }

        echo '<textarea name="hpt_custom_robots_txt" rows="10" class="large-text code" id="hpt_custom_robots_txt_field">' . esc_textarea($value) . '</textarea>';
        echo '<p><a href="' . esc_url(admin_url('admin-post.php?action=hpt_generate_robots_txt')) . '" class="button button-secondary" id="generate-robots-btn">Générer et sauvegarder robots.txt</a></p>';
        echo '<p class="description">Voici quelques exemples de directives :</p>';
        echo '<ul class="description" style="margin-left: 1em;">
            <li><code>User-agent: *</code> → s’applique à tous les robots</li>
            <li><code>Disallow: /wp-admin/</code> → empêche l’accès à l’admin</li>
            <li><code>Allow: /wp-content/uploads/</code> → autorise un sous-dossier</li>
            <li><code>Sitemap: https://exemple.com/sitemap.xml</code> → lien vers le plan de site</li>
        </ul>';

        // Prévisualisation en lecture seule du fichier robots.txt (affiche le contenu réel du fichier)
        echo '<h4>Aperçu actuel du fichier robots.txt</h4>';
        if (file_exists($file_path)) {
            $real_content = file_get_contents($file_path);
            echo '<pre style="background:#f6f6f6;padding:1em;border:1px solid #ccc;">' . esc_html($real_content) . '</pre>';
        } else {
            echo '<pre style="background:#f6f6f6;padding:1em;border:1px solid #ccc;">(Aucun fichier robots.txt trouvé à la racine du site)</pre>';
        }

        // Test d'accessibilité du robots.txt
        echo '<p class="description"><strong>Test d\'accessibilité :</strong> ';
        $robots_url = home_url('/robots.txt');
        $response = wp_remote_get($robots_url);
        if (is_wp_error($response)) {
            echo '<span style="color:red;">Erreur d\'accès</span>';
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                echo '<a href="' . esc_url($robots_url) . '" target="_blank" style="color:green;">✅ Accessible</a>';
            } else {
                echo '<span style="color:orange;">⚠️ Réponse HTTP : ' . esc_html($code) . '</span>';
            }
        }
        echo '</p>';
    }
}
// Callback pour le champ de sélection des types de contenus du sitemap
function hpt_sitemap_post_types_callback()
{
    $selected = get_option('hpt_sitemap_post_types', ['post', 'page']);
    $post_types = get_post_types(['public' => true], 'objects');
    foreach ($post_types as $pt) {
        echo '<label><input type="checkbox" name="hpt_sitemap_post_types[]" value="' . esc_attr($pt->name) . '" ' . checked(in_array($pt->name, $selected), true, false) . '> ' . esc_html($pt->labels->singular_name) . '</label><br>';
    }
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

// Callback pour bouton de régénération du sitemap
function hpt_generate_sitemap_button_callback()
{
    $url = admin_url('admin-post.php?action=hpt_generate_sitemap_now');
    $last = get_option('hpt_sitemap_last_generated');
    $filepath = ABSPATH . 'sitemap.xml';
    $file_exists = file_exists($filepath);
    $file_url = home_url('/sitemap.xml');

    // Vérifie l’accessibilité HTTP
    $http_ok = false;
    if ($file_exists) {
        $response = wp_remote_head($file_url);
        $http_ok = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    echo '<div style="display: flex; align-items: center; gap: 1em;">';
    echo '<a href="' . esc_url($url) . '" class="button button-secondary">Régénérer le sitemap</a>';

    if ($last) {
        echo '<span class="description">Dernière génération : ' . esc_html($last) . '</span>';
    }

    if (!$file_exists) {
        echo '<span class="description" style="color:red;">❌ Le fichier sitemap.xml est manquant !</span>';
    } elseif (!$http_ok) {
        echo '<span class="description" style="color:orange;">⚠️ <a href="' . esc_url($file_url) . '" target="_blank">Fichier présent, mais inaccessible en HTTP</a></span>';
    } else {
        echo '<span class="description" style="color:green;">✅ <a href="' . esc_url($file_url) . '" target="_blank">Voir le sitemap</a></span>';
    }

    // Ajout du lien vers le fichier de logs
    $log_path = plugin_dir_path(__FILE__) . 'sitemap.log';
    $log_url = plugin_dir_url(__FILE__) . 'sitemap.log';
    if (file_exists($log_path)) {
        echo '<span class="description"><a href="' . esc_url($log_url) . '" target="_blank">📄 Voir les logs</a></span>';
        // Ajout du bouton pour vider le fichier de log
        $clear_log_url = admin_url('admin-post.php?action=hpt_clear_sitemap_log');
        echo '<span class="description"><a href="' . esc_url($clear_log_url) . '" class="button button-small" style="margin-left: 1em;">🗑️ Vider les logs</a></span>';
    }
    echo '</div>';
}
// Déclenche la génération du sitemap et flush les permaliens lors de la modification des options pertinentes
function hpt_flush_rewrite_and_generate_sitemap()
{
    haptic_GPT_write_sitemap_to_file();
    flush_rewrite_rules();
}

add_action('update_option_hpt_seo_title_pattern', 'hpt_flush_rewrite_and_generate_sitemap');
add_action('update_option_hpt_seo_desc_pattern', 'hpt_flush_rewrite_and_generate_sitemap');
add_action('update_option_hpt_sitemap_post_types', 'hpt_flush_rewrite_and_generate_sitemap');

// Traitement de l'action
add_action('admin_post_hpt_generate_sitemap_now', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }
    require_once plugin_dir_path(__FILE__) . 'seo-sitemap.php';
    hpt_flush_rewrite_and_generate_sitemap();
    wp_redirect(admin_url('options-general.php?page=hpt-options&hpt_message=sitemap_regenerated'));
    exit;
});

// Action pour vider le fichier de log du sitemap
add_action('admin_post_hpt_clear_sitemap_log', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }
    $log_file = plugin_dir_path(__FILE__) . 'sitemap.log';
    if (file_exists($log_file)) {
        file_put_contents($log_file, '');
    }
    wp_redirect(admin_url('options-general.php?page=hpt-options&hpt_message=log_cleared'));
    exit;
});

function hpt_show_sitemap_notice()
{
    if (isset($_GET['hpt_message'])) {
        if ($_GET['hpt_message'] === 'sitemap_regenerated') {
            echo '<div class="notice notice-success is-dismissible"><p>Le fichier sitemap.xml a été régénéré avec succès.</p></div>';
        }
        if ($_GET['hpt_message'] === 'log_cleared') {
            echo '<div class="notice notice-success is-dismissible"><p>Le fichier de log a été vidé.</p></div>';
        }
    }
}
add_action('admin_notices', 'hpt_show_sitemap_notice');


function hpt_seo_title_pattern_callback()
{
    $value = get_option('hpt_seo_title_pattern', '{titre_page} - {nom_site}');

    // Tableau des variables disponibles : code => label lisible
    $variables = [
        '{titre_page}'       => 'Titre de la page',
        '{nom_site}'         => 'Nom du site',
        '{slogan}'           => 'Slogan',
        '{categorie}'        => 'Catégorie',
        '{resume}'           => 'Résumé',
        '{auteur}'           => 'Auteur',
        '{type_contenu}'     => 'Type de contenu',
        '{date_publication}' => 'Date de publication',
    ];
?>
    <!-- Badges variables à glisser‑déposer -->
    <div class="hpt-seo-variables mb-2">
        <?php foreach ($variables as $code => $label) : ?>
            <span class="badge bg-secondary me-1 hpt-draggable"
                draggable="true"
                data-seo-variable="<?php echo esc_attr($code); ?>">
                <?php echo esc_html($label); ?>
            </span>
        <?php endforeach; ?>
    </div>

    <!-- Zone éditable -->
    <div id="hpt_seo_title_pattern_editable"
        class="form-control"
        contenteditable="true"
        data-hidden-input="hpt_seo_title_pattern"
        style="min-height:40px;overflow:auto;"><?php echo esc_html($value); ?></div>

    <!-- Champ caché réellement enregistré -->
    <input type="hidden"
        id="hpt_seo_title_pattern"
        name="hpt_seo_title_pattern"
        value="<?php echo esc_attr($value); ?>" />

    <p class="description">
        Glissez‑déposez les variables ci‑dessus ou tapez « { » pour les insérer.<br>
        Variables disponibles : {titre_page}, {nom_site}, {slogan}, {categorie},
        {resume}, {auteur}, {type_contenu}, {date_publication}.
    </p>
<?php
}

function hpt_seo_desc_pattern_callback()
{
    $value = get_option('hpt_seo_desc_pattern', '{resume} - {nom_site}');

    $variables = [
        '{titre_page}'       => 'Titre de la page',
        '{nom_site}'         => 'Nom du site',
        '{slogan}'           => 'Slogan',
        '{categorie}'        => 'Catégorie',
        '{resume}'           => 'Résumé',
        '{auteur}'           => 'Auteur',
        '{type_contenu}'     => 'Type de contenu',
        '{date_publication}' => 'Date de publication',
    ];
?>
    <div class="hpt-seo-variables mb-2">
        <?php foreach ($variables as $code => $label) : ?>
            <span class="badge bg-secondary me-1 hpt-draggable"
                draggable="true"
                data-seo-variable="<?php echo esc_attr($code); ?>">
                <?php echo esc_html($label); ?>
            </span>
        <?php endforeach; ?>
    </div>

    <div id="hpt_seo_desc_pattern_editable"
        class="form-control"
        contenteditable="true"
        data-hidden-input="hpt_seo_desc_pattern"
        style="min-height:40px;overflow:auto;"><?php echo esc_html($value); ?></div>

    <input type="hidden"
        id="hpt_seo_desc_pattern"
        name="hpt_seo_desc_pattern"
        value="<?php echo esc_attr($value); ?>" />

    <p class="description">
        Glissez‑déposez les variables ci‑dessus ou tapez « { » pour les insérer.<br>
        Variables disponibles : {titre_page}, {nom_site}, {slogan}, {categorie},
        {resume}, {auteur}, {type_contenu}, {date_publication}.
    </p>
<?php
}


require_once('seo-meta.php');
require_once('seo-sitemap.php');
require_once('seo-output.php');

// Filtre pour personnaliser le robots.txt
add_filter('robots_txt', function ($output, $public) {
    if (!get_option('hpt_enable_robots')) {
        return $output;
    }

    $custom = get_option('hpt_custom_robots_txt', '');
    return !empty($custom) ? $custom : $output;
}, 10, 2);


add_action('admin_post_hpt_generate_robots_txt', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }

    $content = get_option('hpt_custom_robots_txt', '');
    update_option('hpt_custom_robots_txt', $content);
    $file = ABSPATH . 'robots.txt';

    if (!empty($content)) {
        file_put_contents($file, $content);
    }

    wp_redirect(admin_url('options-general.php?page=hpt-options&hpt_message=robots_generated'));
    exit;
});

add_action('admin_notices', function () {
    if (isset($_GET['hpt_message']) && $_GET['hpt_message'] === 'robots_generated') {
        echo '<div class="notice notice-success is-dismissible"><p>Le fichier robots.txt a été généré avec succès.</p></div>';
    }
});
