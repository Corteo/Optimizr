/**
 * Optimizr — suivi des conversions sur le site (functions/conversions.php).
 *
 * Actions suivies (window.optimizrConversions.actions) : formulaire envoyé (form : Contact Form 7, WPForms, Gravity
 * Forms, Fluent Forms, Elementor Pro, Ninja Forms, Formidable), inscription à la lettre d'information MailPoet,
 * Brevo ou Mailchimp for WordPress (newsletter), page de remerciement (thankyou), clic sur un numéro de téléphone
 * (phone), une adresse email (email), WhatsApp (whatsapp), un service de prise de rendez-vous (booking), un
 * itinéraire (directions), un élément de classe optimizr-conversion (cta), un fichier (download), le formulaire
 * d'avis Google (review), un réseau social (social) ; WooCommerce : add_to_cart, begin_checkout, purchase.
 * Chaque conversion part :
 * - vers gtag() (Google Analytics 4, Google Ads), qui applique le Google Consent Mode v2 ;
 * - vers le dataLayer de Google Tag Manager : { event: 'optimizr_conversion', conversion_action, conversion_event } ;
 * - en évènement DOM « optimizr:conversion » sur document, pour les extensions (Optimizr Pro : Google Ads, Meta).
 * Rien n'est envoyé à un outil que tarteaucitron.js n'a pas chargé. Un signal anonyme compte la conversion sur le
 * site (onglet Suivi, functions/tracking.php), que le visiteur ait accepté les cookies ou non.
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

        // Compte anonyme pour l'onglet Suivi : action, chemin de la page, accord du visiteur pour la mesure
        // d'audience ; numéro de commande pour un achat (le montant est relu côté serveur). Ni cookie ni donnée du visiteur.
        if (config.ping && navigator.sendBeacon && window.FormData) {
            var body = new FormData();
            body.append('action', 'haptic_conversion_seen');
            body.append('type', action);
            body.append('page', window.location.pathname);
            body.append('consent', consentState());
            if (action === 'purchase' && config.woo && config.woo.purchase) body.append('order', config.woo.purchase.order || '');
            navigator.sendBeacon(config.ping, body);
        }
    }

    // 1 : Google Analytics ou Tag Manager accepté ; 0 : refusé ou pas encore choisi ; 2 : aucun outil de mesure
    function consentState() {
        if (!config.gtag && !config.gtm) return '2';
        var state = window.tarteaucitron && window.tarteaucitron.state ? window.tarteaucitron.state : {};
        return (config.gtag && state.gtag === true) || (config.gtm && state.googletagmanager === true) ? '1' : '0';
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

    var SOCIAL = ['facebook.com', 'fb.com', 'instagram.com', 'linkedin.com', 'tiktok.com', 'youtube.com', 'youtu.be',
        'x.com', 'twitter.com', 'pinterest.com', 'pinterest.fr', 'threads.net', 'snapchat.com'];

    function linkAction(link) {
        var href = (link.getAttribute('href') || '').trim();
        var lower = href.toLowerCase();
        if (lower.indexOf('tel:') === 0) return 'phone';
        if (lower.indexOf('mailto:') === 0) return 'email';
        if (lower.indexOf('whatsapp:') === 0) return 'whatsapp';
        if (lower.indexOf('geo:') === 0 || lower.indexOf('maps:') === 0) return 'directions';
        if (!/^https?:/i.test(link.href)) return '';
        if (link.hasAttribute('download') || DOWNLOAD.test(link.pathname || '')) return 'download';
        var host = (link.hostname || '').toLowerCase().replace(/^www\./, '');
        var path = (link.pathname || '').toLowerCase();
        if (host === 'wa.me' || hostMatches(host, ['whatsapp.com'])) return 'whatsapp';
        if (config.booking && config.booking.length && hostMatches(host, config.booking)) return 'booking';
        // Avis Google avant l'itinéraire : les deux passent par des adresses Google
        if ((host === 'g.page' && /\/review\/?$/.test(path)) || path.indexOf('writereview') > -1) return 'review';
        if (/^(maps\.)?google\.[a-z.]+$/.test(host) && (host.indexOf('maps.') === 0 || path.indexOf('/maps') === 0)) return 'directions';
        if (host === 'maps.app.goo.gl' || (host === 'goo.gl' && path.indexOf('/maps') === 0)
            || hostMatches(host, ['waze.com', 'maps.apple.com'])) return 'directions';
        if (hostMatches(host, SOCIAL)) return 'social';
        return '';
    }

    function linkLocation(el) {
        return el.closest('header, nav') ? 'header' : (el.closest('footer') ? 'footer' : 'content');
    }

    document.addEventListener('click', function (e) {
        var target = e.target && e.target.closest ? e.target : null;
        if (!target) return;

        // Bouton ou lien marqué de la classe optimizr-conversion (en plus de l'action propre au lien)
        var marked = enabled.cta ? target.closest('.optimizr-conversion') : null;
        if (marked) {
            track('cta', {
                link_text: (marked.getAttribute('aria-label') || marked.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 100),
                link_location: linkLocation(marked)
            });
        }

        var link = target.closest('a[href]');
        if (!link) return;
        var action = linkAction(link);
        if (!action || !enabled[action]) return;

        var params = { link_location: linkLocation(link) };
        if (action === 'booking' || action === 'directions' || action === 'social') params.link_domain = (link.hostname || '').replace(/^www\./, '');
        if (action === 'download') {
            var file = (link.pathname || '').split('/').pop() || '';
            try { file = decodeURIComponent(file); } catch (e) { /* nom tel quel */ }
            params.file_name = file;
            params.file_extension = file.indexOf('.') > -1 ? file.split('.').pop().toLowerCase() : '';
        }
        track(action, params);
    }, true);

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    // Page de remerciement : chemin de la page parmi ceux saisis (sans barre finale ni majuscules)
    if (enabled.thankyou && config.thankyou && config.thankyou.length) {
        var here = '/' + window.location.pathname.toLowerCase().replace(/^\/+|\/+$/g, '');
        try { here = decodeURI(here); } catch (e) { /* chemin tel quel */ }
        if (config.thankyou.indexOf(here) > -1) track('thankyou', { page_path: here });
    }

    /*
     * Formulaires : message réellement envoyé, jamais au simple clic. Les champs saisis sont relevés à l'envoi
     * (l'extension vide souvent le formulaire avant d'annoncer le succès) et accompagnent la conversion (inputs).
     */
    if (enabled.form) {
        var submitted = window.WeakMap ? new WeakMap() : null;
        var lastFields = [];
        var formFields = function (form) {
            var fields = [];
            if (!form || !window.FormData) return fields;
            try {
                new FormData(form).forEach(function (value, name) {
                    if (typeof value === 'string' && value !== '' && !/password|token|nonce|captcha|^_/i.test(name)) {
                        fields.push({ name: name, value: value });
                    }
                });
            } catch (e) { /* formulaire illisible */ }
            return fields;
        };
        document.addEventListener('submit', function (e) {
            if (!e.target || e.target.tagName !== 'FORM') return;
            lastFields = formFields(e.target);
            if (submitted) submitted.set(e.target, lastFields);
        }, true);
        var fieldsOf = function (form) {
            return (form && submitted && submitted.get(form)) || lastFields;
        };
        var formSent = function (plugin, formId, form, inputs) {
            track('form', { form_plugin: plugin, form_id: String(formId || '') }, { inputs: inputs || fieldsOf(form) });
        };

        // Contact Form 7
        document.addEventListener('wpcf7mailsent', function (e) {
            var detail = e.detail || {};
            formSent('Contact Form 7', detail.contactFormId, e.target, detail.inputs || []);
        });

        // Fluent Forms : évènement DOM sur document
        document.addEventListener('fluentform_submission_success', function (e) {
            var form = e.detail && e.detail.form;
            formSent('Fluent Forms', form && form.getAttribute ? (form.getAttribute('data-form_id') || form.id) : '', form);
        });

        onReady(function () {
            var $ = window.jQuery;
            if ($ && $.fn && $.fn.on) {
                // WPForms (AJAX ; aussi avant une redirection)
                $(document).on('wpformsAjaxSubmitSuccess', function (e) {
                    var form = e.target;
                    formSent('WPForms', form && form.getAttribute ? form.getAttribute('data-formid') : '', form);
                });
                // Gravity Forms (AJAX)
                $(document).on('gform_confirmation_loaded', function (e, formId) {
                    formSent('Gravity Forms', formId, document.getElementById('gform_' + formId));
                });
                // Elementor Pro
                $(document).on('submit_success', function (e) {
                    var form = e.target;
                    if (!form || !form.closest || !form.closest('.elementor-form, .elementor-widget-form')) return;
                    var id = form.querySelector('input[name="form_id"]');
                    formSent('Elementor Pro', id ? id.value : '', form);
                });
                // Formidable (AJAX)
                $(document).on('frmFormComplete', function (e, form) {
                    var el = form && form.jquery ? form[0] : form;
                    var id = el && el.querySelector ? el.querySelector('input[name="form_id"]') : null;
                    formSent('Formidable', id ? id.value : '', el);
                });
            }

            // Ninja Forms : canal « forms » de sa messagerie interne
            if (window.nfRadio && typeof window.nfRadio.channel === 'function') {
                window.nfRadio.channel('forms').on('submit:response', function (response) {
                    response = response || {};
                    var errors = response.errors;
                    if (errors && (Array.isArray(errors) ? errors.length : Object.keys(errors).length)) return;
                    var formId = response.data && response.data.form_id;
                    formSent('Ninja Forms', formId, document.querySelector('#nf-form-' + formId + '-cont form'));
                });
            }

            // Envoi sans AJAX : la page rechargée affiche le message de confirmation
            var confirmations = [
                ['WPForms', '[id^="wpforms-confirmation-"]', /(\d+)$/],
                ['Gravity Forms', '[id^="gform_confirmation_message_"]', /(\d+)$/],
                ['Formidable', '.frm_forms .frm_message', null]
            ];
            confirmations.forEach(function (c) {
                var el = document.querySelector(c[1]);
                if (!el) return;
                var match = c[2] ? (el.id || '').match(c[2]) : null;
                var container = c[2] ? null : el.closest('[id^="frm_form_"]');
                var id = match ? match[1] : (container ? (container.id.match(/frm_form_(\d+)/) || [])[1] : '');
                formSent(c[0], id, null, []);
            });
        });
    }

    /*
     * Lettre d'information : inscription confirmée par l'extension, jamais au simple clic.
     * method : extension d'origine (paramètre recommandé de l'évènement GA4 sign_up). inputs : champs du formulaire
     * [{ name, value }], comme pour Contact Form 7 (Optimizr Pro en tire l'email des conversions améliorées).
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

        onReady(function () { watchAjax(); watchBrevoEmbed(); });
    }

    /*
     * WooCommerce : ajout au panier (AJAX par les listes et les blocs ; formulaire classique préparé par le serveur),
     * page de commande et confirmation de commande (montant, devise et articles préparés par le serveur).
     */
    var woo = config.woo || {};
    var currency = woo.currency || '';
    var shop = function (action, data, extra) {
        if (!enabled[action] || !data) return;
        var params = { currency: data.currency || currency, value: Number(data.value) || 0 };
        if (data.items && data.items.length) params.items = data.items;
        if (data.transaction_id) params.transaction_id = data.transaction_id;
        track(action, params, extra);
    };
    if (woo.added) shop('add_to_cart', woo.added);
    if (woo.checkout) shop('begin_checkout', woo.checkout);
    if (woo.purchase) shop('purchase', woo.purchase, { inputs: woo.purchase.email ? [{ name: 'email', value: woo.purchase.email }] : [] });

    if (enabled.add_to_cart) {
        var addedToCart = function (button) {
            var params = { currency: currency };
            var id = button && button.getAttribute ? (button.getAttribute('data-product_sku') || button.getAttribute('data-product_id')) : '';
            if (id) params.items = [{ item_id: String(id), quantity: Number(button.getAttribute('data-quantity')) || 1 }];
            track('add_to_cart', params);
        };
        onReady(function () {
            var $ = window.jQuery;
            if ($ && $.fn && $.fn.on) {
                $(document.body).on('added_to_cart', function (e, fragments, hash, $button) {
                    addedToCart($button && $button.jquery ? $button[0] : $button);
                });
            }
            // Blocs WooCommerce (le même ajout peut aussi passer par added_to_cart : une seule conversion)
            document.body.addEventListener('wc-blocks_added_to_cart', function () { addedToCart(null); });
        });
    }
})();
