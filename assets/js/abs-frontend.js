(function ($) {
    "use strict";

    $(function () {
        const config = window.absAdRotatorFrontend || {};
        if (!config.ajaxUrl) {
            return;
        }

        $('.abs-ad-block[data-abs-group-id][data-abs-interval][data-abs-rotation-type]').each(function () {
            const $block = $(this);
            const groupId = parseInt($block.attr('data-abs-group-id'), 10) || 0;
            const intervalSec = parseInt($block.attr('data-abs-interval'), 10) || 0;
            const rotationType = String($block.attr('data-abs-rotation-type') || '');

            if (!groupId || rotationType !== 'time' || intervalSec < 1) {
                return;
            }

            let loading = false;

            window.setInterval(function () {
                if (loading) {
                    return;
                }

                loading = true;

                $.ajax({
                    url: config.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'abs_ad_block_rotate',
                        group_id: groupId
                    }
                })
                    .done(function (response) {
                        if (!response || !response.success || !response.data || typeof response.data.html !== 'string') {
                            return;
                        }

                        $block.find('.abs-ad-inner').html(response.data.html);
                    })
                    .always(function () {
                        loading = false;
                    });
            }, intervalSec * 1000);
        });
    });
})(jQuery);
