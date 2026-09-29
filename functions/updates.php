<?php

if (!defined('ABSPATH')) {
    exit;
}

// Le bouton de vérification est dans l'en-tête de la page d'options (functions/admin-ui.php),
// son script dans js/optimizr-admin.js.

require_once plugin_dir_path(__DIR__) . '/assets/plugin-update-checker/5.6/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Initialise le système de mise à jour du plugin (instance unique par requête).
 */
function haptic_GPT_initialize_updater()
{
    static $updater = null;

    if ($updater !== null) {
        return $updater;
    }

    $updater = PucFactory::buildUpdateChecker(
        // Sans « .git » : la librairie en déduit l'URL d'API /repos/Corteo/Optimizr
        'https://github.com/Corteo/Optimizr/',
        plugin_dir_path(__DIR__) . 'hpt_options.php',
        'optimizr'
    );

    $updater->setBranch('stable');

    $token = hpt_get_github_token();
    if ($token) {
        $updater->setAuthentication($token);
    }

    return $updater;
}

// Construit à chaque chargement pour que WordPress détecte les mises à jour automatiquement
add_action('plugins_loaded', 'haptic_GPT_initialize_updater');

/**
 * Récupère le token GitHub : constante OPTIMIZR_GITHUB_TOKEN (wp-config.php) en priorité,
 * sinon endpoint distant mis en cache.
 *
 * @return string|false
 */
function hpt_get_github_token()
{
    if (defined('OPTIMIZR_GITHUB_TOKEN') && OPTIMIZR_GITHUB_TOKEN) {
        return OPTIMIZR_GITHUB_TOKEN;
    }

    $transient_key = 'optimizr_github_token';
    $cached_token = get_transient($transient_key);

    if ($cached_token !== false) {
        // Chaîne vide = échec récent mis en cache
        return $cached_token !== '' ? $cached_token : false;
    }

    $response = wp_remote_get('https://cdn.alexandrevergnaud.fr/opti/token.php', ['timeout' => 5]);

    if (is_wp_error($response)) {
        hpt_log('[Updates] Erreur lors de la récupération du token : ' . $response->get_error_message());
        set_transient($transient_key, '', 15 * MINUTE_IN_SECONDS);
        return false;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
        hpt_log('[Updates] Code HTTP inattendu lors de la récupération du token : ' . wp_remote_retrieve_response_code($response));
        set_transient($transient_key, '', 15 * MINUTE_IN_SECONDS);
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($body['token'])) {
        set_transient($transient_key, $body['token'], 6 * HOUR_IN_SECONDS);
        return $body['token'];
    }

    hpt_log('[Updates] Réponse invalide : token manquant');
    set_transient($transient_key, '', 15 * MINUTE_IN_SECONDS);
    return false;
}

add_action('wp_ajax_hpt_ajax_check_update', 'haptic_GPT_ajax_check_github_updates');

function haptic_GPT_ajax_check_github_updates()
{
    check_ajax_referer('hpt_check_update_action', 'nonce');

    if (!current_user_can('update_plugins')) {
        wp_send_json_error(['status' => 'error', 'message' => 'Permissions insuffisantes']);
    }

    $checker = haptic_GPT_initialize_updater();
    hpt_log('[AJAX Updates] Lancement de la vérification via AJAX.');

    try {
        $update = $checker->checkForUpdates();

        if ($update && !empty($update->new_version)) {
            hpt_log('[AJAX Updates] Nouvelle version disponible : ' . $update->new_version);
            wp_send_json_success([
                'status' => 'update_available',
                'new_version' => $update->new_version,
                'details_url' => $update->details_url,
            ]);
        } else {
            hpt_log('[AJAX Updates] Aucune mise à jour détectée.');
            wp_send_json_success([
                'status' => 'no_update',
            ]);
        }
    } catch (Exception $e) {
        hpt_log('[AJAX Updates] Erreur lors de la vérification : ' . $e->getMessage());
        wp_send_json_error([
            'status' => 'error',
            'message' => 'Erreur : ' . $e->getMessage(),
        ]);
    }
}
