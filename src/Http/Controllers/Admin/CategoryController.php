<?php

namespace Dominservice\LaravelCms\Http\Controllers\Admin;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsTypeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CategoryController extends Controller
{
    private const TRANSLATION_FIELDS = [
        'name',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ];

    public function index(): View
    {
        $categories = Category::query()->with('translations')->orderBy('created_at')->get();

        return view('cms::admin.categories.index', [
            'categories' => $categories,
            'locales' => CmsLocales::available(),
        ]);
    }

    public function create(): View
    {
        return view('cms::admin.categories.edit', [
            'category' => new Category(),
            'locales' => CmsLocales::available(),
            'types' => CmsTypeResolver::categoryTypes(),
            'isNew' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $defaultLocale = CmsLocales::default();
        $request->validate([
            "translations.{$defaultLocale}.name" => 'required|string|max:255',
        ]);

        $category = new Category();
        $category->type = (string) $request->input('type', $this->defaultType());
        $category->status = (int) $request->input('status', 0);
        $category->fill($this->collectTranslations($request));
        $category->save();

        return redirect()
            ->route($this->adminRoute('categories.edit'), $category)
            ->with('status', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('cms::admin.categories.edit', [
            'category' => $category,
            'locales' => CmsLocales::available(),
            'types' => CmsTypeResolver::categoryTypes(),
            'isNew' => false,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $defaultLocale = CmsLocales::default();
        $request->validate([
            "translations.{$defaultLocale}.name" => 'required|string|max:255',
        ]);

        $category->type = (string) $request->input('type', $this->defaultType());
        $category->status = (int) $request->input('status', 0);
        $category->fill($this->collectTranslations($request));
        $category->save();

        return redirect()
            ->route($this->adminRoute('categories.edit'), $category)
            ->with('status', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()
            ->route($this->adminRoute('categories.index'))
            ->with('status', 'Category deleted.');
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

    private function defaultType(): string
    {
        $types = CmsTypeResolver::categoryTypes();
        return $types[0] ?? 'default';
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
