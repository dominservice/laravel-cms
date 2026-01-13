<?php

namespace Dominservice\LaravelCms\Support;

class CmsStructure
{
    public static function pages(): array
    {
        return config('cms.structure.pages', []);
    }

    public static function page(string $pageKey): ?array
    {
        $pages = self::pages();
        return $pages[$pageKey] ?? null;
    }

    public static function sections(string $pageKey): array
    {
        $page = self::page($pageKey);
        if (!is_array($page)) {
            return [];
        }

        return $page['sections'] ?? [];
    }

    public static function pageType(?string $pageKey = null): string
    {
        if ($pageKey) {
            $page = self::page($pageKey);
            if ($page && isset($page['type'])) {
                return (string) $page['type'];
            }
        }

        return (string) config('cms.structure.page_type', 'page');
    }

    public static function blockType(string $pageKey, ?string $sectionKey = null): string
    {
        if ($sectionKey) {
            $section = self::sections($pageKey)[$sectionKey] ?? null;
            if (is_array($section) && isset($section['type'])) {
                return (string) $section['type'];
            }
        }

        return (string) config('cms.structure.block_type', 'block');
    }
}
