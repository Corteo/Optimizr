<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Services tiers gérés par Tarteaucitron.
 * 'fields' : clé enregistrée dans l'option haptic_option_service_{service} => libellé du champ.
 */
function haptic_cookie_services()
{
    return [
        'audience' => [
            'label' => 'Mesure d\'audience',
            'services' => [
                'google_analytics' => ['name' => 'Google Analytics 4', 'mono' => 'GA', 'note' => 'Consent Mode v2', 'fields' => ['ID' => 'G-XXXXXXXXXX']],
                'google_tags'      => ['name' => 'Google Tag Manager', 'mono' => 'GTM', 'fields' => ['ID' => 'GTM-XXXXXXX']],
                'clarity'          => ['name' => 'Microsoft Clarity', 'mono' => 'MC', 'note' => 'Bing Consent Mode', 'fields' => ['ID' => 'ID du projet']],
                'matomo'           => ['name' => 'Matomo Tag Manager', 'mono' => 'M', 'fields' => ['ID' => 'URL du conteneur']],
                'hotjar'           => ['name' => 'Hotjar', 'mono' => 'HJ', 'fields' => ['Site ID' => 'ID du site', 'Version' => 'Version']],
            ],
        ],
        'ads' => [
            'label' => 'Publicité',
            'services' => [
                'google_ads'     => ['name' => 'Google Ads', 'mono' => 'Ads', 'note' => 'Consent Mode v2', 'fields' => ['ID' => 'AW-XXXXXXXXX']],
                'facebook_pixel' => ['name' => 'Pixel Meta (Facebook)', 'mono' => 'FB', 'fields' => ['Pixel ID' => 'ID du pixel']],
            ],
        ],
        'embeds' => [
            'label' => 'Contenus intégrés',
            'services' => [
                'google_fonts' => ['name' => 'Google Fonts', 'mono' => 'Aa', 'fields' => ['Families' => 'Familles, séparées par des virgules']],
                // Zoom, coordonnées et taille se règlent sur chaque carte (bloc googlemaps-canvas), pas ici
                'google_maps'  => ['name' => 'Google Maps', 'mono' => 'GM', 'note' => 'Avec clé API', 'fields' => ['API Key' => 'Clé API']],
            ],
        ],
        'antispam' => [
            'label' => 'Anti-spam',
            'services' => [
                'google_recaptcha' => ['name' => 'Google reCAPTCHA', 'mono' => 'rC', 'fields' => ['API Key' => 'Clé du site']],
                'hcaptcha'         => ['name' => 'hCaptcha', 'mono' => 'hC', 'fields' => ['Site Key' => 'Clé du site']],
            ],
        ],
    ];
}

/**
 * Couleurs du bandeau : option => [libellé, valeur par défaut].
 */
function haptic_tac_colors()
{
    return [
        'haptic_option_tarteaucitron_highlight_color' => ['Fond du bandeau', '#e0e0e0'],
        'haptic_option_tarteaucitron_banner_bg'       => ['Fond du panneau de préférences', '#f9f9f9'],
        'haptic_option_tarteaucitron_btn_accept'      => ['Bouton Accepter', '#1e7f4f'],
        'haptic_option_tarteaucitron_btn_deny'        => ['Bouton Refuser', '#b3261e'],
        'haptic_option_tarteaucitron_btn_customize'   => ['Bouton Personnaliser', '#9a4a00'],
    ];
}

function haptic_tac_color($option)
{
    $colors = haptic_tac_colors();
    $value = sanitize_hex_color((string) get_option($option));
    return $value ?: $colors[$option][1];
}

/**
 * Couleur de texte lisible (sombre ou blanche) sur un fond donné.
 */
function haptic_readable_text_color($hex)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $channels = array_map(function ($c) {
        $c = hexdec($c) / 255;
        return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    }, str_split($hex, 2));
    $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

    return $luminance > 0.179 ? '#1e1e1e' : '#ffffff';
}

function haptic_options_register_cookie_settings()
{
    $group = haptic_settings_group('cookies');
    $page  = haptic_settings_page('cookies');

    register_setting($group, 'haptic_option_enable_tarteaucitron', ['sanitize_callback' => 'absint']);
    register_setting($group, 'haptic_option_tarteaucitron_banner_style', [
        'sanitize_callback' => function ($value) {
            return in_array($value, ['top', 'middle', 'bottom'], true) ? $value : 'middle';
        },
    ]);
    foreach (array_keys(haptic_tac_colors()) as $option) {
        register_setting($group, $option, ['sanitize_callback' => 'sanitize_hex_color']);
    }
    foreach (haptic_cookie_services() as $category) {
        foreach (array_keys($category['services']) as $service_key) {
            register_setting($group, "haptic_option_service_{$service_key}", [
                'sanitize_callback' => function ($value) {
                    return is_array($value) ? array_map('sanitize_text_field', $value) : [];
                },
            ]);
        }
    }

    add_settings_section(
        'haptic-cookies-banner',
        'Bandeau de consentement',
        function () {
            echo '<p>Propulsé par Tarteaucitron. Les services ci-dessous ne sont chargés qu\'après accord du visiteur. Le bandeau renvoie vers la politique de confidentialité choisie dans <a href="' . esc_url(admin_url('options-privacy.php')) . '">Réglages › Confidentialité</a>, une fois cette page publiée.</p>';
        },
        $page,
        ['toggle' => 'haptic_option_enable_tarteaucitron', 'toggle_label' => 'Afficher le bandeau de consentement']
    );
    add_settings_field('haptic-tac-position', 'Position', 'haptic_options_tarteaucitron_banner_style_callback', $page, 'haptic-cookies-banner');
    add_settings_field('haptic-tac-colors', 'Couleurs', 'haptic_options_tarteaucitron_colors_callback', $page, 'haptic-cookies-banner', [
        'description' => 'Le ratio affiché mesure la lisibilité du texte blanc sur chaque bouton (4,5:1 minimum).',
    ]);

    add_settings_section(
        'haptic-cookies-services',
        'Services',
        function () {
            echo '<p>Un service est actif dès que son identifiant est renseigné.</p>';
        },
        $page
    );
    add_settings_field('haptic-tac-services', '', 'haptic_options_services_callback', $page, 'haptic-cookies-services', ['layout' => 'stacked']);
}
add_action('admin_init', 'haptic_options_register_cookie_settings');

function haptic_options_tarteaucitron_banner_style_callback()
{
    $styles = [
        'top' => 'En haut',
        'middle' => 'Centré',
        'bottom' => 'En bas',
    ];
    $selected = get_option('haptic_option_tarteaucitron_banner_style', 'middle');

    echo '<div class="optimizr-segmented" role="radiogroup" aria-label="Position du bandeau">';
    foreach ($styles as $value => $label) {
        printf(
            '<label class="optimizr-segmented__option"><input type="radio" name="haptic_option_tarteaucitron_banner_style" value="%s" data-tac-position %s><span>%s</span></label>',
            esc_attr($value),
            checked($selected, $value, false),
            esc_html($label)
        );
    }
    echo '</div>';
}

function haptic_options_tarteaucitron_colors_callback()
{
    echo '<div class="optimizr-colors">';
    foreach (haptic_tac_colors() as $option => $color) {
        $value = haptic_tac_color($option);
        $is_button = strpos($option, '_btn_') !== false;
        printf(
            '<label class="optimizr-color"><input type="color" name="%1$s" value="%2$s" data-tac-color="%1$s"><span class="optimizr-color__label">%3$s</span>%4$s<code class="optimizr-color__hex" data-tac-hex>%2$s</code></label>',
            esc_attr($option),
            esc_attr($value),
            esc_html($color[0]),
            $is_button ? '<span class="optimizr-contrast" data-tac-contrast></span>' : ''
        );
    }
    echo '</div>';
}

function haptic_options_services_callback()
{
    echo '<div class="optimizr-services" data-optimizr-services>';
    echo '<div class="optimizr-services__search">' . wp_kses(haptic_icon('search', 16), haptic_kses_ui()) . '<input type="search" placeholder="Rechercher un service" aria-label="Rechercher un service" data-optimizr-services-search></div>';

    foreach (haptic_cookie_services() as $category) {
        echo '<div class="optimizr-services__group" data-optimizr-services-group>';
        echo '<h3 class="optimizr-services__group-title">' . esc_html($category['label']) . '</h3>';

        foreach ($category['services'] as $service_key => $service) {
            $option = get_option("haptic_option_service_{$service_key}");
            $option = is_array($option) ? $option : [];
            $first_field = array_key_first($service['fields']);
            $active = !empty($option[$first_field]);

            echo '<div class="optimizr-service" data-optimizr-service="' . esc_attr(strtolower($service['name'])) . '">';
            echo '<span class="optimizr-service__mono" aria-hidden="true">' . esc_html($service['mono']) . '</span>';
            echo '<div class="optimizr-service__name"><span>' . esc_html($service['name']) . '</span>';
            if (!empty($service['note'])) {
                echo '<small>' . esc_html($service['note']) . '</small>';
            }
            echo '</div>';

            echo '<div class="optimizr-service__inputs' . (count($service['fields']) > 1 ? ' optimizr-service__inputs--multi' : '') . '">';
            foreach ($service['fields'] as $field_key => $placeholder) {
                printf(
                    '<input type="text" name="haptic_option_service_%1$s[%2$s]" value="%3$s" placeholder="%4$s" aria-label="%5$s" spellcheck="false">',
                    esc_attr($service_key),
                    esc_attr($field_key),
                    esc_attr(isset($option[$field_key]) ? $option[$field_key] : ''),
                    esc_attr($placeholder),
                    esc_attr($service['name'] . ' – ' . $placeholder)
                );
            }
            // Champs retirés (Google Maps : zoom, coordonnées, taille), inutilisés par Tarteaucitron : valeurs enregistrées conservées
            foreach (array_diff_key($option, $service['fields']) as $field_key => $value) {
                printf(
                    '<input type="hidden" name="haptic_option_service_%1$s[%2$s]" value="%3$s">',
                    esc_attr($service_key),
                    esc_attr($field_key),
                    esc_attr($value)
                );
            }
            echo '</div>';

            echo '<span class="optimizr-status optimizr-status--' . ($active ? 'ok' : 'off') . '">' . ($active ? 'Actif' : 'Inactif') . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    echo '<p class="optimizr-services__empty" data-optimizr-services-empty hidden>Aucun service ne correspond à cette recherche.</p>';
    echo '<p class="optimizr-services__footer">Toujours proposés au visiteur, sans réglage : YouTube, Dailymotion, X (Twitter), Facebook, Google Maps sans clé.</p>';
    echo '</div>';
}

// Panneau latéral : aperçu du bandeau, mis à jour en direct par js/optimizr-admin.js
add_action('haptic_admin_aside_cookies', function () {
    $banner = haptic_tac_color('haptic_option_tarteaucitron_highlight_color');
    ?>
    <section class="optimizr-card optimizr-sticky">
        <div class="optimizr-card__header">
            <h2 class="optimizr-card__title">Aperçu du bandeau</h2>
        </div>
        <div class="optimizr-card__body">
            <div class="optimizr-tac-preview" data-tac-preview data-position="<?php echo esc_attr(get_option('haptic_option_tarteaucitron_banner_style', 'middle')); ?>">
                <div class="optimizr-tac-preview__bar" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="optimizr-tac-preview__page">
                    <div class="optimizr-tac-preview__banner" data-tac-preview-banner style="background: <?php echo esc_attr($banner); ?>; color: <?php echo esc_attr(haptic_readable_text_color($banner)); ?>;">
                        <strong>Ce site utilise des cookies</strong>
                        <p>Vous choisissez les services autorisés. Votre choix est modifiable à tout moment.</p>
                        <div class="optimizr-tac-preview__buttons">
                            <span data-tac-preview-btn="haptic_option_tarteaucitron_btn_accept" style="background: <?php echo esc_attr(haptic_tac_color('haptic_option_tarteaucitron_btn_accept')); ?>;">Tout accepter</span>
                            <span data-tac-preview-btn="haptic_option_tarteaucitron_btn_deny" style="background: <?php echo esc_attr(haptic_tac_color('haptic_option_tarteaucitron_btn_deny')); ?>;">Tout refuser</span>
                            <span data-tac-preview-btn="haptic_option_tarteaucitron_btn_customize" style="background: <?php echo esc_attr(haptic_tac_color('haptic_option_tarteaucitron_btn_customize')); ?>;">Personnaliser</span>
                        </div>
                    </div>
                </div>
            </div>
            <p class="optimizr-card__hint">Aperçu indicatif : le rendu final dépend aussi du thème.</p>
        </div>
    </section>
    <div class="optimizr-callout">
        <?php echo wp_kses(haptic_icon('info', 18), haptic_kses_ui()); ?>
        <span>Google Consent Mode v2 et Bing Consent Mode sont transmis automatiquement à GA4, Google Ads et Clarity.</span>
    </div>
    <?php
});

// Tarteaucitron, sa configuration et les services déclarés, dans le footer du site
add_action('haptic_before_enqueue_scripts', function () {
    if (!get_option('haptic_option_enable_tarteaucitron')) {
        return;
    }

    wp_enqueue_script('tarteaucitron', dirname(plugin_dir_url(__FILE__)) . '/assets/tarteaucitron/tarteaucitron.min.js', [], '1.21.0', true);
    // Imprimé juste après la librairie : tarteaucitron.init() en a besoin
    wp_add_inline_script('tarteaucitron', haptic_tarteaucitron_inline_script());

    // Couleurs de la bannière et des boutons
    wp_register_style('optimizr-tarteaucitron', false, [], HAPTIC_OPTIMIZR_VERSION);
    wp_enqueue_style('optimizr-tarteaucitron');
    wp_add_inline_style('optimizr-tarteaucitron', haptic_tarteaucitron_inline_style());
});

/** Configuration de Tarteaucitron, services déclarés et bouton de réouverture du panneau. */
function haptic_tarteaucitron_inline_script()
{
    $config = [
        'privacyUrl'              => esc_url_raw(get_privacy_policy_url()), // Page choisie dans Réglages > Confidentialité
        'bodyPosition'            => 'bottom',
        'hashtag'                 => '#tarteaucitron',
        'cookieName'              => 'tarteaucitron',
        'orientation'             => (string) get_option('haptic_option_tarteaucitron_banner_style', 'middle'), // top, middle, bottom
        'groupServices'           => false,
        'serviceDefaultState'     => 'wait',
        'showAlertSmall'          => false,
        'cookieslist'             => false,
        'closePopup'              => true,
        'showIcon'                => true, // Masquée par le CSS : remplacée par le bouton #haptic-cookie-button
        'iconPosition'            => 'BottomRight',
        'adblocker'               => false,
        'DenyAllCta'              => true,
        'AcceptAllCta'            => true,
        'highPrivacy'             => true, // Pas de consentement implicite
        'handleBrowserDNTRequest' => true, // Do Not Track = tout refuser
        'removeCredit'            => true,
        'moreInfoLink'            => true,
        'useExternalCss'          => false,
        'useExternalJs'           => false,
        'mandatory'               => true,
        'mandatoryCta'            => true,
        'googleConsentMode'       => true, // Google Consent Mode v2 (Google Ads, GA4)
        'bingConsentMode'         => true, // Bing Consent Mode (Clarity, Bing Ads)
        'softConsentMode'         => false,
        'dataLayer'               => false,
        'serverSide'              => false,
        'partnersList'            => false,
    ];

    $js = 'tarteaucitron.init(' . wp_json_encode($config, JSON_HEX_TAG) . ");\n";

    // Services réglés dans l'onglet Cookies : variables attendues par Tarteaucitron, puis le service
    $user = [];
    $jobs = [];
    foreach (haptic_cookie_services() as $category) {
        foreach (array_keys($category['services']) as $service_key) {
            $option = get_option("haptic_option_service_{$service_key}");
            $option = is_array($option) ? array_map('trim', array_map('strval', $option)) : [];

            switch ($service_key) {
                case 'google_analytics':
                    if (!empty($option['ID'])) {
                        $user['gtagUa'] = $option['ID'];
                        $jobs[] = 'gtag';
                    }
                    break;
                case 'google_ads':
                    if (!empty($option['ID'])) {
                        $user['googleadsId'] = $option['ID'];
                        $jobs[] = 'googleads';
                    }
                    break;
                case 'google_tags':
                    if (!empty($option['ID'])) {
                        $user['googletagmanagerId'] = $option['ID'];
                        $jobs[] = 'googletagmanager';
                    }
                    break;
                case 'hotjar':
                    if (!empty($option['Site ID']) && !empty($option['Version'])) {
                        $user['hotjarId'] = (int) $option['Site ID'];
                        $user['HotjarSv'] = (int) $option['Version'];
                        $jobs[] = 'hotjar';
                    }
                    break;
                case 'matomo':
                    if (!empty($option['ID'])) {
                        $user['matomotmUrl'] = $option['ID'];
                        $jobs[] = 'matomotm';
                    }
                    break;
                case 'clarity':
                    if (!empty($option['ID'])) {
                        $user['clarity'] = $option['ID'];
                        $jobs[] = 'clarity';
                    }
                    break;
                case 'facebook_pixel':
                    if (!empty($option['Pixel ID'])) {
                        $user['facebookpixelId'] = $option['Pixel ID'];
                        $jobs[] = 'facebookpixel';
                    }
                    break;
                case 'google_fonts':
                    if (!empty($option['Families'])) {
                        $user['googleFonts'] = array_values(array_filter(array_map('trim', explode(',', $option['Families']))));
                        $jobs[] = 'googlefonts';
                    }
                    break;
                case 'google_maps':
                    if (!empty($option['API Key'])) {
                        $user['googlemapsKey'] = $option['API Key'];
                        $jobs[] = 'googlemaps';
                        $jobs[] = 'googlemapssearch';
                    }
                    break;
                case 'google_recaptcha':
                    if (!empty($option['API Key'])) {
                        $user['recaptchaapi'] = $option['API Key'];
                        $jobs[] = 'recaptcha';
                    }
                    break;
                case 'hcaptcha':
                    if (!empty($option['Site Key'])) {
                        $user['hcaptchaSiteKey'] = $option['Site Key'];
                        $jobs[] = 'hcaptcha';
                    }
                    break;
            }
        }
    }

    // Services toujours proposés au visiteur, puis Google Consent Mode v2
    $jobs = array_merge($jobs, [
        'facebook', 'maps_noapi', 'twitterwidgetsapi', 'dailymotion', 'youtube', 'youtubeapi',
        'gcmadstorage', 'gcmanalyticsstorage', 'gcmfunctionality', 'gcmpersonalization', 'gcmadsuserdata', 'gcmsecurity',
    ]);

    foreach ($user as $key => $value) {
        $js .= 'tarteaucitron.user[' . wp_json_encode($key) . '] = ' . wp_json_encode($value, JSON_HEX_TAG) . ";\n";
    }
    $js .= 'tarteaucitron.job = tarteaucitron.job || [];' . "\n";
    foreach ($jobs as $job) {
        $js .= 'tarteaucitron.job.push(' . wp_json_encode($job) . ");\n";
    }

    // Bouton flottant pour rouvrir le panneau de préférences (imprimé par haptic_cookie_button())
    $js .= <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('haptic-cookie-button');
    if (!button) return;
    button.addEventListener('mouseenter', function () { button.style.opacity = '1'; });
    button.addEventListener('mouseleave', function () { button.style.opacity = '.6'; });
    button.addEventListener('click', function () {
        if (typeof tarteaucitron !== 'undefined') {
            tarteaucitron.userInterface.openPanel();
        }
    });
});
JS;

    return $js;
}

/** Couleurs de la bannière et des boutons, choisies dans l'onglet Cookies. */
function haptic_tarteaucitron_inline_style()
{
    $highlight = haptic_tac_color('haptic_option_tarteaucitron_highlight_color');

    return '#tarteaucitron #tarteaucitronServices .tarteaucitronTitle button,'
        . '#tarteaucitron #tarteaucitronInfo,'
        . '#tarteaucitron #tarteaucitronServices .tarteaucitronMainLine,'
        . '#tarteaucitronRoot #tarteaucitronAlertBig{background-color:' . $highlight . ' !important}'
        . '#tarteaucitronRoot,#tarteaucitronRoot div#tarteaucitronSave{background-color:' . haptic_tac_color('haptic_option_tarteaucitron_banner_bg') . '}'
        . '#tarteaucitronRoot .tarteaucitronAllow{background-color:' . haptic_tac_color('haptic_option_tarteaucitron_btn_accept') . ' !important}'
        . '#tarteaucitronRoot .tarteaucitronDeny{background-color:' . haptic_tac_color('haptic_option_tarteaucitron_btn_deny') . ' !important}'
        . '#tarteaucitronRoot .tarteaucitronPersonalize{background-color:' . haptic_tac_color('haptic_option_tarteaucitron_btn_customize') . ' !important}'
        . '#tarteaucitronRoot #tarteaucitronAlertBig,#tarteaucitronAlertBig #tarteaucitronDisclaimerAlert{color:' . haptic_readable_text_color($highlight) . ' !important}'
        . 'div#tarteaucitronInfo{border:none}'
        . '#tarteaucitronRoot #tarteaucitronIcon{display:none !important}';
}

// Bouton flottant pour rouvrir le panneau de préférences (remplace l'icône Tarteaucitron masquée)
add_action('wp_footer', 'haptic_cookie_button');
function haptic_cookie_button()
{
    if (!get_option('haptic_option_enable_tarteaucitron')) {
        return;
    }
    ?>
    <button type="button" id="haptic-cookie-button" aria-label="Gérer les cookies" style="position:fixed;bottom:15px;right:15px;z-index:99999;display:flex;align-items:center;justify-content:center;width:44px;height:44px;padding:0;border:0;border-radius:50%;background:#fff;color:#1e1e1e;box-shadow:0 2px 8px rgba(0,0,0,.2);cursor:pointer;opacity:.6;transition:opacity .2s ease"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg></button>
    <?php
}
