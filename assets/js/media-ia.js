jQuery(document).ready(function ($) {
    const { __, sprintf } = wp.i18n;

    // Le bouton est rendu côté PHP via le filtre attachment_fields_to_edit (functions/media.php)
    $(document).on('click', '.haptic-ai-alt', function () {
        const attachmentId = $(this).data('post-id');
        const $button = $(this);
        $button.prop('disabled', true).text(__('Generating…', 'optimizr'));

        $.post(hapticMediaIA.ajaxUrl, {
            action: 'haptic_generate_image_meta',
            post_id: attachmentId,
            nonce: hapticMediaIA.nonce,
        }, function (response) {
            $button.prop('disabled', false).text(__('Generate with AI', 'optimizr'));

            if (response.success) {
                // Champ ALT : médiathèque (grille), modale média, puis écran d'édition du média
                const $altField = $('#attachment-details-two-column-alt-text, #attachment-details-alt-text, #attachment_alt').first();

                if ($altField.length) {
                    $altField.val(response.data.alt).trigger('change');
                }

                alert(__('ALT text generated successfully.', 'optimizr'));
            } else {
                /* translators: %s: error message */
                alert(sprintf(__('Error: %s', 'optimizr'), response.data));
            }
        }).fail(function () {
            $button.prop('disabled', false).text(__('Generate with AI', 'optimizr'));
            alert(__('The request failed.', 'optimizr'));
        });
    });
});
