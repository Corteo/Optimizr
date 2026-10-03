/**
 * Optimizr — navigation de l'assistant de configuration (functions/onboarding.php).
 * Sans JavaScript, toutes les étapes s'affichent l'une sous l'autre avec le bouton d'enregistrement.
 */
(function () {
    'use strict';

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
        next.textContent = index === 0 ? 'Commencer' : 'Continuer';
        submit.classList.toggle('is-visible', last);

        if (progress) progress.style.width = Math.round((index / (list.length - 1)) * 100) + '%';
        if (count) count.textContent = 'Étape ' + (index + 1) + ' sur ' + list.length;

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
                if (input.value.trim()) items.push(input.dataset.summaryValue + ' : ' + input.value.trim());
            });
            var key = step.querySelector('[data-ob-key]');
            if (key && key.value.trim()) items.push('Nouvelle clé API');

            var group = el('section', 'ob-summary__group');
            group.style.animationDelay = (i * 0.06) + 's';
            var head = el('div', 'ob-summary__head');
            head.appendChild(el('h2', 'ob-summary__title', step.querySelector('.ob-step__eyebrow').textContent));
            var edit = el('button', 'ob-summary__edit', 'Modifier');
            edit.type = 'button';
            edit.addEventListener('click', function () { show(i, true); });
            head.appendChild(edit);
            group.appendChild(head);

            if (items.length) {
                var ul = el('ul', 'ob-summary__list');
                items.forEach(function (item) { ul.appendChild(el('li', '', item)); });
                group.appendChild(ul);
            } else {
                group.appendChild(el('p', 'ob-summary__empty', 'Rien d\'activé.'));
            }
            box.appendChild(group);
        });
    }

    /* ---------- Assistant IA : lien et indication de clé selon le fournisseur ---------- */

    var keyInput = form.querySelector('[data-ob-key]');
    var keyLink = form.querySelector('[data-ob-key-link]');

    function syncProvider() {
        var radio = form.querySelector('input[name="hpt_ai_provider"]:checked');
        if (!radio || !keyInput) return;
        keyInput.placeholder = radio.dataset.hasKey === '1' ? keyInput.dataset.placeholderSaved : keyInput.dataset.placeholderEmpty;
        if (keyLink) {
            keyLink.href = radio.dataset.keyUrl || '#';
            keyLink.hidden = !radio.dataset.keyUrl;
        }
    }

    form.querySelectorAll('input[name="hpt_ai_provider"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            keyInput.value = '';
            syncProvider();
        });
    });
    syncProvider();

    /* ---------- Navigation ---------- */

    next.addEventListener('click', function () { show(current + 1, false); });
    prev.addEventListener('click', function () { show(current - 1, true); });

    // Entrée dans un champ : étape suivante plutôt qu'enregistrement anticipé
    form.addEventListener('submit', function (event) {
        if (current < visibleSteps().length - 1) {
            event.preventDefault();
            show(current + 1, false);
            return;
        }
        submit.setAttribute('aria-busy', 'true');
        submit.textContent = 'Application…';
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
