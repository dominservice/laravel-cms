<?php

namespace Dominservice\LaravelCms\Http\Controllers\Frontend;

use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Support\CmsSectionResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Request $request): View
    {
        $pageKey = (string) $request->route('page_key', '');
        $pageConfig = (array) config('cms.routes.pages.' . $pageKey, []);
        abort_if($pageKey === '' || $pageConfig === [], 404);

        $contentKey = (string) ($pageConfig['content_key'] ?? "cms.default_pages.{$pageKey}.page_uuid");
        $contentUuid = $contentKey !== '' ? config($contentKey) : null;
        $content = $contentUuid ? Content::find($contentUuid) : null;
        abort_if(!$content, 404);

        $section = CmsSectionResolver::contentSection($pageKey);
        $blocks = $section ? CmsSectionResolver::blocksForContentSection($section) : [];

        $view = (string) ($pageConfig['view'] ?? config('cms.routes.page_view', 'cms::frontend.page'));

        return view($view, [
            'content' => $content,
            'pageKey' => $pageKey,
            'pageConfig' => $pageConfig,
            'blocks' => $blocks,
        ]);
    }
}
