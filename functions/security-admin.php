<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Onglet Sécurité : réglages du module (functions/security.php).
 */

add_action('admin_init', 'hpt_sec_register_settings');

function hpt_sec_register_settings()
{
    $group = hpt_settings_group('security');
    $page = hpt_settings_page('security');

    $toggles = ['limit_login', 'proxy_cloudflare', 'generic_errors', 'login_alert', 'hide_version', 'block_enumeration', 'block_author_pages',
        'disable_xmlrpc', 'disable_app_passwords', 'disable_file_edit', 'uploads_php', 'headers', 'hsts'];
    foreach ($toggles as $toggle) {
        register_setting($group, 'hpt_sec_' . $toggle, ['sanitize_callback' => 'absint']);
    }
    register_setting($group, 'hpt_sec_login_slug', ['sanitize_callback' => 'hpt_sec_sanitize_login_slug']);
    register_setting($group, 'hpt_sec_login_max', ['sanitize_callback' => function ($v) {
        return min(20, max(2, absint($v) ?: 5));
    }]);
    register_setting($group, 'hpt_sec_login_lockout', ['sanitize_callback' => function ($v) {
        return min(1440, max(1, absint($v) ?: 15));
    }]);
    register_setting($group, 'hpt_sec_login_whitelist', ['sanitize_callback' => function ($v) {
        $ips = array_filter(array_map('trim', preg_split('/\R/', (string) $v)), function ($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP);
        });
        return implode("\n", array_unique($ips));
    }]);
    register_setting($group, 'hpt_sec_trusted_proxies', ['sanitize_callback' => function ($v) {
        $ranges = array_filter(array_map('trim', preg_split('/\R/', (string) $v)), 'hpt_sec_is_ip_range');
        return implode("\n", array_unique($ranges));
    }]);

    /* Connexion */
    add_settings_section('hpt_sec_login_section', 'Connexion', function () {
        echo '<p>Protège la page de connexion contre les robots qui testent des mots de passe en boucle.</p>';
    }, $page);

    add_settings_field('hpt_sec_login_slug', 'Adresse de connexion', 'hpt_sec_login_slug_callback', $page, 'hpt_sec_login_section', [
        'label_for' => 'hpt_sec_login_slug',
        'layout' => 'stacked',
    ]);
    add_settings_field('hpt_sec_limit_login', 'Limiter les tentatives', 'hpt_sec_limit_login_callback', $page, 'hpt_sec_login_section', [
        'description' => 'Verrouille temporairement une adresse IP après plusieurs mots de passe erronés.',
    ]);
    add_settings_field('hpt_sec_trusted_proxies', 'Proxys de confiance', 'hpt_sec_proxies_callback', $page, 'hpt_sec_login_section', [
        'description' => 'Derrière Cloudflare, un CDN ou un répartiteur de charge, tous les visiteurs semblent venir de l\'adresse du proxy : un verrouillage bloquerait tout le monde.',
    ]);
    hpt_sec_add_toggle('generic_errors', 'Messages d\'erreur neutres', 'Affiche « Identifiant ou mot de passe incorrect » sans préciser lequel : un robot ne peut plus deviner les identifiants existants.', 'hpt_sec_login_section');
    hpt_sec_add_toggle('login_alert', 'Alerte de connexion', 'Envoie un email à l\'administrateur concerné lorsqu\'il se connecte depuis une adresse IP inconnue.', 'hpt_sec_login_section');

    /* Discrétion */
    add_settings_section('hpt_sec_privacy_section', 'Discrétion', function () {
        echo '<p>Réduit les informations que le site donne aux robots qui cherchent des failles connues.</p>';
    }, $page);
    hpt_sec_add_toggle('hide_version', 'Masquer la version de WordPress', 'Retire la balise « generator », la version des flux RSS et des fichiers (?ver=), et l\'en-tête X-Powered-By de PHP.', 'hpt_sec_privacy_section');
    hpt_sec_add_toggle('block_enumeration', 'Bloquer la liste des comptes', 'Empêche de découvrir les identifiants des comptes par ?author=1, par l\'API REST et par les intégrations oEmbed.', 'hpt_sec_privacy_section');
    hpt_sec_add_toggle('block_author_pages', 'Masquer les pages auteur', 'Les pages /author/identifiant/ affichent une page introuvable aux visiteurs (avec l\'option précédente).', 'hpt_sec_privacy_section');

    /* Surface d'attaque */
    add_settings_section('hpt_sec_surface_section', 'Fonctions inutilisées', function () {
        echo '<p>Désactive des portes d\'entrée dont la plupart des sites vitrines n\'ont pas besoin.</p>';
    }, $page);
    hpt_sec_add_toggle('disable_xmlrpc', 'Désactiver XML-RPC', class_exists('Jetpack')
        ? 'Attention : Jetpack est actif et a besoin de XML-RPC.'
        : 'Ancienne interface, cible fréquente des attaques par mot de passe. Nécessaire uniquement pour Jetpack et quelques applications mobiles.', 'hpt_sec_surface_section');
    hpt_sec_add_toggle('disable_app_passwords', 'Désactiver les mots de passe d\'application', 'Ces mots de passe permettent de se connecter à l\'API sans la double vérification habituelle. Inutiles sans application externe connectée.', 'hpt_sec_surface_section');
    add_settings_field('hpt_sec_disable_file_edit', 'Désactiver l\'éditeur de fichiers', 'hpt_sec_file_edit_callback', $page, 'hpt_sec_surface_section', [
        'description' => 'Retire l\'éditeur de code des thèmes et extensions : un compte piraté ne peut plus y injecter de code.',
    ]);
    add_settings_field('hpt_sec_uploads_php', 'Bloquer le PHP dans les médias', 'hpt_sec_uploads_callback', $page, 'hpt_sec_surface_section', [
        'description' => 'Empêche l\'exécution d\'un script PHP déposé dans le dossier des médias par une faille.',
    ]);

    /* En-têtes */
    add_settings_section('hpt_sec_headers_section', 'En-têtes de sécurité', function () {
        echo '<p>Consignes envoyées aux navigateurs pour limiter les détournements du site.</p>';
    }, $page);
    hpt_sec_add_toggle('headers', 'En-têtes recommandés', 'X-Content-Type-Options, X-Frame-Options (le site ne peut plus être affiché dans un cadre d\'un autre site), Referrer-Policy et Permissions-Policy.', 'hpt_sec_headers_section');
    add_settings_field('hpt_sec_hsts', 'Forcer HTTPS (HSTS)', 'hpt_sec_hsts_callback', $page, 'hpt_sec_headers_section', [
        'description' => 'Les navigateurs refuseront toute connexion non sécurisée au site pendant un an.',
    ]);
}

function hpt_sec_add_toggle($name, $label, $description, $section)
{
    add_settings_field('hpt_sec_' . $name, $label, function () use ($name, $label) {
        echo hpt_switch_input('hpt_sec_' . $name, hpt_sec_option($name), $label);
    }, hpt_settings_page('security'), $section, ['description' => $description]);
}

function hpt_sec_login_slug_callback()
{
    $slug = (string) get_option('hpt_sec_login_slug', '');
    $prefix = get_option('permalink_structure') ? home_url('/') : home_url('/') . '?';
    ?>
    <div class="optimizr-prefixed">
        <span class="optimizr-prefixed__prefix"><?php echo esc_html($prefix); ?></span>
        <input type="text" name="hpt_sec_login_slug" id="hpt_sec_login_slug" value="<?php echo esc_attr($slug); ?>" placeholder="laisser vide pour garder wp-login.php" spellcheck="false" autocomplete="off" />
    </div>
    <?php if (defined('OPTIMIZR_DISABLE_CUSTOM_LOGIN') && OPTIMIZR_DISABLE_CUSTOM_LOGIN) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo hpt_icon('alert', 18); ?><span>Désactivée par la constante <code>OPTIMIZR_DISABLE_CUSTOM_LOGIN</code> dans wp-config.php : la connexion se fait par wp-login.php.</span></div>
    <?php elseif (is_multisite()) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo hpt_icon('alert', 18); ?><span>Non disponible sur un réseau multisite.</span></div>
    <?php elseif ($slug !== '') : ?>
        <div class="optimizr-notice optimizr-notice--ok"><?php echo hpt_icon('check', 18); ?><span><strong>Connexion :</strong> <a href="<?php echo esc_url(hpt_sec_login_url()); ?>"><?php echo esc_html(hpt_sec_login_url()); ?></a> — wp-login.php et /wp-admin/ affichent une page introuvable aux visiteurs.</span></div>
    <?php endif; ?>
    <p class="optimizr-field__help">Ajoutez la nouvelle adresse à vos favoris : un email la rappelle à l'administrateur à chaque changement. En cas de blocage, ajoutez <code>define('OPTIMIZR_DISABLE_CUSTOM_LOGIN', true);</code> dans wp-config.php.</p>
    <?php
}

function hpt_sec_limit_login_callback()
{
    echo hpt_switch_input('hpt_sec_limit_login', hpt_sec_option('limit_login'), 'Limiter les tentatives de connexion');
    ?>
    <div class="optimizr-inline">
        <label class="optimizr-muted" for="hpt_sec_login_max">Verrouiller après</label>
        <input type="number" class="optimizr-input-small" name="hpt_sec_login_max" id="hpt_sec_login_max" min="2" max="20" value="<?php echo esc_attr(hpt_sec_option('login_max', 5)); ?>">
        <label class="optimizr-muted" for="hpt_sec_login_lockout">échecs, pendant</label>
        <input type="number" class="optimizr-input-small" name="hpt_sec_login_lockout" id="hpt_sec_login_lockout" min="1" max="1440" value="<?php echo esc_attr(hpt_sec_option('login_lockout', 15)); ?>">
        <span class="optimizr-muted">minutes</span>
    </div>
    <label for="hpt_sec_login_whitelist" class="optimizr-muted">Adresses IP jamais verrouillées (une par ligne)</label>
    <textarea name="hpt_sec_login_whitelist" id="hpt_sec_login_whitelist" rows="2" class="optimizr-code" spellcheck="false"><?php echo esc_textarea(hpt_sec_option('login_whitelist', '')); ?></textarea>
    <p class="optimizr-field__help">Votre adresse IP actuelle : <code><?php echo esc_html(hpt_sec_client_ip()); ?></code>. Ajoutez-la si elle est fixe, pour ne jamais vous bloquer vous-même.</p>
    <?php
}

function hpt_sec_proxies_callback()
{
    $remote = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '';
    $client = hpt_sec_client_ip();
    // En-têtes qui contiennent déjà l'adresse vue par le serveur : il les a lui-même traités (mod_remoteip, real_ip de nginx), rien à déclarer
    $forwarded = array_filter(array_map('trim', array_merge(
        [(string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '')],
        explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))
    )));
    $proxied = $forwarded && !in_array($remote, $forwarded, true);
    ?>
    <div class="optimizr-check-row">
        <?php echo hpt_switch_input('hpt_sec_proxy_cloudflare', hpt_sec_option('proxy_cloudflare'), 'Site derrière Cloudflare'); ?>
        <span><strong>Site derrière Cloudflare</strong><br><span class="optimizr-muted">Adresse du visiteur lue dans l'en-tête CF-Connecting-IP, pour les seules requêtes venant des serveurs de Cloudflare.</span></span>
    </div>
    <label for="hpt_sec_trusted_proxies" class="optimizr-muted">Autres proxys : adresses IP ou plages CIDR, une par ligne</label>
    <textarea name="hpt_sec_trusted_proxies" id="hpt_sec_trusted_proxies" rows="2" class="optimizr-code" spellcheck="false" placeholder="10.0.0.0/8"><?php echo esc_textarea(hpt_sec_option('trusted_proxies', '')); ?></textarea>
    <p class="optimizr-field__help">Pour une requête venant de ces adresses, celle du visiteur est lue dans l'en-tête X-Forwarded-For. N'ajoutez que les adresses de vos proxys (hébergeur, CDN, répartiteur de charge) : une adresse déclarée peut transmettre n'importe quelle adresse de visiteur. Laissez vide si le site n'est derrière aucun proxy.</p>
    <?php if ($remote !== '' && $client !== $remote) : ?>
        <div class="optimizr-notice optimizr-notice--ok"><?php echo hpt_icon('check', 18); ?><span>Votre requête passe par le proxy <code><?php echo esc_html($remote); ?></code> : votre adresse retenue est <code><?php echo esc_html($client); ?></code>.</span></div>
    <?php elseif ($remote !== '' && hpt_sec_ip_in_ranges($remote, hpt_sec_trusted_proxies())) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo hpt_icon('alert', 18); ?><span>Votre requête vient du proxy déclaré <code><?php echo esc_html($remote); ?></code>, mais il ne transmet aucune adresse de visiteur lisible : c'est la sienne qui est retenue.</span></div>
    <?php elseif ($proxied) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo hpt_icon('alert', 18); ?><span>Votre requête semble passer par un proxy (adresse vue par le serveur : <code><?php echo esc_html($remote); ?></code>). S'il est le vôtre, déclarez-le ici.</span></div>
    <?php endif; ?>
    <?php
}

function hpt_sec_file_edit_callback()
{
    if (defined('DISALLOW_FILE_EDIT') && !hpt_sec_option('disable_file_edit')) {
        echo '<p class="optimizr-muted">Déjà ' . (DISALLOW_FILE_EDIT ? 'désactivé' : 'réglé') . ' par la constante <code>DISALLOW_FILE_EDIT</code> dans wp-config.php.</p>';
        return;
    }
    echo hpt_switch_input('hpt_sec_disable_file_edit', hpt_sec_option('disable_file_edit'), 'Désactiver l\'éditeur de fichiers');
}

function hpt_sec_uploads_callback()
{
    global $is_apache, $is_nginx;
    echo hpt_switch_input('hpt_sec_uploads_php', hpt_sec_option('uploads_php'), 'Bloquer le PHP dans les médias');

    if (hpt_sec_option('uploads_php')) {
        echo hpt_sec_uploads_rule_present()
            ? '<p class="optimizr-field__help">Règle présente dans <code>wp-content/uploads/.htaccess</code>.</p>'
            : '<p class="optimizr-field__help">Impossible d\'écrire <code>wp-content/uploads/.htaccess</code> : vérifiez les droits du dossier.</p>';
    }
    if (!empty($is_nginx) || empty($is_apache)) {
        echo '<p class="optimizr-field__help">Ce serveur n\'est pas Apache : la règle .htaccess est sans effet. Demandez à l\'hébergeur de bloquer l\'exécution de PHP dans <code>wp-content/uploads</code>.</p>';
    }
}

function hpt_sec_hsts_callback()
{
    if (!is_ssl() && strpos(home_url(), 'https://') !== 0) {
        echo '<p class="optimizr-muted">Disponible uniquement pour un site servi en HTTPS.</p>';
        return;
    }
    echo hpt_switch_input('hpt_sec_hsts', hpt_sec_option('hsts'), 'Forcer HTTPS');
    echo '<p class="optimizr-field__help">À n\'activer que si le site et tous ses sous-domaines fonctionnent en HTTPS (avec « En-têtes recommandés »).</p>';
}

// Panneau latéral : bilan et journal
add_action('hpt_admin_aside_security', function () {
    $protections = [
        'Adresse de connexion personnalisée' => hpt_sec_login_slug() !== '',
        'Tentatives de connexion limitées' => (bool) hpt_sec_option('limit_login'),
        'Version de WordPress masquée' => (bool) hpt_sec_option('hide_version'),
        'Liste des comptes bloquée' => (bool) hpt_sec_option('block_enumeration'),
        'XML-RPC désactivé' => (bool) hpt_sec_option('disable_xmlrpc'),
        'Éditeur de fichiers désactivé' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
        'En-têtes de sécurité' => (bool) hpt_sec_option('headers'),
    ];
    $active = count(array_filter($protections));
    $log = array_slice(hpt_sec_get_log(), 0, 8);
    ?>
    <div class="optimizr-sticky">
        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title">Bilan de sécurité</h2>
                    <p class="optimizr-card__desc"><?php echo esc_html(sprintf('%d protection(s) sur %d activée(s).', $active, count($protections))); ?></p>
                </div>
            </div>
            <ul class="optimizr-checks">
                <?php foreach (hpt_sec_environment_checks() as $check) : ?>
                    <li class="optimizr-checks__item">
                        <span class="optimizr-checks__icon optimizr-checks__icon--<?php echo esc_attr($check['status']); ?>"><?php echo hpt_icon($check['status'] === 'ok' ? 'check' : 'alert', 14); ?></span>
                        <span class="optimizr-checks__label"><?php echo esc_html($check['label']); ?></span>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($protections as $label => $on) : ?>
                    <li class="optimizr-checks__item">
                        <span class="optimizr-checks__icon optimizr-checks__icon--<?php echo $on ? 'ok' : 'off'; ?>"><?php echo hpt_icon($on ? 'check' : 'alert', 14); ?></span>
                        <span class="optimizr-checks__label"><?php echo esc_html($label); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title">Journal</h2>
                    <p class="optimizr-card__desc">Verrouillages et alertes des 30 derniers jours.</p>
                </div>
            </div>
            <?php if (!$log) : ?>
                <p class="optimizr-card__empty">Aucun évènement.</p>
            <?php else : ?>
                <ul class="optimizr-sec-log">
                    <?php foreach ($log as $entry) : ?>
                        <li>
                            <strong><?php echo esc_html($entry['event']); ?></strong>
                            <span class="optimizr-muted"><?php echo esc_html(wp_date('j M, H:i', $entry['time'])); ?> · <?php echo esc_html($entry['ip']); ?></span>
                            <?php if ($entry['detail']) : ?><span class="optimizr-muted"><?php echo esc_html($entry['detail']); ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <?php
});
