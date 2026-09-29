<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Services tiers gérés par Tarteaucitron.
 * 'fields' : clé enregistrée dans l'option hpt_option_service_{service} => libellé du champ.
 */
function hpt_cookie_services()
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
                'google_maps'  => ['name' => 'Google Maps', 'mono' => 'GM', 'note' => 'Avec clé API', 'fields' => [
                    'API Key' => 'Clé API', 'Zoom' => 'Zoom', 'Latitude' => 'Latitude',
                    'Longitude' => 'Longitude', 'Width' => 'Largeur (px)', 'Height' => 'Hauteur (px)',
                ]],
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
function hpt_tac_colors()
{
    return [
        'hpt_option_tarteaucitron_highlight_color' => ['Fond du bandeau', '#e0e0e0'],
        'hpt_option_tarteaucitron_banner_bg'       => ['Fond du panneau de préférences', '#f9f9f9'],
        'hpt_option_tarteaucitron_btn_accept'      => ['Bouton Accepter', '#1e7f4f'],
        'hpt_option_tarteaucitron_btn_deny'        => ['Bouton Refuser', '#b3261e'],
        'hpt_option_tarteaucitron_btn_customize'   => ['Bouton Personnaliser', '#9a4a00'],
    ];
}

function hpt_tac_color($option)
{
    $colors = hpt_tac_colors();
    $value = sanitize_hex_color((string) get_option($option));
    return $value ?: $colors[$option][1];
}

/**
 * Couleur de texte lisible (sombre ou blanche) sur un fond donné.
 */
function hpt_readable_text_color($hex)
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

function hpt_options_register_cookie_settings()
{
    $group = hpt_settings_group('cookies');
    $page  = hpt_settings_page('cookies');

    register_setting($group, 'hpt_option_enable_tarteaucitron', ['sanitize_callback' => 'absint']);
    register_setting($group, 'hpt_option_tarteaucitron_banner_style', [
        'sanitize_callback' => function ($value) {
            return in_array($value, ['top', 'middle', 'bottom'], true) ? $value : 'middle';
        },
    ]);
    foreach (array_keys(hpt_tac_colors()) as $option) {
        register_setting($group, $option, ['sanitize_callback' => 'sanitize_hex_color']);
    }
    foreach (hpt_cookie_services() as $category) {
        foreach (array_keys($category['services']) as $service_key) {
            register_setting($group, "hpt_option_service_{$service_key}", [
                'sanitize_callback' => function ($value) {
                    return is_array($value) ? array_map('sanitize_text_field', $value) : [];
                },
            ]);
        }
    }

    add_settings_section(
        'hpt-cookies-banner',
        'Bandeau de consentement',
        function () {
            echo '<p>Propulsé par Tarteaucitron. Les services ci-dessous ne sont chargés qu\'après accord du visiteur.</p>';
        },
        $page,
        ['toggle' => 'hpt_option_enable_tarteaucitron', 'toggle_label' => 'Afficher le bandeau de consentement']
    );
    add_settings_field('hpt-tac-position', 'Position', 'hpt_options_tarteaucitron_banner_style_callback', $page, 'hpt-cookies-banner');
    add_settings_field('hpt-tac-colors', 'Couleurs', 'hpt_options_tarteaucitron_colors_callback', $page, 'hpt-cookies-banner', [
        'description' => 'Le ratio affiché mesure la lisibilité du texte blanc sur chaque bouton (4,5:1 minimum).',
    ]);

    add_settings_section(
        'hpt-cookies-services',
        'Services',
        function () {
            echo '<p>Un service est actif dès que son identifiant est renseigné.</p>';
        },
        $page
    );
    add_settings_field('hpt-tac-services', '', 'hpt_options_services_callback', $page, 'hpt-cookies-services', ['layout' => 'stacked']);
}
add_action('admin_init', 'hpt_options_register_cookie_settings');

function hpt_options_tarteaucitron_banner_style_callback()
{
    $styles = [
        'top' => 'En haut',
        'middle' => 'Centré',
        'bottom' => 'En bas',
    ];
    $selected = get_option('hpt_option_tarteaucitron_banner_style', 'middle');

    echo '<div class="optimizr-segmented" role="radiogroup" aria-label="Position du bandeau">';
    foreach ($styles as $value => $label) {
        printf(
            '<label class="optimizr-segmented__option"><input type="radio" name="hpt_option_tarteaucitron_banner_style" value="%s" data-tac-position %s><span>%s</span></label>',
            esc_attr($value),
            checked($selected, $value, false),
            esc_html($label)
        );
    }
    echo '</div>';
}

function hpt_options_tarteaucitron_colors_callback()
{
    echo '<div class="optimizr-colors">';
    foreach (hpt_tac_colors() as $option => $color) {
        $value = hpt_tac_color($option);
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

function hpt_options_services_callback()
{
    echo '<div class="optimizr-services" data-optimizr-services>';
    echo '<div class="optimizr-services__search">' . hpt_icon('search', 16) . '<input type="search" placeholder="Rechercher un service" aria-label="Rechercher un service" data-optimizr-services-search></div>';

    foreach (hpt_cookie_services() as $category) {
        echo '<div class="optimizr-services__group" data-optimizr-services-group>';
        echo '<h3 class="optimizr-services__group-title">' . esc_html($category['label']) . '</h3>';

        foreach ($category['services'] as $service_key => $service) {
            $option = get_option("hpt_option_service_{$service_key}");
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

            $multi = count($service['fields']) > 1 ? ' optimizr-service__inputs--multi' : '';
            echo '<div class="optimizr-service__inputs' . $multi . '">';
            foreach ($service['fields'] as $field_key => $placeholder) {
                printf(
                    '<input type="text" name="hpt_option_service_%1$s[%2$s]" value="%3$s" placeholder="%4$s" aria-label="%5$s" spellcheck="false">',
                    esc_attr($service_key),
                    esc_attr($field_key),
                    esc_attr(isset($option[$field_key]) ? $option[$field_key] : ''),
                    esc_attr($placeholder),
                    esc_attr($service['name'] . ' – ' . $placeholder)
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
add_action('hpt_admin_aside_cookies', function () {
    $banner = hpt_tac_color('hpt_option_tarteaucitron_highlight_color');
    ?>
    <section class="optimizr-card optimizr-sticky">
        <div class="optimizr-card__header">
            <h2 class="optimizr-card__title">Aperçu du bandeau</h2>
        </div>
        <div class="optimizr-card__body">
            <div class="optimizr-tac-preview" data-tac-preview data-position="<?php echo esc_attr(get_option('hpt_option_tarteaucitron_banner_style', 'middle')); ?>">
                <div class="optimizr-tac-preview__bar" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="optimizr-tac-preview__page">
                    <div class="optimizr-tac-preview__banner" data-tac-preview-banner style="background: <?php echo esc_attr($banner); ?>; color: <?php echo esc_attr(hpt_readable_text_color($banner)); ?>;">
                        <strong>Ce site utilise des cookies</strong>
                        <p>Vous choisissez les services autorisés. Votre choix est modifiable à tout moment.</p>
                        <div class="optimizr-tac-preview__buttons">
                            <span data-tac-preview-btn="hpt_option_tarteaucitron_btn_accept" style="background: <?php echo esc_attr(hpt_tac_color('hpt_option_tarteaucitron_btn_accept')); ?>;">Tout accepter</span>
                            <span data-tac-preview-btn="hpt_option_tarteaucitron_btn_deny" style="background: <?php echo esc_attr(hpt_tac_color('hpt_option_tarteaucitron_btn_deny')); ?>;">Tout refuser</span>
                            <span data-tac-preview-btn="hpt_option_tarteaucitron_btn_customize" style="background: <?php echo esc_attr(hpt_tac_color('hpt_option_tarteaucitron_btn_customize')); ?>;">Personnaliser</span>
                        </div>
                    </div>
                </div>
            </div>
            <p class="optimizr-card__hint">Aperçu indicatif : le rendu final dépend aussi du thème.</p>
        </div>
    </section>
    <div class="optimizr-callout">
        <?php echo hpt_icon('info', 18); ?>
        <span>Google Consent Mode v2 et Bing Consent Mode sont transmis automatiquement à GA4, Google Ads et Clarity.</span>
    </div>
    <?php
});

add_action('before_hpt_options_enqueue_scripts', function () {
    if (get_option('hpt_option_enable_tarteaucitron')) {
        wp_enqueue_script('tarteaucitron', dirname(plugin_dir_url(__FILE__)) . '/assets/tarteaucitron/tarteaucitron.min.js', [], '1.19.0', true);
    }
});

// Initialisation de Tarteaucitron et déclaration des services, dans le footer du site
function hpt_options_enqueue_tracking_scripts()
{
    // Sans la librairie (chargée seulement si l'option est active), tarteaucitron.init() plante
    if (!get_option('hpt_option_enable_tarteaucitron')) {
        return;
    }

    // Initialize tarteaucitron
    echo "<script type=\"text/javascript\">
        tarteaucitron.init({
            \"privacyUrl\": \"\", /* Privacy policy url */
            \"bodyPosition\": \"bottom\", /* or top to bring it as first element for accessibility */
            \"hashtag\": \"#tarteaucitron\", /* Open the panel with this hashtag */
            \"cookieName\": \"tarteaucitron\", /* Cookie name */
            \"orientation\": \"" . esc_js(get_option('hpt_option_tarteaucitron_banner_style', 'middle')) . "\", /* Banner position (top - bottom - middle) */
            \"groupServices\": false, /* Group services by category */
            \"serviceDefaultState\": \"wait\", /* Default state (true - wait - false) */
            \"showAlertSmall\": false, /* Show the small banner on bottom right */
            \"cookieslist\": false, /* Show the cookie list */
            \"closePopup\": true, /* Show a close X on the banner */
            \"showIcon\": true, /* Show cookie icon to manage cookies */
            \"iconPosition\": \"BottomRight\", /* BottomRight, BottomLeft, TopRight and TopLeft */
            \"adblocker\": false, /* Show a Warning if an adblocker is detected */
            \"DenyAllCta\": true, /* Show the deny all button */
            \"AcceptAllCta\": true, /* Show the accept all button when highPrivacy on */
            \"highPrivacy\": true, /* HIGHLY RECOMMENDED Disable auto consent */
            \"handleBrowserDNTRequest\": true, /* If Do Not Track == 1, disallow all */
            \"removeCredit\": true, /* Remove credit link */
            \"moreInfoLink\": true, /* Show more info link */
            \"useExternalCss\": false, /* If false, the tarteaucitron.css file will be loaded */
            \"useExternalJs\": false, /* If false, the tarteaucitron.js file will be loaded */
            \"mandatory\": true, /* Show a message about mandatory cookies */
            \"mandatoryCta\": true, /* Show the disabled accept button when mandatory on */

          //\"cookieDomain\": \".my-multisite-domaine.fr\", /* Optionnel: domaine principal pour partager le consentement avec des sous domaines */
     
           //\"customCloserId\": \"\", /* Optionnel a11y: ID personnalisé pour ouvrir le panel */
          
          \"googleConsentMode\": true, /* Activer le Google Consent Mode v2 pour Google ads & GA4 */
          \"bingConsentMode\": true, /* Activer le Bing Consent Mode pour Clarity & Bing Ads */
          \"softConsentMode\": false, /* Soft consent mode (le consentement est requis pour charger les tags) */

          \"dataLayer\": false, /* Envoyer un événement dans dataLayer avec le statut des services */
          \"serverSide\": false, /* Server side seulement, les tags ne sont pas chargé côté client */
          
          \"partnersList\": false /* Afficher le détail du nombre de partenaires sur la bandeau */
          });
    </script>";

    // Ajout du style personnalisé pour la bannière et les boutons
    echo "<style type=\"text/css\">
 
     #tarteaucitron #tarteaucitronServices .tarteaucitronTitle button,
     #tarteaucitron #tarteaucitronInfo,
#tarteaucitron #tarteaucitronServices .tarteaucitronMainLine,
     #tarteaucitronRoot #tarteaucitronAlertBig{
          background-color: " . esc_html(hpt_tac_color('hpt_option_tarteaucitron_highlight_color')) . " !important;
       }
        #tarteaucitronRoot,
        #tarteaucitronRoot div#tarteaucitronSave {
            background-color: " . esc_html(hpt_tac_color('hpt_option_tarteaucitron_banner_bg')) . ";
        }
        #tarteaucitronRoot .tarteaucitronAllow {
            background-color: " . esc_html(hpt_tac_color('hpt_option_tarteaucitron_btn_accept')) . " !important;
        }
        #tarteaucitronRoot .tarteaucitronDeny {
            background-color: " . esc_html(hpt_tac_color('hpt_option_tarteaucitron_btn_deny')) . " !important;
        }
        #tarteaucitronRoot .tarteaucitronPersonalize {
            background-color: " . esc_html(hpt_tac_color('hpt_option_tarteaucitron_btn_customize')) . " !important;
        }

        #tarteaucitronRoot #tarteaucitronAlertBig,
        #tarteaucitronAlertBig #tarteaucitronDisclaimerAlert {
            color: " . esc_html(hpt_readable_text_color(hpt_tac_color('hpt_option_tarteaucitron_highlight_color'))) . " !important;
        }

        div#tarteaucitronInfo{
            border:none;
        }
        #tarteaucitronRoot #tarteaucitronIcon {
            display: none !important;
        }
    </style>";

    // Bouton flottant pour rouvrir le panneau de préférences (remplace l'icône Tarteaucitron masquée ci-dessus)
    echo '<button type="button" id="hpt-cookie-button" aria-label="Gérer les cookies" style="position:fixed;bottom:15px;right:15px;z-index:99999;display:flex;align-items:center;justify-content:center;width:44px;height:44px;padding:0;border:0;border-radius:50%;background:#fff;color:#1e1e1e;box-shadow:0 2px 8px rgba(0,0,0,.2);cursor:pointer;opacity:.6;transition:opacity .2s ease">'
        . '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg>'
        . '</button>';
    echo '<script type="text/javascript">
        (function () {
            var button = document.getElementById("hpt-cookie-button");
            button.addEventListener("mouseenter", function () { button.style.opacity = "1"; });
            button.addEventListener("mouseleave", function () { button.style.opacity = ".6"; });
            button.addEventListener("click", function () {
                if (typeof tarteaucitron !== "undefined") {
                    tarteaucitron.userInterface.openPanel();
                }
            });
        })();
    </script>';

    echo "<!-- TAC : Start -->";

    $service_keys = [];
    foreach (hpt_cookie_services() as $category) {
        $service_keys = array_merge($service_keys, array_keys($category['services']));
    }

    foreach ($service_keys as $service_key) {
        $option = get_option("hpt_option_service_{$service_key}");
        // Les valeurs sont injectées dans des chaînes JS
        $option = is_array($option) ? array_map('esc_js', array_map('trim', $option)) : [];

        if ($service_key === 'google_analytics' && !empty($option['ID'])) {
            echo "<!-- TAC : Google Analytics -->
            <script type=\"text/javascript\">
                tarteaucitron.user.gtagUa = '{$option['ID']}';
                (tarteaucitron.job = tarteaucitron.job || []).push('gtag');
            </script>";
        }
        if ($service_key === 'google_ads' && !empty($option['ID'])) {
            echo "<!-- TAC : Google Ads -->
            <script type=\"text/javascript\">
                tarteaucitron.user.googleadsId = '{$option['ID']}';
                (tarteaucitron.job = tarteaucitron.job || []).push('googleads');
            </script>";
        }

        if ($service_key === 'google_tags' && !empty($option['ID'])) {
            echo "<!-- TAC : Google tags Manager -->
            <script type=\"text/javascript\">
                tarteaucitron.user.googletagmanagerId = '{$option['ID']}';
                (tarteaucitron.job = tarteaucitron.job || []).push('googletagmanager');
            </script>";
        }

        if ($service_key === 'hotjar' && !empty($option['Site ID']) && !empty($option['Version'])) {
            echo "<!-- TAC : Hotjar -->
            <script type=\"text/javascript\">
                (tarteaucitron.job = tarteaucitron.job || []).push('hotjar');
            </script>
            <script type=\"text/javascript\">
                tarteaucitron.user.hotjarId = " . intval($option['Site ID']) . ";
                tarteaucitron.user.HotjarSv = " . intval($option['Version']) . ";
            </script>";
        }

        if ($service_key === 'matomo' && !empty($option['ID'])) {
            echo "<!-- TAC : Matomo -->
            <script type=\"text/javascript\">
                tarteaucitron.user.matomotmUrl = '{$option['ID']}';
                (tarteaucitron.job = tarteaucitron.job || []).push('matomotm');
            </script>";
        }

        if ($service_key === 'clarity' && !empty($option['ID'])) {
            echo "<!-- TAC : Clarity -->
            <script type=\"text/javascript\">
                tarteaucitron.user.clarity = '{$option['ID']}';
                (tarteaucitron.job = tarteaucitron.job || []).push('clarity');
            </script>";
        }

        if ($service_key === 'facebook_pixel' && !empty($option['Pixel ID'])) {
            echo "<!-- TAC : Facebook Pixel -->
            <script type=\"text/javascript\">
                tarteaucitron.user.facebookpixelId = '{$option['Pixel ID']}';
                (tarteaucitron.job = tarteaucitron.job || []).push('facebookpixel');
            </script>";
        }

        if ($service_key === 'google_fonts' && !empty($option['Families'])) {
            $families = explode(',', $option['Families']);
            $families = array_map('trim', $families);
            $families = array_map(function ($family) {
                return "'{$family}'";
            }, $families);
            $families = implode(', ', $families);
            echo "<!-- TAC : Google Fonts -->
    <script type=\"text/javascript\">
        tarteaucitron.user.googleFonts = [{$families}];
        (tarteaucitron.job = tarteaucitron.job || []).push('googlefonts');
    </script>";
        }

        if ($service_key === 'google_maps' && !empty($option['API Key']) && !empty($option['Zoom']) && !empty($option['Latitude']) && !empty($option['Longitude']) && !empty($option['Width']) && !empty($option['Height'])) {
            echo "<!-- TAC : Google Maps -->
            <script type=\"text/javascript\">
                tarteaucitron.user.googlemapsKey = '{$option['API Key']}';
                (tarteaucitron.job = tarteaucitron.job || []).push('googlemaps');
                        (tarteaucitron.job = tarteaucitron.job || []).push('googlemapssearch');

            </script>";
        }

        if ($service_key === 'google_recaptcha' && !empty($option['API Key'])) {
            echo "<!-- TAC : reCAPTCHA -->
            <script type=\"text/javascript\">
                tarteaucitron.user.recaptchaapi = '{$option['API Key']}';
                (tarteaucitron.job = tarteaucitron.job || []).push('recaptcha');
            </script>";
        }


        if ($service_key === 'hcaptcha' && !empty($option['Site Key'])) {
            echo "<!-- TAC : hCAPTCHA -->
            <script type=\"text/javascript\">
                tarteaucitron.user.hcaptchaSiteKey = '{$option['Site Key']}';
                (tarteaucitron.job = tarteaucitron.job || []).push('hcaptcha');
            </script>";
        }
    }

    // Services toujours proposés au visiteur, puis Google Consent Mode v2
    $always_on = [
        'facebook', 'maps_noapi', 'twitterwidgetsapi', 'dailymotion', 'youtube', 'youtubeapi',
        'gcmadstorage', 'gcmanalyticsstorage', 'gcmfunctionality', 'gcmpersonalization', 'gcmadsuserdata', 'gcmsecurity',
    ];
    echo "<script type=\"text/javascript\">\n";
    foreach ($always_on as $job) {
        echo "(tarteaucitron.job = tarteaucitron.job || []).push('{$job}');\n";
    }
    echo "</script>\n";

    echo "<!-- TAC : End -->";
}

add_action('wp_footer', 'hpt_options_enqueue_tracking_scripts', 20);
