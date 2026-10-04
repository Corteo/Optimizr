<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Assistant IA : fournisseurs, modèles, tarifs, estimation des coûts et consommation réelle.
 *
 * Les estimations sont calculées à partir des données du site (taille de chaque image,
 * longueur de chaque contenu). La consommation réelle vient des réponses des API
 * et est cumulée par mois.
 */

/** Date de relevé des tarifs ci-dessous. */
const HPT_AI_PRICING_DATE = '2026-10-01';

const HPT_AI_DEFAULT_PROVIDER = 'openai';

/**
 * Fournisseurs et modèles proposés.
 *
 * Tarifs : dollars US pour 1 million de tokens (entrée, sortie), grilles officielles relevées à HPT_AI_PRICING_DATE.
 * 'price_changes' : nouveaux tarifs déjà annoncés, appliqués automatiquement à leur date.
 * 'vision' : méthode de décompte des tokens d'une image (voir hpt_ai_image_tokens()).
 * 'hidden_output' : tokens de réflexion facturés en sortie, estimés pour une tâche courte.
 * 'options' : réglages propres au modèle, appliqués par les adaptateurs (functions/ai-providers.php).
 *
 * Modifiable par le filtre hpt_ai_providers quand les fournisseurs changent leurs prix ou leurs modèles.
 */
function hpt_ai_providers()
{
    return apply_filters('hpt_ai_providers', [
        'openai' => [
            'label'        => 'OpenAI',
            'key_option'   => 'hpt_openai_api_key',
            'model_option' => 'hpt_openai_model',
            'default'      => 'gpt-4.1-mini',
            'key_url'      => 'https://platform.openai.com/api-keys',
            'usage_url'    => 'https://platform.openai.com/usage',
            'pricing_url'  => 'https://developers.openai.com/api/docs/pricing',
            'models'       => [
                'gpt-5.6-luna' => [
                    'label' => 'GPT-5.6 Luna — récent et économique',
                    'input' => 0.20, 'output' => 1.20,
                    // Détail « auto » : image envoyée sans réduction (taille « large » du site)
                    'vision' => ['method' => 'patch', 'multiplier' => 1.2, 'max_patches' => 0],
                    'options' => ['reasoning_effort' => 'none'],
                ],
                'gpt-4.1-mini' => [
                    'label' => 'GPT-4.1 Mini — réponses stables et prévisibles',
                    'input' => 0.40, 'output' => 1.60,
                    'vision' => ['method' => 'patch', 'multiplier' => 1.62],
                ],
                'gpt-4.1-nano' => [
                    'label' => 'GPT-4.1 Nano',
                    'input' => 0.10, 'output' => 0.40,
                    'vision' => ['method' => 'patch', 'multiplier' => 2.46],
                ],
                'gpt-4.1' => [
                    'label' => 'GPT-4.1 — qualité maximale',
                    'input' => 2.00, 'output' => 8.00,
                    'vision' => ['method' => 'tile', 'base' => 85, 'tile' => 170],
                ],
                'gpt-4o' => [
                    'label' => 'GPT-4o',
                    'input' => 2.50, 'output' => 10.00,
                    'vision' => ['method' => 'tile', 'base' => 85, 'tile' => 170],
                ],
                'gpt-4o-mini' => [
                    'label' => 'GPT-4o Mini — économique en texte, cher en images',
                    'input' => 0.15, 'output' => 0.60,
                    'vision' => ['method' => 'tile', 'base' => 2833, 'tile' => 5667],
                ],
                'gpt-5.6-terra' => [
                    'label' => 'GPT-5.6 Terra',
                    'input' => 2.00, 'output' => 12.00,
                    'vision' => ['method' => 'patch', 'multiplier' => 1.2, 'max_patches' => 0],
                    'options' => ['reasoning_effort' => 'none'],
                ],
                'gpt-5.6-sol' => [
                    'label' => 'GPT-5.6 Sol — le plus capable',
                    'input' => 4.00, 'output' => 20.00,
                    'vision' => ['method' => 'patch', 'multiplier' => 1.2, 'max_patches' => 0],
                    'options' => ['reasoning_effort' => 'none'],
                ],
            ],
        ],
        'anthropic' => [
            'label'        => 'Anthropic (Claude)',
            'key_option'   => 'hpt_anthropic_api_key',
            'model_option' => 'hpt_anthropic_model',
            'default'      => 'claude-haiku-4-5',
            'key_url'      => 'https://platform.claude.com/settings/keys',
            'usage_url'    => 'https://platform.claude.com/usage',
            'pricing_url'  => 'https://platform.claude.com/docs/en/about-claude/pricing',
            'models'       => [
                'claude-haiku-4-5' => [
                    'label' => 'Claude Haiku 4.5 — rapide et économique',
                    'input' => 1.00, 'output' => 5.00,
                    'vision' => ['method' => 'claude', 'max_edge' => 1568, 'max_tokens' => 1568],
                ],
                'claude-sonnet-5-5' => [
                    'label' => 'Claude Sonnet 5.5 — meilleure rédaction',
                    'input' => 2.00, 'output' => 10.00,
                    'vision' => ['method' => 'claude', 'max_edge' => 2576, 'max_tokens' => 4784],
                    'options' => ['effort' => 'low', 'thinking' => 'between_tools', 'fallbacks' => true],
                ],
                'claude-opus-5-5' => [
                    'label' => 'Claude Opus 5.5 — le plus capable',
                    'input' => 4.00, 'output' => 20.00,
                    'vision' => ['method' => 'claude', 'max_edge' => 2576, 'max_tokens' => 4784],
                    // Réflexion toujours active sur ce modèle : l'effort « low » la limite
                    'hidden_output' => 200,
                    'options' => ['effort' => 'low', 'fallbacks' => true],
                ],
            ],
        ],
        'gemini' => [
            'label'        => 'Google (Gemini)',
            'key_option'   => 'hpt_gemini_api_key',
            'model_option' => 'hpt_gemini_model',
            'default'      => 'gemini-3.5-flash-lite',
            'key_url'      => 'https://aistudio.google.com/apikey',
            'usage_url'    => 'https://aistudio.google.com/usage',
            'pricing_url'  => 'https://ai.google.dev/gemini-api/docs/pricing',
            'models'       => [
                'gemini-3.5-flash-lite' => [
                    'label' => 'Gemini 3.5 Flash-Lite — recommandé par Google, économique',
                    'input' => 0.30, 'output' => 2.50,
                    'vision' => ['method' => 'fixed', 'tokens' => 1120],
                    'hidden_output' => 50,
                ],
                'gemini-3.1-flash-lite' => [
                    'label' => 'Gemini 3.1 Flash-Lite — arrêt prévu le 7 mai 2027',
                    'input' => 0.25, 'output' => 1.50,
                    'vision' => ['method' => 'fixed', 'tokens' => 1120],
                    'hidden_output' => 50,
                ],
                'gemini-3.8-flash' => [
                    'label' => 'Gemini 3.8 Flash — le plus capable (tarif doublé au 1er janvier 2027)',
                    'input' => 0.75, 'output' => 3.75,
                    'price_changes' => ['2027-01-01' => ['input' => 1.50, 'output' => 7.50]],
                    'vision' => ['method' => 'fixed', 'tokens' => 1120],
                    // Réflexion « moyenne » par défaut sur ce modèle
                    'hidden_output' => 400,
                ],
            ],
        ],
    ]);
}

function hpt_ai_provider()
{
    $provider = (string) get_option('hpt_ai_provider', HPT_AI_DEFAULT_PROVIDER);
    return isset(hpt_ai_providers()[$provider]) ? $provider : HPT_AI_DEFAULT_PROVIDER;
}

function hpt_ai_provider_config($provider = null)
{
    return hpt_ai_providers()[$provider ?: hpt_ai_provider()];
}

/** Clé API du fournisseur actif (ou de celui indiqué). */
function hpt_ai_api_key($provider = null)
{
    return trim((string) get_option(hpt_ai_provider_config($provider)['key_option'], ''));
}

/** Modèles du fournisseur actif (ou de celui indiqué). */
function hpt_ai_models($provider = null)
{
    return hpt_ai_provider_config($provider)['models'];
}

/** Modèle choisi pour un fournisseur ; un ancien modèle enregistré est conservé tel quel. */
function hpt_ai_current_model($provider = null)
{
    $config = hpt_ai_provider_config($provider);
    return (string) get_option($config['model_option'], $config['default']);
}

/**
 * Modèle réellement utilisé pour les requêtes : le modèle choisi s'il est encore proposé,
 * sinon le modèle par défaut du fournisseur (un modèle retiré provoquerait une erreur de l'API).
 */
function hpt_ai_request_model($provider = null)
{
    $model = hpt_ai_current_model($provider);
    return isset(hpt_ai_models($provider)[$model]) ? $model : hpt_ai_provider_config($provider)['default'];
}

/** Modèle utilisé pour décrire les images (tous les modèles proposés lisent les images). */
function hpt_ai_vision_model($provider = null)
{
    return hpt_ai_request_model($provider);
}

/** Réglages d'un modèle, ou null s'il n'est pas dans la liste. */
function hpt_ai_model_config($model, $provider = null)
{
    return hpt_ai_models($provider)[$model] ?? null;
}

/**
 * Tarif en vigueur aujourd'hui (changements de prix annoncés compris).
 *
 * @return array ['input' => float, 'output' => float]
 */
function hpt_ai_model_price(array $config)
{
    $price = ['input' => $config['input'], 'output' => $config['output']];
    $today = current_time('Y-m-d');

    foreach ((array) ($config['price_changes'] ?? []) as $date => $change) {
        if ($today >= $date) {
            $price = $change;
        }
    }

    return $price;
}

/**
 * Coût en dollars d'un nombre de tokens, ou null si le tarif du modèle est inconnu.
 */
function hpt_ai_cost($model, $input_tokens, $output_tokens, $provider = null)
{
    $config = hpt_ai_model_config($model, $provider);
    if (!$config) {
        return null;
    }
    $price = hpt_ai_model_price($config);
    return ($input_tokens * $price['input'] + $output_tokens * $price['output']) / 1000000;
}

/**
 * Tokens facturés pour une image de largeur × hauteur donnée, selon la documentation de chaque fournisseur :
 * - tile     (OpenAI) : tokens de base + tuiles de 512 px, après mise à l'échelle ;
 * - patch    (OpenAI) : carrés de 32 px (1 536 au maximum, sans plafond pour GPT-5.6) × coefficient du modèle ;
 * - claude   (Anthropic) : carrés de 28 px, image réduite sous la limite de bord et de tokens du modèle ;
 * - gemini25 (Google) : 258 tokens par tuile de 768 px (258 au total sous 384 px) ;
 * - fixed    (Google Gemini 3.x) : forfait par image (résolution par défaut).
 */
function hpt_ai_image_tokens($model, $width, $height, $provider = null)
{
    $config = hpt_ai_model_config($model, $provider);
    if (!$config || $width <= 0 || $height <= 0) {
        return 0;
    }
    $vision = $config['vision'];

    switch ($vision['method']) {
        case 'fixed':
            return (int) $vision['tokens'];

        case 'gemini25':
            if ($width <= 384 && $height <= 384) {
                return 258;
            }
            return (int) (258 * ceil($width / 768) * ceil($height / 768));

        case 'claude':
            // Réduction proportionnelle sous le bord maximal, puis sous le plafond de carrés de 28 px
            $scale = min(1, $vision['max_edge'] / max($width, $height));
            $width *= $scale;
            $height *= $scale;
            $tokens = ceil($width / 28) * ceil($height / 28);
            if ($tokens > $vision['max_tokens']) {
                // Plus grande échelle (pixels entiers) qui tient sous le plafond, trouvée par dichotomie
                $low = 0.0;
                $high = 1.0;
                for ($i = 0; $i < 30; $i++) {
                    $mid = ($low + $high) / 2;
                    $fits = ceil(floor($width * $mid) / 28) * ceil(floor($height * $mid) / 28) <= $vision['max_tokens'];
                    if ($fits) {
                        $low = $mid;
                    } else {
                        $high = $mid;
                    }
                }
                $tokens = ceil(floor($width * $low) / 28) * ceil(floor($height * $low) / 28);
            }
            return (int) $tokens;

        case 'patch':
            $patches = ceil($width / 32) * ceil($height / 32);
            $budget = $vision['max_patches'] ?? 1536;
            if ($budget && $patches > $budget) {
                // Réduction pour tenir dans 1 536 carrés de 32 px, puis ajustement de la largeur
                // sur un nombre entier de carrés (formule de la documentation OpenAI)
                $shrink = sqrt(32 * 32 * $budget / ($width * $height));
                $width *= $shrink;
                $height *= $shrink;
                $adjust = floor($width / 32) / ($width / 32);
                $width *= $adjust;
                $height *= $adjust;
                // Arrondi à 6 décimales : évite qu'une imprécision de calcul ajoute une rangée de carrés
                $patches = min($budget, ceil(round($width / 32, 6)) * ceil(round($height / 32, 6)));
            }
            return (int) ceil($patches * $vision['multiplier']);

        case 'tile':
        default:
            // Tuiles : l'image tient dans 2 048 × 2 048, puis son petit côté est ramené à 768 px
            $scale = min(1, 2048 / max($width, $height));
            $width *= $scale;
            $height *= $scale;
            if (min($width, $height) > 768) {
                $scale = 768 / min($width, $height);
                $width *= $scale;
                $height *= $scale;
            }
            return (int) ($vision['base'] + ceil($width / 512) * ceil($height / 512) * $vision['tile']);
    }
}

/** Tokens approximatifs d'un texte français (1 token ≈ 3,5 caractères, estimation prudente). */
function hpt_ai_text_tokens($text)
{
    return (int) ceil(mb_strlen((string) $text) / 3.5);
}

/** Tokens de sortie estimés : texte attendu + réflexion éventuelle du modèle. */
function hpt_ai_estimated_output($model, $visible, $provider = null)
{
    $config = hpt_ai_model_config($model, $provider);
    return $visible + (int) ($config['hidden_output'] ?? 0);
}

/**
 * Tokens estimés pour la meta description d'un contenu.
 *
 * @return array ['input' => int, 'output' => int]
 */
function hpt_ai_estimate_description_tokens($post_id, $model = null, $provider = null)
{
    $content = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes(get_post_field('post_content', $post_id)))));
    $prompt = 120 + hpt_ai_text_tokens(get_the_title($post_id)) + hpt_ai_text_tokens(get_option('hpt_ai_context', ''));

    return [
        'input'  => $prompt + hpt_ai_text_tokens(mb_substr($content, 0, 6000)),
        'output' => hpt_ai_estimated_output($model ?: hpt_ai_request_model($provider), 60, $provider),
    ];
}

/**
 * Tokens estimés pour la balise ALT d'une image.
 *
 * @return array ['input' => int, 'output' => int]
 */
function hpt_ai_estimate_alt_tokens($attachment_id, $model = null, $provider = null)
{
    $model = $model ?: hpt_ai_vision_model($provider);
    $source = hpt_ai_alt_image_source($attachment_id);
    $prompt = 200 + hpt_ai_text_tokens(get_option('hpt_ai_context', ''));

    return [
        'input'  => $prompt + hpt_ai_image_tokens($model, $source['width'], $source['height'], $provider),
        'output' => hpt_ai_estimated_output($model, 40, $provider),
    ];
}

/**
 * Estimation du coût d'une liste d'éléments, pour un ou plusieurs modèles.
 * Au-delà de 300 éléments, la moyenne d'un échantillon est extrapolée.
 *
 * @param string   $task    'alt' ou 'descriptions'
 * @param int[]    $ids
 * @param array    $targets Liste de [fournisseur, modèle] ; par défaut le modèle utilisé par la tâche
 * @return array ['count' => int, 'per_model' => ['fournisseur:modèle' => ['total' => float|null, 'unit' => float|null]]]
 */
function hpt_ai_estimate($task, array $ids, array $targets = [])
{
    $count = count($ids);
    if (!$targets) {
        $provider = hpt_ai_provider();
        $targets = [[$provider, $task === 'alt' ? hpt_ai_vision_model() : hpt_ai_request_model()]];
    }

    // Échantillon réparti sur toute la liste
    $sample = $count > 300 ? array_values(array_intersect_key($ids, array_flip(range(0, $count - 1, (int) ceil($count / 300))))) : $ids;

    $result = ['count' => $count, 'per_model' => []];
    foreach ($targets as list($provider, $model)) {
        $total = 0.0;
        foreach ($sample as $id) {
            $tokens = $task === 'alt'
                ? hpt_ai_estimate_alt_tokens($id, $model, $provider)
                : hpt_ai_estimate_description_tokens($id, $model, $provider);
            $cost = hpt_ai_cost($model, $tokens['input'], $tokens['output'], $provider);
            if ($cost === null) {
                $total = null;
                break;
            }
            $total += $cost;
        }

        $unit = ($total !== null && $sample) ? $total / count($sample) : null;
        $result['per_model'][$provider . ':' . $model] = [
            'total' => $unit === null ? null : $unit * $count,
            'unit'  => $unit,
        ];
    }

    return $result;
}

/**
 * Coût estimé d'un élément pour chacun des modèles de tous les fournisseurs, mis en cache 12 heures :
 * le calcul parcourt jusqu'à 300 éléments pour chaque modèle, à chaque affichage de l'onglet Assistant IA.
 * Recalculé dès que la liste des éléments, les modèles, les tarifs, le contexte du site ou la version du plugin changent.
 *
 * @param string $task 'alt' ou 'descriptions'
 * @param int[]  $ids
 * @return array 'fournisseur:modèle' => float|null
 */
function hpt_ai_estimate_units($task, array $ids)
{
    $targets = [];
    foreach (hpt_ai_providers() as $slug => $provider) {
        foreach (array_keys($provider['models']) as $model) {
            $targets[] = [$slug, $model];
        }
    }

    $transient = 'hpt_ai_estimate_' . $task;
    $hash = md5(wp_json_encode([$ids, hpt_ai_providers(), get_option('hpt_ai_context', ''), current_time('Y-m-d'), HPT_OPTIMIZR_VERSION]));
    $cache = get_transient($transient);
    if (is_array($cache) && ($cache['hash'] ?? '') === $hash) {
        return (array) $cache['units'];
    }

    $units = array_map(function ($estimate) {
        return $estimate['unit'];
    }, hpt_ai_estimate($task, $ids, $targets)['per_model']);
    set_transient($transient, ['hash' => $hash, 'units' => $units], 12 * HOUR_IN_SECONDS);

    return $units;
}

/** Estimation pour le modèle actuellement utilisé par une tâche. */
function hpt_ai_estimate_current($task, array $ids)
{
    $key = hpt_ai_provider() . ':' . ($task === 'alt' ? hpt_ai_vision_model() : hpt_ai_request_model());
    $units = hpt_ai_estimate_units($task, $ids);

    // Modèle absent du comparatif (filtre hpt_ai_providers) : calcul direct
    if (!array_key_exists($key, $units)) {
        $estimate = hpt_ai_estimate($task, $ids);
        return reset($estimate['per_model']);
    }

    return [
        'total' => $units[$key] === null ? null : $units[$key] * count($ids),
        'unit'  => $units[$key],
    ];
}

/** Montant en dollars lisible : « 0,42 $ », « < 0,01 $ ». */
function hpt_ai_format_cost($amount)
{
    if ($amount === null) {
        return 'tarif inconnu';
    }
    if ($amount > 0 && $amount < 0.01) {
        return '< 0,01 $';
    }
    return number_format_i18n($amount, $amount < 10 ? 2 : 0) . ' $';
}

/** Coût unitaire lisible, avec plus de décimales : « 0,0004 $ ». */
function hpt_ai_format_unit($amount)
{
    if ($amount === null) {
        return 'tarif inconnu';
    }
    return number_format_i18n($amount, $amount < 0.01 ? 4 : 3) . ' $';
}

/* ---------- Consommation réelle ---------- */

/**
 * Enregistre la consommation réelle d'une requête (cumul par mois, 12 derniers mois).
 *
 * @return float|null Coût de la requête en dollars
 */
function hpt_ai_record_usage($provider, $model, $input, $output)
{
    $cost = hpt_ai_cost($model, (int) $input, (int) $output, $provider);

    $month = current_time('Y-m');
    $all = (array) get_option('hpt_ai_usage', []);
    $entry = $all[$month] ?? ['requests' => 0, 'input' => 0, 'output' => 0, 'cost' => 0.0];

    $entry['requests']++;
    $entry['input'] += (int) $input;
    $entry['output'] += (int) $output;
    $entry['cost'] += (float) $cost;

    $all[$month] = $entry;
    krsort($all);
    update_option('hpt_ai_usage', array_slice($all, 0, 12, true), false);

    $GLOBALS['hpt_ai_last_cost'] = $cost;

    return $cost;
}

/** Coût de la dernière requête de cette exécution PHP. */
function hpt_ai_last_cost()
{
    return $GLOBALS['hpt_ai_last_cost'] ?? null;
}

function hpt_ai_usage_this_month()
{
    $all = (array) get_option('hpt_ai_usage', []);
    return $all[current_time('Y-m')] ?? ['requests' => 0, 'input' => 0, 'output' => 0, 'cost' => 0.0];
}
