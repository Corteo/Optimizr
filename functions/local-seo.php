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

/** Jours de la semaine : clé enregistrée (schema.org) => libellé traduit. */
function haptic_local_days()
{
    return [
        'Monday'    => __('Monday', 'optimizr'),
        'Tuesday'   => __('Tuesday', 'optimizr'),
        'Wednesday' => __('Wednesday', 'optimizr'),
        'Thursday'  => __('Thursday', 'optimizr'),
        'Friday'    => __('Friday', 'optimizr'),
        'Saturday'  => __('Saturday', 'optimizr'),
        'Sunday'    => __('Sunday', 'optimizr'),
    ];
}

/** Types schema.org courants pour une activité locale (liste complète : schema.org/LocalBusiness). */
function haptic_local_types()
{
    return apply_filters('haptic_local_types', [
        'LocalBusiness'          => __('Local shop or business (generic)', 'optimizr'),
        'Store'                  => __('Store', 'optimizr'),
        'Restaurant'             => __('Restaurant', 'optimizr'),
        'CafeOrCoffeeShop'       => __('Café', 'optimizr'),
        'BarOrPub'               => __('Bar', 'optimizr'),
        'Bakery'                 => __('Bakery, pastry shop', 'optimizr'),
        'LodgingBusiness'        => __('Accommodation', 'optimizr'),
        'HealthAndBeautyBusiness' => __('Beauty, wellness', 'optimizr'),
        'MedicalBusiness'        => __('Health', 'optimizr'),
        'ProfessionalService'    => __('Business services', 'optimizr'),
        'LegalService'           => __('Legal', 'optimizr'),
        'RealEstateAgent'        => __('Real estate agency', 'optimizr'),
        'HomeAndConstructionBusiness' => __('Construction, trades', 'optimizr'),
        'AutomotiveBusiness'     => __('Automotive', 'optimizr'),
        'SportsActivityLocation' => __('Sports', 'optimizr'),
        'EducationalOrganization' => __('Education, training', 'optimizr'),
        'EntertainmentBusiness'  => __('Leisure, culture', 'optimizr'),
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

    add_settings_section('haptic_local_identity', __('Business', 'optimizr'), function () {
        echo '<p>' . esc_html__('This information must match your Google Business Profile listing.', 'optimizr') . '</p>';
    }, $page);
    haptic_local_field('type', __('Business type', 'optimizr'), 'haptic_local_identity', 'select');
    haptic_local_field('name', __('Name', 'optimizr'), 'haptic_local_identity', 'text', get_bloginfo('name'));
    haptic_local_field('description', __('Description', 'optimizr'), 'haptic_local_identity', 'textarea');
    haptic_local_field('logo', __('Logo (URL)', 'optimizr'), 'haptic_local_identity', 'url', __('Default: the site icon.', 'optimizr'));
    haptic_local_field('image', __('Business photo (URL)', 'optimizr'), 'haptic_local_identity', 'url', __('Storefront or interior, at least 1200 px wide.', 'optimizr'));
    haptic_local_field('price_range', __('Price range', 'optimizr'), 'haptic_local_identity', 'text', __('€, €€, €€€ or a range (“€15-30”).', 'optimizr'));

    add_settings_section('haptic_local_contact', __('Contact details', 'optimizr'), null, $page);
    haptic_local_field('street', __('Address', 'optimizr'), 'haptic_local_contact', 'text');
    haptic_local_field('postal_code', __('Postal code', 'optimizr'), 'haptic_local_contact', 'text');
    haptic_local_field('city', __('City', 'optimizr'), 'haptic_local_contact', 'text');
    haptic_local_field('region', __('Region', 'optimizr'), 'haptic_local_contact', 'text');
    haptic_local_field('country', __('Country (code)', 'optimizr'), 'haptic_local_contact', 'text', __('Two-letter code: FR, BE, CH…', 'optimizr'));
    haptic_local_field('phone', __('Phone', 'optimizr'), 'haptic_local_contact', 'tel', __('International format recommended: +33 1 23 45 67 89.', 'optimizr'));
    haptic_local_field('email', __('Email', 'optimizr'), 'haptic_local_contact', 'email');
    add_settings_field('haptic_local_geo', __('GPS coordinates', 'optimizr'), 'haptic_local_geo_callback', $page, 'haptic_local_contact', [
        'description' => __('On Google Maps, right-click the business: the latitude and longitude are shown.', 'optimizr'),
    ]);

    add_settings_section('haptic_local_hours_section', __('Opening hours', 'optimizr'), function () {
        echo '<p>' . esc_html__('Two time ranges per day at most (e.g. 9:00–12:30 and 14:00–19:00). Leave “Closed” checked for a day off.', 'optimizr') . '</p>';
    }, $page);
    add_settings_field('haptic_local_hours', '', 'haptic_local_hours_callback', $page, 'haptic_local_hours_section', ['layout' => 'stacked']);

    add_settings_section('haptic_local_google', 'Google', null, $page);
    add_settings_field('haptic_local_place_id', __('Place ID', 'optimizr'), 'haptic_local_place_id_callback', $page, 'haptic_local_google', [
        'label_for' => 'haptic_local_place_id',
        'description' => __('Used to create the “Leave a review” and “Directions” links.', 'optimizr'),
    ]);
    haptic_local_field('same_as', __('Online profiles', 'optimizr'), 'haptic_local_google', 'textarea', __('One URL per line: Google listing, Facebook, Instagram, LinkedIn, Yellow Pages… This lets Google link the site to these profiles.', 'optimizr'));
    add_settings_field('haptic_local_scope', __('Structured data', 'optimizr'), function () {
        $scope = haptic_local('schema_scope', 'home');
        echo '<div class="optimizr-segmented" role="radiogroup" aria-label="' . esc_attr__('Pages that carry the structured data', 'optimizr') . '">';
        foreach (['home' => __('Home page', 'optimizr'), 'all' => __('All pages', 'optimizr'), 'off' => _x('Disabled', 'structured data', 'optimizr')] as $value => $label) {
            printf('<label class="optimizr-segmented__option"><input type="radio" name="haptic_local[schema_scope]" value="%s" %s><span>%s</span></label>', esc_attr($value), checked($scope, $value, false), esc_html($label));
        }
        echo '</div>';
    }, $page, 'haptic_local_google', ['description' => __('The home page is enough in most cases.', 'optimizr')]);
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
        <label class="screen-reader-text" for="haptic_local_lat"><?php esc_html_e('Latitude', 'optimizr'); ?></label>
        <input type="text" class="optimizr-input-medium" name="haptic_local[lat]" id="haptic_local_lat" value="<?php echo esc_attr(haptic_local('lat')); ?>" placeholder="<?php esc_attr_e('Latitude (48.8566)', 'optimizr'); ?>" inputmode="decimal">
        <label class="screen-reader-text" for="haptic_local_lng"><?php esc_html_e('Longitude', 'optimizr'); ?></label>
        <input type="text" class="optimizr-input-medium" name="haptic_local[lng]" id="haptic_local_lng" value="<?php echo esc_attr(haptic_local('lng')); ?>" placeholder="<?php esc_attr_e('Longitude (2.3522)', 'optimizr'); ?>" inputmode="decimal">
    </div>
    <?php
}

function haptic_local_place_id_callback()
{
    $place_id = haptic_local('place_id');
    echo '<input type="text" name="haptic_local[place_id]" id="haptic_local_place_id" value="' . esc_attr($place_id) . '" spellcheck="false">';
    /* translators: %s: URL of Google's Place ID finder */
    echo '<p class="optimizr-field__help">' . wp_kses_post(sprintf(__('Find it with Google\'s <a href="%s" target="_blank" rel="noopener">Place ID finder</a>.', 'optimizr'), 'https://developers.google.com/maps/documentation/places/web-service/place-id#find-id')) . '</p>';
    if ($place_id) {
        /* translators: 1: Google review URL, 2: Google Maps directions URL */
        $haptic_links = __('Generated links: <a href="%1$s" target="_blank" rel="noopener">leave a review</a> · <a href="%2$s" target="_blank" rel="noopener">directions</a>', 'optimizr');
        echo '<p class="optimizr-field__help">' . wp_kses_post(sprintf(
            $haptic_links,
            esc_url(haptic_local_review_url()),
            esc_url(haptic_local_directions_url())
        )) . '</p>';
    }
}

function haptic_local_hours_callback()
{
    $hours = (array) haptic_local('hours', []);
    echo '<div class="optimizr-hours">';
    foreach (haptic_local_days() as $day => $label) {
        $h = (array) ($hours[$day] ?? []);
        $closed = !isset($h['closed']) || $h['closed'];
        ?>
        <div class="optimizr-hours__day">
            <strong><?php echo esc_html($label); ?></strong>
            <label class="optimizr-check"><input type="checkbox" name="haptic_local[hours][<?php echo esc_attr($day); ?>][closed]" value="1" <?php checked($closed); ?> data-hours-closed> <?php esc_html_e('Closed', 'optimizr'); ?></label>
            <span class="optimizr-hours__ranges">
                <?php for ($i = 1; $i <= 2; $i++) : ?>
                    <input type="time" name="haptic_local[hours][<?php echo esc_attr($day); ?>][open<?php echo (int) $i; ?>]" value="<?php echo esc_attr($h['open' . $i] ?? ''); ?>" aria-label="<?php
                        /* translators: 1: day of the week, 2: time range number */
                        echo esc_attr(sprintf(__('%1$s, opening %2$d', 'optimizr'), $label, $i));
                    ?>">
                    <span aria-hidden="true">–</span>
                    <input type="time" name="haptic_local[hours][<?php echo esc_attr($day); ?>][close<?php echo (int) $i; ?>]" value="<?php echo esc_attr($h['close' . $i] ?? ''); ?>" aria-label="<?php
                        /* translators: 1: day of the week, 2: time range number */
                        echo esc_attr(sprintf(__('%1$s, closing %2$d', 'optimizr'), $label, $i));
                    ?>">
                    <?php if ($i === 1) : ?><span class="optimizr-muted"><?php echo esc_html_x('and', 'between two time ranges', 'optimizr'); ?></span><?php endif; ?>
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
    foreach (array_keys(haptic_local_days()) as $day) {
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
    foreach (haptic_local_days() as $day => $label) {
        $h = (array) ($hours[$day] ?? []);
        $ranges = [];
        if (empty($h['closed'])) {
            foreach ([1, 2] as $i) {
                if (!empty($h['open' . $i]) && !empty($h['close' . $i])) {
                    $ranges[] = str_replace(':', ' h ', $h['open' . $i]) . ' – ' . str_replace(':', ' h ', $h['close' . $i]);
                }
            }
        }
        $html .= '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($ranges ? implode(', ', $ranges) : __('Closed', 'optimizr')) . '</td></tr>';
    }
    return $html . '</tbody></table>';
});

add_shortcode('optimizr_avis_google', function ($atts) {
    $atts = shortcode_atts(['texte' => __('Leave a review on Google', 'optimizr')], $atts, 'optimizr_avis_google');
    $url = haptic_local_review_url();
    return $url ? '<a class="optimizr-avis-google" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($atts['texte']) . '</a>' : '';
});

add_shortcode('optimizr_itineraire', function ($atts) {
    $atts = shortcode_atts(['texte' => __('Directions', 'optimizr')], $atts, 'optimizr_itineraire');
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
        wp_die(esc_html__('Access denied', 'optimizr'));
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
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Contact details imported from the theme. Check them, then save.', 'optimizr') . '</p></div>';
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
                        <h2 class="optimizr-card__title"><?php esc_html_e('Theme contact details', 'optimizr'); ?></h2>
                        <p class="optimizr-card__desc"><?php
                        /* translators: %s: number of values found */
                        echo esc_html(sprintf(_n('%s value found in the theme Customizer. Fields already filled in here are kept.', '%s values found in the theme Customizer. Fields already filled in here are kept.', count($theme), 'optimizr'), number_format_i18n(count($theme))));
                        ?></p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=haptic_local_import_theme'), 'haptic_local_import_theme')); ?>"><?php esc_html_e('Import from the theme', 'optimizr'); ?></a>
                </div>
            </section>
        <?php endif; ?>

        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title"><?php esc_html_e('Data for Google', 'optimizr'); ?></h2>
                    <p class="optimizr-card__desc"><?php echo esc_html($schema ? (haptic_local('schema_scope', 'home') === 'all' ? __('Added to all pages.', 'optimizr') : (haptic_local('schema_scope', 'home') === 'off' ? __('Added to no page (disabled).', 'optimizr') : __('Added to the home page.', 'optimizr'))) : __('Enter at least the address and the city.', 'optimizr')); ?></p>
                </div>
            </div>
            <?php if ($schema) : ?>
                <div class="optimizr-card__body">
                    <pre class="optimizr-json"><?php echo esc_html(wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                    <a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url('https://search.google.com/test/rich-results?url=' . rawurlencode(home_url('/'))); ?>" target="_blank" rel="noopener"><?php esc_html_e('Test with Google', 'optimizr'); ?></a>
                </div>
            <?php endif; ?>
        </section>

        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title"><?php esc_html_e('Shortcodes', 'optimizr'); ?></h2>
                    <p class="optimizr-card__desc"><?php esc_html_e('Place them in a page or widget: they always show up-to-date information.', 'optimizr'); ?></p>
                </div>
            </div>
            <ul class="optimizr-shortcodes">
                <li><code>[optimizr_adresse]</code> <?php esc_html_e('Full address', 'optimizr'); ?></li>
                <li><code>[optimizr_telephone]</code> <?php esc_html_e('Clickable phone number', 'optimizr'); ?></li>
                <li><code>[optimizr_email]</code> <?php esc_html_e('Email protected from bots', 'optimizr'); ?></li>
                <li><code>[optimizr_horaires]</code> <?php esc_html_e('Opening hours table', 'optimizr'); ?></li>
                <li><code>[optimizr_avis_google]</code> <?php esc_html_e('“Leave a review” link', 'optimizr'); ?></li>
                <li><code>[optimizr_itineraire]</code> <?php esc_html_e('Google Maps directions link', 'optimizr'); ?></li>
            </ul>
        </section>
    </div>
    <?php
});
