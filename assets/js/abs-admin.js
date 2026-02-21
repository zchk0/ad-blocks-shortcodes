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

        function initCountryCodesInput() {
            const $list = $('#abs_country_codes_list');
            const $input = $('#abs_item_country_code_input');

            if (!$list.length || !$input.length) {
                return;
            }

            function extractCodes(rawValue) {
                return String(rawValue || '')
                    .toUpperCase()
                    .split(/[^A-Z]+/)
                    .filter(function (code) {
                        return /^[A-Z]{2}$/.test(code);
                    });
            }

            function hasCode(code) {
                let found = false;
                $list.find('.abs-country-tag').each(function () {
                    if (String($(this).data('code')) === code) {
                        found = true;
                    }
                });
                return found;
            }

            function addCode(code) {
                if (!code || hasCode(code)) {
                    return;
                }

                const $tag = $('<span/>', {
                    'class': 'abs-country-tag',
                    'data-code': code,
                    'style': 'display:inline-flex; align-items:center; gap:6px; background:#f0f0f1; border:1px solid #dcdcde; border-radius:4px; padding:2px 8px;'
                });

                $tag.append($('<strong/>').text(code));
                $tag.append($('<button/>', {
                    'type': 'button',
                    'class': 'button-link-delete abs-country-remove',
                    'aria-label': 'Удалить код',
                    'style': 'line-height:1;'
                }).html('&times;'));
                $tag.append($('<input/>', {
                    'type': 'hidden',
                    'name': 'abs_item_country_codes[]',
                    'value': code
                }));

                $list.append($tag);
            }

            function addCodesFromInput() {
                const codes = extractCodes($input.val());
                if (!codes.length) {
                    return;
                }

                codes.forEach(addCode);
                $input.val('');
            }

            $input.on('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addCodesFromInput();
                }
            });

            $input.on('blur', addCodesFromInput);

            $list.on('click', '.abs-country-remove', function (event) {
                event.preventDefault();
                $(this).closest('.abs-country-tag').remove();
            });
        }

        if ($('#abs_rotation_type').length) {
            $('#abs_rotation_type').on('change', toggleRotationType);
            toggleRotationType();
        }

        initDeviceTabs();
        initCountryCodesInput();
    });
})(jQuery);
