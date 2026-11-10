/**
 * Optimizr — barre flottante (functions/toolbar.php) : ouverture du menu ou de la fiche, fenêtres du dock,
 * « Voir comme un visiteur ». Échap ferme ce qui est ouvert, ou fait revenir le module masqué.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-otb]');
    if (!root) {
        return;
    }

    var panel = root.querySelector('#otb-panel');
    var toggles = root.querySelectorAll('[data-otb-toggle]');
    var pops = root.querySelectorAll('[data-otb-pop]');

    function setPanel(open) {
        if (!panel) {
            return;
        }
        panel.hidden = !open;
        root.classList.toggle('is-open', open);
        toggles.forEach(function (button) {
            if (button.hasAttribute('aria-expanded')) {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        });
        if (!open) {
            closeDetails();
        }
    }

    function setPop(id) {
        pops.forEach(function (button) {
            var name = button.getAttribute('data-otb-pop');
            var target = root.querySelector('#otb-pop-' + name);
            var open = name === id;
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (target) {
                target.hidden = !open;
                if (open) {
                    place(button, target);
                }
            }
        });
        root.classList.toggle('has-pop', !!id);
    }

    // Fenêtre du dock centrée sur son bouton, sans sortir de l'écran
    function place(button, target) {
        var box = root.getBoundingClientRect();
        var b = button.getBoundingClientRect();
        var width = target.offsetWidth;
        var left = b.left + b.width / 2 - width / 2;
        left = Math.max(12, Math.min(left, window.innerWidth - width - 12));
        target.style.left = (left - box.left) + 'px';
    }

    function closeDetails() {
        root.querySelectorAll('details[open]').forEach(function (details) {
            details.open = false;
        });
    }

    function closeAll() {
        setPanel(false);
        setPop(null);
        closeDetails();
    }

    toggles.forEach(function (button) {
        button.addEventListener('click', function () {
            setPanel(panel.hidden);
        });
    });

    pops.forEach(function (button) {
        button.addEventListener('click', function () {
            setPop(button.getAttribute('aria-expanded') === 'true' ? null : button.getAttribute('data-otb-pop'));
        });
    });

    root.querySelectorAll('[data-otb-hide]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeAll();
            root.hidden = true;
        });
    });

    // Un seul menu « Créer » ou sous-menu ouvert à la fois dans une même liste
    root.addEventListener('toggle', function (event) {
        var details = event.target;
        if (details.tagName !== 'DETAILS' || !details.open || !details.classList.contains('otb-create')) {
            return;
        }
        setPop(null);
    }, true);

    document.addEventListener('click', function (event) {
        if (!root.contains(event.target)) {
            closeAll();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        if (root.hidden) {
            root.hidden = false;
            return;
        }
        var opened = (panel && !panel.hidden) || root.classList.contains('has-pop') || root.querySelector('details[open]');
        if (opened) {
            closeAll();
            var trigger = root.querySelector('.otb-bubble, .otb-tab');
            if (trigger && root.contains(document.activeElement)) {
                trigger.focus();
            }
        }
    });
})();
