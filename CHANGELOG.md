# Historique des versions

Toutes les évolutions notables de Haptic Optimizr sont consignées ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et la numérotation suit le [Semantic Versioning](https://semver.org/lang/fr/).

## [Non publié]

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
