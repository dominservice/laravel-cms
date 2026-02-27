<?php

namespace Dominservice\LaravelCms\Services;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelConfig\Config;
use Dominservice\LaravelConfig\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

class CmsStructuredSyncService
{
    public function sync(): void
    {
        if (!(bool) config('cms.admin.settings.sync.enabled', true)) {
            return;
        }

        $indexes = (array) config('cms.admin.settings.sync.indexes', []);
        if ($indexes === []) {
            return;
        }

        $targetKeys = $this->extractTargetKeys($indexes);
        foreach ($targetKeys as $targetKey) {
            Setting::where('key', 'like', $targetKey . '.%')->delete();
        }

        $payload = [];
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                continue;
            }
            $payload = array_merge($payload, $this->buildIndexPayload($index));
        }

        $config = new Config();
        if ($payload !== []) {
            $config->set($payload);
        }
        $config->buildCache();

        if ((bool) config('cms.admin.settings.sync.rebuild_routes', false)) {
            try {
                Artisan::call('route:cache');
            } catch (\Throwable $e) {
                // no-op in environments where route cache cannot be rebuilt
            }
        }
    }

    /**
     * @param array<string,mixed> $index
     * @return array<string,mixed>
     */
    private function buildIndexPayload(array $index): array
    {
        $targetKey = (string) ($index['target_key'] ?? '');
        if ($targetKey === '') {
            return [];
        }

        if (!empty($index['group_key'])) {
            return $this->buildGroupPayload($index, $targetKey);
        }

        if (!empty($index['single_key'])) {
            return $this->buildSinglePayload($index, $targetKey);
        }

        return [];
    }

    /**
     * @param array<string,mixed> $index
     * @return array<string,mixed>
     */
    private function buildGroupPayload(array $index, string $targetKey): array
    {
        $payload = [];
        $groupKey = (string) ($index['group_key'] ?? '');
        $itemKey = (string) ($index['item_key'] ?? 'page_uuid');
        $orderKey = (string) ($index['order_key'] ?? 'order');
        $entitySwitchKey = (string) ($index['entity_switch_key'] ?? '');
        $entityTypeKey = (string) ($index['entity_type_key'] ?? '');
        $menuKeys = (array) ($index['menu_keys'] ?? []);

        $rows = (array) config($groupKey, []);
        uasort($rows, static function ($a, $b) use ($orderKey): int {
            $left = is_array($a) ? (int) ($a[$orderKey] ?? 0) : 0;
            $right = is_array($b) ? (int) ($b[$orderKey] ?? 0) : 0;
            return $left <=> $right;
        });

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $uuid = (string) ($row[$itemKey] ?? '');
            if ($uuid === '') {
                continue;
            }

            $entityType = 'content';
            if ($entityTypeKey !== '' && !empty($row[$entityTypeKey])) {
                $entityType = (string) $row[$entityTypeKey];
            } elseif ($entitySwitchKey !== '' && !empty($row[$entitySwitchKey])) {
                $entityType = 'category';
            }

            $model = $this->resolveModelByType($entityType, $uuid);
            if (!$model) {
                continue;
            }

            $base = $targetKey . '.' . $model->getKey();
            $payload[$base . '.order'] = (int) ($row[$orderKey] ?? 0);

            if ($entitySwitchKey !== '') {
                $payload[$base . '.' . $entitySwitchKey] = (bool) ($row[$entitySwitchKey] ?? false);
            }

            foreach ($menuKeys as $targetField => $sourceField) {
                if (!is_string($targetField) || !is_string($sourceField) || $sourceField === '') {
                    continue;
                }
                $payload[$base . '.' . $targetField] = (bool) ($row[$sourceField] ?? false);
            }

            foreach ($this->resolveMappedFields($index, $row) as $mappedField => $mappedValue) {
                $payload[$base . '.' . $mappedField] = $mappedValue;
            }

            foreach (CmsLocales::all() as $locale) {
                $translation = $this->translation($model, $locale);
                $payload[$base . '.slug.' . $locale] = (string) ($translation?->slug ?? '');
                $payload[$base . '.name.' . $locale] = (string) ($translation?->name ?? '');
            }

            $payload = array_merge($payload, $this->buildChildrenPayload($index, $model, $base));
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $index
     * @return array<string,mixed>
     */
    private function buildSinglePayload(array $index, string $targetKey): array
    {
        $payload = [];
        $singleKey = (string) ($index['single_key'] ?? '');
        if ($singleKey === '') {
            return $payload;
        }

        $uuid = (string) config($singleKey, '');
        if ($uuid === '') {
            return $payload;
        }

        $singleType = (string) ($index['single_type'] ?? 'content');
        $model = $this->resolveModelByType($singleType, $uuid);
        if (!$model) {
            return $payload;
        }

        if (!empty($index['children']['enabled'])) {
            return $this->buildChildrenPayload($index, $model, $targetKey, true);
        }

        $base = $targetKey . '.' . $model->getKey();
        foreach (CmsLocales::all() as $locale) {
            $translation = $this->translation($model, $locale);
            $payload[$base . '.slug.' . $locale] = (string) ($translation?->slug ?? '');
            $payload[$base . '.name.' . $locale] = (string) ($translation?->name ?? '');
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $index
     * @return array<string,mixed>
     */
    private function buildChildrenPayload(array $index, Model $model, string $base, bool $baseIsTargetKey = false): array
    {
        $childrenConfig = (array) ($index['children'] ?? []);
        if (empty($childrenConfig['enabled'])) {
            return [];
        }

        $relation = (string) ($childrenConfig['relation'] ?? 'contents');
        if (!method_exists($model, $relation)) {
            return [];
        }

        $query = $model->{$relation}();
        $orderKey = (string) ($childrenConfig['order_key'] ?? 'order');
        try {
            $query->orderBy($orderKey);
        } catch (\Throwable $e) {
            $query->orderBy('created_at');
        }

        $children = $query->get();
        $targetSubkey = (string) ($childrenConfig['target_subkey'] ?? 'pages');
        $flatten = (bool) ($childrenConfig['flatten'] ?? false);

        $payload = [];
        $fallbackOrder = 1;
        foreach ($children as $child) {
            $childBase = $flatten
                ? $base . '.' . $child->getKey()
                : $base . '.' . $targetSubkey . '.' . $child->getKey();

            $payload[$childBase . '.order'] = (int) ($child->{$orderKey} ?? $fallbackOrder);
            $fallbackOrder++;

            foreach (CmsLocales::all() as $locale) {
                $translation = $this->translation($child, $locale);
                $payload[$childBase . '.slug.' . $locale] = (string) ($translation?->slug ?? '');
                $payload[$childBase . '.name.' . $locale] = (string) ($translation?->name ?? '');
            }
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $index
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function resolveMappedFields(array $index, array $row): array
    {
        $resolved = [];

        $explicitMappings = (array) ($index['field_mappings'] ?? []);
        foreach ($explicitMappings as $targetField => $sourceField) {
            if (!is_string($targetField) || !is_string($sourceField) || $sourceField === '') {
                continue;
            }
            if (!array_key_exists($sourceField, $row)) {
                continue;
            }
            $value = $row[$sourceField];
            if (is_array($value)) {
                continue;
            }
            $resolved[$targetField] = $value;
        }

        $passthroughFields = (array) ($index['passthrough_fields'] ?? []);
        foreach ($passthroughFields as $fieldName) {
            if (!is_string($fieldName) || $fieldName === '') {
                continue;
            }
            if (!array_key_exists($fieldName, $row)) {
                continue;
            }
            $value = $row[$fieldName];
            if (is_array($value)) {
                continue;
            }
            $resolved[$fieldName] = $value;
        }

        return $resolved;
    }

    /**
     * @param array<int, mixed> $indexes
     * @return array<int, string>
     */
    private function extractTargetKeys(array $indexes): array
    {
        $keys = [];
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                continue;
            }
            $target = (string) ($index['target_key'] ?? '');
            if ($target !== '') {
                $keys[] = $target;
            }
        }

        return array_values(array_unique($keys));
    }

    private function resolveModelByType(string $type, string $uuid): ?Model
    {
        return match ($type) {
            'category' => Category::find($uuid),
            default => Content::find($uuid),
        };
    }

    private function translation(Model $model, string $locale): mixed
    {
        if (method_exists($model, 'translateOrDefault')) {
            return $model->translateOrDefault($locale);
        }

        if (method_exists($model, 'translate')) {
            return $model->translate($locale);
        }

        return null;
    }
}
