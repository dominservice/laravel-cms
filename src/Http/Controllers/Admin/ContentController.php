<?php

namespace Dominservice\LaravelCms\Http\Controllers\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Services\ContentSaveService;
use Dominservice\LaravelCms\Support\CmsConfigStore;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsSectionResolver;
use Dominservice\LaravelCms\Support\CmsTypeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(): View
    {
        $locale = CmsLocales::default();
        $sections = [];

        foreach (CmsSectionResolver::contentSections() as $section) {
            $items = CmsSectionResolver::itemsForContentSection($section);
            $blocks = CmsSectionResolver::blocksForContentSection($section);

            $sections[] = [
                'key' => $section['key'],
                'label' => $section['label'],
                'columns' => $section['columns'],
                'items' => $this->mapContentItems($items, $section, $locale),
                'blocks' => $this->mapContentItems($blocks, $section, $locale, true),
                'allow_create' => (bool) ($section['allow_create'] ?? false),
            ];
        }

        return view('cms::admin.content.index', [
            'sections' => $sections,
        ]);
    }

    public function create(string $section, ?string $blockKey = null): View
    {
        $sectionConfig = CmsSectionResolver::contentSection($section);
        abort_if(!$sectionConfig, 404);

        $blockConfig = $blockKey ? (($sectionConfig['blocks'][$blockKey] ?? null)) : null;
        $type = $blockConfig['type'] ?? $sectionConfig['type'] ?? 'page';
        $content = new Content();
        $content->type = $type;

        return view('cms::admin.content.form', [
            'content' => $content,
            'sectionKey' => $section,
            'blockKey' => $blockKey,
            'configKey' => $blockConfig['config_key'] ?? $sectionConfig['config_key'] ?? null,
            'configHandle' => null,
            'fields' => CmsSectionResolver::formFields($sectionConfig, 'content'),
            'locales' => CmsLocales::all(),
            'types' => CmsTypeResolver::contentTypes(),
            'fixedType' => $type,
            'categories' => Category::all(),
        ]);
    }

    public function store(Request $request, string $section, ?string $blockKey = null): RedirectResponse
    {
        $sectionConfig = CmsSectionResolver::contentSection($section);
        abort_if(!$sectionConfig, 404);

        $blockConfig = $blockKey ? (($sectionConfig['blocks'][$blockKey] ?? null)) : null;
        $type = $blockConfig['type'] ?? $sectionConfig['type'] ?? 'page';

        $service = new ContentSaveService();
        $request->validate($service->validationRules());

        $prepared = $service->prepareTranslatableData($request->all(), $type);
        $data = $prepared['data'];

        if (!$prepared['hasName']) {
            return redirect()->back()
                ->withErrors(['error' => 'The name cannot be empty. Provide a name in at least one language.'])
                ->withInput();
        }

        $data['type'] = $type;
        $data['status'] = $request->boolean('status') ? 1 : 0;
        $data['is_nofollow'] = $request->boolean('is_nofollow') ? 1 : 0;
        $data['external_url'] = $request->input('external_url');

        $content = Content::create($data);

        if ($categoryUuid = $request->input('category_uuid')) {
            $content->categories()->sync([$categoryUuid]);
        }

        $service->handleMedia($content, $request, false);

        $this->persistConfig($sectionConfig, $content, $request->input('config_key'), $request->input('config_handle'), $blockConfig);

        return redirect()
            ->route($this->adminRoute('content.index'))
            ->with('status', 'Content created.');
    }

    public function edit(Content $content, Request $request): View
    {
        $sectionKey = $request->input('section');
        $blockKey = $request->input('block');
        $sectionConfig = $sectionKey ? CmsSectionResolver::contentSection($sectionKey) : null;

        return view('cms::admin.content.form', [
            'content' => $content,
            'sectionKey' => $sectionKey,
            'blockKey' => $blockKey,
            'configKey' => $request->input('config_key'),
            'configHandle' => $request->input('config_handle'),
            'fields' => $sectionConfig ? CmsSectionResolver::formFields($sectionConfig, 'content') : config('cms.admin.content.default_form_fields', []),
            'locales' => CmsLocales::all(),
            'types' => CmsTypeResolver::contentTypes(),
            'fixedType' => $sectionConfig['type'] ?? null,
            'categories' => Category::all(),
        ]);
    }

    public function update(Request $request, Content $content): RedirectResponse
    {
        $sectionKey = $request->input('section');
        $sectionConfig = $sectionKey ? CmsSectionResolver::contentSection($sectionKey) : null;
        $type = $this->normalizeTypeValue($content->type);

        $service = new ContentSaveService();
        $request->validate($service->validationRules());

        $prepared = $service->prepareTranslatableData($request->all(), $type);
        $data = $prepared['data'];

        if (!$prepared['hasName']) {
            return redirect()->back()
                ->withErrors(['error' => 'The name cannot be empty. Provide a name in at least one language.'])
                ->withInput();
        }

        $data['status'] = $request->boolean('status') ? 1 : 0;
        $data['is_nofollow'] = $request->boolean('is_nofollow') ? 1 : 0;
        $data['external_url'] = $request->input('external_url');

        $content->update($data);

        if ($categoryUuid = $request->input('category_uuid')) {
            $content->categories()->sync([$categoryUuid]);
        } else {
            $content->categories()->detach();
        }

        $service->handleMedia($content, $request, true);

        if ($sectionConfig) {
            $this->persistConfig($sectionConfig, $content, $request->input('config_key'), $request->input('config_handle'), null);
        }

        return redirect()
            ->route($this->adminRoute('content.index'))
            ->with('status', 'Content updated.');
    }

    public function destroy(Content $content): RedirectResponse
    {
        $content->delete();

        return redirect()
            ->route($this->adminRoute('content.index'))
            ->with('status', 'Content deleted.');
    }

    public function categoryContents(Category $category): View
    {
        $locale = CmsLocales::default();
        $items = $category->contents()->get()->map(function (Content $content) {
            return [
                'key' => $content->uuid,
                'label' => $content->uuid,
                'config_key' => null,
                'model' => $content,
            ];
        })->all();

        $section = [
            'key' => 'category-' . $category->uuid,
            'label' => 'Category: ' . ($category->translate($locale)?->name ?? $category->uuid),
            'columns' => config('cms.admin.content.default_columns', []),
            'items' => $this->mapContentItems($items, ['key' => 'category'], $locale),
            'blocks' => [],
            'allow_create' => false,
        ];

        return view('cms::admin.content.index', [
            'sections' => [$section],
        ]);
    }

    private function mapContentItems(array $items, array $section, string $locale, bool $isBlock = false): array
    {
        $columns = $section['columns'] ?? config('cms.admin.content.default_columns', []);

        return collect($items)->map(function (array $item) use ($columns, $locale, $section, $isBlock) {
            /** @var Content|null $model */
            $model = $item['model'] ?? null;
            $translation = $model ? $model->translate($locale) : null;

            $columnValues = [];
            foreach ($columns as $column) {
                $columnValues[$column] = $this->columnValue($column, $model, $translation, $item);
            }

            $query = [
                'section' => $section['key'] ?? null,
                'block' => $isBlock ? ($item['key'] ?? null) : null,
                'config_key' => $item['config_key'] ?? null,
                'config_handle' => $item['key'] ?? null,
            ];

            return [
                'key' => $item['key'] ?? null,
                'label' => $item['label'] ?? null,
                'config_key' => $item['config_key'] ?? null,
                'model' => $model,
                'columns' => $columnValues,
                'edit_url' => $model ? $this->editUrl($model, $query) : null,
                'create_url' => $model ? null : $this->createUrl($section['key'] ?? null, $isBlock ? ($item['key'] ?? null) : null),
                'delete_url' => $model ? route($this->adminRoute('content.destroy'), $model) : null,
            ];
        })->all();
    }

    private function columnValue(string $column, ?Content $model, mixed $translation, array $item): string
    {
        if (!$model) {
            return '-';
        }

        return match ($column) {
            'uuid' => $model->uuid,
            'name' => $translation?->name ?? '-',
            'slug' => $translation?->slug ?? '-',
            'type' => $this->normalizeTypeValue($model->type) ?: '-',
            'status' => $model->status ? 'Enabled' : 'Disabled',
            'category' => $model->categories()->first()?->name ?? '-',
            'config_key' => $item['config_key'] ?? '-',
            default => (string) ($model->{$column} ?? '-'),
        };
    }

    private function editUrl(Content $content, array $query): string
    {
        $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');
        $url = route($this->adminRoute('content.edit'), $content);

        return $query ? $url . '?' . http_build_query($query) : $url;
    }

    private function createUrl(?string $sectionKey, ?string $blockKey = null): ?string
    {
        if (!$sectionKey) {
            return null;
        }

        if ($blockKey) {
            return route($this->adminRoute('content.block.create'), [
                'section' => $sectionKey,
                'blockKey' => $blockKey,
            ]);
        }

        return route($this->adminRoute('content.section.create'), ['section' => $sectionKey]);
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
