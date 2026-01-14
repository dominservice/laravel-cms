<?php

namespace Dominservice\LaravelCms\Http\Controllers\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Services\CategorySaveService;
use Dominservice\LaravelCms\Support\CmsConfigStore;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsSectionResolver;
use Dominservice\LaravelCms\Support\CmsTypeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $locale = CmsLocales::default();
        $sections = [];

        foreach (CmsSectionResolver::categorySections() as $section) {
            $items = CmsSectionResolver::itemsForCategorySection($section);
            $sections[] = [
                'key' => $section['key'],
                'label' => $section['label'],
                'columns' => $section['columns'],
                'items' => $this->mapCategoryItems($items, $section, $locale),
                'allow_create' => (bool) ($section['allow_create'] ?? true),
            ];
        }

        return view('cms::admin.category.index', [
            'sections' => $sections,
        ]);
    }

    public function create(): View
    {
        $category = new Category();

        return view('cms::admin.category.form', [
            'category' => $category,
            'fields' => config('cms.admin.category.default_form_fields', []),
            'locales' => CmsLocales::all(),
            'types' => CmsTypeResolver::categoryTypes(),
            'parents' => Category::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $service = new CategorySaveService();
        $request->validate($service->validationRules());

        $prepared = $service->prepareTranslatableData($request->all());
        $data = $prepared['data'];

        if (!$prepared['hasName']) {
            return redirect()->back()
                ->withErrors(['error' => 'The name cannot be empty. Provide a name in at least one language.'])
                ->withInput();
        }

        $data['type'] = $request->input('type', CmsTypeResolver::categoryTypes()[0] ?? 'default');
        $data['status'] = $request->boolean('status') ? 1 : 0;
        $data['parent_uuid'] = $request->input('parent_uuid');

        $category = Category::create($data);

        $service->handleMedia($category, $request, false);

        if ($configKey = $request->input('config_key')) {
            CmsConfigStore::set($configKey, $category->uuid);
        }

        return redirect()
            ->route($this->adminRoute('category.index'))
            ->with('status', 'Category created.');
    }

    public function edit(Category $category, Request $request): View
    {
        return view('cms::admin.category.form', [
            'category' => $category,
            'fields' => config('cms.admin.category.default_form_fields', []),
            'locales' => CmsLocales::all(),
            'types' => CmsTypeResolver::categoryTypes(),
            'parents' => Category::all(),
            'configKey' => $request->input('config_key'),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $service = new CategorySaveService();
        $request->validate($service->validationRules());

        $prepared = $service->prepareTranslatableData($request->all());
        $data = $prepared['data'];

        if (!$prepared['hasName']) {
            return redirect()->back()
                ->withErrors(['error' => 'The name cannot be empty. Provide a name in at least one language.'])
                ->withInput();
        }

        $data['type'] = $this->normalizeTypeValue($request->input('type', $category->type));
        $data['status'] = $request->boolean('status') ? 1 : 0;
        $data['parent_uuid'] = $request->input('parent_uuid');

        $category->update($data);

        $service->handleMedia($category, $request, true);

        if ($configKey = $request->input('config_key')) {
            CmsConfigStore::set($configKey, $category->uuid);
        }

        return redirect()
            ->route($this->adminRoute('category.index'))
            ->with('status', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()
            ->route($this->adminRoute('category.index'))
            ->with('status', 'Category deleted.');
    }

    private function mapCategoryItems(array $items, array $section, string $locale): array
    {
        $columns = $section['columns'] ?? config('cms.admin.category.default_columns', []);

        return collect($items)->map(function (array $item) use ($columns, $locale, $section) {
            /** @var Category|null $model */
            $model = $item['model'] ?? null;
            $translation = $model ? $model->translate($locale) : null;

            $columnValues = [];
            foreach ($columns as $column) {
                $columnValues[$column] = $this->columnValue($column, $model, $translation, $item);
            }

            $query = [
                'section' => $section['key'] ?? null,
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
                'create_url' => $model ? null : route($this->adminRoute('category.create')),
                'delete_url' => $model ? route($this->adminRoute('category.destroy'), $model) : null,
            ];
        })->all();
    }

    private function columnValue(string $column, ?Category $model, mixed $translation, array $item): string
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
            'parent_uuid' => (string) ($model->parent_uuid ?? '-'),
            'config_key' => $item['config_key'] ?? '-',
            default => (string) ($model->{$column} ?? '-'),
        };
    }

    private function editUrl(Category $category, array $query): string
    {
        $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');
        $url = route($this->adminRoute('category.edit'), $category);

        return $query ? $url . '?' . http_build_query($query) : $url;
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

    private function adminRoute(string $name): string
    {
        $prefix = rtrim((string) config('cms.admin.route_name_prefix', 'cms.'), '.');
        return $prefix === '' ? $name : $prefix . '.' . $name;
    }
}
