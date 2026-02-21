<?php
/**
 * Plugin Name: ALStrive: Рекламные шорткоды
 * Description: Рекламные группы с ротацией. Элементы: код или картинка+ссылка. Вывод через шорткод.
 * Version: 1.0.0
 * Author: alstrive.ru
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ABS_AD_BLOCKS_SHORTCODES_VERSION')) {
    define('ABS_AD_BLOCKS_SHORTCODES_VERSION', '1.0.0');
}

if (!defined('ABS_AD_BLOCKS_SHORTCODES_FILE')) {
    define('ABS_AD_BLOCKS_SHORTCODES_FILE', __FILE__);
}

require_once plugin_dir_path(__FILE__) . 'includes/class-abs-ad-blocks-rotator.php';

function abs_ad_blocks_shortcodes_run() {
    new ABS_Ad_Blocks_Rotator();
}

abs_ad_blocks_shortcodes_run();
