<?php

if (!defined('ABSPATH')) {
    exit;
}

// Points d'extension historiques, conservés pour les modules externes
add_action('admin_init', 'haptic_options_admin_init');

function haptic_options_admin_init()
{
    do_action('haptic_before_admin_init');
    do_action('haptic_add_options_tabs');
    do_action('haptic_after_admin_init');
}
