<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Affiche les sections d'une page Settings API sous forme de cartes.
 *
 * Arguments supplémentaires reconnus dans add_settings_section($id, $title, $cb, $page, $args) :
 * - 'toggle'       => nom d'une option booléenne, affichée en interrupteur dans l'en-tête de la carte
 * - 'toggle_label' => libellé accessible de cet interrupteur
 */
function haptic_do_settings_sections($page)
{
    global $wp_settings_sections, $wp_settings_fields;

    if (empty($wp_settings_sections[$page])) {
        echo '<p class="optimizr-empty">' . esc_html__('No settings in this tab.', 'optimizr') . '</p>';
        return;
    }

    foreach ((array) $wp_settings_sections[$page] as $section) {
        $has_fields = !empty($wp_settings_fields[$page][$section['id']]);

        ob_start();
        if (!empty($section['callback'])) {
            call_user_func($section['callback'], $section);
        }
        $description = trim(ob_get_clean());

        echo '<section class="optimizr-card" id="' . esc_attr('section-' . $section['id']) . '">';
        echo '<div class="optimizr-card__header">';
        echo '<div>';
        if (!empty($section['title'])) {
            echo '<h2 class="optimizr-card__title">' . esc_html($section['title']) . '</h2>';
        }
        if ($description !== '') {
            echo '<div class="optimizr-card__desc">' . wp_kses_post($description) . '</div>';
        }
        echo '</div>';

        if (!empty($section['toggle'])) {
            $label = !empty($section['toggle_label']) ? $section['toggle_label'] : $section['title'];
            echo wp_kses(haptic_switch_input($section['toggle'], get_option($section['toggle']), $label), haptic_kses_ui());
        }
        echo '</div>';

        if ($has_fields) {
            echo '<div class="optimizr-card__body">';
            haptic_do_settings_fields($page, $section['id']);
            echo '</div>';
        }

        echo '</section>';
    }
}

/**
 * Affiche les champs d'une section.
 *
 * Arguments supplémentaires reconnus dans add_settings_field(..., $args) :
 * - 'description' => aide affichée sous le libellé
 * - 'layout'      => 'stacked' pour placer le contrôle sous le libellé (champs larges)
 */
function haptic_do_settings_fields($page, $section)
{
    global $wp_settings_fields;

    if (!isset($wp_settings_fields[$page][$section])) {
        return;
    }

    foreach ((array) $wp_settings_fields[$page][$section] as $field) {
        $args = (array) $field['args'];

        $classes = ['optimizr-field'];
        if (!empty($args['layout']) && $args['layout'] === 'stacked') {
            $classes[] = 'optimizr-field--stacked';
        }
        if (!empty($args['class'])) {
            $classes[] = $args['class'];
        }

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';

        $title = trim(wp_strip_all_tags($field['title']));
        if ($title !== '' || !empty($args['description'])) {
            echo '<div class="optimizr-field__label">';
            if ($title !== '') {
                if (!empty($args['label_for'])) {
                    echo '<label for="' . esc_attr($args['label_for']) . '">' . esc_html($title) . '</label>';
                } else {
                    echo '<span>' . esc_html($title) . '</span>';
                }
            }
            if (!empty($args['description'])) {
                echo '<p class="optimizr-field__help">' . esc_html($args['description']) . '</p>';
            }
            echo '</div>';
        }

        echo '<div class="optimizr-field__control">';
        call_user_func($field['callback'], $args);
        echo '</div>';
        echo '</div>';
    }
}
