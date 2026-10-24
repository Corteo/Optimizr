/*
 * Écran « Sécuriser votre compte » (functions/security-welcome.php) et étape du compte de l'assistant :
 * une étape terminée (évènement optimizr:secured des scripts de mise en place) coche sa carte,
 * et « Plus tard » devient « Continuer » une fois l'étape obligatoire faite.
 */
(function () {
    'use strict';

    var root = document.querySelector('.optimizr-welcome');
    if (!root) {
        return;
    }
    var link = document.querySelector('[data-welcome-continue]');
    var required = root.getAttribute('data-required');

    function toggleClasses(element, attribute, method) {
        (element.getAttribute(attribute) || '').split(' ').forEach(function (name) {
            if (name) {
                element.classList[method](name);
            }
        });
    }

    document.addEventListener('optimizr:secured', function (event) {
        var step = event.detail && event.detail.step;
        // Passkey d'Optimizr Pro : sa carte porte aussi data-passkey-welcome
        var card = step && root.querySelector('[data-welcome-step="' + step + '"]' + (step === 'passkey' ? ',[data-passkey-welcome]' : ''));
        if (card) {
            card.classList.add('is-done');
        }
        if (step === required) {
            required = '';
        }
        if (required || !link) {
            return;
        }

        link.hidden = false;
        link.textContent = link.getAttribute('data-done');
        toggleClasses(link, 'data-later-class', 'remove');
        toggleClasses(link, 'data-done-class', 'add');

        if (!root.querySelector('.optimizr-welcome__step:not(.is-done):not([hidden])')) {
            var hint = root.querySelector('.optimizr-welcome__hint');
            if (hint) {
                hint.hidden = true;
            }
            link.focus();
        }
    });
})();
