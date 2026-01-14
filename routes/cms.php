<?php

use Dominservice\LaravelCms\Http\Controllers\Frontend\CategoryController;
use Dominservice\LaravelCms\Http\Controllers\Frontend\PageController;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsRouteHelper;
use Illuminate\Support\Facades\Route;

if (!config('cms.routes.enabled', false)) {
    return;
}

$baseMiddleware = (array) config('cms.routes.middleware', ['web']);
$localeMiddleware = (string) config('cms.routes.locale_middleware', 'language');
$useLocales = (bool) config('cms.routes.use_locales', true);
$useLocalePrefix = (bool) config('cms.routes.use_locale_prefix', true);
$pages = (array) config('cms.routes.pages', []);

$localeMiddlewareAvailable = in_array(
    $localeMiddleware,
    array_keys(app('router')->getMiddleware()),
    true
);

$registerPages = function (?string $locale = null) use ($pages) {
    foreach ($pages as $pageKey => $pageConfig) {
        $pageConfig = (array) $pageConfig;
        $slug = CmsRouteHelper::pageSlug((string) $pageKey, $pageConfig, $locale);
        $slug = trim((string) $slug, '/');
        $uri = $slug === '' ? '/' : $slug;

        $routeName = (string) ($pageConfig['route_name'] ?? $pageKey);
        Route::get($uri, [PageController::class, 'show'])
            ->name($routeName)
            ->defaults('page_key', (string) $pageKey);
    }
};

$registerCategory = function (?string $locale = null) {
    if (!config('cms.routes.category.enabled', true)) {
        return;
    }

    $prefix = CmsRouteHelper::categoryPrefix($locale);
    $prefix = trim((string) $prefix, '/');
    $uri = $prefix === '' ? '{slug}' : $prefix . '/{slug}';
    $routeName = (string) config('cms.routes.category.route_name', 'category.show');

    Route::get($uri, [CategoryController::class, 'show'])
        ->name($routeName);
};

if ($useLocales) {
    $locales = CmsLocales::all();
    if ($useLocalePrefix) {
        foreach ($locales as $locale) {
            $middleware = $baseMiddleware;
            if ($localeMiddlewareAvailable) {
                $middleware[] = $localeMiddleware . ':' . $locale;
            }

            Route::group([
                'prefix' => $locale,
                'as' => $locale . '.',
                'middleware' => $middleware,
            ], function () use ($registerPages, $registerCategory, $locale) {
                $registerPages($locale);
                $registerCategory($locale);
            });
        }
    } else {
        $middleware = $baseMiddleware;
        if ($localeMiddlewareAvailable) {
            $middleware[] = $localeMiddleware;
        }

        Route::group([
            'middleware' => $middleware,
        ], function () use ($registerPages, $registerCategory) {
            $registerPages(CmsLocales::default());
            $registerCategory(CmsLocales::default());
        });
    }
} else {
    Route::group([
        'middleware' => $baseMiddleware,
    ], function () use ($registerPages, $registerCategory) {
        $registerPages(null);
        $registerCategory(null);
    });
}
