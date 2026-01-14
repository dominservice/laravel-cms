<?php

namespace Dominservice\LaravelCms\Http\Livewire\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsSectionResolver;
use Illuminate\View\View;
use Livewire\Component;

class ContentIndex extends Component
{
    public array $sections = [];
    public ?Category $category = null;

    public function mount(?Category $category = null): void
    {
        $this->category = $category;
        $this->loadSections();
    }

    public function deleteContent(string $uuid): void
    {
        $content = Content::find($uuid);
        if ($content) {
            $content->delete();
            session()->flash('status', 'Content deleted.');
        }

        $this->loadSections();
    }

    public function render(): View
    {
        return view('cms::livewire.admin.content.index')
            ->extends('cms::layouts.admin')
            ->section('content');
    }

    private function loadSections(): void
    {
        $locale = CmsLocales::default();
        $sections = [];

        if ($this->category) {
            $items = $this->category->contents()->get()->map(function (Content $content) {
                return [
                    'key' => $content->uuid,
                    'label' => $content->uuid,
                    'config_key' => null,
                    'model' => $content,
                ];
            })->all();

            $sections[] = [
                'key' => 'category-' . $this->category->uuid,
                'label' => 'Category: ' . ($this->category->translate($locale)?->name ?? $this->category->uuid),
                'columns' => config('cms.admin.content.default_columns', []),
                'items' => $this->mapContentItems($items, ['key' => 'category'], $locale),
                'blocks' => [],
                'allow_create' => false,
            ];

            $this->sections = $sections;
            return;
        }

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
                'create_url' => $this->sectionCreateUrl($section),
            ];
        }

        $this->sections = $sections;
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
                'create_url' => $model ? null : $this->itemCreateUrl($section, $item, $isBlock),
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
            'type' => $this->normalizeTypeValue($model->type),
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

    private function sectionCreateUrl(array $section): ?string
    {
        if (empty($section['allow_create'])) {
            return null;
        }

        $params = ['section' => $section['key'] ?? null];
        if (!empty($section['config_key'])) {
            $params['config_key'] = $section['config_key'];
        }
        if (!empty($section['type_explicit'])) {
            $params['type'] = $section['type'] ?? null;
        }

        return route($this->adminRoute('content.section.create'), array_filter($params, static fn ($value) => $value !== null && $value !== ''));
    }

    private function itemCreateUrl(array $section, array $item, bool $isBlock): ?string
    {
        if ($isBlock) {
            return $this->createUrl($section['key'] ?? null, $item['key'] ?? null);
        }

        $params = [
            'section' => $section['key'] ?? null,
        ];
        if (!empty($item['config_key'])) {
            $params['config_key'] = $item['config_key'];
        }
        if (!empty($section['type_explicit'])) {
            $params['type'] = $section['type'] ?? null;
        }
        if (!empty($item['key'])) {
            $params['config_handle'] = $item['key'];
        }

        return route($this->adminRoute('content.section.create'), array_filter($params, static fn ($value) => $value !== null && $value !== ''));
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
