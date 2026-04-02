<?php

namespace Dominservice\LaravelCms\Http\Livewire\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Services\ContentSaveService;
use Dominservice\LaravelCms\Services\CmsStructuredSyncService;
use Dominservice\LaravelCms\Support\CmsConfigStore;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsSectionResolver;
use Dominservice\LaravelCms\Support\CmsTypeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContentForm extends Component
{
    use WithFileUploads;

    public Content $content;
    public ?string $sectionKey = null;
    public ?string $blockKey = null;
    public ?string $configKey = null;
    public ?string $configHandle = null;
    public array $fields = [];
    public array $locales = [];
    public array $types = [];
    public ?string $fixedType = null;
    public array $categories = [];
    public array $translations = [];
    public ?string $category_uuid = null;
    public ?string $type = null;
    public bool $status = false;
    public bool $is_nofollow = false;
    public ?string $external_url = null;
    public ?string $media_type = null;
    public $avatar;
    public $avatar_small;
    public $poster;
    public $small_poster;
    public ?string $selected_avatar_asset_uuid = null;
    public ?string $selected_avatar_small_asset_uuid = null;
    public ?string $selected_poster_asset_uuid = null;
    public ?string $selected_small_poster_asset_uuid = null;
    public array $schemaFields = [];
    public array $metaData = [];
    public array $metaTranslations = [];

    public function mount(?string $section = null, ?string $blockKey = null, ?Content $content = null): void
    {
        $this->sectionKey = $section ?? request()->query('section');
        $this->blockKey = $blockKey ?? request()->query('block');

        $this->content = $content ?? new Content();
        $sectionConfig = $this->sectionKey ? CmsSectionResolver::contentSection($this->sectionKey) : null;
        $blockConfig = ($this->blockKey && $sectionConfig) ? (($sectionConfig['blocks'][$this->blockKey] ?? null)) : null;

        $this->configKey = request()->query('config_key');
        $this->configHandle = request()->query('config_handle');
        $queryType = request()->query('type');
        $hasScopedTypeContext = $this->sectionKey !== null
            || $this->blockKey !== null
            || $this->configKey !== null
            || $this->configHandle !== null;
        if ($queryType && $hasScopedTypeContext) {
            $this->fixedType = (string) $queryType;
        }
        if (!$this->configKey) {
            $this->configKey = $blockConfig['config_key'] ?? $sectionConfig['config_key'] ?? null;
        }

        $this->fields = $sectionConfig
            ? CmsSectionResolver::formFields($sectionConfig, 'content')
            : config('cms.admin.content.default_form_fields', []);
        $this->schemaFields = $this->resolveSchemaFields($sectionConfig, $blockConfig);
        $this->locales = CmsLocales::all();
        $this->types = CmsTypeResolver::contentTypes();
        $this->categories = Category::all()
            ->map(fn (Category $category) => [
                'uuid' => $category->uuid,
                'name' => $category->translate(CmsLocales::default())?->name ?? $category->uuid,
            ])
            ->all();

        $this->fixedType = $this->fixedType ?? ($blockConfig['type'] ?? $sectionConfig['type'] ?? null);
        $this->type = $this->content->type
            ? $this->normalizeTypeValue($this->content->type)
            : ($this->fixedType ?: 'page');
        $this->status = (bool) $this->content->status;
        $this->is_nofollow = (bool) $this->content->is_nofollow;
        $this->external_url = $this->content->external_url;
        $this->category_uuid = $this->content->categories->pluck('uuid')->first();
        $this->media_type = $this->content->video_path ? 'video' : 'image';

        $existingMeta = $this->normalizeMeta($this->content->meta);
        foreach ($this->schemaFields as $fieldKey => $schema) {
            if (!empty($schema['translatable'])) {
                foreach ($this->locales as $locale) {
                    $default = $this->resolveSchemaDefault($schema, $locale);
                    $this->metaTranslations[$locale][$fieldKey] = $this->normalizeSchemaValue(
                        Arr::get($existingMeta, '_translations.' . $locale . '.' . $fieldKey, $default),
                        (string) ($schema['type'] ?? 'text')
                    );
                }
            } else {
                $default = $this->resolveSchemaDefault($schema);
                $this->metaData[$fieldKey] = $this->normalizeSchemaValue(
                    Arr::get($existingMeta, $fieldKey, $default),
                    (string) ($schema['type'] ?? 'text')
                );
            }
        }

        foreach ($this->locales as $locale) {
            $translation = $this->content->translate($locale);
            $this->translations[$locale] = [
                'name' => $translation?->name ?? '',
                'sub_name' => $translation?->sub_name ?? '',
                'short_description' => $translation?->short_description ?? '',
                'description' => $translation?->description ?? '',
                'meta_title' => $translation?->meta_title ?? '',
                'meta_keywords' => $translation?->meta_keywords ?? '',
                'meta_description' => $translation?->meta_description ?? '',
            ];
        }
    }

    public function save(): void
    {
        $service = new ContentSaveService();
        $this->validate($service->validationRules());

        $sectionConfig = $this->sectionKey ? CmsSectionResolver::contentSection($this->sectionKey) : null;
        $blockConfig = ($this->blockKey && $sectionConfig) ? (($sectionConfig['blocks'][$this->blockKey] ?? null)) : null;
        $type = $blockConfig['type'] ?? $sectionConfig['type'] ?? $this->type ?? 'page';

        $prepared = $service->prepareTranslatableData($this->translations, $type);
        $data = $prepared['data'];

        if (!$prepared['hasName']) {
            $this->addError('translations', __('cms::laravel_cms.name_required_one_language'));
            return;
        }

        $data['type'] = $type;
        $data['status'] = $this->status ? 1 : 0;
        $data['is_nofollow'] = $this->is_nofollow ? 1 : 0;
        $data['external_url'] = $this->external_url;
        $data['parent_uuid'] = $this->content->parent_uuid;
        $data['meta'] = $this->buildMetaPayload();

        if ($this->content->uuid) {
            $this->content->update($data);
        } else {
            $this->content = Content::create($data);
        }

        if ($this->category_uuid) {
            $this->content->categories()->sync([$this->category_uuid]);
        } else {
            $this->content->categories()->detach();
        }

        $service->handleMedia($this->content, $this->buildMediaRequest(), !$this->content->wasRecentlyCreated);

        if ($sectionConfig) {
            $this->persistConfig($sectionConfig, $this->content, $this->configKey, $this->configHandle, $blockConfig);
        }

        app(CmsStructuredSyncService::class)->sync();

        session()->flash('status', $this->content->wasRecentlyCreated ? __('cms::laravel_cms.content_created') : __('cms::laravel_cms.content_updated'));
        $this->redirectRoute($this->adminRoute('content.index'));
    }

    public function render(): View
    {
        return view('cms::livewire.admin.content.form')
            ->extends('cms::layouts.admin')
            ->section('content');
    }

    private function buildMediaRequest(): Request
    {
        $payload = array_filter([
            'media_type' => $this->media_type,
            'avatar_kind' => null,
            'avatar_type' => null,
        ], static fn ($value) => $value !== null);

        $request = Request::create('/', 'POST', $payload);
        if ($this->avatar) {
            $request->files->set('avatar', $this->avatar);
        }
        if ($this->avatar_small) {
            $request->files->set('avatar_small', $this->avatar_small);
        }
        if ($this->poster) {
            $request->files->set('poster', $this->poster);
        }
        if ($this->small_poster) {
            $request->files->set('small_poster', $this->small_poster);
        }

        foreach ([
            'selected_avatar_asset_uuid' => $this->selected_avatar_asset_uuid,
            'selected_avatar_small_asset_uuid' => $this->selected_avatar_small_asset_uuid,
            'selected_poster_asset_uuid' => $this->selected_poster_asset_uuid,
            'selected_small_poster_asset_uuid' => $this->selected_small_poster_asset_uuid,
        ] as $key => $value) {
            if (is_string($value) && $value !== '') {
                $request->request->set($key, $value);
            }
        }

        return $request;
    }

    private function normalizeTypeValue(mixed $type): string
    {
        if ($type instanceof \BackedEnum) {
            return $type->value;
        }

        if (is_string($type)) {
            return $type;
        }

        return (string) $type;
    }

    private function resolveSchemaFields(?array $sectionConfig, ?array $blockConfig): array
    {
        $configured = $blockConfig['schema_fields'] ?? $sectionConfig['schema_fields'] ?? [];
        $resolved = [];

        foreach ((array) $configured as $fieldKey => $fieldSchema) {
            if (is_int($fieldKey) && is_string($fieldSchema)) {
                $fieldKey = $fieldSchema;
                $fieldSchema = [];
            }

            if (!is_string($fieldKey) || $fieldKey === '') {
                continue;
            }

            $schema = is_array($fieldSchema) ? $fieldSchema : [];
            $resolved[$fieldKey] = array_merge([
                'label' => ucfirst(str_replace('_', ' ', $fieldKey)),
                'type' => 'text',
                'translatable' => false,
                'options' => [],
                'default' => null,
                'help' => null,
            ], $schema);
        }

        return $resolved;
    }

    private function buildMetaPayload(): array
    {
        $meta = $this->normalizeMeta($this->content->meta);

        foreach ($this->schemaFields as $fieldKey => $schema) {
            $fieldType = (string) ($schema['type'] ?? 'text');
            if (!empty($schema['translatable'])) {
                foreach ($this->locales as $locale) {
                    $value = $this->metaTranslations[$locale][$fieldKey] ?? $this->resolveSchemaDefault($schema, $locale);
                    Arr::set($meta, '_translations.' . $locale . '.' . $fieldKey, $this->normalizeSchemaValue($value, $fieldType));
                }
                continue;
            }

            $value = $this->metaData[$fieldKey] ?? $this->resolveSchemaDefault($schema);
            Arr::set($meta, $fieldKey, $this->normalizeSchemaValue($value, $fieldType));
        }

        return $meta;
    }

    private function normalizeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_object($meta)) {
            return json_decode(json_encode($meta, JSON_UNESCAPED_UNICODE) ?: '[]', true) ?: [];
        }

        return [];
    }

    private function resolveSchemaDefault(array $schema, ?string $locale = null): mixed
    {
        $default = $schema['default'] ?? null;
        if ($locale !== null && is_array($default)) {
            return $default[$locale] ?? null;
        }

        return $default;
    }

    private function normalizeSchemaValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'checkbox', 'boolean', 'toggle' => (bool) $value,
            'number' => is_numeric($value) ? $value + 0 : null,
            'repeater' => is_array($value) ? array_values($value) : [],
            'editorjs', 'textarea', 'text', 'url', 'select' => is_scalar($value) || $value === null ? (string) ($value ?? '') : '',
            default => $value,
        };
    }

    public function addRepeaterItem(string $fieldKey, ?string $locale = null): void
    {
        $schema = $this->schemaFields[$fieldKey] ?? null;
        if (!is_array($schema) || (string) ($schema['type'] ?? '') !== 'repeater') {
            return;
        }

        $item = $this->defaultRepeaterItem($schema, $locale);

        if ($locale !== null && !empty($schema['translatable'])) {
            $rows = $this->metaTranslations[$locale][$fieldKey] ?? [];
            $rows[] = $item;
            $this->metaTranslations[$locale][$fieldKey] = array_values($rows);
            return;
        }

        $rows = $this->metaData[$fieldKey] ?? [];
        $rows[] = $item;
        $this->metaData[$fieldKey] = array_values($rows);
    }

    public function removeRepeaterItem(string $fieldKey, int $index, ?string $locale = null): void
    {
        $schema = $this->schemaFields[$fieldKey] ?? null;
        if (!is_array($schema) || (string) ($schema['type'] ?? '') !== 'repeater') {
            return;
        }

        if ($locale !== null && !empty($schema['translatable'])) {
            $rows = $this->metaTranslations[$locale][$fieldKey] ?? [];
            unset($rows[$index]);
            $this->metaTranslations[$locale][$fieldKey] = array_values($rows);
            return;
        }

        $rows = $this->metaData[$fieldKey] ?? [];
        unset($rows[$index]);
        $this->metaData[$fieldKey] = array_values($rows);
    }

    private function defaultRepeaterItem(array $schema, ?string $locale = null): array
    {
        $defaults = [];

        foreach ((array) ($schema['fields'] ?? []) as $subFieldKey => $subFieldSchema) {
            if (!is_string($subFieldKey) || $subFieldKey === '') {
                continue;
            }

            $subSchema = is_array($subFieldSchema) ? $subFieldSchema : [];
            $defaults[$subFieldKey] = $this->normalizeSchemaValue(
                $this->resolveSchemaDefault($subSchema, $locale),
                (string) ($subSchema['type'] ?? 'text')
            );
        }

        return $defaults;
    }

    private function persistConfig(array $section, Content $content, ?string $configKey, ?string $configHandle, ?array $blockConfig): void
    {
        if ($blockConfig && !empty($blockConfig['config_key'])) {
            CmsConfigStore::set($blockConfig['config_key'], $content->uuid);
            return;
        }

        if ($configKey) {
            CmsConfigStore::set($configKey, $content->uuid);
            return;
        }

        if (!empty($section['group_key'])) {
            $itemKey = $section['item_key'] ?? 'page_uuid';
            $handle = $configHandle ?: CmsConfigStore::generateHandle($content->name ?? $content->uuid, $section['group_key'], $itemKey);
            $baseKey = $section['group_key'] . '.' . $handle;

            $payload = [];
            foreach ((array) ($section['defaults'] ?? []) as $key => $value) {
                $payload[$baseKey . '.' . $key] = $value;
            }
            $payload[$baseKey . '.' . $itemKey] = $content->uuid;
            CmsConfigStore::setMany($payload);
        }
    }

    private function adminRoute(string $name): string
    {
        $prefix = rtrim((string) config('cms.admin.route_name_prefix', 'cms.'), '.');
        return $prefix === '' ? $name : $prefix . '.' . $name;
    }
}
