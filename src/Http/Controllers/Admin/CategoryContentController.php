<?php

namespace Dominservice\LaravelCms\Http\Controllers\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsTypeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CategoryContentController extends Controller
{
    private const TRANSLATION_FIELDS = [
        'name',
        'sub_name',
        'short_description',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ];

    public function index(Category $category): View
    {
        $contents = $category->contents()->with('translations')->get();

        return view('cms::admin.categories.contents.index', [
            'category' => $category,
            'contents' => $contents,
            'locales' => CmsLocales::available(),
        ]);
    }

    public function create(Category $category): View
    {
        return view('cms::admin.categories.contents.edit', [
            'category' => $category,
            'content' => new Content(),
            'locales' => CmsLocales::available(),
            'types' => CmsTypeResolver::contentTypes(),
            'isNew' => true,
        ]);
    }

    public function store(Request $request, Category $category): RedirectResponse
    {
        $defaultLocale = CmsLocales::default();
        $request->validate([
            "translations.{$defaultLocale}.name" => 'required|string|max:255',
        ]);

        $defaultType = CmsTypeResolver::contentTypes()[0] ?? config('cms.structure.block_type', 'block');

        $content = new Content();
        $content->type = (string) $request->input('type', $defaultType);
        $content->status = (int) $request->input('status', 0);
        $content->is_nofollow = (bool) $request->input('is_nofollow', false);
        $content->external_url = $request->input('external_url');
        $content->fill($this->collectTranslations($request));
        $content->save();

        $content->categories()->syncWithoutDetaching([$category->uuid]);

        return redirect()
            ->route($this->adminRoute('categories.contents.edit'), [$category, $content])
            ->with('status', 'Content created.');
    }

    public function edit(Category $category, Content $content): View
    {
        $this->ensureContentInCategory($category, $content);

        return view('cms::admin.categories.contents.edit', [
            'category' => $category,
            'content' => $content,
            'locales' => CmsLocales::available(),
            'types' => CmsTypeResolver::contentTypes(),
            'isNew' => false,
        ]);
    }

    public function update(Request $request, Category $category, Content $content): RedirectResponse
    {
        $this->ensureContentInCategory($category, $content);

        $defaultLocale = CmsLocales::default();
        $request->validate([
            "translations.{$defaultLocale}.name" => 'required|string|max:255',
        ]);

        $content->type = (string) $request->input('type', $content->type);
        $content->status = (int) $request->input('status', 0);
        $content->is_nofollow = (bool) $request->input('is_nofollow', false);
        $content->external_url = $request->input('external_url');
        $content->fill($this->collectTranslations($request));
        $content->save();

        return redirect()
            ->route($this->adminRoute('categories.contents.edit'), [$category, $content])
            ->with('status', 'Content updated.');
    }

    public function destroy(Category $category, Content $content): RedirectResponse
    {
        $this->ensureContentInCategory($category, $content);
        $content->delete();

        return redirect()
            ->route($this->adminRoute('categories.contents.index'), $category)
            ->with('status', 'Content deleted.');
    }

    private function collectTranslations(Request $request): array
    {
        $translations = [];

        foreach (CmsLocales::available() as $locale) {
            $localeData = $request->input("translations.{$locale}", []);
            if (!is_array($localeData)) {
                continue;
            }

            $filtered = array_intersect_key($localeData, array_flip(self::TRANSLATION_FIELDS));
            if (trim((string) ($filtered['name'] ?? '')) === '') {
                continue;
            }

            $translations[$locale] = $filtered;
        }

        return $translations;
    }

    private function ensureContentInCategory(Category $category, Content $content): void
    {
        if (!$content->categories()->where('category_uuid', $category->uuid)->exists()) {
            abort(404);
        }
    }

    private function adminRoute(string $name): string
    {
        $prefix = (string) config('cms.admin.route_name_prefix', 'cms.');
        if ($prefix !== '' && !str_ends_with($prefix, '.')) {
            $prefix .= '.';
        }

        return $prefix . $name;
    }
}
