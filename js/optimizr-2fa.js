/*
 * Profil : mise en place de la double authentification (functions/security-2fa-profile.php).
 * Le QR code est dessiné ici, dans le navigateur (assets/qrcode-generator) : la clé secrète ne quitte pas le site.
 * Les champs n'ont pas d'attribut name : ils ne partent pas avec le formulaire du profil.
 */
(function () {
    'use strict';

    var data = window.optimizr2fa;
    var box = document.querySelector('.optimizr-2fa');
    if (!data || !box) {
        return;
    }

    var __ = wp.i18n.__;
    var message = box.querySelector('.optimizr-2fa__message');

    function say(text, isError) {
        message.textContent = text || '';
        message.classList.toggle('is-error', !!isError);
    }

    function request(action, fields) {
        var body = new URLSearchParams();
        body.append('action', 'haptic_2fa_' + action);
        body.append('nonce', data.nonce);
        body.append('user', box.getAttribute('data-user'));
        Object.keys(fields || {}).forEach(function (key) {
            body.append(key, fields[key]);
        });
        return fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.data && json.data.message) || __('The request failed. Reload the page and try again.', 'optimizr'));
                }
                return json.data || {};
            });
    }

    function drawQr() {
        var target = box.querySelector('.optimizr-2fa__qr');
        if (!target || target.firstChild || typeof window.qrcode !== 'function') {
            return;
        }
        var qr = window.qrcode(0, 'M');
        qr.addData(target.getAttribute('data-uri'));
        qr.make();
        target.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 16, scalable: true, alt: 'QR code' });
    }

    function showCodes(codes) {
        var target = box.querySelector('.optimizr-2fa__codes');
        target.textContent = '';

        var intro = document.createElement('p');
        intro.textContent = __('Your backup codes. Keep them somewhere safe (password manager, printout): each one lets you log in once without your phone. They will not be shown again.', 'optimizr');
        var list = document.createElement('ul');
        codes.forEach(function (code) {
            var item = document.createElement('li');
            item.textContent = code;
            list.appendChild(item);
        });

        var download = document.createElement('a');
        download.className = 'button';
        download.textContent = __('Download', 'optimizr');
        download.download = data.fileName;
        download.href = URL.createObjectURL(new Blob([codes.join('\n') + '\n'], { type: 'text/plain' }));

        var done = document.createElement('button');
        done.type = 'button';
        done.className = 'button button-primary';
        done.textContent = __('Done', 'optimizr');
        done.addEventListener('click', function () {
            window.location.reload();
        });

        var actions = document.createElement('p');
        actions.appendChild(download);
        actions.appendChild(document.createTextNode(' '));
        actions.appendChild(done);

        target.appendChild(intro);
        target.appendChild(list);
        target.appendChild(actions);
        target.hidden = false;
    }

    function codeOf(button) {
        var input = button.parentNode.querySelector('.optimizr-2fa__code');
        return input ? input.value : '';
    }

    var handlers = {
        start: function (button) {
            box.querySelector('.optimizr-2fa__setup').hidden = false;
            button.hidden = true;
            drawQr();
            box.querySelector('#optimizr-2fa-setup-code').focus();
        },
        activate: function (button) {
            return request('activate', { code: codeOf(button) }).then(function (result) {
                box.querySelector('.optimizr-2fa__setup').hidden = true;
                showCodes(result.codes);
            });
        },
        backup: function () {
            if (!window.confirm(__('The current backup codes will stop working. Continue?', 'optimizr'))) {
                return;
            }
            return request('backup').then(function (result) {
                showCodes(result.codes);
            });
        },
        disable: function (button) {
            return request('disable', { code: codeOf(button) }).then(function () {
                window.location.reload();
            });
        },
        reset: function () {
            if (!window.confirm(__('Reset two-factor authentication for this account?', 'optimizr'))) {
                return;
            }
            return request('reset').then(function () {
                window.location.reload();
            });
        }
    };

    box.addEventListener('click', function (event) {
        var button = event.target.closest('[data-2fa-action]');
        if (!button) {
            return;
        }
        event.preventDefault();
        say('');
        var pending = handlers[button.getAttribute('data-2fa-action')](button);
        if (pending) {
            button.disabled = true;
            pending.catch(function (error) {
                say(error.message, true);
            }).then(function () {
                button.disabled = false;
            });
        }
    });

    // Entrée dans un champ de code : valide ce code au lieu d'enregistrer tout le profil
    box.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || !event.target.classList.contains('optimizr-2fa__code')) {
            return;
        }
        event.preventDefault();
        var button = event.target.parentNode.querySelector('[data-2fa-action]');
        if (button) {
            button.click();
        }
    });
})();
