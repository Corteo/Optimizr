<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Tableau de bord des suivis (onglet « Suivi », module Cookies & RGPD).
 *
 * Chaque conversion repérée par js/optimizr-conversions.js envoie un signal anonyme (functions/conversions.php,
 * haptic_conversion_seen()). Optimizr en garde le compte par jour, action, page (chemin sans paramètres) et accord du
 * visiteur pour la mesure d'audience, dans la table {prefix}optimizr_conversions : ni cookie, ni adresse IP, ni
 * donnée du visiteur. Le montant des commandes WooCommerce y est additionné. Les lignes de plus de 400 jours sont
 * effacées chaque jour (WP-Cron).
 *
 * L'onglet affiche la synthèse de la période (7, 30 ou 90 jours), la part des conversions que Google Analytics ne
 * voit pas (visiteurs qui ont refusé les cookies), les conversions par action, les points à vérifier (formulaire
 * qui ne reçoit plus rien, action jamais vue, paniers abandonnés), les pages qui convertissent et la boutique.
 * Optimizr Pro y ajoute les sources de visiteurs (action haptic_tracking_main).
 */

const HAPTIC_TRACKING_DB_VERSION = '1';
const HAPTIC_TRACKING_RETENTION_DAYS = 400;

function haptic_tracking_table()
{
    global $wpdb;
    return $wpdb->prefix . 'optimizr_conversions';
}

/* ---------- Table ---------- */

add_action('admin_init', 'haptic_tracking_install');

function haptic_tracking_install()
{
    if (get_option('haptic_tracking_db') === HAPTIC_TRACKING_DB_VERSION) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = haptic_tracking_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
        day date NOT NULL,
        action varchar(32) NOT NULL,
        page varchar(190) NOT NULL DEFAULT '',
        consent tinyint(1) NOT NULL DEFAULT 2,
        hits int(10) unsigned NOT NULL DEFAULT 0,
        value decimal(14,2) NOT NULL DEFAULT 0,
        PRIMARY KEY  (day,action,page,consent),
        KEY day_action (day,action)
    ) {$charset};");
    update_option('haptic_tracking_db', HAPTIC_TRACKING_DB_VERSION);
    if (!get_option('haptic_tracking_since')) {
        update_option('haptic_tracking_since', current_time('Y-m-d'), false);
    }
}

add_filter('haptic_settings_internal_options', function ($options) {
    return array_merge($options, ['haptic_tracking_db', 'haptic_tracking_since']);
});

/* ---------- Enregistrement ---------- */

/**
 * Compte une conversion.
 *
 * @param string     $action  clé de haptic_conversion_events()
 * @param string     $page    chemin de la page (sans domaine ni paramètres)
 * @param int        $consent 1 : mesure d'audience acceptée ; 0 : refusée ou pas encore choisie ; 2 : aucun outil de mesure
 * @param float      $value   montant (commande WooCommerce)
 */
function haptic_tracking_record($action, $page, $consent, $value = 0.0)
{
    global $wpdb;
    if (get_option('haptic_tracking_db') !== HAPTIC_TRACKING_DB_VERSION) {
        haptic_tracking_install();
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compteur propre au plugin, incrémenté en une requête
    $wpdb->query($wpdb->prepare(
        'INSERT INTO %i (day, action, page, consent, hits, value) VALUES (%s, %s, %s, %d, 1, %f)
         ON DUPLICATE KEY UPDATE hits = hits + 1, value = value + VALUES(value)',
        haptic_tracking_table(),
        current_time('Y-m-d'),
        $action,
        $page,
        $consent,
        $value
    ));
}

/** Chemin de page reçu du navigateur : sans domaine, paramètres ni ancre, 190 caractères au plus. */
function haptic_tracking_clean_page($raw)
{
    $path = (string) wp_parse_url((string) $raw, PHP_URL_PATH);
    $path = '/' . ltrim(sanitize_text_field(rawurldecode($path)), '/');
    // Page de confirmation de commande : le numéro de commande n'a pas d'intérêt ici
    $path = preg_replace('#/(order-received|commande-recue)/\d+/?$#', '/$1/', $path);
    return function_exists('mb_substr') ? mb_substr($path, 0, 190) : substr($path, 0, 190);
}

/**
 * Limite les signaux d'une même adresse IP : 30 par minute. L'adresse n'est gardée que hachée, le temps de la
 * minute (transient), jamais avec les comptes.
 */
function haptic_tracking_throttled()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $key = 'haptic_conv_rate_' . substr(hash_hmac('sha256', $ip . gmdate('YmdHi'), wp_salt('nonce')), 0, 20);
    $count = (int) get_transient($key);
    if ($count >= 30) {
        return true;
    }
    set_transient($key, $count + 1, MINUTE_IN_SECONDS);
    return false;
}

/* ---------- Ménage ---------- */

add_action('init', function () {
    if (!wp_next_scheduled('haptic_tracking_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'haptic_tracking_cleanup');
    }
});

add_action('haptic_tracking_cleanup', function () {
    global $wpdb;
    if (get_option('haptic_tracking_db') !== HAPTIC_TRACKING_DB_VERSION) {
        return;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- effacement des comptes trop anciens, table propre au plugin
    $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE day < %s', haptic_tracking_table(), wp_date('Y-m-d', strtotime('-' . HAPTIC_TRACKING_RETENTION_DAYS . ' days'))));
});

add_action('haptic_deactivate', function () {
    wp_clear_scheduled_hook('haptic_tracking_cleanup');
});

/* ---------- Lecture ---------- */

/** Familles d'actions : clé => [libellé, couleur]. Ajout au panier et commande commencée sont des étapes. */
function haptic_tracking_groups()
{
    return [
        'contact' => [__('Contacts', 'optimizr'), '#3858e9'],
        'visit'   => [__('Appointments and visits', 'optimizr'), '#0f9d8a'],
        'engage'  => [__('Engagement', 'optimizr'), '#b88a00'],
        'shop'    => [__('Shop', 'optimizr'), '#8a3ffc'],
    ];
}

function haptic_tracking_action_group($action)
{
    $map = apply_filters('haptic_tracking_action_groups', [
        'form' => 'contact', 'thankyou' => 'contact', 'phone' => 'contact', 'email' => 'contact', 'whatsapp' => 'contact',
        'booking' => 'visit', 'directions' => 'visit',
        'newsletter' => 'engage', 'cta' => 'engage', 'download' => 'engage', 'review' => 'engage', 'social' => 'engage',
        'add_to_cart' => 'shop', 'begin_checkout' => 'shop', 'purchase' => 'shop',
    ]);
    return $map[$action] ?? 'engage';
}

/** Étapes d'achat : affichées, mais pas comptées dans le total des conversions. */
function haptic_tracking_is_step($action)
{
    return in_array($action, ['add_to_cart', 'begin_checkout'], true);
}

/**
 * Comptes par jour et par action entre deux dates (incluses).
 *
 * @return array<string, array<string, array{hits:int, seen:int, hidden:int, value:float}>> [action][jour]
 */
function haptic_tracking_daily($start, $end)
{
    global $wpdb;
    if (get_option('haptic_tracking_db') !== HAPTIC_TRACKING_DB_VERSION) {
        return [];
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lecture de la table propre au plugin, à l'affichage de l'onglet
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT day, action, consent, SUM(hits) AS hits, SUM(value) AS value FROM %i WHERE day BETWEEN %s AND %s GROUP BY day, action, consent',
        haptic_tracking_table(),
        $start,
        $end
    ), ARRAY_A);
    $data = [];
    foreach ((array) $rows as $row) {
        $cell = &$data[$row['action']][$row['day']];
        $cell = $cell ?? ['hits' => 0, 'seen' => 0, 'hidden' => 0, 'value' => 0.0];
        $cell['hits'] += (int) $row['hits'];
        $cell['value'] += (float) $row['value'];
        if ((int) $row['consent'] === 1) {
            $cell['seen'] += (int) $row['hits'];
        } elseif ((int) $row['consent'] === 0) {
            $cell['hidden'] += (int) $row['hits'];
        }
        unset($cell);
    }
    return $data;
}

/** Pages qui convertissent le plus sur la période (hors étapes d'achat) : [page => [groupe => nombre]]. */
function haptic_tracking_pages($start, $end, $limit = 6)
{
    global $wpdb;
    if (get_option('haptic_tracking_db') !== HAPTIC_TRACKING_DB_VERSION) {
        return [];
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lecture de la table propre au plugin, à l'affichage de l'onglet
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT page, action, SUM(hits) AS hits FROM %i WHERE day BETWEEN %s AND %s AND action NOT IN ('add_to_cart', 'begin_checkout') GROUP BY page, action",
        haptic_tracking_table(),
        $start,
        $end
    ), ARRAY_A);
    $pages = [];
    foreach ((array) $rows as $row) {
        $group = haptic_tracking_action_group($row['action']);
        $pages[$row['page']][$group] = ($pages[$row['page']][$group] ?? 0) + (int) $row['hits'];
    }
    uasort($pages, function ($a, $b) {
        return array_sum($b) <=> array_sum($a);
    });
    return array_slice($pages, 0, $limit, true);
}

/** Périodes proposées (jours). */
function haptic_tracking_periods()
{
    return [7, 30, 90];
}

function haptic_tracking_current_days()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- période affichée, lecture seule
    $days = isset($_GET['days']) ? absint($_GET['days']) : 30;
    return in_array($days, haptic_tracking_periods(), true) ? $days : 30;
}

/** Liste des jours (Y-m-d) de $from jours avant aujourd'hui (exclu) à aujourd'hui moins $to. */
function haptic_tracking_day_list($days, $offset = 0)
{
    $list = [];
    $today = current_time('timestamp'); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- dates locales du site, comme les comptes
    for ($i = $days - 1 + $offset; $i >= $offset; $i--) {
        $list[] = gmdate('Y-m-d', $today - $i * DAY_IN_SECONDS);
    }
    return $list;
}

/** Somme d'un champ sur une liste de jours. */
function haptic_tracking_sum(array $by_day, array $days, $field = 'hits')
{
    $total = 0;
    foreach ($days as $day) {
        $total += $by_day[$day][$field] ?? 0;
    }
    return $total;
}

/**
 * Données de l'onglet pour une période.
 *
 * @return array
 */
function haptic_tracking_report($days)
{
    $current = haptic_tracking_day_list($days);
    $previous = haptic_tracking_day_list($days, $days);
    // Assez d'historique pour repérer une action qui s'est arrêtée (28 jours avant son dernier compte)
    $history = haptic_tracking_day_list(max($days * 2, 60));
    $data = haptic_tracking_daily($history[0], end($current));

    // Pas de comparaison avec une période où Optimizr ne comptait pas encore (évolution trompeuse)
    $comparable = (string) get_option('haptic_tracking_since', '') !== '' && get_option('haptic_tracking_since') <= $previous[0];

    $settings = haptic_conversions_settings();
    $actions = haptic_conversion_actions();
    $report = [
        'days' => $days, 'current' => $current, 'data' => $data,
        'total' => 0, 'previous' => 0, 'groups' => array_fill_keys(array_keys(haptic_tracking_groups()), 0),
        'seen' => 0, 'hidden' => 0, 'revenue' => 0.0, 'orders' => 0, 'rows' => [],
    ];
    foreach ($actions as $key => $action) {
        $by_day = $data[$key] ?? [];
        $tracked = !empty($settings['actions'][$key]);
        $count = haptic_tracking_sum($by_day, $current);
        $before = $comparable ? haptic_tracking_sum($by_day, $previous) : 0;
        $last = '';
        foreach (array_reverse($history) as $day) {
            if (!empty($by_day[$day]['hits'])) {
                $last = $day;
                break;
            }
        }
        $report['rows'][$key] = [
            'title' => $action[0], 'group' => haptic_tracking_action_group($key), 'tracked' => $tracked,
            'count' => $count, 'previous' => $before, 'last' => $last,
            'series' => array_map(function ($day) use ($by_day) {
                return (int) ($by_day[$day]['hits'] ?? 0);
            }, $current),
        ];
        if (haptic_tracking_is_step($key)) {
            continue;
        }
        $report['total'] += $count;
        $report['previous'] += $before;
        $report['groups'][$report['rows'][$key]['group']] += $count;
        $report['seen'] += haptic_tracking_sum($by_day, $current, 'seen');
        $report['hidden'] += haptic_tracking_sum($by_day, $current, 'hidden');
        if ($key === 'purchase') {
            $report['revenue'] = (float) haptic_tracking_sum($by_day, $current, 'value');
            $report['orders'] = $count;
        }
    }
    $report['pages'] = haptic_tracking_pages($current[0], end($current));
    $report['alerts'] = haptic_tracking_alerts($report, $history);
    return $report;
}

/**
 * Points à vérifier : [gravité (bad|warn), titre, explication, lien].
 */
function haptic_tracking_alerts(array $report, array $history)
{
    $alerts = [];
    $today = end($history);
    $since = (string) get_option('haptic_tracking_since', $today);

    if (!get_option('haptic_option_enable_tarteaucitron')) {
        $alerts[] = ['bad', __('The consent banner is off', 'optimizr'), __('No conversion is detected or counted while it is off.', 'optimizr'), haptic_admin_tab_url('cookies')];
    }

    foreach ($report['rows'] as $key => $row) {
        if (!$row['tracked']) {
            continue;
        }
        $by_day = $report['data'][$key] ?? [];
        if ($row['last'] === '') {
            // Jamais vue alors que le compte tourne depuis plus de 14 jours
            if ((strtotime($today) - strtotime($since)) / DAY_IN_SECONDS >= 14 && !haptic_tracking_is_step($key)) {
                $alerts[] = ['warn', sprintf(
                    /* translators: %s: tracked action, e.g. "Contact form sent" */
                    __('%s: never seen', 'optimizr'),
                    $row['title']
                ), __('Tracked for more than two weeks without a single one. Check that it can still happen on the site, or stop tracking it.', 'optimizr'), haptic_campaigns_url()];
            }
            continue;
        }
        // Arrêt anormal : aucune depuis N jours alors qu'il en venait au moins 4 sur une durée équivalente avant
        $gap = (int) round((strtotime($today) - strtotime($row['last'])) / DAY_IN_SECONDS);
        if ($gap < 3) {
            continue;
        }
        $window = [];
        $last_index = array_search($row['last'], $history, true);
        for ($i = max(0, $last_index - 27); $i <= $last_index; $i++) {
            $window[] = $history[$i];
        }
        $average = haptic_tracking_sum($by_day, $window) / max(1, count($window));
        if ($average * $gap >= 4) {
            $alerts[] = ['bad', sprintf(
                /* translators: 1: tracked action, 2: number of days */
                _n('%1$s: none for %2$d day', '%1$s: none for %2$d days', $gap, 'optimizr'),
                $row['title'],
                $gap
            ), sprintf(
                /* translators: %s: usual number per day, e.g. "1.5" */
                __('Usually about %s a day. Try it yourself to check that it still works.', 'optimizr'),
                number_format_i18n($average, $average < 1 ? 1 : 0)
            ), ''];
        }
    }

    $carts = $report['rows']['add_to_cart']['count'] ?? 0;
    $checkouts = $report['rows']['begin_checkout']['count'] ?? 0;
    if ($carts >= 10 && $checkouts / $carts < 0.3) {
        $alerts[] = ['warn', __('Many abandoned carts', 'optimizr'), sprintf(
            /* translators: %s: percentage */
            __('%s of the products added to the cart do not reach the checkout.', 'optimizr'),
            number_format_i18n(100 - $checkouts / $carts * 100) . ' %'
        ), ''];
    }

    return apply_filters('haptic_tracking_alerts', $alerts, $report);
}

/* ---------- Affichage ---------- */

add_filter('haptic_admin_tabs', function ($tabs) {
    return haptic_array_insert_after($tabs, 'dashboard', [
        'tracking' => ['label' => _x('Tracking', 'admin tab', 'optimizr'), 'modules' => ['cookies'], 'group' => '', 'render' => 'haptic_render_tracking_tab'],
    ]);
}, 5);

/** Évolution en pourcentage, ou null sans point de comparaison. */
function haptic_tracking_delta($now, $before)
{
    return $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
}

function haptic_tracking_format_delta($delta)
{
    if ($delta === null) {
        return '—';
    }
    return ($delta > 0 ? '+' : ($delta < 0 ? '−' : '')) . number_format_i18n(abs($delta)) . ' %';
}

function haptic_tracking_ago($day)
{
    $gap = (int) round((strtotime(current_time('Y-m-d')) - strtotime($day)) / DAY_IN_SECONDS);
    if ($gap <= 0) {
        return __('Today', 'optimizr');
    }
    if ($gap === 1) {
        return __('Yesterday', 'optimizr');
    }
    /* translators: %d: number of days */
    return sprintf(_n('%d day ago', '%d days ago', $gap, 'optimizr'), $gap);
}

function haptic_tracking_money($amount)
{
    $currency = function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '€';
    return number_format_i18n($amount, 0) . "\u{00a0}" . $currency;
}

/** Courbe d'une action (SVG), regroupée par semaine au-delà de 14 jours. */
function haptic_tracking_sparkline(array $values, $color)
{
    if (count($values) > 14) {
        $values = array_map('array_sum', array_chunk($values, 7));
    }
    if (count($values) < 2) {
        return '';
    }
    $w = 96;
    $h = 26;
    $max = max(1, max($values));
    $points = [];
    foreach ($values as $i => $v) {
        $points[] = [round($i / (count($values) - 1) * ($w - 4) + 2, 1), round($h - 3 - $v / $max * ($h - 6), 1)];
    }
    $line = '';
    foreach ($points as $i => $p) {
        $line .= ($i ? 'L' : 'M') . $p[0] . ' ' . $p[1];
    }
    $last = end($points);
    $area = $line . 'L' . $last[0] . ' ' . ($h - 1) . 'L' . $points[0][0] . ' ' . ($h - 1) . 'Z';
    return sprintf(
        '<svg class="optimizr-track__spark" viewBox="0 0 %1$d %2$d" aria-hidden="true"><path d="%3$s" fill="%5$s" fill-opacity=".12"/><path d="%4$s" fill="none" stroke="%5$s" stroke-width="1.6" stroke-linejoin="round"/><circle cx="%6$s" cy="%7$s" r="2.4" fill="%5$s"/></svg>',
        $w, $h, esc_attr($area), esc_attr($line), esc_attr($color), esc_attr($last[0]), esc_attr($last[1])
    );
}

function haptic_render_tracking_tab()
{
    $days = haptic_tracking_current_days();
    $report = haptic_tracking_report($days);
    $groups = haptic_tracking_groups();
    $woo = class_exists('WooCommerce');
    $measured = haptic_cookie_service_id('google_analytics') !== '' || haptic_cookie_service_id('google_tags') !== '';
    $tab_url = haptic_admin_tab_url('tracking');
    ?>
    <div class="optimizr-track">
        <div class="optimizr-track__head">
            <div class="optimizr-page-head">
                <h2 class="optimizr-page-head__title"><?php esc_html_e('Campaign tracking', 'optimizr'); ?></h2>
                <p class="optimizr-page-head__intro"><?php esc_html_e('What your visitors do: contacts, appointments, sales. Counted on the site, without a cookie.', 'optimizr'); ?></p>
            </div>
            <div class="optimizr-track__actions">
                <nav class="optimizr-track__periods" aria-label="<?php esc_attr_e('Period', 'optimizr'); ?>">
                    <?php foreach (haptic_tracking_periods() as $period) : ?>
                        <a href="<?php echo esc_url(add_query_arg('days', $period, $tab_url)); ?>"<?php echo $period === $days ? ' aria-current="true"' : ''; ?>><?php
                            /* translators: %d: number of days */
                            echo esc_html(sprintf(__('%d days', 'optimizr'), $period));
                        ?></a>
                    <?php endforeach; ?>
                </nav>
                <a class="optimizr-btn optimizr-btn--secondary" href="<?php echo esc_url(haptic_campaigns_url()); ?>"><?php echo wp_kses(haptic_icon('sparkles', 16), haptic_kses_ui()); ?><?php esc_html_e('Campaign tracking wizard', 'optimizr'); ?></a>
            </div>
        </div>

        <?php haptic_render_tracking_hero($report, $groups, $woo, $measured); ?>

        <div class="optimizr-track__grid">
            <div class="optimizr-track__col">
                <?php haptic_render_tracking_actions($report, $groups); ?>
                <?php haptic_render_tracking_pages($report, $groups); ?>
                <?php
                // Optimizr Pro : sources de visiteurs (Google Analytics 4)
                do_action('haptic_tracking_main', $days, $report);
                if (!haptic_is_pro()) :
                    ?>
                    <section class="optimizr-card optimizr-track__upsell">
                        <div class="optimizr-card__header">
                            <div>
                                <h3 class="optimizr-card__title"><?php esc_html_e('Where visitors come from', 'optimizr'); ?></h3>
                                <p class="optimizr-card__desc"><?php esc_html_e('With Optimizr Pro, visits and conversions for each source (Google search, Google Ads, social networks…) from Google Analytics, next to these figures.', 'optimizr'); ?></p>
                            </div>
                        </div>
                        <div class="optimizr-card__foot">
                            <a class="optimizr-link" href="<?php echo esc_url(HAPTIC_OPTIMIZR_PRO_INFO_URL); ?>" target="_blank" rel="noopener"><?php esc_html_e('Learn more about Optimizr Pro →', 'optimizr'); ?></a>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
            <div class="optimizr-track__col">
                <?php haptic_render_tracking_alerts($report); ?>
                <?php if ($woo && !empty($report['rows']['purchase'])) {
                    haptic_render_tracking_shop($report);
                } ?>
                <?php do_action('haptic_tracking_aside', $days, $report); ?>
            </div>
        </div>
    </div>
    <?php
}

function haptic_render_tracking_hero(array $report, array $groups, $woo, $measured)
{
    $days = $report['days'];
    $delta = haptic_tracking_delta($report['total'], $report['previous']);
    $since = (string) get_option('haptic_tracking_since', '');
    ?>
    <section class="optimizr-track__hero" aria-label="<?php esc_attr_e('Summary', 'optimizr'); ?>">
        <div class="optimizr-track__hero-grid">
            <div class="optimizr-track__summary">
                <span class="optimizr-track__eyebrow"><?php
                    /* translators: %d: number of days */
                    echo esc_html(sprintf(__('Last %d days', 'optimizr'), $days));
                ?></span>
                <p class="optimizr-track__total"><strong><?php echo esc_html(number_format_i18n($report['total'])); ?></strong> <?php echo esc_html(_n('conversion', 'conversions', $report['total'], 'optimizr')); ?>
                    <small><?php
                        /* translators: %s: average number of conversions per day */
                        echo esc_html(sprintf(__('· %s a day', 'optimizr'), number_format_i18n($report['total'] / $days, 1)));
                    ?></small></p>
                <p class="optimizr-track__compare">
                    <?php if ($delta !== null) : ?>
                        <span class="optimizr-track__delta optimizr-track__delta--<?php echo $delta >= 0 ? 'up' : 'down'; ?>"><?php echo esc_html(haptic_tracking_format_delta($delta)); ?></span>
                        <?php
                        /* translators: %d: number of days */
                        echo esc_html(sprintf(__('compared with the previous %d days', 'optimizr'), $days));
                        ?>
                    <?php elseif ($since !== '') : ?>
                        <?php
                        /* translators: %s: date */
                        echo esc_html(sprintf(__('Counted since %s.', 'optimizr'), date_i18n(get_option('date_format'), strtotime($since))));
                        ?>
                    <?php endif; ?>
                </p>
                <div class="optimizr-track__kpis optimizr-track__kpis--<?php echo $woo ? 4 : 3; ?>">
                    <?php foreach (['contact', 'visit', 'engage'] as $group) : ?>
                        <div class="optimizr-track__kpi"><span><i style="background:<?php echo esc_attr($groups[$group][1]); ?>"></i><?php echo esc_html($groups[$group][0]); ?></span><b><?php echo esc_html(number_format_i18n($report['groups'][$group])); ?></b></div>
                    <?php endforeach; ?>
                    <?php if ($woo) : ?>
                        <div class="optimizr-track__kpi"><span><i style="background:<?php echo esc_attr($groups['shop'][1]); ?>"></i><?php esc_html_e('Sales', 'optimizr'); ?></span><b><?php echo esc_html(haptic_tracking_money($report['revenue'])); ?></b>
                            <small><?php
                                /* translators: %s: number of orders */
                                echo esc_html(sprintf(_n('%s order', '%s orders', $report['orders'], 'optimizr'), number_format_i18n($report['orders'])));
                            ?></small></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php haptic_render_tracking_chart($report, $groups, $woo); ?>
        </div>
        <?php
        $known = $report['seen'] + $report['hidden'];
        if ($measured && $known > 0) :
            $hidden = (int) round($report['hidden'] / $known * 100);
            ?>
            <div class="optimizr-track__consent">
                <p><?php echo wp_kses(sprintf(
                    /* translators: %s: percentage */
                    __('<strong>%s of conversions</strong> come from visitors who refused cookies. Optimizr counts them; Google Analytics and Google Ads do not see them.', 'optimizr'),
                    esc_html(number_format_i18n($hidden) . ' %')
                ), ['strong' => []]); ?></p>
                <div>
                    <div class="optimizr-track__consent-bar"><span style="width:<?php echo esc_attr(100 - $hidden); ?>%"></span><span style="width:<?php echo esc_attr($hidden); ?>%"></span></div>
                    <div class="optimizr-track__consent-keys"><span><?php
                        /* translators: %s: number of conversions */
                        echo esc_html(sprintf(__('%s seen by Google', 'optimizr'), number_format_i18n($report['seen'])));
                    ?></span><span><?php
                        /* translators: %s: number of conversions */
                        echo esc_html(sprintf(__('%s without consent', 'optimizr'), number_format_i18n($report['hidden'])));
                    ?></span></div>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

/** Barres empilées par jour (par semaine sur 90 jours), en HTML pour suivre la largeur de la carte. */
function haptic_render_tracking_chart(array $report, array $groups, $woo)
{
    $keys = $woo ? ['contact', 'visit', 'engage', 'shop'] : ['contact', 'visit', 'engage'];
    $days = $report['current'];
    $buckets = array_chunk($days, $report['days'] > 30 ? 7 : 1);
    $bars = [];
    foreach ($buckets as $bucket) {
        $values = array_fill_keys($keys, 0);
        foreach ($report['rows'] as $action => $row) {
            if (haptic_tracking_is_step($action) || !isset($values[$row['group']])) {
                continue;
            }
            foreach ($bucket as $day) {
                $values[$row['group']] += $report['data'][$action][$day]['hits'] ?? 0;
            }
        }
        $bars[] = ['day' => $bucket[0], 'week' => count($bucket) > 1, 'values' => $values];
    }
    $max = 1;
    foreach ($bars as $bar) {
        $max = max($max, array_sum($bar['values']));
    }
    $top = (int) (ceil($max / 4) * 4);
    $format = __('M j', 'optimizr');
    ?>
    <div class="optimizr-track__chart">
        <div class="optimizr-track__legend">
            <?php foreach ($keys as $key) : ?>
                <span><i style="background:<?php echo esc_attr($groups[$key][1]); ?>"></i><?php echo esc_html($groups[$key][0]); ?></span>
            <?php endforeach; ?>
        </div>
        <div class="optimizr-track__plot" role="img" aria-label="<?php esc_attr_e('Conversions per day', 'optimizr'); ?>">
            <div class="optimizr-track__axis" aria-hidden="true"><span><?php echo esc_html(number_format_i18n($top)); ?></span><span><?php echo esc_html(number_format_i18n($top / 2)); ?></span><span>0</span></div>
            <div class="optimizr-track__bars">
                <?php foreach ($bars as $bar) :
                    $total = array_sum($bar['values']);
                    $label = ($bar['week']
                        /* translators: %s: date of the first day of the week */
                        ? sprintf(__('Week of %s', 'optimizr'), date_i18n($format, strtotime($bar['day'])))
                        : date_i18n($format, strtotime($bar['day']))) . ' · ' . number_format_i18n($total);
                    ?>
                    <span class="optimizr-track__bar" title="<?php echo esc_attr($label); ?>">
                        <?php foreach ($bar['values'] as $key => $value) :
                            if (!$value) {
                                continue;
                            }
                            ?>
                            <span style="height:<?php echo esc_attr(round($value / $top * 100, 2)); ?>%;background:<?php echo esc_attr($groups[$key][1]); ?>"></span>
                        <?php endforeach; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="optimizr-track__dates" aria-hidden="true">
            <span><?php echo esc_html(date_i18n($format, strtotime($days[0]))); ?></span>
            <span><?php echo esc_html(date_i18n($format, strtotime(end($days)))); ?></span>
        </div>
    </div>
    <?php
}

function haptic_render_tracking_actions(array $report, array $groups)
{
    $tracked = count(array_filter(array_column($report['rows'], 'tracked')));
    $alerting = [];
    foreach ($report['alerts'] as $alert) {
        $alerting[] = $alert[1];
    }
    ?>
    <section class="optimizr-card">
        <div class="optimizr-card__header">
            <h3 class="optimizr-card__title"><?php esc_html_e('Conversions by action', 'optimizr'); ?></h3>
            <span class="optimizr-card__desc"><?php
                /* translators: %s: number of tracked actions */
                echo esc_html(sprintf(_n('%s action tracked', '%s actions tracked', $tracked, 'optimizr'), number_format_i18n($tracked)));
            ?></span>
        </div>
        <div class="optimizr-track__table-wrap">
            <table class="optimizr-track__table">
                <thead><tr>
                    <th scope="col"><?php esc_html_e('Action', 'optimizr'); ?></th>
                    <th scope="col" class="is-num"><?php esc_html_e('Number', 'optimizr'); ?></th>
                    <th scope="col" class="is-num"><?php esc_html_e('Change', 'optimizr'); ?></th>
                    <th scope="col" class="is-num"><?php esc_html_e('Trend', 'optimizr'); ?></th>
                    <th scope="col"><?php esc_html_e('Last', 'optimizr'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($groups as $group => list($label, $color)) :
                    $rows = array_filter($report['rows'], function ($row) use ($group) {
                        return $row['group'] === $group;
                    });
                    if (!$rows) {
                        continue;
                    }
                    ?>
                    <tr class="optimizr-track__group"><td colspan="5"><i style="background:<?php echo esc_attr($color); ?>"></i><?php echo esc_html($label); ?></td></tr>
                    <?php foreach ($rows as $key => $row) :
                        $delta = haptic_tracking_delta($row['count'], $row['previous']);
                        $trend = $delta === null || abs($delta) <= 3 ? 'flat' : ($delta > 0 ? 'up' : 'down');
                        if (!$row['tracked']) {
                            $status = ['off', _x('Not tracked', 'conversion', 'optimizr')];
                        } elseif ($row['last'] === '') {
                            $status = ['warn', __('Not seen yet', 'optimizr')];
                        } else {
                            $stalled = false;
                            foreach ($alerting as $title) {
                                $stalled = $stalled || strpos($title, $row['title']) === 0;
                            }
                            $status = [$stalled ? 'bad' : 'ok', haptic_tracking_ago($row['last'])];
                        }
                        ?>
                        <tr class="<?php echo $row['tracked'] ? '' : 'is-off'; ?>">
                            <th scope="row"><?php echo esc_html($row['title']); ?><?php if (haptic_tracking_is_step($key)) : ?><span class="optimizr-track__note"><?php esc_html_e('step, not counted in the total', 'optimizr'); ?></span><?php endif; ?></th>
                            <td class="is-num"><b><?php echo $row['tracked'] || $row['count'] ? esc_html(number_format_i18n($row['count'])) : '—'; ?></b></td>
                            <td class="is-num"><span class="optimizr-track__trend optimizr-track__trend--<?php echo esc_attr($trend); ?>"><?php echo esc_html(haptic_tracking_format_delta($delta)); ?></span></td>
                            <td class="is-num"><?php echo $row['tracked'] ? haptic_tracking_sparkline($row['series'], $color) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG construit et échappé par haptic_tracking_sparkline() ?></td>
                            <td><span class="optimizr-status optimizr-status--<?php echo esc_attr($status[0]); ?>"><?php echo esc_html($status[1]); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="optimizr-card__foot">
            <span><?php esc_html_e('A greyed-out action is not tracked.', 'optimizr'); ?></span>
            <a class="optimizr-link" href="<?php echo esc_url(haptic_campaigns_url(['step' => 'conversions'])); ?>"><?php esc_html_e('Choose the tracked actions →', 'optimizr'); ?></a>
        </div>
    </section>
    <?php
}

function haptic_render_tracking_pages(array $report, array $groups)
{
    if (!$report['pages']) {
        return;
    }
    $max = max(1, array_sum(reset($report['pages'])));
    ?>
    <section class="optimizr-card">
        <div class="optimizr-card__header">
            <h3 class="optimizr-card__title"><?php esc_html_e('Pages that convert', 'optimizr'); ?></h3>
            <span class="optimizr-card__desc"><?php esc_html_e('page where the action happened', 'optimizr'); ?></span>
        </div>
        <div class="optimizr-card__body">
            <ul class="optimizr-track__pages">
                <?php foreach ($report['pages'] as $page => $by_group) :
                    $total = array_sum($by_group);
                    ?>
                    <li>
                        <a class="optimizr-track__path" href="<?php echo esc_url(home_url($page)); ?>" target="_blank" rel="noopener"><?php echo esc_html($page); ?></a>
                        <b><?php echo esc_html(number_format_i18n($total)); ?></b>
                        <span class="optimizr-track__pagebar">
                            <?php foreach ($groups as $group => list($label, $color)) :
                                if (empty($by_group[$group])) {
                                    continue;
                                }
                                ?>
                                <span style="width:<?php echo esc_attr(round($by_group[$group] / $max * 100, 2)); ?>%;background:<?php echo esc_attr($color); ?>" title="<?php echo esc_attr($label . ' · ' . number_format_i18n($by_group[$group])); ?>"></span>
                            <?php endforeach; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php
}

function haptic_render_tracking_alerts(array $report)
{
    $alerts = $report['alerts'];
    ?>
    <section class="optimizr-card">
        <div class="optimizr-card__header">
            <h3 class="optimizr-card__title"><?php esc_html_e('To check', 'optimizr'); ?></h3>
            <?php if ($alerts) : ?>
                <span class="optimizr-card__desc"><?php
                    /* translators: %s: number of points */
                    echo esc_html(sprintf(_n('%s point', '%s points', count($alerts), 'optimizr'), number_format_i18n(count($alerts))));
                ?></span>
            <?php endif; ?>
        </div>
        <?php if (!$alerts) : ?>
            <div class="optimizr-track__alert optimizr-track__alert--ok">
                <span class="optimizr-track__alert-icon"><?php echo wp_kses(haptic_icon('check', 14), haptic_kses_ui()); ?></span>
                <div><b><?php esc_html_e('Nothing to report', 'optimizr'); ?></b><span><?php esc_html_e('Every tracked action is behaving as usual.', 'optimizr'); ?></span></div>
            </div>
        <?php endif; ?>
        <?php foreach ($alerts as list($level, $title, $text, $link)) : ?>
            <div class="optimizr-track__alert optimizr-track__alert--<?php echo esc_attr($level); ?>">
                <span class="optimizr-track__alert-icon">!</span>
                <div>
                    <b><?php echo esc_html($title); ?></b>
                    <span><?php echo esc_html($text); ?></span>
                    <?php if ($link) : ?><a class="optimizr-link" href="<?php echo esc_url($link); ?>"><?php esc_html_e('Fix →', 'optimizr'); ?></a><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
    <?php
}

function haptic_render_tracking_shop(array $report)
{
    $rows = $report['rows'];
    $steps = [];
    foreach (['add_to_cart', 'begin_checkout', 'purchase'] as $key) {
        if (isset($rows[$key])) {
            $steps[] = [$rows[$key]['title'], $rows[$key]['count']];
        }
    }
    $first = max(1, $steps ? $steps[0][1] : 1);
    ?>
    <section class="optimizr-card">
        <div class="optimizr-card__header">
            <h3 class="optimizr-card__title"><?php esc_html_e('Shop', 'optimizr'); ?></h3>
            <span class="optimizr-card__desc"><?php esc_html_e('excluding shipping and taxes', 'optimizr'); ?></span>
        </div>
        <div class="optimizr-card__body">
            <div class="optimizr-track__shop">
                <div><span><?php esc_html_e('Sales', 'optimizr'); ?></span><b><?php echo esc_html(haptic_tracking_money($report['revenue'])); ?></b></div>
                <div><span><?php esc_html_e('Orders', 'optimizr'); ?></span><b><?php echo esc_html(number_format_i18n($report['orders'])); ?></b></div>
                <div><span><?php esc_html_e('Average order', 'optimizr'); ?></span><b><?php echo esc_html(haptic_tracking_money($report['orders'] ? $report['revenue'] / $report['orders'] : 0)); ?></b></div>
            </div>
            <div class="optimizr-track__funnel">
                <?php foreach ($steps as $i => list($label, $count)) : ?>
                    <div class="optimizr-track__step">
                        <span><?php echo esc_html($label); ?></span>
                        <span class="optimizr-track__stepbar"><span style="width:<?php echo esc_attr(round($count / $first * 100, 2)); ?>%"></span></span>
                        <b><?php echo esc_html(number_format_i18n($count)); ?></b>
                        <?php if ($i > 0 && $steps[$i - 1][1] > 0) : ?>
                            <small><?php
                                /* translators: %s: percentage */
                                echo esc_html(sprintf(__('%s stop before this step', 'optimizr'), number_format_i18n(max(0, 100 - $count / $steps[$i - 1][1] * 100)) . ' %'));
                            ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}
