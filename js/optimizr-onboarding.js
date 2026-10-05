/**
 * Optimizr — navigation de l'assistant de configuration (functions/onboarding.php).
 * Sans JavaScript, toutes les étapes s'affichent l'une sous l'autre avec le bouton d'enregistrement.
 */
(function () {
    'use strict';

    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf;

    var form = document.querySelector('[data-ob-form]');
    if (!form) return;

    var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
    var prev = form.querySelector('[data-ob-prev]');
    var next = form.querySelector('[data-ob-next]');
    var submit = form.querySelector('[data-ob-submit]');
    var progress = document.querySelector('[data-ob-progress]');
    var count = document.querySelector('[data-ob-count]');
    var pillars = document.querySelector('.ob-pillars');
    var current = 0;

    function moduleChecked(slug) {
        var input = form.querySelector('[data-module-toggle="' + slug + '"]');
        return !input || input.checked;
    }

    // Étapes affichées : celles sans module, et celles dont le module est choisi
    function visibleSteps() {
        return steps.filter(function (step) {
            return !step.dataset.module || moduleChecked(step.dataset.module);
        });
    }

    function show(index, back) {
        var list = visibleSteps();
        index = Math.max(0, Math.min(index, list.length - 1));
        var step = list[index];
        var slug = step.dataset.step;
        current = index;

        steps.forEach(function (s) {
            var active = s === step;
            s.classList.toggle('is-active', active);
            s.classList.toggle('is-back', active && !!back);
        });

        document.querySelectorAll('[data-illu]').forEach(function (el) {
            el.classList.toggle('is-active', el.dataset.illu === slug);
        });
        var pillar = 'all';
        document.querySelectorAll('[data-caption]').forEach(function (el) {
            var active = el.dataset.caption === slug;
            el.classList.toggle('is-active', active);
            if (active) pillar = el.dataset.pillar;
        });
        if (pillars) pillars.dataset.pillar = pillar;

        document.querySelectorAll('[data-stepper]').forEach(function (el) {
            var pos = list.findIndex(function (s) { return s.dataset.step === el.dataset.stepper; });
            el.hidden = pos === -1;
            el.classList.toggle('is-current', pos === index);
            el.classList.toggle('is-done', pos !== -1 && pos < index);
            if (pos === index) {
                el.setAttribute('aria-current', 'step');
            } else {
                el.removeAttribute('aria-current');
            }
        });

        var last = index === list.length - 1;
        prev.hidden = index === 0;
        next.classList.toggle('is-hidden', last);
        next.textContent = index === 0 ? __('Get started', 'optimizr') : __('Continue', 'optimizr');
        submit.classList.toggle('is-visible', last);

        if (progress) progress.style.width = Math.round((index / (list.length - 1)) * 100) + '%';
        /* translators: 1: current step number, 2: total number of steps */
        if (count) count.textContent = sprintf(__('Step %1$s of %2$s', 'optimizr'), index + 1, list.length);

        if (slug === 'summary') buildSummary(list);

        var title = step.querySelector('.ob-step__title');
        if (back !== null) {
            if (title) title.focus({ preventScroll: true });
            window.scrollTo({ top: 0, behavior: 'auto' });
        }
    }

    /* ---------- Récapitulatif ---------- */

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    }

    function buildSummary(list) {
        var box = form.querySelector('[data-ob-summary]');
        if (!box) return;
        box.textContent = '';

        list.forEach(function (step, i) {
            var slug = step.dataset.step;
            if (slug === 'welcome' || slug === 'summary') return;

            var items = [];
            step.querySelectorAll('[data-summary]').forEach(function (input) {
                if (input.checked) items.push(input.dataset.summary);
            });
            step.querySelectorAll('[data-summary-value]').forEach(function (input) {
                // Champ masqué (modèle d'un autre fournisseur) : non retenu
                if (input.closest('[hidden]')) return;
                var value = input.tagName === 'SELECT'
                    ? (input.selectedIndex > -1 ? input.options[input.selectedIndex].text : '')
                    : input.value.trim().split(/\s*\n\s*/).join(', ');
                /* translators: 1: setting label, 2: entered value */
                if (value) items.push(sprintf(__('%1$s: %2$s', 'optimizr'), input.dataset.summaryValue, (input.dataset.summaryPrefix || '') + value));
            });
            var key = step.querySelector('[data-ob-key]');
            if (key && key.value.trim()) items.push(__('New API key', 'optimizr'));

            var group = el('section', 'ob-summary__group');
            group.style.animationDelay = (i * 0.06) + 's';
            var head = el('div', 'ob-summary__head');
            head.appendChild(el('h2', 'ob-summary__title', step.querySelector('.ob-step__eyebrow').textContent));
            var edit = el('button', 'ob-summary__edit', __('Edit', 'optimizr'));
            edit.type = 'button';
            edit.addEventListener('click', function () { show(i, true); });
            head.appendChild(edit);
            group.appendChild(head);

            if (items.length) {
                var ul = el('ul', 'ob-summary__list');
                items.forEach(function (item) { ul.appendChild(el('li', '', item)); });
                group.appendChild(ul);
            } else {
                group.appendChild(el('p', 'ob-summary__empty', __('Nothing enabled.', 'optimizr')));
            }
            box.appendChild(group);
        });
    }

    /* ---------- Assistant IA : lien et indication de clé selon le fournisseur ---------- */

    var keyInput = form.querySelector('[data-ob-key]');
    var keyLink = form.querySelector('[data-ob-key-link]');

    function syncProvider() {
        var radio = form.querySelector('input[name="haptic_ai_provider"]:checked');
        if (!radio || !keyInput) return;
        keyInput.placeholder = radio.dataset.hasKey === '1' ? keyInput.dataset.placeholderSaved : keyInput.dataset.placeholderEmpty;
        if (keyLink) {
            keyLink.href = radio.dataset.keyUrl || '#';
            keyLink.hidden = !radio.dataset.keyUrl;
        }
    }

    form.querySelectorAll('input[name="haptic_ai_provider"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            keyInput.value = '';
            syncProvider();
        });
    });
    syncProvider();

    // Modèle proposé selon le fournisseur choisi
    form.querySelectorAll('input[name="haptic_ai_provider"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            form.querySelectorAll('[data-ob-provider-row]').forEach(function (row) {
                row.hidden = row.dataset.obProviderRow !== radio.value;
            });
        });
    });

    /* ---------- Sécurité : adresse de connexion et liste blanche ---------- */

    var slugInput = form.querySelector('#ob-login-slug');
    var suggest = form.querySelector('[data-ob-suggest-slug]');
    if (suggest && slugInput) {
        suggest.addEventListener('click', function () {
            /* translators: comma-separated words used to suggest a login URL; letters a-z only, no spaces or accents */
            var words = __('area,access,manage,workshop,office,backstage', 'optimizr').split(',');
            var rand = Math.random().toString(36).slice(2, 6);
            slugInput.value = words[Math.floor(Math.random() * words.length)] + '-' + rand;
            slugInput.focus();
        });
    }

    var addIp = form.querySelector('[data-ob-add-ip]');
    if (addIp) {
        addIp.addEventListener('click', function () {
            var area = form.querySelector('#ob-login-whitelist');
            var ip = addIp.dataset.obAddIp;
            var lines = area.value.split(/\n/).map(function (l) { return l.trim(); }).filter(Boolean);
            if (lines.indexOf(ip) === -1) lines.push(ip);
            area.value = lines.join('\n');
            addIp.disabled = true;
            addIp.textContent = __('Address added', 'optimizr');
        });
    }

    /* ---------- Référencement : variables des motifs ---------- */

    var lastPattern = form.querySelector('[data-ob-pattern]');
    form.querySelectorAll('[data-ob-pattern]').forEach(function (input) {
        input.addEventListener('focus', function () { lastPattern = input; });
    });
    form.querySelectorAll('[data-ob-var]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!lastPattern) return;
            var value = lastPattern.value;
            var start = lastPattern.selectionStart != null ? lastPattern.selectionStart : value.length;
            var end = lastPattern.selectionEnd != null ? lastPattern.selectionEnd : value.length;
            var text = button.dataset.obVar;
            lastPattern.value = value.slice(0, start) + text + value.slice(end);
            lastPattern.focus();
            lastPattern.setSelectionRange(start + text.length, start + text.length);
        });
    });

    /* ---------- Navigation ---------- */

    // Champs de l'étape affichée : on ne passe à la suivante que s'ils sont valides
    function stepIsValid() {
        var step = visibleSteps()[current];
        var fields = step.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            if (!fields[i].closest('[hidden]') && !fields[i].checkValidity()) {
                fields[i].reportValidity();
                return false;
            }
        }
        return true;
    }

    function goNext() {
        if (stepIsValid()) show(current + 1, false);
    }

    next.addEventListener('click', goNext);
    prev.addEventListener('click', function () { show(current - 1, true); });

    // Entrée dans un champ : étape suivante plutôt qu'enregistrement anticipé
    form.addEventListener('submit', function (event) {
        if (current < visibleSteps().length - 1) {
            event.preventDefault();
            goNext();
            return;
        }
        submit.setAttribute('aria-busy', 'true');
        submit.textContent = __('Applying…', 'optimizr');
    });

    // Un module décoché en revenant en arrière ne doit pas laisser l'étape courante hors de la liste
    form.querySelectorAll('[data-module-toggle]').forEach(function (input) {
        input.addEventListener('change', function () {
            var list = visibleSteps();
            current = Math.min(current, list.length - 1);
            show(list.indexOf(form.querySelector('[data-step].is-active')), null);
        });
    });

    show(0, null);
})();
