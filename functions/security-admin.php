<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Onglet Sécurité : réglages du module (functions/security.php).
 */

add_action('admin_init', 'haptic_sec_register_settings');

function haptic_sec_register_settings()
{
    $group = haptic_settings_group('security');
    $page = haptic_settings_page('security');

    $toggles = ['limit_login', 'proxy_cloudflare', 'generic_errors', 'login_alert', 'two_factor'];
    foreach ($toggles as $toggle) {
        register_setting($group, 'haptic_sec_' . $toggle, ['sanitize_callback' => 'absint']);
    }
    register_setting($group, 'haptic_sec_login_slug', ['sanitize_callback' => 'haptic_sec_sanitize_login_slug']);
    register_setting($group, 'haptic_sec_login_max', ['sanitize_callback' => function ($v) {
        return min(20, max(2, absint($v) ?: 5));
    }]);
    register_setting($group, 'haptic_sec_login_lockout', ['sanitize_callback' => function ($v) {
        return min(1440, max(1, absint($v) ?: 15));
    }]);
    register_setting($group, 'haptic_sec_login_whitelist', ['sanitize_callback' => function ($v) {
        $ips = array_filter(array_map('trim', preg_split('/\R/', (string) $v)), function ($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP);
        });
        return implode("\n", array_unique($ips));
    }]);
    register_setting($group, 'haptic_sec_trusted_proxies', ['sanitize_callback' => function ($v) {
        $ranges = array_filter(array_map('trim', preg_split('/\R/', (string) $v)), 'haptic_sec_is_ip_range');
        return implode("\n", array_unique($ranges));
    }]);
    register_setting($group, 'haptic_sec_two_factor_roles', ['sanitize_callback' => function ($v) {
        return array_values(array_intersect((array) $v, array_keys(wp_roles()->get_names())));
    }]);

    /* Connexion */
    add_settings_section('haptic_sec_login_section', __('Login', 'optimizr'), function () {
        echo '<p>' . esc_html__('Protects the login page against bots that try passwords over and over.', 'optimizr') . '</p>';
    }, $page);

    add_settings_field('haptic_sec_login_slug', __('Login address', 'optimizr'), 'haptic_sec_login_slug_callback', $page, 'haptic_sec_login_section', [
        'label_for' => 'haptic_sec_login_slug',
        'layout' => 'stacked',
    ]);
    add_settings_field('haptic_sec_limit_login', __('Limit attempts', 'optimizr'), 'haptic_sec_limit_login_callback', $page, 'haptic_sec_login_section', [
        'description' => __('Temporarily locks out an IP address after several wrong passwords.', 'optimizr'),
    ]);
    add_settings_field('haptic_sec_trusted_proxies', __('Trusted proxies', 'optimizr'), 'haptic_sec_proxies_callback', $page, 'haptic_sec_login_section', [
        'description' => __('Behind Cloudflare, a CDN or a load balancer, all visitors seem to come from the proxy\'s address: a lockout would block everyone.', 'optimizr'),
    ]);
    haptic_sec_add_toggle('generic_errors', __('Neutral error messages', 'optimizr'), __('Shows "Incorrect username or password" without saying which one: a bot can no longer guess existing usernames.', 'optimizr'), 'haptic_sec_login_section');
    haptic_sec_add_toggle('login_alert', __('Login alert', 'optimizr'), __('Emails the administrator concerned when they log in from an unknown IP address.', 'optimizr'), 'haptic_sec_login_section');

    /* Double authentification */
    add_settings_section('haptic_sec_2fa_section', __('Two-factor authentication', 'optimizr'), function () {
        echo '<p>' . esc_html__('After the password, a 6-digit code from an app on the phone (Google Authenticator, Microsoft Authenticator, 1Password…). Computed by the site itself, without any external service.', 'optimizr') . '</p>';
    }, $page);
    add_settings_field('haptic_sec_two_factor', __('Two-factor authentication', 'optimizr'), 'haptic_sec_two_factor_callback', $page, 'haptic_sec_2fa_section', [
        'description' => __('Each user can enable it from their profile.', 'optimizr'),
    ]);
    add_settings_field('haptic_sec_two_factor_roles', __('Required for', 'optimizr'), 'haptic_sec_two_factor_roles_callback', $page, 'haptic_sec_2fa_section', [
        'description' => __('Users with these roles must set it up: until they do, the dashboard is limited to their profile.', 'optimizr'),
    ]);
}

function haptic_sec_two_factor_callback()
{
    echo wp_kses(haptic_switch_input('haptic_sec_two_factor', haptic_sec_option('two_factor'), __('Two-factor authentication', 'optimizr')), haptic_kses_ui());
    ?>
    <?php if (defined('OPTIMIZR_DISABLE_2FA') && OPTIMIZR_DISABLE_2FA) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><span><?php /* translators: %s: OPTIMIZR_DISABLE_2FA constant name */ echo wp_kses_post(sprintf(__('Disabled by the %s constant in wp-config.php: logging in only asks for the password.', 'optimizr'), '<code>OPTIMIZR_DISABLE_2FA</code>')); ?></span></div>
    <?php elseif (haptic_sec_option('two_factor')) : ?>
        <div class="optimizr-notice optimizr-notice--<?php echo haptic_2fa_config(get_current_user_id()) ? 'ok' : 'warn'; ?>"><?php echo wp_kses(haptic_icon(haptic_2fa_config(get_current_user_id()) ? 'check' : 'alert', 18), haptic_kses_ui()); ?><span><?php
            echo wp_kses_post(haptic_2fa_config(get_current_user_id())
                ? __('Enabled on your account.', 'optimizr')
                /* translators: %s: URL of the profile page */
                : sprintf(__('Not enabled on your account yet: <a href="%s">set it up in your profile</a>.', 'optimizr'), esc_url(admin_url('profile.php#optimizr-2fa'))));
        ?></span></div>
    <?php endif; ?>
    <p class="optimizr-field__help"><?php /* translators: %s: PHP line to add to wp-config.php */ echo wp_kses_post(sprintf(__('Lost phone: a backup code replaces the app code, and an administrator can reset the account from its profile. If no administrator can log in, add %s to wp-config.php.', 'optimizr'), '<code>define(\'OPTIMIZR_DISABLE_2FA\', true);</code>')); ?></p>
    <?php
}

function haptic_sec_two_factor_roles_callback()
{
    $required = haptic_2fa_required_roles();
    echo '<div class="optimizr-pills">';
    foreach (wp_roles()->get_names() as $role => $name) {
        echo '<label class="optimizr-pill"><input type="checkbox" name="haptic_sec_two_factor_roles[]" value="' . esc_attr($role) . '" ' . checked(in_array($role, $required, true), true, false) . ' /> ' . esc_html(translate_user_role($name)) . '</label>';
    }
    echo '</div>';
    // Aucune case cochée : le champ doit tout de même partir pour vider le réglage
    echo '<input type="hidden" name="haptic_sec_two_factor_roles[]" value="">';
}

function haptic_sec_add_toggle($name, $label, $description, $section)
{
    add_settings_field('haptic_sec_' . $name, $label, function () use ($name, $label) {
        echo wp_kses(haptic_switch_input('haptic_sec_' . $name, haptic_sec_option($name), $label), haptic_kses_ui());
    }, haptic_settings_page('security'), $section, ['description' => $description]);
}

function haptic_sec_login_slug_callback()
{
    $slug = (string) get_option('haptic_sec_login_slug', '');
    $prefix = get_option('permalink_structure') ? home_url('/') : home_url('/') . '?';
    ?>
    <div class="optimizr-prefixed">
        <span class="optimizr-prefixed__prefix"><?php echo esc_html($prefix); ?></span>
        <input type="text" name="haptic_sec_login_slug" id="haptic_sec_login_slug" value="<?php echo esc_attr($slug); ?>" placeholder="<?php esc_attr_e('leave empty to keep wp-login.php', 'optimizr'); ?>" spellcheck="false" autocomplete="off" />
    </div>
    <?php if (defined('OPTIMIZR_DISABLE_CUSTOM_LOGIN') && OPTIMIZR_DISABLE_CUSTOM_LOGIN) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><span><?php /* translators: %s: OPTIMIZR_DISABLE_CUSTOM_LOGIN constant name */ echo wp_kses_post(sprintf(__('Disabled by the %s constant in wp-config.php: login goes through wp-login.php.', 'optimizr'), '<code>OPTIMIZR_DISABLE_CUSTOM_LOGIN</code>')); ?></span></div>
    <?php elseif (is_multisite()) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><span><?php esc_html_e('Not available on a multisite network.', 'optimizr'); ?></span></div>
    <?php elseif ($slug !== '') : ?>
        <div class="optimizr-notice optimizr-notice--ok"><?php echo wp_kses(haptic_icon('check', 18), haptic_kses_ui()); ?><span><?php /* translators: %s: link to the new login address */ echo wp_kses_post(sprintf(__('<strong>Login:</strong> %s — wp-login.php and /wp-admin/ show a not-found page to visitors.', 'optimizr'), '<a href="' . esc_url(haptic_sec_login_url()) . '">' . esc_html(haptic_sec_login_url()) . '</a>')); ?></span></div>
    <?php endif; ?>
    <p class="optimizr-field__help"><?php /* translators: %s: PHP line to add to wp-config.php */ echo wp_kses_post(sprintf(__('Bookmark the new address: an email reminds the administrator of it after each change. If you get locked out, add %s to wp-config.php.', 'optimizr'), '<code>define(\'OPTIMIZR_DISABLE_CUSTOM_LOGIN\', true);</code>')); ?></p>
    <?php
}

function haptic_sec_limit_login_callback()
{
    echo wp_kses(haptic_switch_input('haptic_sec_limit_login', haptic_sec_option('limit_login'), __('Limit login attempts', 'optimizr')), haptic_kses_ui());
    ?>
    <div class="optimizr-inline">
        <label class="optimizr-muted" for="haptic_sec_login_max"><?php esc_html_e('Lock out after', 'optimizr'); ?></label>
        <input type="number" class="optimizr-input-small" name="haptic_sec_login_max" id="haptic_sec_login_max" min="2" max="20" value="<?php echo esc_attr(haptic_sec_option('login_max', 5)); ?>">
        <label class="optimizr-muted" for="haptic_sec_login_lockout"><?php esc_html_e('failures, for', 'optimizr'); ?></label>
        <input type="number" class="optimizr-input-small" name="haptic_sec_login_lockout" id="haptic_sec_login_lockout" min="1" max="1440" value="<?php echo esc_attr(haptic_sec_option('login_lockout', 15)); ?>">
        <span class="optimizr-muted"><?php esc_html_e('minutes', 'optimizr'); ?></span>
    </div>
    <label for="haptic_sec_login_whitelist" class="optimizr-muted"><?php esc_html_e('IP addresses never locked out (one per line)', 'optimizr'); ?></label>
    <textarea name="haptic_sec_login_whitelist" id="haptic_sec_login_whitelist" rows="2" class="optimizr-code" spellcheck="false"><?php echo esc_textarea(haptic_sec_option('login_whitelist', '')); ?></textarea>
    <p class="optimizr-field__help"><?php /* translators: %s: current IP address */ echo wp_kses_post(sprintf(__('Your current IP address: %s. Add it if it is static, so you never lock yourself out.', 'optimizr'), '<code>' . esc_html(haptic_sec_client_ip()) . '</code>')); ?></p>
    <?php
}

function haptic_sec_proxies_callback()
{
    $remote = filter_var(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')), FILTER_VALIDATE_IP) ?: '';
    $client = haptic_sec_client_ip();
    // En-têtes qui contiennent déjà l'adresse vue par le serveur : il les a lui-même traités (mod_remoteip, real_ip de nginx), rien à déclarer
    $forwarded = array_filter(array_map('trim', array_merge(
        [sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''))],
        explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')))
    )));
    $proxied = $forwarded && !in_array($remote, $forwarded, true);
    ?>
    <div class="optimizr-check-row">
        <?php echo wp_kses(haptic_switch_input('haptic_sec_proxy_cloudflare', haptic_sec_option('proxy_cloudflare'), __('Site behind Cloudflare', 'optimizr')), haptic_kses_ui()); ?>
        <span><strong><?php esc_html_e('Site behind Cloudflare', 'optimizr'); ?></strong><br><span class="optimizr-muted"><?php esc_html_e('Visitor address read from the CF-Connecting-IP header, only for requests coming from Cloudflare servers.', 'optimizr'); ?></span></span>
    </div>
    <label for="haptic_sec_trusted_proxies" class="optimizr-muted"><?php esc_html_e('Other proxies: IP addresses or CIDR ranges, one per line', 'optimizr'); ?></label>
    <textarea name="haptic_sec_trusted_proxies" id="haptic_sec_trusted_proxies" rows="2" class="optimizr-code" spellcheck="false" placeholder="10.0.0.0/8"><?php echo esc_textarea(haptic_sec_option('trusted_proxies', '')); ?></textarea>
    <p class="optimizr-field__help"><?php esc_html_e('For a request coming from these addresses, the visitor\'s address is read from the X-Forwarded-For header. Only add your own proxies\' addresses (host, CDN, load balancer): a declared address can pass on any visitor address. Leave empty if the site is not behind a proxy.', 'optimizr'); ?></p>
    <?php if ($remote !== '' && $client !== $remote) : ?>
        <div class="optimizr-notice optimizr-notice--ok"><?php echo wp_kses(haptic_icon('check', 18), haptic_kses_ui()); ?><span><?php /* translators: 1: proxy IP address, 2: visitor IP address used */ echo wp_kses_post(sprintf(__('Your request goes through the proxy %1$s: the address used for you is %2$s.', 'optimizr'), '<code>' . esc_html($remote) . '</code>', '<code>' . esc_html($client) . '</code>')); ?></span></div>
    <?php elseif ($remote !== '' && haptic_sec_ip_in_ranges($remote, haptic_sec_trusted_proxies())) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><span><?php /* translators: %s: proxy IP address */ echo wp_kses_post(sprintf(__('Your request comes from the declared proxy %s, but it does not pass on any readable visitor address: its own address is used.', 'optimizr'), '<code>' . esc_html($remote) . '</code>')); ?></span></div>
    <?php elseif ($proxied) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><span><?php /* translators: %s: IP address seen by the server */ echo wp_kses_post(sprintf(__('Your request seems to go through a proxy (address seen by the server: %s). If it is yours, declare it here.', 'optimizr'), '<code>' . esc_html($remote) . '</code>')); ?></span></div>
    <?php endif; ?>
    <?php
}

// Panneau latéral : bilan et journal
add_action('haptic_admin_aside_security', function () {
    // Optimizr Pro ajoute ses protections (discrétion, fonctions inutilisées, en-têtes)
    $protections = apply_filters('haptic_sec_protections', [
        __('Custom login address', 'optimizr') => haptic_sec_login_slug() !== '',
        __('Login attempts limited', 'optimizr') => (bool) haptic_sec_option('limit_login'),
        __('Neutral error messages', 'optimizr') => (bool) haptic_sec_option('generic_errors'),
        __('Login alert', 'optimizr') => (bool) haptic_sec_option('login_alert'),
        __('Two-factor authentication', 'optimizr') => haptic_2fa_available(),
    ]);
    // Types d'évènement enregistrés dans le journal (clés en base, inchangées) et leur libellé affiché
    // Optimizr Pro ajoute les siens (filtre haptic_sec_log_labels)
    $event_labels = apply_filters('haptic_sec_log_labels', [
        'Verrouillage'            => __('Lockout', 'optimizr'),
        'Alerte de connexion'     => __('Login alert', 'optimizr'),
        'Double authentification' => __('Two-factor authentication', 'optimizr'),
    ]);
    $active = count(array_filter($protections));
    $log = array_slice(haptic_sec_get_log(), 0, 8);
    ?>
    <div class="optimizr-sticky">
        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title"><?php esc_html_e('Security overview', 'optimizr'); ?></h2>
                    <p class="optimizr-card__desc"><?php /* translators: 1: number of enabled protections, 2: total number of protections */ echo esc_html(sprintf(_n('%1$s of %2$s protection enabled.', '%1$s of %2$s protections enabled.', count($protections), 'optimizr'), number_format_i18n($active), number_format_i18n(count($protections)))); ?></p>
                </div>
            </div>
            <ul class="optimizr-checks">
                <?php foreach (apply_filters('haptic_sec_environment_checks', []) as $check) : ?>
                    <li class="optimizr-checks__item">
                        <span class="optimizr-checks__icon optimizr-checks__icon--<?php echo esc_attr($check['status']); ?>"><?php echo wp_kses(haptic_icon($check['status'] === 'ok' ? 'check' : 'alert', 14), haptic_kses_ui()); ?></span>
                        <span class="optimizr-checks__label"><?php echo esc_html($check['label']); ?></span>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($protections as $label => $on) : ?>
                    <li class="optimizr-checks__item">
                        <span class="optimizr-checks__icon optimizr-checks__icon--<?php echo $on ? 'ok' : 'off'; ?>"><?php echo wp_kses(haptic_icon($on ? 'check' : 'alert', 14), haptic_kses_ui()); ?></span>
                        <span class="optimizr-checks__label"><?php echo esc_html($label); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="optimizr-card">
            <div class="optimizr-card__header">
                <div>
                    <h2 class="optimizr-card__title"><?php echo esc_html_x('Log', 'security log', 'optimizr'); ?></h2>
                    <p class="optimizr-card__desc"><?php esc_html_e('Lockouts and alerts from the last 30 days.', 'optimizr'); ?></p>
                </div>
            </div>
            <?php if (!$log) : ?>
                <p class="optimizr-card__empty"><?php esc_html_e('No events.', 'optimizr'); ?></p>
            <?php else : ?>
                <ul class="optimizr-sec-log">
                    <?php foreach ($log as $entry) : ?>
                        <li>
                            <strong><?php echo esc_html($event_labels[$entry['event']] ?? $entry['event']); ?></strong>
                            <span class="optimizr-muted"><?php /* translators: date format of a security log entry, see https://www.php.net/manual/datetime.format.php */ echo esc_html(wp_date(__('M j, H:i', 'optimizr'), $entry['time'])); ?> · <?php echo esc_html($entry['ip']); ?></span>
                            <?php if ($entry['detail']) : ?><span class="optimizr-muted"><?php echo esc_html($entry['detail']); ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <?php
});
