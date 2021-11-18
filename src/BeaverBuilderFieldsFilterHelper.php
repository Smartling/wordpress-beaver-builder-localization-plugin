<?php

namespace Smartling\BeaverBuilder;

use Smartling\Bootstrap;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Submissions\SubmissionEntity;

class BeaverBuilderFieldsFilterHelper extends FieldsFilterHelper
{
    private const DATA_FIELD_NAME = '_fl_builder_data';
    private const META_FIELD_NAME = 'meta/ ' . self::DATA_FIELD_NAME;

    private function getRemoveList(): array
    {
        $base = self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/';
        $heads = [
            $base,
            "{$base}list_items/\\d+/",
        ];
        $remove = [
            '^meta/_fl_builder_history_position',
            '^meta/_fl_builder_draft',
            '^meta/_fl_builder_history_state',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/node$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/parent$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/position$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/type$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/animation',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/click_action$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/feed_url$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/export$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/(heading|content)_typography',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/import$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/typography',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/layout',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/list_(?!items)',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*list_items/\d+/[^/]*padding[^/]*$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*margin[^/]*$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*responsive[^/]*$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*padding[^/]*$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/photo_border',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/separator_style$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/show_captions$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*size[^/]*$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/source$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*tag[^/]*$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/type$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*visibility[^/]*$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*width[^/]*$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*bg_[^/]+$',
            '^' . self::META_FIELD_NAME . '/[0-9a-f]{13}/settings/[^/]*ss_[^/]+$',
        ];
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
        return $remove;
    }

    public function processStringsBeforeEncoding(
        SubmissionEntity $submission,
        array $data,
        string $strategy = self::FILTER_STRATEGY_UPLOAD
    ): array
    {
        ContentSerializationHelper::prepareFieldProcessorValues($this->getSettingsManager(), $submission);
        $data = $this->prepareSourceData($data);
        $data = $this->flattenArray($data);

        $data = $this->removeUntranslatable($data);
        $data = $this->passFieldProcessorsBeforeSendFilters($submission, $data);

        try {
            $removeAsRegExp = $this->getSettingsManager()->getSingleSettingsProfile($submission->getSourceBlogId())->getFilterFieldNameRegExp();
        } catch (SmartlingDbException $e) {
            $removeAsRegExp = false;
        }

        return $this->passConnectionProfileFilters($data, $strategy, $removeAsRegExp);
    }

    private function removeUntranslatable(array $data): array
    {
        $remove = $this->getRemoveList();
        $result = [];

        foreach ($data as $key => $value) {
            foreach ($remove as $regex) {
                if (0 !== preg_match("~$regex~", $key)) {
                    continue 2;
                }
            }
            $result[$key] = $value;
        }

        return $result;
    }

    public function applyTranslatedValues(SubmissionEntity $submission, array $originalValues, array $translatedValues, $_ = true): array
    {
        $result = array_merge(
            $this->flattenArray($this->prepareSourceData($originalValues)),
            $this->flattenArray($this->prepareSourceData($translatedValues)),
        );

        try {
            $removeFields = Bootstrap::getContainer()->get('content-serialization.helper')->getRemoveFields();
        } catch (\Exception $e) {
            $removeFields = [];
        }
        if (!array_key_exists('entity', $removeFields)) {
            $removeFields['entity'] = [];
        }
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
        foreach ($result['meta'][self::DATA_FIELD_NAME] as $key => $value) {
            $result['meta'][self::DATA_FIELD_NAME][$key] = $this->buildData(
                $data[$key],
                $value,
                ''
            );
        }
        $result['meta']['_fl_builder_data_settings'] = $this->toStdClass($result['meta']['_fl_builder_data_settings']);
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
