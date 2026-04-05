(function ($) {
    "use strict";

    function getProfileEnabledValue(profile) {
        if (!profile) {
            return '0';
        }

        if (typeof profile === 'object') {
            return String(profile.enabled || '0') === '1' ? '1' : '0';
        }

        return String(profile) === '1' ? '1' : '0';
    }

    function syncBlockDeviceVisibility($block, profiles) {
        if (!$block.length) {
            return;
        }

        if (!profiles || typeof profiles !== 'object') {
            $block.removeAttr('data-abs-device-visibility');
            $block.removeAttr('data-abs-visible-phone');
            $block.removeAttr('data-abs-visible-tablet');
            $block.removeAttr('data-abs-visible-computer');
            return;
        }

        $block.attr('data-abs-device-visibility', '1');
        $block.attr('data-abs-visible-phone', getProfileEnabledValue(profiles.phone));
        $block.attr('data-abs-visible-tablet', getProfileEnabledValue(profiles.tablet));
        $block.attr('data-abs-visible-computer', getProfileEnabledValue(profiles.computer));
    }

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
            const blockElement = $block.get(0);
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

            if ($block.data('absBaseDisplay') === undefined) {
                $block.data('absBaseDisplay', blockElement && blockElement.style && typeof blockElement.style.display === 'string' ? blockElement.style.display : '');
            }

            $wrap.attr('style', String($wrap.data('absBaseStyle') || ''));
            $img.attr('style', String($img.data('absBaseStyle') || ''));
            if (blockElement && blockElement.style) {
                blockElement.style.display = String($block.data('absBaseDisplay') || '');
            }
            syncBlockDeviceVisibility($block, profiles);

            if (!profiles || typeof profiles !== 'object') {
                return;
            }

            const profile = profiles[profileKey];
            if (!profile || typeof profile !== 'object') {
                return;
            }

            if (String(profile.enabled || '0') !== '1') {
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

    function trackAdClick(config, itemId) {
        itemId = parseInt(itemId, 10) || 0;
        if (!itemId || !config.ajaxUrl) {
            return;
        }

        if (window.navigator && typeof window.navigator.sendBeacon === 'function' && typeof window.FormData === 'function') {
            const beaconData = new window.FormData();
            beaconData.append('action', 'abs_track_ad_click');
            beaconData.append('item_id', String(itemId));

            if (window.navigator.sendBeacon(config.ajaxUrl, beaconData)) {
                return;
            }
        }

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'abs_track_ad_click',
                item_id: itemId
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

        $(document).on('click', '.abs-ad-block a', function () {
            const $link = $(this);

            if (String($link.attr('data-abs-click-mode') || '') === 'redirect') {
                return;
            }

            const $block = $link.closest('.abs-ad-block[data-abs-item-id]');
            const itemId = parseInt($block.attr('data-abs-item-id'), 10) || parseInt($link.attr('data-abs-item-id'), 10) || 0;

            if (!itemId) {
                return;
            }

            trackAdClick(config, itemId);
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
                        if (response.data.item_id !== undefined) {
                            const itemId = parseInt(response.data.item_id, 10) || 0;
                            if (itemId) {
                                $block.attr('data-abs-item-id', itemId);
                            } else {
                                $block.removeAttr('data-abs-item-id');
                            }
                        }
                        syncBlockDeviceVisibility($block, response.data.device_visibility);
                        applyMobileProfiles($block);
                    })
                    .always(function () {
                        loading = false;
                    });
            }, intervalSec * 1000);
        });
    });
})(jQuery);
