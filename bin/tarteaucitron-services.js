#!/usr/bin/env node
/*
 * Services de Tarteaucitron fournis avec Optimizr : seulement ceux que le module Cookies peut activer
 * (functions/cookies.php, haptic_tarteaucitron_inline_script()), repris tels quels des fichiers d'origine.
 * Les autres services de Tarteaucitron (plus de 200, chacun chargeant le script d'un tiers) ne sont pas fournis.
 *
 * Usage, à la mise à jour de Tarteaucitron :
 *   node bin/tarteaucitron-services.js <dossier de tarteaucitron.js d'origine>
 *     → assets/tarteaucitron/tarteaucitron.services.js et tarteaucitron.services.min.js
 *   node bin/tarteaucitron-services.js <dossier d'origine> <services,séparés,par,des,virgules> <fichier de sortie, sans .js>
 *     → mêmes définitions pour une autre liste (Optimizr Pro : assets/tarteaucitron/optimizr-pro-services)
 *
 * Après une mise à jour, retirer aussi de tarteaucitron.js et tarteaucitron.min.js le cas particulier de
 * cdn.jsdelivr.net (useMinifiedJS) et l'envoi de statistiques à tarteaucitron.io (proPing) : voir CHANGELOG.md, 7.9.0.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

// Services du module Cookies : Google Analytics 4, Google Tag Manager, contenus intégrés et Google Consent Mode v2
const CORE_SERVICES = [
    'gtag', 'googletagmanager',
    'facebook', 'maps_noapi', 'twitterwidgetsapi', 'dailymotion', 'youtube', 'youtubeapi',
    'gcmadstorage', 'gcmanalyticsstorage', 'gcmfunctionality', 'gcmpersonalization', 'gcmadsuserdata', 'gcmsecurity',
];

/** Définitions « tarteaucitron.services.<clé> = {…}; » du fichier, chacune vérifiée comme instruction complète. */
function definitions(file, minified) {
    const src = fs.readFileSync(file, 'utf8');
    const re = minified ? /tarteaucitron\.services\.([A-Za-z0-9_]+)=\{/g : /^tarteaucitron\.services\.([A-Za-z0-9_]+)\s*=\s*\{/gm;
    const starts = [];
    let m;
    while ((m = re.exec(src))) {
        starts.push({ key: m[1], index: m.index });
    }

    const blocks = new Map();
    starts.forEach((start, i) => {
        let code = src.slice(start.index, i + 1 < starts.length ? starts[i + 1].index : src.length);
        if (!minified) {
            // Le commentaire « // service » qui suit annonce la définition suivante
            code = code.replace(/\n(\/\/[^\n]*\n|\s*\n)*$/, '\n');
        }
        new vm.Script(code, { filename: start.key });
        blocks.set(start.key, code.trimEnd());
    });
    return blocks;
}

/** Exécutée seule, une définition ne doit créer que tarteaucitron.services[clé]. */
function check(key, code) {
    const services = {};
    const context = { tarteaucitron: { services, user: {} } };
    vm.runInNewContext(code, context);
    if (Object.keys(services).join() !== key || Object.keys(context.tarteaucitron).join() !== 'services,user') {
        throw new Error(`Définition inattendue pour le service ${key}`);
    }
}

const [, , source, list, output] = process.argv;
if (!source) {
    console.error('Usage : node bin/tarteaucitron-services.js <dossier de tarteaucitron.js> [services] [sortie]');
    process.exit(1);
}
const keys = list ? list.split(',') : CORE_SERVICES;
const out = output || path.join(__dirname, '..', 'assets', 'tarteaucitron', 'tarteaucitron.services');
const version = JSON.parse(fs.readFileSync(path.join(source, 'package.json'), 'utf8')).version;
const banner = `/*
 * Tarteaucitron ${version} (https://github.com/AmauriC/tarteaucitron.js, licence MIT) : services ${keys.join(', ')},
 * repris tels quels de tarteaucitron.services.js par bin/tarteaucitron-services.js d'Optimizr.
 */`;

for (const minified of [false, true]) {
    const blocks = definitions(path.join(source, `tarteaucitron.services${minified ? '.min' : ''}.js`), minified);
    const picked = keys.map((key) => {
        if (!blocks.has(key)) {
            throw new Error(`Service absent de Tarteaucitron ${version} : ${key}`);
        }
        check(key, blocks.get(key));
        return blocks.get(key);
    });
    fs.writeFileSync(`${out}${minified ? '.min' : ''}.js`, banner + '\n' + picked.join(minified ? '' : '\n\n') + '\n');
}
console.log(`${keys.length} services de Tarteaucitron ${version} : ${out}.js et ${out}.min.js`);
