<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Models\ContentFile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DynamicAvatarAccessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cms_content_files', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->uuid('content_uuid');
            $table->string('kind');
            $table->string('type')->nullable();
            $table->json('names');
            $table->timestamps();
            $table->softDeletes();
        });

        Storage::fake('avatars');
        config()->set('cms.disks.content', 'avatars');
    }

    public function test_it_resolves_only_an_explicitly_assigned_avatar_file(): void
    {
        Storage::disk('avatars')->put('content/hero.webp', 'image');
        ContentFile::query()->create([
            'content_uuid' => 'content-1',
            'kind' => 'avatar',
            'type' => 'image',
            'names' => ['large' => 'content/hero.webp'],
        ]);

        $content = (new TestableContent)->forceFill(['uuid' => 'content-1']);
        $url = $content->resolveAvatarUrl('large');

        self::assertNotNull($url);
        self::assertStringContainsString('content/hero.webp', $url);
        self::assertStringContainsString('?v=', $url);
    }

    public function test_it_does_not_render_an_unassigned_legacy_avatar(): void
    {
        Storage::disk('avatars')->put('content_content-2.webp', 'legacy-image');
        $content = (new TestableContent)->forceFill(['uuid' => 'content-2']);

        self::assertNull($content->resolveAvatarUrl('large'));
    }

    public function test_it_honours_the_logical_kind_mapping(): void
    {
        config()->set('cms.file_kind_map.avatar', ['portrait']);
        Storage::disk('avatars')->put('content/portrait.webp', 'image');
        ContentFile::query()->create([
            'content_uuid' => 'content-3',
            'kind' => 'portrait',
            'type' => 'image',
            'names' => ['large' => 'content/portrait.webp'],
        ]);

        $content = (new TestableContent)->forceFill(['uuid' => 'content-3']);

        self::assertStringContainsString('content/portrait.webp', (string) $content->resolveAvatarUrl('large'));
    }
}

final class TestableContent extends Content
{
    public function resolveAvatarUrl(string $size): ?string
    {
        return $this->resolveAvatarUrlForSize($size);
    }
}
