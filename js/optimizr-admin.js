/**
 * Optimizr — comportements de la page d'options (functions/admin-ui.php).
 */
(function () {
    'use strict';

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

    // Même règle que hpt_readable_text_color() côté PHP
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
            text.textContent = dirty ? 'Modifications non enregistrées' : 'Toutes les modifications sont enregistrées';
        }

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        document.addEventListener('hpt:seo-pattern-change', refresh);

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

    /* ---------- Vérification des mises à jour ---------- */

    function initUpdateCheck() {
        var button = document.getElementById('hpt-check-update-btn');
        var status = document.getElementById('hpt-update-status');
        if (!button || !status || typeof window.ajaxurl === 'undefined') return;

        function show(message, state) {
            status.className = 'optimizr-header__status' + (state ? ' is-' + state : '');
            status.textContent = message;
        }

        button.addEventListener('click', function () {
            button.setAttribute('aria-busy', 'true');
            button.disabled = true;
            show('Vérification en cours…');

            var body = new URLSearchParams({ action: 'hpt_ajax_check_update', nonce: button.dataset.nonce });

            fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (!res.success) {
                        show('La vérification a échoué.', 'warn');
                    } else if (res.data.status === 'update_available') {
                        show('Version ' + res.data.new_version + ' disponible. ', 'warn');
                        var link = document.createElement('a');
                        link.href = res.data.details_url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.textContent = 'Détails';
                        status.appendChild(link);
                    } else {
                        show('Optimizr est à jour.', 'ok');
                    }
                })
                .catch(function () {
                    show('La requête a échoué. Vérifiez votre connexion.', 'warn');
                })
                .finally(function () {
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                });
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
                    contrast.textContent = (ok ? 'AA ' : 'Faible ') + ratio.toFixed(1).replace('.', ',') + ':1';
                    contrast.className = 'optimizr-contrast ' + (ok ? 'is-ok' : 'is-warn');
                }

                if (!preview) return;
                if (input.dataset.tacColor === 'hpt_option_tarteaucitron_highlight_color') {
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
                status.textContent = active ? 'Actif' : 'Inactif';
            });
        });
    }

    /* ---------- SEO : aperçus en direct ---------- */

    function initSeoPreview() {
        var root = document.querySelector('[data-seo-preview]');
        if (!root) return;

        var vars = JSON.parse(root.dataset.seoVars || '{}');
        var titleInput = document.getElementById('hpt_seo_title_pattern');
        var descInput = document.getElementById('hpt_seo_desc_pattern');
        var titleCount = root.querySelector('[data-seo-count-title]');
        var descCount = root.querySelector('[data-seo-count-desc]');

        // Même résultat que hpt_generate_seo_content() : variables remplacées, séparateurs orphelins retirés
        function render(pattern) {
            var text = Object.keys(vars).reduce(function (acc, code) {
                return acc.split(code).join(vars[code]);
            }, pattern || '');
            return text.replace(/\s+/g, ' ').replace(/^[\s\-–—|*·•:,;\/]+|[\s\-–—|*·•:,;\/]+$/g, '');
        }

        function count(el, text, max) {
            el.textContent = text.length + ' / ' + max + ' caractères';
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

        document.addEventListener('hpt:seo-pattern-change', update);
        update();
    }

    /* ---------- Assistant IA : traitement en masse ---------- */

    function initAiBulk() {
        var root = document.querySelector('[data-optimizr-bulk]');
        if (!root || typeof window.ajaxurl === 'undefined') return;

        var nonce = root.dataset.nonce;
        var running = 0;

        // Quitter la page interrompt le traitement : on prévient
        window.addEventListener('beforeunload', function (e) {
            if (running > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        root.querySelectorAll('.optimizr-bulk__task').forEach(function (task) {
            var start = task.querySelector('[data-bulk-start]');
            var stop = task.querySelector('[data-bulk-stop]');
            var progress = task.querySelector('[data-bulk-progress]');
            var bar = progress.querySelector('.optimizr-progress');
            var status = task.querySelector('[data-bulk-status]');
            var summary = task.querySelector('[data-bulk-summary]');
            var log = task.querySelector('[data-bulk-log]');

            var state = null;

            function addLog(kind, item, text) {
                var li = document.createElement('li');
                li.className = 'optimizr-bulk__entry is-' + kind;

                var badge = document.createElement('span');
                badge.className = 'optimizr-status optimizr-status--' + (kind === 'ok' ? 'ok' : 'warn');
                badge.textContent = kind === 'ok' ? 'Généré' : 'Ignoré';
                li.appendChild(badge);

                var name = document.createElement(item.link ? 'a' : 'span');
                name.textContent = item.title;
                if (item.link) {
                    name.href = item.link;
                    name.target = '_blank';
                    name.rel = 'noopener';
                }
                li.appendChild(name);

                var detail = document.createElement('span');
                detail.className = 'optimizr-bulk__detail';
                detail.textContent = text;
                li.appendChild(detail);

                log.hidden = false;
                log.insertBefore(li, log.firstChild);
                while (log.children.length > 50) log.removeChild(log.lastChild);
            }

            // Montant en dollars : « 0,0042 $ » sous un centime, « 0,42 $ » au-delà
            function formatCost(amount) {
                return amount.toLocaleString('fr-FR', {
                    minimumFractionDigits: amount < 0.01 ? 4 : 2,
                    maximumFractionDigits: amount < 0.01 ? 4 : 2
                }) + ' $';
            }

            function render() {
                var processed = state.done + state.skipped.length;
                var total = Math.max(state.total, processed);
                var percent = total ? Math.round(processed / total * 100) : 100;
                bar.firstElementChild.style.width = percent + '%';
                bar.setAttribute('aria-valuenow', percent);
                status.textContent = processed + ' / ' + total + ' traité(s) · ' + state.done + ' généré(s)'
                    + (state.skipped.length ? ' · ' + state.skipped.length + ' ignoré(s)' : '')
                    + ' · coût réel ' + formatCost(state.cost);
            }

            function finish(message, remaining) {
                running--;
                state.running = false;
                start.hidden = false;
                stop.hidden = true;
                status.textContent = message + ' Coût réel : ' + formatCost(state.cost) + '.';
                task.dataset.count = remaining;
                summary.textContent = remaining > 0 ? remaining + ' ' + task.dataset.unit + '.' : task.dataset.empty;
                start.disabled = remaining === 0;
            }

            function next() {
                if (!state.running) {
                    finish('Traitement arrêté · ' + state.done + ' généré(s).', state.remaining);
                    return;
                }

                var body = new URLSearchParams({ action: 'hpt_ai_bulk_next', nonce: nonce, task: task.dataset.task });
                state.skipped.forEach(function (id) { body.append('skip[]', id); });

                fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        var data = res.data || {};
                        state.remaining = typeof data.remaining === 'number' ? data.remaining : state.remaining;

                        if (!res.success || data.status === 'fatal') {
                            if (data.id) addLog('skipped', data, data.message);
                            finish('Arrêt : ' + (data.message || 'erreur inconnue.'), state.remaining);
                            return;
                        }
                        if (data.status === 'done') {
                            render();
                            finish('Terminé · ' + state.done + ' généré(s)' + (state.skipped.length ? ', ' + state.skipped.length + ' ignoré(s)' : '') + '.', state.skipped.length);
                            return;
                        }
                        if (data.status === 'ok') {
                            state.done++;
                            if (typeof data.cost === 'number') state.cost += data.cost;
                            addLog('ok', data, data.text);
                        } else {
                            state.skipped.push(data.id);
                            addLog('skipped', data, data.message);
                        }
                        render();
                        next();
                    })
                    .catch(function () {
                        finish('Arrêt : la requête a échoué (connexion perdue ?).', state.remaining);
                    });
            }

            start.addEventListener('click', function () {
                var total = parseInt(task.dataset.count, 10) || 0;
                state = { running: true, total: total, remaining: total, done: 0, skipped: [], cost: 0 };
                running++;
                start.hidden = true;
                stop.hidden = false;
                progress.hidden = false;
                log.textContent = '';
                render();
                next();
            });

            // L'élément en cours se termine, puis le traitement s'arrête
            stop.addEventListener('click', function () {
                if (state) state.running = false;
                stop.hidden = true;
                status.textContent = 'Arrêt après l\'élément en cours…';
            });
        });
    }

    /* ---------- Boutons (?) : panneau de détail replié ---------- */

    function initHelpToggles() {
        document.querySelectorAll('[data-costs-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var panel = document.getElementById(button.getAttribute('aria-controls'));
                if (!panel) return;
                var open = panel.hidden;
                panel.hidden = !open;
                // Tous les boutons qui pilotent ce panneau reflètent son état
                document.querySelectorAll('[aria-controls="' + panel.id + '"]').forEach(function (other) {
                    other.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                if (open) panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        });
    }

    /* ---------- Assistant IA : fournisseur ---------- */

    // Affiche le modèle et la clé du fournisseur choisi (les autres restent enregistrés)
    function initAiProvider() {
        var select = document.querySelector('[data-ai-provider-select]');
        if (!select) return;

        var initial = select.value;
        var notice = document.querySelector('[data-ai-provider-notice]');

        select.addEventListener('change', function () {
            document.querySelectorAll('.hpt-ai-provider-row').forEach(function (row) {
                row.classList.toggle('optimizr-field--hidden', !row.classList.contains('hpt-ai-provider-' + select.value));
            });
            if (notice) notice.hidden = select.value === initial;
        });
    }

    /* ---------- Redirections : type 410 et analyse des liens cassés ---------- */

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

    function initLinksScan() {
        var root = document.querySelector('[data-links-scan]');
        if (!root) return;

        var start = root.querySelector('[data-links-start]');
        var progress = root.querySelector('[data-links-progress]');
        var bar = progress.querySelector('.optimizr-progress');
        var status = root.querySelector('[data-links-status]');
        var summary = root.querySelector('[data-links-summary]');
        var results = root.querySelector('[data-links-results]');
        var rows = root.querySelector('[data-links-rows]');
        var issues = 0;

        function call(step) {
            var body = new FormData();
            body.append('action', 'hpt_links_scan');
            body.append('nonce', root.getAttribute('data-nonce'));
            body.append('step', step);
            return fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (response) { return response.json(); })
                .then(function (json) {
                    if (!json || !json.success) throw new Error((json && json.data) || 'Réponse inattendue du serveur.');
                    return json.data;
                });
        }

        function setProgress(checked, total) {
            var percent = total ? Math.round(checked / total * 100) : 100;
            bar.firstElementChild.style.width = percent + '%';
            bar.setAttribute('aria-valuenow', percent);
            status.textContent = checked + ' / ' + total + ' lien(s) vérifié(s)…';
        }

        function finish(message) {
            start.disabled = false;
            progress.hidden = true;
            summary.hidden = false;
            summary.textContent = message;
        }

        function check() {
            call('check').then(function (data) {
                data.issues.forEach(function (html) {
                    rows.insertAdjacentHTML('beforeend', html);
                    issues++;
                });
                results.hidden = issues === 0;
                setProgress(data.checked, data.total);
                if (data.remaining > 0) {
                    check();
                } else {
                    finish('Analyse terminée : ' + data.checked + ' lien(s) vérifié(s), ' + issues + ' problème(s).');
                }
            }).catch(function (error) {
                finish('Analyse interrompue : ' + error.message);
            });
        }

        start.addEventListener('click', function () {
            start.disabled = true;
            issues = 0;
            rows.innerHTML = '';
            results.hidden = true;
            summary.hidden = true;
            progress.hidden = false;
            bar.firstElementChild.style.width = '0';
            status.textContent = 'Recherche des liens dans les contenus…';

            call('collect').then(function (data) {
                if (!data.total) {
                    finish('Aucun lien trouvé dans les contenus publiés.');
                    return;
                }
                setProgress(0, data.total);
                check();
            }).catch(function (error) {
                finish('Analyse impossible : ' + error.message);
            });
        });
    }

    /* ---------- SEO local : jours fermés ---------- */

    function initLocalHours() {
        document.querySelectorAll('[data-hours-closed]').forEach(function (box) {
            var day = box.closest('.optimizr-hours__day');
            var sync = function () { day.classList.toggle('is-closed', box.checked); };
            box.addEventListener('change', sync);
            sync();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSaveBar();
        initHelpToggles();
        initAiProvider();
        initAiBulk();
        initUpdateCheck();
        initModules();
        initCookieBanner();
        initServices();
        initSeoPreview();
        initRedirectForm();
        initLinksScan();
        initLocalHours();
    });
})();
