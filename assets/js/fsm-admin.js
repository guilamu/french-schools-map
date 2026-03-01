/**
 * French Schools Map — Admin JS.
 *
 * @package French_Schools_Map
 */

(function ($) {
    'use strict';

    var $btn     = $('#fsm-sync-btn');
    var $spinner = $('#fsm-sync-spinner');
    var $msg     = $('#fsm-sync-message');

    $btn.on('click', function () {
        if (!confirm(fsmAdmin.i18n.confirmSync)) {
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text(fsmAdmin.i18n.syncing).removeClass('success error');

        $.ajax({
            url: fsmAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'fsm_sync',
                nonce: fsmAdmin.nonce,
            },
            timeout: 600000, // 10 minutes
            success: function (response) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);

                if (response.success) {
                    $msg.text(fsmAdmin.i18n.syncSuccess).addClass('success');
                    // Update status fields.
                    var s = response.data;
                    $('#fsm-record-count').text(
                        parseInt(s.record_count, 10).toLocaleString('fr-FR')
                    );
                    $('#fsm-sync-status').text('Succès');
                    $('#fsm-last-sync').text(new Date().toLocaleString('fr-FR'));
                } else {
                    $msg.text(fsmAdmin.i18n.syncError + ' ' + (response.data || '')).addClass('error');
                }
            },
            error: function (xhr, status) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                $msg.text(fsmAdmin.i18n.syncError + ' (' + status + ')').addClass('error');
            },
        });
    });
})(jQuery);
