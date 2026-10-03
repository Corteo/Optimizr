<?php

if (!defined('ABSPATH')) {
    exit;
}

global $hpt_log_queue, $hpt_console_queue;
$hpt_log_queue = [];
$hpt_console_queue = [];

function hpt_enqueue_debug_assets()
{
    // Uniquement utile pour les modales de vd(), réservées aux admins en mode développeur
    if (!get_option('hpt_dev_mode') || !current_user_can('manage_options')) {
        return;
    }

    // Ajouter le CSS personnalisé en priorité
    wp_enqueue_style('hpt-debug-css', plugin_dir_url(__FILE__) . 'utils.css');

    // Injecter un script pour vérifier la présence de Bootstrap côté client (compatibilité v3/v4/v5)
    add_action('wp_footer', function () {
?>
        <script type="text/javascript">
            (function() {
                var isBootstrapLoaded = false;

                // Vérifie si Bootstrap 5 (objet global) est présent
                if (typeof bootstrap !== 'undefined') {
                    isBootstrapLoaded = true;
                }

                // Vérifie si Bootstrap 3 ou 4 est présent via jQuery.fn.modal (souvent utilisé dans v3 et v4)
                if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'function') {
                    isBootstrapLoaded = true;
                }

                if (!isBootstrapLoaded) {
                    // Charger Bootstrap CSS
                    var link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css';
                    document.head.appendChild(link);

                    // Charger Bootstrap JS
                    var script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
                    script.defer = true;
                    document.body.appendChild(script);
                }
            })();
        </script>
    <?php
    }, 100); // S'assurer que ce script est injecté après tous les autres
}
add_action('admin_enqueue_scripts', 'hpt_enqueue_debug_assets');
add_action('wp_enqueue_scripts', 'hpt_enqueue_debug_assets');


// Stockage des variables de debug pour affichage en fin de rendu
global $hpt_debug_entries;
$hpt_debug_entries = [];

if (!function_exists('vd')) {
    function vd($variable, $label = null)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!get_option('hpt_dev_mode')) {
            return;
        }

        global $hpt_debug_entries;

        // Utiliser debug_backtrace() pour récupérer l'appelant de la fonction
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller_line = $backtrace[0]['line']; // Ligne d'où la fonction a été appelée
        $caller_file = $backtrace[0]['file']; // Fichier où elle a été appelée

        // Lire la ligne du fichier contenant l'appel à vd()
        $file_lines = file($caller_file);
        $call_line = $file_lines[$caller_line - 1];

        // Utiliser une regex pour essayer d'extraire le nom de la variable
        preg_match('/vd\s*\(\s*\$(\w+)/', $call_line, $matches);
        $variable_name = isset($matches[1]) ? $matches[1] : 'Variable Debug';

        // Si aucun label n'est défini, utiliser le nom de la variable
        $label = $label ?: $variable_name;

        // Transformer la variable en format lisible
        ob_start();
        var_dump($variable);
        $dumpContent = ob_get_clean();
        $dumpContent = htmlspecialchars($dumpContent, ENT_QUOTES, 'UTF-8');

        // Générer un ID unique pour chaque debug box
        $uniqueId = 'hptDebugModal_' . uniqid();

        // Stocker les données de debug pour un affichage ultérieur
        $hpt_debug_entries[] = [
            'id' => $uniqueId,
            'label' => $label,
            'content' => $dumpContent
        ];
    }
}

function hpt_render_debug_popups()
{
    global $hpt_debug_entries;

    if (empty($hpt_debug_entries)) {
        return;
    }
    ?>
    <div class="btn-vd-group">
        <?php foreach ($hpt_debug_entries as $debug) : ?>
            <button type="button" class="hpt-debug-btn" data-bs-toggle="modal" data-bs-target="#<?php echo esc_attr($debug['id']); ?>">
                <?php echo esc_html($debug['label']); ?>
            </button>

            <div class="modal fade" id="<?php echo esc_attr($debug['id']); ?>" tabindex="-1" aria-labelledby="<?php echo esc_attr($debug['id']); ?>Label" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="<?php echo esc_attr($debug['id']); ?>Label"><?php echo esc_html($debug['label']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <pre class="hpt-debug-content"><?php echo $debug['content']; ?></pre>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
add_action('admin_footer', 'hpt_render_debug_popups');
add_action('wp_footer', 'hpt_render_debug_popups');

// ---------- Alerte des erreurs PHP (mode debug) ----------

// On définit un tableau global pour stocker les erreurs récupérées.
global $hpt_collected_errors;
$hpt_collected_errors = [];

/**
 * Fonction : haptic_GPT_set_custom_error_handler
 * But : Définir un gestionnaire d'erreurs qui stocke les erreurs dans un tableau global.
 */
function haptic_GPT_set_custom_error_handler()
{
    if (!get_option('hpt_debug_mode')) {
        return;
    }

    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        global $hpt_collected_errors;

        // Respecte error_reporting() et l'opérateur @
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // Construire un message d'erreur
        $error_message = sprintf(
            "PHP Error [%d]: %s in %s on line %d",
            $errno,
            $errstr,
            $errfile,
            $errline
        );

        // Stocker le message dans le tableau global
        $hpt_collected_errors[] = $error_message;

        // Laisse PHP poursuivre son traitement normal (debug.log, affichage)
        return false;
    });
}

/**
 * Fonction : haptic_GPT_display_php_errors_in_popup
 * But : Afficher les erreurs collectées dans une pop-up JavaScript. 
 *       Vérifie d'abord que l'utilisateur est administrateur.
 */
function haptic_GPT_display_php_errors_in_popup()
{
    global $hpt_collected_errors;

    // On vérifie si l'utilisateur est administrateur
    if (!current_user_can('administrator')) {
        return;
    }

    if (!get_option('hpt_debug_mode')) {
        return;
    }

    // Si aucune erreur, on ne fait rien
    if (empty($hpt_collected_errors)) {
        return;
    }

    // Convertir les erreurs en JSON pour les transmettre au script JS
    $errors_json = wp_json_encode($hpt_collected_errors, JSON_HEX_TAG);
    ?>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Récupérer les erreurs depuis la variable PHP
    var errors = <?php echo $errors_json; ?>;
    var message = errors.join("\n\n");

    // Afficher une pop-up basique (alert)
    alert("Erreurs PHP détectées :\n\n" + message);
});
    </script>
<?php
}

/**
 * Fonction : haptic_GPT_register_shutdown_handler
 * But : Intercepter les erreurs fatales via register_shutdown_function pour les enregistrer avant que le script ne termine.
 */
function haptic_GPT_register_shutdown_handler()
{
    register_shutdown_function(function () {
        global $hpt_collected_errors;
        $last_error = error_get_last();

        // On cible les erreurs fatales, parse errors, etc.
        if ($last_error && in_array($last_error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $error_message = sprintf(
                "Fatal Error [%d]: %s in %s on line %d",
                $last_error['type'],
                $last_error['message'],
                $last_error['file'],
                $last_error['line']
            );
            $hpt_collected_errors[] = $error_message;
        }
    });
}

/**
 * Hooks WordPress
 */
// Initialisation du gestionnaire d'erreurs
add_action('plugins_loaded', 'haptic_GPT_set_custom_error_handler');
// Registration du shutdown handler (pour erreurs fatales)
add_action('plugins_loaded', 'haptic_GPT_register_shutdown_handler');
// Injection de la pop-up dans l'admin
add_action('admin_print_footer_scripts', 'haptic_GPT_display_php_errors_in_popup');

/**
 * Écrit un message de log dans logs/debug.log (plugin).
 *
 * @param string $message
 * @param mixed $context
 */
function hpt_log($message, $context = null)
{
    global $hpt_log_queue;
    $hpt_log_queue[] = ['message' => $message, 'context' => $context];
}

function _hpt_log_execute($message, $context = null)
{
    if (!function_exists('current_user_can') || !function_exists('get_option')) return;
    if (!function_exists('wp_get_current_user') || !is_user_logged_in()) return;
    if (!current_user_can('manage_options')) return;
    if (!get_option('hpt_debug_mode')) return;

    $output = '[HPT LOG] ' . date('Y-m-d H:i:s') . ' - ' . $message;

    if ($context !== null) {
        if (is_scalar($context)) {
            $output .= ' | ' . $context;
        } elseif (is_array($context) || is_object($context)) {
            $output .= ' | ' . print_r($context, true);
        } else {
            $output .= ' | ' . gettype($context);
        }
    }

    $log_dir = plugin_dir_path(__DIR__) . 'logs/';
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }

    file_put_contents($log_dir . 'debug.log', $output . PHP_EOL, FILE_APPEND);
}

// Écriture en fin de requête : l'utilisateur courant est alors connu
add_action('shutdown', function () {
    global $hpt_log_queue;

    if (!empty($hpt_log_queue)) {
        foreach ($hpt_log_queue as $log) {
            _hpt_log_execute($log['message'], $log['context']);
        }
        $hpt_log_queue = [];
    }
});

/**
 * Affiche un message dans la console du navigateur.
 *
 * @param string $message
 * @param mixed $context
 */
function hpt_console($message, $context = null)
{
    global $hpt_console_queue;
    $hpt_console_queue[] = ['message' => $message, 'context' => $context];
}

function _hpt_console_execute($message, $context = null)
{
    if (!function_exists('current_user_can') || !function_exists('get_option')) return;
    if (!function_exists('wp_get_current_user') || !is_user_logged_in()) return;
    if (!current_user_can('manage_options')) return;
    if (!get_option('hpt_debug_mode')) return;

    $output = '[HPT CONSOLE] ' . $message;

    if ($context !== null) {
        $output .= ' | ' . ((is_array($context) || is_object($context)) ? json_encode($context) : $context);
    }

    echo "<script>console.log(" . wp_json_encode($output, JSON_HEX_TAG) . ");</script>";
}

// Dans le footer : un <script> émis pendant admin_init casserait les en-têtes HTTP
add_action('admin_footer', function () {
    global $hpt_console_queue;

    if (!empty($hpt_console_queue)) {
        foreach ($hpt_console_queue as $log) {
            _hpt_console_execute($log['message'], $log['context']);
        }
        $hpt_console_queue = [];
    }
});
