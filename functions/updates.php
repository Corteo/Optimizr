<?php
require_once plugin_dir_path(__DIR__) . '/assets/plugin-update-checker/5.6/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// require_once plugin_dir_path(__DIR__) . '/assets/plugin-update-checker/5.6/load-v5p6.php';

$hpt_updater = PucFactory::buildUpdateChecker(
    'https://github.com/Corteo/Optimizr.git', // Remplace par ton vrai repo
    __FILE__,
    'optimizr'
);

// Récupération dynamique du token GitHub depuis un fichier JSON sécurisé avec auth HTTP
$transient_key = 'optimizr_github_token';
$cached_token = get_transient($transient_key);

if ($cached_token === false) {
    $response = wp_remote_get('https://cdn.alexandrevergnaud.fr/token.php', [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode('opti_haptic:mu0j58nDcLTGIr1UeNrA'),
        ]
    ]);

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['token'])) {
            $cached_token = $body['token'];
            set_transient($transient_key, $cached_token, 6 * HOUR_IN_SECONDS);
        }
    }
}

if (!empty($cached_token)) {
    error_log('[Optimizr] Token GitHub récupéré : ' . substr($cached_token, 0, 8) . '...');

    $hpt_updater->setAuthentication($cached_token);
}

// Optionnel : si ton plugin est en mode "release avec tag", active cette option :
$hpt_updater->setBranch('stable'); // ou 'stable', ou 'master', selon ton repo
// $hpt_updater->getVcsApi()->enableReleaseAssets(); // pour utiliser les assets de release GitHub

function haptic_GPT_check_for_updates()
{
    if (isset($_POST['hpt_check_update'])) {
        if (class_exists('\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
            $checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                'https://github.com/Corteo/Optimizr.git',
                plugin_dir_path(__DIR__) . 'hpt_options.php',
                'optimizr'
            );

            $token = get_transient('optimizr_github_token');
            if (!empty($token)) {
                $checker->setAuthentication($token);
            }

            $checker->setBranch('stable');
            $update = $checker->checkForUpdates();
            if ($update && !empty($update->new_version)) {
                echo '<div class="notice notice-warning"><p>Une mise à jour est disponible : <strong>' . esc_html($update->new_version) . '</strong>.<br>';
                echo 'Détails : ' . esc_html($update->details_url) . '</p>';
                echo '<form method="post"><input type="submit" name="hpt_do_update" class="button button-primary" value="Mettre à jour maintenant"></form></div>';
            } else {
                echo '<div class="notice notice-success"><p>Aucune mise à jour disponible.</p></div>';
            }
        }
    }
}
