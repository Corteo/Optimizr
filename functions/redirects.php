<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Module Redirections & liens : redirections, journal des pages introuvables (404),
 * redirection automatique des contenus dont l'adresse change.
 *
 * Données dans deux tables dédiées (créées par hpt_redirects_install()) :
 * - {prefix}optimizr_redirects : source, destination, type, visites ;
 * - {prefix}optimizr_404       : adresses introuvables, visites, provenance.
 */

const HPT_REDIRECT_CODES = [
    301 => 'Permanente (301)',
    302 => 'Temporaire (302)',
    307 => 'Temporaire, méthode conservée (307)',
    410 => 'Supprimée définitivement (410)',
];

/* ---------- Adresses ---------- */

/**
 * Adresse normalisée, relative au site : « /chemin » (sans barre finale) et sa requête éventuelle.
 * Accepte une URL complète du site, un chemin, ou un chemin avec « ?requête ».
 */
function hpt_redirects_normalize($url)
{
    $url = trim(wp_unslash((string) $url));
    if ($url === '') {
        return '';
    }

    $parts = wp_parse_url($url);
    $path = rawurldecode($parts['path'] ?? '/');

    // Chemin relatif à l'installation (site dans un sous-dossier)
    $home = rtrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home !== '' && stripos($path, $home . '/') === 0) {
        $path = substr($path, strlen($home));
    } elseif ($home !== '' && strcasecmp($path, $home) === 0) {
        $path = '/';
    }

    // Permaliens « /index.php/article » (serveurs sans réécriture d'adresse)
    if (stripos($path, '/index.php/') === 0) {
        $path = substr($path, 10);
    }

    $path = '/' . trim($path, '/');
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

    return mb_substr($path . $query, 0, 255);
}

/** Adresse demandée par le visiteur, normalisée. */
function hpt_redirects_current_request()
{
    return hpt_redirects_normalize($_SERVER['REQUEST_URI'] ?? '/');
}

/** URL complète de destination (chemin du site ou adresse externe). */
function hpt_redirects_target_url($target)
{
    $target = trim((string) $target);
    if ($target === '' || preg_match('#^https?://#i', $target)) {
        return $target;
    }
    return home_url('/' . ltrim($target, '/'));
}

/** Destination sur ce site (chemin relatif, ou URL complète du même domaine). */
function hpt_redirects_target_is_internal($target)
{
    if (!preg_match('#^https?://#i', (string) $target)) {
        return true;
    }
    return strcasecmp((string) wp_parse_url($target, PHP_URL_HOST), (string) wp_parse_url(home_url(), PHP_URL_HOST)) === 0;
}

/* ---------- Règles ---------- */

/**
 * Règle applicable à une adresse : correspondance exacte (avec ou sans requête), puis jokers « /chemin/* ».
 *
 * @return object|null Règle, avec la destination déjà résolue pour un joker
 */
function hpt_redirects_match($request)
{
    global $wpdb;
    $table = hpt_redirects_table();
    $path = strtok($request, '?');

    $rule = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE source IN (%s, %s) ORDER BY CHAR_LENGTH(source) DESC LIMIT 1",
        $request,
        $path
    ));
    if ($rule) {
        return $rule;
    }

    // Jokers : la plus longue source qui correspond l'emporte
    $wildcards = $wpdb->get_results("SELECT * FROM $table WHERE source LIKE '%*' ORDER BY CHAR_LENGTH(source) DESC");
    foreach ($wildcards as $rule) {
        $prefix = rtrim(substr($rule->source, 0, -1), '/');
        if ($prefix === '' || stripos($path, $prefix . '/') === 0 || strcasecmp($path, $prefix) === 0) {
            $rest = ltrim(substr($path, strlen($prefix)), '/');
            $rule->target = strpos($rule->target, '*') !== false
                ? str_replace('*', $rest, $rule->target)
                : $rule->target;
            return $rule;
        }
    }

    return null;
}

/**
 * Ajoute ou modifie une règle.
 *
 * @return int|WP_Error Identifiant de la règle
 */
function hpt_redirects_save($source, $target, $code = 301, $note = '', $id = 0)
{
    global $wpdb;
    $table = hpt_redirects_table();

    $source = hpt_redirects_normalize($source);
    $code = isset(HPT_REDIRECT_CODES[(int) $code]) ? (int) $code : 301;
    $target = $code === 410 ? '' : trim(sanitize_text_field(wp_unslash((string) $target)));

    if ($source === '' || $source === '/') {
        return new WP_Error('hpt_redirect_source', 'Indiquez l\'adresse à rediriger (la page d\'accueil ne peut pas l\'être).');
    }
    if ($code !== 410 && $target === '') {
        return new WP_Error('hpt_redirect_target', 'Indiquez la destination de la redirection.');
    }
    if ($code !== 410 && !preg_match('#^https?://#i', $target)) {
        $target = hpt_redirects_normalize($target);
    }
    if ($code !== 410 && strcasecmp($target, $source) === 0) {
        return new WP_Error('hpt_redirect_loop', 'La destination est identique à la source : la redirection tournerait en boucle.');
    }
    if ($code !== 410 && strpos($source, '*') === false && hpt_redirects_normalize(hpt_redirects_target_url($target)) === $source) {
        return new WP_Error('hpt_redirect_loop', 'La destination renvoie vers la source : la redirection tournerait en boucle.');
    }
    // Joker : une destination interne couverte par la source (« /blog/* » → « /blog/nouveau/* ») serait redirigée à son tour
    if ($code !== 410 && substr($source, -1) === '*' && hpt_redirects_target_is_internal($target)) {
        $prefix = rtrim(substr($source, 0, -1), '/');
        $target_path = strtok(hpt_redirects_normalize(str_replace('*', '', hpt_redirects_target_url($target))), '?');
        if ($prefix === '' || stripos($target_path . '/', $prefix . '/') === 0) {
            return new WP_Error('hpt_redirect_loop', 'La destination fait partie des adresses redirigées par ce joker : la redirection tournerait en boucle.');
        }
    }

    $data = ['source' => $source, 'target' => $target, 'code' => $code, 'note' => mb_substr(sanitize_text_field((string) $note), 0, 255)];
    $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE source = %s", $source));

    if ($existing && $existing !== (int) $id) {
        if (!$id) {
            // Même source : la règle existante est mise à jour
            $id = $existing;
        } else {
            return new WP_Error('hpt_redirect_duplicate', 'Une autre redirection existe déjà pour cette adresse.');
        }
    }

    if ($id) {
        $wpdb->update($table, $data, ['id' => (int) $id]);
        return (int) $id;
    }

    $data['created'] = current_time('mysql');
    $wpdb->insert($table, $data);
    return (int) $wpdb->insert_id;
}

function hpt_redirects_delete($id)
{
    global $wpdb;
    return (bool) $wpdb->delete(hpt_redirects_table(), ['id' => (int) $id]);
}

/* ---------- Application ---------- */

// Avant la redirection canonique de WordPress (priorité 10)
add_action('template_redirect', function () {
    $request = hpt_redirects_current_request();
    $rule = hpt_redirects_match($request);
    if (!$rule) {
        return;
    }

    global $wpdb;
    $wpdb->query($wpdb->prepare(
        'UPDATE ' . hpt_redirects_table() . ' SET hits = hits + 1, last_hit = %s WHERE id = %d',
        current_time('mysql'),
        $rule->id
    ));

    if ((int) $rule->code === 410) {
        global $wp_query;
        $wp_query->set_404();
        status_header(410);
        nocache_headers();
        // Page de 404 du thème servie telle quelle : ni redirection canonique, ni entrée au journal
        remove_action('template_redirect', 'redirect_canonical');
        $GLOBALS['hpt_redirect_gone'] = true;
        return;
    }

    $url = hpt_redirects_target_url($rule->target);

    // Paramètres de la demande conservés si la destination n'en a pas (campagnes, suivi)
    $query = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    if ($query !== '' && strpos($rule->source, '?') === false && strpos($url, '?') === false) {
        $url .= '?' . $query;
    }

    wp_redirect($url, (int) $rule->code, 'Optimizr');
    exit;
}, 0);

/* ---------- Journal des pages introuvables ---------- */

add_action('template_redirect', function () {
    if (!is_404() || !empty($GLOBALS['hpt_redirect_gone']) || !get_option('hpt_redir_log404', 1)) {
        return;
    }

    $path = hpt_redirects_current_request();

    // Bruit sans intérêt : icônes demandées automatiquement par les navigateurs, fichiers de débogage
    if (preg_match('#^/(favicon\.ico|apple-touch-icon[^/]*\.png)$|\.map$#i', $path)) {
        return;
    }

    $referrer = '';
    $raw = wp_get_raw_referer();
    if ($raw) {
        $parts = wp_parse_url($raw);
        $referrer = mb_substr(($parts['host'] ?? '') . ($parts['path'] ?? ''), 0, 255);
    }

    global $wpdb;
    $now = current_time('mysql');
    $wpdb->query($wpdb->prepare(
        'INSERT INTO ' . hpt_404_table() . ' (path, referrer, hits, first_seen, last_seen) VALUES (%s, %s, 1, %s, %s)
         ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = IF(VALUES(referrer) = \'\', referrer, VALUES(referrer))',
        $path,
        $referrer,
        $now,
        $now
    ));

    // 2 000 adresses au plus : les plus anciennes sont oubliées
    if (wp_rand(1, 50) === 1) {
        $count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . hpt_404_table());
        if ($count > 2000) {
            $wpdb->query('DELETE FROM ' . hpt_404_table() . ' ORDER BY last_seen ASC LIMIT ' . ($count - 2000));
        }
    }
}, 99);

/* ---------- Redirection automatique des contenus déplacés ---------- */

// Adresse avant l'enregistrement…
add_action('pre_post_update', function ($post_id) {
    if (get_post_status($post_id) === 'publish') {
        $GLOBALS['hpt_redirects_old_links'][$post_id] = get_permalink($post_id);
    }
});

// … comparée à l'adresse après l'enregistrement
add_action('post_updated', function ($post_id, $after) {
    if (!get_option('hpt_redir_auto', 1) || empty($GLOBALS['hpt_redirects_old_links'][$post_id])) {
        return;
    }
    if ($after->post_status !== 'publish' || wp_is_post_revision($post_id) || !is_post_type_viewable($after->post_type)) {
        return;
    }

    $old = hpt_redirects_normalize($GLOBALS['hpt_redirects_old_links'][$post_id]);
    $new = hpt_redirects_normalize(get_permalink($post_id));
    if ($old === $new || $old === '/' || strpos($old, '?') !== false) {
        return;
    }

    global $wpdb;
    $table = hpt_redirects_table();
    // Une ancienne règle qui partait de la nouvelle adresse créerait une boucle
    $wpdb->delete($table, ['source' => $new]);
    // Les règles qui pointaient vers l'ancienne adresse vont directement à la nouvelle (pas de chaîne)
    $wpdb->update($table, ['target' => $new], ['target' => $old]);

    hpt_redirects_save($old, $new, 301, 'Automatique : adresse de « ' . get_the_title($post_id) . ' » modifiée');
}, 10, 2);
