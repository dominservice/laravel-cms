<?php

namespace Dominservice\LaravelCms\Http\Livewire\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Services\CmsStructuredSyncService;
use Dominservice\LaravelCms\Support\CmsConfigStore;
use Dominservice\LaravelCms\Support\CmsSectionResolver;
use Illuminate\View\View;
use Livewire\Component;

class SettingsDashboard extends Component
{
    public array $metaFields = [];
    public array $metaValues = [];
    public array $panels = [];

    public bool $pickerOpen = false;
    public string $pickerSource = 'content';
    public ?string $pickerSectionKey = null;
    public ?string $pickerConfigKey = null;
    public ?string $pickerGroupKey = null;
    public ?string $pickerHandle = null;
    public string $pickerItemKey = 'page_uuid';
    public string $pickerSearch = '';
    public array $pickerResults = [];

    public function mount(): void
    {
        $this->loadMetaFields();
        $this->loadPanels();
    }

    public function saveMetaField(string $key, mixed $value): void
    {
        CmsConfigStore::set($key, $value);
        $this->metaValues[$key] = $value;
        session()->flash('status', __('Settings saved.'));
    }

    public function updateGroupField(string $groupKey, string $handle, string $field, mixed $value, string $type = 'boolean'): void
    {
        $key = $groupKey . '.' . $handle . '.' . $field;
        $normalized = $this->normalizeFieldValue($value, $type);
        CmsConfigStore::set($key, $normalized);

        $this->syncAndRefresh();
    }

    /**
     * @param array<int, string> $handles
     */
    public function reorderGroup(string $groupKey, string $orderKey, array $handles): void
    {
        $payload = [];
        $position = 1;
        foreach ($handles as $handle) {
            if (!is_string($handle) || $handle === '') {
                continue;
            }
            $payload[$groupKey . '.' . $handle . '.' . $orderKey] = $position;
            $position++;
        }

        CmsConfigStore::setMany($payload);
        $this->syncAndRefresh();
    }

    public function openUuidPicker(
        string $source,
        string $sectionKey,
        ?string $configKey = null,
        ?string $groupKey = null,
        ?string $handle = null,
        string $itemKey = 'page_uuid'
    ): void {
        $this->pickerOpen = true;
        $this->pickerSource = $source;
        $this->pickerSectionKey = $sectionKey;
        $this->pickerConfigKey = $configKey;
        $this->pickerGroupKey = $groupKey;
        $this->pickerHandle = $handle;
        $this->pickerItemKey = $itemKey;
        $this->pickerSearch = '';
        $this->pickerResults = $this->searchPickerResults();
    }

    public function closeUuidPicker(): void
    {
        $this->pickerOpen = false;
        $this->pickerSearch = '';
        $this->pickerResults = [];
    }

    public function updatedPickerSearch(): void
    {
        if (!$this->pickerOpen) {
            return;
        }

        $this->pickerResults = $this->searchPickerResults();
    }

    public function selectPickerUuid(string $uuid): void
    {
        $key = $this->pickerConfigKey;
        if (!$key && $this->pickerGroupKey && $this->pickerHandle) {
            $key = $this->pickerGroupKey . '.' . $this->pickerHandle . '.' . $this->pickerItemKey;
        }

        if (!$key) {
            $this->closeUuidPicker();
            return;
        }

        CmsConfigStore::set($key, $uuid);
        $this->closeUuidPicker();
        $this->syncAndRefresh();
    }

    public function render(): View
    {
        return view('cms::livewire.admin.settings.dashboard')
            ->extends('cms::layouts.admin')
            ->section('content');
    }

    private function loadMetaFields(): void
    {
        $this->metaFields = (array) config('cms.admin.settings.meta_fields', []);
        $this->metaValues = [];

        foreach ($this->metaFields as $field) {
            if (!is_array($field) || empty($field['key'])) {
                continue;
            }
            $key = (string) $field['key'];
            $this->metaValues[$key] = config($key);
        }
    }

    private function loadPanels(): void
    {
        $panelConfigs = (array) config('cms.admin.settings.panels', []);
        $panels = [];

        foreach ($panelConfigs as $index => $panelConfig) {
            if (!is_array($panelConfig)) {
                continue;
            }

            $source = (string) ($panelConfig['source'] ?? 'content');
            if (!in_array($source, ['content', 'category'], true)) {
                continue;
            }

            $availableSections = $source === 'category'
                ? CmsSectionResolver::categorySections()
                : CmsSectionResolver::contentSections();

            $requestedSections = (array) ($panelConfig['sections'] ?? []);
            if ($requestedSections !== []) {
                $filtered = [];
                foreach ($requestedSections as $sectionKey) {
                    if (!is_string($sectionKey) || !isset($availableSections[$sectionKey])) {
                        continue;
                    }
                    $filtered[$sectionKey] = $availableSections[$sectionKey];
                }
                $availableSections = $filtered;
            }

            $sections = [];
            foreach ($availableSections as $sectionKey => $sectionConfig) {
                $rows = $this->buildRowsForSection($source, $sectionKey, (array) $sectionConfig);
                $sections[] = [
                    'key' => $sectionKey,
                    'label' => (string) ($sectionConfig['label'] ?? $sectionKey),
                    'source' => $source,
                    'group_key' => (string) ($sectionConfig['group_key'] ?? ''),
                    'order_key' => (string) ($sectionConfig['order_key'] ?? 'order'),
                    'manage_url' => $this->sectionManageUrl($source, $sectionKey),
                    'rows' => $rows,
                ];
            }

            $panels[] = [
                'key' => (string) ($panelConfig['key'] ?? ('panel_' . $index)),
                'label' => (string) ($panelConfig['label'] ?? ucfirst($source)),
                'source' => $source,
                'sections' => $sections,
            ];
        }

        $this->panels = $panels;
    }

    /**
     * @param array<string,mixed> $sectionConfig
     * @return array<int, array<string,mixed>>
     */
    private function buildRowsForSection(string $source, string $sectionKey, array $sectionConfig): array
    {
        if (!empty($sectionConfig['group_key'])) {
            return $this->buildGroupRows($source, $sectionKey, $sectionConfig);
        }

        if (!empty($sectionConfig['config_key'])) {
            return [$this->buildSingleRow($source, $sectionKey, $sectionConfig)];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $sectionConfig
     * @return array<int, array<string,mixed>>
     */
    private function buildGroupRows(string $source, string $sectionKey, array $sectionConfig): array
    {
        $rows = [];
        $groupKey = (string) ($sectionConfig['group_key'] ?? '');
        $itemKey = (string) ($sectionConfig['item_key'] ?? ($source === 'category' ? 'category_uuid' : 'page_uuid'));
        $orderKey = (string) ($sectionConfig['order_key'] ?? 'order');
        $groupItems = (array) config($groupKey, []);

        uasort($groupItems, static function ($a, $b) use ($orderKey): int {
            $left = is_array($a) ? (int) ($a[$orderKey] ?? 0) : 0;
            $right = is_array($b) ? (int) ($b[$orderKey] ?? 0) : 0;
            return $left <=> $right;
        });

        foreach ($groupItems as $handle => $item) {
            if (!is_array($item) || !is_string($handle)) {
                continue;
            }

            $rows[] = $this->buildConfigRow(
                $source,
                $sectionKey,
                $sectionConfig,
                $groupKey . '.' . $handle . '.' . $itemKey,
                $groupKey,
                $handle,
                $itemKey,
                $item
            );
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $sectionConfig
     * @return array<string,mixed>
     */
    private function buildSingleRow(string $source, string $sectionKey, array $sectionConfig): array
    {
        $configKey = (string) ($sectionConfig['config_key'] ?? '');
        return $this->buildConfigRow(
            $source,
            $sectionKey,
            $sectionConfig,
            $configKey,
            null,
            null,
            (string) ($sectionConfig['item_key'] ?? ($source === 'category' ? 'category_uuid' : 'page_uuid')),
            []
        );
    }

    /**
     * @param array<string,mixed> $sectionConfig
     * @param array<string,mixed> $groupItem
     * @return array<string,mixed>
     */
    private function buildConfigRow(
        string $source,
        string $sectionKey,
        array $sectionConfig,
        string $configKey,
        ?string $groupKey,
        ?string $handle,
        string $itemKey,
        array $groupItem
    ): array {
        $uuid = (string) config($configKey, '');
        $effectiveSource = $this->resolveRowSource($source, $sectionConfig, $groupItem);
        $model = $uuid !== '' ? $this->resolveModel($effectiveSource, $uuid) : null;

        $editUrl = null;
        $createUrl = null;
        $listUrl = null;

        if ($model) {
            if ($effectiveSource === 'category') {
                $editUrl = route($this->adminRoute('category.edit'), ['category' => $model->getKey()]) . '?' . http_build_query([
                    'section' => $sectionKey,
                    'config_key' => $configKey,
                    'config_handle' => $handle,
                ]);
                $listUrl = route($this->adminRoute('category.contents'), ['category' => $model->getKey()]);
            } else {
                $editUrl = route($this->adminRoute('content.edit'), ['content' => $model->getKey()]) . '?' . http_build_query([
                    'section' => $sectionKey,
                    'config_key' => $configKey,
                    'config_handle' => $handle,
                ]);
            }
        } else {
            if ($effectiveSource === 'category') {
                $createUrl = route($this->adminRoute('category.create')) . '?' . http_build_query(array_filter([
                    'section' => $sectionKey,
                    'config_key' => $configKey,
                    'config_handle' => $handle,
                ]));
            } else {
                $createUrl = route($this->adminRoute('content.section.create'), ['section' => $sectionKey]) . '?' . http_build_query(array_filter([
                    'config_key' => $configKey,
                    'config_handle' => $handle,
                    'type' => $sectionConfig['type'] ?? null,
                ]));
            }
        }

        return [
            'source' => $effectiveSource,
            'section_key' => $sectionKey,
            'config_key' => $configKey,
            'group_key' => $groupKey,
            'handle' => $handle,
            'item_key' => $itemKey,
            'label' => $this->rowLabel($sectionConfig, $handle, $groupItem, $model),
            'uuid' => $uuid,
            'entity_name' => $model ? $this->entityName($model) : null,
            'entity_type' => $effectiveSource,
            'edit_url' => $editUrl,
            'create_url' => $createUrl,
            'list_url' => $listUrl,
            'options' => $this->resolveOptionFields($source, $sectionConfig, $groupItem, $groupKey, $handle),
        ];
    }

    /**
     * @param array<string,mixed> $sectionConfig
     * @param array<string,mixed> $groupItem
     */
    private function resolveRowSource(string $source, array $sectionConfig, array $groupItem): string
    {
        $switchKey = (string) ($sectionConfig['entity_switch_key'] ?? '');
        if ($switchKey !== '' && !empty($groupItem[$switchKey])) {
            return 'category';
        }

        if ($source === 'content' && array_key_exists('category', $groupItem) && !empty($groupItem['category'])) {
            return 'category';
        }

        return $source;
    }

    /**
     * @param array<string,mixed> $sectionConfig
     * @param array<string,mixed> $groupItem
     * @return array<int, array<string,mixed>>
     */
    private function resolveOptionFields(
        string $source,
        array $sectionConfig,
        array $groupItem,
        ?string $groupKey,
        ?string $handle
    ): array {
        if (!$groupKey || !$handle) {
            return [];
        }

        $configured = (array) ($sectionConfig['settings_fields'] ?? []);
        $fields = [];

        if ($configured !== []) {
            foreach ($configured as $fieldKey => $fieldSchema) {
                if (is_int($fieldKey) && is_string($fieldSchema)) {
                    $fieldKey = $fieldSchema;
                    $fieldSchema = [];
                }
                if (!is_string($fieldKey) || $fieldKey === '') {
                    continue;
                }
                $schema = is_array($fieldSchema) ? $fieldSchema : [];
                $fields[] = $this->buildOptionField($groupKey, $handle, $fieldKey, $schema);
            }

            return $fields;
        }

        $skip = [
            (string) ($sectionConfig['item_key'] ?? ($source === 'category' ? 'category_uuid' : 'page_uuid')),
            (string) ($sectionConfig['order_key'] ?? 'order'),
            'label',
        ];

        foreach ($groupItem as $key => $value) {
            if (!is_string($key) || in_array($key, $skip, true) || is_array($value)) {
                continue;
            }

            $fields[] = $this->buildOptionField($groupKey, $handle, $key, [
                'type' => is_bool($value) ? 'boolean' : 'text',
            ]);
        }

        return $fields;
    }

    /**
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private function buildOptionField(string $groupKey, string $handle, string $fieldKey, array $schema): array
    {
        $configPath = $groupKey . '.' . $handle . '.' . $fieldKey;
        $type = (string) ($schema['type'] ?? 'boolean');
        $raw = config($configPath);
        $value = $type === 'boolean' ? (bool) $raw : (string) ($raw ?? '');

        return [
            'field' => $fieldKey,
            'label' => (string) ($schema['label'] ?? ucfirst(str_replace('_', ' ', $fieldKey))),
            'type' => $type,
            'config_path' => $configPath,
            'value' => $value,
        ];
    }

    private function sectionManageUrl(string $source, string $sectionKey): string
    {
        if ($source === 'category') {
            return route($this->adminRoute('category.index')) . '?section=' . urlencode($sectionKey);
        }

        return route($this->adminRoute('content.index')) . '?section=' . urlencode($sectionKey);
    }

    private function entityName(mixed $model): string
    {
        if (method_exists($model, 'translateOrDefault')) {
            $translation = $model->translateOrDefault(config('app.locale'));
            if (!empty($translation?->name)) {
                return (string) $translation->name;
            }
        }

        return (string) ($model->name ?? $model->uuid ?? '');
    }

    /**
     * @param array<string,mixed> $sectionConfig
     * @param array<string,mixed> $groupItem
     */
    private function rowLabel(array $sectionConfig, ?string $handle, array $groupItem, mixed $model): string
    {
        if (!empty($groupItem['label']) && is_string($groupItem['label'])) {
            return $groupItem['label'];
        }

        if ($model) {
            return $this->entityName($model);
        }

        if ($handle) {
            return ucfirst(str_replace('_', ' ', $handle));
        }

        return (string) ($sectionConfig['label'] ?? $sectionConfig['key'] ?? __('Item'));
    }

    private function resolveModel(string $source, string $uuid): mixed
    {
        return $source === 'category'
            ? Category::find($uuid)
            : Content::find($uuid);
    }

    /**
     * @return array<int, array<string,string>>
     */
    private function searchPickerResults(): array
    {
        $limit = (int) config('cms.admin.settings.uuid_picker.limit', 25);
        $query = trim($this->pickerSearch);

        if ($this->pickerSource === 'category') {
            $builder = Category::query()->with('translations');
            if ($query !== '') {
                $builder->where(function ($q) use ($query) {
                    $q->where('uuid', 'like', '%' . $query . '%')
                        ->orWhereHas('translations', function ($t) use ($query) {
                            $t->where('name', 'like', '%' . $query . '%')
                                ->orWhere('slug', 'like', '%' . $query . '%');
                        });
                });
            }

            return $builder->limit($limit)->get()->map(function (Category $item): array {
                return [
                    'uuid' => (string) $item->uuid,
                    'name' => $this->entityName($item),
                ];
            })->all();
        }

        $builder = Content::query()->with('translations');
        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $q->where('uuid', 'like', '%' . $query . '%')
                    ->orWhereHas('translations', function ($t) use ($query) {
                        $t->where('name', 'like', '%' . $query . '%')
                            ->orWhere('slug', 'like', '%' . $query . '%');
                    });
            });
        }

        return $builder->limit($limit)->get()->map(function (Content $item): array {
            return [
                'uuid' => (string) $item->uuid,
                'name' => $this->entityName($item),
            ];
        })->all();
    }

    private function normalizeFieldValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'number' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => is_string($value) ? trim($value) : $value,
        };
    }

    private function syncAndRefresh(): void
    {
        app(CmsStructuredSyncService::class)->sync();
        $this->loadPanels();
        session()->flash('status', __('Settings saved.'));
    }

    private function adminRoute(string $name): string
    {
        $prefix = rtrim((string) config('cms.admin.route_name_prefix', 'cms.'), '.');
        return $prefix === '' ? $name : $prefix . '.' . $name;
    }
}

