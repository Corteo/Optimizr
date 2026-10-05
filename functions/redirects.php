<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Module Redirections & liens : redirections, journal des pages introuvables (404),
 * redirection automatique des contenus dont l'adresse change.
 *
 * Données dans deux tables dédiées (créées par haptic_redirects_install()) :
 * - {prefix}optimizr_redirects : source, destination, type, visites ;
 * - {prefix}optimizr_404       : adresses introuvables, visites, provenance.
 */

const HAPTIC_REDIRECT_CODES = [
    301 => 'Permanente (301)',
    302 => 'Temporaire (302)',
    307 => 'Temporaire, méthode conservée (307)',
    410 => 'Supprimée définitivement (410)',
];

/* ---------- Adresses ---------- */

/**
 * Adresse normalisée, relative au site : « /chemin » (sans barre finale) et sa requête éventuelle.
 * Accepte une URL complète du site, un chemin, ou un chemin avec « ?requête ».
 * Valeur déjà sans barres obliques inverses ajoutées par WordPress : wp_unslash() est à la charge de l'appelant ($_POST, $_SERVER…).
 */
function haptic_redirects_normalize($url)
{
    $url = trim((string) $url);
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
function haptic_redirects_current_request()
{
    return haptic_redirects_normalize(wp_strip_all_tags(wp_unslash($_SERVER['REQUEST_URI'] ?? '/')));
}

/** URL complète de destination (chemin du site ou adresse externe). */
function haptic_redirects_target_url($target)
{
    $target = trim((string) $target);
    if ($target === '' || preg_match('#^https?://#i', $target)) {
        return $target;
    }
    return home_url('/' . ltrim($target, '/'));
}

/** Destination sur ce site (chemin relatif, ou URL complète du même domaine). */
function haptic_redirects_target_is_internal($target)
{
    if (!preg_match('#^https?://#i', (string) $target)) {
        return true;
    }
    return strcasecmp((string) wp_parse_url($target, PHP_URL_HOST), (string) wp_parse_url(home_url(), PHP_URL_HOST)) === 0;
}

/* ---------- Règles ---------- */

/**
 * Règles à joker « /chemin/* », la plus longue source en premier.
 * Gardées en cache (transient sans expiration, chargé avec les options) : pas de parcours de la table à chaque page.
 *
 * @return array[] id, source, target, code
 */
function haptic_redirects_wildcards()
{
    $rules = get_transient('haptic_redirects_wildcards');
    return is_array($rules) ? $rules : haptic_redirects_refresh_cache();
}

/** Recalcule le cache des jokers : à appeler après chaque écriture dans la table des redirections. */
function haptic_redirects_refresh_cache()
{
    global $wpdb;
    $rules = (array) $wpdb->get_results($wpdb->prepare('SELECT id, source, target, code FROM %i WHERE source LIKE %s ORDER BY CHAR_LENGTH(source) DESC', haptic_redirects_table(), '%*'), ARRAY_A);
    // Table inaccessible : rien en cache, nouvel essai à la prochaine page
    if (!$wpdb->last_error) {
        set_transient('haptic_redirects_wildcards', $rules);
    }
    return $rules;
}

/**
 * Règle applicable à une adresse : correspondance exacte (avec ou sans requête), puis jokers « /chemin/* ».
 *
 * @return object|null Règle, avec la destination déjà résolue pour un joker
 */
function haptic_redirects_match($request)
{
    global $wpdb;
    $table = haptic_redirects_table();
    $path = strtok($request, '?');

    $rule = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM %i WHERE source IN (%s, %s) ORDER BY CHAR_LENGTH(source) DESC LIMIT 1',
        $table,
        $request,
        $path
    ));
    if ($rule) {
        return $rule;
    }

    // Jokers : la plus longue source qui correspond l'emporte
    foreach (haptic_redirects_wildcards() as $rule) {
        $rule = (object) $rule;
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
 * Ajoute ou modifie une règle. Valeurs sans barres obliques inverses de WordPress (wp_unslash() par l'appelant).
 *
 * @return int|WP_Error Identifiant de la règle
 */
function haptic_redirects_save($source, $target, $code = 301, $note = '', $id = 0)
{
    global $wpdb;
    $table = haptic_redirects_table();

    $source = haptic_redirects_normalize($source);
    $code = isset(HAPTIC_REDIRECT_CODES[(int) $code]) ? (int) $code : 301;
    $target = $code === 410 ? '' : trim(sanitize_text_field((string) $target));

    if ($source === '' || $source === '/') {
        return new WP_Error('haptic_redirect_source', 'Indiquez l\'adresse à rediriger (la page d\'accueil ne peut pas l\'être).');
    }
    if ($code !== 410 && $target === '') {
        return new WP_Error('haptic_redirect_target', 'Indiquez la destination de la redirection.');
    }
    if ($code !== 410 && !preg_match('#^https?://#i', $target)) {
        $target = haptic_redirects_normalize($target);
    }
    if ($code !== 410 && strcasecmp($target, $source) === 0) {
        return new WP_Error('haptic_redirect_loop', 'La destination est identique à la source : la redirection tournerait en boucle.');
    }
    if ($code !== 410 && strpos($source, '*') === false && haptic_redirects_normalize(haptic_redirects_target_url($target)) === $source) {
        return new WP_Error('haptic_redirect_loop', 'La destination renvoie vers la source : la redirection tournerait en boucle.');
    }
    // Joker : une destination interne couverte par la source (« /blog/* » → « /blog/nouveau/* ») serait redirigée à son tour
    if ($code !== 410 && substr($source, -1) === '*' && haptic_redirects_target_is_internal($target)) {
        $prefix = rtrim(substr($source, 0, -1), '/');
        $target_path = strtok(haptic_redirects_normalize(str_replace('*', '', haptic_redirects_target_url($target))), '?');
        if ($prefix === '' || stripos($target_path . '/', $prefix . '/') === 0) {
            return new WP_Error('haptic_redirect_loop', 'La destination fait partie des adresses redirigées par ce joker : la redirection tournerait en boucle.');
        }
    }

    $data = ['source' => $source, 'target' => $target, 'code' => $code, 'note' => mb_substr(sanitize_text_field((string) $note), 0, 255)];
    $existing = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE source = %s', $table, $source));

    if ($existing && $existing !== (int) $id) {
        if (!$id) {
            // Même source : la règle existante est mise à jour
            $id = $existing;
        } else {
            return new WP_Error('haptic_redirect_duplicate', 'Une autre redirection existe déjà pour cette adresse.');
        }
    }

    if ($id) {
        $wpdb->update($table, $data, ['id' => (int) $id]);
        // Jokers recalculés pour un joker, ou si la source a changé (l'ancienne a pu en être un) : rien par règle exacte réimportée
        if (strpos($source, '*') !== false || $existing !== (int) $id) {
            haptic_redirects_refresh_cache();
        }
        return (int) $id;
    }

    $data['created'] = current_time('mysql');
    $wpdb->insert($table, $data);
    $id = (int) $wpdb->insert_id;
    // Nouvelle règle exacte : jokers inchangés (import CSV sans requête superflue)
    if (strpos($source, '*') !== false) {
        haptic_redirects_refresh_cache();
    }
    return $id;
}

function haptic_redirects_delete($id)
{
    global $wpdb;
    $deleted = (bool) $wpdb->delete(haptic_redirects_table(), ['id' => (int) $id]);
    haptic_redirects_refresh_cache();
    return $deleted;
}

/* ---------- Application ---------- */

// Avant la redirection canonique de WordPress (priorité 10)
add_action('template_redirect', function () {
    $request = haptic_redirects_current_request();
    $rule = haptic_redirects_match($request);
    if (!$rule) {
        return;
    }

    global $wpdb;
    $wpdb->query($wpdb->prepare(
        'UPDATE %i SET hits = hits + 1, last_hit = %s WHERE id = %d',
        haptic_redirects_table(),
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
        $GLOBALS['haptic_redirect_gone'] = true;
        return;
    }

    $url = haptic_redirects_target_url($rule->target);

    // Paramètres de la demande conservés si la destination n'en a pas (campagnes, suivi)
    $query = (string) wp_parse_url(wp_strip_all_tags(wp_unslash($_SERVER['REQUEST_URI'] ?? '')), PHP_URL_QUERY);
    if ($query !== '' && strpos($rule->source, '?') === false && strpos($url, '?') === false) {
        $url .= '?' . $query;
    }

    wp_redirect($url, (int) $rule->code, 'Optimizr');
    exit;
}, 0);

/* ---------- Journal des pages introuvables ---------- */

add_action('template_redirect', function () {
    if (!is_404() || !empty($GLOBALS['haptic_redirect_gone']) || !get_option('haptic_redir_log404', 1)) {
        return;
    }

    // Chemin seul : la requête (« ?… ») peut contenir des données personnelles (adresse e-mail, jeton)
    $path = strtok(haptic_redirects_current_request(), '?');

    // Bruit sans intérêt : icônes demandées automatiquement par les navigateurs, fichiers de débogage,
    // page d'accueil avec une requête (« /?author=… », « /?p=… ») : sans celle-ci, adresse impossible à rediriger
    if ($path === '/' || preg_match('#^/(favicon\.ico|apple-touch-icon[^/]*\.png)$|\.map$#i', $path)) {
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
        'INSERT INTO %i (path, referrer, hits, first_seen, last_seen) VALUES (%s, %s, 1, %s, %s)
         ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = IF(VALUES(referrer) = \'\', referrer, VALUES(referrer))',
        haptic_404_table(),
        $path,
        $referrer,
        $now,
        $now
    ));

    // 2 000 adresses au plus : les plus anciennes sont oubliées
    if (wp_rand(1, 50) === 1) {
        $count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', haptic_404_table()));
        if ($count > 2000) {
            $wpdb->query($wpdb->prepare('DELETE FROM %i ORDER BY last_seen ASC LIMIT %d', haptic_404_table(), $count - 2000));
        }
    }
}, 99);

/* ---------- Redirection automatique des contenus déplacés ---------- */

/**
 * Contenus publiés placés sous un contenu hiérarchique (pages enfants, petits-enfants…) et leur adresse actuelle.
 *
 * @return array identifiant => adresse
 */
function haptic_redirects_descendant_links(WP_Post $post)
{
    $links = [];
    $seen = [$post->ID => true];
    $parents = [$post->ID];
    while ($parents) {
        $children = get_posts([
            'post_type'              => $post->post_type,
            'post_status'            => 'any',
            'post_parent__in'        => $parents,
            'numberposts'            => -1,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $parents = [];
        foreach ($children as $child) {
            // Hiérarchie en boucle (données corrompues) : chaque contenu une seule fois
            if (isset($seen[$child->ID])) {
                continue;
            }
            $seen[$child->ID] = true;
            // Un brouillon peut avoir des enfants publiés : on descend quand même
            $parents[] = $child->ID;
            if ($child->post_status === 'publish') {
                $links[$child->ID] = get_permalink($child);
            }
        }
    }
    return $links;
}

/** Redirige l'ancienne adresse d'un contenu vers la nouvelle (301), sans boucle ni chaîne. */
function haptic_redirects_auto($old_link, $new_link, $post_id)
{
    $old = haptic_redirects_normalize($old_link);
    $new = haptic_redirects_normalize($new_link);
    if ($old === $new || $old === '/' || strpos($old, '?') !== false) {
        return;
    }

    global $wpdb;
    $table = haptic_redirects_table();
    // Une ancienne règle qui partait de la nouvelle adresse créerait une boucle
    $wpdb->delete($table, ['source' => $new]);
    // Les règles qui pointaient vers l'ancienne adresse vont directement à la nouvelle (pas de chaîne)
    $repointed = $wpdb->update($table, ['target' => $new], ['target' => $old]);

    haptic_redirects_save($old, $new, 301, 'Automatique : adresse de « ' . get_the_title($post_id) . ' » modifiée');
    // Un joker a pu changer de destination ci-dessus
    if ($repointed) {
        haptic_redirects_refresh_cache();
    }
}

// Adresse avant l'enregistrement…
add_action('pre_post_update', function ($post_id, $data = []) {
    if (get_post_status($post_id) !== 'publish') {
        return;
    }
    $GLOBALS['haptic_redirects_old_links'][$post_id] = get_permalink($post_id);

    // … et celle des pages enfants, qui contient l'identifiant (slug) du parent : elle change avec lui
    $post = get_post($post_id);
    $moved = (isset($data['post_name']) && $data['post_name'] !== $post->post_name)
        || (isset($data['post_parent']) && (int) $data['post_parent'] !== (int) $post->post_parent);
    $GLOBALS['haptic_redirects_old_children'][$post_id] = $moved && get_option('haptic_redir_auto', 1) && is_post_type_hierarchical($post->post_type)
        ? haptic_redirects_descendant_links($post)
        : [];
}, 10, 2);

// … comparée à l'adresse après l'enregistrement
add_action('post_updated', function ($post_id, $after) {
    if (!get_option('haptic_redir_auto', 1) || empty($GLOBALS['haptic_redirects_old_links'][$post_id])) {
        return;
    }
    if ($after->post_status !== 'publish' || wp_is_post_revision($post_id) || !is_post_type_viewable($after->post_type)) {
        return;
    }

    haptic_redirects_auto($GLOBALS['haptic_redirects_old_links'][$post_id], get_permalink($post_id), $post_id);

    // Pages enfants : nouvelle adresse calculée avec celle du parent, déjà enregistrée
    foreach ($GLOBALS['haptic_redirects_old_children'][$post_id] ?? [] as $child_id => $old_link) {
        haptic_redirects_auto($old_link, get_permalink($child_id), $child_id);
    }
}, 10, 2);
