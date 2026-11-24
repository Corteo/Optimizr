/**
 * Optimizr — comportements de la page d'options (functions/admin-ui.php).
 */
(function () {
    'use strict';

    var __ = wp.i18n.__;
    var _x = wp.i18n._x;
    var _n = wp.i18n._n;
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

    function contrast(a, b) {
        var la = luminance(a), lb = luminance(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    // Même règle que haptic_readable_text_color() côté PHP
    function readableText(hex) {
        var dark = contrast(hex, '#1e1e1e'), light = contrast(hex, '#ffffff');
        if (Math.max(dark, light) < 4.5) return '#000000';
        return dark >= light ? '#1e1e1e' : '#ffffff';
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

        // Les éditeurs personnalisés (motifs SEO, lignes des types de contenu d'Optimizr Pro) ne suivent pas form.reset() : on recharge
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

    var ICON_CHECK = '<path d="M20 6 9 17l-5-5"/>';
    var ICON_ALERT = '<path d="M12 8v5"/><path d="M12 17h.01"/>';
    var toastTimer;

    function svgIcon(paths, size) {
        return '<svg class="optimizr-icon" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + paths + '</svg>';
    }

    // Message de confirmation en bas de page ; action : { label, href } (lien) ou { label, onClick } (bouton)
    function showToast(message, action, isError) {
        var old = document.querySelector('.optimizr-toast');
        if (old) old.remove();
        clearTimeout(toastTimer);

        var toast = document.createElement('div');
        toast.className = 'optimizr-toast' + (isError ? ' is-error' : '');
        toast.setAttribute('role', 'status');

        var text = document.createElement('span');
        text.className = 'optimizr-toast__text';
        text.innerHTML = svgIcon(isError ? ICON_ALERT : ICON_CHECK, 16);
        text.appendChild(document.createTextNode(message));
        toast.appendChild(text);

        if (action) {
            var button = document.createElement(action.href ? 'a' : 'button');
            button.className = 'optimizr-toast__action';
            button.textContent = action.label;
            if (action.href) {
                button.href = action.href;
            } else {
                button.type = 'button';
                button.addEventListener('click', function () {
                    toast.remove();
                    action.onClick();
                });
            }
            toast.appendChild(button);
        }

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'optimizr-toast__close';
        close.setAttribute('aria-label', __('Close', 'optimizr'));
        close.innerHTML = svgIcon('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>', 14);
        close.addEventListener('click', function () { toast.remove(); });
        toast.appendChild(close);

        (document.querySelector('.optimizr') || document.body).appendChild(toast);
        toastTimer = setTimeout(function () { toast.remove(); }, 6000);
    }

    function post(dashboard, data) {
        var body = new FormData();
        body.append('_ajax_nonce', dashboard.dataset.nonce);
        Object.keys(data).forEach(function (key) { body.append(key, data[key]); });
        return fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error(json && json.data && json.data.message ? json.data.message : '');
                }
                return json.data;
            });
    }

    function updateGroupCount(group) {
        var count = group.querySelector('[data-optimizr-group-count]');
        if (!count) return;
        var active = group.querySelectorAll('[data-module-toggle][aria-checked="true"]').length;
        var total = parseInt(count.dataset.total, 10);
        /* translators: 1: number of active modules, 2: number of modules in the theme */
        count.textContent = sprintf(_n('%1$s active out of %2$s', '%1$s active out of %2$s', active, 'optimizr'), active, total);
    }

    // Activation d'un module : enregistrée aussitôt, menu mis à jour, confirmation avec « Configurer » ou « Annuler »
    function setModule(dashboard, row, active) {
        var toggle = row.querySelector('[data-module-toggle]');
        var status = row.querySelector('[data-module-status]');
        var configure = row.querySelector('[data-module-configure]');
        var label = row.dataset.label;

        toggle.setAttribute('aria-busy', 'true');
        toggle.setAttribute('aria-checked', active ? 'true' : 'false');
        row.classList.toggle('is-off', !active);

        post(dashboard, { action: 'haptic_module_toggle', module: row.dataset.module, active: active ? 1 : 0 })
            .then(function (data) {
                toggle.setAttribute('aria-checked', data.active ? 'true' : 'false');
                row.classList.toggle('is-off', !data.active);
                status.className = 'optimizr-status optimizr-status--' + data.status;
                status.textContent = data.label;
                configure.hidden = !data.active;

                var nav = document.querySelector('[data-optimizr-tabs-nav]');
                if (nav && data.tabs) {
                    nav.innerHTML = data.tabs;
                }

                row.classList.add('is-changed');
                setTimeout(function () { row.classList.remove('is-changed'); }, 1200);
                updateGroupCount(row.closest('.optimizr-group'));

                if (data.active) {
                    /* translators: %s: module name */
                    showToast(sprintf(__('%s enabled.', 'optimizr'), label), { label: __('Configure →', 'optimizr'), href: configure.href });
                } else {
                    /* translators: %s: module name */
                    showToast(sprintf(__('%s disabled. Its settings are kept.', 'optimizr'), label), {
                        label: __('Undo', 'optimizr'),
                        onClick: function () { setModule(dashboard, row, true); }
                    });
                }
            })
            .catch(function (error) {
                toggle.setAttribute('aria-checked', active ? 'false' : 'true');
                row.classList.toggle('is-off', active);
                showToast(error.message || __('The change could not be saved. Try again.', 'optimizr'), null, true);
            })
            .then(function () {
                toggle.removeAttribute('aria-busy');
            });
    }

    function initDashboard() {
        var dashboard = document.querySelector('[data-optimizr-dashboard]');
        if (!dashboard) return;

        dashboard.querySelectorAll('[data-module-toggle]').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                if (toggle.getAttribute('aria-busy') === 'true') return;
                setModule(dashboard, toggle.closest('[data-module]'), toggle.getAttribute('aria-checked') !== 'true');
            });
        });

        // « Plus tard » (assistant) et « Masquer » (présentation d'Optimizr Pro) : retenus pour ce compte
        dashboard.querySelectorAll('[data-optimizr-hide]').forEach(function (button) {
            button.addEventListener('click', function () {
                var key = button.dataset.optimizrHide;
                var block = dashboard.querySelector('[data-optimizr-hideable="' + key + '"]');
                if (block) block.hidden = true;
                post(dashboard, { action: 'haptic_dashboard_hide', key: key }).catch(function () {});
            });
        });

        // Dernier audit : mobile ou ordinateur
        var audit = dashboard.querySelector('[data-optimizr-audit-card]');
        if (audit) {
            audit.querySelectorAll('[data-audit-show]').forEach(function (button) {
                button.addEventListener('click', function () {
                    audit.querySelectorAll('[data-audit-show]').forEach(function (other) {
                        other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                    });
                    audit.querySelectorAll('[data-audit-panel]').forEach(function (panel) {
                        panel.hidden = panel.dataset.auditPanel !== button.dataset.auditShow;
                    });
                });
            });
        }
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
                    // Texte réellement affiché sur le bouton (sombre ou blanc, cf. readableText)
                    var ratio = contrast(value, readableText(value));
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
                if (btn) {
                    btn.style.background = value;
                    btn.style.color = readableText(value);
                }
            }

            input.addEventListener('input', update);
            update();
        });

        document.querySelectorAll('[data-tac-appearance]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (!preview || !radio.checked) return;
                preview.querySelectorAll('[data-tac-preview-look]').forEach(function (look) {
                    look.hidden = look.dataset.tacPreviewLook !== radio.value;
                });
            });
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

    /* ---------- SEO : carte « Page d'accueil » (functions/seo/seo-home.php) ---------- */

    function initHomeSeo() {
        // Compteurs de caractères : le titre vide compte celui de WordPress (affiché en exemple grisé)
        document.querySelectorAll('[data-seo-home-count]').forEach(function (counter) {
            var input = document.getElementById(counter.dataset.seoHomeCount);
            if (!input) return;
            var max = parseInt(counter.dataset.max, 10);

            function update() {
                var text = (input.value.trim() || (input.tagName === 'INPUT' ? input.placeholder : '')).replace(/\s+/g, ' ');
                /* translators: 1: current length, 2: recommended maximum length */
                counter.textContent = sprintf(__('%1$s / %2$s characters', 'optimizr'), text.length, max);
                counter.classList.toggle('is-warn', text.length > max);
            }

            input.addEventListener('input', update);
            update();
        });

        // Image de partage : vignette et bouton « Choisir… » (médiathèque de WordPress)
        var box = document.querySelector('[data-seo-home-media]');
        if (!box) return;

        var labels = JSON.parse(box.dataset.seoHomeMedia || '{}');
        var input = box.querySelector('input');
        var thumb = box.querySelector('[data-seo-home-thumb]');
        var img = thumb.querySelector('img');
        var frame = null;

        function updateThumb() {
            var url = input.value.trim() || thumb.dataset.default || '';
            if (url) {
                img.src = url;
                thumb.removeAttribute('data-empty');
            } else {
                img.removeAttribute('src');
                thumb.setAttribute('data-empty', '');
            }
        }

        input.addEventListener('input', updateThumb);

        box.querySelector('[data-seo-home-pick]').addEventListener('click', function () {
            if (!window.wp || !wp.media) {
                input.focus();
                return;
            }
            if (!frame) {
                frame = wp.media({
                    title: labels.title,
                    button: { text: labels.button },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    // Taille « large » si elle existe : suffisante pour les partages, plus légère que l'original
                    var size = attachment.sizes && attachment.sizes.large ? attachment.sizes.large : attachment;
                    input.value = size.url || attachment.url;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
            frame.open();
        });
    }

    /* ---------- Menu latéral : liste déroulante des petits écrans ---------- */

    function initNav() {
        // Délégué : le menu est remplacé après l'activation d'un module
        document.addEventListener('change', function (event) {
            var select = event.target.closest && event.target.closest('[data-optimizr-nav-select]');
            if (select && select.value) {
                window.location.href = select.value;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initNav();
        initSaveBar();
        initDashboard();
        initCookieBanner();
        initServices();
        initSeoPreview();
        initHomeSeo();
    });
})();
