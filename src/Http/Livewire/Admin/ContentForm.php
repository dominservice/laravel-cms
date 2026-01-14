<?php

namespace Dominservice\LaravelCms\Http\Livewire\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Services\ContentSaveService;
use Dominservice\LaravelCms\Support\CmsConfigStore;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsSectionResolver;
use Dominservice\LaravelCms\Support\CmsTypeResolver;
use Illuminate\Http\Request;
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
        if ($queryType) {
            $this->fixedType = (string) $queryType;
        }
        if (!$this->configKey) {
            $this->configKey = $blockConfig['config_key'] ?? $sectionConfig['config_key'] ?? null;
        }

        $this->fields = $sectionConfig
            ? CmsSectionResolver::formFields($sectionConfig, 'content')
            : config('cms.admin.content.default_form_fields', []);
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
            $this->addError('translations', 'The name cannot be empty. Provide a name in at least one language.');
            return;
        }

        $data['type'] = $type;
        $data['status'] = $this->status ? 1 : 0;
        $data['is_nofollow'] = $this->is_nofollow ? 1 : 0;
        $data['external_url'] = $this->external_url;
        $data['parent_uuid'] = $this->content->parent_uuid;

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

        session()->flash('status', $this->content->wasRecentlyCreated ? 'Content created.' : 'Content updated.');
        $this->redirect(route($this->adminRoute('content.index')), navigate: true);
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
