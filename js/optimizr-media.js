/*
 * Panneau « Optimizr » de la médiathèque (functions/media-panel.php).
 * La ligne « Texte alternatif » suit la saisie du champ de WordPress, sans attendre l'enregistrement.
 * window.optimizrMediaPanel sert aux actions ajoutées par Optimizr Pro (message d'état, rechargement de la fiche).
 */
(function ($) {
    const { __ } = wp.i18n;
    const ALT_FIELDS = '#attachment-details-two-column-alt-text, #attachment-details-alt-text, #attachment_alt';

    function panelFor(element) {
        const $scope = $(element).closest('.attachment-details, .media-sidebar, #post');
        return ($scope.length ? $scope : $(document)).find('.optimizr-media-panel').first();
    }

    $(document).on('input change', ALT_FIELDS, function () {
        const $item = panelFor(this).find('[data-item="alt"]');
        if (!$item.length) {
            return;
        }
        const filled = $.trim($(this).val()) !== '';
        $item.toggleClass('is-ok', filled).toggleClass('is-warn', !filled);
        $item.find('[data-item-text]').text(filled
            ? __('Filled in.', 'optimizr')
            : __('Missing. It describes the image to search engines and screen readers.', 'optimizr'));
    });

    window.optimizrMediaPanel = {
        /** Message sous les boutons d'action (type : '', 'error'). */
        setStatus(element, message, type) {
            const $status = $(element).closest('.optimizr-media-panel').find('.optimizr-media-panel__status');
            $status.text(message || '').toggleClass('is-error', type === 'error').prop('hidden', !message);
        },
        /** Recharge la fiche du média : la fenêtre de la médiathèque réaffiche le panneau, l'écran d'édition se recharge. */
        refresh(attachmentId, element) {
            const inModal = $(element).closest('.media-modal, .media-frame').length > 0;
            if (inModal && window.wp && wp.media && wp.media.attachment) {
                wp.media.attachment(attachmentId).fetch();
            } else {
                window.location.reload();
            }
        },
    };
})(jQuery);
