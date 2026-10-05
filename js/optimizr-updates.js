/**
 * Optimizr — bouton « Vérifier les mises à jour » de l'en-tête (functions/updates.php).
 * Absent de la version WordPress.org, où les mises à jour passent par WordPress.
 */
(function () {
    'use strict';

    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf;

    function initUpdateCheck() {
        var button = document.getElementById('haptic-check-update-btn');
        var status = document.getElementById('haptic-update-status');
        if (!button || !status || typeof window.ajaxurl === 'undefined') return;

        function show(message, state) {
            status.className = 'optimizr-header__status' + (state ? ' is-' + state : '');
            status.textContent = message;
        }

        // Installation par l'action AJAX de WordPress (celle des boutons « Mettre à jour » de la page Extensions) :
        // téléchargement, remplacement des fichiers et identifiants FTP éventuels sont gérés par WordPress.
        function install(version) {
            button.setAttribute('aria-busy', 'true');
            button.disabled = true;
            /* translators: %s: version number */
            show(sprintf(__('Installing version %s…', 'optimizr'), version));

            var plugin = button.dataset.plugin;
            var body = new URLSearchParams({
                action: 'update-plugin',
                _ajax_nonce: button.dataset.updateNonce,
                plugin: plugin,
                slug: plugin.split('/')[0]
            });

            fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (res.success) {
                        // Le rechargement exécute les migrations de la nouvelle version (haptic_options_maybe_upgrade)
                        /* translators: %s: version number */
                        show(sprintf(__('Version %s installed. Reloading…', 'optimizr'), version), 'ok');
                        window.setTimeout(function () { window.location.reload(); }, 1500);
                        return;
                    }
                    var message = res.data && res.data.errorMessage ? res.data.errorMessage : '';
                    if (res.data && res.data.errorCode === 'unable_to_connect_to_filesystem') {
                        message = __('WordPress cannot access the site files. Add the FTP credentials to wp-config.php (FTP_HOST, FTP_USER, FTP_PASS) or update from the Plugins page.', 'optimizr');
                    }
                    /* translators: %s: error message */
                    show(message ? sprintf(__('The update failed: %s', 'optimizr'), message) : __('The update failed.', 'optimizr'), 'warn');
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                })
                .catch(function () {
                    show(__('The update did not complete. Reload the page to check the installed version.', 'optimizr'), 'warn');
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                });
        }

        button.addEventListener('click', function () {
            button.setAttribute('aria-busy', 'true');
            button.disabled = true;
            show(__('Checking…', 'optimizr'));

            var body = new URLSearchParams({ action: 'haptic_ajax_check_update', nonce: button.dataset.nonce });

            fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (!res.success) {
                        /* translators: %s: error message */
                        show(res.data && res.data.message ? sprintf(__('The check failed: %s', 'optimizr'), res.data.message) : __('The check failed.', 'optimizr'), 'warn');
                    } else if (res.data.status === 'update_available') {
                        /* translators: %s: version number */
                        show(sprintf(__('Version %s available.', 'optimizr'), res.data.new_version) + ' ', 'warn');
                        var link = document.createElement('a');
                        link.href = res.data.details_url;
                        link.textContent = __('Update now', 'optimizr');
                        link.addEventListener('click', function (e) {
                            // Sans les données nécessaires, le lien mène à la page des mises à jour de WordPress
                            if (!button.dataset.updateNonce || !button.dataset.plugin) return;
                            e.preventDefault();
                            install(res.data.new_version);
                        });
                        status.appendChild(link);
                    } else {
                        show(__('Optimizr is up to date.', 'optimizr'), 'ok');
                    }
                })
                .catch(function () {
                    show(__('The request failed. Check your connection.', 'optimizr'), 'warn');
                })
                .finally(function () {
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                });
        });
    }

    document.addEventListener('DOMContentLoaded', initUpdateCheck);
})();
