<?php

/**
 * Plugin Name: Haptic Optimizr
 * Plugin URI: https://alexandrevergnaud.fr/
 * Description: Haptic Optimizr is a WordPress plugin that allows you to enable or disable certain features and manage specific options for your website.
 * Version: 1.1
 * Author: Alexandre Vergnaud
 * Author URI: https://alexandrevergnaud.fr/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hpt-options
 */

// 
// 
//  Hooks for plugin activation and deactivation
// 
// 


if (!defined('ABSPATH')) {
    wp_die('Accès interdit');
}


// Function to be executed when the plugin is activated
function hpt_options_activate()
{
    // Add your initialization code here
    // For example, you can create default options or tables in the database
    add_action('admin_notice', 'hpt_options_activation_notice');
}

// Function to be executed when the plugin is deactivated
function hpt_options_deactivate()
{
    // Add your cleanup code here
    // For example, you can remove options or tables from the database
}

function hpt_options_activation_notice()
{
    $message = 'Haptic Optimizr à été activé avec succès!';
    echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
}

// Register a callback function to be executed when the plugin is activated
register_activation_hook(__FILE__, 'hpt_options_activate');

// Register a callback function to be executed when the plugin is deactivated
register_deactivation_hook(__FILE__, 'hpt_options_deactivate');

// 
// 
// Filters and action hooks for plugin settings
// 
// 

// Add a callback function to the 'init' action hook
add_action('init', 'hpt_options_init');

// Add a callback function to the 'wp_enqueue_scripts' action hook
add_action('wp_enqueue_scripts', 'hpt_options_enqueue_scripts');

// Function to initialize the plugin
function hpt_options_init()
{
    // Add your initialization code here
    // For example, you can register custom settings, add custom fields, and create custom tables in the database
    register_setting('hpt-options-settings-group', 'hpt_debug_mode');
    register_setting('hpt-options-settings-group', 'hpt_dev_mode');
}

// Function to enqueue scripts and styles
function hpt_options_enqueue_scripts()
{
    // Add your script and style enqueueing code here
    // For example, you can enqueue a CSS file like this:
    // wp_enqueue_style('hpt-options-style', plugins_url('css/admin-style.min.css', __FILE__));
    // wp_enqueue_script('hpt-tabs-script', plugins_url('js/tabs.js', __FILE__));
    do_action('before_hpt_options_enqueue_scripts');


    do_action('after_hpt_options_enqueue_scripts');


    // // Output the generated script
    // echo hpt_options_generate_script();
}

function hpt_options_enqueue_admin_scripts()
{
    // Enqueue Bootstrap CSS
    wp_enqueue_style('bootstrap-css', plugin_dir_url(__FILE__) . 'assets/bootstrap/5.3.3/css/bootstrap.min.css');

    // Enqueue Bootstrap JS
    wp_enqueue_script('bootstrap-js', plugin_dir_url(__FILE__) . 'assets/bootstrap/5.3.3/js/bootstrap.min.js', ['jquery'], null, true);
}

add_action('admin_enqueue_scripts', 'hpt_options_enqueue_admin_scripts');

// 
// 
// Create a settings page in the admin menu
// 
// 



// Add a callback function to the 'admin_menu' action hook
add_action('admin_menu', 'hpt_options_admin_menu');

// Function to add a menu item in the admin
function hpt_options_admin_menu()
{
    add_options_page(
        'Réglages - Optimizr', // Page title
        'Optimizr',          // Menu title
        'manage_options',          // Capability
        'hpt-options',             // Menu slug
        'hpt_options_settings_page' // Callback function
    );
}


// Function to display the settings page
function hpt_options_settings_page()
{
    // Update the options based on the current state before displaying the settings page
    hpt_options_update_settings();

    add_settings_section('hpt-options-main', 'Options principales', null, 'hpt-options');

    add_settings_field('hpt_debug_mode', 'Activer le mode Debug', function () {
        $value = get_option('hpt_debug_mode', false);
        echo '<input type="checkbox" name="hpt_debug_mode" value="1" ' . checked(1, $value, false) . '> Activer l\'affichage des erreurs PHP';
    }, 'hpt-options', 'hpt-options-main');

    add_settings_field('hpt_dev_mode', 'Activer le mode Développeur', function () {
        $value = get_option('hpt_dev_mode', false);
        echo '<input type="checkbox" name="hpt_dev_mode" value="1" ' . checked(1, $value, false) . '> Activer l\'affichage des variables de debug (vd)';
    }, 'hpt-options', 'hpt-options-main');

?>
    <div class="wrap">
        <h1>Haptic Optimizr</h1>
        <h2>Réglages</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('hpt-options-settings-group');
            hpt_do_settings_sections('hpt-options'); ?>
            <div class="validation">
                <?php submit_button();   ?>
            </div>
        </form>
    </div>
<?php
}

require_once('hpt_admin-page.php');
require_once('functions/thumbnails.php');
require_once('functions/cpts.php');
require_once('functions/cookies.php');
require_once('functions/ai.php');
require_once('functions/seo/seo.php');
require_once('gutenberg/gutenberg.php');
require_once('functions/tabs.php');
require_once('functions/utils.php');
