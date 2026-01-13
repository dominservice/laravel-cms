<?php

namespace Dominservice\LaravelCms\Http\Controllers\Admin;

use Dominservice\LaravelCms\Support\CmsContentStore;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class PageContentController extends Controller
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

    public function index(): View
    {
        $pages = CmsStructure::pages();

        return view('cms::admin.pages.index', [
            'pages' => $pages,
        ]);
    }

    public function editPage(string $pageKey): View
    {
        $pageConfig = CmsStructure::page($pageKey);
        abort_if(!$pageConfig, 404);

        $content = CmsContentStore::findOrCreatePage($pageKey);

        return view('cms::admin.pages.edit', [
            'pageKey' => $pageKey,
            'pageConfig' => $pageConfig,
            'content' => $content,
            'locales' => CmsLocales::available(),
        ]);
    }

    public function updatePage(Request $request, string $pageKey): RedirectResponse
    {
        $pageConfig = CmsStructure::page($pageKey);
        abort_if(!$pageConfig, 404);

        $defaultLocale = CmsLocales::default();
        $request->validate([
            "translations.{$defaultLocale}.name" => 'required|string|max:255',
        ]);

        $content = CmsContentStore::findOrCreatePage($pageKey);
        $content->type = CmsStructure::pageType($pageKey);
        $content->status = (int) $request->input('status', 0);
        $content->is_nofollow = (bool) $request->input('is_nofollow', false);
        $content->external_url = $request->input('external_url');

        $content->meta = $this->mergeMeta($content->meta, [
            'page_key' => $pageKey,
        ]);

        $content->fill($this->collectTranslations($request));
        $content->save();

        return redirect()
            ->route($this->adminRoute('pages.edit'), ['pageKey' => $pageKey])
            ->with('status', 'Page content updated.');
    }

    public function editSection(string $pageKey, string $sectionKey): View
    {
        $pageConfig = CmsStructure::page($pageKey);
        $sections = CmsStructure::sections($pageKey);
        abort_if(!$pageConfig || !isset($sections[$sectionKey]), 404);

        $page = CmsContentStore::findOrCreatePage($pageKey);
        $content = CmsContentStore::findOrCreateSection($page, $sectionKey, $pageKey);

        return view('cms::admin.pages.section-edit', [
            'pageKey' => $pageKey,
            'sectionKey' => $sectionKey,
            'sectionConfig' => $sections[$sectionKey],
            'content' => $content,
            'locales' => CmsLocales::available(),
        ]);
    }

    public function updateSection(Request $request, string $pageKey, string $sectionKey): RedirectResponse
    {
        $pageConfig = CmsStructure::page($pageKey);
        $sections = CmsStructure::sections($pageKey);
        abort_if(!$pageConfig || !isset($sections[$sectionKey]), 404);

        $defaultLocale = CmsLocales::default();
        $request->validate([
            "translations.{$defaultLocale}.name" => 'required|string|max:255',
        ]);

        $page = CmsContentStore::findOrCreatePage($pageKey);
        $content = CmsContentStore::findOrCreateSection($page, $sectionKey, $pageKey);

        $content->type = CmsStructure::blockType($pageKey, $sectionKey);
        $content->status = (int) $request->input('status', 0);
        $content->is_nofollow = (bool) $request->input('is_nofollow', false);
        $content->external_url = $request->input('external_url');
        $content->parent_uuid = $page->uuid;

        $content->meta = $this->mergeMeta($content->meta, [
            'page_key' => $pageKey,
            'section_key' => $sectionKey,
        ]);

        $content->fill($this->collectTranslations($request));
        $content->save();

        return redirect()
            ->route($this->adminRoute('pages.sections.edit'), ['pageKey' => $pageKey, 'sectionKey' => $sectionKey])
            ->with('status', 'Section content updated.');
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

    private function mergeMeta(mixed $existing, array $updates): array
    {
        $meta = is_array($existing) ? $existing : (array) $existing;

        foreach ($updates as $key => $value) {
            $meta[$key] = $value;
        }

        return $meta;
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
