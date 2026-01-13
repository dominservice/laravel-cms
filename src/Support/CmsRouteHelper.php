<?php

namespace Dominservice\LaravelCms\Support;

class CmsRouteHelper
{
    public static function pageSlug(string $pageKey, array $pageConfig, ?string $locale = null): string
    {
        $route = $pageConfig['route'] ?? [];

        if (array_key_exists('slug', $route)) {
            return self::resolveLocalizedValue($route['slug'], $locale);
        }

        $useTranslated = (bool) ($route['translated'] ?? config('cms.routes.translated_slugs', false));
        if ($useTranslated) {
            $translationKey = $route['translation_key'] ?? $pageKey;
            return self::translateKey($translationKey, $locale);
        }

        return $pageKey;
    }

    public static function categoryPrefix(?string $locale = null): string
    {
        $category = config('cms.routes.category', []);
        $prefix = $category['prefix'] ?? 'category';

        if (is_array($prefix)) {
            return self::resolveLocalizedValue($prefix, $locale);
        }

        if ((bool) config('cms.routes.translated_slugs', false)) {
            return self::translateKey((string) $prefix, $locale);
        }

        return (string) $prefix;
    }

    public static function joinPath(?string $prefix, ?string $slug): string
    {
        $prefix = trim((string) $prefix, '/');
        $slug = trim((string) $slug, '/');

        if ($prefix === '') {
            return $slug;
        }

        if ($slug === '') {
            return $prefix;
        }

        return $prefix . '/' . $slug;
    }

    public static function resolveLocalizedValue(string|array|null $value, ?string $locale = null): string
    {
        if (is_array($value)) {
            $defaultLocale = CmsLocales::default();
            return (string) ($value[$locale] ?? $value[$defaultLocale] ?? reset($value) ?? '');
        }

        return (string) ($value ?? '');
    }

    public static function translateKey(string $key, ?string $locale = null): string
    {
        $group = config('cms.routes.translation_group', 'routes');
        $translationKey = $group . '.' . $key;
        $translated = __($translationKey, [], $locale);

        if ($translated === $translationKey) {
            return $key;
        }

        return $translated;
    }
}
