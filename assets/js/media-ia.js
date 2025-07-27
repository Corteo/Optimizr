jQuery(document).ready(function ($) {
    console.log("media-ia.js chargé");
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if ($(mutation.addedNodes).find('.attachment-details').length) {
                let $metaPanel = $('.attachment-details');
                if (!$metaPanel.find('.hpt-ai-tools').length) {
                    $metaPanel.append(
                        '<div class="hpt-ai-tools" style="margin-top:10px;"><button class="button hpt-ai-alt" title="Générer ALT"><i class="fa fa-magic"></i> Générer avec IA</button></div>'
                    );
                }
            }
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

    $(document).on('click', '.hpt-ai-alt', function () {
        const attachmentId = $(this).data('post-id');
        const $button = $(this);
        $button.prop('disabled', true).text('Génération en cours...');

        $.post(ajaxurl, {
            action: 'haptic_GPT_generate_image_meta',
            post_id: attachmentId,
        }, function (response) {
            $button.prop('disabled', false).html('<i class="fa fa-magic"></i> Générer avec IA');

            if (response.success) {
                const data = response.data;
                // Remplacement de la sélection du champ ALT par l'ID spécifique de la colonne deux colonnes
                const $altField = $('#attachment-details-two-column-alt-text');

                if ($altField.length) {
                    $altField.val(data.alt).trigger('change');
                }

                alert('Balise ALT générée avec succès.');
            } else {
                alert('Erreur : ' + response.data);
            }
        });
    });
});
