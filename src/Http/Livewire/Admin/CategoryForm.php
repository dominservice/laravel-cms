<?php

namespace Dominservice\LaravelCms\Http\Livewire\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Services\CategorySaveService;
use Dominservice\LaravelCms\Support\CmsConfigStore;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsTypeResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class CategoryForm extends Component
{
    use WithFileUploads;

    public Category $category;
    public array $fields = [];
    public array $locales = [];
    public array $types = [];
    public array $parents = [];
    public array $translations = [];
    public ?string $configKey = null;
    public ?string $type = null;
    public ?string $parent_uuid = null;
    public bool $status = false;
    public ?string $media_type = null;
    public $avatar;
    public $avatar_small;
    public $poster;
    public $small_poster;

    public function mount(?Category $category = null): void
    {
        $this->category = $category ?? new Category();
        $this->fields = config('cms.admin.category.default_form_fields', []);
        $this->locales = CmsLocales::all();
        $this->types = CmsTypeResolver::categoryTypes();
        $this->parents = Category::all()
            ->map(fn (Category $parent) => [
                'uuid' => $parent->uuid,
                'name' => $parent->translate(CmsLocales::default())?->name ?? $parent->uuid,
            ])
            ->all();

        $this->configKey = request()->query('config_key');
        $this->type = $this->category->type ? (string) $this->category->type : ($this->types[0] ?? 'default');
        $this->parent_uuid = $this->category->parent_uuid;
        $this->status = (bool) $this->category->status;
        $this->media_type = $this->category->video_path ? 'video' : 'image';

        foreach ($this->locales as $locale) {
            $translation = $this->category->translate($locale);
            $this->translations[$locale] = [
                'name' => $translation?->name ?? '',
                'description' => $translation?->description ?? '',
                'meta_title' => $translation?->meta_title ?? '',
                'meta_keywords' => $translation?->meta_keywords ?? '',
                'meta_description' => $translation?->meta_description ?? '',
            ];
        }
    }

    public function save(): void
    {
        $service = new CategorySaveService();
        $this->validate($service->validationRules());

        $prepared = $service->prepareTranslatableData($this->translations);
        $data = $prepared['data'];

        if (!$prepared['hasName']) {
            $this->addError('translations', 'The name cannot be empty. Provide a name in at least one language.');
            return;
        }

        $data['type'] = $this->type ?: ($this->types[0] ?? 'default');
        $data['status'] = $this->status ? 1 : 0;
        $data['parent_uuid'] = $this->parent_uuid;

        if ($this->category->uuid) {
            $this->category->update($data);
        } else {
            $this->category = Category::create($data);
        }

        $service->handleMedia($this->category, $this->buildMediaRequest(), !$this->category->wasRecentlyCreated);

        if ($this->configKey) {
            CmsConfigStore::set($this->configKey, $this->category->uuid);
        }

        session()->flash('status', $this->category->wasRecentlyCreated ? 'Category created.' : 'Category updated.');
        $this->redirect(route($this->adminRoute('category.index')), navigate: true);
    }

    public function render(): View
    {
        return view('cms::livewire.admin.category.form')
            ->extends('cms::layouts.admin')
            ->section('content');
    }

    private function buildMediaRequest(): Request
    {
        $payload = array_filter([
            'media_type' => $this->media_type,
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

    private function adminRoute(string $name): string
    {
        $prefix = rtrim((string) config('cms.admin.route_name_prefix', 'cms.'), '.');
        return $prefix === '' ? $name : $prefix . '.' . $name;
    }
}
