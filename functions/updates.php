<?php

if (!defined('ABSPATH')) {
    exit;
}

// Le bouton de vérification est dans l'en-tête de la page d'options (functions/admin-ui.php),
// son script dans js/optimizr-admin.js.

require_once plugin_dir_path(__DIR__) . '/assets/plugin-update-checker/5.6/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Canaux de mise à jour : chacun correspond à la branche GitHub du même nom.
 * « beta » reçoit aussi les versions finales (elles sont publiées sur les deux branches, cf. README.md).
 * « main » suit la branche de développement, pour tester avant de publier sur stable ou beta.
 */
function hpt_update_channels()
{
    return ['stable' => 'Stable', 'beta' => 'Bêta', 'main' => 'Développement (main)'];
}

/** Canal suivi. Sur un multisite, celui du site principal (les mises à jour concernent tout le réseau). */
function hpt_update_channel()
{
    $channel = is_multisite() ? get_blog_option(get_main_site_id(), 'hpt_update_channel') : get_option('hpt_update_channel');
    return isset(hpt_update_channels()[$channel]) ? $channel : 'stable';
}

function hpt_sanitize_update_channel($value)
{
    // Choix réservé à qui peut installer les mises à jour (sinon on garde la valeur actuelle)
    if (!current_user_can('update_plugins')) {
        return get_option('hpt_update_channel', 'stable');
    }
    return isset(hpt_update_channels()[$value]) ? $value : 'stable';
}

// Changement de canal : on oublie la dernière vérification, la suivante lit la nouvelle branche
add_action('update_option_hpt_update_channel', 'hpt_update_channel_reset');
add_action('add_option_hpt_update_channel', 'hpt_update_channel_reset');
function hpt_update_channel_reset()
{
    delete_site_option('external_updates-optimizr');
}

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

    $updater->setBranch(hpt_update_channel());

    // Sur « main », la librairie lirait la dernière release GitHub (donc la version stable) :
    // on lui fait lire la branche elle-même, comme pour stable et beta.
    $updater->addFilter('vcs_update_detection_strategies', function ($strategies, $slug) {
        if ($slug === 'optimizr' && hpt_update_channel() === 'main' && isset($strategies['branch'])) {
            return ['branch' => $strategies['branch']];
        }
        return $strategies;
    }, 10, 2);

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

        // La librairie renvoie un objet Update : le numéro est dans « version » (« new_version » n'existe qu'au format WordPress)
        if ($update && !empty($update->version)) {
            hpt_log('[AJAX Updates] Nouvelle version disponible : ' . $update->version);
            wp_send_json_success([
                'status' => 'update_available',
                'new_version' => $update->version,
                'details_url' => self_admin_url('update-core.php'),
            ]);
        }

        // Dépôt injoignable ou jeton refusé : le signaler plutôt qu'annoncer « à jour »
        $errors = $checker->getLastRequestApiErrors();
        if ($errors) {
            $message = $errors[0]['error'] instanceof WP_Error ? $errors[0]['error']->get_error_message() : 'GitHub injoignable';
            hpt_log('[AJAX Updates] Erreur lors de la vérification : ' . $message);
            wp_send_json_error([
                'status' => 'error',
                'message' => $message,
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
