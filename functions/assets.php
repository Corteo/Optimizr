<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'haptic_options_enqueue_scripts');
add_action('admin_enqueue_scripts', 'haptic_options_enqueue_admin_scripts');

/**
 * Enregistre la feuille des jetons de design communs (css/optimizr-tokens.css) et renvoie son identifiant,
 * à passer en dépendance des autres feuilles de l'interface : WordPress la charge alors avant elles, une seule fois.
 *
 * @return string Identifiant de la feuille : « optimizr-tokens ».
 */
function haptic_tokens_style()
{
    if (!wp_style_is('optimizr-tokens', 'registered')) {
        $css = HAPTIC_OPTIMIZR_PATH . 'css/optimizr-tokens.css';
        wp_register_style('optimizr-tokens', HAPTIC_OPTIMIZR_URL . 'css/optimizr-tokens.css', [], filemtime($css));
    }
    return 'optimizr-tokens';
}

function haptic_options_enqueue_scripts()
{
    // Permet aux modules d'ajouter ou de modifier les scripts du site (ex. Tarteaucitron)
    do_action('haptic_before_enqueue_scripts');
    do_action('haptic_after_enqueue_scripts');
}

function haptic_options_enqueue_admin_scripts()
{
    // Permet aux modules d'ajouter ou de modifier les scripts de l'administration.
    // Les styles de la page d'options sont chargés par functions/admin-ui.php.
    do_action('haptic_before_enqueue_admin_scripts');
    do_action('haptic_after_enqueue_admin_scripts');
}

/**
 * Hooks disponibles :
 * - haptic_before_enqueue_scripts / haptic_after_enqueue_scripts : pour injecter des scripts côté frontend
 * - haptic_before_enqueue_admin_scripts / haptic_after_enqueue_admin_scripts : pour injecter des scripts côté admin
 */
