<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Mises à jour de la version GitHub d'Optimizr, depuis les releases publiques du dépôt.
 *
 * WordPress interroge la dernière release (tag vX.Y.Z, archive optimizr-X.Y.Z.zip dont le dossier racine est
 * « optimizr ») et la propose dans Tableau de bord › Mises à jour et Extensions, comme une mise à jour classique
 * (mises à jour automatiques comprises). Aucune licence, aucun compte, aucune donnée du site envoyée : une seule
 * requête anonyme à l'API GitHub, mise en cache 12 heures.
 *
 * Ce fichier n'existe que dans la version GitHub : bin/build-wporg.sh le retire (.distignore) avec la ligne qui le
 * charge (marquée build:github-only), car WordPress.org interdit les mises à jour venant d'ailleurs. Et dès que
 * WordPress.org connaît Optimizr (slug « optimizr »), ce module s'efface : les mises à jour viennent alors de là.
 *
 * Filtre haptic_github_updates_enabled (booléen) pour le couper.
 */

define('HAPTIC_OPTIMIZR_GITHUB_REPO', 'Corteo/Optimizr');

/** Clé du cache de la dernière release (option de site, partagée en multisite). */
define('HAPTIC_OPTIMIZR_GITHUB_CACHE', 'haptic_github_release');

/** Mises à jour depuis GitHub actives ? */
function haptic_github_updates_enabled()
{
    return (bool) apply_filters('haptic_github_updates_enabled', true);
}

/**
 * Dernière release publiée sur GitHub (ni brouillon ni pré-version), avec son archive.
 *
 * @param bool $force Ignorer le cache.
 * @return array|null version, package, url, notes, published ; null si aucune release exploitable.
 */
function haptic_github_latest_release($force = false)
{
    $cached = get_site_transient(HAPTIC_OPTIMIZR_GITHUB_CACHE);
    if (!$force && is_array($cached)) {
        return $cached['release'] ?? null;
    }

    $response = wp_remote_get('https://api.github.com/repos/' . HAPTIC_OPTIMIZR_GITHUB_REPO . '/releases/latest', [
        'timeout'    => 10,
        'headers'    => ['Accept' => 'application/vnd.github+json'],
        // Sans l'adresse du site que WordPress ajoute par défaut
        'user-agent' => 'Optimizr/' . HAPTIC_OPTIMIZR_VERSION,
    ]);

    $release = null;
    $code = wp_remote_retrieve_response_code($response);
    if (!is_wp_error($response) && $code === 200) {
        $release = haptic_github_parse_release(json_decode(wp_remote_retrieve_body($response), true));
    }

    // Échec réseau ou limite de l'API (60 requêtes par heure et par adresse IP) : nouvel essai dans une heure
    $failed = is_wp_error($response) || ($code !== 200 && $code !== 404);
    set_site_transient(HAPTIC_OPTIMIZR_GITHUB_CACHE, ['release' => $release], $failed ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS);

    return $release;
}

/**
 * Release de l'API GitHub ramenée à l'essentiel. Le numéro vient du tag (vX.Y.Z) ou du titre (X.Y.Z), l'archive
 * doit s'appeler optimizr-X.Y.Z.zip (workflow Release) : sinon la release est ignorée.
 *
 * @param mixed $data Réponse décodée de /releases/latest.
 * @return array|null
 */
function haptic_github_parse_release($data)
{
    if (!is_array($data) || !empty($data['draft']) || !empty($data['prerelease'])) {
        return null;
    }

    $version = '';
    foreach ([$data['tag_name'] ?? '', $data['name'] ?? ''] as $candidate) {
        if (preg_match('/^v?(\d+\.\d+\.\d+)$/', trim((string) $candidate), $m)) {
            $version = $m[1];
            break;
        }
    }
    if ($version === '') {
        return null;
    }

    $package = '';
    foreach ((array) ($data['assets'] ?? []) as $asset) {
        if (is_array($asset) && ($asset['name'] ?? '') === 'optimizr-' . $version . '.zip') {
            $package = (string) ($asset['browser_download_url'] ?? '');
            break;
        }
    }
    if (strpos($package, 'https://github.com/') !== 0) {
        return null;
    }

    return [
        'version'   => $version,
        'package'   => $package,
        'url'       => (string) ($data['html_url'] ?? 'https://github.com/' . HAPTIC_OPTIMIZR_GITHUB_REPO . '/releases'),
        'notes'     => (string) ($data['body'] ?? ''),
        'published' => (string) ($data['published_at'] ?? ''),
    ];
}

/** Optimizr est-il connu de WordPress.org ? (réponse de api.wordpress.org dans la vérification en cours) */
function haptic_github_is_on_wporg($transient, $basename)
{
    foreach (['response', 'no_update'] as $list) {
        $item = $transient->{$list}[$basename] ?? null;
        $id = is_object($item) ? (string) ($item->id ?? '') : (is_array($item) ? (string) ($item['id'] ?? '') : '');
        if (strpos($id, 'w.org/plugins/') === 0) {
            return true;
        }
    }
    return false;
}

/** Données de mise à jour au format attendu par WordPress (transient update_plugins). */
function haptic_github_update_item(array $release)
{
    $basename = plugin_basename(HAPTIC_OPTIMIZR_FILE);
    return (object) [
        'id'            => 'github.com/' . HAPTIC_OPTIMIZR_GITHUB_REPO,
        'slug'          => dirname($basename),
        'plugin'        => $basename,
        'new_version'   => $release['version'],
        'url'           => 'https://github.com/' . HAPTIC_OPTIMIZR_GITHUB_REPO,
        'package'       => $release['package'],
        'requires'      => '6.2',
        'requires_php'  => '7.4',
        'icons'         => [],
        'banners'       => [],
        'compatibility' => new stdClass(),
    ];
}

// Vérification des mises à jour par WordPress : la release GitHub est proposée si elle est plus récente
add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    if (!is_object($transient) || empty($transient->checked) || !haptic_github_updates_enabled()) {
        return $transient;
    }

    $basename = plugin_basename(HAPTIC_OPTIMIZR_FILE);
    if (haptic_github_is_on_wporg($transient, $basename)) {
        return $transient;
    }

    // Vérification forcée (bouton « Vérifier à nouveau » de Tableau de bord › Mises à jour) : cache ignoré
    $force = is_admin() && isset($_GET['force-check']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple lecture, comme le fait WordPress sur cet écran
    $release = haptic_github_latest_release($force);
    if (!$release) {
        return $transient;
    }

    $item = haptic_github_update_item($release);
    if (version_compare($release['version'], HAPTIC_OPTIMIZR_VERSION, '>')) {
        $transient->response[$basename] = $item;
        unset($transient->no_update[$basename]);
    } else {
        // Dans no_update, l'extension affiche le lien « Activer les mises à jour automatiques »
        $item->new_version = HAPTIC_OPTIMIZR_VERSION;
        $item->package = '';
        $transient->no_update[$basename] = $item;
        unset($transient->response[$basename]);
    }

    return $transient;
});

// Fenêtre « Voir les détails » : notes de la release GitHub à la place de la fiche WordPress.org
add_filter('plugins_api', function ($result, $action, $args) {
    $basename = plugin_basename(HAPTIC_OPTIMIZR_FILE);
    if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== dirname($basename) || !haptic_github_updates_enabled()) {
        return $result;
    }

    $updates = get_site_transient('update_plugins');
    $item = is_object($updates) ? ($updates->response[$basename] ?? $updates->no_update[$basename] ?? null) : null;
    if (!is_object($item) || strpos((string) ($item->id ?? ''), 'github.com/') !== 0) {
        return $result;
    }

    $release = haptic_github_latest_release();
    if (!$release) {
        return $result;
    }

    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin = get_plugin_data(HAPTIC_OPTIMIZR_FILE, false, false);

    return (object) [
        'name'          => 'Optimizr',
        'slug'          => dirname($basename),
        'version'       => $release['version'],
        'author'        => '<a href="https://alexandrevergnaud.fr/">Haptic</a>',
        'homepage'      => 'https://optimizr.hapticstudio.fr/',
        'requires'      => '6.2',
        'requires_php'  => '7.4',
        'last_updated'  => $release['published'],
        'download_link' => $release['package'],
        'sections'      => [
            'description' => wpautop(esc_html($plugin['Description'] ?? '')),
            'changelog'   => haptic_github_markdown_to_html($release['notes']) . '<p><a href="' . esc_url($release['url']) . '" target="_blank" rel="noopener">GitHub</a></p>',
        ],
    ];
}, 10, 3);

/**
 * Notes de release (Markdown de CHANGELOG.md) en HTML simple : titres, listes, gras, code et liens.
 *
 * @param string $markdown
 * @return string HTML échappé.
 */
function haptic_github_markdown_to_html($markdown)
{
    $html = '';
    $in_list = false;
    foreach (preg_split('/\r\n|\r|\n/', (string) $markdown) as $line) {
        $line = rtrim($line);
        $is_item = (bool) preg_match('/^\s*[-*]\s+(.*)$/', $line, $item);
        if ($in_list && !$is_item) {
            $html .= '</ul>';
            $in_list = false;
        }
        if ($line === '') {
            continue;
        }
        if ($is_item) {
            if (!$in_list) {
                $html .= '<ul>';
                $in_list = true;
            }
            $html .= '<li>' . haptic_github_markdown_inline($item[1]) . '</li>';
        } elseif (preg_match('/^#{1,6}\s+(.*)$/', $line, $title)) {
            $html .= '<h4>' . haptic_github_markdown_inline($title[1]) . '</h4>';
        } else {
            $html .= '<p>' . haptic_github_markdown_inline($line) . '</p>';
        }
    }
    if ($in_list) {
        $html .= '</ul>';
    }
    return $html;
}

/** Mise en forme d'une ligne : texte échappé, puis **gras**, `code` et [lien](https://…). */
function haptic_github_markdown_inline($text)
{
    $text = esc_html($text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    return preg_replace_callback('/\[([^\]]+)\]\((https:\/\/[^)\s]+)\)/', function ($m) {
        return '<a href="' . esc_url(html_entity_decode($m[2])) . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
    }, $text);
}

/*
 * Installation : l'archive de la release contient le dossier « optimizr ». Si l'extension est installée sous un autre
 * nom de dossier (zip « Optimizr-main » téléchargé depuis GitHub, par exemple), le dossier extrait reprend ce nom
 * pour que WordPress remplace bien l'extension en place au lieu d'en installer une seconde.
 */
add_filter('upgrader_source_selection', function ($source, $remote_source, $upgrader, $hook_extra = []) {
    global $wp_filesystem;

    $basename = plugin_basename(HAPTIC_OPTIMIZR_FILE);
    if (!is_array($hook_extra) || ($hook_extra['plugin'] ?? '') !== $basename || !is_object($wp_filesystem)) {
        return $source;
    }

    $folder = dirname($basename);
    if ($folder === '.' || basename(untrailingslashit($source)) === $folder) {
        return $source;
    }

    $target = trailingslashit($remote_source) . $folder . '/';
    if (!$wp_filesystem->exists(trailingslashit($source) . 'optimizr.php') || !$wp_filesystem->move($source, $target, true)) {
        return $source;
    }
    return $target;
}, 10, 4);

// Après une mise à jour d'Optimizr, la release en cache est oubliée (le numéro installé a changé)
add_action('upgrader_process_complete', function ($upgrader, $options) {
    $plugins = (array) ($options['plugins'] ?? []);
    if (($options['type'] ?? '') === 'plugin' && in_array(plugin_basename(HAPTIC_OPTIMIZR_FILE), $plugins, true)) {
        delete_site_transient(HAPTIC_OPTIMIZR_GITHUB_CACHE);
    }
}, 10, 2);
