<?php

use Smartling\BeaverBuilder\Bootloader;

/**
 * @package smartling-beaver-builder
 * @wordpress-plugin
 * Author: Smartling
 * Author URI: https://www.smartling.com
 * Plugin Name: Smartling-Beaver Builder
 * Version: 2.11.1
 * Description: Extend Smartling Connector functionality to support Beaver Builder
 * SupportedSmartlingConnectorVersions: 2.9-2.11
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
                echo "<div class=\"error\"><p>" . esc_html($e->getMessage()) . "</p></div>";
            });
        }
    });
}
