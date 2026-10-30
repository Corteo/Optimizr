<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Tableau de bord (onglet par défaut de la page d'options) : état du site, indicateurs, versions et modules par thème.
 * Les modules s'activent sans barre d'enregistrement (AJAX haptic_module_toggle, js/optimizr-admin.js).
 * Masquages propres à chaque compte : métadonnée haptic_dashboard_hidden (assistant, présentation d'Optimizr Pro).
 */

/**
 * Thèmes du tableau de bord, dans l'ordre d'affichage.
 * 'modules' : identifiants des modules du thème, y compris ceux d'Optimizr Pro ; un module absent de toutes
 * les listes rejoint le dernier thème. 'colors' : dégradé de l'icône du thème (début, fin).
 */
function haptic_dashboard_groups()
{
    return apply_filters('haptic_dashboard_groups', [
        'security'    => ['label' => __('Security & compliance', 'optimizr'), 'short' => __('Security', 'optimizr'), 'icon' => 'shield',
            'colors' => ['#19b5d6', '#3858e9'], 'modules' => ['cookies', 'security', 'emails']],
        'visibility'  => ['label' => __('Visibility', 'optimizr'), 'short' => __('Visibility', 'optimizr'), 'icon' => 'search',
            'colors' => ['#a78bfa', '#6d48e8'], 'modules' => ['seo', 'redirects', 'local', 'ai']],
        'performance' => ['label' => __('Performance', 'optimizr'), 'short' => __('Performance', 'optimizr'), 'icon' => 'gauge',
            'colors' => ['#2fd39a', '#0e8f63'], 'modules' => ['cache', 'thumbnails']],
        'content'     => ['label' => __('Content & tools', 'optimizr'), 'short' => _x('Content', 'dashboard group', 'optimizr'), 'icon' => 'layers',
            'colors' => ['#5b7bff', '#1d35b4'], 'modules' => ['cpts', 'pages', 'mcp']],
    ]);
}

/**
 * Thèmes avec leurs modules existants (sans les thèmes vides, par exemple ceux d'Optimizr Pro quand il est absent).
 *
 * @return array Thème => définition, 'modules' remplacé par [identifiant => module]
 */
function haptic_dashboard_grouped_modules()
{
    $modules = haptic_modules();
    $groups = haptic_dashboard_groups();
    $placed = [];

    foreach ($groups as $key => $group) {
        $groups[$key]['modules'] = [];
        foreach ((array) $group['modules'] as $slug) {
            if (isset($modules[$slug]) && !isset($placed[$slug])) {
                $groups[$key]['modules'][$slug] = $modules[$slug];
                $placed[$slug] = $key;
            }
        }
    }

    $last = array_key_last($groups);
    foreach ($modules as $slug => $module) {
        if (!isset($placed[$slug]) && $last !== null) {
            $groups[$last]['modules'][$slug] = $module;
        }
    }

    return array_filter($groups, function ($group) {
        return !empty($group['modules']);
    });
}

/** Thème d'une vérification de l'état du site : 'group', sinon celui de son module, sinon celui de l'onglet de son lien. */
function haptic_dashboard_check_group(array $check, array $groups)
{
    if (!empty($check['group']) && isset($groups[$check['group']])) {
        return $check['group'];
    }

    $slug = $check['module'] ?? '';
    if ($slug === '' && !empty($check['link'])) {
        parse_str((string) wp_parse_url($check['link'], PHP_URL_QUERY), $query);
        $tab = isset($query['page'], $query['tab']) && $query['page'] === 'haptic-options' ? $query['tab'] : '';
        foreach (haptic_modules() as $candidate => $module) {
            if ($tab !== '' && ($module['tab'] ?? '') === $tab) {
                $slug = $candidate;
                break;
            }
        }
    }

    foreach ($groups as $key => $group) {
        if ($slug !== '' && isset($group['modules'][$slug])) {
            return $key;
        }
    }
    return '';
}

/** Style en ligne des couleurs d'un thème (icône en dégradé, teinte des icônes de ses modules). */
function haptic_dashboard_group_style(array $group)
{
    $colors = array_values(array_filter((array) ($group['colors'] ?? []), function ($color) {
        return (bool) sanitize_hex_color($color);
    }));
    if (count($colors) < 2) {
        return '';
    }
    return '--og-from:' . $colors[0] . ';--og-to:' . $colors[1] . ';';
}

/* ---------- Masquages propres à chaque compte ---------- */

function haptic_dashboard_is_hidden($key)
{
    $hidden = get_user_meta(get_current_user_id(), 'haptic_dashboard_hidden', true);
    return is_array($hidden) && !empty($hidden[$key]);
}

add_action('wp_ajax_haptic_dashboard_hide', function () {
    check_ajax_referer('haptic_dashboard');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Access denied', 'optimizr')], 403);
    }

    $key = sanitize_key(wp_unslash($_POST['key'] ?? ''));
    if (!in_array($key, ['onboarding', 'pro'], true)) {
        wp_send_json_error();
    }

    $hidden = get_user_meta(get_current_user_id(), 'haptic_dashboard_hidden', true);
    $hidden = is_array($hidden) ? $hidden : [];
    $hidden[$key] = 1;
    update_user_meta(get_current_user_id(), 'haptic_dashboard_hidden', $hidden);
    wp_send_json_success();
});

/* ---------- Activation d'un module sans enregistrement ---------- */

add_action('wp_ajax_haptic_module_toggle', function () {
    check_ajax_referer('haptic_dashboard');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Access denied', 'optimizr')], 403);
    }

    $slug = sanitize_key(wp_unslash($_POST['module'] ?? ''));
    $modules = haptic_modules();
    if (!isset($modules[$slug])) {
        wp_send_json_error(['message' => __('Unknown module.', 'optimizr')]);
    }
    $active = !empty($_POST['active']);

    // État de tous les modules, complété par celui demandé (haptic_sanitize_modules() s'applique à l'enregistrement)
    $state = [];
    foreach (array_keys($modules) as $key) {
        $state[$key] = haptic_is_module_active($key) ? 1 : 0;
    }
    $state[$slug] = $active ? 1 : 0;
    update_option('haptic_modules', haptic_sanitize_modules($state));

    $status = haptic_module_status($slug);
    ob_start();
    haptic_render_tabs_nav(haptic_admin_tabs(), 'dashboard');
    $tabs = ob_get_clean();

    wp_send_json_success([
        'active' => haptic_is_module_active($slug),
        'status' => $status['status'],
        'label'  => $status['label'],
        'tabs'   => $tabs,
    ]);
});

/* ---------- Affichage ---------- */

/** Présentation d'Optimizr Pro, affichée sur le tableau de bord tant que l'extension n'est pas installée. */
function haptic_pro_features()
{
    return [
        __('Unlimited redirects, 404 log, wildcards, CSV import, broken links', 'optimizr'),
        __('Local SEO: business profile, opening hours and Google reviews', 'optimizr'),
        __('AI assistant for meta descriptions and ALT text', 'optimizr'),
        __('Content types: ready-made Events type and custom post types', 'optimizr'),
        __('Custom 404 page and maintenance mode', 'optimizr'),
        __('SEO: XML sitemap, robots.txt, IndexNow, structured data', 'optimizr'),
    ];
}

/**
 * Actions rapides de l'en-tête du tableau de bord : ['label', 'url', 'icon'].
 * Optimizr Pro y ajoute la régénération du sitemap.
 */
function haptic_dashboard_actions()
{
    $actions = [];
    if (haptic_is_module_active('cache') && function_exists('haptic_cache_can_purge') && haptic_cache_can_purge() && haptic_cache_page_active()) {
        $actions['cache'] = ['label' => __('Clear the cache', 'optimizr'), 'url' => haptic_cache_purge_url(), 'icon' => 'refresh'];
    }
    $actions['audit'] = ['label' => __('Run an audit', 'optimizr'), 'url' => haptic_admin_tab_url('audit'), 'icon' => 'gauge'];

    return apply_filters('haptic_dashboard_actions', $actions);
}

/**
 * Ligne de la carte « Versions et licence » (utilisée aussi par Optimizr Pro, action haptic_dashboard_versions).
 *
 * @param array $row 'name', 'detail', et au choix 'pill' + 'pill_status' (ok|warn), 'button' + 'button_url', 'link' + 'link_url'
 */
function haptic_dashboard_version_row(array $row)
{
    ?>
    <div class="optimizr-versions__row">
        <div class="optimizr-versions__name">
            <strong><?php echo esc_html($row['name'] ?? ''); ?></strong>
            <span><?php echo esc_html($row['detail'] ?? ''); ?></span>
        </div>
        <?php if (!empty($row['button']) && !empty($row['button_url'])) : ?>
            <a class="optimizr-btn optimizr-btn--primary optimizr-btn--sm" href="<?php echo esc_url($row['button_url']); ?>"><?php echo esc_html($row['button']); ?></a>
        <?php elseif (!empty($row['link']) && !empty($row['link_url'])) : ?>
            <a class="optimizr-link" href="<?php echo esc_url($row['link_url']); ?>"><?php echo esc_html($row['link']); ?></a>
        <?php elseif (!empty($row['pill'])) : ?>
            <span class="optimizr-pill-status optimizr-pill-status--<?php echo esc_attr(($row['pill_status'] ?? 'ok') === 'warn' ? 'warn' : 'ok'); ?>"><?php echo esc_html($row['pill']); ?></span>
        <?php endif; ?>
    </div>
    <?php
}

/** Nouvelle version proposée par WordPress pour une extension (null si aucune). */
function haptic_dashboard_available_update($plugin_file)
{
    $updates = get_site_transient('update_plugins');
    $basename = plugin_basename($plugin_file);
    if (is_object($updates) && isset($updates->response[$basename]->new_version)) {
        return (string) $updates->response[$basename]->new_version;
    }
    return null;
}

function haptic_render_dashboard()
{
    $groups = haptic_dashboard_grouped_modules();
    ?>
    <div class="optimizr-dashboard" data-optimizr-dashboard data-nonce="<?php echo esc_attr(wp_create_nonce('haptic_dashboard')); ?>">
        <div class="optimizr-page-head optimizr-page-head--actions">
            <div>
                <h2 class="optimizr-page-head__title"><?php esc_html_e('Overview', 'optimizr'); ?></h2>
                <p class="optimizr-page-head__intro"><?php esc_html_e('The state of your site, its indicators and the Optimizr modules.', 'optimizr'); ?></p>
            </div>
            <div class="optimizr-quick-actions" aria-label="<?php esc_attr_e('Quick actions', 'optimizr'); ?>">
                <?php foreach (haptic_dashboard_actions() as $action) : ?>
                    <a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url($action['url']); ?>"><?php echo wp_kses(haptic_icon($action['icon'] ?? 'zap', 16), haptic_kses_ui()); ?><?php echo esc_html($action['label']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php haptic_render_dashboard_hero($groups); ?>

        <?php if (!get_option('haptic_onboarding_done') && !haptic_dashboard_is_hidden('onboarding')) : ?>
            <div class="optimizr-wizard-cta" data-optimizr-hideable="onboarding">
                <span class="optimizr-wizard-cta__art" aria-hidden="true">
                    <span><?php echo wp_kses(haptic_icon('shield', 18), haptic_kses_ui()); ?></span>
                    <span><?php echo wp_kses(haptic_icon('gauge', 18), haptic_kses_ui()); ?></span>
                    <span><?php echo wp_kses(haptic_icon('sparkles', 18), haptic_kses_ui()); ?></span>
                </span>
                <div class="optimizr-wizard-cta__text">
                    <h3 class="optimizr-wizard-cta__title"><?php esc_html_e('Set up the essentials in a few minutes', 'optimizr'); ?></h3>
                    <p><?php esc_html_e('The wizard enables the useful modules and the recommended security, SEO and privacy settings.', 'optimizr'); ?></p>
                </div>
                <div class="optimizr-wizard-cta__actions">
                    <button type="button" class="optimizr-btn optimizr-btn--ghost" data-optimizr-hide="onboarding"><?php esc_html_e('Later', 'optimizr'); ?></button>
                    <a href="<?php echo esc_url(haptic_onboarding_url()); ?>" class="optimizr-btn optimizr-btn--primary"><?php esc_html_e('Launch the wizard', 'optimizr'); ?></a>
                </div>
            </div>
        <?php endif; ?>

        <div class="optimizr-dash">
            <div class="optimizr-dash__modules">
                <?php foreach ($groups as $key => $group) : ?>
                    <?php haptic_render_dashboard_group($key, $group); ?>
                <?php endforeach; ?>

                <?php if (!haptic_is_pro() && !haptic_dashboard_is_hidden('pro')) : ?>
                    <section class="optimizr-card optimizr-pro-card" data-optimizr-hideable="pro">
                        <div class="optimizr-card__header">
                            <div>
                                <h2 class="optimizr-card__title"><?php esc_html_e('Go further with Optimizr Pro', 'optimizr'); ?></h2>
                                <p class="optimizr-card__desc"><?php esc_html_e('A separate add-on that adds its own modules. Your current settings stay as they are.', 'optimizr'); ?></p>
                            </div>
                            <button type="button" class="optimizr-btn optimizr-btn--ghost optimizr-btn--sm" data-optimizr-hide="pro"><?php esc_html_e('Hide', 'optimizr'); ?></button>
                        </div>
                        <ul class="optimizr-pro-card__list">
                            <?php foreach (haptic_pro_features() as $feature) : ?>
                                <li><?php echo wp_kses(haptic_icon('plus', 16), haptic_kses_ui()); ?><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="optimizr-card__foot">
                            <a class="optimizr-link" href="<?php echo esc_url(HAPTIC_OPTIMIZR_PRO_INFO_URL); ?>" target="_blank" rel="noopener"><?php esc_html_e('Learn more about Optimizr Pro →', 'optimizr'); ?></a>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <div class="optimizr-dash__indicators">
                <?php haptic_render_dashboard_audit(); ?>
                <?php haptic_render_dashboard_cache(); ?>
            </div>

            <div class="optimizr-dash__versions">
                <?php haptic_render_dashboard_versions(); ?>
                <?php
                // Cartes ajoutées par une extension (Optimizr Pro antérieur à 1.13.0 : version et licence)
                do_action('haptic_dashboard_aside');
                ?>
            </div>
        </div>
    </div>
    <?php
}

/** En-tête illustré : salutation, points à régler, état par thème. */
function haptic_render_dashboard_hero(array $groups)
{
    $checks = haptic_site_health_checks();
    $warnings = array_values(array_filter($checks, function ($check) {
        return ($check['status'] ?? '') === 'warn';
    }));
    $passed = array_values(array_filter($checks, function ($check) {
        return ($check['status'] ?? '') === 'ok';
    }));
    $count = count($warnings);

    $by_group = array_fill_keys(array_keys($groups), 0);
    foreach ($warnings as $check) {
        $key = haptic_dashboard_check_group($check, $groups);
        if ($key !== '') {
            $by_group[$key]++;
        }
    }

    $user = wp_get_current_user();
    $name = $user->first_name !== '' ? $user->first_name : $user->display_name;

    if ($count === 0) {
        $title = __('Everything is in order', 'optimizr');
        $intro = __('Your site is protected, fast and easy to find.', 'optimizr');
    } else {
        /* translators: %s: number of items to fix */
        $title = sprintf(_n('%s item to fix', '%s items to fix', $count, 'optimizr'), number_format_i18n($count));
        /* translators: %s: number of checks passed */
        $intro = $passed ? sprintf(_n('%s other check passed.', '%s other checks passed.', count($passed), 'optimizr'), number_format_i18n(count($passed))) : '';
    }
    ?>
    <section class="optimizr-hero" aria-labelledby="optimizr-hero-title">
        <div class="optimizr-hero__inner">
            <div class="optimizr-hero__text">
                <p class="optimizr-hero__eyebrow"><?php
                    /* translators: %s: first name or display name of the current user */
                    echo esc_html($name !== '' ? sprintf(__('Hello %s', 'optimizr'), $name) : __('Welcome', 'optimizr'));
                ?></p>
                <h2 class="optimizr-hero__title" id="optimizr-hero-title"><?php echo esc_html($title); ?></h2>
                <p class="optimizr-hero__intro"><?php echo esc_html(trim($intro . ' ' . __('Checked when this page was opened.', 'optimizr'))); ?></p>

                <?php if ($warnings) : ?>
                    <ul class="optimizr-hero__list">
                        <?php foreach ($warnings as $check) : ?>
                            <li class="optimizr-hero__row">
                                <span class="optimizr-hero__dot optimizr-hero__dot--warn"><?php echo wp_kses(haptic_icon('alert', 14), haptic_kses_ui()); ?></span>
                                <span class="optimizr-hero__label"><?php echo esc_html($check['label']); ?></span>
                                <?php if (!empty($check['link'])) : ?>
                                    <a class="optimizr-btn optimizr-btn--light optimizr-btn--sm" href="<?php echo esc_url($check['link']); ?>"><?php echo esc_html($check['link_label'] ?? __('Fix', 'optimizr')); ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (count($groups) > 1) : ?>
                    <nav class="optimizr-hero__pills" aria-label="<?php esc_attr_e('Status by theme', 'optimizr'); ?>">
                        <?php foreach ($groups as $key => $group) :
                            $n = $by_group[$key];
                            /* translators: %s: number of items to fix */
                            $state = $n ? sprintf(_n('%s item', '%s items', $n, 'optimizr'), number_format_i18n($n)) : _x('OK', 'dashboard theme status', 'optimizr');
                            ?>
                            <a class="optimizr-hero__pill" href="#optimizr-group-<?php echo esc_attr($key); ?>">
                                <span class="optimizr-hero__pill-dot<?php echo $n ? ' is-warn' : ''; ?>" aria-hidden="true"></span>
                                <?php echo esc_html($group['short'] ?? $group['label']); ?>
                                <span class="optimizr-hero__pill-state"><?php echo esc_html($state); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </div>

            <div class="optimizr-hero__art" aria-hidden="true">
                <?php haptic_render_dashboard_art($groups, $by_group, $count); ?>
            </div>
        </div>

        <?php if ($passed) : ?>
            <details class="optimizr-hero__passed">
                <summary><?php
                    /* translators: %s: number of checks passed */
                    echo esc_html(sprintf(_n('Show the passed check (%s)', 'Show the passed checks (%s)', count($passed), 'optimizr'), number_format_i18n(count($passed))));
                ?></summary>
                <ul class="optimizr-hero__list optimizr-hero__list--passed">
                    <?php foreach ($passed as $check) : ?>
                        <li class="optimizr-hero__row">
                            <span class="optimizr-hero__dot optimizr-hero__dot--ok"><?php echo wp_kses(haptic_icon('check', 14), haptic_kses_ui()); ?></span>
                            <span class="optimizr-hero__label"><?php echo esc_html($check['label']); ?></span>
                            <?php if (!empty($check['link'])) : ?>
                                <a class="optimizr-hero__link" href="<?php echo esc_url($check['link']); ?>"><?php echo esc_html($check['link_label'] ?? ''); ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * Illustration de l'en-tête : une page de site entourée d'une pastille par thème, avec un point vert ou orange.
 * SVG décoratif, sans ressource externe.
 */
function haptic_render_dashboard_art(array $groups, array $by_group, $count)
{
    // Emplacements des pastilles autour de la page (jusqu'à 6 thèmes)
    $spots = [[78, 62], [290, 66], [72, 182], [288, 184], [180, 20], [180, 226]];
    $ok = '#2fd39a';
    $warn = '#f59e0b';
    ?>
    <svg viewBox="0 0 360 240" fill="none" focusable="false">
        <defs>
            <radialGradient id="optimizr-art-glow" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#4a6bff" stop-opacity=".55"/><stop offset="100%" stop-color="#4a6bff" stop-opacity="0"/></radialGradient>
            <linearGradient id="optimizr-art-line" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#19b5d6"/><stop offset="100%" stop-color="#7f98ff"/></linearGradient>
            <?php $i = 0; foreach ($groups as $key => $group) :
                $colors = array_values((array) ($group['colors'] ?? []));
                $from = sanitize_hex_color($colors[0] ?? '') ?: '#5b7bff';
                $to = sanitize_hex_color($colors[1] ?? '') ?: '#3858e9';
                ?>
                <linearGradient id="optimizr-art-<?php echo esc_attr((string) $i); ?>" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="<?php echo esc_attr($from); ?>"/><stop offset="100%" stop-color="<?php echo esc_attr($to); ?>"/></linearGradient>
            <?php $i++; endforeach; ?>
        </defs>
        <circle cx="180" cy="120" r="118" fill="url(#optimizr-art-glow)"/>
        <circle cx="180" cy="122" r="104" stroke="rgba(255,255,255,.18)" stroke-dasharray="2 7"/>
        <circle cx="180" cy="122" r="74" stroke="rgba(255,255,255,.08)"/>
        <g class="optimizr-hero__float">
            <rect x="112" y="72" width="136" height="98" rx="12" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.22)"/>
            <circle cx="126" cy="85" r="3" fill="#ff8a80"/><circle cx="136" cy="85" r="3" fill="#ffd166"/><circle cx="146" cy="85" r="3" fill="#5ee0b0"/>
            <rect x="158" y="81" width="78" height="8" rx="4" fill="rgba(255,255,255,.12)"/>
            <rect x="124" y="100" width="58" height="7" rx="3.5" fill="rgba(255,255,255,.45)"/>
            <rect x="124" y="112" width="88" height="4" rx="2" fill="rgba(255,255,255,.18)"/>
            <polyline points="124,156 140,146 154,150 170,134 184,138" stroke="url(#optimizr-art-line)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="200" y="142" width="8" height="16" rx="2" fill="#7f98ff"/>
            <rect x="212" y="134" width="8" height="24" rx="2" fill="#5b7bff"/>
            <rect x="224" y="124" width="8" height="34" rx="2" fill="#2fd39a"/>
            <circle cx="242" cy="166" r="15" fill="<?php echo esc_attr($count ? '#d97706' : '#0e8f63'); ?>" stroke="#0b1437" stroke-width="3"/>
            <path d="<?php echo esc_attr($count ? 'M12 6v7M12 18h.01' : 'M20 6 9 17l-5-5'); ?>" transform="translate(233 157) scale(.75)" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </g>
        <?php $i = 0; foreach ($groups as $key => $group) :
            if (!isset($spots[$i])) {
                break;
            }
            [$x, $y] = $spots[$i];
            ?>
            <g class="<?php echo esc_attr($i % 2 ? 'optimizr-hero__float' : 'optimizr-hero__float optimizr-hero__float--late'); ?>">
                <circle cx="<?php echo esc_attr((string) $x); ?>" cy="<?php echo esc_attr((string) $y); ?>" r="24" fill="url(#optimizr-art-<?php echo esc_attr((string) $i); ?>)" stroke="rgba(255,255,255,.35)" stroke-width="1.5"/>
                <g transform="translate(<?php echo esc_attr(($x - 10) . ' ' . ($y - 10)); ?>) scale(.833)" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <?php echo wp_kses(haptic_icon_paths($group['icon'] ?? 'layers'), haptic_kses_ui()); ?>
                </g>
                <circle cx="<?php echo esc_attr((string) ($x + 18)); ?>" cy="<?php echo esc_attr((string) ($y - 18)); ?>" r="6" fill="<?php echo esc_attr($by_group[$key] ? $warn : $ok); ?>" stroke="#0b1437" stroke-width="2"/>
            </g>
        <?php $i++; endforeach; ?>
        <circle cx="40" cy="120" r="2" fill="#7fdcf0"/>
        <circle cx="330" cy="126" r="2.5" fill="#5ee0b0"/>
        <circle cx="168" cy="226" r="2" fill="#7f98ff"/>
    </svg>
    <?php
}

/** Un thème : titre, compteur et liste des modules avec leur interrupteur. */
function haptic_render_dashboard_group($key, array $group)
{
    $count = 0;
    foreach (array_keys($group['modules']) as $slug) {
        $count += haptic_is_module_active($slug) ? 1 : 0;
    }
    $total = count($group['modules']);

    // Modules actifs d'abord
    $modules = $group['modules'];
    uksort($modules, function ($a, $b) use ($group) {
        $order = array_flip(array_keys($group['modules']));
        return [haptic_is_module_active($b), -$order[$b]] <=> [haptic_is_module_active($a), -$order[$a]];
    });
    ?>
    <section class="optimizr-group" id="optimizr-group-<?php echo esc_attr($key); ?>" style="<?php echo esc_attr(haptic_dashboard_group_style($group)); ?>">
        <div class="optimizr-group__head">
            <span class="optimizr-group__icon"><?php echo wp_kses(haptic_icon($group['icon'] ?? 'layers', 18), haptic_kses_ui()); ?></span>
            <h3 class="optimizr-group__title"><?php echo esc_html($group['label']); ?></h3>
            <span class="optimizr-group__count" data-optimizr-group-count data-total="<?php echo esc_attr((string) $total); ?>"><?php
                /* translators: 1: number of active modules, 2: number of modules in the theme */
                echo esc_html(sprintf(_n('%1$s active out of %2$s', '%1$s active out of %2$s', $count, 'optimizr'), number_format_i18n($count), number_format_i18n($total)));
            ?></span>
        </div>
        <div class="optimizr-group__list">
            <?php foreach ($modules as $slug => $module) :
                $active = haptic_is_module_active($slug);
                $status = haptic_module_status($slug);
                ?>
                <div class="optimizr-mrow<?php echo $active ? '' : ' is-off'; ?>" data-module="<?php echo esc_attr($slug); ?>" data-label="<?php echo esc_attr($module['label']); ?>">
                    <span class="optimizr-mrow__icon"><?php echo wp_kses(haptic_icon($module['icon'] ?? 'layers'), haptic_kses_ui()); ?></span>
                    <div class="optimizr-mrow__text">
                        <h4 class="optimizr-mrow__title"><?php echo esc_html($module['label']); ?></h4>
                        <p class="optimizr-mrow__desc"><?php echo esc_html($module['desc']); ?></p>
                    </div>
                    <div class="optimizr-mrow__meta">
                        <span class="optimizr-status optimizr-status--<?php echo esc_attr($status['status']); ?>" data-module-status><?php echo esc_html($status['label']); ?></span>
                        <a class="optimizr-link" href="<?php echo esc_url(haptic_admin_tab_url($module['tab'])); ?>" data-module-configure <?php echo $active ? '' : 'hidden'; ?>><?php esc_html_e('Configure →', 'optimizr'); ?></a>
                    </div>
                    <?php
                    /* translators: %s: module name */
                    $switch_label = sprintf(__('%s module', 'optimizr'), $module['label']);
                    ?>
                    <button type="button" role="switch" class="optimizr-toggle" aria-checked="<?php echo $active ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr($switch_label); ?>" data-module-toggle><span class="optimizr-toggle__knob" aria-hidden="true"></span></button>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

/** Dernier audit PageSpeed : scores par appareil et évolution du score Performance (functions/audit.php). */
function haptic_render_dashboard_audit()
{
    $results = function_exists('haptic_audit_results') ? haptic_audit_results() : [];
    $strategies = array_values(array_filter(['mobile', 'desktop'], function ($strategy) use ($results) {
        return !empty($results[$strategy]['scores']);
    }));
    $labels = haptic_audit_strategies();
    ?>
    <section class="optimizr-card optimizr-dash-audit" data-optimizr-audit-card>
        <div class="optimizr-card__header">
            <div>
                <h2 class="optimizr-card__title"><?php esc_html_e('Latest audit', 'optimizr'); ?></h2>
                <?php if (!$strategies) : ?>
                    <p class="optimizr-card__desc"><?php esc_html_e('Google measures the speed, accessibility and SEO of a page of your site.', 'optimizr'); ?></p>
                <?php endif; ?>
            </div>
            <?php if (count($strategies) > 1) : ?>
                <div class="optimizr-segmented" role="group" aria-label="<?php esc_attr_e('Device', 'optimizr'); ?>">
                    <?php foreach ($strategies as $i => $strategy) : ?>
                        <button type="button" aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>" data-audit-show="<?php echo esc_attr($strategy); ?>"><?php echo esc_html($labels[$strategy]); ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$strategies) : ?>
            <div class="optimizr-card__foot">
                <span class="optimizr-card__hint"><?php esc_html_e('No audit yet.', 'optimizr'); ?></span>
                <a class="optimizr-btn optimizr-btn--secondary optimizr-btn--sm" href="<?php echo esc_url(haptic_admin_tab_url('audit')); ?>"><?php echo esc_html(haptic_audit_api_key() === '' ? __('Set up audits', 'optimizr') : __('Run an audit', 'optimizr')); ?></a>
            </div>
        <?php else : ?>
            <?php foreach ($strategies as $i => $strategy) :
                $result = $results[$strategy];
                $path = (string) wp_parse_url((string) $result['url'], PHP_URL_PATH);
                ?>
                <div class="optimizr-dash-audit__panel" data-audit-panel="<?php echo esc_attr($strategy); ?>" <?php echo $i === 0 ? '' : 'hidden'; ?>>
                    <p class="optimizr-dash-audit__meta"><?php
                        echo esc_html(implode(' · ', [
                            $labels[$strategy],
                            ($path === '' || $path === '/') ? __('Home page', 'optimizr') : $path,
                            wp_date(get_option('date_format'), (int) $result['time']),
                        ]));
                    ?></p>
                    <div class="optimizr-dash-audit__scores">
                        <?php foreach (haptic_audit_categories() as $slug => $label) : ?>
                            <?php echo wp_kses(haptic_audit_gauge($result['scores'][$slug] ?? null, $label), haptic_audit_kses()); ?>
                        <?php endforeach; ?>
                    </div>
                    <?php haptic_render_dashboard_sparkline($strategy); ?>
                </div>
            <?php endforeach; ?>
            <div class="optimizr-card__foot">
                <a class="optimizr-link" href="<?php echo esc_url(haptic_admin_tab_url('audit')); ?>"><?php esc_html_e('See the details →', 'optimizr'); ?></a>
                <a class="optimizr-btn optimizr-btn--secondary optimizr-btn--sm" href="<?php echo esc_url(haptic_admin_tab_url('audit')); ?>"><?php esc_html_e('Run again', 'optimizr'); ?></a>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

/** Courbe du score Performance sur les derniers audits d'un appareil (historique de l'onglet Audit). */
function haptic_render_dashboard_sparkline($strategy)
{
    $scores = [];
    foreach (haptic_audit_history() as $entry) {
        if (($entry['strategy'] ?? '') === $strategy && isset($entry['scores']['performance'])) {
            $scores[] = (int) $entry['scores']['performance'];
        }
        if (count($scores) === 7) {
            break;
        }
    }
    if (count($scores) < 2) {
        return;
    }
    $scores = array_reverse($scores);

    $low = min($scores) - 4;
    $high = max($scores) + 4;
    $points = [];
    foreach ($scores as $i => $score) {
        $points[] = round($i * 200 / (count($scores) - 1), 1) . ',' . round(38 - ($score - $low) / ($high - $low) * 34, 1);
    }
    $delta = end($scores) - reset($scores);
    ?>
    <div class="optimizr-sparkline">
        <svg viewBox="0 0 200 40" preserveAspectRatio="none" role="img" aria-label="<?php echo esc_attr(sprintf(
            /* translators: %s: list of scores, oldest first */
            __('Performance scores, oldest first: %s', 'optimizr'),
            implode(', ', $scores)
        )); ?>">
            <polygon points="<?php echo esc_attr('0,40 ' . implode(' ', $points) . ' 200,40'); ?>" class="optimizr-sparkline__area"/>
            <polyline points="<?php echo esc_attr(implode(' ', $points)); ?>" class="optimizr-sparkline__line" vector-effect="non-scaling-stroke"/>
        </svg>
        <span class="optimizr-sparkline__label">
            <strong class="<?php echo esc_attr($delta < 0 ? 'is-down' : 'is-up'); ?>"><?php
                /* translators: %s: change of the score in points, with its sign */
                echo esc_html(sprintf(__('%s pts', 'optimizr'), ($delta > 0 ? '+' : '') . number_format_i18n($delta)));
            ?></strong>
            <?php
            /* translators: %s: number of audits */
            echo esc_html(sprintf(__('Performance, last %s audits', 'optimizr'), number_format_i18n(count($scores))));
            ?>
        </span>
    </div>
    <?php
}

/** État du cache (module Cache actif) : pages en cache, protections, dernier vidage. */
function haptic_render_dashboard_cache()
{
    if (!haptic_is_module_active('cache') || !function_exists('haptic_cache_stats')) {
        return;
    }

    $state = haptic_cache_state();
    $active = haptic_cache_page_active();
    $stats = haptic_cache_stats();
    $last = (int) get_option('haptic_cache_last_purge');
    $checks = [
        __('Page cache', 'optimizr')    => $active,
        __('Browser cache', 'optimizr') => !empty($state['browser']),
        __('Compression', 'optimizr')   => !empty($state['browser']) && get_option('haptic_cache_gzip', 1),
    ];
    ?>
    <section class="optimizr-card">
        <div class="optimizr-card__header">
            <div>
                <h2 class="optimizr-card__title"><?php esc_html_e('Cache', 'optimizr'); ?></h2>
                <p class="optimizr-card__desc"><?php
                if ($active) {
                    /* translators: 1: number of cached pages, 2: size such as "2 MB" */
                    echo esc_html(sprintf(_n('%1$s page cached (%2$s).', '%1$s pages cached (%2$s).', $stats['count'], 'optimizr'), number_format_i18n($stats['count']) . ($stats['more'] ? '+' : ''), size_format($stats['size'])));
                } else {
                    esc_html_e('The page cache is off.', 'optimizr');
                }
                ?></p>
            </div>
            <span class="optimizr-pill-status optimizr-pill-status--<?php echo $active ? 'ok' : 'warn'; ?>"><?php echo $active ? esc_html_x('Active', 'cache status', 'optimizr') : esc_html_x('Off', 'cache status', 'optimizr'); ?></span>
        </div>
        <ul class="optimizr-checks">
            <?php foreach ($checks as $label => $on) : ?>
                <li class="optimizr-checks__item">
                    <span class="optimizr-checks__icon optimizr-checks__icon--<?php echo $on ? 'ok' : 'off'; ?>"><?php echo wp_kses(haptic_icon($on ? 'check' : 'alert', 14), haptic_kses_ui()); ?></span>
                    <span class="optimizr-checks__label"><?php echo esc_html($label); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($active && haptic_cache_can_purge()) : ?>
            <div class="optimizr-card__foot">
                <span class="optimizr-card__hint"><?php
                    echo $last
                        /* translators: %s: date and time */
                        ? esc_html(sprintf(__('Last cleared: %s', 'optimizr'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last)))
                        : esc_html__('Never cleared.', 'optimizr');
                ?></span>
                <a class="optimizr-btn optimizr-btn--secondary optimizr-btn--sm" href="<?php echo esc_url(haptic_cache_purge_url()); ?>"><?php esc_html_e('Clear', 'optimizr'); ?></a>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

/** Carte « Versions » : Optimizr, puis les lignes d'une extension (action haptic_dashboard_versions, titre : filtre haptic_dashboard_versions_title). */
function haptic_render_dashboard_versions()
{
    $update = haptic_dashboard_available_update(HAPTIC_OPTIMIZR_FILE);
    ?>
    <section class="optimizr-card optimizr-versions">
        <div class="optimizr-card__header">
            <h2 class="optimizr-card__title"><?php echo esc_html(apply_filters('haptic_dashboard_versions_title', __('Versions', 'optimizr'))); ?></h2>
        </div>
        <?php
        haptic_dashboard_version_row(haptic_dashboard_update_row('Optimizr', HAPTIC_OPTIMIZR_VERSION, $update));
        do_action('haptic_dashboard_versions');
        ?>
    </section>
    <?php
}

/** Ligne d'une extension : version installée, et bouton de mise à jour quand WordPress en propose une. */
function haptic_dashboard_update_row($name, $version, $update)
{
    if ($update === null) {
        return ['name' => $name, 'detail' => $version, 'pill' => __('Up to date', 'optimizr'), 'pill_status' => 'ok'];
    }
    return [
        'name'       => $name,
        /* translators: 1: installed version, 2: available version */
        'detail'     => sprintf(__('%1$s · %2$s available', 'optimizr'), $version, $update),
        'button'     => current_user_can('update_plugins') ? __('Update', 'optimizr') : '',
        'button_url' => self_admin_url('update-core.php'),
        'pill'       => __('Update available', 'optimizr'),
        'pill_status' => 'warn',
    ];
}
