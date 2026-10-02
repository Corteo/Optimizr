<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Variables disponibles dans les motifs de titre et de description.
 */
function hpt_seo_pattern_variables()
{
    return [
        '{titre_page}'       => 'Titre de la page',
        '{nom_site}'         => 'Nom du site',
        '{slogan}'           => 'Slogan',
        '{categorie}'        => 'Catégorie',
        '{resume}'           => 'Résumé',
        '{auteur}'           => 'Auteur',
        '{type_contenu}'     => 'Type de contenu',
        '{date_publication}' => 'Date de publication',
    ];
}

// S'assure que c'est un tableau et filtre les post types valides
function hpt_sanitize_sitemap_post_types($input)
{
    $post_types = get_post_types(['public' => true], 'names');
    $input = is_array($input) ? $input : [];
    return array_values(array_intersect($input, $post_types));
}

// Ajouter les paramètres SEO dans les options du plugin
function hpt_options_register_seo_settings()
{
    $group = hpt_settings_group('seo');
    $page  = hpt_settings_page('seo');

    register_setting($group, 'hpt_enable_seo', ['sanitize_callback' => 'absint']);
    register_setting($group, 'hpt_enable_opengraph', ['sanitize_callback' => 'absint']);
    register_setting($group, 'hpt_seo_title_pattern', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting($group, 'hpt_seo_desc_pattern', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting($group, 'hpt_sitemap_post_types', [
        'type' => 'array',
        'sanitize_callback' => 'hpt_sanitize_sitemap_post_types',
        'default' => ['post', 'page']
    ]);
    register_setting($group, 'hpt_enable_robots', ['sanitize_callback' => 'absint']);
    register_setting($group, 'hpt_custom_robots_txt', ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field']);

    // Carte « Balises meta »
    add_settings_section(
        'hpt_seo_meta_section',
        'Balises meta',
        function () {
            echo '<p>Utilisées quand un contenu n\'a pas de titre ou de description personnalisés.</p>';
        },
        $page,
        ['toggle' => 'hpt_enable_seo', 'toggle_label' => 'Générer les balises meta']
    );
    add_settings_field('hpt_seo_title_pattern', 'Titre', 'hpt_seo_title_pattern_callback', $page, 'hpt_seo_meta_section', ['layout' => 'stacked']);
    add_settings_field('hpt_seo_desc_pattern', 'Description', 'hpt_seo_desc_pattern_callback', $page, 'hpt_seo_meta_section', ['layout' => 'stacked']);
    add_settings_field('hpt_enable_opengraph', 'Balises OpenGraph', 'hpt_enable_opengraph_callback', $page, 'hpt_seo_meta_section', [
        'description' => 'Titre, description et image lors d\'un partage sur les réseaux sociaux.',
    ]);

    // Carte « Plan du site »
    add_settings_section(
        'hpt_seo_sitemap_section',
        'Plan du site',
        function () {
            echo '<p>sitemap.xml, régénéré une minute après chaque modification de contenu et une fois par jour. Les contenus « ne pas indexer », protégés par mot de passe et les pages privées de WooCommerce en sont exclus.</p>';
        },
        $page
    );
    add_settings_field('hpt_generate_sitemap_now', '', 'hpt_generate_sitemap_button_callback', $page, 'hpt_seo_sitemap_section', ['layout' => 'stacked']);
    add_settings_field('hpt_sitemap_post_types', 'Contenus inclus', 'hpt_sitemap_post_types_callback', $page, 'hpt_seo_sitemap_section', ['layout' => 'stacked']);

    // Carte « robots.txt »
    add_settings_section(
        'hpt_seo_robots_section',
        'robots.txt',
        function () {
            echo '<p>Remplace le fichier généré par WordPress.</p>';
        },
        $page,
        ['toggle' => 'hpt_enable_robots', 'toggle_label' => 'Personnaliser le robots.txt']
    );
    add_settings_field('hpt_custom_robots_txt', 'Contenu', 'hpt_custom_robots_txt_callback', $page, 'hpt_seo_robots_section', [
        'layout' => 'stacked',
        'label_for' => 'hpt_custom_robots_txt_field',
    ]);
}
add_action('admin_init', 'hpt_options_register_seo_settings');

// Callback pour l'option OpenGraph
function hpt_enable_opengraph_callback()
{
    echo hpt_switch_input('hpt_enable_opengraph', get_option('hpt_enable_opengraph', 0), 'Générer les balises OpenGraph');
}

function hpt_custom_robots_txt_callback()
{
    $db_value = get_option('hpt_custom_robots_txt', '');
    $file_path = ABSPATH . 'robots.txt';

    // La valeur enregistrée prime ; le fichier existant ne sert qu'à pré-remplir le champ
    if (!empty($db_value)) {
        $value = $db_value;
    } elseif (file_exists($file_path)) {
        $value = file_get_contents($file_path);
    } else {
        $value = "User-agent: *\nDisallow:\nSitemap: " . home_url('/sitemap.xml');
    }

    echo '<textarea name="hpt_custom_robots_txt" rows="8" class="optimizr-code" id="hpt_custom_robots_txt_field" spellcheck="false">' . esc_textarea($value) . '</textarea>';

    echo '<div class="optimizr-inline">';

    // Test d'accessibilité du robots.txt
    $robots_url = home_url('/robots.txt');
    $response = wp_remote_get($robots_url);
    if (is_wp_error($response)) {
        echo '<span class="optimizr-status optimizr-status--warn">Inaccessible</span>';
    } else {
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            echo '<a href="' . esc_url($robots_url) . '" target="_blank" rel="noopener" class="optimizr-status optimizr-status--ok">Accessible (HTTP 200)</a>';
        } else {
            echo '<span class="optimizr-status optimizr-status--warn">Réponse HTTP ' . esc_html($code) . '</span>';
        }
    }

    if (file_exists($file_path)) {
        echo '<span class="optimizr-muted">Un fichier robots.txt physique existe à la racine : il est prioritaire sur ce réglage.</span>';
    }

    echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=hpt_generate_robots_txt'), 'hpt_generate_robots_txt')) . '" class="optimizr-btn optimizr-btn--secondary optimizr-push">Écrire le fichier robots.txt</a>';
    echo '</div>';

    echo '<details class="optimizr-details"><summary>Exemples de directives</summary><ul>
        <li><code>User-agent: *</code> s\'applique à tous les robots</li>
        <li><code>Disallow: /wp-admin/</code> empêche l\'accès à l\'admin</li>
        <li><code>Allow: /wp-content/uploads/</code> autorise un sous-dossier</li>
        <li><code>Sitemap: ' . esc_html(home_url('/sitemap.xml')) . '</code> indique le plan du site</li>
    </ul></details>';
}

// Callback pour le champ de sélection des types de contenus du sitemap
function hpt_sitemap_post_types_callback()
{
    $selected = (array) get_option('hpt_sitemap_post_types', ['post', 'page']);
    $post_types = get_post_types(['public' => true], 'objects');

    echo '<div class="optimizr-pills">';
    foreach ($post_types as $pt) {
        echo '<label class="optimizr-pill"><input type="checkbox" name="hpt_sitemap_post_types[]" value="' . esc_attr($pt->name) . '" ' . checked(in_array($pt->name, $selected, true), true, false) . '> ' . esc_html($pt->labels->name) . '</label>';
    }
    echo '</div>';
}

function hpt_enqueue_seo_admin_scripts($hook)
{
    if ($hook !== HPT_OPTIMIZR_ADMIN_HOOK || hpt_current_tab() !== 'seo') {
        return;
    }

    $file_path = plugin_dir_path(__FILE__) . 'seo-dynamic-input.js';
    wp_enqueue_script('hpt-seo-dynamic-input', plugin_dir_url(__FILE__) . 'seo-dynamic-input.js', [], filemtime($file_path), true);
    wp_localize_script('hpt-seo-dynamic-input', 'hptSeoVariables', hpt_seo_pattern_variables());
}
add_action('admin_enqueue_scripts', 'hpt_enqueue_seo_admin_scripts');

// Callback pour l'état et la régénération du sitemap
function hpt_generate_sitemap_button_callback()
{
    $url = wp_nonce_url(admin_url('admin-post.php?action=hpt_generate_sitemap_now'), 'hpt_generate_sitemap_now');
    $error = get_option('hpt_sitemap_error');
    $history = (array) get_option('hpt_sitemap_history', []);
    $file_exists = file_exists(ABSPATH . 'sitemap.xml');
    $file_url = home_url('/sitemap.xml');

    // Vérifie l'accessibilité HTTP
    $http_ok = false;
    if ($file_exists) {
        $response = wp_remote_head($file_url);
        $http_ok = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    $details = '';
    if (!empty($history[0])) {
        $details = ' · ' . sprintf(_n('%d URL', '%d URL', $history[0]['count']), $history[0]['count'])
            . ' · dernière génération : ' . esc_html(mysql2date('j F Y à H:i', $history[0]['time']));
    }

    if ($error) {
        echo '<div class="optimizr-notice optimizr-notice--warn">' . hpt_icon('alert', 18) . '<span><strong>La dernière génération a échoué.</strong> ' . esc_html($error) . '</span></div>';
    } elseif (!$file_exists) {
        echo '<div class="optimizr-notice optimizr-notice--warn">' . hpt_icon('alert', 18) . '<span><strong>Le fichier sitemap.xml est absent.</strong> Régénérez-le ci-dessous.</span></div>';
    } elseif (!$http_ok) {
        echo '<div class="optimizr-notice optimizr-notice--warn">' . hpt_icon('alert', 18) . '<span><strong>sitemap.xml existe mais ne répond pas en HTTP</strong>' . $details . '</span><a href="' . esc_url($file_url) . '" target="_blank" rel="noopener">Ouvrir</a></div>';
    } else {
        echo '<div class="optimizr-notice optimizr-notice--ok">' . hpt_icon('check', 18) . '<span><strong>sitemap.xml est accessible</strong>' . $details . '</span><a href="' . esc_url($file_url) . '" target="_blank" rel="noopener">Ouvrir</a></div>';
    }

    echo '<div class="optimizr-inline">';
    echo '<a href="' . esc_url($url) . '" class="optimizr-btn optimizr-btn--secondary">' . hpt_icon('refresh', 16) . 'Régénérer maintenant</a>';
    echo '</div>';

    if ($history) {
        echo '<details class="optimizr-details"><summary>Historique des générations</summary><ul>';
        foreach ($history as $entry) {
            printf(
                '<li>%s · %s · %s</li>',
                esc_html(mysql2date('j M Y, H:i', $entry['time'])),
                esc_html($entry['origin']),
                $entry['ok'] ? esc_html(sprintf('%d URL', $entry['count'])) : '<strong>échec d\'écriture</strong>'
            );
        }
        echo '</ul></details>';
    }

    echo '<p class="optimizr-field__help">Plan du site pour les visiteurs : placez le shortcode <code>[optimizr_plan_du_site]</code> dans une page.</p>';
}

// Déclenche la génération du sitemap et flush les permaliens lors de la modification des options pertinentes
function hpt_flush_rewrite_and_generate_sitemap()
{
    haptic_GPT_write_sitemap_to_file('réglages');
    flush_rewrite_rules();
}

// Changement des types de contenu inclus : régénération immédiate
add_action('update_option_hpt_sitemap_post_types', 'hpt_flush_rewrite_and_generate_sitemap');

// Traitement de l'action
add_action('admin_post_hpt_generate_sitemap_now', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }
    check_admin_referer('hpt_generate_sitemap_now');
    $ok = haptic_GPT_write_sitemap_to_file('manuel');
    flush_rewrite_rules();
    wp_redirect(add_query_arg('hpt_message', $ok ? 'sitemap_regenerated' : 'sitemap_failed', hpt_admin_tab_url('seo')));
    exit;
});

function hpt_show_sitemap_notice()
{
    if (!isset($_GET['hpt_message'])) {
        return;
    }

    $messages = [
        'sitemap_regenerated' => 'Le sitemap a été régénéré.',
        'robots_generated'    => 'Le fichier robots.txt a été généré avec succès.',
    ];

    $key = sanitize_key($_GET['hpt_message']);
    if ($key === 'sitemap_failed') {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(get_option('hpt_sitemap_error', 'La génération du sitemap a échoué.')) . '</p></div>';
    } elseif (isset($messages[$key])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$key]) . '</p></div>';
    }
}
add_action('admin_notices', 'hpt_show_sitemap_notice');

/**
 * Éditeur de motif : variables en étiquettes (js : seo-dynamic-input.js).
 */
function hpt_seo_pattern_editor($option, $default, $label)
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
        <span class="optimizr-muted">Cliquez ou glissez :</span>
        <?php foreach (hpt_seo_pattern_variables() as $code => $var_label) : ?>
            <button type="button" class="optimizr-token-add" draggable="true"
                data-seo-variable="<?php echo esc_attr($code); ?>"
                data-target="<?php echo esc_attr($editable_id); ?>">+ <?php echo esc_html($var_label); ?></button>
        <?php endforeach; ?>
    </div>
    <?php
}

function hpt_seo_title_pattern_callback()
{
    hpt_seo_pattern_editor('hpt_seo_title_pattern', '{titre_page} - {nom_site}', 'Motif du titre');
    echo '<p class="optimizr-field__help">Tapez votre texte librement et « { » pour choisir une variable. Idéalement entre 50 et 60 caractères une fois les variables remplacées.</p>';
}

function hpt_seo_desc_pattern_callback()
{
    hpt_seo_pattern_editor('hpt_seo_desc_pattern', '{resume} - {nom_site}', 'Motif de la description');
    echo '<p class="optimizr-field__help">Tapez votre texte librement et « { » pour choisir une variable. Idéalement entre 120 et 160 caractères.</p>';
}

// Panneau latéral : aperçus Google et réseaux sociaux, mis à jour en direct par js/optimizr-admin.js
add_action('hpt_admin_aside_seo', function () {
    $post_types = (array) get_option('hpt_sitemap_post_types', ['post', 'page']);
    $latest = get_posts([
        'post_type' => $post_types ?: ['post'],
        'post_status' => 'publish',
        'numberposts' => 1,
    ]);

    if (empty($latest)) {
        return;
    }

    $post = $latest[0];
    $vars = hpt_seo_variables($post->ID);
    $title = hpt_generate_seo_content(get_option('hpt_seo_title_pattern', '{titre_page} - {nom_site}'), $post->ID);
    $desc = hpt_generate_seo_content(get_option('hpt_seo_desc_pattern', '{resume} - {nom_site}'), $post->ID);
    $url = get_permalink($post);
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $image = get_the_post_thumbnail_url($post, 'large');
    ?>
    <div class="optimizr-sticky" data-seo-preview data-seo-vars="<?php echo esc_attr(wp_json_encode($vars)); ?>">
        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <h2 class="optimizr-card__title">Aperçu dans Google</h2>
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
                    <dt>Titre</dt>
                    <dd data-seo-count-title></dd>
                    <dt>Description</dt>
                    <dd data-seo-count-desc></dd>
                </dl>
                <p class="optimizr-card__hint">Exemple avec « <?php echo esc_html(get_the_title($post)); ?> », dernier contenu publié.</p>
            </div>
        </section>

        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <h2 class="optimizr-card__title">Aperçu d'un partage</h2>
            </div>
            <div class="optimizr-card__body">
                <div class="optimizr-share">
                    <div class="optimizr-share__image">
                        <?php if ($image) : ?>
                            <img src="<?php echo esc_url($image); ?>" alt="">
                        <?php else : ?>
                            <?php echo hpt_icon('image', 18); ?><span>Pas d'image à la une</span>
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

require_once('seo-meta.php');
require_once('seo-sitemap.php');
require_once('seo-output.php');
require_once('seo-extras.php');

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
    check_admin_referer('hpt_generate_robots_txt');

    $content = get_option('hpt_custom_robots_txt', '');
    $file = ABSPATH . 'robots.txt';

    if (!empty($content)) {
        file_put_contents($file, $content);
    }

    wp_redirect(add_query_arg('hpt_message', 'robots_generated', hpt_admin_tab_url('seo')));
    exit;
});
