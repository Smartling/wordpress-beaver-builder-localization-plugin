<?php

namespace Smartling\BeaverBuilder;

use Smartling\Bootstrap;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Submissions\SubmissionEntity;

class BeaverBuilderFieldsFilterHelper extends FieldsFilterHelper
{
    private const DATA_FIELD_NAME = '_fl_builder_data';
    private const META_FIELD_NAME = 'meta/' . self::DATA_FIELD_NAME;
    private const META_NODE_PATH_NAME_REGEX = self::META_FIELD_NAME . '/[0-9a-f]{13}/';
    public const META_NODE_SETTINGS_NAME_REGEX = self::META_NODE_PATH_NAME_REGEX . 'settings/';
    private const META_NODE_SETTINGS_CHILD_NODE_REGEX = self::META_NODE_SETTINGS_NAME_REGEX . '.+/';

    public function processStringsBeforeEncoding(
        SubmissionEntity $submission,
        array $data,
        string $strategy = self::FILTER_STRATEGY_UPLOAD
    ): array
    {
        if (!array_key_exists(self::DATA_FIELD_NAME, $data['meta'] ?? [])) {
            $this->getLogger()->debug("No Beaver Builder data found while processing strings for submissionId={$submission->getId()}, sourceId={$submission->getSourceId()}");
            return parent::processStringsBeforeEncoding($submission, $data, $strategy);
        }
        ContentSerializationHelper::prepareFieldProcessorValues($this->getSettingsManager(), $submission);
        $data = $this->prepareSourceData($data);
        $data = $this->flattenArray($data);

        $data = $this->removeUntranslatable($data);
        $data = $this->passFieldProcessorsBeforeSendFilters($submission, $data);

        return $this->passConnectionProfileFilters($data, $strategy, $this->getSettingsManager()->getSingleSettingsProfile($submission->getSourceBlogId())->getFilterFieldNameRegExp());
    }

    private function removeUntranslatable(array $data): array
    {
        $result = [];
        $base = self::META_NODE_SETTINGS_NAME_REGEX;
        $heads = [
            $base,
            "{$base}list_items/\\d+/"
        ];
        $remove = array_merge($this->flattenArray(Bootstrap::getContainer()->get('content-serialization.helper')->getRemoveFields()), [
            '^meta/_fl_builder_data_settings/css$',
            '^meta/_fl_builder_data_settings/js$',
            '^meta/_fl_builder_draft',
            '^meta/_fl_builder_history_position',
            '^meta/_fl_builder_history_state',
            '^' . self::META_NODE_PATH_NAME_REGEX . 'node$',
            '^' . self::META_NODE_PATH_NAME_REGEX . 'parent$',
            '^' . self::META_NODE_PATH_NAME_REGEX . 'position$',
            '^' . self::META_NODE_PATH_NAME_REGEX . 'type$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'align',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'animation',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'border',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'caption',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'crop$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'click_action$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'data/(?!id$|caption$)',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'feed_url$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'export$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '(heading|content)_typography',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?id$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'import$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?type',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'typography',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'layout',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'list_(?!items)',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*list_items/\d+/[^/]*padding[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*margin[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*responsive[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*padding[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'photo_',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'separator_style$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'show_captions?$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*size[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'source$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?style$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*tag[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'title_hover$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?transition',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?type$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*visibility[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*width[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*bg_[^/]+$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*ss_[^/]+$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?color$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_family$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?height$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?layout$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?position$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?style$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?target$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?tag$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?transition$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?type$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?unit$',
        ]);
        foreach ($heads as $head) {
            foreach ([
                         '[^/]*border[^/]*$',
                         'class$',
                         '[^/]*color[^/]*$',
                         '[^/]*container[^/]*$',
                         'content_alignment$',
                         '[^/]*edge[^/]*$',
                         'flrich\d{13}_content$',
                         'flrich\d{13}_text$',
                         '[^/]*height[^/]*$',
                         '[^/]*icon[^/]*$',
                         'id$',
                         '[^/]*link[^/]*$',
                         '[^/]*margin[^/]*$',
                         '[^/]*responsive[^/]*$',
                         '[^/]*padding[^/]*$',
                         '[^/]*size[^/]*$',
                         '[^/]*tag[^/]*$',
                         'type$',
                         '[^/]*visibility[^/]*$',
                         '[^/]*width[^/]*$',
                         '[^/]*bg_[^/]+$',
                         '[^/]*ss_[^/]+$',
                     ] as $property) {
                $remove[] = $head . $property;
            }
        }
        foreach ($data as $key => $value) {
            foreach ($remove as $regex) {
                if (0 !== preg_match("~$regex~", $key)) {
                    continue 2;
                }
            }
            $result[$key] = $value;
        }

        unset($result['entity/post_content']);

        return $result;
    }

    public function applyTranslatedValues(SubmissionEntity $submission, array $originalValues, array $translatedValues, $_ = true): array
    {
        $originalValues = $this->flattenArray($this->prepareSourceData($originalValues));
        $translatedValues = $this->flattenArray($this->prepareSourceData($translatedValues));

        $result = array_merge($originalValues, $translatedValues);

        $removeFields = Bootstrap::getContainer()->get('content-serialization.helper')->getRemoveFields();
        $removeFields['entity'] = array_merge($removeFields['entity'], ['ID', 'post_status', 'guid', 'comment_count']);
        foreach ($removeFields as $prefix => $fields) {
            foreach ($fields as $field) {
                unset ($result["$prefix/$field"]);
            }
        }

        return $this->inflateArray(get_post_meta($submission->getSourceId(), '_fl_builder_data')[0] ?? [], $result);
    }

    private function buildData(\stdClass $original, array $array, string $prefix, string $path = ''): \stdClass
    {
        $arrayOriginal = (array)$original;
        foreach ($array as $key => $value) {
            $currentType = gettype($value);
            $newPath = ltrim($path . self::ARRAY_DIVIDER . $key, self::ARRAY_DIVIDER);
            if (array_key_exists($prefix . $newPath, $arrayOriginal)) {
                $originalType = gettype($arrayOriginal["$prefix$newPath"]);
                if ($currentType !== $originalType) {
                    if (is_array($value) && $originalType === 'object') {
                        $array[$key] = $this->buildData($original, $value, $prefix, $newPath);
                    } elseif (is_scalar($value)) {
                        settype($array[$key], $originalType);
                    }
                }
            }
        }

        return (object)$array;
    }

    private function inflateArray(array $data, array $translated): array
    {
        $result = $this->structurizeArray($translated, self::ARRAY_DIVIDER);
        foreach ($result['meta'][self::DATA_FIELD_NAME] ?? [] as $key => $value) {
            $result['meta'][self::DATA_FIELD_NAME][$key] = $this->buildData(
                $data[$key],
                $value,
                ''
            );
        }
        if (array_key_exists('_fl_builder_data_settings', $result['meta'] ?? [])) {
            $result['meta']['_fl_builder_data_settings'] = $this->toStdClass($result['meta']['_fl_builder_data_settings']);
        }
        return $result;
    }

    public function flattenArray(array $array, string $base = '', string $divider = self::ARRAY_DIVIDER): array
    {
        $result = [];
        foreach ($array as $key => $element) {
            $path = '' === $base ? $key : implode($divider, [$base, $key]);
            $result[] = $this->processArrayElement($path, $element, $divider);
        }

        return array_merge(...$result);
    }

    private function flattenObject(\stdClass $object, string $base, string $divider): array
    {
        $result = [];
        foreach ((array)$object as $key => $value) {
            $path = '' === $base ? $key : implode($divider, [$base, $key]);
            $result[] = $this->processArrayElement($path, $value, $divider);
        }

        return array_merge(...$result);
    }

    private function toStdClass(array $array): \stdClass
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->toStdClass($value);
            }
        }

        return (object)$array;
    }

    private function processArrayElement(string $path, $value, string $divider): array
    {
        $valueType = gettype($value);
        $result = [];
        switch ($valueType) {
            case 'array':
                $result = $this->flattenArray($value, $path, $divider);
                break;
            case 'NULL':
            case 'boolean':
            case 'string':
            case 'integer':
            case 'double':
                $result[$path] = (string)$value;
                break;
            case 'object':
                $result = $this->flattenObject($value, $path, $divider);
                break;
            case 'unknown type':
            case 'resource':
            default:
                $message = vsprintf(
                    'Unsupported type \'%s\' found in scope for translation. Skipped. Contents: \'%s\'.',
                    [$valueType, var_export($value, true)]
                );
                $this->getLogger()->warning($message);
        }

        return $result;
    }
}
