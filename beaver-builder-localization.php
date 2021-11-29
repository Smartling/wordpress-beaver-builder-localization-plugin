<?php

use Smartling\BeaverBuilder\Bootloader;

/**
 * @package smartling-beaver-builder
 * @wordpress-plugin
 * Author: Smartling
 * Author URI: https://www.smartling.com
 * Plugin Name: Smartling-Beaver Builder
 * Version: 2.8.0
 * Description: Extend Smartling Connector functionality to support Beaver Builder
 * SupportedSmartlingConnectorVersions: 2.8-2.8
 * SupportedPluginVersions: 2.4-2.4
 */

if (!class_exists(Bootloader::class)) {
    require_once plugin_dir_path(__FILE__) . 'src/Bootloader.php';
}

/**
 * Execute ONLY for admin pages
 */
if ((defined('DOING_CRON') && true === DOING_CRON) || is_admin()) {
    add_action('smartling_before_init', static function ($di) {
        try {
            (new Bootloader(__FILE__, $di))->run();
        } catch (\RuntimeException $e) {
            add_action('all_admin_notices', static function () use ($e) {
                $text = esc_html($e->getMessage());
                echo "<div class=\"error\"><p>$text</p></div>";
            });
        }
    });
}
