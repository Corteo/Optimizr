<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Onglet Audit : audit Lighthouse d'une page du site (performance, accessibilité, bonnes pratiques, SEO),
 * exécuté par Google avec l'API PageSpeed Insights, à la demande d'un administrateur.
 * Rien n'est envoyé à Google sans clic sur « Lancer l'audit ».
 */

const HAPTIC_AUDIT_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

/** Nombre d'audits conservés dans l'historique. */
const HAPTIC_AUDIT_HISTORY_MAX = 30;

/** Points à améliorer affichés par catégorie (les plus importants d'abord). */
const HAPTIC_AUDIT_ISSUES_MAX = 10;

/** Catégories Lighthouse demandées, dans l'ordre d'affichage. */
function haptic_audit_categories()
{
    return [
        'performance'    => __('Performance', 'optimizr'),
        'accessibility'  => __('Accessibility', 'optimizr'),
        'best-practices' => __('Best practices', 'optimizr'),
        'seo'            => __('SEO', 'optimizr'),
    ];
}

function haptic_audit_strategies()
{
    return [
        'mobile'  => __('Mobile', 'optimizr'),
        'desktop' => __('Desktop', 'optimizr'),
    ];
}

/** Mesures de laboratoire affichées sous les scores (audits Lighthouse). */
function haptic_audit_metric_ids()
{
    return ['first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index'];
}

function haptic_audit_api_key()
{
    return trim((string) get_option('haptic_audit_api_key', ''));
}

add_action('admin_init', function () {
    register_setting(haptic_settings_group('audit'), 'haptic_audit_api_key', [
        'type'              => 'string',
        'sanitize_callback' => function ($value) {
            return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $value);
        },
    ]);
});

/**
 * Adresse à auditer : une page de ce site (adresse complète ou chemin).
 *
 * @return string|WP_Error
 */
function haptic_audit_normalize_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return home_url('/');
    }
    if ($url[0] === '/') {
        $url = home_url($url);
    }

    $url = esc_url_raw($url, ['http', 'https']);
    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    if ($url === '' || $host !== strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
        return new WP_Error('haptic_audit_url', __('Enter the address of a page of this site.', 'optimizr'));
    }
    return $url;
}

/** Site inaccessible depuis Internet (installation locale) : Google ne pourra pas l'ouvrir. */
function haptic_audit_site_is_local()
{
    $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    if ($host === '' || $host === 'localhost' || preg_match('/\.(localhost|local|test|invalid|example|lan|internal)$/', $host)) {
        return true;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    return false;
}

/**
 * Lance un audit avec l'API PageSpeed Insights (Lighthouse exécuté par Google).
 *
 * @return array|WP_Error Résultat condensé (cf. haptic_audit_parse())
 */
function haptic_audit_run($url, $strategy)
{
    $key = haptic_audit_api_key();
    if ($key === '') {
        return new WP_Error('haptic_audit_key', __('Add a Google API key first.', 'optimizr'));
    }

    // Paramètre category répété : http_build_query() ne sait pas le faire
    $query = 'url=' . rawurlencode($url) . '&strategy=' . rawurlencode($strategy)
        . '&locale=' . rawurlencode(str_replace('_', '-', get_user_locale())) . '&key=' . rawurlencode($key);
    foreach (array_keys(haptic_audit_categories()) as $category) {
        $query .= '&category=' . rawurlencode(strtoupper(str_replace('-', '_', $category)));
    }

    // Lighthouse met de 10 à 60 secondes à analyser une page
    if (function_exists('set_time_limit')) {
        set_time_limit(180); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- requête AJAX d'un administrateur, plus longue que le délai PHP habituel (30 s)
    }
    $response = wp_remote_get(HAPTIC_AUDIT_ENDPOINT . '?' . $query, ['timeout' => 120]);

    if (is_wp_error($response)) {
        /* translators: %s: error message */
        return new WP_Error('haptic_audit_http', sprintf(__('Google PageSpeed Insights did not respond: %s', 'optimizr'), $response->get_error_message()));
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if ($code !== 200 || !is_array($data) || empty($data['lighthouseResult'])) {
        return haptic_audit_api_error($code, is_array($data) ? $data : []);
    }

    return haptic_audit_parse($data, $strategy);
}

/** Message d'erreur compréhensible à partir d'une réponse d'erreur de l'API. */
function haptic_audit_api_error($code, array $data)
{
    $message = (string) ($data['error']['message'] ?? '');
    $reason = (string) ($data['error']['errors'][0]['reason'] ?? '');

    if ($code === 429) {
        return new WP_Error('haptic_audit_quota', __('The quota of this API key is used up. Try again later, or check its quotas in the Google Cloud console.', 'optimizr'));
    }
    if (stripos($message, 'API key not valid') !== false || $reason === 'keyInvalid') {
        return new WP_Error('haptic_audit_key', __('Google rejects this API key. Check it, then save it again.', 'optimizr'));
    }
    if ($code === 403) {
        return new WP_Error('haptic_audit_forbidden', __('This API key cannot use PageSpeed Insights. In the Google Cloud console, enable the "PageSpeed Insights API" for its project and check its restrictions.', 'optimizr'));
    }
    if (stripos($message, 'Lighthouse returned error') !== false) {
        $hint = haptic_audit_site_is_local()
            ? __('This site seems to be on a local or private address: Google cannot open it.', 'optimizr')
            : __('Check that the page opens for visitors who are not logged in (maintenance mode, password, firewall).', 'optimizr');
        /* translators: 1: error returned by Lighthouse, 2: advice */
        return new WP_Error('haptic_audit_lighthouse', sprintf(__('Google could not analyze the page (%1$s). %2$s', 'optimizr'), $message, $hint));
    }

    /* translators: 1: HTTP status code, 2: error message returned by Google */
    return new WP_Error('haptic_audit_api', sprintf(__('Google PageSpeed Insights returned an error (%1$d): %2$s', 'optimizr'), $code, $message !== '' ? $message : __('unknown error', 'optimizr')));
}

/**
 * Condense la réponse de l'API (plusieurs centaines de Ko) en ce qu'affiche l'onglet.
 *
 * @return array url, strategy, time, version, scores, metrics, field, issues
 */
function haptic_audit_parse(array $data, $strategy)
{
    $lh = $data['lighthouseResult'];
    $audits = $lh['audits'] ?? [];
    $result = [
        'url'      => (string) ($lh['finalDisplayedUrl'] ?? $lh['finalUrl'] ?? $data['id'] ?? ''),
        'strategy' => $strategy,
        'time'     => time(),
        'version'  => (string) ($lh['lighthouseVersion'] ?? ''),
        'scores'   => [],
        'titles'   => [],
        'metrics'  => [],
        'field'    => haptic_audit_parse_field($data['loadingExperience'] ?? []),
        'issues'   => [],
    ];

    foreach (array_keys(haptic_audit_categories()) as $slug) {
        $category = $lh['categories'][$slug] ?? null;
        if (!$category) {
            continue;
        }
        $result['scores'][$slug] = isset($category['score']) ? (int) round($category['score'] * 100) : null;
        $result['titles'][$slug] = (string) ($category['title'] ?? '');
        $result['issues'][$slug] = haptic_audit_parse_issues($category, $audits, $slug);
    }

    foreach (haptic_audit_metric_ids() as $id) {
        if (empty($audits[$id])) {
            continue;
        }
        $result['metrics'][$id] = [
            'title' => (string) ($audits[$id]['title'] ?? $id),
            'value' => (string) ($audits[$id]['displayValue'] ?? ''),
            'score' => isset($audits[$id]['score']) ? (int) round($audits[$id]['score'] * 100) : null,
        ];
    }

    return $result;
}

/**
 * Points à améliorer d'une catégorie : audits notés sous 90, sans les mesures (affichées à part)
 * ni les audits masqués par Lighthouse (doublons des « insights »).
 */
function haptic_audit_parse_issues(array $category, array $audits, $slug)
{
    $issues = [];

    foreach ((array) ($category['auditRefs'] ?? []) as $ref) {
        $group = $ref['group'] ?? '';
        $audit = $audits[$ref['id'] ?? ''] ?? null;
        if (!$audit || in_array($group, ['metrics', 'hidden'], true)
            || !in_array($audit['scoreDisplayMode'] ?? '', ['numeric', 'binary', 'metricSavings'], true)
            || !isset($audit['score']) || $audit['score'] >= 0.9) {
            continue;
        }

        $savings = array_sum(array_map('floatval', (array) ($audit['metricSavings'] ?? [])));
        if (!$savings && isset($audit['details']['overallSavingsMs'])) {
            $savings = (float) $audit['details']['overallSavingsMs'];
        }

        $issues[] = [
            'title'   => (string) ($audit['title'] ?? $ref['id']),
            'value'   => (string) ($audit['displayValue'] ?? ''),
            'desc'    => (string) ($audit['description'] ?? ''),
            'score'   => (int) round($audit['score'] * 100),
            'weight'  => (float) ($ref['weight'] ?? 0),
            'savings' => $savings,
        ];
    }

    // Performance : gain estimé d'abord ; autres catégories : poids dans le score, puis note
    usort($issues, function ($a, $b) use ($slug) {
        if ($slug === 'performance' && $a['savings'] !== $b['savings']) {
            return $b['savings'] <=> $a['savings'];
        }
        return [$b['weight'], $a['score']] <=> [$a['weight'], $b['score']];
    });

    return [
        'total' => count($issues),
        'items' => array_map(function ($issue) {
            unset($issue['weight'], $issue['savings']);
            return $issue;
        }, array_slice($issues, 0, HAPTIC_AUDIT_ISSUES_MAX)),
    ];
}

/** Données réelles des visiteurs (Chrome UX Report), quand Google en a assez pour la page ou le domaine. */
function haptic_audit_parse_field(array $experience)
{
    $metrics = $experience['metrics'] ?? [];
    if (empty($metrics)) {
        return null;
    }

    $field = [
        'origin'  => !empty($experience['origin_fallback']),
        'overall' => (string) ($experience['overall_category'] ?? ''),
        'metrics' => [],
    ];
    $known = [
        'LARGEST_CONTENTFUL_PAINT_MS'    => 'lcp',
        'INTERACTION_TO_NEXT_PAINT'      => 'inp',
        'CUMULATIVE_LAYOUT_SHIFT_SCORE'  => 'cls',
        'FIRST_CONTENTFUL_PAINT_MS'      => 'fcp',
        'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => 'ttfb',
    ];
    foreach ($known as $key => $id) {
        if (isset($metrics[$key]['percentile'])) {
            $field['metrics'][$id] = [
                'value'    => (float) $metrics[$key]['percentile'],
                'category' => (string) ($metrics[$key]['category'] ?? ''),
            ];
        }
    }
    return $field['metrics'] ? $field : null;
}

/* ---------- Résultats enregistrés ---------- */

/** Dernier audit de chaque appareil : ['mobile' => [...], 'desktop' => [...]]. */
function haptic_audit_results()
{
    $results = get_option('haptic_audit_results', []);
    return is_array($results) ? $results : [];
}

function haptic_audit_history()
{
    $history = get_option('haptic_audit_history', []);
    return is_array($history) ? $history : [];
}

function haptic_audit_save(array $result)
{
    $results = haptic_audit_results();
    $results[$result['strategy']] = $result;
    update_option('haptic_audit_results', $results, false);

    $history = haptic_audit_history();
    array_unshift($history, [
        'time'     => $result['time'],
        'url'      => $result['url'],
        'strategy' => $result['strategy'],
        'scores'   => $result['scores'],
    ]);
    update_option('haptic_audit_history', array_slice($history, 0, HAPTIC_AUDIT_HISTORY_MAX), false);
}

/* ---------- AJAX ---------- */

add_action('wp_ajax_haptic_audit_run', function () {
    check_ajax_referer('haptic_audit_run');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Access denied', 'optimizr')], 403);
    }

    $strategy = sanitize_key(wp_unslash($_POST['strategy'] ?? 'mobile'));
    if (!array_key_exists($strategy, haptic_audit_strategies())) {
        $strategy = 'mobile';
    }
    $url = haptic_audit_normalize_url(esc_url_raw(wp_unslash($_POST['url'] ?? '')));
    if (is_wp_error($url)) {
        wp_send_json_error(['message' => $url->get_error_message()]);
    }

    // Un audit à la fois : chacun consomme le quota de la clé
    if (get_transient('haptic_audit_lock')) {
        wp_send_json_error(['message' => __('An audit is already running. Wait for it to finish.', 'optimizr')]);
    }
    set_transient('haptic_audit_lock', 1, 3 * MINUTE_IN_SECONDS);
    $result = haptic_audit_run($url, $strategy);
    delete_transient('haptic_audit_lock');

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    haptic_audit_save($result);
    wp_send_json_success([
        'strategy' => $strategy,
        'html'     => haptic_audit_result_html($result),
        'history'  => haptic_audit_history_html(),
    ]);
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== HAPTIC_OPTIMIZR_ADMIN_HOOK || haptic_current_tab() !== 'audit') {
        return;
    }
    $js = HAPTIC_OPTIMIZR_PATH . 'js/optimizr-audit.js';
    wp_enqueue_script('optimizr-audit', HAPTIC_OPTIMIZR_URL . 'js/optimizr-audit.js', [], filemtime($js), true);
});

/* ---------- Affichage ---------- */

/** Note Lighthouse : pass (90 et plus), average (50 à 89), fail (moins de 50). */
function haptic_audit_rating($score)
{
    if ($score === null) {
        return 'na';
    }
    return $score >= 90 ? 'pass' : ($score >= 50 ? 'average' : 'fail');
}

/** Balises autorisées dans le HTML des résultats : celles des contenus, plus les icônes et jauges SVG. */
function haptic_audit_kses()
{
    return array_merge(wp_kses_allowed_html('post'), haptic_kses_ui());
}

/** Description Lighthouse (Markdown limité : liens et code) en HTML. */
function haptic_audit_markdown($text)
{
    $html = esc_html($text);
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    return preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $url = esc_url(html_entity_decode($m[2]), ['https', 'http']);
        return $url ? '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>' : $m[1];
    }, $html);
}

function haptic_audit_gauge($score, $label)
{
    $circumference = 2 * M_PI * 26;
    $length = $score === null ? 0 : $circumference * $score / 100;

    return '<div class="optimizr-audit-score optimizr-audit--' . esc_attr(haptic_audit_rating($score)) . '">'
        . '<span class="optimizr-audit-score__gauge">'
        . '<svg width="72" height="72" viewBox="0 0 64 64" aria-hidden="true" focusable="false">'
        . '<circle cx="32" cy="32" r="26" fill="none" stroke-width="6" class="optimizr-audit-score__track"/>'
        . '<circle cx="32" cy="32" r="26" fill="none" stroke-width="6" stroke-linecap="round" class="optimizr-audit-score__bar"'
        . ' stroke-dasharray="' . esc_attr(round($length, 2) . ' ' . round($circumference, 2)) . '" transform="rotate(-90 32 32)"/>'
        . '</svg>'
        . '<span class="optimizr-audit-score__value">' . esc_html($score === null ? '–' : (string) $score) . '</span>'
        . '</span>'
        . '<span class="optimizr-audit-score__label">' . esc_html($label) . '</span>'
        . '</div>';
}

/** Valeur d'une donnée réelle (Chrome UX Report), au format de la langue du site. */
function haptic_audit_field_value($id, $value)
{
    if ($id === 'cls') {
        return number_format_i18n($value / 100, 2);
    }
    if (in_array($id, ['inp', 'ttfb'], true) || $value < 1000) {
        /* translators: %s: duration in milliseconds */
        return sprintf(__('%s ms', 'optimizr'), number_format_i18n($value));
    }
    /* translators: %s: duration in seconds */
    return sprintf(__('%s s', 'optimizr'), number_format_i18n($value / 1000, 1));
}

function haptic_audit_result_html(array $result)
{
    $categories = haptic_audit_categories();
    $strategies = haptic_audit_strategies();
    $report = add_query_arg([
        'url'         => rawurlencode($result['url']),
        'form_factor' => $result['strategy'],
    ], 'https://pagespeed.web.dev/analysis');

    $html = '<p class="optimizr-audit-meta">'
        . sprintf(
            /* translators: 1: page URL, 2: device (Mobile or Desktop), 3: date and time */
            esc_html__('%1$s · %2$s · %3$s', 'optimizr'),
            '<a href="' . esc_url($result['url']) . '" target="_blank" rel="noopener">' . esc_html($result['url']) . '</a>',
            esc_html($strategies[$result['strategy']] ?? $result['strategy']),
            esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $result['time']))
        )
        . ' · <a href="' . esc_url($report) . '" target="_blank" rel="noopener">' . esc_html__('Full report on PageSpeed Insights', 'optimizr') . '</a></p>';

    $html .= '<div class="optimizr-audit-scores">';
    foreach ($categories as $slug => $label) {
        if (array_key_exists($slug, $result['scores'])) {
            $html .= haptic_audit_gauge($result['scores'][$slug], $result['titles'][$slug] ?: $label);
        }
    }
    $html .= '</div>';

    if ($result['metrics']) {
        $html .= '<section class="optimizr-card"><div class="optimizr-card__header"><div>'
            . '<h3 class="optimizr-card__title">' . esc_html__('Measurements', 'optimizr') . '</h3>'
            . '<p class="optimizr-card__desc">' . esc_html__('Measured by Lighthouse during this audit, on a simulated device and connection.', 'optimizr') . '</p>'
            . '</div></div><div class="optimizr-card__body"><dl class="optimizr-audit-metrics">';
        foreach ($result['metrics'] as $metric) {
            $html .= '<div class="optimizr-audit-metric optimizr-audit--' . esc_attr(haptic_audit_rating($metric['score'])) . '">'
                . '<dt>' . esc_html($metric['title']) . '</dt><dd>' . esc_html($metric['value']) . '</dd></div>';
        }
        $html .= '</dl></div></section>';
    }

    $html .= haptic_audit_field_html($result['field']);

    foreach ($categories as $slug => $label) {
        if (!isset($result['issues'][$slug])) {
            continue;
        }
        $issues = $result['issues'][$slug];
        $title = $result['titles'][$slug] ?: $label;
        $html .= '<section class="optimizr-card"><div class="optimizr-card__header"><div>'
            . '<h3 class="optimizr-card__title">' . esc_html($title) . '</h3>'
            . '<p class="optimizr-card__desc">' . esc_html($issues['total']
                /* translators: %s: number of items */
                ? sprintf(_n('%s point to improve', '%s points to improve', $issues['total'], 'optimizr'), number_format_i18n($issues['total']))
                : __('Nothing to improve.', 'optimizr'))
            . ($issues['total'] > count($issues['items'])
                /* translators: %s: number of items shown */
                ? ' ' . esc_html(sprintf(__('(the %s most important are shown)', 'optimizr'), number_format_i18n(count($issues['items']))))
                : '')
            . '</p></div></div>';

        if ($issues['items']) {
            $html .= '<ul class="optimizr-audit-issues">';
            foreach ($issues['items'] as $issue) {
                $html .= '<li class="optimizr-audit--' . esc_attr(haptic_audit_rating($issue['score'])) . '"><details><summary>'
                    . '<span class="optimizr-audit-issues__title">' . haptic_audit_markdown($issue['title']) . '</span>'
                    . ($issue['value'] !== '' ? '<span class="optimizr-audit-issues__value">' . esc_html($issue['value']) . '</span>' : '')
                    . '</summary><p>' . haptic_audit_markdown($issue['desc']) . '</p></details></li>';
            }
            $html .= '</ul>';
        }
        $html .= '</section>';
    }

    return $html;
}

function haptic_audit_field_html($field)
{
    $html = '<section class="optimizr-card"><div class="optimizr-card__header"><div>'
        . '<h3 class="optimizr-card__title">' . esc_html__('Real visitors', 'optimizr') . '</h3>';

    if (!$field) {
        return $html . '<p class="optimizr-card__desc">' . esc_html__('Not enough Chrome visits on this page and this site for Google to publish real-world data (Chrome UX Report, last 28 days). Lighthouse measurements above remain valid.', 'optimizr') . '</p></div></div></section>';
    }

    $labels = [
        'lcp'  => __('Largest Contentful Paint (LCP)', 'optimizr'),
        'inp'  => __('Interaction to Next Paint (INP)', 'optimizr'),
        'cls'  => __('Cumulative Layout Shift (CLS)', 'optimizr'),
        'fcp'  => __('First Contentful Paint (FCP)', 'optimizr'),
        'ttfb' => __('Time to First Byte (TTFB)', 'optimizr'),
    ];
    $ratings = ['FAST' => 'pass', 'AVERAGE' => 'average', 'SLOW' => 'fail'];

    $html .= '<p class="optimizr-card__desc">' . esc_html($field['origin']
        ? __('Experience of Chrome users over the last 28 days, for the whole site (not enough visits on this page alone). 75% of visits do at least as well.', 'optimizr')
        : __('Experience of Chrome users over the last 28 days, on this page. 75% of visits do at least as well.', 'optimizr')) . '</p>';
    if (isset($ratings[$field['overall']])) {
        $html .= '<p class="optimizr-audit-verdict optimizr-audit--' . esc_attr($ratings[$field['overall']]) . '">'
            . esc_html($field['overall'] === 'FAST' ? __('Core Web Vitals assessment: passed', 'optimizr') : __('Core Web Vitals assessment: failed', 'optimizr')) . '</p>';
    }
    $html .= '</div></div><div class="optimizr-card__body"><dl class="optimizr-audit-metrics">';
    foreach ($field['metrics'] as $id => $metric) {
        $html .= '<div class="optimizr-audit-metric optimizr-audit--' . esc_attr($ratings[$metric['category']] ?? 'na') . '">'
            . '<dt>' . esc_html($labels[$id]) . '</dt><dd>' . esc_html(haptic_audit_field_value($id, $metric['value'])) . '</dd></div>';
    }
    return $html . '</dl></div></section>';
}

function haptic_audit_history_html()
{
    $history = haptic_audit_history();
    if (!$history) {
        return '<p class="optimizr-card__empty">' . esc_html__('No audit yet.', 'optimizr') . '</p>';
    }

    $strategies = haptic_audit_strategies();
    $html = '<div class="optimizr-table-wrap optimizr-table-wrap--flush"><table class="optimizr-table optimizr-audit-history"><thead><tr>'
        . '<th scope="col">' . esc_html__('Date', 'optimizr') . '</th>';
    $short = [
        'performance'    => _x('Perf.', 'short for Performance', 'optimizr'),
        'accessibility'  => _x('Access.', 'short for Accessibility', 'optimizr'),
        'best-practices' => _x('Pract.', 'short for Best practices', 'optimizr'),
        'seo'            => __('SEO', 'optimizr'),
    ];
    foreach (haptic_audit_categories() as $slug => $label) {
        $html .= '<th scope="col"><abbr title="' . esc_attr($label) . '">' . esc_html($short[$slug]) . '</abbr></th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($history as $entry) {
        $path = (string) wp_parse_url($entry['url'], PHP_URL_PATH);
        $html .= '<tr><th scope="row" title="' . esc_attr($entry['url']) . '">'
            . esc_html(wp_date(get_option('date_format'), $entry['time']))
            . '<span class="optimizr-audit-history__page">' . esc_html(($strategies[$entry['strategy']] ?? $entry['strategy']) . ' · ' . ($path !== '' ? $path : '/')) . '</span></th>';
        foreach (array_keys(haptic_audit_categories()) as $slug) {
            $score = $entry['scores'][$slug] ?? null;
            $html .= '<td><span class="optimizr-audit-pill optimizr-audit--' . esc_attr(haptic_audit_rating($score)) . '">' . esc_html($score === null ? '–' : (string) $score) . '</span></td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

function haptic_render_audit_tab()
{
    $key = haptic_audit_api_key();
    $results = haptic_audit_results();
    $strategies = haptic_audit_strategies();
    $current = isset($results['mobile']) || !isset($results['desktop']) ? 'mobile' : 'desktop';
    $last_url = $results[$current]['url'] ?? home_url('/');
    ?>
    <div class="optimizr-page-head">
        <h2 class="optimizr-page-head__title"><?php esc_html_e('Lighthouse audit', 'optimizr'); ?></h2>
        <p class="optimizr-page-head__intro"><?php esc_html_e('Performance, accessibility, best practices and SEO scores of a page, measured by Google with Lighthouse.', 'optimizr'); ?></p>
    </div>

    <?php if (haptic_audit_site_is_local()) : ?>
        <div class="optimizr-notice optimizr-notice--warn"><?php echo wp_kses(haptic_icon('alert', 18), haptic_kses_ui()); ?><span><strong><?php esc_html_e('Local site.', 'optimizr'); ?></strong> <?php esc_html_e('The audit is run by Google\'s servers: they cannot open a site on a local or private address. Run it once the site is online.', 'optimizr'); ?></span></div>
    <?php endif; ?>

    <div class="optimizr-layout optimizr-layout--aside">
        <div class="optimizr-layout__main">
            <form class="optimizr-card" data-optimizr-audit
                data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                data-nonce="<?php echo esc_attr(wp_create_nonce('haptic_audit_run')); ?>"
                <?php /* translators: %d: elapsed seconds */ ?>
                data-running="<?php echo esc_attr__('Audit in progress… %d s (usually 15 to 60 seconds)', 'optimizr'); ?>"
                data-error="<?php esc_attr_e('The audit could not be completed. Reload the page and try again.', 'optimizr'); ?>">
                <div class="optimizr-card__header">
                    <div>
                        <h3 class="optimizr-card__title"><?php esc_html_e('Run an audit', 'optimizr'); ?></h3>
                        <p class="optimizr-card__desc"><?php esc_html_e('Google loads the page like a first-time visitor, on a mid-range phone with a 4G connection, or on a computer.', 'optimizr'); ?></p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <div class="optimizr-field optimizr-field--stacked">
                        <div class="optimizr-field__label"><label for="haptic-audit-url"><?php esc_html_e('Page', 'optimizr'); ?></label></div>
                        <div class="optimizr-field__control">
                            <input type="url" id="haptic-audit-url" name="url" class="large-text" value="<?php echo esc_attr($last_url); ?>" required>
                        </div>
                    </div>
                    <div class="optimizr-audit-run">
                        <div class="optimizr-segmented" role="radiogroup" aria-label="<?php esc_attr_e('Device', 'optimizr'); ?>">
                            <?php foreach ($strategies as $slug => $label) : ?>
                                <label class="optimizr-segmented__option"><input type="radio" name="strategy" value="<?php echo esc_attr($slug); ?>" <?php checked($slug, $current); ?>><span><?php echo esc_html($label); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="optimizr-btn optimizr-btn--primary" <?php disabled($key, ''); ?>><?php esc_html_e('Run the audit', 'optimizr'); ?></button>
                    </div>
                    <p class="optimizr-audit-status" data-audit-status role="status" aria-live="polite" <?php echo $key === '' ? '' : 'hidden'; ?>><?php
                        if ($key === '') {
                            esc_html_e('Add a Google API key (free) to run audits.', 'optimizr');
                        }
                    ?></p>
                </div>
            </form>

            <?php foreach ($strategies as $slug => $label) : ?>
                <div class="optimizr-audit-result" data-audit-panel="<?php echo esc_attr($slug); ?>" <?php echo $slug === $current ? '' : 'hidden'; ?>>
                    <?php if (isset($results[$slug])) : ?>
                        <?php echo wp_kses(haptic_audit_result_html($results[$slug]), haptic_audit_kses()); ?>
                    <?php else : ?>
                        <p class="optimizr-audit-empty"><?php echo esc_html($slug === 'desktop' ? __('No desktop audit yet.', 'optimizr') : __('No mobile audit yet.', 'optimizr')); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="optimizr-layout__aside">
            <form class="optimizr-card" method="post" action="options.php">
                <?php settings_fields(haptic_settings_group('audit')); ?>
                <div class="optimizr-card__header">
                    <div>
                        <h3 class="optimizr-card__title"><?php esc_html_e('Google API key', 'optimizr'); ?></h3>
                        <p class="optimizr-card__desc"><?php esc_html_e('Google requires a key to use PageSpeed Insights. It is free, with up to 25,000 audits per day.', 'optimizr'); ?></p>
                    </div>
                </div>
                <div class="optimizr-card__body">
                    <label class="screen-reader-text" for="haptic-audit-api-key"><?php esc_html_e('Google API key', 'optimizr'); ?></label>
                    <input type="password" id="haptic-audit-api-key" name="haptic_audit_api_key" class="large-text" value="<?php echo esc_attr($key); ?>" autocomplete="off" spellcheck="false">
                    <ol class="optimizr-audit-steps">
                        <li><?php
                        echo wp_kses(sprintf(
                            /* translators: %s: link to the Google Cloud console */
                            __('Open %s and sign in with a Google account.', 'optimizr'),
                            '<a href="https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com" target="_blank" rel="noopener">' . esc_html__('PageSpeed Insights API in the Google Cloud console', 'optimizr') . '</a>'
                        ), ['a' => ['href' => [], 'target' => [], 'rel' => []]]);
                        ?></li>
                        <li><?php esc_html_e('Click "Enable" (create a project if asked).', 'optimizr'); ?></li>
                        <li><?php esc_html_e('In "Credentials", create an API key, then paste it here.', 'optimizr'); ?></li>
                    </ol>
                    <div><button type="submit" class="optimizr-btn optimizr-btn--secondary"><?php esc_html_e('Save', 'optimizr'); ?></button></div>
                </div>
            </form>

            <section class="optimizr-card">
                <div class="optimizr-card__header">
                    <div>
                        <h3 class="optimizr-card__title"><?php esc_html_e('History', 'optimizr'); ?></h3>
                        <?php /* translators: %d: number of audits kept */ ?>
                        <p class="optimizr-card__desc"><?php echo esc_html(sprintf(__('The last %d audits.', 'optimizr'), HAPTIC_AUDIT_HISTORY_MAX)); ?></p>
                    </div>
                </div>
                <div data-audit-history>
                    <?php echo wp_kses(haptic_audit_history_html(), haptic_audit_kses()); ?>
                </div>
            </section>
        </aside>
    </div>
    <?php
}
