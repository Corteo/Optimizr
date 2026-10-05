<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Liens cassés : recherche des liens et des images introuvables dans les contenus publiés.
 *
 * Deux étapes pilotées par le navigateur (action AJAX haptic_links_scan, js/optimizr-admin.js) :
 * 1. « collect » rassemble les adresses uniques et les contenus qui les citent ;
 * 2. « check » en vérifie quelques-unes par requête, jusqu'à la fin de la liste.
 * Le dernier rapport est conservé dans l'option haptic_links_report.
 */

/** Nombre maximal d'adresses vérifiées par analyse. */
const HAPTIC_LINKS_MAX = 2000;

/**
 * Adresses vérifiées par requête AJAX : au pire 2 × (5 s HEAD + 5 s GET), loin des délais
 * d'exécution courants (30 s pour PHP, 60 s pour nginx).
 */
const HAPTIC_LINKS_BATCH = 2;

/** Types de contenu analysés : ceux du sitemap s'il est configuré, sinon articles et pages. */
function haptic_links_post_types()
{
    return array_values(array_filter((array) get_option('haptic_sitemap_post_types', ['post', 'page'])));
}

/**
 * Adresses des liens (href) et des images (src) d'un contenu, en URL absolues.
 *
 * @return string[]
 */
function haptic_links_extract($html)
{
    preg_match_all('#<(?:a|img)\b[^>]*?\s(?:href|src)\s*=\s*(["\'])(.*?)\1#is', (string) $html, $matches);

    $urls = [];
    foreach ($matches[2] as $url) {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || $url[0] === '#' || preg_match('#^(mailto|tel|javascript|data|sms):#i', $url)) {
            continue;
        }
        // « http:/site.fr » : les navigateurs le lisent comme « http://site.fr »
        $url = preg_replace('#^(https?):/*(?=[^/])#i', '$1://', $url);
        if (strpos($url, '//') === 0) {
            $url = (is_ssl() ? 'https:' : 'http:') . $url;
        } elseif (!preg_match('#^https?://#i', $url)) {
            $url = home_url('/' . ltrim($url, '/'));
        }
        // L'ancre n'a pas d'incidence sur l'existence de la page
        $urls[] = strtok($url, '#');
    }

    return array_unique($urls);
}

function haptic_links_is_internal($url)
{
    return strcasecmp((string) wp_parse_url($url, PHP_URL_HOST), (string) wp_parse_url(home_url(), PHP_URL_HOST)) === 0;
}

/**
 * Vérifie une adresse.
 *
 * @return array ['state' => ok|broken|blocked|error, 'code' => int, 'label' => string]
 */
function haptic_links_check($url)
{
    $internal = haptic_links_is_internal($url);

    // Domaine accentué d'un autre site : forme ASCII (punycode), seule résolue par wp_http_validate_url()
    // (celui du site, même accentué, est reconnu tel quel)
    $host = (string) wp_parse_url($url, PHP_URL_HOST);
    if (!$internal && preg_match('/[^\x00-\x7F]/', $host) && class_exists('WpOrg\Requests\IdnaEncoder')) {
        try {
            $url = substr_replace($url, \WpOrg\Requests\IdnaEncoder::encode($host), strpos($url, $host), strlen($host));
        } catch (Exception $e) {
            // Domaine invalide : refusé ci-dessous
        }
    }

    // Liens saisis par les auteurs : jamais de requête vers le réseau local du serveur ni vers un port inhabituel
    // (wp_http_validate_url, comme wp_safe_remote_* ; les adresses du site lui-même restent autorisées)
    if (!wp_http_validate_url($url)) {
        return ['state' => 'error', 'code' => 0, 'label' => 'Inaccessible (domaine introuvable ou adresse non autorisée)'];
    }

    $args = [
        'timeout'     => 5,
        'redirection' => 5,
        'user-agent'  => 'Mozilla/5.0 (compatible; Optimizr-LinkChecker; +' . home_url('/') . ')',
        // Sites locaux et certificats auto-signés de préproduction
        'sslverify'   => !$internal,
    ];

    // Versions « safe » : chaque redirection suivie est contrôlée de la même façon
    $response = wp_safe_remote_head($url, $args);
    $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);

    // Certains serveurs refusent HEAD : seconde tentative en GET
    if (is_wp_error($response) || in_array($code, [403, 405, 501], true)) {
        $response = wp_safe_remote_get($url, $args + ['limit_response_size' => 4096]);
        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
    }

    if (is_wp_error($response)) {
        return ['state' => 'error', 'code' => 0, 'label' => 'Inaccessible (' . $response->get_error_message() . ')'];
    }
    if ($code >= 200 && $code < 400) {
        return ['state' => 'ok', 'code' => $code, 'label' => (string) $code];
    }
    if (in_array($code, [401, 403, 429], true)) {
        // Protection anti-robots fréquente sur les sites externes : à vérifier à la main
        return ['state' => 'blocked', 'code' => $code, 'label' => $code . ' — accès refusé au vérificateur, à contrôler'];
    }
    $labels = [404 => 'Page introuvable', 410 => 'Supprimée définitivement'];
    return ['state' => 'broken', 'code' => $code, 'label' => $code . ' — ' . ($labels[$code] ?? ($code >= 500 ? 'Erreur du serveur' : 'Erreur'))];
}

add_action('wp_ajax_haptic_links_scan', function () {
    check_ajax_referer('haptic_links_scan', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permissions insuffisantes.');
    }

    $step = sanitize_key($_POST['step'] ?? '');

    if ($step === 'collect') {
        $queue = [];
        $page = 1;
        do {
            $posts = get_posts([
                'post_type' => haptic_links_post_types(),
                'post_status' => 'publish',
                'numberposts' => 200,
                'paged' => $page++,
                'suppress_filters' => false,
            ]);
            foreach ($posts as $post) {
                foreach (haptic_links_extract($post->post_content) as $url) {
                    if (count($queue) >= HAPTIC_LINKS_MAX && !isset($queue[$url])) {
                        continue;
                    }
                    $queue[$url][] = $post->ID;
                }
            }
        } while (count($posts) === 200);

        update_option('haptic_links_queue', $queue, false);
        update_option('haptic_links_report', ['date' => current_time('mysql'), 'checked' => 0, 'total' => count($queue), 'running' => true, 'issues' => []], false);

        wp_send_json_success(['total' => count($queue)]);
    }

    if ($step === 'check') {
        $queue = (array) get_option('haptic_links_queue', []);
        $report = (array) get_option('haptic_links_report', []);
        $batch = array_slice($queue, 0, HAPTIC_LINKS_BATCH, true);
        $found = [];

        foreach ($batch as $url => $sources) {
            $result = haptic_links_check($url);
            if ($result['state'] !== 'ok') {
                $issue = [
                    'url'      => $url,
                    'internal' => haptic_links_is_internal($url),
                    'sources'  => array_values(array_unique(array_map('intval', $sources))),
                ] + $result;
                $report['issues'][] = $issue;
                // Ligne prête à afficher, mise en forme par le même gabarit que la liste enregistrée
                ob_start();
                haptic_links_render_row($issue);
                $found[] = ob_get_clean();
            }
            unset($queue[$url]);
        }

        $report['checked'] = (int) ($report['checked'] ?? 0) + count($batch);
        $report['running'] = !empty($queue);
        update_option('haptic_links_queue', $queue, false);
        update_option('haptic_links_report', $report, false);

        wp_send_json_success([
            'checked'   => $report['checked'],
            'total'     => (int) $report['total'],
            'remaining' => count($queue),
            'issues'    => $found,
        ]);
    }

    wp_send_json_error('Étape inconnue.');
});
