<?php

use Dominservice\LaravelCms\Http\Controllers\Admin\CategoryContentController;
use Dominservice\LaravelCms\Http\Controllers\Admin\CategoryController;
use Dominservice\LaravelCms\Http\Controllers\Admin\PageContentController;
use Illuminate\Support\Facades\Route;

$prefix = (string) config('cms.admin.prefix', 'cms');
$namePrefix = (string) config('cms.admin.route_name_prefix', 'cms.');
if ($namePrefix !== '' && !str_ends_with($namePrefix, '.')) {
    $namePrefix .= '.';
}

Route::prefix($prefix)
    ->middleware(config('cms.admin.middleware', ['web']))
    ->name($namePrefix)
    ->group(function () {
        Route::get('pages', [PageContentController::class, 'index'])->name('pages.index');
        Route::get('pages/{pageKey}/edit', [PageContentController::class, 'editPage'])->name('pages.edit');
        Route::put('pages/{pageKey}', [PageContentController::class, 'updatePage'])->name('pages.update');
        Route::get('pages/{pageKey}/sections/{sectionKey}', [PageContentController::class, 'editSection'])->name('pages.sections.edit');
        Route::put('pages/{pageKey}/sections/{sectionKey}', [PageContentController::class, 'updateSection'])->name('pages.sections.update');

        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::get('categories/{category}/contents', [CategoryContentController::class, 'index'])->name('categories.contents.index');
        Route::get('categories/{category}/contents/create', [CategoryContentController::class, 'create'])->name('categories.contents.create');
        Route::post('categories/{category}/contents', [CategoryContentController::class, 'store'])->name('categories.contents.store');
        Route::get('categories/{category}/contents/{content}/edit', [CategoryContentController::class, 'edit'])->name('categories.contents.edit');
        Route::put('categories/{category}/contents/{content}', [CategoryContentController::class, 'update'])->name('categories.contents.update');
        Route::delete('categories/{category}/contents/{content}', [CategoryContentController::class, 'destroy'])->name('categories.contents.destroy');
    });
