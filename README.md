# Optimizr

Plugin WordPress qui regroupe, dans une seule page d'administration, les réglages dont un site vitrine a besoin au quotidien : consentement aux cookies (RGPD), balises SEO, protection de la connexion, images (images à la une, conversion en WebP ou AVIF), cache et quelques outils de maintenance.

Chaque fonctionnalité est un **module** que l'on active ou désactive depuis le tableau de bord du plugin. Un module désactivé ne charge aucun code et son onglet disparaît.

Optimizr est la version gratuite, publiable sur WordPress.org. Les fonctionnalités avancées (redirections et journal des 404, types de contenu, SEO local, pages spéciales, assistant IA, sitemap, sécurité complète…) sont dans l'extension séparée **[Optimizr Pro](https://github.com/Corteo/Optimizr-pro)**, qui se branche sur Optimizr par ses points d'extension et reprend les réglages existants.

**Version actuelle :** 7.20.0 · [Historique des versions](CHANGELOG.md)

Pour les administrateurs, un menu **Optimizr** dans la barre d'administration (sur le site comme dans l'administration) ouvre le tableau de bord du plugin et chacun de ses onglets, l'assistant de configuration et le suivi des campagnes.

En option (onglet **Outils**, désactivée par défaut), une **barre flottante** remplace sur le site la barre d'administration de WordPress : un petit module qui permet de modifier la page affichée, de créer un contenu, de voir son état SEO et son aperçu Google, de vider le cache, d'auditer la page et de voir les commentaires et mises à jour en attente. Trois designs au choix (pastille, dock, fiche de la page), à gauche ou à droite, pour tous les rôles ou seulement les éditeurs ou les administrateurs. L'administration garde la barre de WordPress.

## Modules

### Cookies & RGPD
- Bandeau de consentement [Tarteaucitron.js](https://tarteaucitron.io/) : position (haut, centre, bas), avec aperçu en direct. Apparence **Thème du site** (par défaut) : le bandeau et le panneau « Personnaliser » reprennent le fond, le texte, les polices et les boutons du thème (`js/optimizr-cookies-theme.js`), ou **Couleurs du bandeau**. Styles dans `css/optimizr-cookies.css` : textes toujours lisibles (contraste WCAG AA vérifié pour chaque fond), panneau plein écran sur téléphone.
- Google Analytics 4 et Google Tag Manager, chargés uniquement après accord du visiteur.
- Google Consent Mode v2 transmis automatiquement.
- **Suivi des campagnes** (menu **Optimizr › Suivi des campagnes**, et lien dans l'onglet Cookies & RGPD) : parcours guidé plein écran, dans le style de l'assistant de configuration, pour préparer des campagnes Google (et Meta avec Optimizr Pro). Étapes : objectif (Google, Meta), mesure d'audience (ID Google Analytics 4 ou conteneur Google Tag Manager, avec l'endroit où les trouver, format vérifié et mise en garde contre la double mesure), conversions, consentement (bandeau, politique de confidentialité, Consent Mode v2), récapitulatif. Il active le module Cookies & RGPD et le bandeau. L'écran final liste les vérifications à faire (Tag Assistant, temps réel, événements clés, association Google Ads).
- **Conversions** (`functions/conversions.php`, `js/optimizr-conversions.js`) : formulaire envoyé, seulement si le message part (Contact Form 7 : `wpcf7mailsent` ; WPForms : `wpformsAjaxSubmitSuccess` ; Gravity Forms : `gform_confirmation_loaded` ; Fluent Forms : `fluentform_submission_success` ; Elementor Pro : `submit_success` ; Ninja Forms : `submit:response` du canal `forms` ; Formidable : `frmFormComplete` ; sans AJAX, message de confirmation de WPForms, Gravity Forms ou Formidable affiché par la page rechargée ; champs relevés à l'envoi et transmis en `inputs`), page de remerciement (chemins saisis dans l'assistant, `generate_lead` avec `page_path`), inscription à la lettre d'information (MailPoet et Brevo : réponse de leur requête AJAX lue dans l'évènement jQuery `ajaxSuccess` ; formulaire Brevo intégré depuis sibforms.com : panneau `#success-message` ; Mailchimp for WordPress : évènement `subscribed` de son API `mc4wp.forms` ; jamais au simple clic, et pas pour une adresse déjà inscrite), clic sur un numéro de téléphone (`tel:`), une adresse e-mail (`mailto:`), WhatsApp (`wa.me`, `whatsapp.com`) un service de prise de rendez-vous (domaines saisis, Doctolib, Calendly et Planity proposés) un itinéraire (Google Maps, `maps.app.goo.gl`, Waze, Plans d'Apple, `geo:`), un élément de classe `optimizr-conversion` (`click_cta` avec `link_text`), un fichier à télécharger (PDF, Word, Excel, PowerPoint, ZIP… ou lien `download`), le formulaire d'avis Google (`g.page/r/…/review`, `writereview`) ou un réseau social ; avec WooCommerce, ajout au panier (évènement jQuery `added_to_cart`, évènement `wc-blocks_added_to_cart` des blocs, ou formulaire classique gardé en session pour la page suivante), page de commande et confirmation de commande (une fois par commande, méta `_haptic_conversion_sent`), avec `value` (hors livraison et taxes), `currency`, `items` et `transaction_id`. Chaque action choisie part vers Google Analytics 4 par `gtag()` (`generate_lead`, `click_phone`, `click_email`, `click_whatsapp`, `click_booking`, `click_directions`, `click_cta`, `click_review`, `click_social`, `add_to_cart`, `begin_checkout`, `purchase`, `sign_up` avec `method` (MailPoet, Brevo, Mailchimp) et `form_id`, `click_download` avec `file_name` et `file_extension` ; nom distinct du `file_download` de la mesure améliorée de GA4, pour ne rien compter deux fois), qui applique le Consent Mode v2, et vers Google Tag Manager par le dataLayer (évènement `optimizr_conversion`, avec `conversion_action` et `conversion_event`). Autres formulaires ou boutons : `window.optimizrConversion('form', { form_id: 'devis' })`.
- L'onglet Cookies & RGPD affiche l'état du suivi (outils renseignés, conversions suivies) et la date de la dernière conversion vue pour chaque action.
- **Onglet Suivi** (`functions/tracking.php`, avec le module Cookies & RGPD) : tableau de bord des conversions sur 7, 30 ou 90 jours (`?days=`). À chaque conversion, le navigateur envoie au site un signal anonyme (requête `haptic_conversion_seen` : action, chemin de la page sans paramètres, accord du visiteur pour Google Analytics ou Tag Manager ; numéro et clé de commande pour un achat, dont le montant est relu sur la commande), compté par jour dans la table `{prefix}optimizr_conversions`, sans cookie ni donnée du visiteur (30 signaux par minute et par adresse IP au plus ; comptes effacés après 400 jours). L'onglet montre la synthèse et son évolution, un graphique par jour, la part des conversions de visiteurs qui ont refusé les cookies (invisibles pour Google), les conversions par action (évolution, courbe, dernière vue), les points à vérifier (bandeau désactivé, action qui s'arrête, action jamais vue, paniers abandonnés), les pages qui convertissent et, avec WooCommerce, ventes, panier moyen et entonnoir. *Optimizr Pro :* sources de visiteurs d'après Google Analytics 4.
- Toujours proposés, sans réglage : YouTube, Dailymotion, X (Twitter), Facebook, Google Maps sans clé.
- *Optimizr Pro :* Microsoft Clarity, Matomo, Hotjar, Google Ads, Pixel Meta, Google Fonts, Google Maps avec clé, reCAPTCHA, hCaptcha, couleurs du bandeau ; dans le suivi des campagnes, conversions Google Ads (et conversions améliorées), Pixel Meta avec évènements Lead, Contact et Schedule, validation du domaine Meta.

### SEO
- Titre et description par défaut construits à partir de variables (titre de la page, nom du site, résumé, catégorie, auteur…), avec aperçu Google et aperçu de partage social en direct.
- Balises OpenGraph (titre, description, image).
- Metabox « SEO & OpenGraph » dans l'éditeur : titre, description et image personnalisés, option « ne pas indexer ».
- *Optimizr Pro :* sitemap XML, plan du site, robots.txt, options d'indexation, codes de vérification, IndexNow, données structurées, chiffres de la Search Console (clics, impressions, requêtes, pages).

### Redirections
- *Optimizr Pro uniquement* (depuis Optimizr 5.0.0) : redirections 301, 302, 307 et 410 illimitées, journal des pages introuvables (404), jokers, import/export CSV, redirection automatique des contenus déplacés, liens cassés.
- Les redirections et le journal créés avec Optimizr 4.x restent dans la base (tables `{prefix}optimizr_redirects` et `{prefix}optimizr_404`) : Optimizr Pro 1.2.0 ou plus récent les reprend tels quels. Sans Optimizr Pro, ils ne s'appliquent plus mais ne sont pas effacés.
- Optimizr Pro antérieur à 1.2.0 : ses redirections sont en pause et un avertissement demande sa mise à jour.

### Types de contenu
- *Optimizr Pro uniquement* (depuis Optimizr 7.0.0) : type « Évènements » prêt à l'emploi (date de début, date de fin, lieu, horaires, sans ACF) et création de types de contenu personnalisés depuis l'administration (nom et icône Dashicons).
- Les types créés avec Optimizr 6.x et leurs contenus restent dans la base (options `haptic_custom_cpts` et `haptic_options_settings`, articles) : Optimizr Pro 1.3.0 ou plus récent les reprend tels quels, avec les mêmes adresses. Sans Optimizr Pro, ces types ne sont plus enregistrés : leurs contenus ne sont plus visibles mais ne sont pas effacés.
- Optimizr Pro antérieur à 1.3.0 : un avertissement demande sa mise à jour.

### Images
- Choix des types de contenu qui proposent une image de couverture.
- **Formats d'image** (désactivé par défaut) : les images JPEG et PNG importées sont enregistrées en WebP ou en AVIF, taille « full » et tailles intermédiaires comprises, par l'éditeur d'images de WordPress (GD ou Imagick), sans service externe. Qualité réglable (82 par défaut) et choix des formats d'origine à convertir. Le fichier d'origine reste dans le dossier de la médiathèque, référencé comme image originale. Les GIF ne sont jamais convertis.
  - Images CMJN (JPEG préparé pour l'impression) : avec Imagick, converties en sRGB avec leur profil ICC, pour garder leurs couleurs ; avec GD, qui ignore les profils, elles restent en JPEG.
  - AVIF n'est proposé que si le serveur sait l'écrire (WordPress 6.5+, GD ou Imagick compilés avec AVIF) ; sinon, ou si l'hébergement change, les images passent en WebP.
  - Seules les images importées après l'activation sont converties. *Optimizr Pro :* conversion de la médiathèque existante.
- **Panneau « Optimizr » de la médiathèque** : dans la fiche de chaque image (fenêtre de la médiathèque, modale média de l'éditeur, écran « Modifier le média »), le format et le poids de l'image, sa conversion en WebP ou AVIF et le gain obtenu, la raison si elle n'est pas convertie (conversion désactivée, GIF, image CMJN, format exclu des réglages) et l'état de son texte alternatif, mis à jour pendant la saisie. *Optimizr Pro* y ajoute ses actions : conversion de l'image, texte alternatif par IA.

### Cache
Tout est désactivé par défaut. Sur un site existant, le module s'active depuis le tableau de bord.
- **Cache des pages** : les pages vues par les visiteurs non connectés sont enregistrées en fichiers HTML (`wp-content/cache/optimizr/pages/`) et renvoyées avant le chargement de WordPress, par le fichier `wp-content/advanced-cache.php` et la constante `WP_CACHE`, qu'Optimizr ajoute en haut de `wp-config.php` (sinon, il indique la ligne à ajouter). Durée de vie réglable (10 heures par défaut, ce qui laisse les formulaires fonctionner), adresses exclues (`/contact/`, `/boutique/*`), cache séparé pour les téléphones en option. Jamais en cache : personnes connectées, commentateurs, contenus protégés par mot de passe, recherches, pages introuvables, paniers et commandes WooCommerce, adresses avec paramètres (sauf ceux de suivi comme `utm_*`, `gclid`, `fbclid`), pages qui posent un cookie. En-tête `X-Optimizr-Cache: HIT` sur une page servie depuis le cache.
- **Vidage automatique** : un contenu modifié vide sa page, l'accueil et ses archives (type, catégories, auteur) ; un contenu publié, dépublié ou dont l'adresse change, un commentaire, un menu, un widget, le thème, une catégorie, une extension ou un réglage vide tout le cache.
- **Vidage manuel** : bouton de l'onglet Cache et menu **Optimizr › Cache** de la barre d'administration (tout le site, ou la page affichée).
- **Cache navigateur et compression** : règles `mod_expires`, `mod_headers` et `mod_deflate` dans un bloc « Optimizr Cache » du `.htaccess` (Apache, LiteSpeed) : images, polices et vidéos un an, styles et scripts un mois. Sur Nginx, l'onglet affiche les règles à transmettre à l'hébergeur.
- **Conflits** : avec une autre extension de cache (WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache…) ou un `advanced-cache.php` qui n'est pas d'Optimizr, le cache des pages reste désactivé. Le cache de l'hébergeur (WP Engine, Kinsta, Pantheon, GoDaddy, Pressable, Varnish) est signalé, et vidé en même temps quand c'est possible (WP Engine, Pantheon, extension Nginx Helper). Pas de cache des pages sur un multisite.
- Désactiver le cache, le module ou le plugin retire `advanced-cache.php`, la ligne de `wp-config.php`, les règles du `.htaccess` et les pages en cache.
- *Optimizr Pro :* préchargement du cache et vidage de Cloudflare.

### Emails
- Désactivation des emails envoyés après les mises à jour automatiques des extensions et de WordPress.

### Sécurité
Toutes les protections sont désactivées par défaut et s'activent une à une.
- **Adresse de connexion personnalisée** : wp-login.php et /wp-admin/ affichent aux visiteurs la page 404 du thème, comme une adresse qui n'existe pas ; la nouvelle adresse est envoyée par email à chaque changement. Fonctionne avec les permaliens simples (`https://site/?adresse`). En cas de blocage : `define('OPTIMIZR_DISABLE_CUSTOM_LOGIN', true);` dans wp-config.php.
- **Limitation des tentatives de connexion** par adresse IP (seuil et durée réglables, liste blanche, proxys de confiance et Cloudflare), **messages d'erreur neutres**, **alerte email** lors d'une connexion administrateur depuis une nouvelle adresse IP.
- **Double authentification** : après le mot de passe, un code à 6 chiffres, au choix du compte :
  - donné par une application d'authentification (Google Authenticator, Microsoft Authenticator, 1Password…) : codes TOTP calculés par le plugin, sans service externe, QR code dessiné dans le navigateur (bibliothèque qrcode-generator, MIT, dans `assets/qrcode-generator/`) ;
  - ou envoyé par e-mail (réglage « Code par e-mail », activé par défaut) : code valable 10 minutes, 3 envois au plus par connexion, toujours envoyé à l'adresse vérifiée à la mise en place. Moins sûr que l'application ; le réglage « Application pour les rôles obligatoires » l'écarte pour ces rôles.

  Chacun la met en place depuis son profil ou depuis l'écran proposé à la connexion, avec 10 codes de secours à usage unique ; elle peut être imposée à certains rôles (leur administration se limite au profil tant qu'elle n'est pas en place). Nouveaux codes de secours, changement de méthode et désactivation demandent un code. 5 codes faux au plus par quart d'heure et par compte (connexion et profil confondus), 10 e-mails de code au plus par heure. Un administrateur peut la réinitialiser depuis le profil d'un autre compte. La saisie du code se fait sur l'adresse de connexion, personnalisée ou non ; les codes faux comptent dans la limitation des tentatives ; XML-RPC avec le mot de passe d'un compte protégé est refusé, et un mot de passe d'application (profil ou écran « Autoriser l'application ») ne peut être créé que dans les 10 minutes qui suivent la connexion. En cas de blocage : `define('OPTIMIZR_DISABLE_2FA', true);` dans wp-config.php.
- **Écran « Sécurisez votre compte »** : à la première connexion qui suit l'activation de la double authentification (ou des passkeys d'Optimizr Pro), un écran court, dans le cadre de la page de connexion, propose de la mettre en place avant d'arriver sur la page demandée : une carte par étape, choix de la méthode, QR code ou code reçu, codes de secours, puis « Continuer ». « Plus tard » le repousse d'une semaine ; pour un rôle obligatoire, il n'y a que la mise en place. Proposé aux comptes qui peuvent écrire des contenus (filtre `haptic_sec_welcome_user`). Les mêmes étapes terminent l'assistant de configuration pour l'administrateur qui l'a suivi.
- **Désactivation des commentaires** (carte « Commentaires », `functions/security-comments.php`) : commentaires et pings fermés sur tous les contenus (boîte Discussion et colonne Commentaires retirées), commentaires existants masqués sur le site (modèle de commentaires vide, blocs de commentaires des thèmes en blocs, widget « Commentaires récents »), flux de commentaires retirés et redirigés vers la page ou l'accueil, en-tête `X-Pingback` et méthodes pingback de XML-RPC retirés, routes `/wp/v2/comments` de l'API REST supprimées, menu Commentaires, écrans de modération et widgets du tableau de bord masqués, nœud Commentaires retiré de la barre d'administration et de la barre flottante. Rien n'est effacé en base : désactiver le réglage rend les commentaires tels qu'ils étaient. Filtre `haptic_comments_disabled` pour forcer l'état.
- Journal des verrouillages, alertes et évènements de double authentification (30 jours).
- *Optimizr Pro :* passkeys (connexion sans mot de passe, qui dispense du code), discrétion, fonctions inutilisées désactivées, en-têtes de sécurité, bilan de l'environnement.

### Audit Lighthouse
- Onglet **Audit** : scores de performance, d'accessibilité, de bonnes pratiques et de SEO d'une page du site (accueil par défaut), sur mobile ou sur ordinateur, mesurés par Google avec Lighthouse ([API PageSpeed Insights](https://developers.google.com/speed/docs/insights/v5/get-started)).
- Mesures de laboratoire (FCP, LCP, TBT, CLS, Speed Index), données réelles des visiteurs de Chrome (Chrome UX Report) quand le site a assez de trafic, et les principaux points à améliorer de chaque catégorie, avec leur explication. Historique des 30 derniers audits et lien vers le rapport complet.
- Demande une **clé API Google**, gratuite (jusqu'à 25 000 audits par jour) : console Google Cloud › activer « PageSpeed Insights API » › Identifiants › créer une clé API, puis la coller dans l'onglet. Google ne répond plus aux requêtes sans clé.
- L'audit est lancé par Google depuis ses serveurs : un site local (MAMP, `.local`, `.test`, adresse privée) ne peut pas être audité. Rien n'est envoyé à Google sans clic sur « Lancer l'audit ».

### Outils
- **Import depuis Yoast SEO, All in One SEO, Rank Math, SEOPress, The SEO Framework et Solid Security** (`functions/migration.php`, `js/optimizr-migration.js`) : la carte n'apparaît que si l'une de ces extensions est installée ou a laissé des données. **Solid Security** (anciennement iThemes Security) y apporte ses réglages : adresse de connexion masquée, limite des tentatives, adresses IP autorisées, Cloudflare, double authentification (chaque compte la refait à sa prochaine connexion) et, avec Optimizr Pro, éditeur de fichiers, XML-RPC et énumération des comptes ; aucune protection n'est désactivée. *Optimizr Pro* y ajoute les redirections (Redirection, Yoast SEO Premium, Rank Math, All in One SEO Pro, SEOPress Pro). La carte indique ce qu'elle a trouvé (« 312 titres, 280 descriptions… ») et importe, par lots de 100 contenus, le titre SEO, la description, l'image de partage et le « noindex » de chaque contenu dans les champs d'Optimizr. Les variables sont traduites (`%%title%%`, `#post_title`, `%title%`, `%%post_title%%` → `{titre_page}` ; `%%sitename%%`, `#site_title`, `%sitename%`, `%%sitetitle%%` → `{nom_site}`…), et le nom du site que The SEO Framework ajoutait au titre est repris ; celles sans équivalent (numéro de page, année, champ personnalisé) sont retirées. En option, les modèles de titre et de description (ceux des pages, sinon des articles) remplacent ceux d'Optimizr (une seule source à la fois, cochée d'office pour la première seulement s'ils n'ont jamais été modifiés). Une valeur déjà saisie dans Optimizr n'est jamais remplacée, une valeur égale au résultat du modèle n'est pas enregistrée, et les données de l'autre extension restent en base. L'extension d'origine peut être désactivée à la fin de l'import (jamais supprimée) ; le module SEO et ses balises automatiques sont activés et le cache vidé. All in One SEO 3 (métadonnées `_aioseop_*`) est lu quand la table `aioseo_posts` d'All in One SEO 4 est absente.
- **Export / import des réglages** : un fichier JSON contient tous les réglages d'Optimizr, modules désactivés compris. On configure un site, on exporte, on importe sur le suivant. La clé API n'est exportée que sur demande, et l'adresse du site d'origine est remplacée automatiquement (robots.txt…).
- **Barre flottante** : activation, design (pastille, dock ou fiche de la page), côté de l'écran et rôles concernés.
- **Suppression du plugin** : à la désactivation (ou dans l'onglet Outils), on choisit ce qui sera effacé si le plugin est ensuite supprimé : réglages, redirections et SEO des contenus, journaux. Par défaut, tout est conservé ; les tâches planifiées, caches et fichiers générés sont toujours supprimés.

## Prérequis

- WordPress 6.2 ou plus récent
- PHP 7.4 ou plus récent

## Installation

1. Récupérez l'archive `optimizr-X.Y.Z.zip` de la [dernière release GitHub](https://github.com/Corteo/Optimizr/releases/latest).
2. Dans l'administration WordPress, allez dans **Extensions › Ajouter une extension › Téléverser une extension** et envoyez l'archive.
3. Activez **Optimizr**.

Le dossier du plugin doit s'appeler `optimizr`. Depuis 7.19.0, les nouvelles versions publiées en release GitHub sont proposées dans **Tableau de bord › Mises à jour** et dans **Extensions**, comme pour toute extension (mises à jour automatiques comprises). Une fois Optimizr publié sur WordPress.org sous le slug `optimizr`, elles viendront de là. Les sites en version antérieure à 7.19.0 installent celle-ci une fois à la main (« Remplacer la version installée »). Les éventuelles migrations de la base s'exécutent au chargement suivant.

### Mise à jour depuis la 1.5

La version 1.5 ne détecte pas les mises à jour (adresse du dépôt mal formée). Sur ces sites, installez la 2.0 **une seule fois à la main** : **Extensions › Ajouter une extension › Téléverser une extension**, puis « Remplacer la version installée ». Les réglages sont conservés et migrés automatiquement ; les mises à jour suivantes seront proposées normalement.

Après la mise à jour, tous les modules sont actifs. Les nouveaux (SEO local, Pages spéciales, Sécurité) n'ont aucun effet tant qu'ils ne sont pas réglés ; seul le module Redirections agit d'emblée, avec le journal des pages introuvables et la redirection automatique des contenus déplacés. Désactivez depuis le tableau de bord du plugin les modules inutiles. FontAwesome n'est plus chargé par le plugin ; un thème qui en utilise les icônes doit le charger lui-même.

## Utilisation

Ouvrez le menu **Optimizr** dans la barre latérale de l'administration.

- **Assistant de configuration :** s'ouvre en plein écran à la première activation du plugin, puis à la demande (menu **Optimizr › Assistant**, bouton « Assistant de configuration » de la page d'options, lien dans la liste des extensions). En quelques étapes, il fait choisir les modules puis renseigner l'essentiel de chacun : adresse de connexion et verrouillage, connexion des comptes (double authentification, et passkeys d'Optimizr Pro), nom du site et motifs SEO, outils de mesure (Optimizr Pro y ajoute ses propres étapes). Rien n'est enregistré avant la dernière étape ; les étapes des modules non choisis sont sautées. Avec le module Sécurité, l'administrateur met ensuite en place la double authentification (ou sa passkey) de son propre compte, avant l'écran final ; les autres comptes se la voient proposer à leur prochaine connexion.
- **Tableau de bord :** un en-tête illustré donne l'état du site (points à régler, état par thème : sécurité, visibilité, performance, contenu), avec le dernier audit, une carte « Google Search » (présentation de la Search Console ; avec Optimizr Pro, ses clics, impressions et positions), l'état du cache, les versions et des actions rapides (vider le cache, lancer un audit). Les modules sont rangés par thème ; leur interrupteur les active ou les désactive aussitôt, sans enregistrement, et leur onglet apparaît ou disparaît.
- **Menu latéral :** les onglets sont rangés à gauche de la page, par rubrique, dans l'ordre et avec les couleurs des thèmes du tableau de bord : Tableau de bord et Suivi en tête, puis Sécurité et conformité (Cookies & RGPD, Sécurité, Emails), Visibilité (SEO, et avec Optimizr Pro Search Console, Redirections, SEO local, Assistant IA), Performance (Cache, Images, Audit), Contenu et outils (avec Optimizr Pro Types de contenu, Pages spéciales, Connecteur IA ; Outils). La rubrique d'un onglet est celle de son module (`haptic_dashboard_groups()`), ou sa clé `group`. Sur petit écran, le menu devient une liste déroulante.
- **Un onglet par module :** chaque onglet s'enregistre séparément avec le bouton « Enregistrer les modifications » en bas de page. Une alerte signale les modifications non enregistrées.
- **Audit :** audit Lighthouse d'une page, sur mobile ou sur ordinateur, et historique des scores.
- **Outils :** import depuis Yoast SEO, All in One SEO, Rank Math, SEOPress, The SEO Framework et Solid Security, export et import des réglages, choix de ce qu'efface la suppression du plugin.

## Pour les développeurs

### Configuration

| Constante (`wp-config.php`) | Rôle |
|---|---|
| `OPTIMIZR_DISABLE_CUSTOM_LOGIN` | `true` désactive l'adresse de connexion personnalisée (retour à wp-login.php), en cas de blocage. |

### Points d'extension

| Hook | Type | Rôle |
|---|---|---|
| `haptic_modules` | filtre | Ajouter ou modifier un module du tableau de bord. |
| `haptic_admin_tabs` | filtre | Ajouter ou modifier un onglet de la page d'options. Clé `group` facultative : rubrique du menu latéral (thème de `haptic_dashboard_groups()`, `''` pour un lien en tête) ; sinon celle du premier module de l'onglet. |
| `haptic_admin_tab_aliases` | filtre | Anciennes adresses d'onglets (`tab=`) redirigées vers leur nouvelle place quand l'onglet n'existe plus. |
| `haptic_admin_nav_icons` | filtre | Icônes des liens de tête du menu latéral (tableau de bord, suivi). |
| `haptic_admin_aside_{onglet}` | action | Afficher un panneau latéral dans un onglet. |
| `haptic_site_health_checks` | filtre | Ajouter une vérification à l'état du site (en-tête du tableau de bord). Clés `module` ou `group` facultatives : thème auquel rattacher un point à régler (sinon déduit de l'onglet du lien). |
| `haptic_dashboard_groups` | filtre | Thèmes du tableau de bord : libellé, icône, couleurs et modules de chacun. |
| `haptic_dashboard_actions` | filtre | Actions rapides de l'en-tête du tableau de bord (`label`, `url`, `icon`). |
| `haptic_media_panel_items` | filtre | Lignes d'état du panneau « Optimizr » de la médiathèque (`label`, `status` : ok, warn, off ou info, `text`, `link`, `link_label`). |
| `haptic_media_panel_actions` | filtre | Boutons (HTML) du panneau de la médiathèque ; `window.optimizrMediaPanel` (`setStatus()`, `refresh()`) sert à leur script. |
| `haptic_media_panel_enabled` | filtre | `false` masque le panneau pour une image. |
| `haptic_dashboard_versions` | action | Lignes de la carte « Versions et licence » ; `haptic_dashboard_version_row()` affiche une ligne. |
| `haptic_module_status` | filtre | État d'un module sur le tableau de bord. |
| `haptic_dashboard_search` | action | Contenu de la carte « Google Search » du tableau de bord (Search Console d'Optimizr Pro) ; sans elle, la carte présente la Search Console. |
| `haptic_dashboard_aside` | action | Carte supplémentaire sous « Versions et licence » (Optimizr Pro antérieur à 1.13.0). |
| `haptic_loaded` | action | Optimizr est chargé : point d'entrée des extensions. |
| `haptic_activate` / `haptic_deactivate` | action | Activation et désactivation d'Optimizr. |
| `haptic_settings_imported` | action | Réglages importés depuis l'onglet Outils. |
| `haptic_migration_sources` | filtre | Extensions concurrentes dont Optimizr sait importer les données (cf. `functions/migration.php`). |
| `haptic_migration_extras` | filtre | Autres données importables, rattachées à une source : résumé et import (redirections d'Optimizr Pro). |
| `haptic_migration_solid_settings` | filtre | Réglages de Solid Security repris dans Optimizr. |
| `haptic_migration_finished` | action | Import depuis une autre extension terminé (sources, totaux). |
| `haptic_cookie_services` | filtre | Services proposés dans l'onglet Cookies, par catégorie. |
| `haptic_tarteaucitron_service` | filtre | Variables et services Tarteaucitron d'un service ajouté. |
| `haptic_tac_color` | filtre | Couleur du bandeau (valeur par défaut d'Optimizr). |
| `haptic_cookies_consent_note` | filtre | Texte de l'encadré sur les modes de consentement (onglet Cookies). |
| `haptic_seo_post_types` | filtre | Types de contenu qui ont la metabox SEO. |
| `haptic_seo_metabox_after_description` | action | Contenu ajouté sous le champ description de la metabox SEO. |
| `haptic_sec_protections` / `haptic_sec_environment_checks` | filtre | Protections et vérifications du bilan de l'onglet Sécurité. |
| `haptic_sec_login` | action | Connexion terminée (après le code de double authentification s'il y en a un), à la place de `wp_login`. |
| `haptic_sec_two_factor_bypass` | filtre | Renvoyer `true` pour une connexion qui vaut déjà deux facteurs (passkey d'Optimizr Pro) : pas de code demandé. |
| `haptic_sec_log_labels` | filtre | Libellés des types d'évènement du journal de sécurité. |
| `haptic_sec_welcome_user` | filtre | Proposer l'écran « Sécurisez votre compte » à ce compte (par défaut : ceux qui peuvent écrire des contenus). |
| `haptic_sec_welcome_steps` | filtre | Étapes de cet écran (clé => obligatoire), comme la passkey d'Optimizr Pro. |
| `haptic_sec_welcome_enqueue` / `haptic_sec_welcome_content` | action | Scripts et contenu d'une étape de cet écran. |
| `haptic_onboarding_steps` | filtre | Étapes de l'assistant (une étape peut fournir sa fonction `render`). |
| `haptic_onboarding_account_toggles`, `haptic_onboarding_security_toggles`, `haptic_onboarding_seo_toggles`, `haptic_onboarding_cookie_fields`, `haptic_onboarding_save` | filtre | Champs de l'assistant et enregistrement (`haptic_onboarding_account_toggles` : connexion des comptes, comme les passkeys d'Optimizr Pro). |
| `haptic_onboarding_seo_fields`, `haptic_onboarding_step_after`, `haptic_onboarding_print_scripts`, `haptic_onboarding_illustration` | action | Contenu ajouté aux étapes de l'assistant. |
| `haptic_campaigns_goals`, `haptic_campaigns_steps`, `haptic_campaigns_save`, `haptic_campaigns_checks`, `haptic_campaigns_status_rows` | filtre | Suivi des campagnes : objectifs, étapes (même format que l'assistant, avec `illu`), enregistrement, vérifications de l'écran final, lignes de l'état du suivi. |
| `haptic_conversion_actions`, `haptic_conversion_events`, `haptic_conversions_front_config` | filtre | Actions suivies comme conversions, évènements GA4 et Meta de chacune, configuration lue par `js/optimizr-conversions.js`. |
| `haptic_tracking_alerts`, `haptic_tracking_action_groups` | filtre | Onglet Suivi : points à vérifier (`[gravité, titre, texte, lien]`), famille de chaque action (`contact`, `visit`, `engage`, `shop`). |
| `haptic_tracking_main`, `haptic_tracking_aside` | action | Onglet Suivi : cartes ajoutées dans la colonne principale ou latérale (reçoivent le nombre de jours et le rapport). |
| `haptic_campaigns_step_after`, `haptic_campaigns_print_scripts`, `haptic_campaigns_saved` | action | Contenu ajouté aux étapes du suivi des campagnes, réglages enregistrés. |
| `optimizr:conversion` | évènement DOM | Déclenché sur `document` à chaque conversion (`detail` : `action`, `event`, `params`, `inputs` pour un formulaire). Optimizr Pro s'en sert pour Google Ads et Meta. |
| `haptic_sec_cloudflare_ranges` | filtre | Plages d'adresses de Cloudflare reconnues par l'option « Cloudflare » des proxys de confiance. |
| `haptic_cache_purged` | action | Cache vidé : tableau des adresses vidées, vide pour tout le site (Optimizr Pro : préchargement, Cloudflare). |
| `haptic_cache_skip_request` | filtre | Renvoyer `true` pour ne pas mettre la page demandée en cache. |
| `haptic_cache_post_urls` | filtre | Pages vidées quand un contenu publié est modifié (`urls`, et `tree` avec les pages en dessous). |
| `haptic_cache_bypass_cookies`, `haptic_cache_ignored_query_args` | filtre | Cookies qui excluent du cache, paramètres d'adresse ignorés. |
| `haptic_cache_conflicting_plugins`, `haptic_cache_host_caches` | filtre | Extensions de cache et caches d'hébergeur détectés. |
| `haptic_cache_purge_capability` | filtre | Droit nécessaire pour vider le cache (`manage_options` par défaut). |
| `haptic_cache_aside` | action | Carte ajoutée au panneau latéral de l'onglet Cache. |
| `haptic_toolbar_show` | filtre | Renvoyer `false` pour garder la barre de WordPress sur la page affichée (barre flottante activée). |
| `haptic_toolbar_context` | filtre | Contenu de la barre flottante pour la page affichée (titre, liens, SEO, cache, éléments à traiter…). |
| `haptic_toolbar_collect_nodes`, `haptic_toolbar_other_nodes` | filtre | Raccourcis des autres extensions repris dans la barre flottante : `false` pour ne pas les recueillir, liste des liens. |
| `haptic_settings_internal_options` | filtre | Options propres au site, exclues de l'export et de l'import. |
| `haptic_seo_disable_theme_tags` | filtre | Renvoyer `false` pour conserver les balises SEO du thème Haptic. |
| `haptic_before_enqueue_scripts` / `haptic_after_…` | action | Charger des scripts côté site. |
| `haptic_before_enqueue_admin_scripts` / `haptic_after_…` | action | Charger des scripts côté administration. |

`haptic_sec_recent_auth()` indique si la session a été ouverte ou confirmée (mot de passe, ou code de l'application) il y a moins de 10 minutes : condition pour ajouter un moyen de connexion, comme une passkey d'Optimizr Pro. La confirmation passe par la requête `haptic_sec_confirm` (nonce `haptic_sec_confirm`).

`haptic_cache_purge_all()` vide tout le cache des pages, `haptic_cache_purge_urls($urls)` celui de quelques adresses du site ; `haptic_cache_page_active()` indique si le cache des pages est en service.

`haptic_is_pro()` indique si Optimizr Pro est actif, `haptic_pro_is_outdated()` s'il est antérieur à la version minimale `HAPTIC_OPTIMIZR_PRO_MIN_VERSION` (1.3.0). Les réglages d'Optimizr Pro sont des options `haptic_*` : Optimizr les exporte, les importe et les supprime avec les siens.

Un onglet déclare ses réglages avec la Settings API de WordPress, en utilisant `haptic_settings_group($onglet)` pour `register_setting()` et `haptic_settings_page($onglet)` pour `add_settings_section()` et `add_settings_field()`. Les sections sont affichées sous forme de cartes ; l'argument `toggle` d'une section place un interrupteur dans son en-tête.

### Organisation du code

```
optimizr.php             Fichier principal : constantes, chargement des modules
functions/admin-ui.php   Page d'options : modules, onglets, icônes
functions/dashboard.php  Tableau de bord : état du site, thèmes de modules, activation sans enregistrement
functions/onboarding.php Assistant de configuration plein écran (+ css/optimizr-onboarding.css, js/optimizr-onboarding.js)
functions/campaigns.php  Suivi des campagnes : parcours guidé (même présentation que l'assistant)
functions/conversions.php Conversions sur le site (+ js/optimizr-conversions.js) et état du suivi (onglet Cookies)
functions/tabs.php       Affichage des sections en cartes
functions/cookies.php    Module Cookies & RGPD (+ css/optimizr-cookies.css, thème du bandeau)
functions/seo/           Module SEO (réglages, metabox, balises)
functions/tools.php      Onglet Outils : export et import des réglages
functions/migration.php  Import depuis les extensions SEO et Solid Security
functions/admin-bar.php  Menu Optimizr de la barre d'administration
functions/toolbar.php    Barre flottante qui remplace la barre d'administration sur le site (+ css/optimizr-toolbar.css, js/optimizr-toolbar.js)
functions/audit.php      Onglet Audit : audit Lighthouse avec l'API PageSpeed Insights (+ js/optimizr-audit.js)
functions/cache.php      Module Cache : écriture des pages, vidage, onglet Cache
functions/cache-files.php Cache : advanced-cache.php, wp-config.php, .htaccess (toujours chargé, pour tout retirer)
functions/cache-serve.php Cache : service des pages avant WordPress (PHP natif, chargé par advanced-cache.php)
functions/thumbnails.php Module Images : images à la une
functions/images.php     Module Images : conversion des images importées en WebP ou AVIF
                         (+ class-haptic-image-editors.php : images CMJN ; assets/icc/ : profil sRGB, CC0)
functions/media-panel.php Panneau « Optimizr » de la fiche des images de la médiathèque (+ css/optimizr-media.css, js/optimizr-media.js)
functions/mails.php      Module Emails
functions/security.php   Module Sécurité, protection de la connexion (+ security-admin.php : onglet de réglages)
functions/security-2fa*.php  Double authentification : connexion (security-2fa.php), profil (security-2fa-profile.php, js/optimizr-2fa.js)
functions/security-welcome.php  Écran « Sécurisez votre compte » proposé après la connexion et à la fin de l'assistant (+ css/optimizr-welcome.css)
assets/qrcode-generator/ Bibliothèque de QR code (MIT) pour la mise en place de la double authentification
css/, js/                Interface de la page d'options
bin/                     Outils : build-wporg.sh (zip WordPress.org), check-version.sh, check-php.sh, build-translations.py
.github/workflows/       Intégration continue (ci.yml) et archive des releases (release.yml)
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
3. Vérifiez que le numéro concorde partout avec `bin/check-version.sh` (en-tête, constante, `Stable tag` et changelog de `readme.txt`, CHANGELOG.md et « Version actuelle » de ce README) ; l'intégration continue le refait sur chaque pull request.
4. Publiez la release GitHub avec le workflow « Release » (onglet Actions › Release › Run workflow, numéro `X.Y.Z`) : tag `vX.Y.Z`, titre `X.Y.Z` et archive `optimizr-X.Y.Z.zip`, dont le dossier racine est `optimizr`. Dans les 12 heures, les sites installés depuis GitHub se voient proposer la mise à jour.
5. Une fois Optimizr publié sur WordPress.org : construisez le zip WordPress.org (`bin/build-wporg.sh`) et publiez-le dans le dépôt SVN de WordPress.org.

Depuis 7.19.0, la version GitHub se met à jour depuis les releases publiques du dépôt (`functions/github-updates.php`, sans bibliothèque) : une requête anonyme à `api.github.com/repos/Corteo/Optimizr/releases/latest`, en cache 12 heures (1 heure après un échec), ignorée si l'archive `optimizr-X.Y.Z.zip` manque ou si la release est un brouillon ou une pré-version. Aucune licence, aucun compte, aucune donnée du site. « Voir les détails » affiche les notes de la release. Dès que la réponse de WordPress.org contient Optimizr, ce module s'efface et WordPress.org prend le relais. Filtre `haptic_github_updates_enabled` pour le couper. Pour essayer une version de `main` sur un site de test, installez son zip à la main (Extensions › Ajouter une extension › Téléverser).

### Intégration continue

Deux workflows GitHub Actions, dans `.github/workflows/` (exclus du zip WordPress.org et des archives des releases) :

- **CI** (`ci.yml`), à chaque pull request et à chaque push sur `main` : syntaxe de tous les fichiers PHP de PHP 7.4 à PHP 8.4 (`bin/check-php.sh`), numéro de version identique partout (`bin/check-version.sh`), et [Plugin Check](https://wordpress.org/plugins/plugin-check/) sur la version WordPress.org construite par `bin/build-wporg.sh`. Une erreur de Plugin Check fait échouer la CI ; ses avertissements sont affichés sans la bloquer.
- **Release** (`release.yml`) : « Run workflow » (onglet Actions) avec le numéro `X.Y.Z` crée la release sur le dernier commit de `main` (tag `vX.Y.Z`, titre `X.Y.Z`, notes reprises de la section de la version dans CHANGELOG.md), puis y ajoute `optimizr-X.Y.Z.zip`, dont le dossier racine est `optimizr`. Une release publiée à la main passe par les mêmes vérifications : tag égal à `v` + la version du plugin, titre ramené à `X.Y.Z`, notes vides remplies, archive ajoutée. Relancé sur une release existante, il refait son archive. La branche `stable` n'est jamais modifiée.

### Version WordPress.org

Le plugin existe en deux distributions construites à partir du même code :

- **GitHub** (ce dépôt tel quel) : mises à jour depuis les releases GitHub (`functions/github-updates.php`), jusqu'à ce que WordPress.org connaisse Optimizr.
- **WordPress.org** : le répertoire interdit les mises à jour venant d'ailleurs. `bin/build-wporg.sh [slug]` produit `dist/<slug>-<version>.zip` sans les fichiers de développement (liste dans `.distignore`), et retire des fichiers PHP les lignes marquées `build:github-only`. Le script échoue s'il trouve un système de mise à jour hors WordPress.org ou un fichier caché, ou si `Contributors` n'est pas renseigné dans `readme.txt`. Les icônes et bannières de la fiche WordPress.org sont dans `.wordpress-org/`, à envoyer dans le dossier `assets/` du dépôt SVN (pas dans le zip).

Plugin Check se passe sur ce zip, installé dans un dossier `optimizr` : sur la version GitHub, il signale à tort `bin/`, les fichiers cachés et, si le dossier s'appelle `Optimizr`, chaque texte traduit (domaine `optimizr` attendu égal au nom du dossier).

WordPress.org déduit le slug du nom du plugin (`Plugin Name` de `optimizr.php` et titre de `readme.txt`) : « Optimizr » donne `optimizr`, qui doit rester égal au domaine de traduction et au nom du dossier. Le slug ne change plus après l'approbation.

`readme.txt` (en anglais, au format WordPress.org) déclare les services externes contactés ; à tenir à jour quand un module en ajoute un.

Une migration à exécuter une seule fois se place dans `haptic_options_maybe_upgrade()` (`functions/activation.php`).

## Auteur

Alexandre Vergnaud · [alexandrevergnaud.fr](https://alexandrevergnaud.fr/) · Site du plugin : [optimizr.hapticstudio.fr](https://optimizr.hapticstudio.fr/)

## Licence

GPL2 · [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
