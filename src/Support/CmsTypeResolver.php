<?php

namespace Dominservice\LaravelCms\Support;

class CmsTypeResolver
{
    public static function contentTypes(): array
    {
        return self::resolve(config('cms.types.content'));
    }

    public static function categoryTypes(): array
    {
        return self::resolve(config('cms.types.category'));
    }

    public static function resolve(mixed $types): array
    {
        if (is_array($types)) {
            return array_values($types);
        }

        if (is_string($types)) {
            if (enum_exists($types)) {
                return array_map(static fn ($case) => $case->value, $types::cases());
            }

            if (class_exists($types) && method_exists($types, 'values')) {
                return $types::values();
            }
        }

        return [];
    }
}
