<?php


function hpt_do_settings_sections($page)
{
    global $wp_settings_sections, $wp_settings_fields;

    if (!isset($wp_settings_sections[$page])) {
        return;
    }

    echo '<ul class="nav nav-tabs" role="tablist">';
    $i = 0;
    foreach ((array) $wp_settings_sections[$page] as $index => $section) {
        $i++;
        // Ensure section['id'] exists, fallback to sanitized title or index
        $section['id'] = isset($section['id']) ? $section['id'] : sanitize_title($section['title']);
        $active_class = $i == 1 ? 'active' : '';
        echo '<li class="nav-item">';
        echo '<a class="nav-link ' . $active_class . '" id="tab-' . esc_attr($section['id']) . '-tab" data-bs-toggle="tab" href="#tab-' . esc_attr($section['id']) . '" role="tab" aria-controls="tab-' . esc_attr($section['id']) . '" aria-selected="' . ($i == 1 ? 'true' : 'false') . '">' . esc_html($section['title']) . '</a>';
        echo '</li>';
    }
    echo '</ul>';

    echo '<div class="tab-content">';
    $i = 0;
    foreach ((array) $wp_settings_sections[$page] as $index => $section) {
        $i++;

        // Ensure section['id'] exists, fallback to sanitized title or index
        $section['id'] = isset($section['id']) ? $section['id'] : sanitize_title($section['title']);

        // Add fallback for before_section, section_class, and after_section
        $section['before_section'] = isset($section['before_section']) ? $section['before_section'] : '';
        $section['section_class'] = isset($section['section_class']) ? $section['section_class'] : '';
        $section['after_section'] = isset($section['after_section']) ? $section['after_section'] : '';

        $active_class = $i == 1 ? 'show active' : '';
        echo '<div class="tab-pane fade ' . $active_class . '" id="tab-' . esc_attr($section['id']) . '" role="tabpanel" aria-labelledby="tab-' . esc_attr($section['id']) . '-tab">';

        if ('' !== $section['before_section']) {
            if ('' !== $section['section_class']) {
                echo wp_kses_post(sprintf($section['before_section'], esc_attr($section['section_class'])));
            } else {
                echo wp_kses_post($section['before_section']);
            }
        }

        if ($section['callback']) {
            call_user_func($section['callback'], $section);
        }

        if (isset($wp_settings_fields[$page][$section['id']])) {
            echo '<div class="form-div" role="presentation">';
            hpt_do_settings_fields($page, $section['id']);
            echo '</div>';
        }

        if ('' !== $section['after_section']) {
            echo wp_kses_post($section['after_section']);
        }

        echo '</div>';
    }
    echo '</div>';
}

function hpt_do_settings_fields($page, $section)
{
    global $wp_settings_fields;

    if (!isset($wp_settings_fields[$page][$section])) {
        return;
    }

    foreach ((array) $wp_settings_fields[$page][$section] as $field) {
        $class = '';

        if (!empty($field['args']['class'])) {
            $class = ' class="' . esc_attr($field['args']['class']) . '"';
        }

        echo '<div class="form-group row"' . $class . '>';
        if (!empty($field['args']['label_for'])) {
            echo '<label for="' . esc_attr($field['args']['label_for']) . '" class="col-sm-2 col-form-label">' . $field['title'] . '</label>';
        } else {
            echo '<label class="col-sm-2 col-form-label">' . $field['title'] . '</label>';
        }
        echo '<div class="col-sm-10">';
        call_user_func($field['callback'], $field['args']);
        echo '</div>';
        echo '</div>';
    }
}
