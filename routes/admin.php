<?php

use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryIndex;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentIndex;
use Illuminate\Support\Facades\Route;

if (!config('cms.admin.enabled', true)) {
    return;
}

$prefix = (string) config('cms.admin.prefix', 'cms');
$namePrefix = (string) config('cms.admin.route_name_prefix', 'cms.');
$namePrefix = $namePrefix === '' ? '' : rtrim($namePrefix, '.') . '.';
$middleware = (array) config('cms.admin.middleware', ['web', 'auth']);

Route::group([
    'prefix' => $prefix,
    'as' => $namePrefix,
    'middleware' => $middleware,
], function () {
    Route::get('/', function () {
        $prefix = (string) config('cms.admin.route_name_prefix', 'cms.');
        $prefix = $prefix === '' ? '' : rtrim($prefix, '.') . '.';
        return redirect()->route($prefix . 'content.index');
    })->name('index');

    Route::get('content', ContentIndex::class)->name('content.index');
    Route::get('content/section/{section}/create', ContentForm::class)->name('content.section.create');
    Route::get('content/section/{section}/block/{blockKey}/create', ContentForm::class)->name('content.block.create');
    Route::get('content/{content}/edit', ContentForm::class)->name('content.edit');

    Route::get('category', CategoryIndex::class)->name('category.index');
    Route::get('category/create', CategoryForm::class)->name('category.create');
    Route::get('category/{category}/edit', CategoryForm::class)->name('category.edit');
    Route::get('category/{category}/contents', ContentIndex::class)->name('category.contents');
});
