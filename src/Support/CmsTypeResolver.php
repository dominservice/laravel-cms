<?php

namespace Dominservice\LaravelCms\Support;

class CmsTypeResolver
{
    public static function contentTypes(): array
    {
        return self::resolve('content');
    }

    public static function categoryTypes(): array
    {
        return self::resolve('category');
    }

    public static function resolve(string $key): array
    {
        $types = config("cms.types.{$key}");

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
