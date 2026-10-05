/**
 * Optimizr — bouton « Vérifier les mises à jour » de l'en-tête (functions/updates.php).
 * Absent de la version WordPress.org, où les mises à jour passent par WordPress.
 */
(function () {
    'use strict';

    function initUpdateCheck() {
        var button = document.getElementById('hpt-check-update-btn');
        var status = document.getElementById('hpt-update-status');
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
            show('Installation de la version ' + version + '…');

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
                        // Le rechargement exécute les migrations de la nouvelle version (hpt_options_maybe_upgrade)
                        show('Version ' + version + ' installée. Rechargement…', 'ok');
                        window.setTimeout(function () { window.location.reload(); }, 1500);
                        return;
                    }
                    var message = res.data && res.data.errorMessage ? res.data.errorMessage : '';
                    if (res.data && res.data.errorCode === 'unable_to_connect_to_filesystem') {
                        message = 'WordPress n\'a pas accès aux fichiers du site. Renseignez les accès FTP dans wp-config.php (FTP_HOST, FTP_USER, FTP_PASS) ou mettez à jour depuis la page Extensions.';
                    }
                    show('La mise à jour a échoué' + (message ? ' : ' + message : '.'), 'warn');
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                })
                .catch(function () {
                    show('La mise à jour n\'a pas abouti. Rechargez la page pour vérifier la version installée.', 'warn');
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                });
        }

        button.addEventListener('click', function () {
            button.setAttribute('aria-busy', 'true');
            button.disabled = true;
            show('Vérification en cours…');

            var body = new URLSearchParams({ action: 'hpt_ajax_check_update', nonce: button.dataset.nonce });

            fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (!res.success) {
                        show('La vérification a échoué' + (res.data && res.data.message ? ' : ' + res.data.message : '.'), 'warn');
                    } else if (res.data.status === 'update_available') {
                        show('Version ' + res.data.new_version + ' disponible. ', 'warn');
                        var link = document.createElement('a');
                        link.href = res.data.details_url;
                        link.textContent = 'Mettre à jour';
                        link.addEventListener('click', function (e) {
                            // Sans les données nécessaires, le lien mène à la page des mises à jour de WordPress
                            if (!button.dataset.updateNonce || !button.dataset.plugin) return;
                            e.preventDefault();
                            install(res.data.new_version);
                        });
                        status.appendChild(link);
                    } else {
                        show('Optimizr est à jour.', 'ok');
                    }
                })
                .catch(function () {
                    show('La requête a échoué. Vérifiez votre connexion.', 'warn');
                })
                .finally(function () {
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                });
        });
    }

    document.addEventListener('DOMContentLoaded', initUpdateCheck);
})();
