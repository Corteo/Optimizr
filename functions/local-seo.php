<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Module SEO local : fiche de l'établissement, données structurées LocalBusiness (Google),
 * liens Google (avis, itinéraire) et shortcodes pour afficher les coordonnées sur le site.
 *
 * Toutes les informations sont dans l'option haptic_local (tableau).
 */

const HAPTIC_LOCAL_DAYS = [
    'Monday'    => 'Lundi',
    'Tuesday'   => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday'  => 'Jeudi',
    'Friday'    => 'Vendredi',
    'Saturday'  => 'Samedi',
    'Sunday'    => 'Dimanche',
];

/** Types schema.org courants pour une activité locale (liste complète : schema.org/LocalBusiness). */
function haptic_local_types()
{
    return apply_filters('haptic_local_types', [
        'LocalBusiness'          => 'Commerce ou activité locale (générique)',
        'Store'                  => 'Magasin',
        'Restaurant'             => 'Restaurant',
        'CafeOrCoffeeShop'       => 'Café',
        'BarOrPub'               => 'Bar',
        'Bakery'                 => 'Boulangerie, pâtisserie',
        'LodgingBusiness'        => 'Hébergement',
        'HealthAndBeautyBusiness' => 'Beauté, bien-être',
        'MedicalBusiness'        => 'Santé',
        'ProfessionalService'    => 'Services aux entreprises',
        'LegalService'           => 'Juridique',
        'RealEstateAgent'        => 'Agence immobilière',
        'HomeAndConstructionBusiness' => 'Bâtiment, artisan',
        'AutomotiveBusiness'     => 'Automobile',
        'SportsActivityLocation' => 'Sport',
        'EducationalOrganization' => 'Enseignement, formation',
        'EntertainmentBusiness'  => 'Loisirs, culture',
    ]);
}

function haptic_local($key = null, $default = '')
{
    $data = (array) get_option('haptic_local', []);
    if ($key === null) {
        return $data;
    }
    return isset($data[$key]) && $data[$key] !== '' ? $data[$key] : $default;
}

/* ---------- Réglages ---------- */

add_action('admin_init', function () {
    $page = haptic_settings_page('local');

    register_setting(haptic_settings_group('local'), 'haptic_local', [
        'type' => 'array',
        'sanitize_callback' => 'haptic_local_sanitize',
    ]);

    add_settings_section('haptic_local_identity', 'Établissement', function () {
        echo '<p>Ces informations doivent être identiques à celles de votre fiche Google Business Profile.</p>';
    }, $page);
    haptic_local_field('type', 'Activité', 'haptic_local_identity', 'select');
    haptic_local_field('name', 'Nom', 'haptic_local_identity', 'text', get_bloginfo('name'));
    haptic_local_field('description', 'Description', 'haptic_local_identity', 'textarea');
    haptic_local_field('logo', 'Logo (URL)', 'haptic_local_identity', 'url', 'Par défaut : l\'icône du site.');
    haptic_local_field('image', 'Photo de l\'établissement (URL)', 'haptic_local_identity', 'url', 'Façade ou intérieur, au moins 1200 px de large.');
    haptic_local_field('price_range', 'Gamme de prix', 'haptic_local_identity', 'text', '€, €€, €€€ ou une fourchette (« 15-30 € »).');

    add_settings_section('haptic_local_contact', 'Coordonnées', null, $page);
    haptic_local_field('street', 'Adresse', 'haptic_local_contact', 'text');
    haptic_local_field('postal_code', 'Code postal', 'haptic_local_contact', 'text');
    haptic_local_field('city', 'Ville', 'haptic_local_contact', 'text');
    haptic_local_field('region', 'Région', 'haptic_local_contact', 'text');
    haptic_local_field('country', 'Pays (code)', 'haptic_local_contact', 'text', 'Code à deux lettres : FR, BE, CH…');
    haptic_local_field('phone', 'Téléphone', 'haptic_local_contact', 'tel', 'Format international conseillé : +33 1 23 45 67 89.');
    haptic_local_field('email', 'Email', 'haptic_local_contact', 'email');
    add_settings_field('haptic_local_geo', 'Coordonnées GPS', 'haptic_local_geo_callback', $page, 'haptic_local_contact', [
        'description' => 'Sur Google Maps, clic droit sur l\'établissement : la latitude et la longitude s\'affichent.',
    ]);

    add_settings_section('haptic_local_hours_section', 'Horaires d\'ouverture', function () {
        echo '<p>Deux plages par jour au plus (ex. 9:00–12:30 et 14:00–19:00). Laissez « Fermé » coché pour un jour de fermeture.</p>';
    }, $page);
    add_settings_field('haptic_local_hours', '', 'haptic_local_hours_callback', $page, 'haptic_local_hours_section', ['layout' => 'stacked']);

    add_settings_section('haptic_local_google', 'Google', null, $page);
    add_settings_field('haptic_local_place_id', 'Identifiant de lieu (Place ID)', 'haptic_local_place_id_callback', $page, 'haptic_local_google', [
        'label_for' => 'haptic_local_place_id',
        'description' => 'Sert à créer les liens « Laisser un avis » et « Itinéraire ».',
    ]);
    haptic_local_field('same_as', 'Profils en ligne', 'haptic_local_google', 'textarea', 'Une adresse par ligne : fiche Google, Facebook, Instagram, LinkedIn, Pages Jaunes… Google relie ainsi le site à ces profils.');
    add_settings_field('haptic_local_scope', 'Données structurées', function () {
        $scope = haptic_local('schema_scope', 'home');
        echo '<div class="optimizr-segmented" role="radiogroup" aria-label="Pages qui portent les données structurées">';
        foreach (['home' => 'Page d\'accueil', 'all' => 'Toutes les pages', 'off' => 'Désactivées'] as $value => $label) {
            printf('<label class="optimizr-segmented__option"><input type="radio" name="haptic_local[schema_scope]" value="%s" %s><span>%s</span></label>', esc_attr($value), checked($scope, $value, false), esc_html($label));
        }
        echo '</div>';
    }, $page, 'haptic_local_google', ['description' => 'La page d\'accueil suffit dans la plupart des cas.']);
});

function haptic_local_field($key, $label, $section, $type = 'text', $help = '')
{
    add_settings_field('haptic_local_' . $key, $label, function () use ($key, $type) {
        $name = 'haptic_local[' . $key . ']';
        $id = 'haptic_local_' . $key;
        $value = haptic_local($key);

        if ($type === 'select') {
            echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '">';
            foreach (haptic_local_types() as $schema => $title) {
                printf('<option value="%s" %s>%s</option>', esc_attr($schema), selected($value ?: 'LocalBusiness', $schema, false), esc_html($title));
            }
            echo '</select>';
        } elseif ($type === 'textarea') {
            printf('<textarea name="%s" id="%s" rows="3">%s</textarea>', esc_attr($name), esc_attr($id), esc_textarea($value));
        } else {
            printf('<input type="%s" name="%s" id="%s" value="%s"%s>', esc_attr($type), esc_attr($name), esc_attr($id), esc_attr($value), $key === 'name' ? ' placeholder="' . esc_attr(get_bloginfo('name')) . '"' : '');
        }
    }, haptic_settings_page('local'), $section, [
        'label_for' => 'haptic_local_' . $key,
        'description' => $key === 'name' ? '' : $help,
    ]);
}

function haptic_local_geo_callback()
{
    ?>
    <div class="optimizr-inline">
        <label class="screen-reader-text" for="haptic_local_lat">Latitude</label>
        <input type="text" class="optimizr-input-medium" name="haptic_local[lat]" id="haptic_local_lat" value="<?php echo esc_attr(haptic_local('lat')); ?>" placeholder="Latitude (48.8566)" inputmode="decimal">
        <label class="screen-reader-text" for="haptic_local_lng">Longitude</label>
        <input type="text" class="optimizr-input-medium" name="haptic_local[lng]" id="haptic_local_lng" value="<?php echo esc_attr(haptic_local('lng')); ?>" placeholder="Longitude (2.3522)" inputmode="decimal">
    </div>
    <?php
}

function haptic_local_place_id_callback()
{
    $place_id = haptic_local('place_id');
    echo '<input type="text" name="haptic_local[place_id]" id="haptic_local_place_id" value="' . esc_attr($place_id) . '" spellcheck="false">';
    echo '<p class="optimizr-field__help">À trouver avec l\'<a href="https://developers.google.com/maps/documentation/places/web-service/place-id#find-id" target="_blank" rel="noopener">outil de recherche de Place ID</a> de Google.</p>';
    if ($place_id) {
        printf(
            '<p class="optimizr-field__help">Liens générés : <a href="%s" target="_blank" rel="noopener">laisser un avis</a> · <a href="%s" target="_blank" rel="noopener">itinéraire</a></p>',
            esc_url(haptic_local_review_url()),
            esc_url(haptic_local_directions_url())
        );
    }
}

function haptic_local_hours_callback()
{
    $hours = (array) haptic_local('hours', []);
    echo '<div class="optimizr-hours">';
    foreach (HAPTIC_LOCAL_DAYS as $day => $label) {
        $h = (array) ($hours[$day] ?? []);
        $closed = !isset($h['closed']) || $h['closed'];
        ?>
        <div class="optimizr-hours__day">
            <strong><?php echo esc_html($label); ?></strong>
            <label class="optimizr-check"><input type="checkbox" name="haptic_local[hours][<?php echo esc_attr($day); ?>][closed]" value="1" <?php checked($closed); ?> data-hours-closed> Fermé</label>
            <span class="optimizr-hours__ranges">
                <?php for ($i = 1; $i <= 2; $i++) : ?>
                    <input type="time" name="haptic_local[hours][<?php echo esc_attr($day); ?>][open<?php echo (int) $i; ?>]" value="<?php echo esc_attr($h['open' . $i] ?? ''); ?>" aria-label="<?php echo esc_attr($label . ', ouverture ' . $i); ?>">
                    <span aria-hidden="true">–</span>
                    <input type="time" name="haptic_local[hours][<?php echo esc_attr($day); ?>][close<?php echo (int) $i; ?>]" value="<?php echo esc_attr($h['close' . $i] ?? ''); ?>" aria-label="<?php echo esc_attr($label . ', fermeture ' . $i); ?>">
                    <?php if ($i === 1) : ?><span class="optimizr-muted">et</span><?php endif; ?>
                <?php endfor; ?>
            </span>
        </div>
        <?php
    }
    echo '</div>';
}

/** Valeurs déjà sans barres obliques d'échappement (options.php, assistant, import du thème). */
function haptic_local_sanitize($input)
{
    $input = is_array($input) ? $input : [];
    $clean = [];

    $clean['type'] = array_key_exists($input['type'] ?? '', haptic_local_types()) ? $input['type'] : 'LocalBusiness';
    foreach (['name', 'street', 'postal_code', 'city', 'region', 'price_range', 'place_id', 'phone'] as $key) {
        $clean[$key] = sanitize_text_field($input[$key] ?? '');
    }
    $clean['country'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($input['country'] ?? 'FR')), 0, 2)) ?: 'FR';
    $clean['description'] = sanitize_textarea_field($input['description'] ?? '');
    $clean['email'] = sanitize_email($input['email'] ?? '');
    $clean['logo'] = esc_url_raw($input['logo'] ?? '');
    $clean['image'] = esc_url_raw($input['image'] ?? '');
    foreach (['lat', 'lng'] as $key) {
        $value = str_replace(',', '.', trim((string) ($input[$key] ?? '')));
        $clean[$key] = is_numeric($value) ? (string) round((float) $value, 6) : '';
    }
    $clean['same_as'] = implode("\n", array_filter(array_map('esc_url_raw', array_map('trim', preg_split('/\R/', (string) ($input['same_as'] ?? ''))))));
    $clean['schema_scope'] = in_array($input['schema_scope'] ?? '', ['home', 'all', 'off'], true) ? $input['schema_scope'] : 'home';

    $clean['hours'] = [];
    foreach (array_keys(HAPTIC_LOCAL_DAYS) as $day) {
        $h = (array) ($input['hours'][$day] ?? []);
        $day_clean = ['closed' => empty($h['closed']) ? 0 : 1];
        foreach (['open1', 'close1', 'open2', 'close2'] as $key) {
            $day_clean[$key] = preg_match('/^\d{2}:\d{2}$/', (string) ($h[$key] ?? '')) ? $h[$key] : '';
        }
        // Un jour sans aucune plage horaire est considéré comme fermé
        if (!$day_clean['open1'] || !$day_clean['close1']) {
            $day_clean['closed'] = 1;
        }
        $clean['hours'][$day] = $day_clean;
    }

    return $clean;
}

/* ---------- Liens Google ---------- */

function haptic_local_review_url()
{
    $place_id = haptic_local('place_id');
    return $place_id ? 'https://search.google.com/local/writereview?placeid=' . rawurlencode($place_id) : '';
}

function haptic_local_directions_url()
{
    $place_id = haptic_local('place_id');
    $destination = trim(implode(' ', array_filter([haptic_local('name', get_bloginfo('name')), haptic_local('street'), haptic_local('postal_code'), haptic_local('city')])));

    return add_query_arg(array_filter([
        'api' => 1,
        'destination' => $destination,
        'destination_place_id' => $place_id,
    ]), 'https://www.google.com/maps/dir/');
}

/* ---------- Données structurées ---------- */

/**
 * Données LocalBusiness (schema.org), ou null si l'adresse n'est pas renseignée.
 */
function haptic_local_schema()
{
    if (!haptic_local('street') || !haptic_local('city')) {
        return null;
    }

    $schema = [
        '@type' => haptic_local('type', 'LocalBusiness'),
        '@id'   => home_url('/#etablissement'),
        'name'  => haptic_local('name', get_bloginfo('name')),
        'url'   => home_url('/'),
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => haptic_local('street'),
            'postalCode' => haptic_local('postal_code'),
            'addressLocality' => haptic_local('city'),
            'addressRegion' => haptic_local('region'),
            'addressCountry' => haptic_local('country', 'FR'),
        ]),
    ];

    $optional = [
        'description' => haptic_local('description'),
        'telephone'   => haptic_local('phone'),
        'email'       => haptic_local('email'),
        'priceRange'  => haptic_local('price_range'),
        'logo'        => haptic_local('logo', get_site_icon_url(512)),
        'image'       => haptic_local('image', haptic_local('logo', get_site_icon_url(512))),
    ];
    $schema += array_filter($optional);

    if (haptic_local('lat') !== '' && haptic_local('lng') !== '') {
        $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) haptic_local('lat'), 'longitude' => (float) haptic_local('lng')];
    }

    // Horaires : jours qui partagent les mêmes plages regroupés
    $specs = [];
    foreach ((array) haptic_local('hours', []) as $day => $h) {
        if (!empty($h['closed'])) {
            continue;
        }
        foreach ([1, 2] as $i) {
            if (!empty($h['open' . $i]) && !empty($h['close' . $i])) {
                $key = $h['open' . $i] . '-' . $h['close' . $i];
                $specs[$key]['days'][] = $day;
                $specs[$key]['opens'] = $h['open' . $i];
                $specs[$key]['closes'] = $h['close' . $i];
            }
        }
    }
    foreach ($specs as $spec) {
        $schema['openingHoursSpecification'][] = [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => $spec['days'],
            'opens' => $spec['opens'],
            'closes' => $spec['closes'],
        ];
    }

    $same_as = array_filter(preg_split('/\R/', (string) haptic_local('same_as')));
    if ($same_as) {
        $schema['sameAs'] = array_values($same_as);
    }
    if (haptic_local('place_id')) {
        $schema['hasMap'] = 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode(haptic_local('place_id'));
    }

    return apply_filters('haptic_local_schema', $schema);
}

// Sortie autonome ; quand les données structurées du module SEO sont actives, elles l'intègrent à leur graphe
add_action('wp_head', function () {
    if (function_exists('haptic_schema_enabled') && haptic_schema_enabled()) {
        return;
    }
    $scope = haptic_local('schema_scope', 'home');
    if ($scope === 'off' || ($scope === 'home' && !is_front_page())) {
        return;
    }
    $schema = haptic_local_schema();
    if ($schema) {
        echo '<script type="application/ld+json">' . wp_json_encode(['@context' => 'https://schema.org'] + $schema, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . PHP_EOL;
    }
}, 20);

/* ---------- Shortcodes ---------- */

add_shortcode('optimizr_adresse', function () {
    $lines = array_filter([haptic_local('name', get_bloginfo('name')), haptic_local('street'), trim(haptic_local('postal_code') . ' ' . haptic_local('city'))]);
    return $lines ? '<address class="optimizr-adresse">' . implode('<br>', array_map('esc_html', $lines)) . '</address>' : '';
});

add_shortcode('optimizr_telephone', function () {
    $phone = haptic_local('phone');
    return $phone ? '<a class="optimizr-telephone" href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $phone)) . '">' . esc_html($phone) . '</a>' : '';
});

// Email protégé des robots collecteurs, lisible par les lecteurs d'écran
add_shortcode('optimizr_email', function () {
    $email = haptic_local('email');
    return $email ? '<a class="optimizr-email" href="mailto:' . antispambot($email, 1) . '">' . antispambot($email) . '</a>' : '';
});

add_shortcode('optimizr_horaires', function () {
    $hours = (array) haptic_local('hours', []);
    if (!$hours) {
        return '';
    }
    $html = '<table class="optimizr-horaires"><tbody>';
    foreach (HAPTIC_LOCAL_DAYS as $day => $label) {
        $h = (array) ($hours[$day] ?? []);
        $ranges = [];
        if (empty($h['closed'])) {
            foreach ([1, 2] as $i) {
                if (!empty($h['open' . $i]) && !empty($h['close' . $i])) {
                    $ranges[] = str_replace(':', ' h ', $h['open' . $i]) . ' – ' . str_replace(':', ' h ', $h['close' . $i]);
                }
            }
        }
        $html .= '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($ranges ? implode(', ', $ranges) : 'Fermé') . '</td></tr>';
    }
    return $html . '</tbody></table>';
});

add_shortcode('optimizr_avis_google', function ($atts) {
    $atts = shortcode_atts(['texte' => 'Laisser un avis sur Google'], $atts, 'optimizr_avis_google');
    $url = haptic_local_review_url();
    return $url ? '<a class="optimizr-avis-google" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($atts['texte']) . '</a>' : '';
});

add_shortcode('optimizr_itineraire', function ($atts) {
    $atts = shortcode_atts(['texte' => 'Itinéraire'], $atts, 'optimizr_itineraire');
    return '<a class="optimizr-itineraire" href="' . esc_url(haptic_local_directions_url()) . '" target="_blank" rel="noopener">' . esc_html($atts['texte']) . '</a>';
});

/* ---------- Import depuis le thème ---------- */

/**
 * Coordonnées saisies dans le Customizer des thèmes Haptic, converties au format du module.
 *
 * @return array Valeurs trouvées (vide si le thème n'en a pas)
 */
function haptic_local_theme_values()
{
    $map = [
        'name' => 'nom_contact', 'street' => 'adresse', 'postal_code' => 'code_postal',
        'city' => 'ville', 'phone' => 'tel_contact', 'email' => 'mail_contact',
    ];
    $values = [];
    foreach ($map as $key => $mod) {
        $value = trim((string) get_theme_mod($mod, ''));
        if ($value !== '') {
            $values[$key] = $value;
        }
    }
    $profiles = [];
    foreach (['facebook', 'instagram', 'linkedin', 'twitter', 'pinterest', 'snapchat'] as $mod) {
        $url = esc_url_raw(trim((string) get_theme_mod($mod, '')));
        if ($url) {
            $profiles[] = $url;
        }
    }
    if ($profiles) {
        $values['same_as'] = implode("\n", $profiles);
    }
    return $values;
}

add_action('admin_post_haptic_local_import_theme', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }
    check_admin_referer('haptic_local_import_theme');

    $current = haptic_local();
    // Les champs déjà remplis dans Optimizr ne sont pas écrasés
    foreach (haptic_local_theme_values() as $key => $value) {
        if (empty($current[$key])) {
            $current[$key] = $value;
        }
    }
    update_option('haptic_local', haptic_local_sanitize($current));
    wp_safe_redirect(add_query_arg('haptic_message', 'local_imported', haptic_admin_tab_url('local')));
    exit;
});

add_action('admin_notices', function () {
    if (sanitize_key(wp_unslash($_GET['haptic_message'] ?? '')) === 'local_imported') {
        echo '<div class="notice notice-success is-dismissible"><p>Coordonnées importées depuis le thème. Vérifiez-les puis enregistrez.</p></div>';
    }
});

// Panneau latéral : aperçu des données, test Google, shortcodes
add_action('haptic_admin_aside_local', function () {
    $schema = haptic_local_schema();
    $theme = haptic_local_theme_values();
    ?>
    <div class="optimizr-sticky">
        <?php if ($theme) : ?>
            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h2 class="optimizr-card__title">Coordonnées du thème</h2>
                        <p class="optimizr-card__desc"><?php echo esc_html(sprintf('%d information(s) trouvée(s) dans le Customizer du thème. Les champs déjà remplis ici sont conservés.', count($theme))); ?></p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=haptic_local_import_theme'), 'haptic_local_import_theme')); ?>">Importer depuis le thème</a>
                </div>
            </section>
        <?php endif; ?>

        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title">Données pour Google</h2>
                    <p class="optimizr-card__desc"><?php echo $schema ? 'Ajoutées à ' . (haptic_local('schema_scope', 'home') === 'all' ? 'toutes les pages.' : (haptic_local('schema_scope', 'home') === 'off' ? 'aucune page (désactivées).' : 'la page d\'accueil.')) : 'Renseignez au moins l\'adresse et la ville.'; ?></p>
                </div>
            </div>
            <?php if ($schema) : ?>
                <div class="optimizr-card__body">
                    <pre class="optimizr-json"><?php echo esc_html(wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                    <a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url('https://search.google.com/test/rich-results?url=' . rawurlencode(home_url('/'))); ?>" target="_blank" rel="noopener">Tester avec Google</a>
                </div>
            <?php endif; ?>
        </section>

        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title">Shortcodes</h2>
                    <p class="optimizr-card__desc">À placer dans une page ou un widget : ils affichent toujours les informations à jour.</p>
                </div>
            </div>
            <ul class="optimizr-shortcodes">
                <li><code>[optimizr_adresse]</code> Adresse complète</li>
                <li><code>[optimizr_telephone]</code> Téléphone cliquable</li>
                <li><code>[optimizr_email]</code> Email protégé des robots</li>
                <li><code>[optimizr_horaires]</code> Tableau des horaires</li>
                <li><code>[optimizr_avis_google]</code> Lien « Laisser un avis »</li>
                <li><code>[optimizr_itineraire]</code> Lien d'itinéraire Google Maps</li>
            </ul>
        </section>
    </div>
    <?php
});
