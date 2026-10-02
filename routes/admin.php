<?php

use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryIndex;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentIndex;
use Dominservice\LaravelCms\Http\Livewire\Admin\SettingsDashboard;
use Dominservice\LaravelCms\Support\AdminComponentResolver;
use Illuminate\Support\Facades\Route;

if (! config('cms.admin.enabled', true)) {
    return;
}

$prefix = (string) config('cms.admin.prefix', 'cms');
$namePrefix = (string) config('cms.admin.route_name_prefix', 'cms.');
$namePrefix = $namePrefix === '' ? '' : rtrim($namePrefix, '.').'.';
$middleware = (array) config('cms.admin.middleware', ['web', 'auth']);
$contentIndex = AdminComponentResolver::resolve('content_index', ContentIndex::class);
$contentForm = AdminComponentResolver::resolve('content_form', ContentForm::class);
$categoryIndex = AdminComponentResolver::resolve('category_index', CategoryIndex::class);
$categoryForm = AdminComponentResolver::resolve('category_form', CategoryForm::class);
$settingsDashboard = AdminComponentResolver::resolve('settings_dashboard', SettingsDashboard::class);

Route::group([
    'prefix' => $prefix,
    'as' => $namePrefix,
    'middleware' => $middleware,
], function () use ($contentIndex, $contentForm, $categoryIndex, $categoryForm, $settingsDashboard) {
    if (config('cms.admin.settings.enabled', true)) {
        $settingsRoute = (string) config('cms.admin.settings.route', 'settings');
        Route::get($settingsRoute, $settingsDashboard)->name('settings');
    }

    Route::get('/', function () {
        $prefix = (string) config('cms.admin.route_name_prefix', 'cms.');
        $prefix = $prefix === '' ? '' : rtrim($prefix, '.').'.';
        $landing = (string) config('cms.admin.landing', 'settings');

        return match ($landing) {
            'settings' => config('cms.admin.settings.enabled', true)
                ? redirect()->route($prefix.'settings')
                : redirect()->route($prefix.'content.index'),
            'category', 'category.index' => redirect()->route($prefix.'category.index'),
            default => redirect()->route($prefix.'content.index'),
        };
    })->name('index');

    Route::get('content', $contentIndex)->name('content.index');
    Route::get('content/section/{section}/create', $contentForm)->name('content.section.create');
    Route::get('content/section/{section}/block/{blockKey}/create', $contentForm)->name('content.block.create');
    Route::get('content/{content}/edit', $contentForm)->name('content.edit');

    Route::get('category', $categoryIndex)->name('category.index');
    Route::get('category/create', $categoryForm)->name('category.create');
    Route::get('category/{category}/edit', $categoryForm)->name('category.edit');
    Route::get('category/{category}/contents', $contentIndex)->name('category.contents');
});
