<?php

namespace Dominservice\LaravelCms\Support;

class CmsRouteHelper
{
    public static function pageSlug(string $pageKey, array $pageConfig, ?string $locale = null): string
    {
        if (array_key_exists('slug', $pageConfig)) {
            return self::resolveLocalizedValue($pageConfig['slug'], $locale);
        }

        $useTranslated = (bool) ($pageConfig['translated'] ?? config('cms.routes.translated_slugs', false));
        if ($useTranslated) {
            return self::translateKey($pageKey, $locale);
        }

        return $pageKey;
    }

    public static function categoryPrefix(?string $locale = null): string
    {
        $prefix = config('cms.routes.category.prefix', 'category');
        return self::resolveLocalizedValue($prefix, $locale);
    }

    public static function resolveLocalizedValue(string|array|null $value, ?string $locale = null): string
    {
        if (is_array($value)) {
            $default = CmsLocales::default();
            return (string) ($value[$locale] ?? $value[$default] ?? reset($value) ?? '');
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
