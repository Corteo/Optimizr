/**
 * Optimizr — import depuis une autre extension SEO (onglet Outils, functions/migration.php).
 * Les contenus sont importés par lots (AJAX haptic_migration_batch), source après source, puis
 * haptic_migration_finish active le SEO d'Optimizr et désactive les extensions choisies.
 */
(function () {
    'use strict';

    var __ = wp.i18n.__;
    var _n = wp.i18n._n;
    var sprintf = wp.i18n.sprintf;

    var form = document.querySelector('[data-migration]');
    if (!form) {
        return;
    }

    var button = form.querySelector('[data-migration-submit]');
    var status = form.querySelector('[data-migration-status]');
    var progress = form.querySelector('[data-migration-progress]');
    var bar = progress.querySelector('span');

    // Un seul jeu de modèles de titre et de description : cocher celui d'une source décoche les autres
    var patternBoxes = form.querySelectorAll('[data-migration-patterns]');
    Array.prototype.forEach.call(patternBoxes, function (box) {
        box.addEventListener('change', function () {
            if (box.checked) {
                Array.prototype.forEach.call(patternBoxes, function (other) {
                    if (other !== box) {
                        other.checked = false;
                    }
                });
            }
        });
    });

    function setStatus(text, isError) {
        status.textContent = text;
        status.classList.toggle('is-error', !!isError);
    }

    function post(action, fields) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('_ajax_nonce', form.getAttribute('data-nonce'));
        Object.keys(fields).forEach(function (name) {
            var value = fields[name];
            if (Array.isArray(value)) {
                value.forEach(function (item) { body.append(name + '[]', item); });
            } else if (value && typeof value === 'object') {
                Object.keys(value).forEach(function (key) { body.append(name + '[' + key + ']', value[key]); });
            } else {
                body.append(name, value);
            }
        });
        return fetch(form.getAttribute('data-ajax-url'), { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error(json && json.data && json.data.message ? json.data.message : '');
                }
                return json.data;
            });
    }

    function checked(name) {
        return Array.prototype.map.call(form.querySelectorAll('input[name="' + name + '[]"]:checked'), function (input) {
            return input.value;
        });
    }

    function summary(counts) {
        var parts = [];
        if (counts.title) {
            /* translators: %s: number of SEO titles */
            parts.push(sprintf(_n('%s title', '%s titles', counts.title, 'optimizr'), counts.title));
        }
        if (counts.description) {
            /* translators: %s: number of meta descriptions */
            parts.push(sprintf(_n('%s description', '%s descriptions', counts.description, 'optimizr'), counts.description));
        }
        if (counts.image) {
            /* translators: %s: number of share images */
            parts.push(sprintf(_n('%s share image', '%s share images', counts.image, 'optimizr'), counts.image));
        }
        if (counts.noindex) {
            /* translators: %s: number of contents hidden from search engines */
            parts.push(sprintf(_n('%s hidden from search engines', '%s hidden from search engines', counts.noindex, 'optimizr'), counts.noindex));
        }
        return parts.length ? parts.join(', ') : __('nothing new', 'optimizr');
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var sources = checked('sources');
        var extras = Array.prototype.filter.call(form.querySelectorAll('input[name="extras[]"]:checked'), function () { return true; });
        if ((!sources.length && !extras.length) || button.disabled) {
            setStatus(__('Choose at least one plugin to import from.', 'optimizr'), true);
            return;
        }
        var patterns = checked('patterns');
        // Une extension n'est désactivée que si quelque chose en a été importé
        var deactivate = checked('deactivate').filter(function (slug) {
            return sources.indexOf(slug) !== -1 || extras.some(function (input) { return input.getAttribute('data-source') === slug; });
        });
        var messages = [];
        var counts = { title: 0, description: 0, image: 0, noindex: 0, kept: 0 };
        var estimate = 0;
        var processed = 0;
        sources.forEach(function (slug) {
            var fieldset = form.querySelector('[data-migration-source="' + slug + '"]');
            estimate += parseInt(fieldset.getAttribute('data-estimate'), 10) || 0;
        });
        // Chaque autre donnée compte pour un lot dans la barre de progression
        var unit = Math.max(1, Math.round(estimate / 20));
        estimate += extras.length * unit;

        function runExtras(index) {
            if (index >= extras.length) {
                return Promise.resolve();
            }
            var input = extras[index];
            var label = form.querySelector('[data-migration-source="' + input.getAttribute('data-source') + '"]').getAttribute('data-label');
            /* translators: %s: plugin name (Yoast SEO, Rank Math…) */
            setStatus(sprintf(__('Importing from %s…', 'optimizr'), label));
            return post('haptic_migration_extra', { extra: input.value }).then(function (data) {
                if (data.message) {
                    /* translators: 1: plugin name, 2: result of the import */
                    messages.push(sprintf(__('%1$s: %2$s', 'optimizr'), label, data.message));
                }
                processed += unit;
                bar.style.width = Math.min(95, Math.round(processed / estimate * 100)) + '%';
                return runExtras(index + 1);
            });
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        progress.hidden = false;
        bar.style.width = '0';

        function run(index, after) {
            if (index >= sources.length) {
                return Promise.resolve();
            }
            var slug = sources[index];
            var label = form.querySelector('[data-migration-source="' + slug + '"]').getAttribute('data-label');
            /* translators: %s: plugin name (Yoast SEO, Rank Math…) */
            setStatus(sprintf(__('Importing from %s…', 'optimizr'), label));
            return post('haptic_migration_batch', {
                source: slug,
                after: after,
                patterns: after === 0 && patterns.indexOf(slug) !== -1 ? 1 : 0
            }).then(function (data) {
                Object.keys(data.counts).forEach(function (field) { counts[field] += data.counts[field]; });
                counts.kept += data.kept;
                processed += data.processed;
                bar.style.width = Math.min(95, estimate ? Math.round(processed / estimate * 100) : 50) + '%';
                return data.done ? run(index + 1, 0) : run(index, data.next);
            });
        }

        run(0, 0)
            .then(function () {
                return runExtras(0);
            })
            .then(function () {
                return post('haptic_migration_finish', { sources: sources, deactivate: deactivate, counts: counts, messages: messages });
            })
            .then(function (data) {
                bar.style.width = '100%';
                var text = __('Import complete.', 'optimizr');
                if (sources.length) {
                    /* translators: %s: summary of imported values (12 titles, 8 descriptions…) */
                    text = sprintf(__('Import complete: %s.', 'optimizr'), summary(counts));
                }
                if (counts.kept) {
                    /* translators: %s: number of values kept */
                    text += ' ' + sprintf(_n('%s value already set in Optimizr was kept.', '%s values already set in Optimizr were kept.', counts.kept, 'optimizr'), counts.kept);
                }
                // Une ligne par autre donnée importée (réglages de sécurité, redirections)
                if (messages.length) {
                    text += '\n' + messages.join('\n');
                }
                if (data.deactivated && data.deactivated.length) {
                    /* translators: %s: plugin names */
                    text += '\n' + sprintf(__('Deactivated: %s.', 'optimizr'), data.deactivated.join(', '));
                }
                setStatus(text);
                button.removeAttribute('aria-busy');
            })
            .catch(function (error) {
                progress.hidden = true;
                button.disabled = false;
                button.removeAttribute('aria-busy');
                setStatus(error.message || __('The import was interrupted. Values already imported are kept: run it again to finish.', 'optimizr'), true);
            });
    });
})();
