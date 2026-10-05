# Haptic Optimizr

Plugin WordPress qui regroupe, dans une seule page d'administration, les réglages dont un site vitrine a besoin au quotidien : consentement aux cookies (RGPD), SEO et SEO local, redirections et liens cassés, page 404 et mode maintenance, sécurité, génération de textes par IA, types de contenu personnalisés et quelques outils de maintenance.

Chaque fonctionnalité est un **module** que l'on active ou désactive depuis le tableau de bord du plugin. Un module désactivé ne charge aucun code et son onglet disparaît.

**Version actuelle :** 3.0.0 · [Historique des versions](CHANGELOG.md)

## Modules

### Cookies & RGPD
- Bandeau de consentement [Tarteaucitron.js](https://tarteaucitron.io/) : position (haut, centre, bas) et couleurs personnalisables, avec aperçu en direct et contrôle du contraste des boutons.
- Services chargés uniquement après accord du visiteur :
  - **Mesure d'audience :** Google Analytics 4, Google Tag Manager, Microsoft Clarity, Matomo Tag Manager, Hotjar
  - **Publicité :** Google Ads, Pixel Meta (Facebook)
  - **Contenus intégrés :** Google Fonts, Google Maps
  - **Anti-spam :** Google reCAPTCHA, hCaptcha
  - **Toujours proposés, sans réglage :** YouTube, Dailymotion, X (Twitter), Facebook, Google Maps sans clé
- Google Consent Mode v2 et Bing Consent Mode transmis automatiquement.

### SEO
- Titre et description par défaut construits à partir de variables (titre de la page, nom du site, résumé, catégorie, auteur…), avec aperçu Google et aperçu de partage social en direct.
- Balises OpenGraph (titre, description, image).
- Metabox « SEO & OpenGraph » dans l'éditeur : titre, description et image personnalisés, option « ne pas indexer ».
- Sitemap XML pour les types de contenu choisis, avec la page d'accueil, régénéré en arrière-plan après chaque modification et chaque jour. Les contenus « ne pas indexer », protégés par mot de passe et les pages privées de WooCommerce en sont exclus ; découpage automatique au-delà de 50 000 URL.
- Plan du site pour les visiteurs avec le shortcode `[optimizr_plan_du_site]` (options : `recent="6"`, `types="post,page"`).
- robots.txt personnalisable, avec test d'accessibilité.
- Options avancées (désactivées par défaut) : pages de recherche, archives par date et étiquettes exclues de l'indexation, pages de médias redirigées vers leur contenu, codes de vérification Google Search Console et Bing, signalement instantané des contenus publiés à Bing et Yandex (IndexNow), données structurées schema.org (site, organisation, fil d'Ariane, article, événement).

### Redirections & liens
- Redirections 301, 302, 307 et 410 (page supprimée), avec jokers (`/blog/*` → `/actualites/*`), compteur de visites, recherche et import/export CSV.
- Redirection automatique quand l'adresse d'un contenu publié change, sans chaîne ni boucle.
- Journal des pages introuvables (404), sans adresse IP, avec création d'une redirection en un clic.
- Analyse des liens et images cassés dans les contenus publiés.

### SEO local
- Fiche de l'établissement (activité, adresse, téléphone, coordonnées GPS, horaires, profils en ligne) publiée pour Google en données structurées LocalBusiness, avec aperçu et lien vers le test des résultats enrichis.
- Liens « Laisser un avis Google » et « Itinéraire » à partir de l'identifiant de lieu.
- Shortcodes `[optimizr_adresse]`, `[optimizr_telephone]`, `[optimizr_email]`, `[optimizr_horaires]`, `[optimizr_avis_google]`, `[optimizr_itineraire]`.
- Reprise des coordonnées saisies dans le thème.

### Assistant IA (OpenAI, Claude, Gemini)
- Trois fournisseurs au choix, chacun avec sa clé et son modèle : **OpenAI** (GPT-5.6 Luna/Terra/Sol, GPT-4.1, GPT-4o), **Anthropic** (Claude Haiku 4.5, Sonnet 5.5, Opus 5.5) et **Google** (Gemini 3.5 Flash-Lite, 3.1 Flash-Lite, 3.8 Flash). Un modèle retiré par son fournisseur est automatiquement remplacé par le modèle par défaut.
- Génération de la meta description d'un contenu depuis sa metabox SEO.
- Génération de la balise ALT des images, à l'import et à la demande depuis la médiathèque.
- Contexte du site (activité, ton) ajouté à chaque demande.
- **Coûts maîtrisés** : estimation avant chaque traitement, calculée sur les images et les contenus du site aux tarifs en vigueur ; comparatif de tous les modèles des trois fournisseurs ; consommation réelle mesurée et cumulée par mois.
- Génération automatique de la balise ALT à l'import des images (désactivable).
- **Traitement en masse** : génère d'un coup les balises ALT manquantes de la médiathèque et les meta descriptions manquantes des contenus, avec progression, journal et arrêt à tout moment. Les éléments déjà renseignés ne sont jamais modifiés.

### Pages spéciales
- **Page 404** : une page WordPress rédigée dans l'éditeur, affichée avec la mise en page du thème, en gardant le code 404. Suggestions de contenus proches de l'adresse demandée et champ de recherche (shortcode `[optimizr_suggestions]`, utilisable aussi dans le `404.php` du thème).
- **Mode maintenance** : page d'attente autonome (ou `maintenance.php` du thème) avec code 503 et en-tête Retry-After, réouverture automatique à une date choisie, site visible normalement pour les personnes connectées qui peuvent modifier le contenu, rappel dans la barre d'administration.
- Les pages choisies sont exclues du sitemap, non indexées et signalées dans la liste des pages.

### Types de contenu
- Type « Évènements » prêt à l'emploi : date de début, date de fin, lieu, horaires (champs [ACF Pro](https://www.advancedcustomfields.com/pro/)).
- Création de types de contenu personnalisés depuis l'administration (nom et icône Dashicons).

### Images à la une
- Choix des types de contenu qui proposent une image de couverture.

### Emails
- Désactivation des emails envoyés après les mises à jour automatiques des extensions et de WordPress.

### Sécurité
Toutes les protections sont désactivées par défaut et s'activent une à une.
- **Adresse de connexion personnalisée** : wp-login.php et /wp-admin/ affichent une page introuvable aux visiteurs ; la nouvelle adresse est envoyée par email à chaque changement. Fonctionne avec les permaliens simples (`https://site/?adresse`). En cas de blocage : `define('OPTIMIZR_DISABLE_CUSTOM_LOGIN', true);` dans wp-config.php.
- **Limitation des tentatives de connexion** par adresse IP (seuil et durée réglables, liste blanche, proxys de confiance et Cloudflare), **messages d'erreur neutres**, **alerte email** lors d'une connexion administrateur depuis une nouvelle adresse IP.
- **Discrétion** : version de WordPress masquée (balise generator, flux, `?ver=`, en-tête X-Powered-By), liste des comptes bloquée (`?author=`, API REST, oEmbed), pages auteur masquées.
- **Fonctions inutilisées désactivées** : XML-RPC, mots de passe d'application, éditeur de fichiers, exécution de PHP dans les médias (Apache).
- **En-têtes de sécurité** (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy) et HSTS en option.
- **Bilan** : HTTPS, compte « admin », affichage des erreurs PHP, version de PHP, journal des verrouillages et alertes (30 jours).

### Outils
- **Export / import des réglages** : un fichier JSON contient tous les réglages d'Optimizr, modules désactivés compris. On configure un site, on exporte, on importe sur le suivant. La clé API n'est exportée que sur demande, et l'adresse du site d'origine est remplacée automatiquement (robots.txt…).
- **Suppression du plugin** : à la désactivation (ou dans l'onglet Outils), on choisit ce qui sera effacé si le plugin est ensuite supprimé : réglages, redirections et SEO des contenus, journaux. Par défaut, tout est conservé ; les tâches planifiées, caches et fichiers générés sont toujours supprimés.

## Prérequis

- WordPress 6.2 ou plus récent
- PHP 7.4 ou plus récent
- ACF Pro, uniquement pour les champs du type « Évènements »
- Une clé API OpenAI, Anthropic ou Google, uniquement pour l'Assistant IA

## Installation

1. Récupérez l'archive ZIP du plugin (branche `stable` du dépôt GitHub).
2. Dans l'administration WordPress, allez dans **Extensions › Ajouter une extension › Téléverser une extension** et envoyez l'archive.
3. Activez **Haptic Optimizr**.

Les mises à jour suivantes sont proposées directement dans **Tableau de bord › Mises à jour**, comme pour toute extension. Le bouton « Vérifier les mises à jour » en haut de la page du plugin force une vérification immédiate ; si une version est disponible, « Mettre à jour » l'installe directement, puis la page se recharge et les éventuelles migrations de la base s'exécutent.

### Mise à jour depuis la 1.5

La version 1.5 ne détecte pas les mises à jour (adresse du dépôt mal formée). Sur ces sites, installez la 2.0 **une seule fois à la main** : **Extensions › Ajouter une extension › Téléverser une extension**, puis « Remplacer la version installée ». Les réglages sont conservés et migrés automatiquement ; les mises à jour suivantes seront proposées normalement.

Après la mise à jour, tous les modules sont actifs. Les nouveaux (SEO local, Pages spéciales, Sécurité) n'ont aucun effet tant qu'ils ne sont pas réglés ; seul le module Redirections agit d'emblée, avec le journal des pages introuvables et la redirection automatique des contenus déplacés. Désactivez depuis le tableau de bord du plugin les modules inutiles. FontAwesome n'est plus chargé par le plugin ; un thème qui en utilise les icônes doit le charger lui-même.

## Utilisation

Ouvrez le menu **Optimizr** dans la barre latérale de l'administration.

- **Assistant de configuration :** s'ouvre en plein écran à la première activation du plugin, puis à la demande (menu **Optimizr › Assistant**, bouton « Assistant de configuration » de la page d'options, lien dans la liste des extensions). En quelques étapes, il fait choisir les modules puis renseigner l'essentiel de chacun : adresse de connexion et verrouillage, nom du site et motifs SEO, codes de vérification, coordonnées de l'établissement, page 404, outils de mesure, fournisseur, clé et modèle d'IA. Rien n'est enregistré avant la dernière étape ; les étapes des modules non choisis sont sautées.
- **Tableau de bord :** activez les modules utiles et consultez l'état du site (sitemap, bandeau cookies, clé API, indexation).
- **Un onglet par module :** chaque onglet s'enregistre séparément avec le bouton « Enregistrer les modifications » en bas de page. Une alerte signale les modifications non enregistrées.
- **Outils :** export et import des réglages, choix de ce qu'efface la suppression du plugin.

## Pour les développeurs

### Configuration

| Constante (`wp-config.php`) | Rôle |
|---|---|
| `OPTIMIZR_GITHUB_TOKEN` | Token GitHub utilisé pour les mises à jour depuis un dépôt privé. Prioritaire sur le token distant. |
| `OPTIMIZR_DISABLE_CUSTOM_LOGIN` | `true` désactive l'adresse de connexion personnalisée (retour à wp-login.php), en cas de blocage. |

### Points d'extension

| Hook | Type | Rôle |
|---|---|---|
| `haptic_modules` | filtre | Ajouter ou modifier un module du tableau de bord. |
| `haptic_admin_tabs` | filtre | Ajouter ou modifier un onglet de la page d'options. |
| `haptic_admin_aside_{onglet}` | action | Afficher un panneau latéral dans un onglet. |
| `haptic_site_health_checks` | filtre | Ajouter une vérification au bloc « État du site ». |
| `haptic_sitemap_excluded_ids` | filtre | Exclure des contenus du sitemap (tableau d'ID). |
| `haptic_sitemap_max_urls` | filtre | Nombre d'URL par fichier de sitemap (50 000 par défaut). |
| `haptic_ai_providers` | filtre | Fournisseurs, modèles proposés et tarifs (dollars pour 1 million de tokens), à ajuster quand un fournisseur change ses prix ou ses modèles. |
| `haptic_ai_capability` | filtre | Droit requis pour lancer une génération IA facturée (`publish_posts` par défaut). |
| `haptic_sec_cloudflare_ranges` | filtre | Plages d'adresses de Cloudflare reconnues par l'option « Cloudflare » des proxys de confiance. |
| `haptic_settings_internal_options` | filtre | Options propres au site, exclues de l'export et de l'import. |
| `haptic_seo_disable_theme_tags` | filtre | Renvoyer `false` pour conserver les balises SEO du thème Haptic. |
| `haptic_before_enqueue_scripts` / `haptic_after_…` | action | Charger des scripts côté site. |
| `haptic_before_enqueue_admin_scripts` / `haptic_after_…` | action | Charger des scripts côté administration. |

Un onglet déclare ses réglages avec la Settings API de WordPress, en utilisant `haptic_settings_group($onglet)` pour `register_setting()` et `haptic_settings_page($onglet)` pour `add_settings_section()` et `add_settings_field()`. Les sections sont affichées sous forme de cartes ; l'argument `toggle` d'une section place un interrupteur dans son en-tête.

### Organisation du code

```
hpt_options.php          Fichier principal : constantes, chargement des modules
functions/admin-ui.php   Page d'options : modules, onglets, tableau de bord
functions/onboarding.php Assistant de configuration plein écran (+ css/optimizr-onboarding.css, js/optimizr-onboarding.js)
functions/tabs.php       Affichage des sections en cartes
functions/cookies.php    Module Cookies & RGPD
functions/seo/           Module SEO (réglages, metabox, sitemap, balises, options avancées)
functions/ai.php         Module Assistant IA : réglages et génération
functions/ai-pricing.php Fournisseurs, modèles, tarifs, estimation et consommation
functions/ai-providers.php Appels HTTP aux API OpenAI, Anthropic et Gemini
functions/ai-bulk.php    Traitement en masse (+ media.php : médiathèque)
functions/redirects.php  Module Redirections (+ redirects-admin.php : onglet, links.php : liens cassés)
functions/local-seo.php  Module SEO local
functions/pages.php      Module Pages spéciales (page 404, maintenance)
functions/tools.php      Onglet Outils : export et import des réglages
functions/cpts.php       Module Types de contenu
functions/thumbnails.php Module Images à la une
functions/mails.php      Module Emails
functions/security.php   Module Sécurité (+ security-admin.php : onglet de réglages)
functions/updates.php    Mises à jour depuis GitHub (absent de la version WordPress.org)
css/, js/                Interface de la page d'options
```

### Numérotation des versions

Le plugin suit le [Semantic Versioning](https://semver.org/lang/fr/) : `MAJEUR.MINEUR.CORRECTIF`.

- **MAJEUR** (3.0.0) : changement qui casse la compatibilité (structure des réglages, fonctionnalité retirée, migration nécessaire).
- **MINEUR** (2.1.0) : nouvelle fonctionnalité ou nouveau module, sans rien casser.
- **CORRECTIF** (2.0.1) : correction de bug ou de sécurité, sans nouvelle fonctionnalité.
- **Pré-version** (`2.1.0-beta.1`) : version de test, publiée uniquement sur la branche `beta`, jamais sur `stable`.

Pour publier une version :

1. Modifiez le numéro aux trois endroits : l'en-tête `Version` et la constante `HAPTIC_OPTIMIZR_VERSION` de `hpt_options.php`, et `Stable tag` dans `readme.txt` (avec une entrée dans sa section « Changelog »).
2. Déplacez les entrées de la section « Non publié » de [CHANGELOG.md](CHANGELOG.md) sous le nouveau numéro, avec la date.
3. Publiez sur la branche du canal : les sites installés lisent l'en-tête de `hpt_options.php` sur la branche de leur canal (choisi dans le tableau de bord, carte « Mises à jour ») pour proposer la mise à jour.
   - **Version finale** : fusionnez dans `stable` **et** dans `beta`, pour que les sites en beta la reçoivent aussi. Ajoutez le tag `vX.Y.Z`.
   - **Pré-version** : fusionnez seulement dans `beta`.

Un site de test réglé sur le canal **Développement** reçoit chaque version fusionnée dans `main`, avant toute publication. Il ne voit une nouveauté que si le numéro de version a augmenté : chaque PR incrémente donc la version.

### Version WordPress.org

Le plugin existe en deux distributions construites à partir du même code :

- **GitHub** (ce dépôt tel quel) : mises à jour par GitHub avec les canaux Stable, Beta et Développement.
- **WordPress.org** : le répertoire interdit les mises à jour venant d'ailleurs. `bin/build-wporg.sh [slug]` produit `dist/<slug>-<version>.zip` sans `functions/updates.php`, `js/optimizr-updates.js`, `assets/plugin-update-checker/` ni les fichiers de développement (liste dans `.distignore`), et retire des fichiers PHP les lignes marquées `build:github-only`. Le script échoue s'il reste du code de mise à jour GitHub ou si `Contributors` n'est pas renseigné dans `readme.txt`.

Le bouton « Vérifier les mises à jour » et la carte du canal sont ajoutés par `functions/updates.php` via les actions `haptic_header_actions` et `haptic_dashboard_updates` : sans ce fichier, la carte « Mises à jour » renvoie vers les mises à jour de WordPress. `readme.txt` (en anglais, au format WordPress.org) déclare les services externes contactés ; à tenir à jour quand un module en ajoute un.

Une migration à exécuter une seule fois se place dans `haptic_options_maybe_upgrade()` (`functions/activation.php`).

Les modèles et tarifs sont dans `haptic_ai_providers()` (`functions/ai-pricing.php`), avec leur date de relevé `HAPTIC_AI_PRICING_DATE` : à vérifier à chaque version sur les grilles officielles d'[OpenAI](https://developers.openai.com/api/docs/pricing), d'[Anthropic](https://platform.claude.com/docs/en/about-claude/pricing) et de [Google](https://ai.google.dev/gemini-api/docs/pricing).

## Auteur

Alexandre Vergnaud · [alexandrevergnaud.fr](https://alexandrevergnaud.fr/)

## Licence

GPL2 · [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
