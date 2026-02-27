<?php

namespace Dominservice\LaravelCms\Support;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;

class CmsSectionResolver
{
    public static function contentSections(): array
    {
        $configuredSections = (array) config('cms.admin.content.sections', []);
        $sections = $configuredSections;
        if ($sections === []) {
            $sections = self::contentSectionsFromDefaultPages();
        }

        $includeAll = (bool) config('cms.admin.content.include_all', true);
        if ($configuredSections !== [] && $includeAll && !array_key_exists('all', $sections)) {
            $sections['all'] = [
                'label' => 'All content',
                'allow_create' => true,
                'list_all' => true,
            ];
        }

        foreach ($sections as $key => $section) {
            $sections[$key] = self::normalizeSection($key, (array) $section, 'content');
        }

        return $sections;
    }

    public static function categorySections(): array
    {
        $configuredSections = (array) config('cms.admin.category.sections', []);
        $sections = $configuredSections;
        if ($sections === []) {
            $sections = [
                'categories' => [
                    'label' => 'Categories',
                ],
            ];
        }

        $includeAll = (bool) config('cms.admin.category.include_all', true);
        if ($configuredSections !== [] && $includeAll && !array_key_exists('all', $sections)) {
            $sections['all'] = [
                'label' => 'All categories',
                'allow_create' => true,
                'list_all' => true,
            ];
        }

        foreach ($sections as $key => $section) {
            $sections[$key] = self::normalizeSection($key, (array) $section, 'category');
        }

        return $sections;
    }

    public static function contentSection(string $key): ?array
    {
        $sections = self::contentSections();
        return $sections[$key] ?? null;
    }

    public static function categorySection(string $key): ?array
    {
        $sections = self::categorySections();
        return $sections[$key] ?? null;
    }

    public static function itemsForContentSection(array $section): array
    {
        return self::itemsForSection($section, Content::class);
    }

    public static function itemsForCategorySection(array $section): array
    {
        return self::itemsForSection($section, Category::class);
    }

    public static function blocksForContentSection(array $section): array
    {
        $blocks = [];
        $blockConfigs = (array) ($section['blocks'] ?? []);

        foreach ($blockConfigs as $blockKey => $blockConfig) {
            $blockConfig = (array) $blockConfig;
            $configKey = $blockConfig['config_key'] ?? null;
            $uuid = $configKey ? config($configKey) : null;
            $blocks[$blockKey] = [
                'key' => $blockKey,
                'label' => $blockConfig['label'] ?? self::labelFromKey($blockKey),
                'config_key' => $configKey,
                'type' => $blockConfig['type'] ?? 'block',
                'model' => $uuid ? Content::find($uuid) : null,
            ];
        }

        return $blocks;
    }

    public static function formFields(array $section, string $type): array
    {
        $fields = $section['form_fields'] ?? [];
        $required = $type === 'content'
            ? ['type', 'name', 'description']
            : ['type', 'name'];

        foreach ($required as $field) {
            if (!in_array($field, $fields, true)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private static function itemsForSection(array $section, string $modelClass): array
    {
        $items = [];

        if (!empty($section['group_key'])) {
            $groupKey = $section['group_key'];
            $itemKey = $section['item_key'] ?? ($modelClass === Category::class ? 'category_uuid' : 'page_uuid');
            $orderKey = (string) ($section['order_key'] ?? 'order');
            $configItems = (array) config($groupKey, []);
            uasort($configItems, static function ($left, $right) use ($orderKey): int {
                $leftOrder = is_array($left) ? (int) ($left[$orderKey] ?? 0) : 0;
                $rightOrder = is_array($right) ? (int) ($right[$orderKey] ?? 0) : 0;

                return $leftOrder <=> $rightOrder;
            });

            foreach ($configItems as $handle => $data) {
                $configKey = $groupKey . '.' . $handle . '.' . $itemKey;
                $uuid = config($configKey);
                $items[] = [
                    'key' => (string) $handle,
                    'label' => $data['label'] ?? self::labelFromKey((string) $handle),
                    'config_key' => $configKey,
                    'model' => $uuid ? $modelClass::find($uuid) : null,
                ];
            }

            return $items;
        }

        if (!empty($section['config_key'])) {
            $configKey = $section['config_key'];
            $uuid = config($configKey);
            $items[] = [
                'key' => $section['key'],
                'label' => $section['label'],
                'config_key' => $configKey,
                'model' => $uuid ? $modelClass::find($uuid) : null,
            ];

            return $items;
        }

        $query = $modelClass::query();
        if (!empty($section['type']) && $modelClass === Content::class && empty($section['list_all'])) {
            $query->where('type', $section['type']);
        }
        $items = $query->get()->map(function ($model) {
            return [
                'key' => $model->getKey(),
                'label' => $model->getKey(),
                'config_key' => null,
                'model' => $model,
            ];
        })->all();

        return $items;
    }

    private static function normalizeSection(string $key, array $section, string $type): array
    {
        $hasExplicitType = array_key_exists('type', $section);
        $section['key'] = $key;
        $section['label'] = $section['label'] ?? self::labelFromKey($key);
        $section['type'] = $section['type'] ?? ($type === 'content' ? 'page' : 'default');
        $section['type_explicit'] = $hasExplicitType;

        if (isset($section['form']['fields']) && !isset($section['form_fields'])) {
            $section['form_fields'] = (array) $section['form']['fields'];
        }

        if (!isset($section['columns'])) {
            $section['columns'] = (array) config("cms.admin.{$type}.default_columns", []);
        }

        if (!isset($section['form_fields'])) {
            $section['form_fields'] = (array) config("cms.admin.{$type}.default_form_fields", []);
        }

        return $section;
    }

    private static function contentSectionsFromDefaultPages(): array
    {
        $sections = [];
        $defaultPages = (array) config('cms.default_pages', []);

        foreach ($defaultPages as $key => $data) {
            if (!is_array($data)) {
                continue;
            }

            if (self::isGroupLikeSection($data)) {
                $itemKey = self::detectGroupItemKey($data, 'page_uuid');
                $sections[$key] = [
                    'label' => self::labelFromKey($key),
                    'type' => 'page',
                    'group_key' => 'cms.default_pages.' . $key,
                    'item_key' => $itemKey,
                    'allow_create' => true,
                    'order_key' => 'order',
                ];
                continue;
            }

            if (array_key_exists('blocks', $data)) {
                $blocks = [];
                foreach ((array) $data['blocks'] as $blockKey => $blockValue) {
                    $blocks[$blockKey] = [
                        'label' => self::labelFromKey($blockKey),
                        'type' => 'block',
                        'config_key' => "cms.default_pages.{$key}.blocks.{$blockKey}",
                    ];
                }

                $sections[$key] = [
                    'label' => self::labelFromKey($key),
                    'type' => 'page',
                    'config_key' => "cms.default_pages.{$key}.page_uuid",
                    'blocks' => $blocks,
                ];
                continue;
            }

            if (array_key_exists('page_uuid', $data)) {
                $sections[$key] = [
                    'label' => self::labelFromKey($key),
                    'type' => 'page',
                    'config_key' => "cms.default_pages.{$key}.page_uuid",
                ];
            }
        }

        return $sections;
    }

    private static function labelFromKey(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function isGroupLikeSection(array $data): bool
    {
        if (array_key_exists('page_uuid', $data) || array_key_exists('blocks', $data)) {
            return false;
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                return false;
            }

            if (
                !array_key_exists('page_uuid', $value)
                && !array_key_exists('category_uuid', $value)
                && !array_key_exists('uuid', $value)
            ) {
                return false;
            }
        }

        return $data !== [];
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function detectGroupItemKey(array $data, string $fallback): string
    {
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (array_key_exists('page_uuid', $item)) {
                return 'page_uuid';
            }
            if (array_key_exists('category_uuid', $item)) {
                return 'category_uuid';
            }
            if (array_key_exists('uuid', $item)) {
                return 'uuid';
            }
        }

        return $fallback;
    }
}
