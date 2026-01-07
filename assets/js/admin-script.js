jQuery(document).ready(function ($) {
    console.log('WP Image Format Converter: Admin scripts loaded.');

    // Manual conversion from Media Library
    $(document).on('click', '.wpifc-manual-convert', function (e) {
        e.preventDefault();
        const $link = $(this);
        const id = $link.data('id');
        const format = $link.data('format');

        $link.css('opacity', '0.5').text('Processing...');

        $.post(wpifc_vars.ajax_url, {
            action: 'wpifc_manual_convert',
            nonce: wpifc_vars.nonce,
            id: id,
            format: format
        }, function (response) {
            if (response.success) {
                $link.text('Success!').css('color', 'green');
                setTimeout(() => {
                    $link.css('opacity', '1').text('Convert again');
                }, 2000);
            } else {
                alert('Error: ' + response.data.message);
                $link.css('opacity', '1').text('Try again').css('color', 'red');
            }
        });
    });

    // Bulk Conversion
    $('#start-conversion').on('click', function () {
        const $btn = $(this);
        const $progressCont = $('#conversion-progress');
        const $fill = $('.wpifc-progress-fill');
        const $text = $('.wpifc-progress-text');

        $btn.prop('disabled', true).text('Initializing...');

        $.post(wpifc_vars.ajax_url, {
            action: 'wpifc_start_bulk',
            nonce: wpifc_vars.nonce
        }, function (response) {
            if (response.success) {
                $progressCont.fadeIn();
                startProgressPolling();
            } else {
                alert(response.data.message);
                $btn.prop('disabled', false).text('Start Mass Conversion');
            }
        });
    });

    function startProgressPolling() {
        const interval = setInterval(function () {
            $.post(wpifc_vars.ajax_url, {
                action: 'wpifc_get_progress',
                nonce: wpifc_vars.nonce
            }, function (response) {
                if (response.success) {
                    const data = response.data;
                    const percentage = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 0;

                    $('.wpifc-progress-fill').css('width', percentage + '%');
                    $('.wpifc-progress-text').text(`${percentage}% - ${data.processed} of ${data.total} processed (${data.failed} failed)`);

                    if (data.status === 'completed' || data.processed >= data.total) {
                        clearInterval(interval);
                        $('.wpifc-progress-text').text('Conversion Completed!');
                        $('#start-conversion').prop('disabled', false).text('Start Mass Conversion');
                    }
                }
            });
        }, 2000);
    }
});
