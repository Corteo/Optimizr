<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Mises à jour depuis GitHub (branche « stable »), pour la version distribuée par GitHub.
 * Ce fichier et assets/plugin-update-checker/ sont retirés de la version
 * WordPress.org (bin/build-wporg.sh), où WordPress gère lui-même les mises à jour.
 */

require_once plugin_dir_path(__DIR__) . '/assets/plugin-update-checker/5.6/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Branche GitHub suivie : toujours « stable » (le choix d'un canal Beta ou Développement a été retiré en 4.0.4).
 * Gardée pour Optimizr Pro, qui suit la même branche.
 */
function haptic_update_channel()
{
    return 'stable';
}

/**
 * Initialise le système de mise à jour du plugin (instance unique par requête).
 */
function haptic_initialize_updater()
{
    static $updater = null;

    if ($updater !== null) {
        return $updater;
    }

    $updater = PucFactory::buildUpdateChecker(
        // Sans « .git » : la librairie en déduit l'URL d'API /repos/Corteo/Optimizr
        'https://github.com/Corteo/Optimizr/',
        HAPTIC_OPTIMIZR_FILE,
        'optimizr'
    );

    $updater->setBranch(haptic_update_channel());

    // Pas de lien « Vérifier les mises à jour » sur la page Extensions : WordPress vérifie de lui-même
    add_filter($updater->getUniqueName('manual_check_link'), '__return_empty_string');

    $token = haptic_get_github_token();
    if ($token) {
        $updater->setAuthentication($token);
    }

    return $updater;
}

// Construit à chaque chargement pour que WordPress détecte les mises à jour automatiquement
add_action('plugins_loaded', 'haptic_initialize_updater');

/**
 * Récupère le token GitHub : constante OPTIMIZR_GITHUB_TOKEN (wp-config.php) en priorité,
 * sinon endpoint distant mis en cache.
 *
 * @return string|false
 */
function haptic_get_github_token()
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
        set_transient($transient_key, '', 15 * MINUTE_IN_SECONDS);
        return false;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
        set_transient($transient_key, '', 15 * MINUTE_IN_SECONDS);
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($body['token'])) {
        set_transient($transient_key, $body['token'], 6 * HOUR_IN_SECONDS);
        return $body['token'];
    }

    set_transient($transient_key, '', 15 * MINUTE_IN_SECONDS);
    return false;
}
