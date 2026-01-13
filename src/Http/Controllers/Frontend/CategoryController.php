<?php

namespace Dominservice\LaravelCms\Http\Controllers\Frontend;

use Dominservice\LaravelCms\Models\Category;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        $category = Category::query()
            ->whereHas('translations', function ($query) use ($slug, $locale) {
                $query->where('slug', $slug)->where('locale', $locale);
            })
            ->firstOrFail();

        $contents = $category->contents()->with('translations')->get();

        return view(config('cms.views.frontend.category', 'cms::frontend.category'), [
            'category' => $category,
            'contents' => $contents,
        ]);
    }
}
