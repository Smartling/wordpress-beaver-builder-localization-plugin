<?php

namespace Smartling\BeaverBuilder;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorManager;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

class Bootloader
{
    private const PLUGIN_NAME = 'Plugin Name';
    private const SUPPORTED_PLUGIN_VERSIONS = 'SupportedPluginVersions';
    private const SUPPORTED_SMARTLING_CONNECTOR_VERSIONS = 'SupportedSmartlingConnectorVersions';

    private ContainerBuilder $di;

    public function __construct(string $pluginFile, ContainerBuilder $di)
    {
        $allPlugins = get_plugins();
        $currentPluginName = $this->getPluginName($pluginFile);
        foreach (
            [
                'Beaver Builder Plugin (Pro Version)' => self::SUPPORTED_PLUGIN_VERSIONS,
                'Smartling Connector' => self::SUPPORTED_SMARTLING_CONNECTOR_VERSIONS,
            ] as $pluginName => $metaName
        ) {
            [$minVersion, $maxVersion] = explode('-', $this->getPluginMeta($pluginFile, $metaName));
            $installed = self::findPluginByName($allPlugins, $pluginName);
            if ($installed && !$this->versionInRange($installed['Version'] ?? '0', $minVersion, $maxVersion)) {
                throw new \RuntimeException("<strong>$currentPluginName</strong> extension plugin requires <strong>$pluginName</strong> plugin version at least <strong>$minVersion</strong> and at most <strong>$maxVersion</strong>");
            }
        }

        require_once __DIR__ . DIRECTORY_SEPARATOR . 'BeaverBuilderFieldsFilterHelper.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'BeaverBuilderMediaProcessor.php';
        $this->di = $di;
    }

    private function getPluginMeta(string $pluginFile, string $metaName): string
    {
        $pluginData = get_file_data($pluginFile, [$metaName => $metaName]);

        return $pluginData[$metaName];
    }

    private function getPluginName(string $pluginFile): string
    {
        return $this->getPluginMeta($pluginFile, self::PLUGIN_NAME);
    }

    private function versionInRange(string $version, string $minVersion, string $maxVersion): bool
    {
        $maxVersionParts = explode('.', $maxVersion);
        $versionParts = explode('.', $version);
        $potentiallyNotSupported = false;
        foreach ($maxVersionParts as $index => $part) {
            if (!array_key_exists($index, $versionParts)) {
                return false; // misconfiguration
            }
            if ($versionParts[$index] > $part && $potentiallyNotSupported) {
                return false; // not supported
            }

            $potentiallyNotSupported = $versionParts[$index] === $part;
        }

        return version_compare($version, $minVersion, '>=');
    }

    /**
     * @return false|array
     */
    private static function findPluginByName(array $allPlugins, string $name)
    {
        return ArrayHelper::first(array_filter($allPlugins, static function ($item) use ($name) {
            return $item['Name'] === $name;
        }));
    }

    public function run(): void
    {
        $this->di->set('fields-filter.helper', new BeaverBuilderFieldsFilterHelper(
            $this->di->get('manager.settings'),
            $this->di->get('acf.dynamic.support'),
        ));
        /**
         * @var MetaFieldProcessorManager $metaFieldProcessorManager
         */
        $metaFieldProcessorManager = $this->di->get('meta-field.processor.manager');
        $metaFieldProcessorManager->registerProcessor(new BeaverBuilderMediaProcessor());
    }
}
