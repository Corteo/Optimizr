<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Assistant IA : tarifs OpenAI, estimation des coûts et suivi de la consommation réelle.
 *
 * Les estimations sont calculées à partir des données du site (taille de chaque image,
 * longueur de chaque contenu). La consommation réelle vient des réponses d'OpenAI
 * (champ « usage ») et est cumulée par mois.
 */

/** Date de relevé des tarifs ci-dessous. */
const HPT_AI_PRICING_DATE = '2026-09-30';
const HPT_AI_PRICING_SOURCE = 'https://developers.openai.com/api/docs/pricing';

/**
 * Modèles proposés, avec leurs tarifs standard en dollars US pour 1 million de tokens
 * et la méthode de décompte des images (documentation OpenAI « Images and vision ») :
 * - 'tile'  : tokens de base + tokens par tuile de 512 px, après mise à l'échelle ;
 * - 'patch' : carrés de 32 px (1 536 au maximum) multipliés par un coefficient propre au modèle.
 *
 * Tarifs modifiables par le filtre hpt_ai_pricing quand OpenAI les change.
 */
function hpt_ai_models()
{
    return apply_filters('hpt_ai_pricing', [
        'gpt-4.1-mini' => [
            'label'  => 'GPT-4.1 Mini — recommandé (bon rapport qualité/prix)',
            'input'  => 0.40,
            'output' => 1.60,
            'vision' => ['method' => 'patch', 'multiplier' => 1.62],
        ],
        'gpt-4.1-nano' => [
            'label'  => 'GPT-4.1 Nano — le plus économique',
            'input'  => 0.10,
            'output' => 0.40,
            'vision' => ['method' => 'patch', 'multiplier' => 2.46],
        ],
        'gpt-4.1' => [
            'label'  => 'GPT-4.1 — qualité maximale',
            'input'  => 2.00,
            'output' => 8.00,
            'vision' => ['method' => 'tile', 'base' => 85, 'tile' => 170],
        ],
        'gpt-4o' => [
            'label'  => 'GPT-4o',
            'input'  => 2.50,
            'output' => 10.00,
            'vision' => ['method' => 'tile', 'base' => 85, 'tile' => 170],
        ],
        'gpt-4o-mini' => [
            'label'  => 'GPT-4o Mini — économique en texte, cher en images',
            'input'  => 0.15,
            'output' => 0.60,
            'vision' => ['method' => 'tile', 'base' => 2833, 'tile' => 5667],
        ],
    ]);
}

/** Modèle par défaut pour les nouvelles installations. */
const HPT_AI_DEFAULT_MODEL = 'gpt-4.1-mini';

function hpt_ai_current_model()
{
    return (string) get_option('hpt_openai_model', HPT_AI_DEFAULT_MODEL);
}

/**
 * Modèle utilisé pour décrire les images : le modèle choisi s'il lit les images, sinon le modèle par défaut.
 */
function hpt_ai_vision_model()
{
    $model = hpt_ai_current_model();
    return isset(hpt_ai_models()[$model]) ? $model : HPT_AI_DEFAULT_MODEL;
}

/**
 * Coût en dollars d'un nombre de tokens, ou null si le tarif du modèle est inconnu.
 */
function hpt_ai_cost($model, $input_tokens, $output_tokens)
{
    $models = hpt_ai_models();
    if (!isset($models[$model])) {
        return null;
    }
    return ($input_tokens * $models[$model]['input'] + $output_tokens * $models[$model]['output']) / 1000000;
}

/**
 * Tokens facturés pour une image de largeur × hauteur donnée (détail « auto »).
 */
function hpt_ai_image_tokens($model, $width, $height)
{
    $models = hpt_ai_models();
    if (!isset($models[$model]) || $width <= 0 || $height <= 0) {
        return 0;
    }
    $vision = $models[$model]['vision'];

    if ($vision['method'] === 'patch') {
        $patches = ceil($width / 32) * ceil($height / 32);
        if ($patches > 1536) {
            // Réduction pour tenir dans 1 536 carrés de 32 px, puis ajustement de la largeur
            // sur un nombre entier de carrés (formule de la documentation OpenAI)
            $shrink = sqrt(32 * 32 * 1536 / ($width * $height));
            $width *= $shrink;
            $height *= $shrink;
            $adjust = floor($width / 32) / ($width / 32);
            $width *= $adjust;
            $height *= $adjust;
            // Arrondi à 6 décimales : évite qu'une imprécision de calcul ajoute une rangée de carrés
            $patches = min(1536, ceil(round($width / 32, 6)) * ceil(round($height / 32, 6)));
        }
        return (int) ceil($patches * $vision['multiplier']);
    }

    // Tuiles : l'image tient dans 2 048 × 2 048, puis son petit côté est ramené à 768 px
    $scale = min(1, 2048 / max($width, $height));
    $width *= $scale;
    $height *= $scale;
    if (min($width, $height) > 768) {
        $scale = 768 / min($width, $height);
        $width *= $scale;
        $height *= $scale;
    }
    $tiles = ceil($width / 512) * ceil($height / 512);

    return (int) ($vision['base'] + $tiles * $vision['tile']);
}

/** Tokens approximatifs d'un texte français (1 token ≈ 3,5 caractères, estimation prudente). */
function hpt_ai_text_tokens($text)
{
    return (int) ceil(mb_strlen((string) $text) / 3.5);
}

/**
 * Tokens estimés pour la balise ALT d'une image.
 *
 * @return array ['input' => int, 'output' => int]
 */
function hpt_ai_estimate_alt_tokens($attachment_id)
{
    $source = hpt_ai_alt_image_source($attachment_id);
    $prompt = 200 + hpt_ai_text_tokens(get_option('hpt_ai_context', ''));

    return [
        'input'  => $prompt + hpt_ai_image_tokens(hpt_ai_vision_model(), $source['width'], $source['height']),
        'output' => 40,
    ];
}

/**
 * Tokens estimés pour la meta description d'un contenu.
 *
 * @return array ['input' => int, 'output' => int]
 */
function hpt_ai_estimate_description_tokens($post_id)
{
    $content = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes(get_post_field('post_content', $post_id)))));
    $prompt = 120 + hpt_ai_text_tokens(get_the_title($post_id)) + hpt_ai_text_tokens(get_option('hpt_ai_context', ''));

    return [
        'input'  => $prompt + hpt_ai_text_tokens(mb_substr($content, 0, 6000)),
        'output' => 60,
    ];
}

/**
 * Estimation du coût d'une liste d'éléments, pour un modèle donné.
 * Au-delà de 300 éléments, la moyenne d'un échantillon est extrapolée.
 *
 * @param string   $task 'alt' ou 'descriptions'
 * @param int[]    $ids
 * @param string[] $models Modèles à chiffrer (par défaut : le modèle utilisé par la tâche)
 * @return array ['count' => int, 'per_model' => [modèle => ['total' => float|null, 'unit' => float|null]]]
 */
function hpt_ai_estimate($task, array $ids, array $models = [])
{
    $count = count($ids);
    $default = $task === 'alt' ? hpt_ai_vision_model() : hpt_ai_current_model();
    $models = $models ?: [$default];

    // Échantillon réparti sur toute la liste
    $sample = $count > 300 ? array_values(array_intersect_key($ids, array_flip(range(0, $count - 1, (int) ceil($count / 300))))) : $ids;

    $totals = array_fill_keys($models, 0.0);
    foreach ($sample as $id) {
        if ($task === 'alt') {
            $source = hpt_ai_alt_image_source($id);
            $prompt = 200 + hpt_ai_text_tokens(get_option('hpt_ai_context', ''));
            foreach ($models as $model) {
                $cost = hpt_ai_cost($model, $prompt + hpt_ai_image_tokens($model, $source['width'], $source['height']), 40);
                $totals[$model] = $cost === null ? null : $totals[$model] + $cost;
            }
        } else {
            $tokens = hpt_ai_estimate_description_tokens($id);
            foreach ($models as $model) {
                $cost = hpt_ai_cost($model, $tokens['input'], $tokens['output']);
                $totals[$model] = $cost === null ? null : $totals[$model] + $cost;
            }
        }
    }

    $result = ['count' => $count, 'per_model' => []];
    foreach ($models as $model) {
        if ($totals[$model] === null || !$sample) {
            $result['per_model'][$model] = ['total' => $totals[$model] === null ? null : 0.0, 'unit' => null];
            continue;
        }
        $unit = $totals[$model] / count($sample);
        $result['per_model'][$model] = ['total' => $unit * $count, 'unit' => $unit];
    }

    return $result;
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

/* ---------- Consommation réelle ---------- */

/**
 * Enregistre la consommation réelle d'une réponse OpenAI (cumul par mois, 12 derniers mois).
 *
 * @return float|null Coût de la requête en dollars
 */
function hpt_ai_record_usage($model, array $usage)
{
    $input = (int) ($usage['prompt_tokens'] ?? 0);
    $output = (int) ($usage['completion_tokens'] ?? 0);
    $cost = hpt_ai_cost($model, $input, $output);

    $month = current_time('Y-m');
    $all = (array) get_option('hpt_ai_usage', []);
    $entry = $all[$month] ?? ['requests' => 0, 'input' => 0, 'output' => 0, 'cost' => 0.0];

    $entry['requests']++;
    $entry['input'] += $input;
    $entry['output'] += $output;
    $entry['cost'] += (float) $cost;

    $all[$month] = $entry;
    krsort($all);
    update_option('hpt_ai_usage', array_slice($all, 0, 12, true), false);

    $GLOBALS['hpt_ai_last_cost'] = $cost;

    return $cost;
}

/** Coût de la dernière requête OpenAI de cette exécution PHP. */
function hpt_ai_last_cost()
{
    return $GLOBALS['hpt_ai_last_cost'] ?? null;
}

function hpt_ai_usage_this_month()
{
    $all = (array) get_option('hpt_ai_usage', []);
    return $all[current_time('Y-m')] ?? ['requests' => 0, 'input' => 0, 'output' => 0, 'cost' => 0.0];
}
