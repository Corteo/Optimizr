/**
 * Bandeau de cookies, apparence « Thème du site » : lit le style du thème (fond, texte, liens,
 * boutons, titres) et le passe à css/optimizr-cookies.css en variables --otac-* sur <html>.
 * Le style en ligne l'emporte sur les couleurs par défaut imprimées par functions/cookies.php,
 * qui restent en place si une valeur ne peut pas être lue.
 */
(function () {
    'use strict';

    var DARK = [30, 30, 30];
    var LIGHT = [255, 255, 255];

    // Toute couleur CSS (rgb, oklch, color(), nom…) en [r, g, b, a] via un canvas d'un pixel
    var ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    function parse(color) {
        if (!ctx || !color || color === 'transparent') return null;
        ctx.clearRect(0, 0, 1, 1);
        ctx.fillStyle = '#000';
        ctx.fillStyle = color;
        ctx.fillRect(0, 0, 1, 1);
        var d = ctx.getImageData(0, 0, 1, 1).data;
        return d[3] === 0 ? null : [d[0], d[1], d[2], d[3] / 255];
    }

    function luminance(rgb) {
        var c = rgb.slice(0, 3).map(function (v) {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    }

    function contrast(a, b) {
        var la = luminance(a), lb = luminance(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    // Même règle que haptic_readable_text_color() côté PHP
    function readable(bg) {
        var dark = contrast(bg, DARK), light = contrast(bg, LIGHT);
        if (Math.max(dark, light) < 4.5) return [0, 0, 0];
        return dark >= light ? DARK : LIGHT;
    }

    // Couleur posée sur un fond, ou à défaut celle qui y reste lisible
    function onBackground(color, bg) {
        return color && contrast(color, bg) >= 4.5 ? color : readable(bg);
    }

    // Couleur semi-transparente mélangée au fond (rendu réel d'un bouton translucide)
    function flatten(color, bg) {
        if (!color) return null;
        var a = color[3];
        return [0, 1, 2].map(function (i) { return Math.round(color[i] * a + bg[i] * (1 - a)); });
    }

    function css(rgb) {
        return 'rgb(' + rgb.slice(0, 3).join(' ') + ')';
    }

    // Élément témoin, hors écran : reçoit les styles du thème sans être vu
    function probe(markup, selector) {
        var box = document.createElement('div');
        box.setAttribute('aria-hidden', 'true');
        box.style.cssText = 'position:absolute;left:-9999px;top:0;visibility:hidden;pointer-events:none';
        box.innerHTML = markup;
        document.body.appendChild(box);
        var el = box.querySelector(selector);
        var style = getComputedStyle(el);
        var read = {
            bg: parse(style.backgroundColor),
            color: parse(style.color),
            radius: parseFloat(style.borderTopLeftRadius) || 0,
            border: parseFloat(style.borderTopWidth) ? parse(style.borderTopColor) : null,
            font: style.fontFamily,
            weight: style.fontWeight,
            transform: style.textTransform,
            tracking: style.letterSpacing
        };
        box.remove();
        return read;
    }

    function pageBackground() {
        var candidates = [document.body, document.querySelector('.wp-site-blocks'), document.getElementById('page'), document.querySelector('.site'), document.documentElement];
        for (var i = 0; i < candidates.length; i++) {
            if (!candidates[i]) continue;
            var c = parse(getComputedStyle(candidates[i]).backgroundColor);
            if (c && c[3] > 0.9) return c;
        }
        return LIGHT;
    }

    function apply() {
        var root = document.documentElement;
        var set = function (name, value) { root.style.setProperty('--otac-' + name, value); };

        var bg = pageBackground();
        var text = onBackground(parse(getComputedStyle(document.body).color), bg);

        // Bouton du thème : bloc Bouton de l'éditeur, puis bouton classique, puis couleur des liens
        var button = probe('<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">x</a></div>', 'a');
        if (!button.bg || button.bg[3] < 0.5) {
            var classic = probe('<button type="button" class="button">x</button>', 'button');
            if (classic.bg && classic.bg[3] >= 0.5 && contrast(flatten(classic.bg, bg), bg) > 1.2) {
                button = classic;
            }
        }
        var link = probe('<p><a href="#">x</a></p>', 'a').color;
        var buttonBg = button.bg && button.bg[3] >= 0.5 ? flatten(button.bg, bg) : null;
        if (!buttonBg || contrast(buttonBg, bg) < 1.2) {
            // Bouton invisible sur le fond (thème à boutons en contour) : couleur des liens, sinon le texte
            buttonBg = link && contrast(link, bg) >= 3 ? link : text;
        }
        var buttonText = onBackground(button.color && flatten(button.color, buttonBg), buttonBg);

        set('banner-bg', css(bg));
        set('banner-text', css(text));
        set('panel-bg', css(bg));
        set('panel-text', css(text));
        set('banner-mix', contrast(bg, text) >= 9 ? '80%' : '100%');
        set('panel-mix', contrast(bg, text) >= 9 ? '80%' : '100%');

        // Tout accepter et Tout refuser au même style (même poids pour les deux choix)
        ['accept', 'deny'].forEach(function (name) {
            set(name + '-bg', css(buttonBg));
            set(name + '-text', css(buttonText));
            set(name + '-line', 'transparent');
        });
        // Personnaliser : bouton en contour, dans la couleur du texte
        set('customize-bg', 'transparent');
        set('customize-text', css(text));
        set('customize-line', 'color-mix(in srgb, ' + css(text) + ' 45%, transparent)');

        // Arrondis du thème : boutons tels quels (pilule comprise), cartes un peu plus arrondies
        var radius = Math.min(button.radius, 999);
        set('theme-btn-radius', radius + 'px');
        set('theme-radius', (radius >= 24 ? 20 : Math.min(Math.round(radius * 1.6), 20)) + 'px');

        set('theme-btn-font', button.font);
        set('theme-btn-weight', button.weight);
        set('theme-btn-transform', button.transform);
        set('theme-btn-tracking', button.tracking === 'normal' ? 'normal' : button.tracking);

        var heading = probe('<h2>x</h2>', 'h2');
        set('theme-heading-font', heading.font);
        set('theme-heading-weight', heading.weight);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apply);
    } else {
        apply();
    }
})();
