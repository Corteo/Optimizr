# Haptic Optimizr

Plugin WordPress qui regroupe, dans une seule page d'administration, les réglages dont un site vitrine a besoin au quotidien : consentement aux cookies (RGPD), balises SEO, redirections, protection de la connexion, types de contenu personnalisés et quelques outils de maintenance.

Chaque fonctionnalité est un **module** que l'on active ou désactive depuis le tableau de bord du plugin. Un module désactivé ne charge aucun code et son onglet disparaît.

Optimizr est la version gratuite, publiable sur WordPress.org. Les fonctionnalités avancées (SEO local, pages spéciales, assistant IA, sitemap, redirections illimitées, sécurité complète…) sont dans l'extension séparée **[Optimizr Pro](https://github.com/Corteo/Optimizr-pro)**, qui se branche sur Optimizr par ses points d'extension et reprend les réglages existants.

**Version actuelle :** 4.0.4 · [Historique des versions](CHANGELOG.md)

## Modules

### Cookies & RGPD
- Bandeau de consentement [Tarteaucitron.js](https://tarteaucitron.io/) : position (haut, centre, bas), avec aperçu en direct.
- Google Analytics 4 et Google Tag Manager, chargés uniquement après accord du visiteur.
- Google Consent Mode v2 transmis automatiquement.
- Toujours proposés, sans réglage : YouTube, Dailymotion, X (Twitter), Facebook, Google Maps sans clé.
- *Optimizr Pro :* Microsoft Clarity, Matomo, Hotjar, Google Ads, Pixel Meta, Google Fonts, Google Maps avec clé, reCAPTCHA, hCaptcha, couleurs du bandeau.

### SEO
- Titre et description par défaut construits à partir de variables (titre de la page, nom du site, résumé, catégorie, auteur…), avec aperçu Google et aperçu de partage social en direct.
- Balises OpenGraph (titre, description, image).
- Metabox « SEO & OpenGraph » dans l'éditeur : titre, description et image personnalisés, option « ne pas indexer ».
- *Optimizr Pro :* sitemap XML, plan du site, robots.txt, options d'indexation, codes de vérification, IndexNow, données structurées.

### Redirections
- Journal des pages introuvables (404), sans adresse IP, avec création d'une redirection en un clic.
- Jusqu'à 5 redirections 301, 302, 307 ou 410, avec compteur de visites. Au-delà, les redirections existantes continuent de fonctionner mais aucune ne peut être ajoutée.
- *Optimizr Pro :* redirections illimitées, jokers, import/export CSV, redirection automatique des contenus déplacés, liens cassés.

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
- Journal des verrouillages et alertes (30 jours).
- *Optimizr Pro :* discrétion, fonctions inutilisées désactivées, en-têtes de sécurité, bilan de l'environnement.

### Outils
- **Export / import des réglages** : un fichier JSON contient tous les réglages d'Optimizr, modules désactivés compris. On configure un site, on exporte, on importe sur le suivant. La clé API n'est exportée que sur demande, et l'adresse du site d'origine est remplacée automatiquement (robots.txt…).
- **Suppression du plugin** : à la désactivation (ou dans l'onglet Outils), on choisit ce qui sera effacé si le plugin est ensuite supprimé : réglages, redirections et SEO des contenus, journaux. Par défaut, tout est conservé ; les tâches planifiées, caches et fichiers générés sont toujours supprimés.

## Prérequis

- WordPress 6.2 ou plus récent
- PHP 7.4 ou plus récent
- ACF Pro, uniquement pour les champs du type « Évènements »

## Installation

1. Récupérez l'archive ZIP du plugin (branche `stable` du dépôt GitHub).
2. Dans l'administration WordPress, allez dans **Extensions › Ajouter une extension › Téléverser une extension** et envoyez l'archive.
3. Activez **Haptic Optimizr**.

Les mises à jour suivantes sont proposées directement dans **Tableau de bord › Mises à jour**, et dans **Extensions**, comme pour toute extension : la mise à jour se lance depuis ces pages. Les éventuelles migrations de la base s'exécutent au chargement suivant.

### Mise à jour depuis la 1.5

La version 1.5 ne détecte pas les mises à jour (adresse du dépôt mal formée). Sur ces sites, installez la 2.0 **une seule fois à la main** : **Extensions › Ajouter une extension › Téléverser une extension**, puis « Remplacer la version installée ». Les réglages sont conservés et migrés automatiquement ; les mises à jour suivantes seront proposées normalement.

Après la mise à jour, tous les modules sont actifs. Les nouveaux (SEO local, Pages spéciales, Sécurité) n'ont aucun effet tant qu'ils ne sont pas réglés ; seul le module Redirections agit d'emblée, avec le journal des pages introuvables et la redirection automatique des contenus déplacés. Désactivez depuis le tableau de bord du plugin les modules inutiles. FontAwesome n'est plus chargé par le plugin ; un thème qui en utilise les icônes doit le charger lui-même.

## Utilisation

Ouvrez le menu **Optimizr** dans la barre latérale de l'administration.

- **Assistant de configuration :** s'ouvre en plein écran à la première activation du plugin, puis à la demande (menu **Optimizr › Assistant**, bouton « Assistant de configuration » de la page d'options, lien dans la liste des extensions). En quelques étapes, il fait choisir les modules puis renseigner l'essentiel de chacun : adresse de connexion et verrouillage, nom du site et motifs SEO, outils de mesure (Optimizr Pro y ajoute ses propres étapes). Rien n'est enregistré avant la dernière étape ; les étapes des modules non choisis sont sautées.
- **Tableau de bord :** activez les modules utiles et consultez l'état du site (bandeau cookies, indexation).
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
| `haptic_module_status` | filtre | Pastille d'état d'un module sur le tableau de bord. |
| `haptic_dashboard_aside` | action | Carte du tableau de bord, quand Optimizr Pro est actif (sinon carte de présentation d'Optimizr Pro). |
| `haptic_loaded` | action | Optimizr est chargé : point d'entrée des extensions. |
| `haptic_activate` / `haptic_deactivate` | action | Activation et désactivation d'Optimizr. |
| `haptic_settings_imported` | action | Réglages importés depuis l'onglet Outils. |
| `haptic_cookie_services` | filtre | Services proposés dans l'onglet Cookies, par catégorie. |
| `haptic_tarteaucitron_service` | filtre | Variables et services Tarteaucitron d'un service ajouté. |
| `haptic_tac_color` | filtre | Couleur du bandeau (valeur par défaut d'Optimizr). |
| `haptic_cookies_consent_note` | filtre | Texte de l'encadré sur les modes de consentement (onglet Cookies). |
| `haptic_seo_post_types` | filtre | Types de contenu qui ont la metabox SEO. |
| `haptic_seo_metabox_after_description` | action | Contenu ajouté sous le champ description de la metabox SEO. |
| `haptic_redirects_limit` | filtre | Nombre maximal de redirections (5 ; 0 = illimité). |
| `haptic_redirects_match` / `haptic_redirects_validate` | filtre | Recherche d'une redirection pour une adresse ; validation d'une adresse source (jokers…). |
| `haptic_redirects_saved` / `haptic_redirects_deleted` | action | Redirection enregistrée ou supprimée. |
| `haptic_redirects_intro`, `haptic_redirects_form_help` | filtre | Textes de l'onglet Redirections. |
| `haptic_redirects_before_render`, `haptic_redirects_tab_main`, `haptic_redirects_tab_aside`, `haptic_redirects_settings_fields`, `haptic_redirects_settings_save` | action | Contenu et réglages ajoutés à l'onglet Redirections. |
| `haptic_sec_protections` / `haptic_sec_environment_checks` | filtre | Protections et vérifications du bilan de l'onglet Sécurité. |
| `haptic_onboarding_steps` | filtre | Étapes de l'assistant (une étape peut fournir sa fonction `render`). |
| `haptic_onboarding_security_toggles`, `haptic_onboarding_seo_toggles`, `haptic_onboarding_cookie_fields`, `haptic_onboarding_save` | filtre | Champs de l'assistant et enregistrement. |
| `haptic_onboarding_seo_fields`, `haptic_onboarding_step_after`, `haptic_onboarding_print_scripts`, `haptic_onboarding_illustration` | action | Contenu ajouté aux étapes de l'assistant. |
| `haptic_sec_cloudflare_ranges` | filtre | Plages d'adresses de Cloudflare reconnues par l'option « Cloudflare » des proxys de confiance. |
| `haptic_settings_internal_options` | filtre | Options propres au site, exclues de l'export et de l'import. |
| `haptic_seo_disable_theme_tags` | filtre | Renvoyer `false` pour conserver les balises SEO du thème Haptic. |
| `haptic_before_enqueue_scripts` / `haptic_after_…` | action | Charger des scripts côté site. |
| `haptic_before_enqueue_admin_scripts` / `haptic_after_…` | action | Charger des scripts côté administration. |

`haptic_is_pro()` indique si Optimizr Pro est actif. Les réglages d'Optimizr Pro sont des options `haptic_*` : Optimizr les exporte, les importe et les supprime avec les siens.

Un onglet déclare ses réglages avec la Settings API de WordPress, en utilisant `haptic_settings_group($onglet)` pour `register_setting()` et `haptic_settings_page($onglet)` pour `add_settings_section()` et `add_settings_field()`. Les sections sont affichées sous forme de cartes ; l'argument `toggle` d'une section place un interrupteur dans son en-tête.

### Organisation du code

```
optimizr.php             Fichier principal : constantes, chargement des modules
functions/admin-ui.php   Page d'options : modules, onglets, tableau de bord
functions/onboarding.php Assistant de configuration plein écran (+ css/optimizr-onboarding.css, js/optimizr-onboarding.js)
functions/tabs.php       Affichage des sections en cartes
functions/cookies.php    Module Cookies & RGPD
functions/seo/           Module SEO (réglages, metabox, balises)
functions/redirects.php  Module Redirections (+ redirects-admin.php : onglet)
functions/tools.php      Onglet Outils : export et import des réglages
functions/cpts.php       Module Types de contenu
functions/thumbnails.php Module Images à la une
functions/mails.php      Module Emails
functions/security.php   Module Sécurité, protection de la connexion (+ security-admin.php : onglet de réglages)
functions/updates.php    Mises à jour depuis GitHub (absent de la version WordPress.org)
css/, js/                Interface de la page d'options
```

### Traductions

Les textes du code sont en anglais (domaine `optimizr`) ; la traduction française est dans `languages/optimizr-fr_FR.po`. Après avoir ajouté ou modifié un texte, ajoutez sa traduction au `.po` puis lancez `python3 bin/build-translations.py`, qui produit le `.mo` (PHP), un `.json` par script JS et le modèle `optimizr.pot`. Un script JS traduit dépend de `wp-i18n` et appelle `wp_set_script_translations()`.

### Numérotation des versions

Le plugin suit le [Semantic Versioning](https://semver.org/lang/fr/) : `MAJEUR.MINEUR.CORRECTIF`.

- **MAJEUR** (4.0.0) : changement qui casse la compatibilité (structure des réglages, fonctionnalité retirée, migration nécessaire).
- **MINEUR** (2.1.0) : nouvelle fonctionnalité ou nouveau module, sans rien casser.
- **CORRECTIF** (2.0.1) : correction de bug ou de sécurité, sans nouvelle fonctionnalité.

Pour publier une version :

1. Modifiez le numéro aux trois endroits : l'en-tête `Version` et la constante `HAPTIC_OPTIMIZR_VERSION` de `optimizr.php`, et `Stable tag` dans `readme.txt` (avec une entrée dans sa section « Changelog »).
2. Déplacez les entrées de la section « Non publié » de [CHANGELOG.md](CHANGELOG.md) sous le nouveau numéro, avec la date.
3. Fusionnez dans `stable` et ajoutez le tag `vX.Y.Z` : les sites installés lisent l'en-tête de `optimizr.php` sur la branche `stable` pour proposer la mise à jour.

Depuis 4.0.4, tous les sites suivent `stable` : il n'y a plus de canal Beta ni Développement à choisir. Pour essayer une version de `main` sur un site de test, installez son zip à la main (Extensions › Ajouter une extension › Téléverser).

### Version WordPress.org

Le plugin existe en deux distributions construites à partir du même code :

- **GitHub** (ce dépôt tel quel) : mises à jour par GitHub, depuis la branche `stable`.
- **WordPress.org** : le répertoire interdit les mises à jour venant d'ailleurs. `bin/build-wporg.sh [slug]` produit `dist/<slug>-<version>.zip` sans `functions/updates.php`, `assets/plugin-update-checker/` ni les fichiers de développement (liste dans `.distignore`), et retire des fichiers PHP les lignes marquées `build:github-only`. Le script échoue s'il reste du code de mise à jour GitHub ou si `Contributors` n'est pas renseigné dans `readme.txt`. Les icônes et bannières de la fiche WordPress.org sont dans `.wordpress-org/`, à envoyer dans le dossier `assets/` du dépôt SVN (pas dans le zip).

`readme.txt` (en anglais, au format WordPress.org) déclare les services externes contactés ; à tenir à jour quand un module en ajoute un.

Une migration à exécuter une seule fois se place dans `haptic_options_maybe_upgrade()` (`functions/activation.php`).

## Auteur

Alexandre Vergnaud · [alexandrevergnaud.fr](https://alexandrevergnaud.fr/) · Site du plugin : [optimizr.hapticstudio.fr](https://optimizr.hapticstudio.fr/)

## Licence

GPL2 · [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
