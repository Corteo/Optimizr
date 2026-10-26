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
    // Optimizr Pro ajoute les autres services (Clarity, Matomo, Hotjar, publicité, contenus intégrés, anti-spam)
    return apply_filters('haptic_cookie_services', [
        'audience' => [
            'label' => __('Audience measurement', 'optimizr'),
            'services' => [
                'google_analytics' => ['name' => 'Google Analytics 4', 'mono' => 'GA', 'note' => 'Consent Mode v2', 'fields' => ['ID' => 'G-XXXXXXXXXX']],
                'google_tags'      => ['name' => 'Google Tag Manager', 'mono' => 'GTM', 'fields' => ['ID' => 'GTM-XXXXXXX']],
            ],
        ],
    ]);
}

/**
 * Couleurs du bandeau : option => [libellé, valeur par défaut].
 * Optimizr Pro permet de les modifier (filtre haptic_tac_color) ; le texte posé sur chaque fond
 * est calculé par haptic_readable_text_color(), quelle que soit la couleur choisie.
 */
function haptic_tac_colors()
{
    return [
        'haptic_option_tarteaucitron_highlight_color' => [__('Banner background', 'optimizr'), '#ffffff'],
        'haptic_option_tarteaucitron_banner_bg'       => [__('Preferences panel background', 'optimizr'), '#ffffff'],
        'haptic_option_tarteaucitron_btn_accept'      => [__('Accept button', 'optimizr'), '#1e7f4f'],
        'haptic_option_tarteaucitron_btn_deny'        => [__('Deny button', 'optimizr'), '#1f2937'],
        'haptic_option_tarteaucitron_btn_customize'   => [__('Customize button', 'optimizr'), '#eef0f3'],
    ];
}

/**
 * Apparence du bandeau : 'theme' (reprend le style du thème, js/optimizr-cookies-theme.js)
 * ou 'custom' (couleurs ci-dessus). Par défaut 'theme', sauf si des couleurs ont déjà été
 * enregistrées avec Optimizr Pro : le site garde alors son bandeau.
 */
function haptic_tac_appearance()
{
    $default = 'theme';
    foreach (array_keys(haptic_tac_colors()) as $option) {
        if (get_option($option) !== false) {
            $default = 'custom';
            break;
        }
    }
    $value = get_option('haptic_option_tarteaucitron_appearance', $default);

    return in_array($value, ['theme', 'custom'], true) ? $value : $default;
}

function haptic_tac_color($option)
{
    $colors = haptic_tac_colors();
    $value = sanitize_hex_color((string) apply_filters('haptic_tac_color', $colors[$option][1], $option));
    return $value ?: $colors[$option][1];
}

/**
 * Couleur de texte lisible (sombre ou blanche) sur un fond donné.
 */
function haptic_readable_text_color($hex)
{
    $dark = haptic_contrast_ratio($hex, '#1e1e1e');
    $light = haptic_contrast_ratio($hex, '#ffffff');
    if (max($dark, $light) < 4.5) {
        return '#000000'; // Fonds intermédiaires : seul le noir atteint 4.5:1
    }

    return $dark >= $light ? '#1e1e1e' : '#ffffff';
}

/** Luminance relative WCAG d'une couleur hexadécimale. */
function haptic_relative_luminance($hex)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $channels = array_map(function ($c) {
        $c = hexdec($c) / 255;
        return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    }, str_split($hex, 2));

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/** Rapport de contraste WCAG entre deux couleurs (1 à 21). */
function haptic_contrast_ratio($a, $b)
{
    $la = haptic_relative_luminance($a);
    $lb = haptic_relative_luminance($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

function haptic_options_register_cookie_settings()
{
    $group = haptic_settings_group('cookies');
    $page  = haptic_settings_page('cookies');

    register_setting($group, 'haptic_option_enable_tarteaucitron', ['sanitize_callback' => 'absint']);
    register_setting($group, 'haptic_option_tarteaucitron_appearance', [
        'sanitize_callback' => function ($value) {
            return in_array($value, ['theme', 'custom'], true) ? $value : 'theme';
        },
    ]);
    register_setting($group, 'haptic_option_tarteaucitron_banner_style', [
        'sanitize_callback' => function ($value) {
            return in_array($value, ['top', 'middle', 'bottom'], true) ? $value : 'middle';
        },
    ]);
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
        __('Consent banner', 'optimizr'),
        function () {
            /* translators: %s: URL of the Settings > Privacy screen */
            echo '<p>' . wp_kses_post(sprintf(__('Powered by Tarteaucitron. The services below are only loaded after the visitor consents. The banner links to the privacy policy chosen in <a href="%s">Settings › Privacy</a>, once that page is published.', 'optimizr'), esc_url(admin_url('options-privacy.php')))) . '</p>';
        },
        $page,
        ['toggle' => 'haptic_option_enable_tarteaucitron', 'toggle_label' => __('Show the consent banner', 'optimizr')]
    );
    add_settings_field('haptic-tac-position', __('Position', 'optimizr'), 'haptic_options_tarteaucitron_banner_style_callback', $page, 'haptic-cookies-banner');
    add_settings_field('haptic-tac-appearance', __('Appearance', 'optimizr'), 'haptic_options_tarteaucitron_appearance_callback', $page, 'haptic-cookies-banner', [
        'description' => __('With "Site theme", the banner uses the background, text, fonts and buttons of your theme.', 'optimizr'),
    ]);

    add_settings_section(
        'haptic-cookies-services',
        __('Services', 'optimizr'),
        function () {
            echo '<p>' . esc_html__('A service is active as soon as its ID is filled in.', 'optimizr') . '</p>';
        },
        $page
    );
    add_settings_field('haptic-tac-services', '', 'haptic_options_services_callback', $page, 'haptic-cookies-services', ['layout' => 'stacked']);
}
add_action('admin_init', 'haptic_options_register_cookie_settings');

function haptic_options_tarteaucitron_banner_style_callback()
{
    $styles = [
        'top' => _x('Top', 'cookie banner position', 'optimizr'),
        'middle' => _x('Center', 'cookie banner position', 'optimizr'),
        'bottom' => _x('Bottom', 'cookie banner position', 'optimizr'),
    ];
    $selected = get_option('haptic_option_tarteaucitron_banner_style', 'middle');

    echo '<div class="optimizr-segmented" role="radiogroup" aria-label="' . esc_attr__('Banner position', 'optimizr') . '">';
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

function haptic_options_tarteaucitron_appearance_callback()
{
    $choices = [
        'theme'  => __('Site theme', 'optimizr'),
        'custom' => __('Banner colors', 'optimizr'),
    ];
    $selected = haptic_tac_appearance();

    echo '<div class="optimizr-segmented" role="radiogroup" aria-label="' . esc_attr__('Banner appearance', 'optimizr') . '">';
    foreach ($choices as $value => $label) {
        printf(
            '<label class="optimizr-segmented__option"><input type="radio" name="haptic_option_tarteaucitron_appearance" value="%s" data-tac-appearance %s><span>%s</span></label>',
            esc_attr($value),
            checked($selected, $value, false),
            esc_html($label)
        );
    }
    echo '</div>';
}

function haptic_options_services_callback()
{
    echo '<div class="optimizr-services" data-optimizr-services>';
    echo '<div class="optimizr-services__search">' . wp_kses(haptic_icon('search', 16), haptic_kses_ui()) . '<input type="search" placeholder="' . esc_attr__('Search for a service', 'optimizr') . '" aria-label="' . esc_attr__('Search for a service', 'optimizr') . '" data-optimizr-services-search></div>';

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

            echo '<span class="optimizr-status optimizr-status--' . ($active ? 'ok' : 'off') . '">' . ($active ? esc_html_x('Active', 'service status', 'optimizr') : esc_html_x('Inactive', 'service status', 'optimizr')) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    echo '<p class="optimizr-services__empty" data-optimizr-services-empty hidden>' . esc_html__('No service matches this search.', 'optimizr') . '</p>';
    echo '<p class="optimizr-services__footer">' . esc_html__('Always offered to visitors, no setup needed: YouTube, Dailymotion, X (Twitter), Facebook, Google Maps without a key.', 'optimizr') . '</p>';
    echo '</div>';
}

/** Fond et texte lisible d'un bouton de l'aperçu. */
function haptic_tac_preview_button_style($option)
{
    $color = haptic_tac_color($option);
    return 'background: ' . $color . '; color: ' . haptic_readable_text_color($color) . ';';
}

/**
 * Style du thème pour l'aperçu de l'apparence « Thème du site » : fond, texte, bouton et arrondi
 * des styles globaux (theme.json). Sans theme.json, valeurs neutres : le site lit le vrai style.
 */
function haptic_tac_theme_preview()
{
    $styles = function_exists('wp_get_global_styles') ? wp_get_global_styles() : [];
    $palette = [];
    if (function_exists('wp_get_global_settings')) {
        foreach ((array) wp_get_global_settings(['color', 'palette']) as $origin) {
            foreach ((array) $origin as $color) {
                if (isset($color['slug'], $color['color'])) {
                    $palette[$color['slug']] = $color['color'];
                }
            }
        }
    }
    // « var:preset|color|base » ou « var(--wp--preset--color--base) » => couleur de la palette
    $resolve = function ($value) use ($palette) {
        $value = is_string($value) ? $value : '';
        if (preg_match('/^var(?::preset\|color\||\(--wp--preset--color--)([a-z0-9-]+)/i', $value, $m)) {
            $value = isset($palette[$m[1]]) ? $palette[$m[1]] : '';
        }
        return sanitize_hex_color($value) ?: null;
    };

    $bg = $resolve($styles['color']['background'] ?? '') ?: '#ffffff';
    $text = $resolve($styles['color']['text'] ?? '');
    $text = $text && haptic_contrast_ratio($text, $bg) >= 4.5 ? $text : haptic_readable_text_color($bg);
    $button = $resolve($styles['elements']['button']['color']['background'] ?? '');
    $button = $button && haptic_contrast_ratio($button, $bg) >= 1.2 ? $button : $text;
    $button_text = $resolve($styles['elements']['button']['color']['text'] ?? '');
    $button_text = $button_text && haptic_contrast_ratio($button_text, $button) >= 4.5 ? $button_text : haptic_readable_text_color($button);
    $radius = $styles['elements']['button']['border']['radius'] ?? '';
    $radius = is_string($radius) && preg_match('/^\d+(\.\d+)?(px|rem|em|%)$/', $radius) ? $radius : '7px';

    return [
        'banner'    => "background: {$bg}; color: {$text};",
        'button'    => "background: {$button}; color: {$button_text}; border-radius: {$radius};",
        'customize' => "background: transparent; color: {$text}; border-radius: {$radius}; box-shadow: inset 0 0 0 1px {$text};",
    ];
}

// Panneau latéral : aperçu du bandeau, mis à jour en direct par js/optimizr-admin.js
add_action('haptic_admin_aside_cookies', function () {
    $banner = haptic_tac_color('haptic_option_tarteaucitron_highlight_color');
    $appearance = haptic_tac_appearance();
    $theme = haptic_tac_theme_preview();
    ?>
    <section class="optimizr-card optimizr-sticky">
        <div class="optimizr-card__header">
            <h2 class="optimizr-card__title"><?php esc_html_e('Banner preview', 'optimizr'); ?></h2>
        </div>
        <div class="optimizr-card__body">
            <div class="optimizr-tac-preview" data-tac-preview data-position="<?php echo esc_attr(get_option('haptic_option_tarteaucitron_banner_style', 'middle')); ?>">
                <div class="optimizr-tac-preview__bar" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="optimizr-tac-preview__page">
                    <div class="optimizr-tac-preview__banner" data-tac-preview-look="theme" style="<?php echo esc_attr($theme['banner']); ?>"<?php echo $appearance === 'theme' ? '' : ' hidden'; ?>>
                        <strong><?php esc_html_e('This site uses cookies', 'optimizr'); ?></strong>
                        <p><?php esc_html_e('You choose which services are allowed. You can change your choice at any time.', 'optimizr'); ?></p>
                        <div class="optimizr-tac-preview__buttons">
                            <span style="<?php echo esc_attr($theme['button']); ?>"><?php esc_html_e('Accept all', 'optimizr'); ?></span>
                            <span style="<?php echo esc_attr($theme['button']); ?>"><?php esc_html_e('Deny all', 'optimizr'); ?></span>
                            <span style="<?php echo esc_attr($theme['customize']); ?>"><?php esc_html_e('Customize', 'optimizr'); ?></span>
                        </div>
                    </div>
                    <div class="optimizr-tac-preview__banner" data-tac-preview-banner data-tac-preview-look="custom" style="background: <?php echo esc_attr($banner); ?>; color: <?php echo esc_attr(haptic_readable_text_color($banner)); ?>;"<?php echo $appearance === 'custom' ? '' : ' hidden'; ?>>
                        <strong><?php esc_html_e('This site uses cookies', 'optimizr'); ?></strong>
                        <p><?php esc_html_e('You choose which services are allowed. You can change your choice at any time.', 'optimizr'); ?></p>
                        <div class="optimizr-tac-preview__buttons">
                            <span data-tac-preview-btn="haptic_option_tarteaucitron_btn_accept" style="<?php echo esc_attr(haptic_tac_preview_button_style('haptic_option_tarteaucitron_btn_accept')); ?>"><?php esc_html_e('Accept all', 'optimizr'); ?></span>
                            <span data-tac-preview-btn="haptic_option_tarteaucitron_btn_deny" style="<?php echo esc_attr(haptic_tac_preview_button_style('haptic_option_tarteaucitron_btn_deny')); ?>"><?php esc_html_e('Deny all', 'optimizr'); ?></span>
                            <span data-tac-preview-btn="haptic_option_tarteaucitron_btn_customize" style="<?php echo esc_attr(haptic_tac_preview_button_style('haptic_option_tarteaucitron_btn_customize')); ?>"><?php esc_html_e('Customize', 'optimizr'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <p class="optimizr-card__hint"><?php esc_html_e('Approximate preview: the final look also depends on the theme.', 'optimizr'); ?></p>
        </div>
    </section>
    <div class="optimizr-callout">
        <?php echo wp_kses(haptic_icon('info', 18), haptic_kses_ui()); ?>
        <span><?php echo esc_html(apply_filters('haptic_cookies_consent_note', __('Google Consent Mode v2 is sent automatically to Google Analytics 4 and Google Tag Manager.', 'optimizr'))); ?></span>
    </div>
    <?php
});

// Tarteaucitron, sa configuration et les services déclarés, dans le footer du site
add_action('haptic_before_enqueue_scripts', function () {
    if (!get_option('haptic_option_enable_tarteaucitron')) {
        return;
    }

    $assets = dirname(plugin_dir_url(__FILE__));
    wp_enqueue_script('optimizr-tarteaucitron', $assets . '/assets/tarteaucitron/tarteaucitron.min.js', [], '1.21.0', true);
    // Imprimé juste après la librairie : tarteaucitron.init() en a besoin
    wp_add_inline_script('optimizr-tarteaucitron', haptic_tarteaucitron_inline_script());

    // Feuille de Tarteaucitron (useExternalCss), puis le thème d'Optimizr et ses couleurs
    wp_enqueue_style('optimizr-tarteaucitron-base', $assets . '/assets/tarteaucitron/css/tarteaucitron.min.css', [], '1.21.0');
    wp_enqueue_style('optimizr-tarteaucitron', $assets . '/css/optimizr-cookies.css', ['optimizr-tarteaucitron-base'], HAPTIC_OPTIMIZR_VERSION);

    // Apparence « Thème du site » : couleurs, police et boutons lus sur la page
    if (haptic_tac_appearance() === 'theme') {
        wp_enqueue_script('optimizr-cookies-theme', $assets . '/js/optimizr-cookies-theme.js', [], HAPTIC_OPTIMIZR_VERSION, true);
    }
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
        'useExternalCss'          => true, // Feuille chargée par WordPress, avant css/optimizr-cookies.css
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

    // Position lue par css/optimizr-cookies.css (Tarteaucitron donne la même classe au haut et au milieu)
    $js = 'document.documentElement.setAttribute("data-optimizr-tac", ' . wp_json_encode($config['orientation']) . ");\n";
    $js .= 'tarteaucitron.init(' . wp_json_encode($config, JSON_HEX_TAG) . ");\n";

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
                case 'google_tags':
                    if (!empty($option['ID'])) {
                        $user['googletagmanagerId'] = $option['ID'];
                        $jobs[] = 'googletagmanager';
                    }
                    break;
                default:
                    // Service ajouté par une extension : variables Tarteaucitron et services à charger
                    $service = apply_filters('haptic_tarteaucitron_service', ['user' => [], 'jobs' => []], $service_key, $option);
                    $user = array_merge($user, (array) ($service['user'] ?? []));
                    $jobs = array_merge($jobs, (array) ($service['jobs'] ?? []));
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

/**
 * Couleurs de la bannière, du panneau et des boutons, choisies dans l'onglet Cookies,
 * en variables lues par css/optimizr-cookies.css. Chaque fond reçoit sa couleur de texte lisible.
 * Posées sur :root : avec l'apparence « Thème du site », js/optimizr-cookies-theme.js les remplace
 * par le style de <html>, et elles restent en secours.
 */
function haptic_tarteaucitron_inline_style()
{
    $surfaces = [
        'banner'    => 'haptic_option_tarteaucitron_highlight_color',
        'panel'     => 'haptic_option_tarteaucitron_banner_bg',
        'accept'    => 'haptic_option_tarteaucitron_btn_accept',
        'deny'      => 'haptic_option_tarteaucitron_btn_deny',
        'customize' => 'haptic_option_tarteaucitron_btn_customize',
    ];
    $banner = haptic_tac_color($surfaces['banner']);

    $vars = '';
    foreach ($surfaces as $name => $option) {
        $color = haptic_tac_color($option);
        $text = haptic_readable_text_color($color);
        $vars .= "--otac-{$name}-bg:{$color};--otac-{$name}-text:{$text};";
        // Texte secondaire adouci seulement si le contraste le permet (reste au-dessus de 4.5:1)
        if (in_array($name, ['banner', 'panel'], true)) {
            $vars .= "--otac-{$name}-mix:" . (haptic_contrast_ratio($color, $text) >= 9 ? '80%' : '100%') . ';';
        }
        // Bouton de la couleur du bandeau (ou presque) : un contour le détache du fond
        if (!in_array($name, ['banner', 'panel'], true)) {
            $line = haptic_contrast_ratio($color, $banner) < 1.5 ? "color-mix(in srgb,{$text} 22%,transparent)" : 'transparent';
            $vars .= "--otac-{$name}-line:{$line};";
        }
    }

    return ':root{' . $vars . '}#tarteaucitronRoot #tarteaucitronIcon{display:none !important}';
}

// Bouton flottant pour rouvrir le panneau de préférences (remplace l'icône Tarteaucitron masquée)
add_action('wp_footer', 'haptic_cookie_button');
function haptic_cookie_button()
{
    if (!get_option('haptic_option_enable_tarteaucitron')) {
        return;
    }
    ?>
    <button type="button" id="haptic-cookie-button" aria-label="<?php esc_attr_e('Manage cookies', 'optimizr'); ?>" style="position:fixed;bottom:15px;right:15px;z-index:99999;display:flex;align-items:center;justify-content:center;width:44px;height:44px;padding:0;border:0;border-radius:50%;background:#fff;color:#1e1e1e;box-shadow:0 2px 8px rgba(0,0,0,.2);cursor:pointer;opacity:.6;transition:opacity .2s ease"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg></button>
    <?php
}
