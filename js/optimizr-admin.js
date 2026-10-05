/**
 * Optimizr — comportements de la page d'options (functions/admin-ui.php).
 */
(function () {
    'use strict';

    var __ = wp.i18n.__;
    var _x = wp.i18n._x;
    var sprintf = wp.i18n.sprintf;

    // Nombre au format de la langue de l'admin (attribut lang de la page), repli sur le format du navigateur
    function formatNumber(value, digits) {
        var options = { minimumFractionDigits: digits, maximumFractionDigits: digits };
        try {
            return value.toLocaleString(document.documentElement.lang || undefined, options);
        } catch (e) {
            return value.toLocaleString(undefined, options);
        }
    }

    /* ---------- Utilitaires couleur ---------- */

    function luminance(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) {
            hex = hex.split('').map(function (c) { return c + c; }).join('');
        }
        var rgb = [0, 2, 4].map(function (i) {
            var c = parseInt(hex.substr(i, 2), 16) / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
    }

    // Même règle que haptic_readable_text_color() côté PHP
    function readableText(hex) {
        return luminance(hex) > 0.179 ? '#1e1e1e' : '#ffffff';
    }

    function contrastWithWhite(hex) {
        return 1.05 / (luminance(hex) + 0.05);
    }

    /* ---------- Barre d'enregistrement ---------- */

    function initSaveBar() {
        var form = document.querySelector('[data-optimizr-form]');
        var bar = document.querySelector('[data-optimizr-savebar]');
        if (!form || !bar) return;

        var text = bar.querySelector('[data-optimizr-savebar-text]');
        var reset = bar.querySelector('[data-optimizr-reset]');
        var submitting = false;

        function serialize() {
            var parts = [];
            new FormData(form).forEach(function (value, key) {
                if (key === '_wpnonce' || key === '_wp_http_referer') return;
                parts.push(key + '=' + value);
            });
            return parts.join('&');
        }

        var initial = serialize();

        function isDirty() {
            return serialize() !== initial;
        }

        function refresh() {
            var dirty = isDirty();
            bar.classList.toggle('is-dirty', dirty);
            text.textContent = dirty ? __('Unsaved changes', 'optimizr') : __('All changes saved', 'optimizr');
        }

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        document.addEventListener('haptic:seo-pattern-change', refresh);

        form.addEventListener('submit', function () {
            submitting = true;
        });

        // Les éditeurs personnalisés (motifs SEO, lignes CPT) ne suivent pas form.reset() : on recharge
        reset.addEventListener('click', function (e) {
            e.preventDefault();
            submitting = true;
            window.location.reload();
        });

        window.addEventListener('beforeunload', function (e) {
            if (!submitting && isDirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    /* ---------- Tableau de bord ---------- */

    function initModules() {
        document.querySelectorAll('.optimizr-module .optimizr-switch input').forEach(function (input) {
            input.addEventListener('change', function () {
                input.closest('.optimizr-module').classList.toggle('is-off', !input.checked);
            });
        });
    }

    /* ---------- Cookies : couleurs, position, aperçu ---------- */

    function initCookieBanner() {
        var preview = document.querySelector('[data-tac-preview]');
        var banner = document.querySelector('[data-tac-preview-banner]');

        document.querySelectorAll('[data-tac-color]').forEach(function (input) {
            var row = input.closest('.optimizr-color');
            var hex = row.querySelector('[data-tac-hex]');
            var contrast = row.querySelector('[data-tac-contrast]');

            function update() {
                var value = input.value;
                hex.textContent = value;

                if (contrast) {
                    var ratio = contrastWithWhite(value);
                    var ok = ratio >= 4.5;
                    if (ok) {
                        /* translators: %s: contrast ratio, e.g. 4.5 */
                        contrast.textContent = sprintf(__('AA %s:1', 'optimizr'), formatNumber(ratio, 1));
                    } else {
                        /* translators: %s: contrast ratio, e.g. 3.2 */
                        contrast.textContent = sprintf(__('Low %s:1', 'optimizr'), formatNumber(ratio, 1));
                    }
                    contrast.className = 'optimizr-contrast ' + (ok ? 'is-ok' : 'is-warn');
                }

                if (!preview) return;
                if (input.dataset.tacColor === 'haptic_option_tarteaucitron_highlight_color') {
                    banner.style.background = value;
                    banner.style.color = readableText(value);
                }
                var btn = preview.querySelector('[data-tac-preview-btn="' + input.dataset.tacColor + '"]');
                if (btn) btn.style.background = value;
            }

            input.addEventListener('input', update);
            update();
        });

        document.querySelectorAll('[data-tac-position]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (preview && radio.checked) preview.dataset.position = radio.value;
            });
        });
    }

    function initServices() {
        var root = document.querySelector('[data-optimizr-services]');
        if (!root) return;

        var search = root.querySelector('[data-optimizr-services-search]');
        var empty = root.querySelector('[data-optimizr-services-empty]');

        search.addEventListener('input', function () {
            var query = search.value.trim().toLowerCase();
            var visible = 0;

            root.querySelectorAll('[data-optimizr-services-group]').forEach(function (group) {
                var groupVisible = 0;
                group.querySelectorAll('[data-optimizr-service]').forEach(function (row) {
                    var match = !query || row.dataset.optimizrService.indexOf(query) !== -1;
                    row.hidden = !match;
                    if (match) groupVisible++;
                });
                group.hidden = groupVisible === 0;
                visible += groupVisible;
            });

            empty.hidden = visible !== 0;
        });

        // Statut Actif / Inactif selon le premier champ du service
        root.querySelectorAll('[data-optimizr-service]').forEach(function (row) {
            var first = row.querySelector('input');
            var status = row.querySelector('.optimizr-status');
            first.addEventListener('input', function () {
                var active = first.value.trim() !== '';
                status.className = 'optimizr-status optimizr-status--' + (active ? 'ok' : 'off');
                status.textContent = active ? _x('Active', 'service status', 'optimizr') : _x('Inactive', 'service status', 'optimizr');
            });
        });
    }

    /* ---------- SEO : aperçus en direct ---------- */

    function initSeoPreview() {
        var root = document.querySelector('[data-seo-preview]');
        if (!root) return;

        var vars = JSON.parse(root.dataset.seoVars || '{}');
        var titleInput = document.getElementById('haptic_seo_title_pattern');
        var descInput = document.getElementById('haptic_seo_desc_pattern');
        var titleCount = root.querySelector('[data-seo-count-title]');
        var descCount = root.querySelector('[data-seo-count-desc]');

        // Même résultat que haptic_generate_seo_content() : variables remplacées, séparateurs orphelins retirés
        function render(pattern) {
            var text = Object.keys(vars).reduce(function (acc, code) {
                return acc.split(code).join(vars[code]);
            }, pattern || '');
            return text.replace(/\s+/g, ' ').replace(/^[\s\-–—|*·•:,;\/]+|[\s\-–—|*·•:,;\/]+$/g, '');
        }

        function count(el, text, max) {
            /* translators: 1: current length, 2: recommended maximum length */
            el.textContent = sprintf(__('%1$s / %2$s characters', 'optimizr'), text.length, max);
            el.classList.toggle('is-warn', text.length > max);
        }

        function update() {
            var title = render(titleInput ? titleInput.value : '');
            var desc = render(descInput ? descInput.value : '');

            root.querySelectorAll('[data-seo-preview-title]').forEach(function (el) { el.textContent = title; });
            root.querySelectorAll('[data-seo-preview-desc]').forEach(function (el) { el.textContent = desc; });
            count(titleCount, title, 60);
            count(descCount, desc, 160);
        }

        document.addEventListener('haptic:seo-pattern-change', update);
        update();
    }

    /* ---------- Redirections : type 410 ---------- */

    // Une page supprimée (410) n'a pas de destination
    function initRedirectForm() {
        var code = document.querySelector('[data-redirect-code]');
        var target = document.querySelector('[data-redirect-target]');
        if (!code || !target) return;

        var field = target.closest('label');
        var sync = function () {
            var gone = code.value === '410';
            field.hidden = gone;
            target.disabled = gone;
            code.closest('.optimizr-redirect-form').classList.toggle('is-gone', gone);
        };
        code.addEventListener('change', sync);
        sync();
    }

    /* ---------- Onglets : défilement horizontal quand la largeur manque ---------- */

    function initTabsScroll() {
        var wrap = document.querySelector('[data-optimizr-tabs]');
        if (!wrap) return;

        var nav = wrap.querySelector('.optimizr-tabs');
        var prev = wrap.querySelector('[data-tabs-scroll="-1"]');
        var next = wrap.querySelector('[data-tabs-scroll="1"]');

        function update() {
            var max = nav.scrollWidth - nav.clientWidth;
            var hasPrev = nav.scrollLeft > 2;
            var hasNext = nav.scrollLeft < max - 2;
            wrap.classList.toggle('has-prev', hasPrev);
            wrap.classList.toggle('has-next', hasNext);
            prev.hidden = !hasPrev;
            next.hidden = !hasNext;
        }

        [prev, next].forEach(function (button) {
            button.addEventListener('click', function () {
                var step = Math.max(160, nav.clientWidth * 0.6);
                nav.scrollBy({ left: step * parseInt(button.getAttribute('data-tabs-scroll'), 10) });
            });
        });

        // Molette verticale de la souris : défilement horizontal de la barre
        nav.addEventListener('wheel', function (event) {
            if (nav.scrollWidth <= nav.clientWidth || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
            event.preventDefault();
            nav.scrollLeft += event.deltaY;
        }, { passive: false });

        nav.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        // Onglet courant visible au chargement, sans animation
        var current = nav.querySelector('[aria-current="page"]');
        if (current) {
            var offset = current.offsetLeft - nav.offsetLeft;
            if (offset + current.offsetWidth > nav.clientWidth) {
                nav.style.scrollBehavior = 'auto';
                nav.scrollLeft = offset - (nav.clientWidth - current.offsetWidth) / 2;
                nav.style.scrollBehavior = '';
            }
        }
        update();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTabsScroll();
        initSaveBar();
        initModules();
        initCookieBanner();
        initServices();
        initSeoPreview();
        initRedirectForm();
    });
})();
