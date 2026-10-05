# Historique des versions

Toutes les évolutions notables de Haptic Optimizr sont consignées ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et la numérotation suit le [Semantic Versioning](https://semver.org/lang/fr/).

## [Non publié]

## [3.0.0] - 2026-10-05

### Modifié (incompatible)
- Préfixe `hpt_` remplacé par `haptic_` partout (fonctions, options, métadonnées, hooks, actions AJAX, nonces, tâches planifiées, constantes `HAPTIC_*`, classes CSS `haptic-*`), comme le demande WordPress.org (préfixe d'au moins 4 caractères). Les anciennes fonctions `haptic_GPT_*` suivent la même règle. Le code personnalisé qui utilise un filtre ou une action `hpt_*` doit être renommé. Les actions `before_hpt_options_*` et `after_hpt_options_*` deviennent `haptic_before_*` et `haptic_after_*`, `add_hpt_options_tabs` devient `haptic_add_options_tabs`. Le fichier principal `hpt_options.php` devient `optimizr.php` : sur les sites mis à jour, WordPress désactive le plugin, qu'il faut réactiver (réglages conservés). Les sites en 2.x ne voient pas cette mise à jour dans leur outil de mise à jour (il lit `hpt_options.php` sur GitHub) : elle s'installe manuellement.
- Migration automatique au premier chargement après la mise à jour : réglages, SEO des contenus, adresses IP connues des comptes et tâches planifiées passent sous le nouveau nom, sans rien perdre. Un export de réglages fait en 2.x s'importe toujours. Les anciennes adresses de la page d'options (`page=hpt-options`) redirigent vers la nouvelle.
- Domaine de traduction : `optimizr`.

### Retiré
- Module « Outils développeur » : mode debug, journal de debug (`wp-content/uploads/optimizr-logs/`, supprimé à la mise à jour), alerte des erreurs PHP et fonction `vd()`.

### Sécurité
- Toutes les sorties HTML sont échappées au moment de l'affichage (icônes et interrupteurs filtrés par `wp_kses()` avec la liste `haptic_kses_ui()`, contenu de la page de maintenance par `wp_kses_post()`…), comme l'exige la vérification Plugin Check de WordPress.org.
- Requêtes SQL préparées avec `$wpdb->prepare()`, noms de table compris (`%i`), y compris le traitement en masse de l'IA et la liste paginée des redirections.
- Données reçues (formulaires, adresse demandée, adresses IP des en-têtes) nettoyées et débarrassées des barres obliques ajoutées par WordPress avant usage. Les actions de l'onglet Redirections vérifient leur nonce directement.

### Modifié
- Bandeau cookies : la configuration de Tarteaucitron et les services sont ajoutés par `wp_add_inline_script()` juste après la librairie, et les couleurs par `wp_add_inline_style()`, au lieu de balises imprimées à la main dans le footer. Le comportement du bandeau ne change pas.
- Autres scripts en ligne (metabox SEO, types de contenu, assistant) imprimés avec `wp_print_inline_script_tag()` ; style du rappel de maintenance de la barre d'administration ajouté par `wp_add_inline_style()`.
- Fichiers (sitemap, robots.txt, clé IndexNow) supprimés et déplacés par l'API Filesystem de WordPress (`wp_delete_file()`, `haptic_filesystem()`) ; export et import CSV des redirections sans accès fichier direct.
- WordPress 6.2 minimum (marqueur `%i` de `$wpdb->prepare()`).

## [2.5.0] - 2026-10-05

### Ajouté
- Préparation de la publication sur le répertoire WordPress.org : `readme.txt` au format WordPress.org (en anglais, services externes déclarés : OpenAI, Anthropic, Google Gemini, IndexNow, services du bandeau cookies, vérification des liens) et script `bin/build-wporg.sh` qui construit une version sans le système de mise à jour GitHub, interdit sur le répertoire.

### Modifié
- En-tête du plugin : auteur « Haptic », licence « GPLv2 or later », versions minimales de WordPress (6.1) et de PHP (7.4) déclarées, Plugin URI retirée (elle doublait l'Author URI).
- Le bouton « Vérifier les mises à jour » et le choix du canal sont fournis par `functions/updates.php` (script dans `js/optimizr-updates.js`). Rien ne change sur les sites installés depuis GitHub ; dans la version WordPress.org, la carte « Mises à jour » renvoie vers les mises à jour de WordPress.
- Mode développeur : les fenêtres de `vd()` n'utilisent plus Bootstrap, qui était chargé depuis un CDN externe.

## [2.4.1] - 2026-10-04

### Sécurité
- Clés API de l'assistant IA : elles ne figurent plus dans le code HTML de l'onglet IA. Le champ reste vide (seuls les 4 derniers caractères sont rappelés) : le laisser vide conserve la clé, une case permet de la supprimer.
- Assistant IA : les générations facturées (description SEO, ALT, ALT automatique à l'import) sont réservées aux rôles qui peuvent publier (auteurs, éditeurs, administrateurs), modifiable par le filtre `hpt_ai_capability`. Les contributeurs ne voient plus les boutons.
- Liens cassés : les adresses externes sont vérifiées avec les requêtes « sûres » de WordPress ; les liens vers le réseau local ou un port inhabituel sont signalés sans être appelés.
- Journal de debug : déplacé du dossier du plugin, lisible publiquement, vers `wp-content/uploads/optimizr-logs/` (accès refusé par .htaccess, nom de fichier non devinable). L'ancien fichier est supprimé à la mise à jour.
- Journal des 404 : l'adresse est enregistrée sans sa requête (« ?… »), qui pouvait contenir des données personnelles ; les entrées existantes avec requête sont supprimées à la mise à jour.
- Export CSV des redirections : les cellules qui commencent par = + - @ sont neutralisées pour ne pas devenir des formules dans un tableur (l'import retire ce préfixe).
- Mode maintenance : l'API REST est fermée aux visiteurs, comme le site.
- Mode debug et mode développeur ne sont plus exportés ni importés : un site client n'hérite plus du mode debug d'un site de développement.

### Ajouté
- Sécurité : réglage « Proxys de confiance » (et option Cloudflare) pour les sites derrière un CDN ou un répartiteur de charge. La limitation des connexions lit alors l'adresse réelle du visiteur au lieu de verrouiller tout le monde ensemble. Sans réglage, rien ne change.
- SEO : bouton « Supprimer le fichier » quand un robots.txt physique masque le réglage.

### Modifié
- ALT automatique à l'import : la génération se fait en arrière-plan quelques secondes après l'import, au lieu de bloquer le téléversement. Elle est ignorée pendant les imports WordPress et sans clé API.
- Types de contenu personnalisés : l'identifiant d'un type est fixé à sa création. Le renommer ne change plus son adresse et ne masque plus ses contenus. Un identifiant trop long (plus de 20 caractères) ou réservé par WordPress est refusé avec un message.
- Redirections : un changement d'adresse d'une page parente crée aussi les redirections de ses pages enfants.
- Le robots.txt écrit par le bouton de l'onglet SEO est supprimé à la désactivation et à la suppression du plugin, s'il n'a pas été modifié depuis.
- Multisite : chaque site sert son propre sitemap (auparavant un fichier unique à la racine, commun à tout le réseau).
- Cookies : le service Google Maps ne demande plus que la clé API ; le lien vers la politique de confidentialité reprend la page définie dans WordPress.

### Performances
- Redirections : la liste des jokers est gardée en cache, au lieu d'une recherche dans toute la table à chaque page vue.
- SEO : les variables des motifs (titre, description, résumé…) sont calculées une fois par contenu et seulement si le motif les utilise.
- Onglet SEO : les tests d'accès au robots.txt et au sitemap sont gardés 5 minutes au lieu d'appeler le site à chaque affichage.
- Onglet IA : les estimations de coût sont gardées en cache.
- Liens cassés : chaque requête de l'analyse reste sous la durée maximale d'exécution de PHP.

### Corrigé
- Sitemap : sans fichier sitemap.xml (racine non accessible en écriture), chaque visite de /sitemap.xml relançait une génération complète et ajoutait une ligne à l'historique. Il est désormais servi depuis un cache. /sitemap.xml répond directement au lieu d'être redirigé vers /sitemap.xml/.
- Robots.txt : l'échec d'écriture du fichier est signalé au lieu d'afficher un succès.
- Traitement IA en masse : au-delà de 1 000 éléments en échec, les mêmes éléments étaient retraités (et facturés) en boucle.
- Journal de sécurité : les entrées de plus de 30 jours sont supprimées même sans nouvel évènement.
- Pages de médias redirigées : la redirection fonctionne aussi quand les fichiers sont sur un CDN.
- Données structurées Article : plus de lien vers la page auteur quand le module Sécurité la masque.
- Metabox SEO : le bouton IA se réactive après une erreur réseau, et l'aperçu n'affiche plus d'image cassée quand le champ image est vidé.
- Éditeur de blocs : le script de la metabox SEO n'est plus chargé deux fois.
- Le journal de debug est aussi écrit pendant les tâches planifiées et WP-CLI ; le gestionnaire d'erreurs du mode debug n'écrase plus celui des autres extensions.
- Import des réglages : les valeurs des modules désactivés sont nettoyées à l'activation du module.
- La notice « activé avec succès », jamais affichée, est retirée.
- La suppression du plugin depuis la liste des extensions provoquait une erreur fatale (depuis la 2.4.0) : les fichiers restaient en place et les choix de nettoyage n'étaient pas appliqués.
- Données structurées (JSON-LD) : un titre ou un résumé contenant « </script> » sous forme d'entités HTML pouvait injecter du code dans les pages publiques. Les chevrons sont désormais échappés.
- Redirections : un joker dont la destination fait partie des adresses redirigées (« /blog/* » vers « /blog/nouveau/* », ou « /* ») est refusé au lieu de créer une boucle infinie.
- Types de contenu personnalisés : les adresses d'un type ajouté ou renommé fonctionnent tout de suite, sans réenregistrer les permaliens.
- Premier enregistrement du tableau de bord ou de l'assistant : désactiver le module SEO supprime bien le sitemap généré, et activer Redirections crée bien ses tables.
- IndexNow : le fichier de clé est republié à la réactivation du plugin (la désactivation le supprime).
- SEO local : les barres obliques inverses saisies dans les champs ne disparaissent plus à l'enregistrement.
- Bandeau cookies : le numéro de version de Tarteaucitron transmis au navigateur correspond à la librairie embarquée (1.21.0), pour que le cache soit renouvelé.

## [2.4.0] - 2026-10-03

Version finale regroupant les versions 2.4.0-beta.1 à 2.4.0-beta.3.

### Ajouté
- Assistant de configuration en plein écran, en plusieurs étapes, avec illustrations animées sur la sécurité, la performance et l'optimisation. Il fait renseigner les vrais réglages : adresse de connexion, verrouillage et liste blanche d'IP, nom du site, motifs SEO et codes de vérification, établissement (SEO local), page 404, identifiants Analytics / Tag Manager / Clarity / Pixel Meta, fournisseur, modèle et contexte de l'assistant IA. Il s'ouvre à la première activation, puis depuis le menu Optimizr › Assistant ; le tableau de bord propose de le lancer tant qu'il n'a été ni terminé ni écarté.

### Corrigé
- Les actions d'activation et de désactivation du plugin ne s'exécutaient jamais. La désactivation supprime de nouveau le sitemap généré, le fichier de clé IndexNow et la règle .htaccess du module Sécurité.
- Sur mobile, les boutons de l'en-tête de la page d'options restent dans l'écran au lieu de déborder.

## [2.4.0-beta.3] - 2026-10-03

### Corrigé
- Sur mobile, le bandeau « Configurez l'essentiel » du tableau de bord n'est plus à l'étroit : le texte occupe toute la largeur et le bouton « Lancer l'assistant » passe en dessous.
- Sur mobile, les boutons de l'en-tête (« Assistant de configuration », « Vérifier les mises à jour ») restent dans l'écran au lieu de déborder.

## [2.4.0-beta.2] - 2026-10-03

### Modifié
- L'assistant de configuration fait renseigner les réglages au lieu de seulement activer des fonctions :
  - **Sécurité :** adresse de connexion personnalisée (avec proposition automatique, contrôle et rappel de la nouvelle adresse à la fin), nombre d'échecs et durée du verrouillage, adresses IP jamais verrouillées (bouton « Ajouter mon adresse »).
  - **Référencement :** nom et slogan du site, motifs du titre et de la description avec insertion des variables, codes de vérification Google Search Console et Bing.
  - **Établissement** (nouvelle étape, module SEO local) : activité, nom, adresse, téléphone et email publiés pour Google.
  - **Page 404** (nouvelle étape, module Pages spéciales) : page affichée et suggestions de contenus.
  - **Cookies & RGPD :** identifiants Microsoft Clarity et Pixel Meta en plus de Google Analytics et Tag Manager.
  - **Assistant IA :** choix du modèle et description de l'activité.
- Une étape ne se valide que si ses champs sont corrects (adresse de connexion, email, code pays).
- Orthographe « Beta » au lieu de « Bêta » (canal de mise à jour, carte « Mises à jour »).

## [2.4.0-beta.1] - 2026-10-03

### Ajouté
- Assistant de configuration en plein écran, en plusieurs étapes : choix des modules, protections de sécurité recommandées, référencement (balises, données structurées, contenus du sitemap), bandeau de cookies et identifiants Google Analytics / Tag Manager, fournisseur et clé de l'assistant IA, puis récapitulatif avant application. Illustrations animées sur la sécurité, la performance et l'optimisation (animations coupées si le système demande moins de mouvement). Il s'ouvre à la première activation du plugin, puis à la demande depuis le menu Optimizr › Assistant, le bouton « Assistant de configuration » de la page d'options ou la liste des extensions. Tant qu'il n'a été ni terminé ni écarté, le tableau de bord propose de le lancer.

### Corrigé
- Les actions d'activation et de désactivation du plugin ne s'exécutaient jamais (chemin du fichier principal mal résolu). La désactivation supprime de nouveau le sitemap généré, le fichier de clé IndexNow et la règle .htaccess du module Sécurité.

## [2.3.2] - 2026-10-03

### Modifié
- Orthographe « Beta » au lieu de « Bêta » (canal de mise à jour, carte « Mises à jour »).

## [2.3.1] - 2026-10-03

### Modifié
- Version de test du canal Développement : aucune modification fonctionnelle, sert à vérifier l'installation directe par le lien « Mettre à jour » ajouté en 2.3.0.

## [2.3.0] - 2026-10-03

### Ajouté
- Le lien « Mettre à jour » qui suit « Vérifier les mises à jour » installe directement la nouvelle version, sans passer par la page des mises à jour de WordPress. La page se recharge ensuite, ce qui exécute les migrations de la base prévues par la nouvelle version. Si WordPress n'a pas accès aux fichiers du site (accès FTP requis), le message l'indique.

## [2.2.1] - 2026-10-03

### Modifié
- Version de test du canal Développement : aucune modification fonctionnelle, sert à vérifier qu'un site réglé sur ce canal reçoit bien la mise à jour depuis `main`.

## [2.2.0] - 2026-10-03

### Ajouté
- Choix du canal de mise à jour dans le tableau de bord (carte « Mises à jour ») : **Stable** (par défaut), **Beta**, qui propose aussi les versions de test, ou **Développement**, qui suit la branche `main` pour tester une version avant de la publier. Réservé à qui peut installer les extensions ; sur un multisite, le canal du site principal vaut pour tout le réseau. Le canal n'est pas exporté avec les réglages.

## [2.1.1] - 2026-10-03

### Corrigé
- Bouton « Vérifier les mises à jour » : il annonçait toujours « Optimizr est à jour », même quand une nouvelle version était disponible. Il affiche maintenant la version proposée avec un lien vers la page des mises à jour, et l'erreur réelle si GitHub ne répond pas ou refuse l'accès.

## [2.1.0] - 2026-10-03

### Ajouté
- Suppression du plugin : un fichier `uninstall.php` efface les tâches planifiées, les caches (dont celui de l'outil de mise à jour) et les fichiers générés (sitemap, clé IndexNow, règle `.htaccess`). Réglages, redirections et SEO des contenus, et journaux ne sont effacés que si on l'a demandé ; par défaut, tout est conservé. Multisite pris en charge.
- La question est posée au clic sur « Désactiver » dans la liste des extensions (un plugin désactivé n'exécute plus de code), et les choix restent modifiables dans l'onglet **Outils**.

### Corrigé
- Onglet Outils : les cases à cocher s'affichaient au-dessus de leur texte.

## [2.0.0] - 2026-10-02

Première version finale de la 2.0 : nouvelle interface, cinq nouveaux modules et de nombreuses corrections. Une migration s'exécute automatiquement à la mise à jour ; aucun réglage existant n'est perdu. Le détail se trouve dans les versions de test ci-dessous (2.0.0-beta.1 à 2.0.0-beta.13).

**Mise à jour depuis la 1.5 :** la 1.5 ne détecte pas les mises à jour (adresse du dépôt mal formée). Installez la 2.0 une fois à la main (Extensions › Ajouter › Téléverser, « Remplacer la version installée ») ; les mises à jour suivantes seront automatiques.

### Ajouté
- Nouvelle interface d'administration : menu **Optimizr**, tableau de bord avec activation des modules et état du site, un onglet par module, barre d'enregistrement, onglets défilants.
- Module **Redirections & liens** : redirections 301/302/307/410 avec jokers et import/export CSV, redirection automatique des contenus déplacés, journal des pages introuvables, analyse des liens cassés.
- Module **SEO local** : fiche de l'établissement en données structurées LocalBusiness, horaires, liens d'avis Google et d'itinéraire, shortcodes de coordonnées.
- Module **Pages spéciales** : page 404 rédigée dans l'éditeur avec suggestions de contenus, mode maintenance (503) avec réouverture automatique.
- Module **Sécurité** : adresse de connexion personnalisée, limitation des tentatives de connexion, masquage de la version de WordPress et des comptes, désactivation de XML-RPC et de l'éditeur de fichiers, en-têtes de sécurité, bilan et journal.
- Onglet **Outils** : export et import des réglages entre sites.
- SEO : aperçus Google et réseaux sociaux en direct, éditeur de motifs au clavier et par glisser-déposer, balises OpenGraph complètes, sitemap régénéré en arrière-plan et découpé au-delà de 50 000 URL, shortcode `[optimizr_plan_du_site]`, options d'indexation, vérification Google/Bing, IndexNow, données structurées schema.org.
- Assistant IA : trois fournisseurs (OpenAI, Claude, Gemini), traitement en masse des balises ALT et des meta descriptions, estimation des coûts et consommation réelle.

### Modifié
- Chaque onglet enregistre ses propres réglages ; les réglages « Balises meta » et « OpenGraph » sont désormais respectés.
- Le motif « Titre » s'applique à la balise `<title>` ; les balises SEO du socle de thème Haptic sont remplacées, sans doublon.
- Un seul sitemap publié (celui de WordPress est désactivé quand le module SEO est actif) ; pages WooCommerce privées, contenus « ne pas indexer » et protégés exclus.

### Supprimé
- FontAwesome et Bootstrap ne sont plus inclus ni chargés par le plugin.
- Fichier statique `sitemap.html` (remplacé par le shortcode).
- Anciens modèles d'IA (GPT-4, GPT-3.5, GPT-4 Turbo…).

### Corrigé
- Les mises à jour du plugin n'étaient jamais détectées.
- Les motifs SEO n'étaient jamais enregistrés ; balises meta et OpenGraph en double sur les thèmes Haptic.
- Erreur JavaScript sur tout le site quand le bandeau cookies était désactivé ; Google reCAPTCHA ne se chargeait jamais.
- Contenu du robots.txt écrasé à chaque affichage.

### Sécurité
- Nonces et contrôle des droits sur toutes les actions d'administration et AJAX.
- Clés API jamais exportées par défaut ; journal du sitemap retiré de l'espace public.

## [2.0.0-beta.13] - 2026-10-02

### Modifié
- Barre d'onglets : quand la largeur manque, des flèches et un fondu signalent les onglets masqués ; défilement à la molette, onglet courant ramené à l'écran au chargement.

## [2.0.0-beta.12] - 2026-10-02

### Ajouté
- Module **Pages spéciales** :
  - page 404 rédigée dans l'éditeur, affichée dans le gabarit du thème avec le code 404, suggestions de contenus proches de l'adresse demandée et champ de recherche (shortcode `[optimizr_suggestions]`) ;
  - mode maintenance : code 503 et Retry-After, page autonome ou `maintenance.php` du thème, réouverture automatique à une date, accès normal pour les personnes connectées qui peuvent modifier le contenu, rappel dans la barre d'administration ;
  - pages choisies exclues du sitemap, non indexées et signalées dans la liste des pages.
- Toutes les pages 404 sont marquées noindex.
- Les réglages de ce module ne font pas partie de l'export : un import ne peut pas fermer un site.

## [2.0.0-beta.11] - 2026-10-02

### Ajouté
- Module **Redirections & liens** :
  - redirections 301, 302, 307 et 410 avec jokers, compteur de visites, recherche, import/export CSV ;
  - redirection automatique des contenus publiés dont l'adresse change (chaînes raccourcies, boucles supprimées) ;
  - journal des pages introuvables (sans adresse IP, 2 000 entrées au plus) avec création de redirection en un clic ;
  - analyse des liens et images cassés dans les contenus publiés.
- Module **SEO local** : fiche de l'établissement en données structurées LocalBusiness (horaires, coordonnées GPS, profils), liens d'avis Google et d'itinéraire, shortcodes de coordonnées et d'horaires, reprise des coordonnées du thème.
- SEO, options avancées (désactivées par défaut) : noindex des recherches, archives par date et étiquettes ; redirection des pages de médias ; codes de vérification Google et Bing ; IndexNow ; données structurées schema.org (WebSite, Organization, BreadcrumbList, Article, Event).

### Modifié
- L'option « ne pas indexer » d'un contenu passe par le filtre `wp_robots` : une seule balise meta robots par page.

## [2.0.0-beta.10] - 2026-10-01

### Ajouté
- Module **Sécurité** (onglet dédié, toutes les protections désactivées par défaut) :
  - adresse de connexion personnalisée, avec email de rappel à chaque changement, prise en charge des permaliens simples, blocage des raccourcis /login et /admin qui la révéleraient, et constante de secours `OPTIMIZR_DISABLE_CUSTOM_LOGIN` ;
  - limitation des tentatives de connexion par adresse IP (seuil, durée, liste blanche), messages d'erreur neutres, alerte email lors d'une connexion administrateur depuis une nouvelle adresse IP ;
  - version de WordPress masquée, blocage de l'énumération des comptes (`?author=`, API REST, oEmbed, sitemap natif) et des pages auteur ;
  - désactivation de XML-RPC, des mots de passe d'application et de l'éditeur de fichiers ; blocage de l'exécution de PHP dans les médias (Apache) ;
  - en-têtes HTTP de sécurité et HSTS en option ;
  - bilan de sécurité (HTTPS, compte « admin », affichage des erreurs, version de PHP) et journal des verrouillages et alertes conservé 30 jours.
- L'adresse de connexion et le journal de sécurité ne sont jamais inclus dans l'export des réglages.

## [2.0.0-beta.9] - 2026-10-01

### Ajouté
- OpenAI : GPT-5.6 Luna (0,20 $ / 1,20 $), Terra et Sol, utilisés sans réflexion (`reasoning_effort: none`).
- Gemini : Gemini 3.8 Flash (0,75 $ / 3,75 $, puis 1,50 $ / 7,50 $ au 1er janvier 2027).
- Changements de prix déjà annoncés pris en compte automatiquement à leur date dans les estimations et la consommation.

### Modifié
- Gemini : modèle par défaut Gemini 3.5 Flash-Lite, recommandé par Google pour les nouveaux projets. Gemini 3.1 Flash-Lite indique sa date d'arrêt (7 mai 2027).
- Un modèle enregistré qui n'est plus proposé est remplacé par le modèle par défaut du fournisseur au lieu d'être envoyé tel quel ; la liste des modèles l'indique.

### Supprimé
- Gemini 2.5 Flash-Lite, désormais réservé par Google aux comptes qui l'utilisaient déjà (erreur « no longer available to new users »).
- GPT-5 Nano et GPT-5 Mini, arrêtés par OpenAI le 11 décembre 2026 (remplacés par GPT-5.6 Luna et Terra).

## [2.0.0-beta.8] - 2026-09-30

### Ajouté
- Assistant IA **multi-fournisseur** : OpenAI, Anthropic (Claude) et Google (Gemini), chacun avec sa propre clé et son propre modèle. Le changement de fournisseur conserve les réglages des autres.
- Nouveaux modèles, avec leurs tarifs relevés le 30 septembre 2026 :
  - OpenAI : GPT-5 Nano (0,05 $ / 0,40 $) et GPT-5 Mini (0,25 $ / 2,00 $) ;
  - Anthropic : Claude Haiku 4.5 (par défaut), Claude Sonnet 5.5 et Claude Opus 5.5 ;
  - Google : Gemini 2.5 Flash-Lite (par défaut, 0,10 $ / 0,40 $), Gemini 3.1 Flash-Lite et Gemini 3.5 Flash-Lite.
- Comparatif des coûts étendu aux trois fournisseurs, avec le décompte des images propre à chacun (vérifié sur les exemples chiffrés des documentations officielles) et une estimation de la réflexion des modèles qui en font.
- Claude Sonnet 5.5 et Opus 5.5 : repli automatique côté serveur si le modèle refuse une demande.

### Modifié
- Appels aux API regroupés dans `functions/ai-providers.php`, en requêtes HTTP directes, sans dépendance. Les refus des filtres de sécurité sont signalés comme éléments ignorés ; une clé invalide, un quota atteint ou un service surchargé arrêtent le traitement en masse.
- L'export des réglages exclut par défaut toutes les clés API, quel que soit le fournisseur.
- Filtre `hpt_ai_pricing` remplacé par `hpt_ai_providers`.

## [2.0.0-beta.7] - 2026-09-30

### Modifié
- Assistant IA : informations de coût plus discrètes. Seule la ligne « Coût estimé » reste visible dans le traitement en masse ; un bouton (?) ouvre le détail (tarifs, comparatif des modèles, consommation réelle). La carte « Coûts » et les prix dans la liste des modèles sont retirés.

## [2.0.0-beta.6] - 2026-09-30

### Ajouté
- Assistant IA : **estimation du coût** avant chaque traitement en masse, calculée sur les images (dimensions réelles, méthode de décompte propre à chaque modèle) et les contenus du site, aux tarifs OpenAI relevés le 30 septembre 2026.
- **Comparatif des modèles** : coût d'une balise ALT, d'une description et de ce qui reste à traiter, pour chaque modèle proposé.
- **Consommation réelle** mesurée à partir des réponses d'OpenAI, affichée pendant les traitements et cumulée par mois (12 derniers mois).
- Réglage « ALT automatique à l'import » pour désactiver la génération facturée à chaque image importée.
- Filtre `hpt_ai_pricing` pour mettre à jour les tarifs.

### Modifié
- Liste des modèles : GPT-4.1 Mini (recommandé, nouveau modèle par défaut), GPT-4.1 Nano, GPT-4.1, GPT-4o et GPT-4o Mini, avec leurs tarifs. Les anciens modèles (GPT-4, GPT-3.5, GPT-4 Turbo) ne sont plus proposés ; un site qui en utilise un le conserve jusqu'à ce qu'un autre soit choisi.
- Pour les balises ALT, un ancien modèle qui ne lit pas les images est remplacé par GPT-4.1 Mini au lieu de GPT-4o, cinq fois plus cher.
- L'ALT automatique à l'import est générée une fois les tailles d'image créées : une version allégée est envoyée plutôt que l'original.

## [2.0.0-beta.5] - 2026-09-30

### Ajouté
- Onglet **Outils** : export des réglages dans un fichier JSON et import sur un autre site. La clé API n'est incluse que sur demande ; l'adresse du site d'origine est remplacée par celle du site courant ; seules les options d'Optimizr sont acceptées, et les options internes (version, historique du sitemap) ne sont jamais transférées.
- Assistant IA : **traitement en masse** des balises ALT manquantes et des meta descriptions manquantes, élément par élément, avec barre de progression, journal et arrêt à tout moment. Une clé refusée, un quota atteint ou une connexion impossible arrête le traitement ; un élément en erreur est ignoré sans bloquer les suivants.
- Filtre `hpt_settings_internal_options`.

### Modifié
- Les images sont envoyées à OpenAI en pièce jointe plutôt que par leur adresse : la génération des balises ALT fonctionne aussi en local et sur les sites protégés.
- Les erreurs d'OpenAI (clé invalide, quota, format d'image…) sont affichées au lieu d'un résultat vide.
- Les meta descriptions générées sont limitées à 160 caractères, coupées sur un mot.

## [2.0.0-beta.4] - 2026-09-29

Une migration s'exécute automatiquement : elle régénère le sitemap avec les nouvelles règles, supprime l'ancien fichier `sitemap.html` et l'ancien journal public.

### Ajouté
- Shortcode `[optimizr_plan_du_site]` : plan du site pour les visiteurs, affiché avec le style du thème (remplace le fichier `sitemap.html`).
- La page d'accueil figure en tête du sitemap.
- Découpage automatique en plusieurs fichiers réunis par un index au-delà de 50 000 URL.
- Historique des 20 dernières générations dans l'onglet SEO, avec le nombre d'URL.
- Alerte dans l'onglet SEO et sur le tableau de bord si le sitemap ne peut pas être écrit.
- Filtres `hpt_sitemap_excluded_ids` et `hpt_sitemap_max_urls`.

### Modifié
- Régénération en arrière-plan une minute après une modification de contenu : l'enregistrement n'est plus ralenti et plusieurs modifications rapprochées ne donnent qu'une régénération.
- Contenus chargés par lots de 500 : consommation mémoire maîtrisée sur les gros sites.
- Le sitemap natif de WordPress (`wp-sitemap.xml`) est désactivé quand le module SEO est actif : un seul sitemap publié.
- Écriture atomique des fichiers : un fichier incomplet n'est jamais servi.

### Corrigé
- Les pages privées de WooCommerce (panier, commande, compte), les contenus « ne pas indexer » et les contenus protégés par mot de passe figuraient dans le sitemap.
- Un sitemap périmé restait en ligne après la désactivation du plugin ou du module SEO.

### Supprimé
- Fichier statique `sitemap.html`, qui chargeait Bootstrap depuis un CDN externe sans consentement des visiteurs.

### Sécurité
- Le journal des générations n'est plus un fichier lisible publiquement.

## [2.0.0-beta.3] - 2026-09-29

Une migration s'exécute automatiquement : elle supprime les titres, descriptions et images que l'ancienne metabox avait enregistrés sans qu'ils soient choisis. Les personnalisations réelles sont conservées.

### Ajouté
- Balises `og:locale`, `og:type`, `og:url`, `og:site_name` et `twitter:card`.
- Image de partage par défaut : image à la une, sinon celle de la page d'accueil, sinon l'icône du site.
- Filtre `hpt_seo_disable_theme_tags` pour conserver les balises du thème si besoin.

### Modifié
- Le motif « Titre » s'applique désormais à la balise `<title>` (celle que Google affiche), et plus seulement au titre de partage. Fonctionne avec les thèmes modernes (`title-tag`) comme avec ceux qui écrivent `<title>` eux-mêmes.
- Metabox « SEO & partage » : les champs sont vides par défaut et affichent en gris le résultat du motif. Seule une valeur saisie volontairement est enregistrée ; un champ vidé revient au motif.
- Quand le module SEO est actif, les balises produites par le socle de thème Haptic (`wdm_add_social_tags`) sont désactivées : une seule source, plus de balises en double.
- Les séparateurs orphelins sont retirés quand une variable est vide (« {resume} - {nom_site} » sans résumé donne « cobalt » et non « - cobalt »).

### Corrigé
- Le titre provisoire « Brouillon auto » apparaissait comme titre de partage sur des pages publiées.
- Les motifs ne s'appliquaient plus aux contenus déjà enregistrés une fois, la metabox ayant figé leurs valeurs.
- `og:title` et la meta description apparaissaient en double, avec des valeurs différentes, sur les thèmes Haptic.

## [2.0.0-beta.2] - 2026-09-29

### Ajouté
- Motifs SEO : glisser-déposer des variables depuis la liste, déplacement des variables déjà placées (y compris d'un champ à l'autre) avec un repère à l'endroit du dépôt.
- Motifs SEO : « { » ouvre la liste des variables, filtrable et utilisable au clavier (flèches, Entrée, Échap).
- Motifs SEO : le collage se fait en texte brut et les variables collées sont reconnues.

### Corrigé
- Impossible de taper du texte au clavier dans les champs Titre et Description des balises meta.
- Une variable n'était reconnue que si elle était entourée d'espaces.
- L'aperçu Google ne se mettait à jour qu'en quittant le champ ; il suit maintenant chaque frappe.

## [2.0.0-beta.1] - 2026-09-29

Version majeure : nouvelle interface et nouvelle organisation des réglages. Une migration s'exécute automatiquement à la mise à jour ; aucun réglage existant n'est perdu.

### Ajouté
- Nouvelle interface d'administration : menu **Optimizr** avec tableau de bord, un onglet par thème, réglages présentés en cartes, interrupteurs et barre d'enregistrement qui signale les modifications en attente.
- Tableau de bord avec activation des modules (Cookies & RGPD, SEO, Assistant IA, Types de contenu, Images à la une, Emails, Outils développeur) et bloc « État du site ».
- SEO : aperçu Google et aperçu de partage social mis à jour en direct, avec compteur de caractères ; insertion des variables d'un clic.
- Cookies : aperçu du bandeau en direct, contrôle du contraste des couleurs des boutons, recherche dans la liste des services.
- Constante `OPTIMIZR_GITHUB_TOKEN` pour fournir le token des mises à jour depuis `wp-config.php`.
- Points d'extension `hpt_modules`, `hpt_admin_tabs`, `hpt_admin_aside_{onglet}` et `hpt_site_health_checks`.

### Modifié
- Chaque onglet enregistre ses propres réglages : enregistrer un onglet ne modifie plus les options des autres.
- Les réglages « Balises meta » et « Balises OpenGraph » sont désormais pris en compte. Ils sont activés automatiquement lors de la migration pour conserver le comportement précédent.
- Couleurs par défaut des boutons du bandeau cookies plus contrastées (Accepter `#1e7f4f`, Refuser `#b3261e`, Personnaliser `#9a4a00`). Les couleurs déjà enregistrées sont conservées.
- Le texte du bandeau cookies prend automatiquement une couleur lisible selon la couleur de fond.
- Le sitemap n'est plus régénéré pour les révisions, les brouillons automatiques et les types de contenu exclus du sitemap.
- Le bouton ALT par IA est disponible partout où la médiathèque s'ouvre.
- Les outils de debug ne chargent leurs styles que pour les administrateurs en mode développeur.
- Décocher tous les types de contenu dans « Images à la une » retire désormais l'image à la une partout.
- Le bouton flottant qui rouvre les préférences cookies est un vrai bouton, utilisable au clavier et lu par les lecteurs d'écran.

### Supprimé
- FontAwesome n'est plus chargé sur le site ni inclus dans le plugin. Un thème qui utilise ses icônes doit désormais le charger lui-même.
- Bootstrap n'est plus inclus dans le plugin.
- Code inutilisé : générateur de script Tarteaucitron, aide des boutons Facebook, anciens styles et scripts d'administration.

### Corrigé
- Les motifs de titre et de description SEO n'étaient jamais enregistrés.
- Erreur JavaScript sur tout le site quand le bandeau Tarteaucitron était désactivé.
- Le contenu personnalisé du robots.txt était écrasé à chaque affichage de la page ; la ligne `Sitemap:` apparaissait en double.
- Google reCAPTCHA ne se chargeait jamais.
- Les mises à jour du plugin n'étaient jamais détectées : l'adresse du dépôt, mal formée (suffixe `.git`), faisait échouer chaque requête à GitHub, et la vérification n'était lancée qu'à la main. Les sites en 1.5 doivent donc être mis à jour manuellement une fois vers la 2.0.
- L'éditeur de motifs SEO, le journal de debug et les redirections après régénération du sitemap ne fonctionnaient plus depuis le passage de la page d'options dans son propre menu.
- La fonction de journalisation console produisait un message erroné.
- La tâche planifiée du sitemap restait active après la désactivation du plugin.

### Sécurité
- Nonce et contrôle des droits sur les actions AJAX (génération IA, vérification des mises à jour).
- Nonce sur la régénération du sitemap, le vidage de son journal et l'écriture du robots.txt.
- Identifiants des services cookies échappés avant leur insertion dans le JavaScript du site.
- Le gestionnaire d'erreurs du mode debug n'intercepte plus les erreurs PHP de tout le site : elles sont de nouveau écrites dans `debug.log`.

## [1.5]

### Ajouté
- Services connectés à ChatGPT (OpenAI).
- Génération des meta descriptions SEO et des balises ALT des images.
- Champ « Contexte du site » pour améliorer les demandes à l'IA.

## [1.4]

### Ajouté
- Gestion et personnalisation des métadonnées SEO.
- Affichage des erreurs PHP pour les administrateurs sous forme de fenêtre d'alerte.

## [1.3]

### Ajouté
- Gestion des types de contenu personnalisés (CPT).

## [1.2]

### Ajouté
- Gestion des erreurs et outils de debug pour les administrateurs.

## [1.1]

### Ajouté
- Prise en charge de Tarteaucitron.js pour la conformité RGPD, avec plusieurs services tiers.

## [1.0]

### Ajouté
- Version initiale du plugin.
- Gestion des images à la une par type de contenu.
