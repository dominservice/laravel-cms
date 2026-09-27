<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Dominservice\LaravelCms\Enums\ContentType;
use Dominservice\LaravelCms\Models\Content;
use Tests\TestCase;

final class ContentTest extends TestCase
{
    public function test_model_contract_matches_the_configured_cms_schema(): void
    {
        $content = new Content;

        self::assertSame('cms_contents', $content->getTable());
        self::assertSame(ContentType::class, $content->getCasts()['type']);
        self::assertSame('object', $content->getCasts()['meta']);
        self::assertContains('parent_uuid', $content->getFillable());
        self::assertContains('meta_description', $content->translatedAttributes);
    }

    public function test_external_url_is_trimmed_and_empty_values_become_null(): void
    {
        $content = new Content;

        $content->external_url = '  https://example.com/page  ';
        self::assertSame('https://example.com/page', $content->external_url);

        $content->external_url = '   ';
        self::assertNull($content->external_url);
    }

    public function test_self_referencing_relations_use_parent_uuid(): void
    {
        $content = new Content;

        self::assertSame('parent_uuid', $content->parent()->getForeignKeyName());
        self::assertSame('uuid', $content->parent()->getOwnerKeyName());
        self::assertSame('parent_uuid', $content->children()->getForeignKeyName());
        self::assertSame('uuid', $content->children()->getLocalKeyName());
    }

    public function test_file_relations_use_the_content_uuid_contract(): void
    {
        $content = new Content;

        self::assertSame('content_uuid', $content->files()->getForeignKeyName());
        self::assertSame('uuid', $content->files()->getLocalKeyName());
        self::assertSame('content_uuid', $content->avatarFile()->getForeignKeyName());
    }
}
