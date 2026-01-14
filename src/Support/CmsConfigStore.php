<?php

namespace Dominservice\LaravelCms\Support;

use Dominservice\LaravelConfig\Config;
use Dominservice\LaravelConfig\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CmsConfigStore
{
    public static function set(string $key, mixed $value): void
    {
        $config = new Config();
        $config->set($key, $value);
        $config->buildCache();
    }

    public static function setMany(array $data): void
    {
        if ($data === []) {
            return;
        }

        $config = new Config();
        $config->set($data);
        $config->buildCache();
    }

    public static function generateHandle(string $label, string $groupKey, string $itemKey = 'page_uuid'): string
    {
        $handles = [];
        $configItems = config($groupKey, []);
        if (is_array($configItems)) {
            $handles = array_keys($configItems);
        }

        try {
            $dbKeys = Setting::query()
                ->where('key', 'like', $groupKey . '.%.' . $itemKey)
                ->pluck('key')
                ->all();

            foreach ($dbKeys as $dbKey) {
                if (preg_match('#^' . preg_quote($groupKey, '#') . '\.([^\.]+)\.' . preg_quote($itemKey, '#') . '$#', $dbKey, $matches)) {
                    $handles[] = $matches[1];
                }
            }
        } catch (\Throwable $e) {
            // ignore if settings are unavailable
        }

        $base = Str::slug($label, '_');
        $base = ltrim($base, '_');
        if ($base === '') {
            $base = 'item';
        }
        if (preg_match('/^\d/', $base)) {
            $base = 'p_' . $base;
        }

        $handle = $base;
        $i = 2;
        $handles = array_unique($handles);
        while (in_array($handle, $handles, true)) {
            $handle = $base . '_' . $i;
            $i++;
        }

        return $handle;
    }

    public static function findHandleByUuid(string $groupKey, string $itemKey, string $uuid): ?string
    {
        $configItems = config($groupKey, []);
        if (is_array($configItems)) {
            foreach ($configItems as $handle => $data) {
                if (Arr::get($data, $itemKey) === $uuid) {
                    return (string) $handle;
                }
            }
        }

        try {
            $setting = Setting::query()
                ->where('key', 'like', $groupKey . '.%.' . $itemKey)
                ->where('value', $uuid)
                ->first();
            if ($setting && preg_match('#^' . preg_quote($groupKey, '#') . '\.([^\.]+)\.' . preg_quote($itemKey, '#') . '$#', $setting->key, $matches)) {
                return $matches[1];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }
}
