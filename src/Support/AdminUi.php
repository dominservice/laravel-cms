<?php

namespace Dominservice\LaravelCms\Support;

use Illuminate\Support\Arr;

class AdminUi
{
    public static function classes(): array
    {
        $theme = (string) config('cms.admin.ui.theme', 'bootstrap');
        $presets = (array) config('cms.admin.ui.presets', []);
        $classes = (array) ($presets[$theme] ?? []);
        $overrides = (array) config('cms.admin.ui.classes', []);

        return array_replace($classes, $overrides);
    }

    public static function get(string $key, string $fallback = ''): string
    {
        return (string) Arr::get(self::classes(), $key, $fallback);
    }
}
