<?php

namespace Dominservice\LaravelCms\Support;

use Dominservice\LaravelCms\Models\Content;
use Illuminate\Database\Eloquent\Collection;

class CmsContentStore
{
    public static function findPage(string $pageKey): ?Content
    {
        return Content::query()
            ->where('type', CmsStructure::pageType($pageKey))
            ->where('meta->page_key', $pageKey)
            ->first();
    }

    public static function findOrCreatePage(string $pageKey): Content
    {
        $page = self::findPage($pageKey);
        if ($page) {
            return $page;
        }

        return Content::create([
            'type' => CmsStructure::pageType($pageKey),
            'status' => 1,
            'meta' => [
                'page_key' => $pageKey,
            ],
        ]);
    }

    public static function findSection(Content $page, string $sectionKey): ?Content
    {
        return Content::query()
            ->where('parent_uuid', $page->uuid)
            ->where('meta->section_key', $sectionKey)
            ->first();
    }

    public static function findOrCreateSection(Content $page, string $sectionKey, string $pageKey): Content
    {
        $section = self::findSection($page, $sectionKey);
        if ($section) {
            return $section;
        }

        return Content::create([
            'parent_uuid' => $page->uuid,
            'type' => CmsStructure::blockType($pageKey, $sectionKey),
            'status' => 1,
            'meta' => [
                'page_key' => $pageKey,
                'section_key' => $sectionKey,
            ],
        ]);
    }

    public static function sectionsForPage(Content $page): Collection
    {
        return Content::query()
            ->where('parent_uuid', $page->uuid)
            ->get();
    }
}
