<?php

/*
 * Exécuté par WordPress quand le plugin est supprimé (pas à la simple désactivation).
 * Ce qui est effacé dépend des choix faits à la désactivation ou dans l'onglet Outils
 * (cf. functions/uninstall.php) : par défaut, réglages, redirections et journaux sont conservés.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/functions/activation.php';
// Cache : advanced-cache.php, ligne de wp-config.php, règles du .htaccess et pages (action haptic_deactivate)
require_once __DIR__ . '/functions/cache-files.php';
require_once __DIR__ . '/functions/uninstall.php';

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $haptic_site_id) {
        switch_to_blog($haptic_site_id);
        haptic_uninstall_site();
        restore_current_blog();
    }
} else {
    haptic_uninstall_site();
}

// Toujours : cache de l'outil de mise à jour (une seule option pour tout le réseau sur un multisite)
delete_site_option('external_updates-optimizr');
