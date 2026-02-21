<?php
if (!defined('ABSPATH')) {
    exit;
}

class ABS_Ad_Blocks_Rotator
{
    const CPT_GROUP = 'abs_ad_group';
    const CPT_ITEM  = 'abs_ad_item';

    // Group metas
    const MG_INTERVAL = '_abs_interval';   // seconds
    const MG_MODE     = '_abs_mode';       // random|round
    const MG_STICKY   = '_abs_sticky';     // 1|0

    // Item metas
    const MI_GROUP_ID = '_abs_group_id';
    const MI_ACTIVE   = '_abs_active';     // 1|0
    const MI_TYPE     = '_abs_type';       // code|image
    const MI_CODE     = '_abs_code';
    const MI_IMAGE_ID = '_abs_image_id';
    const MI_LINK_URL = '_abs_link_url';
    const MI_ALT      = '_abs_alt';

    // Image size metas (CSS-like)
    const MI_IMG_W        = '_abs_img_w';       // e.g. "100%" or "300px" or "auto"
    const MI_IMG_H        = '_abs_img_h';       // e.g. "auto" or "250px"
    const MI_IMG_MAX_W    = '_abs_img_max_w';   // e.g. "100%" or "728px"
    const MI_IMG_MAX_H    = '_abs_img_max_h';   // e.g. "" or "250px"
    const MI_IMG_FIT      = '_abs_img_fit';     // contain|cover|fill|none|scale-down
    const MI_IMG_ALIGN    = '_abs_img_align';   // left|center|right
    const MI_IMG_RADIUS   = '_abs_img_radius';  // e.g. "0" or "8px" or "12px"
    const MI_IMG_MARGIN   = '_abs_img_margin';  // e.g. "10px" or "10px 0 20px"

    public function __construct()
    {
        add_action('init', [$this, 'register_cpts']);
        add_action('add_meta_boxes', [$this, 'add_metaboxes']);
        add_action('save_post', [$this, 'save_metaboxes']);

        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);

        add_shortcode('ad_block', [$this, 'shortcode_ad_block']);
        add_action('wp_ajax_abs_ad_block_rotate', [$this, 'ajax_rotate_ad_block']);
        add_action('wp_ajax_nopriv_abs_ad_block_rotate', [$this, 'ajax_rotate_ad_block']);

        // Чтобы шорткоды работали в виджетах/тексте (по желанию)
        add_filter('widget_text', 'do_shortcode');
        add_filter('the_content', 'do_shortcode');
    }

    /* ---------------------------
     * CPTs
     * --------------------------- */
    public function register_cpts()
    {
        register_post_type(self::CPT_GROUP, [
            'labels' => [
                'name'          => 'Список рекламных шорткодов',
                'singular_name' => 'Создать шорткод',
                'add_new_item'  => 'Добавить шорткод',
                'edit_item'     => 'Редактировать шорткод',
                'menu_name'     => 'Рекламные шорткоды',
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-megaphone',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'has_archive'        => false,
        ]);

        register_post_type(self::CPT_ITEM, [
            'labels' => [
                'name'          => 'Рекламные материалы',
                'singular_name' => 'Элемент рекламы',
                'add_new_item'  => 'Добавить элемент',
                'edit_item'     => 'Редактировать элемент',
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=' . self::CPT_GROUP,
            'menu_icon'          => 'dashicons-format-image',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'has_archive'        => false,
        ]);
    }

    /* ---------------------------
     * Assets
     * --------------------------- */
    public function admin_assets()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) {
            return;
        }

        if (in_array($screen->post_type, [self::CPT_ITEM, self::CPT_GROUP], true)) {
            wp_enqueue_media();
        }

        if ($screen->post_type === self::CPT_ITEM) {
            wp_enqueue_script(
                'abs-ad-rotator-admin',
                plugin_dir_url(ABS_AD_BLOCKS_SHORTCODES_FILE) . 'assets/js/abs-admin.js',
                ['jquery'],
                ABS_AD_BLOCKS_SHORTCODES_VERSION,
                true
            );

            wp_localize_script('abs-ad-rotator-admin', 'absAdRotatorAdmin', [
                'mediaTitle'  => 'Выбрать картинку',
                'mediaButton' => 'Использовать',
            ]);
        }
    }

    public function frontend_assets()
    {
        wp_enqueue_style(
            'abs-ad-rotator-frontend',
            plugin_dir_url(ABS_AD_BLOCKS_SHORTCODES_FILE) . 'assets/css/abs-frontend.css',
            [],
            ABS_AD_BLOCKS_SHORTCODES_VERSION
        );

        wp_enqueue_script(
            'abs-ad-rotator-frontend',
            plugin_dir_url(ABS_AD_BLOCKS_SHORTCODES_FILE) . 'assets/js/abs-frontend.js',
            ['jquery'],
            ABS_AD_BLOCKS_SHORTCODES_VERSION,
            true
        );

        wp_localize_script('abs-ad-rotator-frontend', 'absAdRotatorFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    /* ---------------------------
     * Admin UI (Metaboxes)
     * --------------------------- */
    public function add_metaboxes()
    {
        add_meta_box(
            'abs_group_settings',
            'Настройки ротации',
            [$this, 'render_group_metabox'],
            self::CPT_GROUP,
            'normal',
            'high'
        );

        add_meta_box(
            'abs_item_settings',
            'Настройки элемента',
            [$this, 'render_item_metabox'],
            self::CPT_ITEM,
            'normal',
            'high'
        );
    }

    public function render_group_metabox($post)
    {
        wp_nonce_field('abs_save_group', 'abs_group_nonce');

        $interval = (int) get_post_meta($post->ID, self::MG_INTERVAL, true);
        if ($interval <= 0) $interval = 60;

        $mode = get_post_meta($post->ID, self::MG_MODE, true);
        if (!$mode) $mode = 'random';

        $sticky = get_post_meta($post->ID, self::MG_STICKY, true);
        $sticky = ($sticky === '' ? '1' : $sticky);

        $slug = $post->post_name;
?>
        <p>
            <label><strong>Интервал смены (сек.)</strong></label><br />
            <input type="number" name="abs_interval" min="1" value="<?php echo esc_attr($interval); ?>" style="width:180px;" />
            <span style="opacity:.75;">например: 30, 60, 300, 3600</span>
        </p>

        <p>
            <label><strong>Режим ротации</strong></label><br />
            <select name="abs_mode">
                <option value="random" <?php selected($mode, 'random'); ?>>Случайно</option>
                <option value="round" <?php selected($mode, 'round');  ?>>По очереди</option>
            </select>
        </p>

        <p>
            <label>
                <input type="checkbox" name="abs_sticky" value="1" <?php checked($sticky, '1'); ?> />
                Липкость: одному посетителю показывать один и тот же элемент в пределах интервала
            </label>
        </p>

        <hr />
        <p>
            <strong>Шорткод:</strong>
            <code>[ad_block id="<?php echo (int)$post->ID; ?>"]</code>
            <?php if (!empty($slug)) : ?>
                или <code>[ad_block slug="<?php echo esc_html($slug); ?>"]</code>
            <?php endif; ?>
        </p>
        <p style="opacity:.8;">
            Элементы добавляются в разделе <strong>Рекламные материалы</strong> и привязываются к этой группе.
        </p>
    <?php
    }

    public function render_item_metabox($post)
    {
        wp_nonce_field('abs_save_item', 'abs_item_nonce');

        $active   = get_post_meta($post->ID, self::MI_ACTIVE, true);
        $active   = ($active === '' ? '1' : $active);

        $type     = get_post_meta($post->ID, self::MI_TYPE, true);
        if (!$type) $type = 'code';

        $code     = get_post_meta($post->ID, self::MI_CODE, true);
        $image_id = (int) get_post_meta($post->ID, self::MI_IMAGE_ID, true);
        $link     = get_post_meta($post->ID, self::MI_LINK_URL, true);
        $alt      = get_post_meta($post->ID, self::MI_ALT, true);

        $group_id = (int) get_post_meta($post->ID, self::MI_GROUP_ID, true);

        // Image sizing settings
        $img_w     = get_post_meta($post->ID, self::MI_IMG_W, true);
        $img_h     = get_post_meta($post->ID, self::MI_IMG_H, true);
        $img_max_w = get_post_meta($post->ID, self::MI_IMG_MAX_W, true);
        $img_max_h = get_post_meta($post->ID, self::MI_IMG_MAX_H, true);
        $img_fit   = get_post_meta($post->ID, self::MI_IMG_FIT, true);
        $img_align = get_post_meta($post->ID, self::MI_IMG_ALIGN, true);
        $img_rad   = get_post_meta($post->ID, self::MI_IMG_RADIUS, true);
        $img_margin = get_post_meta($post->ID, self::MI_IMG_MARGIN, true);

        if ($img_w === '')     $img_w = '100%';
        if ($img_h === '')     $img_h = 'auto';
        if ($img_max_w === '') $img_max_w = '100%';
        if ($img_fit === '')   $img_fit = 'contain';
        if ($img_align === '') $img_align = 'center';
        if ($img_rad === '')   $img_rad = '0';

        $groups = get_posts([
            'post_type'   => self::CPT_GROUP,
            'post_status' => 'publish',
            'numberposts' => 200,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]);

        $img_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
    ?>
        <p>
            <label>
                <input type="checkbox" name="abs_item_active" value="1" <?php checked($active, '1'); ?> />
                Активен
            </label>
        </p>

        <p>
            <label><strong>Группа</strong></label><br />
            <select name="abs_item_group_id" required>
                <option value="">— выбери группу —</option>
                <?php foreach ($groups as $g) : ?>
                    <option value="<?php echo (int)$g->ID; ?>" <?php selected($group_id, (int)$g->ID); ?>>
                        <?php echo esc_html($g->post_title); ?> (ID: <?php echo (int)$g->ID; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <hr />

        <p>
            <label><strong>Тип элемента</strong></label><br />
            <select name="abs_item_type" id="abs_item_type">
                <option value="code" <?php selected($type, 'code');  ?>>Код (HTML/JS)</option>
                <option value="image" <?php selected($type, 'image'); ?>>Картинка + ссылка</option>
            </select>
        </p>

        <div class="abs-type abs-type-code" style="<?php echo $type === 'code' ? '' : 'display:none;'; ?>">
            <p>
                <label for="abs_item_code"><strong>Код рекламы (HTML/JS)</strong></label>
                <textarea id="abs_item_code" name="abs_item_code" rows="10" style="width:100%; font-family: ui-monospace, Menlo, Monaco, Consolas, monospace;"><?php echo esc_textarea($code); ?></textarea>
            </p>
            <p style="opacity:.75;">Если нужен AdSense/РСЯ/баннер-скрипт — вставляй сюда.</p>
        </div>

        <div class="abs-type abs-type-image" style="<?php echo $type === 'image' ? '' : 'display:none;'; ?>">
            <input type="hidden" name="abs_item_image_id" id="abs_item_image_id" value="<?php echo esc_attr($image_id); ?>" />

            <p>
                <button type="button" class="button" id="abs_pick_image">Выбрать/загрузить картинку</button>
                <button type="button" class="button" id="abs_clear_image" style="<?php echo $image_id ? '' : 'display:none;'; ?>">Очистить</button>
            </p>

            <div id="abs_image_preview" style="margin:10px 0; <?php echo $image_id ? '' : 'display:none;'; ?>">
                <?php if ($img_url) : ?>
                    <img src="<?php echo esc_url($img_url); ?>" style="max-width:100%; height:auto; border:1px solid #ddd; padding:6px; border-radius:8px;" />
                <?php endif; ?>
            </div>

            <p>
                <label><strong>Ссылка при клике</strong></label><br />
                <input type="url" name="abs_item_link_url" value="<?php echo esc_attr($link); ?>" style="width:100%;" placeholder="https://example.com" />
            </p>
            <p>
                <label><strong>ALT (для картинки)</strong></label><br />
                <input type="text" name="abs_item_alt" value="<?php echo esc_attr($alt); ?>" style="width:100%;" placeholder="Описание картинки" />
            </p>

            <hr />
            <p><strong>Условия размеров (CSS)</strong></p>
            <p>
                <label>Ширина (width)</label><br />
                <input type="text" name="abs_img_w" value="<?php echo esc_attr($img_w); ?>" style="width:220px;"
                    placeholder='например: 100% / 300px / auto' />
            </p>
            <p>
                <label>Высота (height)</label><br />
                <input type="text" name="abs_img_h" value="<?php echo esc_attr($img_h); ?>" style="width:220px;"
                    placeholder='например: auto / 250px' />
            </p>
            <p>
                <label>Макс. ширина (max-width)</label><br />
                <input type="text" name="abs_img_max_w" value="<?php echo esc_attr($img_max_w); ?>" style="width:220px;"
                    placeholder='например: 100% / 728px' />
            </p>
            <p>
                <label>Макс. высота (max-height)</label><br />
                <input type="text" name="abs_img_max_h" value="<?php echo esc_attr($img_max_h); ?>" style="width:220px;"
                    placeholder='например: 250px (можно пусто)' />
            </p>
            <p>
                <label>Вписывание (object-fit)</label><br />
                <select name="abs_img_fit">
                    <option value="contain" <?php selected($img_fit, 'contain'); ?>>contain (вписать целиком)</option>
                    <option value="cover" <?php selected($img_fit, 'cover'); ?>>cover (заполнить, может обрезать)</option>
                    <option value="fill" <?php selected($img_fit, 'fill'); ?>>fill</option>
                    <option value="none" <?php selected($img_fit, 'none'); ?>>none</option>
                    <option value="scale-down" <?php selected($img_fit, 'scale-down'); ?>>scale-down</option>
                </select>
            </p>
            <p>
                <label>Выравнивание</label><br />
                <select name="abs_img_align">
                    <option value="left" <?php selected($img_align, 'left'); ?>>Слева</option>
                    <option value="center" <?php selected($img_align, 'center'); ?>>По центру</option>
                    <option value="right" <?php selected($img_align, 'right'); ?>>Справа</option>
                </select>
            </p>
            <p>
                <label>Скругление (border-radius)</label><br />
                <input type="text" name="abs_img_radius" value="<?php echo esc_attr($img_rad); ?>" style="width:220px;"
                    placeholder='например: 0 / 8px / 12px' />
            </p>
            <p>
                <label>Отступы (margin)</label><br />
                <input type="text" name="abs_img_margin" value="<?php echo esc_attr($img_margin); ?>" style="width:260px;"
                    placeholder='например: 10px / 10px 0 / 10px 12px 8px 12px' />
            </p>
            <p style="opacity:.75;">
                Значения — как в CSS: <code>100%</code>, <code>300px</code>, <code>auto</code>.
            </p>
        </div>
<?php
    }

    /* ---------------------------
     * Save handlers
     * --------------------------- */
    public function save_metaboxes($post_id)
    {
        // Group save
        if (isset($_POST['abs_group_nonce']) && wp_verify_nonce($_POST['abs_group_nonce'], 'abs_save_group')) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (!current_user_can('edit_post', $post_id)) return;
            if (get_post_type($post_id) !== self::CPT_GROUP) return;

            $interval = isset($_POST['abs_interval']) ? max(1, (int)$_POST['abs_interval']) : 60;
            $mode = (isset($_POST['abs_mode']) && in_array($_POST['abs_mode'], ['random', 'round'], true)) ? $_POST['abs_mode'] : 'random';
            $sticky = isset($_POST['abs_sticky']) ? '1' : '0';

            update_post_meta($post_id, self::MG_INTERVAL, (string)$interval);
            update_post_meta($post_id, self::MG_MODE, $mode);
            update_post_meta($post_id, self::MG_STICKY, $sticky);
        }

        // Item save
        if (isset($_POST['abs_item_nonce']) && wp_verify_nonce($_POST['abs_item_nonce'], 'abs_save_item')) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (!current_user_can('edit_post', $post_id)) return;
            if (get_post_type($post_id) !== self::CPT_ITEM) return;

            $active = isset($_POST['abs_item_active']) ? '1' : '0';
            update_post_meta($post_id, self::MI_ACTIVE, $active);

            $group_id = isset($_POST['abs_item_group_id']) ? (int)$_POST['abs_item_group_id'] : 0;
            update_post_meta($post_id, self::MI_GROUP_ID, (string)$group_id);

            $type = (isset($_POST['abs_item_type']) && in_array($_POST['abs_item_type'], ['code', 'image'], true)) ? $_POST['abs_item_type'] : 'code';
            update_post_meta($post_id, self::MI_TYPE, $type);

            // code
            $code = isset($_POST['abs_item_code']) ? wp_unslash($_POST['abs_item_code']) : '';
            update_post_meta($post_id, self::MI_CODE, $code);

            // image
            $image_id = isset($_POST['abs_item_image_id']) ? (int)$_POST['abs_item_image_id'] : 0;
            update_post_meta($post_id, self::MI_IMAGE_ID, (string)$image_id);

            $link = isset($_POST['abs_item_link_url']) ? esc_url_raw($_POST['abs_item_link_url']) : '';
            update_post_meta($post_id, self::MI_LINK_URL, $link);

            $alt = isset($_POST['abs_item_alt']) ? sanitize_text_field($_POST['abs_item_alt']) : '';
            update_post_meta($post_id, self::MI_ALT, $alt);

            // sizing (sanitize)
            $img_w     = isset($_POST['abs_img_w']) ? $this->sanitize_css_size($_POST['abs_img_w'], '100%') : '100%';
            $img_h     = isset($_POST['abs_img_h']) ? $this->sanitize_css_size($_POST['abs_img_h'], 'auto') : 'auto';
            $img_max_w = isset($_POST['abs_img_max_w']) ? $this->sanitize_css_size($_POST['abs_img_max_w'], '100%') : '100%';
            $img_max_h = isset($_POST['abs_img_max_h']) ? $this->sanitize_css_size($_POST['abs_img_max_h'], '') : '';

            $fit_allowed = ['contain', 'cover', 'fill', 'none', 'scale-down'];
            $img_fit = (isset($_POST['abs_img_fit']) && in_array($_POST['abs_img_fit'], $fit_allowed, true)) ? $_POST['abs_img_fit'] : 'contain';

            $align_allowed = ['left', 'center', 'right'];
            $img_align = (isset($_POST['abs_img_align']) && in_array($_POST['abs_img_align'], $align_allowed, true)) ? $_POST['abs_img_align'] : 'center';

            $img_radius = isset($_POST['abs_img_radius']) ? $this->sanitize_css_size($_POST['abs_img_radius'], '0') : '0';
            $img_margin = isset($_POST['abs_img_margin']) ? $this->sanitize_css_box_size($_POST['abs_img_margin'], '') : '';

            update_post_meta($post_id, self::MI_IMG_W, $img_w);
            update_post_meta($post_id, self::MI_IMG_H, $img_h);
            update_post_meta($post_id, self::MI_IMG_MAX_W, $img_max_w);
            update_post_meta($post_id, self::MI_IMG_MAX_H, $img_max_h);
            update_post_meta($post_id, self::MI_IMG_FIT, $img_fit);
            update_post_meta($post_id, self::MI_IMG_ALIGN, $img_align);
            update_post_meta($post_id, self::MI_IMG_RADIUS, $img_radius);
            update_post_meta($post_id, self::MI_IMG_MARGIN, $img_margin);
        }
    }

    /* ---------------------------
     * Shortcode output
     * --------------------------- */
    public function shortcode_ad_block($atts)
    {
        $atts = shortcode_atts([
            'id'    => '',
            'slug'  => '',
            'class' => '',
        ], $atts, 'ad_block');

        $group_id = 0;
        if (!empty($atts['id'])) {
            $group_id = absint($atts['id']);
        } elseif (!empty($atts['slug'])) {
            $p = get_page_by_path(sanitize_title($atts['slug']), OBJECT, self::CPT_GROUP);
            if ($p) $group_id = (int)$p->ID;
        }
        if (!$group_id) return '';

        $interval = (int) get_post_meta($group_id, self::MG_INTERVAL, true);
        if ($interval <= 0) $interval = 60;

        $mode = get_post_meta($group_id, self::MG_MODE, true) ?: 'random';
        $sticky = get_post_meta($group_id, self::MG_STICKY, true);
        $sticky = ($sticky === '' ? '1' : $sticky);

        $items = get_posts([
            'post_type'   => self::CPT_ITEM,
            'post_status' => 'publish',
            'numberposts' => 200,
            'meta_query'  => [
                ['key' => self::MI_GROUP_ID, 'value' => (string)$group_id, 'compare' => '='],
                ['key' => self::MI_ACTIVE,   'value' => '1',              'compare' => '='],
            ],
            'orderby' => 'ID',
            'order'   => 'ASC',
        ]);

        if (!$items) return '';

        $chosen = $this->pick_item($group_id, $items, $interval, $mode, $sticky);
        if (!$chosen) return '';

        $class = $this->sanitize_classes($atts['class']);
        $wrapper_class = 'abs-ad-block' . ($class ? ' ' . $class : '');

        return '<div class="' . esc_attr($wrapper_class) . '" data-abs-group-id="' . (int)$group_id . '" data-abs-interval="' . (int)$interval . '"><div class="abs-ad-inner">' . $this->render_item($chosen) . '</div></div>';
    }

    public function ajax_rotate_ad_block()
    {
        $group_id = isset($_POST['group_id']) ? absint(wp_unslash($_POST['group_id'])) : 0;
        if ($group_id <= 0) {
            wp_send_json_error(['message' => 'Invalid group id'], 400);
        }

        $interval = (int) get_post_meta($group_id, self::MG_INTERVAL, true);
        if ($interval <= 0) $interval = 60;

        $mode = get_post_meta($group_id, self::MG_MODE, true) ?: 'random';
        $sticky = get_post_meta($group_id, self::MG_STICKY, true);
        $sticky = ($sticky === '' ? '1' : $sticky);

        $items = get_posts([
            'post_type'   => self::CPT_ITEM,
            'post_status' => 'publish',
            'numberposts' => 200,
            'meta_query'  => [
                ['key' => self::MI_GROUP_ID, 'value' => (string)$group_id, 'compare' => '='],
                ['key' => self::MI_ACTIVE,   'value' => '1',              'compare' => '='],
            ],
            'orderby' => 'ID',
            'order'   => 'ASC',
        ]);

        if (!$items) {
            wp_send_json_error(['message' => 'No items'], 404);
        }

        $chosen = $this->pick_item($group_id, $items, $interval, $mode, $sticky);
        if (!$chosen) {
            wp_send_json_error(['message' => 'No item selected'], 404);
        }

        wp_send_json_success([
            'html' => $this->render_item($chosen),
        ]);
    }

    private function pick_item($group_id, $items, $interval, $mode, $sticky)
    {
        $count = count($items);
        if ($count === 1) return $items[0];

        // меняется каждые $interval секунд
        $bucket = (int) floor(time() / $interval);

        // visitor id for sticky
        $visitor_id = '';
        if ($sticky === '1') {
            $cookie_vid = 'abs_vid';
            if (!isset($_COOKIE[$cookie_vid]) || strlen((string)$_COOKIE[$cookie_vid]) < 8) {
                $visitor_id = wp_generate_password(18, false, false);

                // setcookie: не всегда сработает после вывода, но чаще ок (шорткод обычно в контенте до отправки заголовков)
                @setcookie($cookie_vid, $visitor_id, time() + 3600 * 24 * 365, COOKIEPATH ?: '/', COOKIE_DOMAIN);
                $_COOKIE[$cookie_vid] = $visitor_id;
            } else {
                $visitor_id = sanitize_text_field($_COOKIE[$cookie_vid]);
            }
        }

        if ($mode === 'random') {
            // детерминированный выбор внутри bucket: одинаково для посетителя (если sticky)
            // если не sticky — чуть более случайно
            $seed = $group_id . '|' . $bucket . '|' . ($visitor_id ?: (string)wp_rand());
            $idx = hexdec(substr(md5($seed), 0, 8)) % $count;
            return $items[$idx];
        }

        // round: "по очереди"
        $base = $bucket;
        if ($visitor_id) {
            $base += (hexdec(substr(md5($visitor_id), 0, 6)) % 9973);
        }
        $idx = $base % $count;
        return $items[$idx];
    }

    private function render_item($post)
    {
        $type = get_post_meta($post->ID, self::MI_TYPE, true) ?: 'code';

        if ($type === 'image') {
            $image_id = (int) get_post_meta($post->ID, self::MI_IMAGE_ID, true);
            if (!$image_id) return '';

            $link = get_post_meta($post->ID, self::MI_LINK_URL, true);
            $alt  = get_post_meta($post->ID, self::MI_ALT, true);

            // sizing
            $w     = get_post_meta($post->ID, self::MI_IMG_W, true) ?: '100%';
            $h     = get_post_meta($post->ID, self::MI_IMG_H, true) ?: 'auto';
            $max_w = get_post_meta($post->ID, self::MI_IMG_MAX_W, true) ?: '100%';
            $max_h = get_post_meta($post->ID, self::MI_IMG_MAX_H, true);
            $fit   = get_post_meta($post->ID, self::MI_IMG_FIT, true) ?: 'contain';
            $align = get_post_meta($post->ID, self::MI_IMG_ALIGN, true) ?: 'center';
            $rad   = get_post_meta($post->ID, self::MI_IMG_RADIUS, true) ?: '0';
            $margin = get_post_meta($post->ID, self::MI_IMG_MARGIN, true);

            // sanitize again on output (на случай кривых данных в базе)
            $w     = $this->sanitize_css_size($w, '100%');
            $h     = $this->sanitize_css_size($h, 'auto');
            $max_w = $this->sanitize_css_size($max_w, '100%');
            $max_h = $this->sanitize_css_size($max_h, '');
            $rad   = $this->sanitize_css_size($rad, '0');
            $margin = $this->sanitize_css_box_size($margin, '');

            $fit_allowed = ['contain', 'cover', 'fill', 'none', 'scale-down'];
            if (!in_array($fit, $fit_allowed, true)) $fit = 'contain';

            $align_allowed = ['left', 'center', 'right'];
            if (!in_array($align, $align_allowed, true)) $align = 'center';

            // inline style
            $style = 'display:block;';
            if ($w !== '')     $style .= 'width:' . $w . ';';
            if ($h !== '')     $style .= 'height:' . $h . ';';
            if ($max_w !== '') $style .= 'max-width:' . $max_w . ';';
            if ($max_h !== '') $style .= 'max-height:' . $max_h . ';';
            $style .= 'object-fit:' . $fit . ';';
            if ($rad !== '')   $style .= 'border-radius:' . $rad . ';';
            if ($margin !== '') $style .= 'margin:' . $margin . ';';

            $img_html = wp_get_attachment_image($image_id, 'full', false, [
                'alt'      => $alt ?: $post->post_title,
                'loading'  => 'lazy',
                'decoding' => 'async',
                'style'    => esc_attr($style),
            ]);

            if (!$img_html) return '';

            $wrap_style = 'text-align:' . $align . ';';
            $inner = '<div class="abs-ad-img-wrap" style="' . esc_attr($wrap_style) . '">' . $img_html . '</div>';

            if (empty($link)) return $inner;

            return '<a href="' . esc_url($link) . '" rel="nofollow sponsored" target="_blank">' . $inner . '</a>';
        }

        // code: выводим как есть (реклама часто требует script)
        $code = get_post_meta($post->ID, self::MI_CODE, true);
        if (!$code) return '';
        return $code;
    }

    /* ---------------------------
     * Helpers
     * --------------------------- */

    // Очень консервативная чистка CSS-значений размеров:
    // допускаем: auto | 0 | число + (px|%|em|rem|vh|vw) | calc(...)
    private function sanitize_css_size($value, $default)
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') return $default;

        $lower = strtolower($value);

        // allow 'auto'
        if ($lower === 'auto') return 'auto';

        // allow simple 0 / 0px / 0%
        if (preg_match('/^0+(px|%|em|rem|vh|vw)?$/', $lower)) return $lower;

        // allow numeric + unit
        if (preg_match('/^\d+(\.\d+)?(px|%|em|rem|vh|vw)$/', $lower)) return $lower;

        // allow calc(...) but only safe chars inside
        if (preg_match('/^calc\([0-9\.\s\+\-\*\/%a-z]+\)$/', $lower)) return $lower;

        // If invalid, fallback
        return $default;
    }

    // For margin/padding-like values: 1..4 css size tokens (e.g. "10px 0 12px 0").
    private function sanitize_css_box_size($value, $default)
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') return $default;

        $parts = preg_split('/\s+/', $value);
        if (!is_array($parts) || count($parts) < 1 || count($parts) > 4) {
            return $default;
        }

        $clean = [];
        foreach ($parts as $part) {
            $safe_part = $this->sanitize_css_size($part, '');
            if ($safe_part === '') {
                return $default;
            }
            $clean[] = $safe_part;
        }

        return implode(' ', $clean);
    }

    private function sanitize_classes($classes)
    {
        $classes = is_string($classes) ? trim($classes) : '';
        if ($classes === '') return '';
        $parts = preg_split('/\s+/', $classes);
        $clean = [];
        foreach ($parts as $c) {
            $c = sanitize_html_class($c);
            if ($c !== '') $clean[] = $c;
        }
        return implode(' ', array_unique($clean));
    }
}
