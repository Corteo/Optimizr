# Historique des versions

Toutes les évolutions notables d'Optimizr sont consignées ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et la numérotation suit le [Semantic Versioning](https://semver.org/lang/fr/).

## [Non publié]

## [7.7.0] - 2026-10-26

### Modifié
- **Nouveau style du bandeau de cookies et du panneau « Personnaliser »**, dans une feuille dédiée (`css/optimizr-cookies.css`) chargée après celle de Tarteaucitron (`useExternalCss`, les deux feuilles sont désormais mises en file par WordPress). Bandeau : carte arrondie sur fond assombri et flouté (position centre), carte flottante sur toute la largeur (haut, bas), icône de cookie dessinée à la place des émojis ☝ 🍪, bouton × discret, lien vers la politique de confidentialité souligné. Panneau : fenêtre centrée (plein écran sur téléphone), en-tête avec « Tout accepter / Tout refuser », catégories dépliables avec chevron, une carte par service avec ses boutons Autoriser / Interdire (contour au repos, plein et coché quand c'est le choix enregistré, au lieu de l'opacité réduite), « Fermer » et « Enregistrer » dans une barre fixée en bas. Police du thème, zones cliquables de 44 px, focus visible, animations coupées si le visiteur les réduit.
- La position choisie est posée sur `<html data-optimizr-tac="top|middle|bottom">` par le script de configuration : Tarteaucitron donne la même classe aux positions haut et milieu.
- Couleurs par défaut : bandeau et panneau blancs, bouton « Tout refuser » ardoise (`#1f2937`), bouton « Personnaliser » gris clair (`#eef0f3`). Les couleurs enregistrées avec Optimizr Pro sont conservées. Un bouton de la couleur du bandeau reçoit un contour.
- Aperçu de l'onglet Cookies : texte des boutons calculé comme sur le site, coins et ombre du nouveau bandeau. Le ratio de contraste affiché par Optimizr Pro mesure le texte réellement affiché sur le bouton (sombre ou blanc).

### Corrigé
- Textes illisibles du bandeau et du panneau : la croix, le lien vers la politique de confidentialité, le titre et le texte du panneau, « Préférences pour tous les services » et les titres de catégorie restaient en blanc sur les fonds clairs choisis.
- La couleur du bouton « Personnaliser » n'était jamais appliquée (le sélecteur visait une classe que Tarteaucitron ne pose pas sur ce bouton).
- La couleur du texte de chaque fond (bandeau, panneau, boutons) est choisie par `haptic_readable_text_color()` entre sombre et blanc selon le meilleur contraste, et passe au noir quand aucun des deux n'atteint 4,5:1 : le niveau AA est atteint quelle que soit la couleur choisie. Nouvelles fonctions `haptic_relative_luminance()` et `haptic_contrast_ratio()`.

## [7.6.2] - 2026-10-25

### Corrigé
- Adresse de connexion personnalisée : un visiteur non connecté qui ouvrait une adresse de l'administration (`/wp-admin/…`) voyait une page « Page introuvable » sans mise en forme, et non la page 404 du thème comme pour `wp-login.php` ou une adresse inexistante. `haptic_sec_not_found()` affiche désormais le modèle 404 du thème dans tous les cas (thèmes classiques et thèmes blocs, styles des blocs compris, `index.php` si le thème n'a pas de `404.php`, filtre `template_include` appliqué pour la page 404 d'Optimizr Pro). La page minimale ne sert plus qu'en dernier recours.

## [7.6.1] - 2026-10-24

### Modifié
- Le plugin s'appelle désormais **Optimizr** (en-tête `Plugin Name` et titre de `readme.txt`), au lieu de « Haptic Optimizr » : WordPress.org déduit le slug du nom, et `optimizr` doit rester égal au domaine de traduction et au dossier des sites déjà installés. Réglages, préfixe `haptic_`, dossier et slug inchangés.
- Script de l'écran « Sécuriser votre compte » (carte cochée, « Plus tard » qui devient « Continuer ») sorti du code PHP, où il tenait sur une ligne, dans `js/optimizr-welcome.js`.
- Script de Tarteaucitron enregistré sous le nom `optimizr-tarteaucitron` au lieu de `tarteaucitron`, pour ne pas entrer en conflit avec une autre extension qui le charge.

### Corrigé
- Plugin Check ne signale plus d'erreur : les valeurs de `$_SERVER` du cache des pages (`functions/cache-serve.php`, qui tourne avant WordPress) sont lues par une seule fonction, `haptic_cache_server_var()`, qui écarte celles contenant des caractères de contrôle ; lecture de la page en cache et appels volontaires de hooks de WordPress (`wp_login_failed`, `login_footer`, `login_redirect`, `set_time_limit()` de l'audit) justifiés dans le code.
- Cache des pages : une page dont l'adresse contient une apostrophe n'était jamais mise en cache, car WordPress échappe `$_SERVER` après le passage d'`advanced-cache.php` : l'adresse lue à l'écriture (`/l\'ete/`) ne correspondait plus à celle lue pour servir la page. La même valeur est désormais utilisée des deux côtés.
- `readme.txt` : l'assistant se relance depuis **Optimizr › Wizard** (et non « Assistant »).

## [7.6.0] - 2026-10-21

### Ajouté
- **Module Cache** et son onglet (`functions/cache.php`). Toutes ses fonctions sont désactivées par défaut ; sur un site existant, le module lui-même s'active depuis le tableau de bord.
- **Cache des pages** : les pages vues par les visiteurs non connectés sont enregistrées en HTML dans `wp-content/cache/optimizr/pages/<hôte>/` et servies avant le chargement de WordPress par `wp-content/advanced-cache.php`, qui charge `functions/cache-serve.php` (PHP natif). Optimizr ajoute `define('WP_CACHE', true);` en haut de `wp-config.php`, ou indique la ligne à ajouter s'il ne peut pas l'écrire. Réglages : durée de vie (10 heures par défaut), adresses exclues avec jokers, cache séparé pour les téléphones. Écartés : personnes connectées et cookies de commentaire, de contenu protégé ou de panier, recherches, aperçus, flux, pages introuvables, panier, commande et compte WooCommerce, adresses avec paramètres autres que ceux de suivi, réponses autres que 200 ou qui posent un cookie, constante `DONOTCACHEPAGE`. Seul l'hôte du site est servi et mis en cache (un en-tête Host forgé n'écrit aucun fichier). Pages écrites dans un fichier temporaire puis renommées ; en-tête `X-Optimizr-Cache: HIT` et réponse 304 si la page n'a pas changé.
- **Vidage automatique**, en fin de requête et une seule fois : la page d'un contenu modifié, l'accueil et ses archives ; tout le cache quand un contenu est publié, dépublié ou change d'adresse, et après un changement de menu, de widget, de thème, de catégorie, d'extension, de réglage de WordPress ou d'Optimizr, ou un import de réglages. Commentaires : la page du contenu commenté.
- **Vidage manuel** : bouton « Vider tout le cache » de l'onglet et menu « Cache » de la barre d'administration (tout le site, ou la page affichée), réservés à `manage_options` (filtre `haptic_cache_purge_capability`).
- **Cache navigateur et compression** : bloc « Optimizr Cache » du `.htaccess` (Apache, LiteSpeed), images, polices et vidéos gardées un an, styles et scripts un mois, compression gzip en option. Sur un autre serveur, l'onglet affiche les règles Nginx à transmettre à l'hébergeur.
- **Détection des conflits** (`functions/cache-files.php`) : une autre extension de cache active (WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache, Cache Enabler, Hummingbird, SiteGround Speed Optimizer, Breeze, WP-Optimize, FlyingPress, Swift Performance, Comet Cache) ou un `advanced-cache.php` étranger empêche d'activer le cache des pages. Le cache de l'hébergeur (WP Engine, Kinsta, Pantheon, GoDaddy, Pressable, Varnish) est signalé, et vidé avec celui d'Optimizr pour WP Engine, Pantheon et l'extension Nginx Helper. Pas de cache des pages sur un multisite.
- Panneau « État du cache » (pages en cache, poids, dernier vidage), pastille du module et ligne « Cache des pages » dans l'état du site du tableau de bord. Module « Cache » dans l'étape Modules de l'assistant.
- Points d'extension : action `haptic_cache_purged` (utilisée par Optimizr Pro 1.8.0 pour le préchargement et Cloudflare), action `haptic_cache_aside`, filtres `haptic_cache_skip_request`, `haptic_cache_post_urls`, `haptic_cache_bypass_cookies`, `haptic_cache_ignored_query_args`, `haptic_cache_conflicting_plugins`, `haptic_cache_host_caches`, `haptic_cache_internal_options` ; fonctions `haptic_cache_purge_all()`, `haptic_cache_purge_urls()` et `haptic_cache_page_active()`. Icône `zap` dans `haptic_icon()`.
- La désactivation du réglage, du module ou du plugin, et la suppression du plugin, retirent `advanced-cache.php`, la ligne de `wp-config.php`, le bloc du `.htaccess` et les pages en cache. Les fichiers PHP écrits sont retirés d'OPcache. Après une mise à jour ou un déplacement du plugin, `advanced-cache.php` est réécrit à la visite suivante de l'administration.

## [7.5.0] - 2026-10-21

### Ajouté
- **Audit Lighthouse** (nouvel onglet Audit, `functions/audit.php`, `js/optimizr-audit.js`) : scores de performance, d'accessibilité, de bonnes pratiques et de SEO d'une page du site, sur mobile ou sur ordinateur, mesurés par Google avec l'API PageSpeed Insights. Affiche les mesures de laboratoire (FCP, LCP, TBT, CLS, Speed Index), les données réelles des visiteurs de Chrome (Chrome UX Report, LCP, INP, CLS) quand Google en a, et les 10 principaux points à améliorer de chaque catégorie avec leur explication, dans la langue de l'administration. Lien vers le rapport complet sur PageSpeed Insights.
- Le dernier audit de chaque appareil (`haptic_audit_results`) et l'historique des 30 derniers (`haptic_audit_history`) sont conservés : ni exportés, ni importés, effacés avec les journaux à la suppression du plugin.
- Clé API Google (`haptic_audit_api_key`), gratuite, réglée dans l'onglet : Google ne sert plus les requêtes sans clé. Exportée seulement avec « Inclure les clés API », comme les autres clés.
- Rien n'est envoyé à Google sans clic sur « Lancer l'audit » ; un seul audit à la fois. Un site à une adresse locale ou privée est signalé, puisque Google ne peut pas l'ouvrir. Service externe déclaré dans le readme.txt.

## [7.4.1] - 2026-10-15

### Corrigé
- **Formats d'image** : une image JPEG en CMJN (préparée pour l'impression, avec son profil ICC) convertie en WebP ou AVIF avait des couleurs très différentes de l'original (verts saturés, jaunes délavés). WebP et AVIF n'acceptent que le RVB, et ImageMagick y passait l'image sans tenir compte de son profil. Avec Imagick, l'image est maintenant convertie en sRGB avec son profil, comme le fait le navigateur pour afficher le JPEG (profil sRGB compact sous licence CC0 dans `assets/icc/`) ; avec GD, qui ignore les profils ICC, une image CMJN garde son format JPEG. Éditeurs `Haptic_Image_Editor_Imagick` et `Haptic_Image_Editor_GD` (`functions/class-haptic-image-editors.php`), utilisés à la place de ceux de WordPress tant que la conversion est active.
- Les images déjà converties avec ce défaut ne sont pas refaites : supprimez-les et importez-les de nouveau.

## [7.4.0] - 2026-10-15

### Ajouté
- **Formats d'image** (onglet Contenu, `functions/images.php`) : les images JPEG et PNG importées sont enregistrées en WebP ou en AVIF, taille « full » et tailles intermédiaires comprises, par l'éditeur d'images de WordPress (GD ou Imagick), sans service externe. Réglages : interrupteur « Convertir les images importées » (désactivé par défaut), format, qualité (82 par défaut) et formats d'origine à convertir. Le fichier d'origine est conservé et référencé comme image originale (`original_image`). Les GIF ne sont jamais convertis (animations) ; les photos HEIC, que WordPress convertit en JPEG, passent directement au format choisi.
- AVIF proposé seulement si le serveur sait l'écrire (WordPress 6.5+, GD ou Imagick compilés avec AVIF) ; s'il ne le peut plus, les images passent en WebP. Sans WebP ni AVIF, la carte le signale et les images sont importées telles quelles.
- Avant WordPress 6.7, qui ne convertit que les images redimensionnées ou pivotées, Optimizr enregistre lui-même la copie convertie de la taille « full ».
- Fonctions `haptic_image_target_mime()`, `haptic_image_source_mimes()`, `haptic_image_format_supported()` et `haptic_image_convert_attachment_file()`, utilisées par Optimizr Pro 1.7.0 pour convertir la médiathèque existante.

### Modifié
- Le module « Images à la une » devient « Images » : il regroupe les images à la une et les formats d'image. Son état (activé ou non) est conservé.

## [7.3.0] - 2026-10-15

### Ajouté
- **Assistant de configuration, étape Sécurité** : nouvelle section « Connexion des comptes » avec la double authentification (recommandée, cochée par défaut tant que le réglage n'a jamais été enregistré). Optimizr Pro 1.6.0 y ajoute les passkeys. Filtre `haptic_onboarding_account_toggles`, enregistré comme les protections (`haptic_sec_<clé>`).
- **Assistant de configuration, compte de l'administrateur** : après « Appliquer les réglages », avec le module Sécurité, un écran (`step=account`) fait mettre en place la double authentification (et la passkey d'Optimizr Pro) de son propre compte, avec les mêmes étapes que l'écran proposé à la connexion, puis mène à l'écran final. « Plus tard » y passe directement ; rien à proposer (déjà en place, protections désactivées) : l'écran final s'affiche aussitôt.
- Fonctions `haptic_sec_account_steps()` (étapes d'un compte, sans la condition « Plus tard »), `haptic_sec_account_enqueue()` et `haptic_sec_account_cards()` : les étapes de l'écran « Sécurisez votre compte » réutilisables ailleurs.
- Icônes `phone` et `key` dans `haptic_icon()`.

### Corrigé
- Écran « Sécurisez votre compte » (`wp-login.php?action=haptic_secure`) : la feuille de la page de connexion de WordPress, chargée après les styles de l'écran, en annulait les marges et les espacements (`.login *`) et faisait flotter les boutons principaux ; cartes collées, bouton « Ajouter une passkey » à côté du titre suivant, « Plus tard » décalé. Les styles passent dans `css/optimizr-welcome.css`, chargée après celle de la page de connexion et entièrement préfixée par `.optimizr-welcome`.

### Modifié
- Écran « Sécurisez votre compte » repensé : en-tête avec le prénom du compte, une carte par étape avec son icône et son badge (« Obligatoire »), choix de la méthode sous forme de cartes avec icône, QR code centré et clé lisible, champ du code et bouton « Activer » sur une ligne, codes de secours en grille, étape cochée en vert une fois faite, « Continuer » mis en avant quand tout est fait. Mise en page adaptée aux petits écrans.
- Choix de la méthode dans le profil : même icône par méthode.

## [7.2.0] - 2026-10-15

### Ajouté
- **Code par e-mail** : deuxième méthode de double authentification, au choix du compte à côté de l'application. Code à 6 chiffres envoyé avec `wp_mail`, valable 10 minutes, enregistré haché ; à la connexion, 3 envois au plus (30 secondes entre deux) et seul le dernier code est valable. Le code part toujours à l'adresse vérifiée à la mise en place, même si l'e-mail du compte change ensuite. Si l'envoi échoue, la page du code le dit et propose les codes de secours.
- Réglages « Code par e-mail » (activé par défaut) et « Application pour les rôles obligatoires » : un compte dont l'e-mail n'est plus permis est invité à choisir une autre méthode à sa prochaine connexion.
- **Écran « Sécurisez votre compte »** (`functions/security-welcome.php`, `wp-login.php?action=haptic_secure`) : juste après la connexion qui suit l'activation, la mise en place en quelques secondes (choix de la méthode, QR code ou code reçu, codes de secours), dans le style de la page de connexion, puis la page demandée. « Plus tard » le repousse d'une semaine ; un rôle obligatoire ne peut que la mettre en place, et son administration le ramène sur cet écran. Proposé aux comptes qui peuvent écrire des contenus. Optimizr Pro 1.5.0 y ajoute la passkey.
- Changement de méthode depuis le profil (l'actuelle reste valable jusqu'à l'activation de la nouvelle).
- Points d'extension : filtres `haptic_sec_welcome_user` et `haptic_sec_welcome_steps`, actions `haptic_sec_welcome_enqueue` et `haptic_sec_welcome_content`, fonction `haptic_sec_recent_auth()` et requête `haptic_sec_confirm` (session récente ou confirmée).
- Journal : activation et changement de méthode.

### Sécurité
- Nouveaux codes de secours : un code est désormais demandé. Avant, une session volée suffisait à en générer, puis à désactiver la double authentification avec l'un d'eux.
- Codes faux : 5 au plus par quart d'heure et par compte, toutes saisies confondues (connexion et profil), même sans la limitation des tentatives. Avant, se reconnecter avec le mot de passe redonnait 5 essais à chaque fois.
- Codes par e-mail : 10 envois au plus par heure et par compte, pour qu'un mot de passe connu ne permette pas d'inonder la boîte mail.
- Un administrateur ne peut plus réinitialiser sa propre double authentification sans code (la réinitialisation reste possible pour les autres comptes).
- Mot de passe d'application d'un compte protégé : création possible seulement dans les 10 minutes qui suivent la connexion (ou une confirmation), depuis le profil comme depuis l'écran « Autoriser l'application », pour qu'une session volée ne permette pas d'obtenir un accès à l'API sans code.

### Modifié
- Compte d'un rôle obligatoire sans double authentification : l'administration redirige vers l'écran « Sécurisez votre compte » (et non plus vers le profil), avec retour à la page demandée.

## [7.1.0] - 2026-10-14

### Ajouté
- **Double authentification** (onglet Sécurité, carte « Double authentification ») : après le mot de passe, un code à 6 chiffres donné par une application d'authentification (Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden…). Codes TOTP (RFC 6238) calculés par le plugin, sans service externe ; le QR code de mise en place est dessiné dans le navigateur par la bibliothèque qrcode-generator (MIT), fournie dans `assets/qrcode-generator/`.
- Mise en place depuis le profil de chaque compte (`functions/security-2fa-profile.php`, `js/optimizr-2fa.js`) : QR code ou clé à saisir, vérification d'un premier code, 10 codes de secours à usage unique (affichés une fois, enregistrés hachés, régénérables). Désactivation avec un code ; réinitialisation par un administrateur depuis le profil d'un autre compte (téléphone perdu).
- Réglage « Obligatoire pour » : les rôles choisis doivent la mettre en place ; en attendant, leur administration se limite à leur profil.
- Page de saisie du code sur l'adresse de connexion (personnalisée ou `wp-login.php`), quel que soit le formulaire de connexion utilisé. Le jeton de la connexion en attente est dans un cookie, jamais dans l'adresse ; les sessions ouvertes par le mot de passe sont annulées tant que le code n'est pas validé. Un code n'est jamais accepté deux fois, 5 codes faux renvoient au mot de passe, et chaque code faux compte dans la limitation des tentatives.
- XML-RPC avec le mot de passe d'un compte protégé : refusé (les mots de passe d'application restent acceptés).
- Constante de secours `OPTIMIZR_DISABLE_2FA` (wp-config.php) pour se connecter avec le seul mot de passe.
- Journal : trop de codes faux, désactivation, réinitialisation. Bilan : « Double authentification ».
- Points d'extension : action `haptic_sec_login` (connexion terminée, après le code s'il y en a un), filtres `haptic_sec_two_factor_bypass` (connexion qui vaut déjà deux facteurs, comme une passkey d'Optimizr Pro 1.4.0) et `haptic_sec_log_labels` (libellés des évènements du journal).

### Modifié
- L'alerte de connexion d'un administrateur et la remise à zéro du compteur d'échecs ont lieu une fois la connexion terminée (action `haptic_sec_login`) : après le code de double authentification pour un compte protégé, et non plus dès le mot de passe.
- Suppression du plugin avec ses réglages : la double authentification des comptes (`haptic_2fa*`) et les passkeys d'Optimizr Pro (`haptic_passkeys`, `haptic_passkey_handle`) sont aussi effacées.

## [7.0.0] - 2026-10-14

### Retiré
- **Module Types de contenu** (type « Évènements » prêt à l'emploi et types personnalisés créés depuis l'administration) : il passe dans Optimizr Pro 1.3.0, qui n'a plus besoin d'ACF Pro pour les champs des évènements. Optimizr ne contient plus `functions/cpts.php` ni ses fonctions `haptic_*` (enregistrement des types, réglages, champs ACF).
- ACF Pro n'est plus mentionné dans les prérequis.

### Modifié
- Les types de contenu déjà créés sont conservés (options `haptic_custom_cpts` et `haptic_options_settings`, état activé ou non du module `cpts`), ainsi que leurs contenus : Optimizr Pro 1.3.0 les reprend tels quels, avec les mêmes identifiants et adresses. Sans Optimizr Pro, ces types ne sont plus enregistrés et leurs contenus ne sont plus accessibles, sans être effacés.
- Version minimale d'Optimizr Pro (`HAPTIC_OPTIMIZR_PRO_MIN_VERSION`) portée à 1.3.0 : un Optimizr Pro plus ancien affiche un avertissement demandant sa mise à jour. La mise en pause des redirections reste limitée à Optimizr Pro antérieur à 1.2.0.
- Onglet Contenu : il ne contient plus que le module Images à la une, et Optimizr Pro y ajoute les types de contenu. La liste des fonctionnalités Pro du tableau de bord mentionne les types de contenu.

## [6.0.1] - 2026-10-13

### Retiré
- `haptic_admin-page.php` et sa fonction `haptic_options_admin_init()` : les actions `haptic_before_admin_init`, `haptic_add_options_tabs` et `haptic_after_admin_init`, déclenchées sur `admin_init`, ne sont plus émises. Aucun code d'Optimizr ni d'Optimizr Pro ne s'y branchait ; les onglets s'ajoutent par le filtre `haptic_admin_tabs`.

## [6.0.0] - 2026-10-13

### Retiré
- **Mises à jour depuis GitHub** : Plugin Update Checker (`assets/plugin-update-checker/`) et `functions/updates.php` sont supprimés. La version GitHub ne lit plus la branche `stable` et ne contacte plus `cdn.alexandrevergnaud.fr/opti/token.php` pour obtenir un jeton. Les mises à jour viennent de WordPress.org, y compris pour les sites installés depuis GitHub (dossier `optimizr`), une fois le plugin publié sous ce slug ; d'ici là, ils se mettent à jour à la main.
- Constante `OPTIMIZR_GITHUB_TOKEN` et fonctions `haptic_initialize_updater()`, `haptic_update_channel()` et `haptic_get_github_token()`.

### Modifié
- À la mise à jour, le jeton GitHub mis en cache et l'état enregistré par Plugin Update Checker (d'Optimizr et d'Optimizr Pro) sont effacés.
- `bin/build-wporg.sh` ne s'arrête plus en silence quand aucun fichier ne contient de ligne `build:github-only` (cas depuis ce retrait).

## [5.0.1] - 2026-10-12

### Corrigé
- Erreurs de Plugin Check sur la version WordPress.org : `readme.txt` indique « Tested up to: 7.1 » (la version actuelle de WordPress), et le zip construit par `bin/build-wporg.sh` ne contient plus le dossier `.github` de Tarteaucitron. Le script échoue désormais s'il reste un fichier caché dans le zip.
- Avertissements de Plugin Check : paramètres d'URL `tab` et `step` passés par `wp_unslash()` et `sanitize_key()`, et justification en commentaire (`phpcs:ignore`) des cas voulus : requêtes SQL ponctuelles sur les options et tables du plugin (export, migration, désinstallation, nettoyage des métadonnées SEO), paramètres d'URL seulement lus pour l'affichage, fichier d'import, chargement des traductions fournies. Aucun changement de comportement.

## [5.0.0] - 2026-10-12

### Retiré
- **Module Redirections** (redirections et journal des pages introuvables) : il passe dans Optimizr Pro 1.2.0, sans la limite de 5 redirections. Optimizr ne contient plus ni onglet Redirections ni redirection appliquée aux visiteurs.
- Points d'extension `haptic_redirects_*` (limite, recherche, validation, textes et contenu de l'onglet) et fonctions `haptic_redirects_*`, désormais internes à Optimizr Pro.

### Modifié
- Les redirections et le journal des 404 déjà enregistrés sont conservés (tables `{prefix}optimizr_redirects` et `{prefix}optimizr_404`, réglage du journal) : Optimizr Pro 1.2.0 les reprend tels quels, et l'état activé ou non du module est gardé. La suppression d'Optimizr les efface toujours selon les choix de l'onglet Outils.
- Optimizr Pro antérieur à 1.2.0 : ses compléments de redirection sont mis en pause (ils s'appuyaient sur le module retiré) et un avertissement demande sa mise à jour.
- Le comportement du formulaire de redirection (type 410 sans destination) passe dans le script d'Optimizr Pro.

## [4.0.4] - 2026-10-06

### Retiré
- Choix du canal de mise à jour (Stable, Beta, Développement) dans la carte « Mises à jour » du tableau de bord. Tous les sites suivent désormais la branche `stable` ; à la mise à jour, le canal enregistré est supprimé. Un site qui suivait Beta ou Développement ne reçoit plus que les versions publiées sur `stable`. Optimizr Pro suit le même canal.

### Ajouté
- En-tête `Plugin URI` : la page Extensions affiche un lien vers le site d'Optimizr (https://optimizr.hapticstudio.fr/).

## [4.0.3] - 2026-10-06

### Modifié
- Le lien « En savoir plus sur Optimizr Pro » du tableau de bord mène à la page des offres du site d'Optimizr (optimizr.hapticstudio.fr/tarifs/), où Optimizr Pro s'achète avec sa licence.

## [4.0.2] - 2026-10-06

### Modifié
- Logo Optimizr dans l'en-tête de la page du plugin, dans l'assistant de configuration et comme icône du menu d'administration (à la place du « O » et de l'icône générique). L'icône du menu prend la couleur du jeu de couleurs de l'administration.

### Ajouté
- Dossier `.wordpress-org/` : icônes et bannières de la fiche WordPress.org (non incluses dans le plugin).

## [4.0.1] - 2026-10-06

### Retiré
- Boutons de mise à jour d'Optimizr : « Vérifier les mises à jour » et « Mettre à jour » en haut de la page du plugin, et le lien « Vérifier les mises à jour » de la page Extensions. WordPress continue de détecter les nouvelles versions et les propose dans **Extensions** et **Tableau de bord › Mises à jour**. Le choix du canal reste dans la carte « Mises à jour ».

## [4.0.0] - 2026-10-05

### Modifié (incompatible)
- Optimizr devient la version gratuite. Les fonctionnalités avancées passent dans l'extension séparée **Optimizr Pro** (dépôt `Corteo/Optimizr-pro`), qui nécessite Optimizr 4.0.0 et reprend les réglages existants sans migration. Sur un site mis à jour sans Optimizr Pro, ces fonctionnalités s'arrêtent mais leurs réglages restent en base : installer Optimizr Pro les rétablit tels quels.
- Cookies & RGPD : services limités à Google Analytics 4 et Google Tag Manager ; les autres services et les couleurs du bandeau passent dans Optimizr Pro (couleurs par défaut sans Pro).
- SEO : balises titre, description et OpenGraph et metabox de l'éditeur. Sitemap XML, plan du site, robots.txt, options d'indexation, codes de vérification, IndexNow et données structurées passent dans Optimizr Pro ; à la mise à jour sans Pro, le fichier sitemap.xml devenu obsolète est supprimé.
- Redirections : journal des pages introuvables et 5 redirections au plus. Les redirections déjà enregistrées au-delà continuent de fonctionner, mais aucune ne peut être ajoutée. Jokers, import/export CSV, redirection automatique des contenus déplacés et liens cassés passent dans Optimizr Pro.
- Sécurité : adresse de connexion, limitation des tentatives, messages neutres et alerte de connexion. Discrétion, fonctions inutilisées, en-têtes de sécurité et bilan passent dans Optimizr Pro.

### Retiré
- Modules SEO local, Pages spéciales (page 404, mode maintenance) et Assistant IA, désormais dans Optimizr Pro. Un site en maintenance rouvre à la mise à jour tant qu'Optimizr Pro n'est pas installé.

### Ajouté
- Points d'extension pour Optimizr Pro et toute autre extension : action `haptic_loaded`, filtres et actions des modules, onglets, cookies, metabox SEO, redirections, sécurité et assistant de configuration (liste dans le README).
- Tableau de bord : carte de présentation d'Optimizr Pro quand il n'est pas installé.

## [3.0.0] - 2026-10-05

### Modifié (incompatible)
- Préfixe `hpt_` remplacé par `haptic_` partout (fonctions, options, métadonnées, hooks, actions AJAX, nonces, tâches planifiées, constantes `HAPTIC_*`, classes CSS `haptic-*`), comme le demande WordPress.org (préfixe d'au moins 4 caractères). Les anciennes fonctions `haptic_GPT_*` suivent la même règle. Le code personnalisé qui utilise un filtre ou une action `hpt_*` doit être renommé. Les actions `before_hpt_options_*` et `after_hpt_options_*` deviennent `haptic_before_*` et `haptic_after_*`, `add_hpt_options_tabs` devient `haptic_add_options_tabs`. Le fichier principal `hpt_options.php` devient `optimizr.php` : sur les sites mis à jour, WordPress désactive le plugin, qu'il faut réactiver (réglages conservés). Les sites en 2.x ne voient pas cette mise à jour dans leur outil de mise à jour (il lit `hpt_options.php` sur GitHub) : elle s'installe manuellement.
- Migration automatique au premier chargement après la mise à jour : réglages, SEO des contenus, adresses IP connues des comptes et tâches planifiées passent sous le nouveau nom, sans rien perdre. Un export de réglages fait en 2.x s'importe toujours. Les anciennes adresses de la page d'options (`page=hpt-options`) redirigent vers la nouvelle.
- Plugin traduisible : textes sources en anglais (domaine `optimizr`), traduction française fournie dans `languages/` (PHP et scripts). Un site en français s'affiche comme avant ; un site dans une autre langue est en anglais. Les invites envoyées à l'IA suivent la langue de l'utilisateur.

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
