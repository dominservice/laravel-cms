<?php

namespace Dominservice\LaravelCms\Http\Livewire\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsSectionResolver;
use Illuminate\View\View;
use Livewire\Component;

class CategoryIndex extends Component
{
    public array $sections = [];

    public function mount(): void
    {
        $this->loadSections();
    }

    public function deleteCategory(string $uuid): void
    {
        $category = Category::find($uuid);
        if ($category) {
            $category->delete();
            session()->flash('status', 'Category deleted.');
        }

        $this->loadSections();
    }

    public function render(): View
    {
        return view('cms::livewire.admin.category.index')
            ->extends('cms::layouts.admin')
            ->section('content');
    }

    private function loadSections(): void
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
                'create_url' => $this->sectionCreateUrl($section),
            ];
        }

        $this->sections = $sections;
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
            if (!empty($section['type_explicit'])) {
                $query['type'] = $section['type'] ?? null;
            }

            return [
                'key' => $item['key'] ?? null,
                'label' => $item['label'] ?? null,
                'config_key' => $item['config_key'] ?? null,
                'model' => $model,
                'columns' => $columnValues,
                'edit_url' => $model ? $this->editUrl($model, $query) : null,
                'create_url' => $model ? null : $this->itemCreateUrl($section, $item),
                'contents_url' => $model ? route($this->adminRoute('category.contents'), $model) : null,
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
            'type' => $this->normalizeTypeValue($model->type),
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

    private function sectionCreateUrl(array $section): ?string
    {
        if (empty($section['allow_create'])) {
            return null;
        }

        $params = [];
        if (!empty($section['group_key'])) {
            $params['section'] = $section['key'];
        }
        if (!empty($section['config_key'])) {
            $params['config_key'] = $section['config_key'];
        }
        if (!empty($section['type_explicit'])) {
            $params['type'] = $section['type'] ?? null;
        }

        return route($this->adminRoute('category.create'), array_filter($params, static fn ($value) => $value !== null && $value !== ''));
    }

    private function itemCreateUrl(array $section, array $item): string
    {
        $params = [];
        if (!empty($section['group_key'])) {
            $params['section'] = $section['key'];
        }
        if (!empty($item['config_key'])) {
            $params['config_key'] = $item['config_key'];
        }
        if (!empty($section['type_explicit'])) {
            $params['type'] = $section['type'] ?? null;
        }

        return route($this->adminRoute('category.create'), array_filter($params, static fn ($value) => $value !== null && $value !== ''));
    }

    private function adminRoute(string $name): string
    {
        $prefix = rtrim((string) config('cms.admin.route_name_prefix', 'cms.'), '.');
        return $prefix === '' ? $name : $prefix . '.' . $name;
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
}
