(function ($) {
    "use strict";

    function getDeviceProfileKey(viewportWidth) {
        if (viewportWidth <= 767) {
            return 'phone';
        }

        if (viewportWidth <= 1024) {
            return 'tablet';
        }

        return 'computer';
    }

    function applyMobileProfiles($scope) {
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
        const profileKey = getDeviceProfileKey(viewportWidth);

        $scope.find('.abs-ad-img-wrap[data-abs-mobile-profiles]').each(function () {
            const $wrap = $(this);
            const $img = $wrap.find('img').first();
            const $block = $wrap.closest('.abs-ad-block');
            const rawProfiles = $wrap.attr('data-abs-mobile-profiles');

            if (!$img.length || !rawProfiles) {
                return;
            }

            let profiles;
            try {
                profiles = JSON.parse(rawProfiles);
            } catch (error) {
                return;
            }

            if ($wrap.data('absBaseStyle') === undefined) {
                $wrap.data('absBaseStyle', $wrap.attr('style') || '');
            }

            if ($img.data('absBaseStyle') === undefined) {
                $img.data('absBaseStyle', $img.attr('style') || '');
            }

            $wrap.attr('style', String($wrap.data('absBaseStyle') || ''));
            $img.attr('style', String($img.data('absBaseStyle') || ''));
            $block.show();

            if (!profiles || typeof profiles !== 'object') {
                return;
            }

            const profile = profiles[profileKey];
            if (!profile || typeof profile !== 'object') {
                return;
            }

            if (String(profile.enabled || '0') !== '1') {
                $block.hide();
                return;
            }

            if (profile.align) {
                $wrap.css('text-align', profile.align);
            }

            if (profile.padding) {
                $wrap.css('padding', profile.padding);
            }

            if (profile.w) {
                $img.css('width', profile.w);
            }

            if (profile.h) {
                $img.css('height', profile.h);
            }

            if (profile.max_w) {
                $img.css('max-width', profile.max_w);
            }

            if (profile.max_h) {
                $img.css('max-height', profile.max_h);
            }

            if (profile.fit) {
                $img.css('object-fit', profile.fit);
            }

            if (profile.radius) {
                $img.css('border-radius', profile.radius);
            }
        });
    }

    $(function () {
        const config = window.absAdRotatorFrontend || {};

        applyMobileProfiles($(document));

        let resizeTimer = null;
        $(window).on('resize', function () {
            if (resizeTimer) {
                window.clearTimeout(resizeTimer);
            }

            resizeTimer = window.setTimeout(function () {
                applyMobileProfiles($(document));
            }, 140);
        });

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
                        applyMobileProfiles($block);
                    })
                    .always(function () {
                        loading = false;
                    });
            }, intervalSec * 1000);
        });
    });
})(jQuery);
