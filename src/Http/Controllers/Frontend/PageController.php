<?php

namespace Dominservice\LaravelCms\Http\Controllers\Frontend;

use Dominservice\LaravelCms\Support\CmsContentStore;
use Dominservice\LaravelCms\Support\CmsStructure;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $pageKey): View
    {
        $pageConfig = CmsStructure::page($pageKey);
        abort_if(!$pageConfig, 404);

        $page = CmsContentStore::findPage($pageKey);
        $sections = collect();

        if ($page) {
            $sections = CmsContentStore::sectionsForPage($page)
                ->keyBy(static fn ($content) => data_get($content->meta, 'section_key'));
        }

        return view(config('cms.views.frontend.page', 'cms::frontend.page'), [
            'pageKey' => $pageKey,
            'pageConfig' => $pageConfig,
            'page' => $page,
            'sections' => $sections,
        ]);
    }
}
