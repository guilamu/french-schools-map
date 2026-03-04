/**
 * French Schools Map — Admin JS.
 *
 * @package French_Schools_Map
 */

(function ($) {
    'use strict';

    var $btn = $('#fsm-sync-btn');
    var $spinner = $('#fsm-sync-spinner');
    var $msg = $('#fsm-sync-message');

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

    // ── Shortcode builder ────────────────────────────────────────────
    var $preview = $('#fsm-builder-preview');
    if ($preview.length) {
        var defaults = {
            departement: 'all',
            academie: 'all',
            statut: 'all',
            education_prioritaire: 'all',
            height: '600px',
            show_filters: 'true',
            show_search: 'true',
            cluster: 'false',
            show_circo_zones: 'true',
            show_transport: 'false',
            types: 'all',
        };

        function buildShortcode() {
            var attrs = [];

            var dept = $('#fsm-builder-dept').val();
            var acad = $('#fsm-builder-acad').val();
            if (dept !== 'all') attrs.push('departement="' + dept + '"');
            if (acad !== 'all') attrs.push('academie="' + acad + '"');

            // Types
            var allTypes = ['Ecole', 'Collège', 'Lycée'];
            var checked = [];
            $('.fsm-builder-type').each(function () {
                if (this.checked) checked.push(this.value);
            });
            if (checked.length > 0 && checked.length < allTypes.length) {
                // Map internal names to user-friendly labels
                var labels = { 'Ecole': 'Écoles', 'Collège': 'Collège', 'Lycée': 'Lycée' };
                var typeLabels = checked.map(function (v) { return labels[v] || v; });
                attrs.push('types="' + typeLabels.join(',') + '"');
            }

            var statut = $('#fsm-builder-statut').val();
            if (statut !== 'all') attrs.push('statut="' + statut + '"');

            var ep = $('#fsm-builder-ep').val();
            if (ep !== 'all') attrs.push('education_prioritaire="' + ep + '"');

            var height = $('#fsm-builder-height').val().trim();
            if (height && height !== defaults.height) attrs.push('height="' + height + '"');

            if (!$('#fsm-builder-filters').is(':checked')) attrs.push('show_filters="false"');
            if (!$('#fsm-builder-search').is(':checked')) attrs.push('show_search="false"');
            if ($('#fsm-builder-cluster').is(':checked')) attrs.push('cluster="true"');
            if (!$('#fsm-builder-circo').is(':checked')) attrs.push('show_circo_zones="false"');
            if ($('#fsm-builder-transport').is(':checked')) attrs.push('show_transport="true"');

            var sc = '[french_schools_map' + (attrs.length ? ' ' + attrs.join(' ') : '') + ']';
            $preview.text(sc);
        }

        // Mutual exclusion: dept ↔ acad.
        $('#fsm-builder-dept').on('change', function () {
            if (this.value !== 'all') $('#fsm-builder-acad').val('all');
            buildShortcode();
        });
        $('#fsm-builder-acad').on('change', function () {
            if (this.value !== 'all') $('#fsm-builder-dept').val('all');
            buildShortcode();
        });

        // All other inputs.
        $('#fsm-builder-statut, #fsm-builder-ep, #fsm-builder-height').on('change input', buildShortcode);
        $('#fsm-builder-filters, #fsm-builder-search, #fsm-builder-cluster, #fsm-builder-circo, #fsm-builder-transport').on('change', buildShortcode);
        $('.fsm-builder-type').on('change', buildShortcode);

        // Copy button.
        $('#fsm-builder-copy').on('click', function () {
            var text = $preview.text();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    $('#fsm-builder-copy').text('✓').delay(1500).queue(function (next) {
                        $(this).text(fsmAdmin.i18n.copy || 'Copy');
                        next();
                    });
                });
            } else {
                // Fallback for older browsers.
                var $tmp = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy');
                $tmp.remove();
                $('#fsm-builder-copy').text('✓').delay(1500).queue(function (next) {
                    $(this).text(fsmAdmin.i18n.copy || 'Copy');
                    next();
                });
            }
        });

        buildShortcode(); // initial render.
    }
})(jQuery);
