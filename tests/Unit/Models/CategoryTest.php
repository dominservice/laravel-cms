<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Dominservice\LaravelCms\Enums\CategoryType;
use Dominservice\LaravelCms\Models\Category;
use Tests\TestCase;

final class CategoryTest extends TestCase
{
    public function test_model_contract_matches_the_configured_cms_schema(): void
    {
        $category = new Category;

        self::assertSame('cms_categories', $category->getTable());
        self::assertSame('parent_uuid', $category->getParentIdName());
        self::assertSame(CategoryType::class, $category->getCasts()['type']);
        self::assertContains('_lft', $category->getFillable());
        self::assertContains('meta_description', $category->translatedAttributes);
    }

    public function test_file_relations_use_the_category_uuid_contract(): void
    {
        $category = new Category;

        self::assertSame('category_uuid', $category->files()->getForeignKeyName());
        self::assertSame('uuid', $category->files()->getLocalKeyName());
        self::assertSame('category_uuid', $category->avatarFile()->getForeignKeyName());
        self::assertSame('category_uuid', $category->video()->getOwnerKeyName());
        self::assertSame('category_uuid', $category->videoPoster()->getOwnerKeyName());
    }
}
