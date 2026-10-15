/*
 * Double authentification : mise en place et réglages du compte (functions/security-2fa-profile.php),
 * dans le profil et sur l'écran proposé à la connexion (functions/security-welcome.php).
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

    function each(selector, callback) {
        Array.prototype.forEach.call(box.querySelectorAll(selector), callback);
    }

    // Mise en place terminée : le profil se recharge, l'écran d'accueil passe à la suite
    function finish() {
        if (data.context === 'welcome') {
            box.textContent = '';
            var done = document.createElement('p');
            done.className = 'optimizr-2fa__done';
            done.textContent = __('Two-factor authentication is enabled.', 'optimizr');
            box.appendChild(done);
            document.dispatchEvent(new CustomEvent('optimizr:secured', { detail: { step: 'two_factor' } }));
        } else {
            window.location.reload();
        }
    }

    function showCodes(codes) {
        var target = box.querySelector('.optimizr-2fa__codes');
        target.textContent = '';

        var intro = document.createElement('p');
        intro.textContent = __('Your backup codes. Keep them somewhere safe (password manager, printout): each one lets you log in once without your phone or your email. They will not be shown again.', 'optimizr');
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
        done.addEventListener('click', finish);

        var actions = document.createElement('p');
        actions.appendChild(download);
        actions.appendChild(document.createTextNode(' '));
        actions.appendChild(done);

        each('.optimizr-2fa__choices, .optimizr-2fa__setup, .optimizr-2fa__confirm', function (element) {
            element.hidden = true;
        });
        target.appendChild(intro);
        target.appendChild(list);
        target.appendChild(actions);
        target.hidden = false;
        done.focus();
    }

    // Code saisi à côté du bouton (mise en place), ou dans le champ de confirmation du compte
    function codeOf(button) {
        var group = button.closest('.optimizr-2fa__setup, .optimizr-2fa__confirm') || box;
        var input = group.querySelector('.optimizr-2fa__code');
        return input ? input.value : '';
    }

    var handlers = {
        choose: function (button) {
            var method = button.getAttribute('data-method');
            box.querySelector('.optimizr-2fa__choices').hidden = true;
            each('.optimizr-2fa__setup', function (panel) {
                panel.hidden = panel.getAttribute('data-method') !== method;
            });
            var panel = box.querySelector('.optimizr-2fa__setup[data-method="' + method + '"]');
            if (method === 'app') {
                drawQr();
                panel.querySelector('.optimizr-2fa__code').focus();
            } else {
                panel.querySelector('[data-2fa-action="send"]').focus();
            }
        },
        back: function () {
            each('.optimizr-2fa__setup', function (panel) {
                panel.hidden = true;
            });
            box.querySelector('.optimizr-2fa__choices').hidden = false;
        },
        send: function (button) {
            return request('send', { purpose: button.getAttribute('data-purpose') }).then(function (result) {
                say(result.message);
                var group = button.closest('.optimizr-2fa__setup, .optimizr-2fa__confirm');
                if (group) {
                    group.querySelector('.optimizr-2fa__code').focus();
                }
            });
        },
        activate: function (button) {
            return request('activate', { method: button.getAttribute('data-method'), code: codeOf(button) }).then(function (result) {
                showCodes(result.codes);
            });
        },
        backup: function (button) {
            if (!window.confirm(__('The current backup codes will stop working. Continue?', 'optimizr'))) {
                return;
            }
            return request('backup', { code: codeOf(button) }).then(function (result) {
                showCodes(result.codes);
            });
        },
        change: function (button) {
            return request('change', { code: codeOf(button) }).then(function () {
                window.location.hash = 'optimizr-2fa';
                window.location.reload();
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

    // Entrée dans un champ de code : active la méthode (mise en place), sans jamais enregistrer tout le profil
    box.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || !event.target.classList.contains('optimizr-2fa__code')) {
            return;
        }
        event.preventDefault();
        var group = event.target.closest('.optimizr-2fa__setup');
        var button = group && group.querySelector('[data-2fa-action="activate"]');
        if (button) {
            button.click();
        }
    });
})();
