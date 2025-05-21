<?php
function hpt_options_add_services_tab()
// Function to add a new tab and its settings
{
    add_settings_section(
        'hpt-options-services-section',
        'Services',
        'hpt_options_services_section_callback',
        'hpt-options'
    );

    $services = [
        ['google_analytics', 'Google Analytics 4', 'ID'],
        ['google_ads', 'Google Ads', 'ID'],
        ['google_tags', 'Google Tag\’s Manager', 'ID'],
        ['clarity', 'Clarity', 'ID'],
        ['matomo', 'Matomo', 'ID'],
        ['hotjar', 'Hotjar', 'Site ID', 'Version'],
        ['facebook_pixel', 'Facebook Pixel', 'Pixel ID'],
        ['google_fonts', 'Google Fonts', 'Families'], // Added Google Fonts service
        ['google_maps', 'Google Maps', 'API Key', 'Zoom', 'Latitude', 'Longitude', 'Width', 'Height'], // Added Google Maps service
        ['google_recaptcha', 'Google reCAPTCHA', 'API Key'], // Added Google reCAPTCHA
        ['hcaptcha', 'hCAPTCHA', 'Site Key'], // Added hCAPTCHA



    ];




    foreach ($services as $service) {
        $service_key = $service[0];
        $service_name = $service[1];
        $service_ids = array_slice($service, 2);

        add_settings_field(
            "hpt-options-service-{$service_key}",
            $service_name,
            "hpt_options_service_callback",
            'hpt-options',
            'hpt-options-services-section',
            ['service_key' => $service_key, 'service_name' => $service_name, 'service_ids' => $service_ids]
        );

        register_setting('hpt-options-settings-group', "hpt_option_service_{$service_key}");
    }
}

// Callback function for the services section
function hpt_options_services_section_callback()
{
    echo '<p> Ajouter les services de suivi pour votre site WordPress.</p>';
}

function hpt_options_add_tarteaucitron_field()
{
    add_settings_field(
        'hpt-options-enable-tarteaucitron',
        'Activer Tarte au citron',
        'hpt_options_enable_tarteaucitron_callback',
        'hpt-options',
        'hpt-options-services-section'
    );
    register_setting('hpt-options-settings-group', 'hpt_option_enable_tarteaucitron');

    add_settings_field(
        'hpt-options-tarteaucitron-banner-style',
        'Style de bannière Tarte au Citron',
        'hpt_options_tarteaucitron_banner_style_callback',
        'hpt-options',
        'hpt-options-services-section'
    );
    register_setting('hpt-options-settings-group', 'hpt_option_tarteaucitron_banner_style');



    add_settings_field(
        'hpt-options-tarteaucitron-banner-bg',
        'Couleur de fond de la bannière',
        'hpt_options_tarteaucitron_banner_bg_callback',
        'hpt-options',
        'hpt-options-services-section'
    );
    register_setting('hpt-options-settings-group', 'hpt_option_tarteaucitron_banner_bg');

    add_settings_field(
        'hpt-options-tarteaucitron-btn-accept',
        'Couleur bouton Accepter',
        'hpt_options_tarteaucitron_btn_accept_callback',
        'hpt-options',
        'hpt-options-services-section'
    );
    register_setting('hpt-options-settings-group', 'hpt_option_tarteaucitron_btn_accept');

    add_settings_field(
        'hpt-options-tarteaucitron-btn-deny',
        'Couleur bouton Refuser',
        'hpt_options_tarteaucitron_btn_deny_callback',
        'hpt-options',
        'hpt-options-services-section'
    );
    register_setting('hpt-options-settings-group', 'hpt_option_tarteaucitron_btn_deny');

    add_settings_field(
        'hpt-options-tarteaucitron-btn-customize',
        'Couleur bouton Personnaliser',
        'hpt_options_tarteaucitron_btn_customize_callback',
        'hpt-options',
        'hpt-options-services-section'
    );
    register_setting('hpt-options-settings-group', 'hpt_option_tarteaucitron_btn_customize');

    add_settings_field(
        'hpt-options-tarteaucitron-highlight-color',
        'Couleur des éléments de service (bordures, listes)',
        'hpt_options_tarteaucitron_highlight_color_callback',
        'hpt-options',
        'hpt-options-services-section'
    );
    register_setting('hpt-options-settings-group', 'hpt_option_tarteaucitron_highlight_color');
}

// function hpt_options_enable_tarteaucitron_callback()
// {
//     $option = get_option('hpt_option_enable_tarteaucitron');
//     $checked = $option ? 'checked' : '';
//     echo '<label><input type="checkbox" name="hpt_option_enable_tarteaucitron" value="1" ' . $checked . ' /> Activer la gestion des Cookies (RGPD)</label>';
// }

function hpt_options_tarteaucitron_banner_style_callback()
{
    $styles = [
        'top' => 'Bannière en haut',
        'bottom' => 'Bannière en bas',
        'middle' => 'Centré (par défaut)'
    ];
    $selected = get_option('hpt_option_tarteaucitron_banner_style', 'middle');
    echo '<select name="hpt_option_tarteaucitron_banner_style">';
    foreach ($styles as $value => $label) {
        $is_selected = $selected === $value ? 'selected' : '';
        echo "<option value=\"$value\" $is_selected>$label</option>";
    }
    echo '</select>';
}

function hpt_options_enable_tarteaucitron_callback()
{
    $option = get_option('hpt_option_enable_tarteaucitron');
    $checked = $option ? 'checked' : '';
    echo '<label><input type="checkbox" name="hpt_option_enable_tarteaucitron" value="1" ' . $checked . ' /> Activer la gestion des Cookies (RGPD)</label>';
}


add_action('admin_init', 'hpt_options_add_tarteaucitron_field');


// Generic callback function for the service settings
function hpt_options_service_callback($args)
{

    // // Check if cookie management is enabled
    // if (!get_option('hpt_option_enable_tarteaucitron')) {
    //     echo "<p>Activez la gestion des cookies pour activer des services de suivi tiers.</p>";
    //     return;
    // }

    $service_key = $args['service_key'];
    $service_name = $args['service_name'];
    $service_ids = $args['service_ids'];
    $option = get_option("hpt_option_service_{$service_key}");

    foreach ($service_ids as $id) {
        $value = isset($option[$id]) ? esc_attr($option[$id]) : '';
        echo '<label>' . esc_html($id) . ': <input type="text" name="hpt_option_service_' . $service_key . '[' . esc_attr($id) . ']" value="' . $value . '" /></label><br>';
    }
}

// Hook to add the new tab and its settings
add_action('add_hpt_options_tabs', 'hpt_options_add_services_tab');


add_action('before_hpt_options_enqueue_scripts', function () {
    if (get_option('hpt_option_enable_tarteaucitron')) {
        wp_enqueue_script('tarteaucitron', dirname(plugin_dir_url(__FILE__)) . '/assets/tarteaucitron/tarteaucitron.min.js', [], '1.19.0', true);
    }
});

// Function to enqueue the tracking scripts
function hpt_options_enqueue_tracking_scripts()
{
    // // Enqueue tarteaucitron.js script
    // wp_enqueue_script('tarteaucitron', plugin_dir_url(__FILE__) . 'assets/tarteaucitron/tarteaucitron.min.js', [], '1.19.0', true);

    // Check if cookie management is enabled
    // if (!get_option('hpt_option_enable_tarteaucitron')) {
    //     // echo "<p>Activez la gestion des cookies pour activer des services de suivi tiers.</p>";
    //     return;
    // }

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
          background-color: " . esc_html(get_option('hpt_option_tarteaucitron_highlight_color', '#e0e0e0')) . " !important;
       }
        #tarteaucitronRoot,
        #tarteaucitronRoot div#tarteaucitronSave {
            background-color: " . esc_html(get_option('hpt_option_tarteaucitron_banner_bg', '#f9f9f9')) . ";
        }
        #tarteaucitronRoot .tarteaucitronAllow {
            background-color: " . esc_html(get_option('hpt_option_tarteaucitron_btn_accept', '#00c853')) . " !important;
        }
        #tarteaucitronRoot .tarteaucitronDeny {
            background-color: " . esc_html(get_option('hpt_option_tarteaucitron_btn_deny', '#d50000')) . " !important;
        }
        #tarteaucitronRoot .tarteaucitronPersonalize {
            background-color: " . esc_html(get_option('hpt_option_tarteaucitron_btn_customize', '#ff9800')) . " !important;
        }

        div#tarteaucitronInfo{
            border:none;
        }
       
    </style>";

    $services = [
        'google_analytics' => 'ID',
        'google_tags' => 'ID',
        'google_ads' => 'ID',
        'clarity' => 'ID',
        'matomo' => 'ID',
        'hotjar' => ['Site ID', 'Version'],
        'facebook_pixel' => 'Pixel ID',
        'google_fonts' => 'Families', // Added Google Fonts service
        'google_maps' => ['API Key', 'Zoom', 'Latitude', 'Longitude', 'Width', 'Height'], // Added Google Maps service
        'google_recaptcha' => ['API Key'], // Added reCAPTCHA service
        'hcaptcha' => ['Site Key'] // Added hCAPTCHA service


    ];

    echo "<!-- TAC : Start -->";

    foreach ($services as $service_key => $service_ids) {
        $option = get_option("hpt_option_service_{$service_key}");

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
                tarteaucitron.user.hotjarId = {$option['Site ID']};
                tarteaucitron.user.HotjarSv = {$option['Version']};
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

        if ($service_key === 'google_recaptcha' && !empty($option['API Key']) && !empty($option['Site Key'])) {
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

    echo "<!-- TAC : Facebook -->
            <script type=\"text/javascript\">
                (tarteaucitron.job = tarteaucitron.job || []).push('facebook');
            </script>";

    echo "<!-- TAC : Maps (No Api) -->
<script type=\"text/javascript\">
        (tarteaucitron.job = tarteaucitron.job || []).push('maps_noapi');
        </script>";

    echo "<!-- TAC : X (Twitter) -->
         <script type=\"text/javascript\">
        (tarteaucitron.job = tarteaucitron.job || []).push('twitterwidgetsapi');
        </script>";
    echo "<!-- TAC : Dailymotion -->
             <script type=\"text/javascript\">
        (tarteaucitron.job = tarteaucitron.job || []).push('dailymotion');
        </script>";
    echo "<!-- TAC : Youtube -->
             <script type=\"text/javascript\">
        (tarteaucitron.job = tarteaucitron.job || []).push('youtube');
        </script>";
    echo "<!-- TAC : Youtube (JS api) -->
             <script type=\"text/javascript\">
        (tarteaucitron.job = tarteaucitron.job || []).push('youtubeapi');
        </script>";
    echo "<!-- TAC : Google Consent Mode V2 -->
             <script type=\"text/javascript\">
(tarteaucitron.job = tarteaucitron.job || []).push('gcmadstorage');
(tarteaucitron.job = tarteaucitron.job || []).push('gcmanalyticsstorage');
(tarteaucitron.job = tarteaucitron.job || []).push('gcmfunctionality');
(tarteaucitron.job = tarteaucitron.job || []).push('gcmpersonalization');
(tarteaucitron.job = tarteaucitron.job || []).push('gcmadsuserdata');
(tarteaucitron.job = tarteaucitron.job || []).push('gcmsecurity');
    </script>";



    echo "<!-- TAC : End -->";
}

// Hook to enqueue the tracking scripts
add_action('wp_footer', 'hpt_options_enqueue_tracking_scripts', 20);


// Function to add a new section for displaying the generated script
function hpt_options_add_generated_script_section()
{
    add_settings_section(
        'hpt-options-generated-script-section',
        'Generated Script',
        'hpt_options_generated_script_section_callback',
        'hpt-options'
    );

    add_settings_field(
        'hpt-options-generated-script',
        'Copy the script below:',
        'hpt_options_generated_script_callback',
        'hpt-options',
        'hpt-options-generated-script-section'
    );
}

// Callback function for the generated script section
function hpt_options_generated_script_section_callback()
{
    echo '<p>Copy the script below and paste it into your site where needed.</p>';
}

// Callback function to display the generated script
function hpt_options_generated_script_callback()
{
    $script = hpt_options_generate_script();
    echo '<textarea readonly style="width: 100%; height: 200px;">' . esc_textarea($script) . '</textarea>';
    // echo $script;
}

function hpt_options_generate_script()
{
    ob_start();

    $services = [
        'google_maps' => ['API Key', 'Zoom', 'Latitude', 'Longitude', 'Width', 'Height'],
        'google_recaptcha' => ['API Key'], // Added reCAPTCHA service
        'hcaptcha' => ['Site Key'] // Added hCAPTCHA service


    ];

    foreach ($services as $service_key => $service_ids) {
        $option = get_option("hpt_option_service_{$service_key}");


        if ($service_key === 'google_maps' && !empty($option['API Key']) && !empty($option['Zoom']) && !empty($option['Latitude']) && !empty($option['Longitude']) && !empty($option['Width']) && !empty($option['Height'])) {
            echo "<!-- TAC : Google Maps script a coller -->
           <div class=\"googlemaps-canvas\" zoom=\"{$option['Zoom']}\" latitude=\"{$option['Latitude']}\" longitude=\"{$option['Longitude']}\" style=\"width: {$option['Width']}px; height: {$option['Height']}px;\"></div>
          \n
          Search query
           <div class=\"googlemapssearch\" data-search=\"SEARCHWORDS\" data-api-key=\"{$option['API Key']}\" width=\"{$option['Width']}\" height=\"{$option['Height']}\"></div>
          \n
          (No API)
          <div class=\"googlemaps_embed\" width=\"{$option['Width']}\" height=\"{$option['Height']}\" id=\"id\"></iframe>";
        }

        if ($service_key === 'google_recaptcha' && !empty($option['Site Key'])) {
            echo "<!-- TAC : reCAPTCHA script a coller -->
           <div class=\"g-recaptcha\" data-sitekey=\"{$option['API Key']}\"></div>";
        }

        if ($service_key === 'hcaptcha' && !empty($option['Site Key'])) {
            echo "<!-- TAC : hCAPTCHA script a coller -->
           <div class=\"h-captcha\" data-sitekey=\"{$option['Site Key']}\"></div>";
        }


        echo "\n";
        echo "\n";
    }
    return ob_get_clean();
}



// Function to add a help button for Facebook like and share buttons
function hpt_options_add_facebook_help_button()
{
    add_settings_section(
        'hpt-options-facebook-help-section',
        '<button type="button" onclick="toggleFacebookHelp()">Facebook Like and Share Buttons</button>',
        'hpt_options_facebook_help_section_callback',
        'hpt-options'
    );
    add_settings_field(
        'hpt-options-facebook-help',
        'Help for Facebook Like and Share Buttons:',
        'hpt_options_facebook_help_callback',
        'hpt-options',
        'hpt-options-facebook-help-section'
    );
}

// Callback function for the Facebook help section
function hpt_options_facebook_help_section_callback()
{
    echo "<div id=\"facebook-help-section\" style=\"display:none;\">";
    echo '<p>Below are examples of Facebook Like and Share buttons you can use on your site.</p>';
}

// Callback function to display the Facebook help content
function hpt_options_facebook_help_callback()
{
    $examples = [
        '<div class="fb-like" data-layout="standard" data-action="like" data-share="true"></div>',
        '<div class="fb-like" data-layout="standard" data-action="like" data-share="false"></div>',
        '<div class="fb-like" data-layout="box_count" data-action="like" data-share="true"></div>',
        '<div class="fb-like" data-layout="box_count" data-action="like" data-share="false"></div>',
        '<div class="fb-like" data-layout="button_count" data-action="like" data-share="true"></div>',
        '<div class="fb-like" data-layout="button_count" data-action="like" data-share="false"></div>',
        '<div class="fb-like" data-layout="button" data-action="like" data-share="true"></div>',
        '<div class="fb-like" data-layout="button" data-action="like" data-share="false"></div>',
        '<div class="fb-like" data-layout="standard" data-action="recommend" data-share="true"></div>',
        '<div class="fb-like" data-layout="standard" data-action="recommend" data-share="false"></div>',
        '<div class="fb-like" data-layout="box_count" data-action="recommend" data-share="true"></div>',
        '<div class="fb-like" data-layout="box_count" data-action="recommend" data-share="false"></div>',
        '<div class="fb-like" data-layout="button_count" data-action="recommend" data-share="true"></div>',
        '<div class="fb-like" data-layout="button_count" data-action="recommend" data-share="false"></div>',
        '<div class="fb-like" data-layout="button" data-action="recommend" data-share="true"></div>',
        '<div class="fb-like" data-layout="button" data-action="recommend" data-share="false"></div>'
    ];

    $images = [
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_1.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_2.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_3.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_4.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_5.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_6.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_7.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_8.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_9.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_10.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_11.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_12.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_13.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_14.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_15.png',
        'assets/tarteaucitron/imgs/facebook_btns/fb_img_16.png'
    ];

    // echo '<div id="facebook-help-content" style="display:none;">';
    foreach ($examples as $index => $example) {
        echo '<img src="' . dirname(plugin_dir_url(__FILE__)) . '/' . $images[$index] . '" alt="Facebook Button Example"><br>';
        echo '<code>' . htmlspecialchars($example) . '</code><br><br>';
    }
    echo '</div>';

    // JavaScript to toggle the display of the Facebook help section
    echo '<script type="text/javascript">
function toggleFacebookHelp() {
    var helpSection = document.getElementById("facebook-help-section");
    var helpContent = document.getElementById("facebook-help-content");
    if (helpSection.style.display === "none") {
        helpSection.style.display = "block";
        helpContent.style.display = "block";
    } else {
        helpSection.style.display = "none";
        helpContent.style.display = "none";
    }
}
</script>';
}



// // Hook to add the Facebook help button section
// add_action('admin_init', 'hpt_options_add_facebook_help_button');

// // Hook to add the new section for displaying the generated script
// add_action('admin_init', 'hpt_options_add_generated_script_section');

// Fonctions de callback pour les champs de couleur personnalisés Tarteaucitron
function hpt_options_tarteaucitron_banner_bg_callback()
{
    $value = get_option('hpt_option_tarteaucitron_banner_bg', '#f9f9f9');
    echo '<input type="color" name="hpt_option_tarteaucitron_banner_bg" value="' . esc_attr($value) . '">';
}

function hpt_options_tarteaucitron_btn_accept_callback()
{
    $value = get_option('hpt_option_tarteaucitron_btn_accept', '#00c853');
    echo '<input type="color" name="hpt_option_tarteaucitron_btn_accept" value="' . esc_attr($value) . '">';
}

function hpt_options_tarteaucitron_btn_deny_callback()
{
    $value = get_option('hpt_option_tarteaucitron_btn_deny', '#d50000');
    echo '<input type="color" name="hpt_option_tarteaucitron_btn_deny" value="' . esc_attr($value) . '">';
}

function hpt_options_tarteaucitron_btn_customize_callback()
{
    $value = get_option('hpt_option_tarteaucitron_btn_customize', '#ff9800');
    echo '<input type="color" name="hpt_option_tarteaucitron_btn_customize" value="' . esc_attr($value) . '">';
}

function hpt_options_tarteaucitron_highlight_color_callback()
{
    $value = get_option('hpt_option_tarteaucitron_highlight_color', '#e0e0e0');
    echo '<input type="color" name="hpt_option_tarteaucitron_highlight_color" value="' . esc_attr($value) . '">';
}
