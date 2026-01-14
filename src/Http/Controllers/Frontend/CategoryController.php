<?php

namespace Dominservice\LaravelCms\Http\Controllers\Frontend;

use Dominservice\LaravelCms\Models\Category;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(string $slug): View
    {
        $category = Category::whereTranslation('slug', $slug, app()->getLocale())->firstOrFail();
        $contents = $category->contents()->get();

        $view = (string) config('cms.routes.category.view', 'cms::frontend.category');

        return view($view, [
            'category' => $category,
            'contents' => $contents,
        ]);
    }
}
