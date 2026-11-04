/**
 * Optimizr — suivi des conversions sur le site (functions/conversions.php).
 *
 * Actions suivies (window.optimizrConversions.actions) : formulaire Contact Form 7 envoyé (form), clic sur un
 * numéro de téléphone (phone), une adresse e-mail (email), WhatsApp (whatsapp) ou un service de prise de
 * rendez-vous (booking). Chaque conversion part :
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
        track(action, params);
    }, true);

    // Contact Form 7 : message réellement envoyé
    if (enabled.form) {
        document.addEventListener('wpcf7mailsent', function (e) {
            var detail = e.detail || {};
            track('form', { form_id: String(detail.contactFormId || '') }, { inputs: detail.inputs || [] });
        });
    }
})();
