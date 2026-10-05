jQuery(document).ready(function ($) {
    // Le bouton est rendu côté PHP via le filtre attachment_fields_to_edit (functions/media.php)
    $(document).on('click', '.haptic-ai-alt', function () {
        const attachmentId = $(this).data('post-id');
        const $button = $(this);
        $button.prop('disabled', true).text('Génération en cours...');

        $.post(hapticMediaIA.ajaxUrl, {
            action: 'haptic_generate_image_meta',
            post_id: attachmentId,
            nonce: hapticMediaIA.nonce,
        }, function (response) {
            $button.prop('disabled', false).text('Générer avec l\'IA');

            if (response.success) {
                // Champ ALT : médiathèque (grille), modale média, puis écran d'édition du média
                const $altField = $('#attachment-details-two-column-alt-text, #attachment-details-alt-text, #attachment_alt').first();

                if ($altField.length) {
                    $altField.val(response.data.alt).trigger('change');
                }

                alert('Balise ALT générée avec succès.');
            } else {
                alert('Erreur : ' + response.data);
            }
        }).fail(function () {
            $button.prop('disabled', false).text('Générer avec l\'IA');
            alert('La requête a échoué.');
        });
    });
});
