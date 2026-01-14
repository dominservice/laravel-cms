<?php

namespace Dominservice\LaravelCms\Enums;

enum ContentType: string
{
    case Page = 'page';
    case Block = 'block';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
