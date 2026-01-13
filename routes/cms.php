<?php

use Dominservice\LaravelCms\Http\Controllers\Frontend\CategoryController;
use Dominservice\LaravelCms\Http\Controllers\Frontend\PageController;
use Dominservice\LaravelCms\Support\CmsLocales;
use Dominservice\LaravelCms\Support\CmsRouteHelper;
use Dominservice\LaravelCms\Support\CmsStructure;
use Illuminate\Support\Facades\Route;

$routesConfig = config('cms.routes', []);
if (!($routesConfig['enabled'] ?? true)) {
    return;
}

$middleware = $routesConfig['middleware'] ?? ['web'];
$useLocales = (bool) ($routesConfig['use_locales'] ?? false);
$useLocalePrefix = (bool) ($routesConfig['use_locale_prefix'] ?? true);
$localeMiddleware = $routesConfig['locale_middleware'] ?? null;

$pageController = $routesConfig['page']['controller'] ?? PageController::class;
$categoryConfig = $routesConfig['category'] ?? [];
$categoryEnabled = (bool) ($categoryConfig['enabled'] ?? true);
$categoryController = $categoryConfig['controller'] ?? CategoryController::class;
$routeName = $categoryConfig['route_name'] ?? 'category.show';

$registerRoutes = function (?string $locale = null) use (
    $pageController,
    $categoryController,
    $categoryEnabled,
    $routeName
): void {
    foreach (CmsStructure::pages() as $pageKey => $pageConfig) {
        $routeConfig = $pageConfig['route'] ?? [];
        $slug = CmsRouteHelper::pageSlug($pageKey, $pageConfig, $locale);
        $prefix = $routeConfig['prefix'] ?? null;
        $path = CmsRouteHelper::joinPath(
            CmsRouteHelper::resolveLocalizedValue($prefix, $locale),
            $slug
        );

        $path = trim($path, '/');

        Route::get($path === '' ? '/' : $path, $pageController)
            ->name(($routeConfig['name'] ?? $pageKey))
            ->defaults('pageKey', $pageKey);
    }

    if ($categoryEnabled) {
        $prefix = CmsRouteHelper::categoryPrefix($locale);
        $path = trim($prefix . '/{slug}', '/');

        Route::get($path, [$categoryController, 'show'])
            ->name($routeName);
    }
};

if ($useLocales) {
    $locales = CmsLocales::available();

    foreach ($locales as $locale) {
        $groupMiddleware = array_filter(array_merge(
            $middleware,
            $localeMiddleware ? [$localeMiddleware] : []
        ));

        $group = Route::middleware($groupMiddleware)->name($locale . '.');

        if ($useLocalePrefix) {
            $group = $group->prefix($locale);
        }

        $group->group(function () use ($registerRoutes, $locale): void {
            $registerRoutes($locale);
        });
    }
} else {
    $groupMiddleware = array_filter(array_merge(
        $middleware,
        $localeMiddleware ? [$localeMiddleware] : []
    ));

    Route::middleware($groupMiddleware)->group(function () use ($registerRoutes): void {
        $registerRoutes(null);
    });
}
