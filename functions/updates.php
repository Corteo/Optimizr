<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Mises à jour depuis GitHub (canaux Stable, Beta, Développement), pour la version distribuée par GitHub.
 * Ce fichier, js/optimizr-updates.js et assets/plugin-update-checker/ sont retirés de la version
 * WordPress.org (bin/build-wporg.sh), où WordPress gère lui-même les mises à jour.
 */

require_once plugin_dir_path(__DIR__) . '/assets/plugin-update-checker/5.6/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Canaux de mise à jour : chacun correspond à la branche GitHub du même nom.
 * « beta » reçoit aussi les versions finales (elles sont publiées sur les deux branches, cf. README.md).
 * « main » suit la branche de développement, pour tester avant de publier sur stable ou beta.
 */
function haptic_update_channel_slugs()
{
    // Sans libellé traduit : lu dès plugins_loaded (haptic_initialize_updater), avant le chargement des traductions
    return ['stable', 'beta', 'main'];
}

function haptic_update_channels()
{
    return [
        'stable' => _x('Stable', 'update channel', 'optimizr'),
        'beta'   => _x('Beta', 'update channel', 'optimizr'),
        'main'   => _x('Development (main)', 'update channel', 'optimizr'),
    ];
}

/** Canal suivi. Sur un multisite, celui du site principal (les mises à jour concernent tout le réseau). */
function haptic_update_channel()
{
    $channel = is_multisite() ? get_blog_option(get_main_site_id(), 'haptic_update_channel') : get_option('haptic_update_channel');
    return in_array($channel, haptic_update_channel_slugs(), true) ? $channel : 'stable';
}

function haptic_sanitize_update_channel($value)
{
    // Choix réservé à qui peut installer les mises à jour (sinon on garde la valeur actuelle)
    if (!current_user_can('update_plugins')) {
        return get_option('haptic_update_channel', 'stable');
    }
    return in_array($value, haptic_update_channel_slugs(), true) ? $value : 'stable';
}

add_action('admin_init', function () {
    register_setting(haptic_settings_group('dashboard'), 'haptic_update_channel', [
        'type'              => 'string',
        'sanitize_callback' => 'haptic_sanitize_update_channel',
    ]);
});

// En-tête de la page d'options : bouton « Vérifier les mises à jour » (script : js/optimizr-updates.js)
add_action('haptic_header_actions', function () {
    ?>
    <span id="haptic-update-status" class="optimizr-header__status" role="status"></span>
    <button type="button" class="optimizr-btn optimizr-btn--secondary" id="haptic-check-update-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('haptic_check_update_action')); ?>" data-update-nonce="<?php echo esc_attr(wp_create_nonce('updates')); ?>" data-plugin="<?php echo esc_attr(plugin_basename(HAPTIC_OPTIMIZR_FILE)); ?>">
        <?php echo wp_kses(haptic_icon('refresh', 16), haptic_kses_ui()); ?><?php esc_html_e('Check for updates', 'optimizr'); ?>
    </button>
    <?php
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== HAPTIC_OPTIMIZR_ADMIN_HOOK) {
        return;
    }
    $js = HAPTIC_OPTIMIZR_PATH . 'js/optimizr-updates.js';
    wp_enqueue_script('optimizr-updates', HAPTIC_OPTIMIZR_URL . 'js/optimizr-updates.js', ['wp-i18n'], filemtime($js), true);
    wp_set_script_translations('optimizr-updates', 'optimizr', HAPTIC_OPTIMIZR_PATH . 'languages');
});

// Carte « Mises à jour » du tableau de bord : canal suivi
add_action('haptic_dashboard_updates', function () {
    ?>
    <dl class="optimizr-dl">
        <dt><?php echo esc_html_x('Channel', 'update channel', 'optimizr'); ?></dt>
        <dd><?php echo esc_html(haptic_update_channels()[haptic_update_channel()]); ?></dd>
    </dl>
    <?php if (current_user_can('update_plugins') && (!is_multisite() || is_main_site())) : ?>
        <label class="optimizr-field__label" for="haptic_update_channel"><?php esc_html_e('Followed channel', 'optimizr'); ?></label>
        <select name="haptic_update_channel" id="haptic_update_channel">
            <?php foreach (haptic_update_channels() as $channel => $label) : ?>
                <option value="<?php echo esc_attr($channel); ?>" <?php selected(haptic_update_channel(), $channel); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="optimizr-card__hint"><?php
        /* translators: %s: name of the development branch, "main" */
        echo wp_kses(sprintf(__('Beta: test versions in addition to final releases. Development: every version merged into %s, before release. Only for a test site. Switching back to Stable does not reinstall the previous version: the next final release will be offered.', 'optimizr'), '<code>main</code>'), ['code' => []]);
        ?></p>
    <?php endif; ?>
    <p class="optimizr-card__hint"><?php esc_html_e('The “Check for updates” button at the top of the page queries GitHub immediately, then “Update now” installs the new version without leaving the page.', 'optimizr'); ?></p>
    <?php
});

// Changement de canal : on oublie la dernière vérification, la suivante lit la nouvelle branche
add_action('update_option_haptic_update_channel', 'haptic_update_channel_reset');
add_action('add_option_haptic_update_channel', 'haptic_update_channel_reset');
function haptic_update_channel_reset()
{
    delete_site_option('external_updates-optimizr');
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

    // Sur « main », la librairie lirait la dernière release GitHub (donc la version stable) :
    // on lui fait lire la branche elle-même, comme pour stable et beta.
    $updater->addFilter('vcs_update_detection_strategies', function ($strategies, $slug) {
        if ($slug === 'optimizr' && haptic_update_channel() === 'main' && isset($strategies['branch'])) {
            return ['branch' => $strategies['branch']];
        }
        return $strategies;
    }, 10, 2);

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

add_action('wp_ajax_haptic_ajax_check_update', 'haptic_ajax_check_github_updates');

function haptic_ajax_check_github_updates()
{
    check_ajax_referer('haptic_check_update_action', 'nonce');

    if (!current_user_can('update_plugins')) {
        wp_send_json_error(['status' => 'error', 'message' => __('Insufficient permissions', 'optimizr')]);
    }

    $checker = haptic_initialize_updater();

    try {
        $update = $checker->checkForUpdates();

        // La librairie renvoie un objet Update : le numéro est dans « version » (« new_version » n'existe qu'au format WordPress)
        if ($update && !empty($update->version)) {
            wp_send_json_success([
                'status' => 'update_available',
                'new_version' => $update->version,
                'details_url' => self_admin_url('update-core.php'),
            ]);
        }

        // Dépôt injoignable ou jeton refusé : le signaler plutôt qu'annoncer « à jour »
        $errors = $checker->getLastRequestApiErrors();
        if ($errors) {
            $message = $errors[0]['error'] instanceof WP_Error ? $errors[0]['error']->get_error_message() : __('GitHub unreachable', 'optimizr');
            wp_send_json_error([
                'status' => 'error',
                'message' => $message,
            ]);
        } else {
            wp_send_json_success([
                'status' => 'no_update',
            ]);
        }
    } catch (Exception $e) {
        wp_send_json_error([
            'status' => 'error',
            /* translators: %s: error message */
            'message' => sprintf(__('Error: %s', 'optimizr'), $e->getMessage()),
        ]);
    }
}
