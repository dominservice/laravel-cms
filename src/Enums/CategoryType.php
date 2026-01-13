<?php

namespace Dominservice\LaravelCms\Enums;

enum CategoryType: string
{
    case Default = 'default';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
