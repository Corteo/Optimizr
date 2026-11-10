<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Import depuis les extensions SEO concurrentes (Yoast SEO, Rank Math…) : titre, description,
 * image de partage et « noindex » de chaque contenu, et modèles globaux de titre et de description.
 *
 * - Une valeur déjà saisie dans Optimizr n'est jamais remplacée.
 * - Les données de l'extension d'origine restent en base : la réactiver retrouve tout.
 * - Traitement par lots (AJAX haptic_migration_batch), pour les sites qui ont des milliers de contenus.
 * - Lit la base directement : l'import fonctionne aussi quand l'extension est déjà désactivée.
 *
 * Optimizr Pro (ou une autre extension) peut ajouter des sources avec le filtre haptic_migration_sources.
 */

/** Contenus traités par lot. */
const HAPTIC_MIGRATION_BATCH = 100;

/**
 * Sources d'import. Pour chacune :
 * - label : nom affiché ;
 * - plugins : fichiers principaux de l'extension (pour savoir si elle est active et la désactiver) ;
 * - stats : fonction qui renvoie ['title' => n, 'description' => n, 'image' => n, 'noindex' => n] ;
 * - posts : fonction ($after_id, $limit) qui renvoie les identifiants de contenus suivants qui ont des données ;
 * - values : fonction ($post_id) qui renvoie ['title', 'description', 'image', 'noindex'] au format d'Optimizr ;
 * - patterns : fonction qui renvoie ['title' => motif, 'description' => motif] au format d'Optimizr (vide si absent).
 *
 * @return array
 */
function haptic_migration_sources()
{
    return apply_filters('haptic_migration_sources', [
        'yoast' => [
            'label'    => 'Yoast SEO',
            'plugins'  => ['wordpress-seo/wp-seo.php', 'wordpress-seo-premium/wp-seo-premium.php'],
            'stats'    => 'haptic_migration_yoast_stats',
            'posts'    => 'haptic_migration_yoast_posts',
            'values'   => 'haptic_migration_yoast_values',
            'patterns' => 'haptic_migration_yoast_patterns',
        ],
        'aioseo' => [
            'label'    => 'All in One SEO',
            'plugins'  => ['all-in-one-seo-pack/all_in_one_seo_pack.php', 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php'],
            'stats'    => 'haptic_migration_aioseo_stats',
            'posts'    => 'haptic_migration_aioseo_posts',
            'values'   => 'haptic_migration_aioseo_values',
            'patterns' => 'haptic_migration_aioseo_patterns',
        ],
        'rankmath' => [
            'label'    => 'Rank Math',
            'plugins'  => ['seo-by-rank-math/rank-math.php', 'seo-by-rank-math-pro/rank-math-pro.php'],
            'stats'    => 'haptic_migration_rankmath_stats',
            'posts'    => 'haptic_migration_rankmath_posts',
            'values'   => 'haptic_migration_rankmath_values',
            'patterns' => 'haptic_migration_rankmath_patterns',
        ],
        'seopress' => [
            'label'    => 'SEOPress',
            'plugins'  => ['wp-seopress/seopress.php', 'wp-seopress-pro/seopress-pro.php'],
            'stats'    => 'haptic_migration_seopress_stats',
            'posts'    => 'haptic_migration_seopress_posts',
            'values'   => 'haptic_migration_seopress_values',
            'patterns' => 'haptic_migration_seopress_patterns',
        ],
        'tsf' => [
            'label'    => 'The SEO Framework',
            'plugins'  => ['autodescription/autodescription.php'],
            'stats'    => 'haptic_migration_tsf_stats',
            'posts'    => 'haptic_migration_tsf_posts',
            'values'   => 'haptic_migration_tsf_values',
            'patterns' => '__return_empty_array',
        ],
    ]);
}

/** Extensions de la source actives sur ce site (fichiers principaux). */
function haptic_migration_active_plugins($source)
{
    $sources = haptic_migration_sources();
    $active = (array) get_option('active_plugins', []);
    $network = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', [])) : [];

    return array_values(array_intersect($sources[$source]['plugins'] ?? [], array_merge($active, $network)));
}

/**
 * Sources qui ont des données à importer sur ce site, avec leurs chiffres.
 *
 * @return array slug => ['label', 'active' => string[], 'stats' => array, 'total' => int, 'patterns' => array]
 */
function haptic_migration_detect()
{
    $found = [];
    foreach (haptic_migration_sources() as $slug => $source) {
        $stats = array_merge(['title' => 0, 'description' => 0, 'image' => 0, 'noindex' => 0], (array) call_user_func($source['stats']));
        $patterns = array_filter((array) call_user_func($source['patterns']));
        $active = haptic_migration_active_plugins($slug);
        if (!array_sum($stats) && !$patterns && !$active) {
            continue;
        }
        $found[$slug] = [
            'label'    => $source['label'],
            'active'   => $active,
            'stats'    => $stats,
            'total'    => array_sum($stats),
            'patterns' => $patterns,
        ];
    }
    return $found;
}

/* ---------- Variables ---------- */

/**
 * Traduit les variables d'une autre extension en variables d'Optimizr.
 * Une variable sans équivalent (numéro de page, année, champ personnalisé…) est retirée ;
 * haptic_seo_clean_text() enlève ensuite le séparateur resté orphelin.
 *
 * @param string   $text
 * @param string   $regex     Expression qui capture le nom de la variable dans le groupe 1
 * @param string[] $map       nom => valeur
 * @param callable $is_var    Le nom capturé est-il une variable (sinon le texte est gardé tel quel) ?
 */
function haptic_migration_translate($text, $regex, array $map, $is_var = null)
{
    $text = preg_replace_callback($regex, function ($m) use ($map, $is_var) {
        $name = strtolower($m[1]);
        if (array_key_exists($name, $map)) {
            return $map[$name];
        }
        return ($is_var === null || $is_var($name)) ? '' : $m[0];
    }, (string) $text);

    // Espaces doublés par une variable retirée
    return trim(preg_replace('/[ \t]{2,}/', ' ', $text));
}

/** Variables d'Optimizr communes aux deux extensions (nom de la variable d'origine => variable d'Optimizr). */
function haptic_migration_common_variables()
{
    return [
        'title'          => '{titre_page}',
        'sitename'       => '{nom_site}',
        'sitedesc'       => '{slogan}',
        'excerpt'        => '{resume}',
        'excerpt_only'   => '{resume}',
        'category'       => '{categorie}',
        'primary_category' => '{categorie}',
        'name'           => '{auteur}',
        'pt_single'      => '{type_contenu}',
        'date'           => '{date_publication}',
    ];
}

/* ---------- Yoast SEO ---------- */

function haptic_migration_yoast_separator()
{
    $titles = get_option('wpseo_titles');
    $key = is_array($titles) && !empty($titles['separator']) ? $titles['separator'] : 'sc-dash';
    $separators = [
        'sc-dash' => '-', 'sc-ndash' => '–', 'sc-mdash' => '—', 'sc-colon' => ':', 'sc-middot' => '·',
        'sc-bull' => '•', 'sc-star' => '*', 'sc-smstar' => '⋆', 'sc-pipe' => '|', 'sc-tilde' => '~',
        'sc-laquo' => '«', 'sc-raquo' => '»', 'sc-lt' => '<', 'sc-gt' => '>',
    ];
    return $separators[$key] ?? '-';
}

/** Texte Yoast (%%title%% %%sep%% %%sitename%%) au format d'Optimizr ({titre_page} - {nom_site}). */
function haptic_migration_yoast_translate($text)
{
    $map = haptic_migration_common_variables();
    $map['sep'] = haptic_migration_yoast_separator();
    return haptic_migration_translate($text, '/%%([a-z0-9_-]+)%%/i', $map);
}

function haptic_migration_yoast_meta_keys()
{
    return [
        'title'       => '_yoast_wpseo_title',
        'description' => '_yoast_wpseo_metadesc',
        'image'       => '_yoast_wpseo_opengraph-image',
        'noindex'     => '_yoast_wpseo_meta-robots-noindex',
    ];
}

function haptic_migration_yoast_stats()
{
    return haptic_migration_meta_stats(haptic_migration_yoast_meta_keys(), '1');
}

function haptic_migration_yoast_posts($after, $limit)
{
    return haptic_migration_meta_posts(haptic_migration_yoast_meta_keys(), $after, $limit);
}

function haptic_migration_yoast_values($post_id)
{
    $keys = haptic_migration_yoast_meta_keys();
    return [
        'title'       => haptic_migration_yoast_translate(get_post_meta($post_id, $keys['title'], true)),
        'description' => haptic_migration_yoast_translate(get_post_meta($post_id, $keys['description'], true)),
        'image'       => (string) get_post_meta($post_id, $keys['image'], true),
        // 1 : ne pas indexer ; 2 : indexer (forcé) ; vide ou 0 : réglage du type de contenu
        'noindex'     => (string) get_post_meta($post_id, $keys['noindex'], true) === '1',
    ];
}

/** Modèles de Yoast : ceux des pages (sites vitrines), sinon ceux des articles. */
function haptic_migration_yoast_patterns()
{
    $titles = get_option('wpseo_titles');
    if (!is_array($titles)) {
        return [];
    }
    $pick = function ($prefix) use ($titles) {
        foreach (['page', 'post'] as $type) {
            if (!empty($titles[$prefix . '-' . $type])) {
                return haptic_migration_yoast_translate($titles[$prefix . '-' . $type]);
            }
        }
        return '';
    };
    return ['title' => $pick('title'), 'description' => $pick('metadesc')];
}

/* ---------- All in One SEO ---------- */

/** Table des réglages par contenu d'All in One SEO 4 (null si absente : All in One SEO 3, ou jamais installé). */
function haptic_migration_aioseo_table()
{
    global $wpdb;
    static $table = false;
    if ($table === false) {
        $name = $wpdb->prefix . 'aioseo_posts';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- vérification ponctuelle de l'existence d'une table d'une autre extension
        $table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($name))) === $name ? $name : null;
    }
    return $table;
}

/** Réglages d'All in One SEO 4 (options JSON aioseo_options et aioseo_options_dynamic). */
function haptic_migration_aioseo_options()
{
    $options = [];
    foreach (['aioseo_options', 'aioseo_options_dynamic'] as $name) {
        $value = get_option($name);
        $value = is_string($value) ? json_decode($value, true) : $value;
        if (is_array($value)) {
            $options = array_replace_recursive($options, $value);
        }
    }
    return $options;
}

function haptic_migration_aioseo_separator()
{
    $options = haptic_migration_aioseo_options();
    $separator = $options['searchAppearance']['global']['separator'] ?? '-';
    return html_entity_decode((string) $separator, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: '-';
}

/** Texte All in One SEO (#post_title #separator_sa #site_title) au format d'Optimizr. */
function haptic_migration_aioseo_translate($text)
{
    $map = [
        'post_title'        => '{titre_page}',
        'site_title'        => '{nom_site}',
        'tagline'           => '{slogan}',
        'post_excerpt'      => '{resume}',
        'post_excerpt_only' => '{resume}',
        'post_content'      => '{resume}',
        'categories'        => '{categorie}',
        'author_name'       => '{auteur}',
        'post_date'         => '{date_publication}',
        'separator_sa'      => haptic_migration_aioseo_separator(),
    ];
    // Champs personnalisés et taxonomies : sans équivalent
    $text = preg_replace('/#(?:custom_field|tax_name)-[a-z0-9_-]+/i', '', (string) $text);
    // Un mot précédé de # n'est une variable que s'il est connu ou composé (#post_content) : « #1 » ou « #promo » restent
    return haptic_migration_translate($text, '/#([a-z]+(?:_[a-z]+)*)(?![a-z0-9_-])/i', $map, function ($name) {
        return strpos($name, '_') !== false || in_array($name, ['permalink', 'separator'], true);
    });
}

function haptic_migration_aioseo_legacy_keys()
{
    return [
        'title'       => '_aioseop_title',
        'description' => '_aioseop_description',
        'noindex'     => '_aioseop_noindex',
    ];
}

function haptic_migration_aioseo_stats()
{
    global $wpdb;
    $table = haptic_migration_aioseo_table();
    if (!$table) {
        return haptic_migration_meta_stats(haptic_migration_aioseo_legacy_keys(), 'on');
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table d'une autre extension, nom construit à partir de $wpdb->prefix ; comptage ponctuel
    $row = $wpdb->get_row("SELECT SUM(title <> '') AS title, SUM(description <> '') AS description, SUM(og_image_custom_url <> '') AS image, SUM(robots_default = 0 AND robots_noindex = 1) AS noindex FROM {$table} WHERE post_id IS NOT NULL", ARRAY_A);
    return array_map('intval', (array) $row);
}

function haptic_migration_aioseo_posts($after, $limit)
{
    global $wpdb;
    $table = haptic_migration_aioseo_table();
    if (!$table) {
        return haptic_migration_meta_posts(haptic_migration_aioseo_legacy_keys(), $after, $limit);
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table d'une autre extension, nom construit à partir de $wpdb->prefix ; lecture par lots
    return array_map('intval', $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$table} WHERE post_id > %d AND (title <> '' OR description <> '' OR og_image_custom_url <> '' OR (robots_default = 0 AND robots_noindex = 1)) ORDER BY post_id LIMIT %d",
        $after,
        $limit
    )));
}

function haptic_migration_aioseo_values($post_id)
{
    global $wpdb;
    $table = haptic_migration_aioseo_table();
    if (!$table) {
        $keys = haptic_migration_aioseo_legacy_keys();
        return [
            'title'       => haptic_migration_aioseo_translate(get_post_meta($post_id, $keys['title'], true)),
            'description' => haptic_migration_aioseo_translate(get_post_meta($post_id, $keys['description'], true)),
            'image'       => '',
            'noindex'     => get_post_meta($post_id, $keys['noindex'], true) === 'on',
        ];
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table d'une autre extension, nom construit à partir de $wpdb->prefix
    $row = (array) $wpdb->get_row($wpdb->prepare("SELECT title, description, og_image_custom_url, robots_default, robots_noindex FROM {$table} WHERE post_id = %d", $post_id), ARRAY_A);
    return [
        'title'       => haptic_migration_aioseo_translate($row['title'] ?? ''),
        'description' => haptic_migration_aioseo_translate($row['description'] ?? ''),
        'image'       => (string) ($row['og_image_custom_url'] ?? ''),
        'noindex'     => isset($row['robots_default']) && !(int) $row['robots_default'] && (int) $row['robots_noindex'],
    ];
}

/** Modèles d'All in One SEO 4 : ceux des pages, sinon ceux des articles. */
function haptic_migration_aioseo_patterns()
{
    $types = haptic_migration_aioseo_options()['searchAppearance']['postTypes'] ?? [];
    if (!is_array($types)) {
        return [];
    }
    $pick = function ($key) use ($types) {
        foreach (['page', 'post'] as $type) {
            if (!empty($types[$type][$key]) && is_string($types[$type][$key])) {
                return haptic_migration_aioseo_translate($types[$type][$key]);
            }
        }
        return '';
    };
    return ['title' => $pick('title'), 'description' => $pick('metaDescription')];
}

/* ---------- Rank Math ---------- */

/** Texte Rank Math (%title% %sep% %sitename%) au format d'Optimizr. */
function haptic_migration_rankmath_translate($text)
{
    $titles = get_option('rank-math-options-titles');
    $map = haptic_migration_common_variables();
    $map['sep'] = is_array($titles) && !empty($titles['title_separator'])
        ? html_entity_decode((string) $titles['title_separator'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
        : '-';
    $map['seo_title'] = '{titre_page}';
    // %customfield(nom)% et autres variables à paramètre : sans équivalent
    return haptic_migration_translate($text, '/%([a-z0-9_]+)(?:\([^)%]*\))?%/i', $map);
}

function haptic_migration_rankmath_meta_keys()
{
    return [
        'title'       => 'rank_math_title',
        'description' => 'rank_math_description',
        'image'       => 'rank_math_facebook_image',
        'noindex'     => 'rank_math_robots',
    ];
}

function haptic_migration_rankmath_stats()
{
    // rank_math_robots : tableau sérialisé (['noindex', 'nofollow'])
    return haptic_migration_meta_stats(haptic_migration_rankmath_meta_keys(), ['like' => '"noindex"']);
}

function haptic_migration_rankmath_posts($after, $limit)
{
    return haptic_migration_meta_posts(haptic_migration_rankmath_meta_keys(), $after, $limit);
}

function haptic_migration_rankmath_values($post_id)
{
    $keys = haptic_migration_rankmath_meta_keys();
    return [
        'title'       => haptic_migration_rankmath_translate(get_post_meta($post_id, $keys['title'], true)),
        'description' => haptic_migration_rankmath_translate(get_post_meta($post_id, $keys['description'], true)),
        'image'       => (string) get_post_meta($post_id, $keys['image'], true),
        'noindex'     => in_array('noindex', (array) get_post_meta($post_id, $keys['noindex'], true), true),
    ];
}

/** Modèles de Rank Math : ceux des pages, sinon ceux des articles. */
function haptic_migration_rankmath_patterns()
{
    $titles = get_option('rank-math-options-titles');
    if (!is_array($titles)) {
        return [];
    }
    $pick = function ($suffix) use ($titles) {
        foreach (['page', 'post'] as $type) {
            if (!empty($titles['pt_' . $type . '_' . $suffix]) && is_string($titles['pt_' . $type . '_' . $suffix])) {
                return haptic_migration_rankmath_translate($titles['pt_' . $type . '_' . $suffix]);
            }
        }
        return '';
    };
    return ['title' => $pick('title'), 'description' => $pick('description')];
}

/* ---------- SEOPress ---------- */

/** Texte SEOPress (%%post_title%% %%sep%% %%sitetitle%%) au format d'Optimizr. */
function haptic_migration_seopress_translate($text)
{
    $options = get_option('seopress_titles_option_name');
    $map = [
        'post_title'        => '{titre_page}',
        'sitetitle'         => '{nom_site}',
        'sitename'          => '{nom_site}',
        'tagline'           => '{slogan}',
        'post_excerpt'      => '{resume}',
        'post_content'      => '{resume}',
        'post_category'     => '{categorie}',
        'post_author'       => '{auteur}',
        'post_date'         => '{date_publication}',
        'sep'               => is_array($options) && !empty($options['seopress_titles_sep'])
            ? html_entity_decode((string) $options['seopress_titles_sep'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '-',
    ];
    return haptic_migration_translate($text, '/%%([a-z0-9_-]+)%%/i', $map);
}

function haptic_migration_seopress_meta_keys()
{
    return [
        'title'       => '_seopress_titles_title',
        'description' => '_seopress_titles_desc',
        'image'       => '_seopress_social_fb_img',
        'noindex'     => '_seopress_robots_index',
    ];
}

function haptic_migration_seopress_stats()
{
    // _seopress_robots_index : « yes » quand la case « noindex » est cochée
    return haptic_migration_meta_stats(haptic_migration_seopress_meta_keys(), 'yes');
}

function haptic_migration_seopress_posts($after, $limit)
{
    return haptic_migration_meta_posts(haptic_migration_seopress_meta_keys(), $after, $limit);
}

function haptic_migration_seopress_values($post_id)
{
    $keys = haptic_migration_seopress_meta_keys();
    return [
        'title'       => haptic_migration_seopress_translate(get_post_meta($post_id, $keys['title'], true)),
        'description' => haptic_migration_seopress_translate(get_post_meta($post_id, $keys['description'], true)),
        'image'       => (string) get_post_meta($post_id, $keys['image'], true),
        'noindex'     => get_post_meta($post_id, $keys['noindex'], true) === 'yes',
    ];
}

/** Modèles de SEOPress : ceux des pages, sinon ceux des articles. */
function haptic_migration_seopress_patterns()
{
    $options = get_option('seopress_titles_option_name');
    $types = is_array($options) ? ($options['seopress_titles_single_titles'] ?? []) : [];
    if (!is_array($types)) {
        return [];
    }
    $pick = function ($key) use ($types) {
        foreach (['page', 'post'] as $type) {
            if (!empty($types[$type][$key]) && is_string($types[$type][$key])) {
                return haptic_migration_seopress_translate($types[$type][$key]);
            }
        }
        return '';
    };
    return ['title' => $pick('title'), 'description' => $pick('description')];
}

/* ---------- The SEO Framework ---------- */

function haptic_migration_tsf_meta_keys()
{
    return [
        'title'       => '_genesis_title',
        'description' => '_genesis_description',
        'image'       => '_social_image_url',
        'noindex'     => '_genesis_noindex',
    ];
}

function haptic_migration_tsf_stats()
{
    return haptic_migration_meta_stats(haptic_migration_tsf_meta_keys(), '1');
}

function haptic_migration_tsf_posts($after, $limit)
{
    return haptic_migration_meta_posts(haptic_migration_tsf_meta_keys(), $after, $limit);
}

/**
 * The SEO Framework n'a pas de variables, mais ajoute le nom du site au titre saisi, sauf si
 * « Retirer le nom du site » est coché (_tsf_title_no_blogname) : on l'ajoute de même, avec son séparateur.
 */
function haptic_migration_tsf_values($post_id)
{
    $keys = haptic_migration_tsf_meta_keys();
    $title = (string) get_post_meta($post_id, $keys['title'], true);

    if ($title !== '' && !get_post_meta($post_id, '_tsf_title_no_blogname', true)) {
        $settings = get_option('autodescription-site-settings');
        $settings = is_array($settings) ? $settings : [];
        if (empty($settings['site_title_removal'])) {
            $separators = ['hyphen' => '-', 'pipe' => '|', 'ndash' => '–', 'mdash' => '—', 'dot' => '·', 'bull' => '•', 'middot' => '·', 'colon' => ':'];
            $sep = $separators[$settings['title_separator'] ?? 'hyphen'] ?? '-';
            $title = ($settings['title_location'] ?? 'right') === 'left'
                ? '{nom_site} ' . $sep . ' ' . $title
                : $title . ' ' . $sep . ' {nom_site}';
        }
    }

    return [
        'title'       => $title,
        'description' => (string) get_post_meta($post_id, $keys['description'], true),
        'image'       => (string) get_post_meta($post_id, $keys['image'], true),
        'noindex'     => (string) get_post_meta($post_id, $keys['noindex'], true) === '1',
    ];
}

/* ---------- Sources en métadonnées (Yoast, Rank Math, SEOPress, The SEO Framework, All in One SEO 3) ---------- */

/**
 * @param string[] $keys    title, description, image, noindex => clé de métadonnée
 * @param string|array $noindex Valeur qui signifie « ne pas indexer », ou ['like' => texte contenu dans la valeur sérialisée]
 */
function haptic_migration_meta_stats(array $keys, $noindex)
{
    global $wpdb;
    $stats = [];
    foreach ($keys as $field => $key) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- comptage ponctuel sur toutes les publications (onglet Outils)
        $stats[$field] = (int) $wpdb->get_var($field === 'noindex'
            ? (is_array($noindex)
                ? $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s", $key, '%' . $wpdb->esc_like($noindex['like']) . '%')
                : $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $key, $noindex))
            : $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", $key));
    }
    return $stats;
}

function haptic_migration_meta_posts(array $keys, $after, $limit)
{
    global $wpdb;
    $keys = array_values($keys);
    $placeholders = implode(', ', array_fill(0, count($keys), '%s'));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- autant de %s que de clés ; lecture par lots
    return array_map('intval', $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders}) AND meta_value <> '' AND post_id > %d ORDER BY post_id LIMIT %d",
        array_merge($keys, [$after, $limit])
    )));
}

/* ---------- Import ---------- */

/** Charge les fonctions SEO d'Optimizr, même si le module SEO n'est pas encore actif. */
function haptic_migration_load_seo()
{
    if (!function_exists('haptic_seo_resolve')) {
        require_once HAPTIC_OPTIMIZR_PATH . 'functions/seo/seo.php';
    }
}

/** Les modèles d'Optimizr n'ont jamais été changés (absents de la base, ou égaux aux valeurs par défaut). */
function haptic_migration_patterns_untouched()
{
    return in_array(get_option('haptic_seo_title_pattern', '{titre_page} - {nom_site}'), ['', '{titre_page} - {nom_site}'], true)
        && in_array(get_option('haptic_seo_desc_pattern', '{resume} - {nom_site}'), ['', '{resume} - {nom_site}'], true);
}

/** Remplace les modèles d'Optimizr par ceux de la source (seulement ceux qu'elle définit). */
function haptic_migration_import_patterns($source)
{
    $sources = haptic_migration_sources();
    $patterns = isset($sources[$source]) ? (array) call_user_func($sources[$source]['patterns']) : [];
    $imported = [];
    foreach (['title' => 'haptic_seo_title_pattern', 'description' => 'haptic_seo_desc_pattern'] as $field => $option) {
        $pattern = sanitize_text_field($patterns[$field] ?? '');
        // Un modèle qui ne garde aucune variable ni texte utile n'est pas repris
        if (haptic_seo_clean_text($pattern) === '') {
            continue;
        }
        update_option($option, $pattern);
        $imported[] = $field;
    }
    return $imported;
}

/**
 * Importe un lot de contenus d'une source. Une valeur déjà présente dans Optimizr est gardée ;
 * une valeur égale au résultat du modèle d'Optimizr n'est pas enregistrée (le modèle continue de s'appliquer).
 *
 * @return array ['next' => dernier identifiant traité, 'done' => bool, 'counts' => [champ => n], 'kept' => n]
 */
function haptic_migration_import_batch($source, $after = 0, $limit = HAPTIC_MIGRATION_BATCH)
{
    haptic_migration_load_seo();
    $sources = haptic_migration_sources();
    $post_ids = (array) call_user_func($sources[$source]['posts'], (int) $after, (int) $limit);
    $types = haptic_seo_post_types();
    $counts = ['title' => 0, 'description' => 0, 'image' => 0, 'noindex' => 0];
    $kept = 0;

    foreach ($post_ids as $post_id) {
        $after = max((int) $after, (int) $post_id);
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, $types, true) || in_array($post->post_status, ['auto-draft', 'trash', 'inherit'], true)) {
            continue;
        }

        $values = (array) call_user_func($sources[$source]['values'], $post_id);
        $seo = haptic_seo_resolve($post_id);

        $fields = [
            'title'       => ['_haptic_og_title', haptic_generate_seo_content($values['title'] ?? '', $post_id), $seo['pattern_title']],
            'description' => ['_haptic_og_desc', sanitize_textarea_field(haptic_generate_seo_content($values['description'] ?? '', $post_id)), $seo['pattern_description']],
            'image'       => ['_haptic_og_image', esc_url_raw($values['image'] ?? ''), $seo['default_image']],
        ];
        foreach ($fields as $field => list($meta_key, $value, $default)) {
            $value = sanitize_text_field($value);
            if ($value === '' || $value === haptic_seo_clean_text($default) || $value === $default) {
                continue;
            }
            if ((string) get_post_meta($post_id, $meta_key, true) !== '') {
                $kept++;
                continue;
            }
            update_post_meta($post_id, $meta_key, $value);
            $counts[$field]++;
        }

        if (!empty($values['noindex']) && !get_post_meta($post_id, '_haptic_noindex', true)) {
            update_post_meta($post_id, '_haptic_noindex', 1);
            $counts['noindex']++;
        }
    }

    return [
        'processed' => count($post_ids),
        'next'   => (int) $after,
        'done'   => count($post_ids) < $limit,
        'counts' => $counts,
        'kept'   => $kept,
    ];
}

/**
 * Fin de l'import : module SEO et balises automatiques activés (sans eux, les valeurs importées ne s'affichent pas),
 * extensions d'origine désactivées si demandé, cache vidé, résumé enregistré.
 *
 * @param string[] $sources    Sources importées
 * @param string[] $deactivate Sources dont l'extension est à désactiver
 * @param array    $counts     Totaux de l'import
 * @return array ['deactivated' => string[] noms des extensions désactivées]
 */
function haptic_migration_finish(array $sources, array $deactivate, array $counts)
{
    $modules = get_option('haptic_modules');
    if (is_array($modules) && empty($modules['seo'])) {
        $modules['seo'] = 1;
        update_option('haptic_modules', $modules);
    }
    if (!get_option('haptic_enable_seo')) {
        update_option('haptic_enable_seo', 1);
    }

    $deactivated = [];
    if ($deactivate && current_user_can('activate_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $all = haptic_migration_sources();
        foreach ($deactivate as $source) {
            $plugins = haptic_migration_active_plugins($source);
            if ($plugins) {
                // Désactivation seulement : l'extension et ses données restent sur le site
                deactivate_plugins($plugins, false, is_multisite() && is_plugin_active_for_network($plugins[0]));
                $deactivated[] = $all[$source]['label'];
            }
        }
    }

    if (function_exists('haptic_cache_purge_all')) {
        haptic_cache_purge_all();
    }

    update_option('haptic_migration_log', [
        'date'        => time(),
        'sources'     => array_values($sources),
        'counts'      => $counts,
        'deactivated' => $deactivated,
    ], false);

    // Extensions : données propres à une source (redirections d'Optimizr Pro…)
    do_action('haptic_migration_finished', $sources, $counts);

    return ['deactivated' => $deactivated];
}

/* ---------- AJAX ---------- */

add_action('wp_ajax_haptic_migration_batch', function () {
    check_ajax_referer('haptic_migration');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Access denied', 'optimizr')], 403);
    }

    $source = sanitize_key(wp_unslash($_POST['source'] ?? ''));
    if (!array_key_exists($source, haptic_migration_sources())) {
        wp_send_json_error(['message' => __('Unknown source.', 'optimizr')], 400);
    }
    $after = absint($_POST['after'] ?? 0);

    // Premier lot : modèles de titre et de description, avant les contenus (une valeur égale au modèle n'est pas enregistrée)
    $patterns = [];
    if ($after === 0 && !empty($_POST['patterns'])) {
        haptic_migration_load_seo();
        $patterns = haptic_migration_import_patterns($source);
    }

    $result = haptic_migration_import_batch($source, $after);
    $result['patterns'] = $patterns;
    wp_send_json_success($result);
});

add_action('wp_ajax_haptic_migration_finish', function () {
    check_ajax_referer('haptic_migration');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Access denied', 'optimizr')], 403);
    }

    $known = array_keys(haptic_migration_sources());
    $list = function ($name) use ($known) {
        $values = isset($_POST[$name]) ? array_map('sanitize_key', (array) wp_unslash($_POST[$name])) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié ci-dessus
        return array_values(array_intersect($values, $known));
    };
    $counts = [];
    foreach (['title', 'description', 'image', 'noindex', 'kept'] as $field) {
        $counts[$field] = absint($_POST['counts'][$field] ?? 0);
    }

    wp_send_json_success(haptic_migration_finish($list('sources'), $list('deactivate'), $counts));
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== HAPTIC_OPTIMIZR_ADMIN_HOOK || haptic_current_tab() !== 'tools') {
        return;
    }
    $js = HAPTIC_OPTIMIZR_PATH . 'js/optimizr-migration.js';
    wp_enqueue_script('optimizr-migration', HAPTIC_OPTIMIZR_URL . 'js/optimizr-migration.js', ['wp-i18n'], filemtime($js), true);
    wp_set_script_translations('optimizr-migration', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
});

/* ---------- Carte de l'onglet Outils ---------- */

/** Résumé chiffré d'une source : « 312 titles, 280 descriptions, 4 noindex ». */
function haptic_migration_stats_text(array $stats)
{
    $parts = [];
    /* translators: %s: number of SEO titles */
    $parts[] = $stats['title'] ? sprintf(_n('%s title', '%s titles', $stats['title'], 'optimizr'), number_format_i18n($stats['title'])) : '';
    /* translators: %s: number of meta descriptions */
    $parts[] = $stats['description'] ? sprintf(_n('%s description', '%s descriptions', $stats['description'], 'optimizr'), number_format_i18n($stats['description'])) : '';
    /* translators: %s: number of share images */
    $parts[] = $stats['image'] ? sprintf(_n('%s share image', '%s share images', $stats['image'], 'optimizr'), number_format_i18n($stats['image'])) : '';
    /* translators: %s: number of contents hidden from search engines */
    $parts[] = $stats['noindex'] ? sprintf(_n('%s hidden from search engines', '%s hidden from search engines', $stats['noindex'], 'optimizr'), number_format_i18n($stats['noindex'])) : '';
    $parts = array_filter($parts);
    return $parts ? implode(', ', $parts) : __('No content setting to import', 'optimizr');
}

function haptic_render_migration_card()
{
    $found = haptic_migration_detect();
    if (!$found) {
        return;
    }
    $untouched = haptic_migration_patterns_untouched();
    // Un seul jeu de modèles : celui de la première source (cases exclusives, cf. js/optimizr-migration.js)
    $patterns_checked = false;
    $can_deactivate = current_user_can('activate_plugins');
    $log = get_option('haptic_migration_log');
    ?>
    <form class="optimizr-card optimizr-migration" data-migration
        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        data-nonce="<?php echo esc_attr(wp_create_nonce('haptic_migration')); ?>">
        <div class="optimizr-card__header">
            <div>
                <h2 class="optimizr-card__title"><?php esc_html_e('Import from another SEO plugin', 'optimizr'); ?></h2>
                <p class="optimizr-card__desc"><?php esc_html_e('Titles, descriptions, share images and “noindex” of each content. Values already set in Optimizr are kept, and the other plugin\'s data stays in the database.', 'optimizr'); ?></p>
            </div>
        </div>
        <div class="optimizr-card__body">
            <?php foreach ($found as $slug => $source) : ?>
                <fieldset class="optimizr-migration__source" data-migration-source="<?php echo esc_attr($slug); ?>" data-label="<?php echo esc_attr($source['label']); ?>" data-estimate="<?php echo esc_attr(max($source['stats'])); ?>">
                    <legend class="screen-reader-text"><?php echo esc_html($source['label']); ?></legend>
                    <label class="optimizr-check">
                        <input type="checkbox" name="sources[]" value="<?php echo esc_attr($slug); ?>" <?php checked($source['total'] > 0 || $source['patterns']); ?>>
                        <span>
                            <strong><?php echo esc_html($source['label']); ?></strong>
                            <?php if ($source['active']) : ?>
                                <span class="optimizr-badge"><?php echo esc_html_x('Active', 'plugin status', 'optimizr'); ?></span>
                            <?php endif; ?>
                            <br><span class="optimizr-muted"><?php echo esc_html(haptic_migration_stats_text($source['stats'])); ?></span>
                        </span>
                    </label>
                    <?php if ($source['patterns']) : ?>
                        <label class="optimizr-check optimizr-migration__option">
                            <input type="checkbox" name="patterns[]" value="<?php echo esc_attr($slug); ?>" data-migration-patterns <?php checked($untouched && !$patterns_checked); ?>>
                            <?php $patterns_checked = $patterns_checked || $untouched; ?>
                            <span><?php esc_html_e('Also use its title and description patterns', 'optimizr'); ?>
                                <?php foreach (['title' => __('Title', 'optimizr'), 'description' => __('Description', 'optimizr')] as $field => $label) : ?>
                                    <?php if (!empty($source['patterns'][$field])) : ?>
                                        <br><span class="optimizr-muted"><?php
                                        /* translators: 1: setting label, 2: entered value */
                                        echo wp_kses(sprintf(__('%1$s: %2$s', 'optimizr'), esc_html($label), '<code>' . esc_html($source['patterns'][$field]) . '</code>'), ['code' => []]);
                                        ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </span>
                        </label>
                    <?php endif; ?>
                    <?php if ($source['active'] && $can_deactivate) : ?>
                        <label class="optimizr-check optimizr-migration__option">
                            <input type="checkbox" name="deactivate[]" value="<?php echo esc_attr($slug); ?>" checked>
                            <span><?php
                            /* translators: %s: plugin name (Yoast SEO, Rank Math…) */
                            echo esc_html(sprintf(__('Deactivate %s after the import', 'optimizr'), $source['label']));
                            ?> <span class="optimizr-muted"><?php esc_html_e('(avoids duplicate tags; it can be reactivated at any time)', 'optimizr'); ?></span></span>
                        </label>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>

            <?php if (count($found) > 1) : ?>
                <p class="optimizr-field__help"><?php esc_html_e('When several plugins have a value for the same content, the first one in the list wins.', 'optimizr'); ?></p>
            <?php endif; ?>

            <div class="optimizr-progress" data-migration-progress hidden><span></span></div>
            <p class="optimizr-migration__status" data-migration-status role="status" aria-live="polite"><?php
            if (is_array($log) && !empty($log['date'])) {
                /* translators: 1: date of the last import, 2: summary of imported values */
                echo esc_html(sprintf(__('Last import on %1$s: %2$s.', 'optimizr'), wp_date(get_option('date_format'), (int) $log['date']), haptic_migration_stats_text(array_merge(['title' => 0, 'description' => 0, 'image' => 0, 'noindex' => 0], (array) $log['counts']))));
            }
            ?></p>
            <div><button type="submit" class="optimizr-btn optimizr-btn--primary" data-migration-submit><?php echo esc_html_x('Import', 'button', 'optimizr'); ?></button></div>
        </div>
    </form>
    <?php
}
