<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use Dominservice\LaravelCms\Helpers\Media;
use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class MediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        Storage::fake('videos');
        config()->set('cms.disks.content', 'images');
        config()->set('cms.disks.content_video', 'videos');
        config()->set('cms.disks.category', 'images');
    }

    public function test_it_detects_legacy_avatar_and_video_names_on_configured_disks(): void
    {
        $content = (new Content)->forceFill(['uuid' => 'article-1']);
        Storage::disk('images')->put('content_article-1.webp', 'image');
        Storage::disk('videos')->put('video_article-1.mp4', 'video');

        self::assertSame(
            ['large' => 'content_article-1.webp'],
            TestableMedia::detectLegacyNames($content, 'avatar', 'images')
        );
        self::assertSame(
            ['hd' => 'video_article-1.mp4'],
            TestableMedia::detectLegacyNames($content, 'video_avatar', 'images')
        );
    }

    public function test_it_resolves_entity_configuration_for_content_and_category(): void
    {
        [$contentEntity, $contentDisk, $contentConfig] = TestableMedia::resolveEntityContext(new Content, 'avatar');
        [$categoryEntity, $categoryDisk] = TestableMedia::resolveEntityContext(new Category, 'avatar');

        self::assertSame('content', $contentEntity);
        self::assertSame('images', $contentDisk);
        self::assertArrayHasKey('sizes', $contentConfig);
        self::assertSame('category', $categoryEntity);
        self::assertSame('images', $categoryDisk);
    }

    public function test_it_rejects_models_outside_the_cms_media_contract(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestableMedia::resolveEntityContext(new class extends Model {}, 'avatar');
    }

    public function test_it_merges_replaced_variants_and_returns_only_superseded_files(): void
    {
        [$merged, $deleted] = TestableMedia::mergeNamesWithDeletions(
            ['desktop' => ['large' => 'old-large.webp', 'thumb' => 'old-thumb.webp']],
            ['desktop' => ['large' => 'new-large.webp']]
        );

        self::assertSame([
            'desktop' => ['large' => 'new-large.webp', 'thumb' => 'old-thumb.webp'],
        ], $merged);
        self::assertSame(['old-large.webp'], $deleted);
    }
}

final class TestableMedia extends Media
{
    /**
     * @return array<string, string>
     */
    public static function detectLegacyNames(Model $model, string $kind, string $diskKey): array
    {
        return parent::detectLegacyNames($model, $kind, $diskKey);
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    public static function resolveEntityContext(Model $model, string $kind): array
    {
        return parent::resolveEntityContext($model, $kind);
    }

    /**
     * @param  array<string, mixed>  $existingNames
     * @param  array<string, mixed>  $newNames
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    public static function mergeNamesWithDeletions(array $existingNames, array $newNames): array
    {
        return parent::mergeNamesWithDeletions($existingNames, $newNames);
    }
}
