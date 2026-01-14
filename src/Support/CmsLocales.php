<?php

namespace Dominservice\LaravelCms\Support;

class CmsLocales
{
    public static function all(): array
    {
        $locales = config('translatable.locales');
        if (is_array($locales) && $locales !== []) {
            return $locales;
        }

        $locales = config('data_locale_parser.allowed_locales');
        if (is_array($locales) && $locales !== []) {
            return $locales;
        }

        return [config('app.locale', 'en')];
    }

    public static function default(): string
    {
        $locales = self::all();
        return $locales[0] ?? config('app.locale', 'en');
    }
}
