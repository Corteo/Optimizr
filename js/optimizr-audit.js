/**
 * Optimizr — onglet Audit (functions/audit.php) : lance l'audit Lighthouse en AJAX
 * et affiche les résultats de l'appareil choisi (mobile ou ordinateur).
 * Les textes viennent des attributs data-* du formulaire, traduits côté PHP.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-optimizr-audit]');
    if (!form) {
        return;
    }

    var button = form.querySelector('button[type="submit"]');
    var status = form.querySelector('[data-audit-status]');
    var history = document.querySelector('[data-audit-history]');
    var timer = null;

    function strategy() {
        var checked = form.querySelector('input[name="strategy"]:checked');
        return checked ? checked.value : 'mobile';
    }

    function showPanel(name) {
        document.querySelectorAll('[data-audit-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-audit-panel') !== name;
        });
    }

    function setStatus(text, isError) {
        status.hidden = !text;
        status.textContent = text || '';
        status.classList.toggle('is-error', !!isError);
    }

    function stop() {
        clearInterval(timer);
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }

    form.addEventListener('change', function (event) {
        if (event.target.name === 'strategy') {
            showPanel(event.target.value);
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (button.disabled) {
            return;
        }

        var started = Date.now();
        var running = form.getAttribute('data-running');
        var tick = function () {
            setStatus(running.replace('%d', Math.round((Date.now() - started) / 1000)));
        };
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        tick();
        timer = setInterval(tick, 1000);

        var body = new URLSearchParams();
        body.append('action', 'haptic_audit_run');
        body.append('_ajax_nonce', form.getAttribute('data-nonce'));
        body.append('url', form.elements.url.value);
        body.append('strategy', strategy());

        fetch(form.getAttribute('data-ajax-url'), { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                stop();
                if (!json || !json.success) {
                    setStatus(json && json.data && json.data.message ? json.data.message : form.getAttribute('data-error'), true);
                    return;
                }
                setStatus('');
                var panel = document.querySelector('[data-audit-panel="' + json.data.strategy + '"]');
                panel.innerHTML = json.data.html;
                history.innerHTML = json.data.history;
                showPanel(json.data.strategy);
                var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                panel.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            })
            .catch(function () {
                stop();
                setStatus(form.getAttribute('data-error'), true);
            });
    });

    // Page envoyée par « Auditer cette page » de la barre flottante : audit lancé à l'ouverture
    if (form.hasAttribute('data-autorun') && !button.disabled) {
        if (form.requestSubmit) {
            form.requestSubmit(button);
        } else {
            button.click();
        }
    }
})();
