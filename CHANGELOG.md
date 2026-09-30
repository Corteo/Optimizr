# Historique des versions

Toutes les évolutions notables de Haptic Optimizr sont consignées ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et la numérotation suit le [Semantic Versioning](https://semver.org/lang/fr/).

## [Non publié]

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
