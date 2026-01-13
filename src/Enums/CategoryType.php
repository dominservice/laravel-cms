<?php

namespace Dominservice\LaravelCms\Enums;

use App\Enums\Concerns\HasLabel;

enum CategoryType: string
{
    use HasLabel;

    case Default = 'default';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
