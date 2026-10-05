<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Module Redirections : redirections et journal des pages introuvables (404).
 * Optimizr Pro ajoute les jokers, la redirection automatique des contenus déplacés, l'import CSV,
 * l'analyse des liens cassés et lève la limite de redirections.
 *
 * Données dans deux tables dédiées (créées par haptic_redirects_install()) :
 * - {prefix}optimizr_redirects : source, destination, type, visites ;
 * - {prefix}optimizr_404       : adresses introuvables, visites, provenance.
 */

/** Types de redirection et leur libellé (fonction et non constante : libellés traduits, après le chargement des traductions). */
function haptic_redirect_codes()
{
    return [
        301 => __('Permanent (301)', 'optimizr'),
        302 => __('Temporary (302)', 'optimizr'),
        307 => __('Temporary, method preserved (307)', 'optimizr'),
        410 => __('Permanently deleted (410)', 'optimizr'),
    ];
}

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
 * Nombre maximal de redirections (0 : illimité). Version gratuite : 5 ; Optimizr Pro lève la limite.
 * Les redirections déjà enregistrées au-delà continuent de s'appliquer.
 */
function haptic_redirects_limit()
{
    return max(0, (int) apply_filters('haptic_redirects_limit', 5));
}

function haptic_redirects_count()
{
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', haptic_redirects_table()));
}

/** Limite atteinte : plus de nouvelle redirection possible (une règle existante reste modifiable). */
function haptic_redirects_limit_reached()
{
    $limit = haptic_redirects_limit();
    return $limit > 0 && haptic_redirects_count() >= $limit;
}

/**
 * Règle applicable à une adresse : correspondance exacte (avec ou sans requête),
 * puis règles d'une extension (jokers d'Optimizr Pro, filtre haptic_redirects_match).
 *
 * @return object|null Règle, avec la destination déjà résolue
 */
function haptic_redirects_match($request)
{
    global $wpdb;
    $path = strtok($request, '?');

    $rule = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM %i WHERE source IN (%s, %s) ORDER BY CHAR_LENGTH(source) DESC LIMIT 1',
        haptic_redirects_table(),
        $request,
        $path
    ));

    return $rule ?: apply_filters('haptic_redirects_match', null, $request, $path);
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
    $code = isset(haptic_redirect_codes()[(int) $code]) ? (int) $code : 301;
    $target = $code === 410 ? '' : trim(sanitize_text_field((string) $target));

    if ($source === '' || $source === '/') {
        return new WP_Error('haptic_redirect_source', __('Enter the address to redirect (the home page cannot be redirected).', 'optimizr'));
    }
    if ($code !== 410 && $target === '') {
        return new WP_Error('haptic_redirect_target', __('Enter the redirect destination.', 'optimizr'));
    }
    if ($code !== 410 && !preg_match('#^https?://#i', $target)) {
        $target = haptic_redirects_normalize($target);
    }
    if ($code !== 410 && strcasecmp($target, $source) === 0) {
        return new WP_Error('haptic_redirect_loop', __('The destination is the same as the source: the redirect would loop.', 'optimizr'));
    }
    if ($code !== 410 && strpos($source, '*') === false && haptic_redirects_normalize(haptic_redirects_target_url($target)) === $source) {
        return new WP_Error('haptic_redirect_loop', __('The destination leads back to the source: the redirect would loop.', 'optimizr'));
    }
    // Contrôles d'une extension (jokers d'Optimizr Pro) ; sans elle, « * » n'est pas un joker
    $error = apply_filters('haptic_redirects_validate', null, $source, $target, $code);
    if ($error === null && strpos($source, '*') !== false) {
        $error = new WP_Error('haptic_redirect_wildcard', __('Wildcards (*) require Optimizr Pro.', 'optimizr'));
    }
    if (is_wp_error($error)) {
        return $error;
    }

    $data = ['source' => $source, 'target' => $target, 'code' => $code, 'note' => mb_substr(sanitize_text_field((string) $note), 0, 255)];
    $existing = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE source = %s', $table, $source));

    if ($existing && $existing !== (int) $id) {
        if (!$id) {
            // Même source : la règle existante est mise à jour
            $id = $existing;
        } else {
            return new WP_Error('haptic_redirect_duplicate', __('Another redirect already exists for this address.', 'optimizr'));
        }
    }

    if ($id) {
        $wpdb->update($table, $data, ['id' => (int) $id]);
        haptic_redirects_flush_cache();
        // 3e argument : la source d'une règle existante a changé (l'ancienne a pu être un joker)
        do_action('haptic_redirects_saved', (int) $id, $source, $existing !== (int) $id);
        return (int) $id;
    }

    if (haptic_redirects_limit_reached()) {
        return new WP_Error('haptic_redirect_limit', sprintf(
            /* translators: %s: maximum number of redirects */
            _n('The free version is limited to %s redirect. Optimizr Pro removes this limit.', 'The free version is limited to %s redirects. Optimizr Pro removes this limit.', haptic_redirects_limit(), 'optimizr'),
            number_format_i18n(haptic_redirects_limit())
        ));
    }

    $data['created'] = current_time('mysql');
    $wpdb->insert($table, $data);
    $id = (int) $wpdb->insert_id;
    haptic_redirects_flush_cache();
    do_action('haptic_redirects_saved', $id, $source, false);
    return $id;
}

/**
 * Vide le cache des jokers (gardé par Optimizr Pro, recalculé à la demande) : une redirection modifiée
 * pendant qu'Optimizr Pro était désactivé ne doit pas rester dans ce cache.
 */
function haptic_redirects_flush_cache()
{
    delete_transient('haptic_redirects_wildcards');
}

function haptic_redirects_delete($id)
{
    global $wpdb;
    $deleted = (bool) $wpdb->delete(haptic_redirects_table(), ['id' => (int) $id]);
    haptic_redirects_flush_cache();
    do_action('haptic_redirects_deleted', (int) $id);
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
