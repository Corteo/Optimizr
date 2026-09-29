<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'hpt_options_enqueue_scripts');
add_action('admin_enqueue_scripts', 'hpt_options_enqueue_admin_scripts');

function hpt_options_enqueue_scripts()
{
    // Permet aux modules d'ajouter ou de modifier les scripts du site (ex. Tarteaucitron)
    do_action('before_hpt_options_enqueue_scripts');
    do_action('after_hpt_options_enqueue_scripts');
}

function hpt_options_enqueue_admin_scripts()
{
    // Permet aux modules d'ajouter ou de modifier les scripts de l'administration.
    // Les styles de la page d'options sont chargés par functions/admin-ui.php.
    do_action('before_hpt_options_enqueue_admin_scripts');
    do_action('after_hpt_options_enqueue_admin_scripts');
}

/**
 * Hooks disponibles :
 * - before_hpt_options_enqueue_scripts / after_... : pour injecter des scripts côté frontend
 * - before_hpt_options_enqueue_admin_scripts / after_... : pour injecter des scripts côté admin
 */
