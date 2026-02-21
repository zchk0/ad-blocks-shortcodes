(function ($) {
    "use strict";

    $(function () {
        let frame;

        function refreshPreview(url) {
            if (!url) {
                $('#abs_image_preview').hide().empty();
                $('#abs_clear_image').hide();
                return;
            }

            $('#abs_image_preview')
                .html('<img src="' + url + '" style="max-width:100%; height:auto; border:1px solid #ddd; padding:6px; border-radius:8px;" />')
                .show();
            $('#abs_clear_image').show();
        }

        $('#abs_pick_image').on('click', function (event) {
            event.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }

            if (frame) {
                frame.open();
                return;
            }

            const l10n = window.absAdRotatorAdmin || {};

            frame = wp.media({
                title: l10n.mediaTitle || 'Выбрать картинку',
                button: { text: l10n.mediaButton || 'Использовать' },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                $('#abs_item_image_id').val(attachment.id);

                const url = (attachment.sizes && attachment.sizes.medium)
                    ? attachment.sizes.medium.url
                    : attachment.url;

                refreshPreview(url);
            });

            frame.open();
        });

        $('#abs_clear_image').on('click', function (event) {
            event.preventDefault();
            $('#abs_item_image_id').val('');
            refreshPreview('');
        });

        $('#abs_item_type').on('change', function () {
            const isCode = $(this).val() === 'code';
            $('.abs-type-code').toggle(isCode);
            $('.abs-type-image').toggle(!isCode);
        });

        function toggleRotationType() {
            const isTime = $('#abs_rotation_type').val() === 'time';
            $('.abs-rotation-time').toggle(isTime);
            $('.abs-rotation-page-random').toggle(!isTime);
        }

        function initDeviceTabs() {
            $('.abs-device-tabs').each(function () {
                const $tabs = $(this);
                const $container = $tabs.closest('.abs-type-image');
                const $buttons = $tabs.find('.abs-device-tab');
                const $panels = $container.find('.abs-device-panel');

                function activateTab(tabKey) {
                    $buttons.each(function () {
                        const $btn = $(this);
                        const isActive = String($btn.data('device-tab')) === String(tabKey);
                        $btn.toggleClass('button-primary', isActive);
                        $btn.toggleClass('is-active', isActive);
                    });

                    $panels.each(function () {
                        const $panel = $(this);
                        const isActive = String($panel.data('device-panel')) === String(tabKey);
                        $panel.toggle(isActive);
                    });
                }

                $buttons.on('click', function (event) {
                    event.preventDefault();
                    activateTab($(this).data('device-tab'));
                });

                activateTab('general');
            });
        }

        if ($('#abs_rotation_type').length) {
            $('#abs_rotation_type').on('change', toggleRotationType);
            toggleRotationType();
        }

        initDeviceTabs();
    });
})(jQuery);
