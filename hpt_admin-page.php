<?php

if (!defined('ABSPATH')) {
    exit;
}

// Points d'extension historiques, conservés pour les modules externes
add_action('admin_init', 'hpt_options_admin_init');

function hpt_options_admin_init()
{
    do_action('before_hpt_options_admin_init');
    do_action('add_hpt_options_tabs');
    do_action('after_hpt_options_admin_init');
}
