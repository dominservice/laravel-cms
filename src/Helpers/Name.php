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

    public static function generateVideoName(string $prefix = null, string $extension = 'mp4'): string
    {
        $unique = Str::ulid()->toBase32();
        $base = trim((string)($prefix ?? ''), '_-');
        $ext = ltrim(strtolower($extension), '.');
        // allow common video extensions only as a safety measure
        $allowed = ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'];
        if (!in_array($ext, $allowed, true)) {
            $ext = 'mp4';
        }
        $name = ($base !== '' ? $base . '-' : '') . $unique;
        return $name . '.' . $ext;
    }
}