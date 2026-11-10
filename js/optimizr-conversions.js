/**
 * Optimizr — suivi des conversions sur le site (functions/conversions.php).
 *
 * Actions suivies (window.optimizrConversions.actions) : formulaire Contact Form 7 envoyé (form), inscription à la
 * lettre d'information MailPoet, Brevo ou Mailchimp for WordPress (newsletter), clic sur un numéro de téléphone
 * (phone), une adresse e-mail (email), WhatsApp (whatsapp) ou un service de prise de rendez-vous (booking),
 * téléchargement d'un fichier (download). Chaque conversion part :
 * - vers gtag() (Google Analytics 4, Google Ads), qui applique le Google Consent Mode v2 ;
 * - vers le dataLayer de Google Tag Manager : { event: 'optimizr_conversion', conversion_action, conversion_event } ;
 * - en évènement DOM « optimizr:conversion » sur document, pour les extensions (Optimizr Pro : Google Ads, Meta).
 * Rien n'est envoyé à un outil que tarteaucitron.js n'a pas chargé.
 * Autres formulaires ou boutons : window.optimizrConversion('form', { form_id: 'devis' }).
 */
(function () {
    'use strict';

    var config = window.optimizrConversions;
    if (!config || !config.actions || !config.actions.length) return;

    var enabled = {};
    config.actions.forEach(function (action) { enabled[action] = true; });
    var last = {};

    function track(action, params, extra) {
        var event = config.events[action];
        if (!event) return;

        // Double clic, ou formulaire renvoyé dans la seconde : une seule conversion
        var now = Date.now();
        if (last[action] && now - last[action] < 1500) return;
        last[action] = now;

        params = params || {};
        if (config.gtag && typeof window.gtag === 'function') {
            window.gtag('event', event.ga4, params);
        }
        if (config.gtm && window.dataLayer && typeof window.dataLayer.push === 'function') {
            var data = { event: 'optimizr_conversion', conversion_action: action, conversion_event: event.ga4 };
            Object.keys(params).forEach(function (key) { data[key] = params[key]; });
            window.dataLayer.push(data);
        }

        var detail = { action: action, event: event, params: params };
        if (extra) {
            Object.keys(extra).forEach(function (key) { detail[key] = extra[key]; });
        }
        var custom;
        try {
            custom = new CustomEvent('optimizr:conversion', { detail: detail });
        } catch (e) {
            custom = document.createEvent('CustomEvent');
            custom.initCustomEvent('optimizr:conversion', false, false, detail);
        }
        document.dispatchEvent(custom);

        // Date de la dernière conversion, pour l'onglet Cookies & RGPD (aucune donnée du visiteur)
        if (config.ping && navigator.sendBeacon && window.FormData) {
            var body = new FormData();
            body.append('action', 'haptic_conversion_seen');
            body.append('type', action);
            navigator.sendBeacon(config.ping, body);
        }
    }

    // API publique : formulaires d'autres extensions, boutons du thème
    window.optimizrConversion = function (action, params) {
        if (enabled[action]) track(action, params);
    };

    var DOWNLOAD = /\.(pdf|docx?|xlsx?|pptx?|odt|ods|odp|csv|zip|rar|7z|epub)$/i;

    function hostMatches(host, domains) {
        host = host.replace(/^www\./, '');
        for (var i = 0; i < domains.length; i++) {
            if (host === domains[i] || host.slice(-domains[i].length - 1) === '.' + domains[i]) return true;
        }
        return false;
    }

    function linkAction(link) {
        var href = (link.getAttribute('href') || '').trim();
        var lower = href.toLowerCase();
        if (lower.indexOf('tel:') === 0) return 'phone';
        if (lower.indexOf('mailto:') === 0) return 'email';
        if (lower.indexOf('whatsapp:') === 0) return 'whatsapp';
        if (!/^https?:/i.test(link.href)) return '';
        if (link.hasAttribute('download') || DOWNLOAD.test(link.pathname || '')) return 'download';
        var host = (link.hostname || '').toLowerCase();
        if (host === 'wa.me' || hostMatches(host, ['whatsapp.com'])) return 'whatsapp';
        if (config.booking && config.booking.length && hostMatches(host, config.booking)) return 'booking';
        return '';
    }

    document.addEventListener('click', function (e) {
        var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) return;
        var action = linkAction(link);
        if (!action || !enabled[action]) return;

        var params = { link_location: link.closest('header, nav') ? 'header' : (link.closest('footer') ? 'footer' : 'content') };
        if (action === 'booking') params.link_domain = link.hostname.replace(/^www\./, '');
        if (action === 'download') {
            var file = (link.pathname || '').split('/').pop() || '';
            try { file = decodeURIComponent(file); } catch (e) { /* nom tel quel */ }
            params.file_name = file;
            params.file_extension = file.indexOf('.') > -1 ? file.split('.').pop().toLowerCase() : '';
        }
        track(action, params);
    }, true);

    // Contact Form 7 : message réellement envoyé
    if (enabled.form) {
        document.addEventListener('wpcf7mailsent', function (e) {
            var detail = e.detail || {};
            track('form', { form_id: String(detail.contactFormId || '') }, { inputs: detail.inputs || [] });
        });
    }

    /*
     * Lettre d'information : inscription confirmée par l'extension, jamais au simple clic.
     * method : extension d'origine (paramètre recommandé de l'évènement GA4 sign_up). inputs : champs du formulaire
     * [{ name, value }], comme pour Contact Form 7 (Optimizr Pro en tire l'e-mail des conversions améliorées).
     */
    if (enabled.newsletter) {
        var signUp = function (method, formId, inputs) {
            track('newsletter', { method: method, form_id: String(formId || '') }, { inputs: inputs || [] });
        };

        // Requête AJAX jQuery : données envoyées en chaîne (a=1&b=2) ou en tableau [{ name, value }]
        var ajaxFields = function (data) {
            var fields = [];
            if (Array.isArray(data)) {
                data.forEach(function (field) {
                    if (field && field.name) fields.push({ name: String(field.name), value: String(field.value || '') });
                });
            } else if (typeof data === 'string' && window.URLSearchParams) {
                new URLSearchParams(data).forEach(function (value, name) { fields.push({ name: name, value: value }); });
            }
            return fields;
        };
        var fieldValue = function (fields, name) {
            for (var i = 0; i < fields.length; i++) {
                if (fields[i].name === name) return fields[i].value;
            }
            return '';
        };

        // MailPoet (endpoint subscribers, méthode subscribe) et Brevo (sib_form_action=subscribe_form_submit) :
        // envoi AJAX par jQuery, réponse lue dans l'évènement global ajaxSuccess
        var watchAjax = function () {
            var $ = window.jQuery;
            if (!$ || !$.fn || !$.fn.on) return;
            $(document).on('ajaxSuccess', function (e, xhr, settings, response) {
                var fields = ajaxFields(settings && settings.data);
                if (!fields.length) return;
                response = response || (xhr && xhr.responseJSON) || {};

                if (fieldValue(fields, 'action') === 'mailpoet' && fieldValue(fields, 'endpoint') === 'subscribers'
                    && fieldValue(fields, 'method') === 'subscribe') {
                    // Champs « data[form_field_…] » : seuls ceux du visiteur sont gardés
                    var mailpoet = fields.filter(function (field) { return field.name.indexOf('data[form_field_') === 0; });
                    if (!response.errors || !response.errors.length) signUp('MailPoet', fieldValue(fields, 'data[form_id]'), mailpoet);
                    return;
                }
                if (fieldValue(fields, 'sib_form_action') === 'subscribe_form_submit') {
                    // « already_exist » : déjà inscrit, pas de nouvelle conversion
                    if (response.status === 'success' || response.status === 'update') {
                        signUp('Brevo', fieldValue(fields, 'sib_form_id'), fields.filter(function (field) {
                            return field.name !== 'security' && field.name.indexOf('sib_') !== 0;
                        }));
                    }
                }
            });
        };

        // Formulaire Brevo intégré (code HTML copié depuis Brevo, envoyé à sibforms.com) : le panneau
        // #success-message s'affiche, ou la page de confirmation remplace le formulaire
        var watchBrevoEmbed = function () {
            var container = document.getElementById('sib-form-container');
            var form = document.getElementById('sib-form');
            if (!container || !form || !window.MutationObserver) return;
            var type = form.getAttribute('data-type');
            if (type && type !== 'subscription') return;

            var pending = null;
            form.addEventListener('submit', function () {
                var fields = [];
                if (window.FormData) {
                    new FormData(form).forEach(function (value, name) {
                        if (typeof value === 'string' && name !== 'g-recaptcha-response') fields.push({ name: name, value: value });
                    });
                }
                pending = fields;
            }, true);

            new MutationObserver(function () {
                if (!pending) return;
                var success = container.querySelector('#success-message');
                var error = container.querySelector('#error-message');
                if ((success && success.classList.contains('sib-form-message-panel--active')) || !container.contains(form)) {
                    signUp('Brevo', form.getAttribute('action') ? form.getAttribute('action').split('/').pop() : '', pending);
                    pending = null;
                } else if (error && error.classList.contains('sib-form-message-panel--active')) {
                    pending = null;
                }
            }).observe(container, { attributes: true, attributeFilter: ['class'], childList: true, subtree: true });
        };

        // Mailchimp for WordPress : évènement « subscribed » de son API (formulaire envoyé en AJAX ou page rechargée).
        // Si son script n'est pas encore chargé, la file d'attente qu'il lit au démarrage est créée comme il le fait.
        if (config.mc4wp) {
            window.mc4wp = window.mc4wp || {
                listeners: [],
                forms: {
                    on: function (event, callback) { window.mc4wp.listeners.push({ event: event, callback: callback }); }
                }
            };
        }
        if (window.mc4wp && window.mc4wp.forms && typeof window.mc4wp.forms.on === 'function') {
            window.mc4wp.forms.on('subscribed', function (form, data) {
                var inputs = [];
                Object.keys(data || {}).forEach(function (name) {
                    if (typeof data[name] === 'string' && name.indexOf('_mc4wp') !== 0) inputs.push({ name: name, value: data[name] });
                });
                signUp('Mailchimp', form && form.id, inputs);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { watchAjax(); watchBrevoEmbed(); });
        } else {
            watchAjax();
            watchBrevoEmbed();
        }
    }
})();
