<?php

namespace Dominservice\LaravelCms\Support;

use Illuminate\Support\Arr;

class CmsLocales
{
    public static function available(): array
    {
        $configured = config('cms.locales');
        if (is_array($configured) && $configured !== []) {
            return array_values($configured);
        }

        $parserLocales = config('data_locale_parser.allowed_locales');
        if (is_array($parserLocales) && $parserLocales !== []) {
            return array_values($parserLocales);
        }

        $translatableLocales = config('translatable.locales');
        if (is_array($translatableLocales) && $translatableLocales !== []) {
            return array_values($translatableLocales);
        }

        return [config('app.locale', 'en')];
    }

    public static function default(): string
    {
        return Arr::first(self::available()) ?? config('app.locale', 'en');
    }
}
