/*
 * Liste des extensions : au clic sur « Désactiver » d'Optimizr, demande ce qu'il faudra effacer
 * (réglages, redirections et SEO des contenus, journaux) si le plugin est ensuite supprimé.
 * C'est le dernier moment où son code s'exécute : un plugin désactivé ne charge plus rien,
 * la question ne peut donc pas être posée au clic sur « Supprimer ».
 * Les choix sont enregistrés en AJAX puis lus par uninstall.php.
 */
(function () {
    'use strict';

    var data = window.optimizrUninstall;
    if (!data) {
        return;
    }

    function save(choices) {
        var body = new URLSearchParams();
        body.append('action', 'haptic_save_uninstall');
        body.append('_ajax_nonce', data.nonce);
        body.append('choices', JSON.stringify(choices));
        body.append('network', data.network ? '1' : '');

        return fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error('save');
                }
                data.choices = choices;
            });
    }

    /* ---------- Fenêtre de confirmation ---------- */

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (key) { node.setAttribute(key, attrs[key]); });
        if (text) {
            node.textContent = text;
        }
        return node;
    }

    function injectStyles() {
        if (document.getElementById('optimizr-uninstall-css')) {
            return;
        }
        var style = el('style', { id: 'optimizr-uninstall-css' });
        style.textContent =
            '.optimizr-uninstall-dialog{max-width:520px;width:calc(100% - 32px);padding:24px;border:0;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.25)}' +
            '.optimizr-uninstall-dialog::backdrop{background:rgba(0,0,0,.5)}' +
            '.optimizr-uninstall-dialog h2{margin:0 0 8px;font-size:18px}' +
            '.optimizr-uninstall-dialog label{display:flex;gap:8px;align-items:flex-start;margin:12px 0}' +
            '.optimizr-uninstall-dialog input[type=checkbox]{margin-top:2px}' +
            '.optimizr-uninstall-dialog .description{display:block}' +
            '.optimizr-uninstall-dialog__actions{display:flex;gap:8px;justify-content:flex-end;margin-top:20px}' +
            '.optimizr-uninstall-dialog__error{color:#b32d2e}';
        document.head.appendChild(style);
    }

    function openDialog(onConfirm) {
        injectStyles();

        var dialog = el('dialog', { 'class': 'optimizr-uninstall-dialog', 'aria-labelledby': 'optimizr-uninstall-title' });
        var form = el('form', { method: 'dialog' });
        form.appendChild(el('h2', { id: 'optimizr-uninstall-title' }, 'Désactiver Optimizr'));
        form.appendChild(el('p', {}, 'La désactivation n\'efface rien. Si vous supprimez ensuite le plugin, que faut-il effacer avec lui ? Ce qui n\'est pas coché est conservé et sera retrouvé en cas de réinstallation.'));

        var boxes = {};
        Object.keys(data.groups).forEach(function (key) {
            var label = el('label');
            var box = el('input', { type: 'checkbox' });
            box.checked = !!data.choices[key];
            boxes[key] = box;
            var text = el('span', {}, 'Effacer : ' + data.groups[key].label);
            text.appendChild(el('span', { 'class': 'description' }, data.groups[key].help));
            label.appendChild(box);
            label.appendChild(text);
            form.appendChild(label);
        });

        form.appendChild(el('p', { 'class': 'description' }, 'Les tâches planifiées, caches et fichiers générés (sitemap, clé IndexNow) sont toujours supprimés. Les pages, médias et textes alternatifs ne sont jamais touchés.'));
        var error = el('p', { 'class': 'optimizr-uninstall-dialog__error', role: 'alert' });
        form.appendChild(error);

        var actions = el('div', { 'class': 'optimizr-uninstall-dialog__actions' });
        var cancel = el('button', { type: 'button', 'class': 'button' }, 'Annuler');
        var confirm = el('button', { type: 'submit', 'class': 'button button-primary' }, 'Désactiver');
        actions.appendChild(cancel);
        actions.appendChild(confirm);
        form.appendChild(actions);
        dialog.appendChild(form);
        document.body.appendChild(dialog);

        function close() {
            dialog.close();
            dialog.remove();
        }

        cancel.addEventListener('click', close);
        dialog.addEventListener('cancel', function () { dialog.remove(); });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var choices = {};
            Object.keys(boxes).forEach(function (key) { choices[key] = boxes[key].checked; });

            confirm.disabled = true;
            error.textContent = '';
            save(choices).then(function () {
                close();
                onConfirm();
            }).catch(function () {
                confirm.disabled = false;
                error.textContent = 'Les choix n\'ont pas pu être enregistrés. Rechargez la page et réessayez.';
            });
        });

        dialog.showModal();
        cancel.focus();
    }

    /* ---------- Lien « Désactiver » de la ligne d'Optimizr ---------- */

    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('tr[data-plugin] .deactivate a');
        if (!link || link.closest('tr').getAttribute('data-plugin') !== data.plugin) {
            return;
        }

        event.preventDefault();
        openDialog(function () {
            window.location.href = link.href;
        });
    });
})();
