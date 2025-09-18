<?php

namespace Dominservice\LaravelCms\Helpers;

use Illuminate\Support\Str;

class Name
{
    public static function generateImageName(string $prefix = null): string
    {
        $unique = Str::ulid()->toBase32();
        $base = trim((string)($prefix ?? ''), '_-');
        $name = ($base !== '' ? $base . '-' : '') . $unique;
        return $name . '.' . config('cms.avatar.extension');
    }
}