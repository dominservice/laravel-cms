<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CmsMediaMigrateV4Test extends TestCase
{
    public function test_command_succeeds_when_no_legacy_tables_exist(): void
    {
        $this->artisan('cms:media:migrate-v4')
            ->expectsOutputToContain('Brak tabel v2/v3')
            ->assertSuccessful();
    }

    public function test_dry_run_reports_rows_without_dropping_legacy_tables(): void
    {
        Schema::create('cms_contents', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->softDeletes();
        });
        Schema::create('cms_content_videos', function (Blueprint $table): void {
            $table->id();
            $table->uuid('content_uuid');
            $table->string('path')->nullable();
            $table->string('rendition')->nullable();
        });

        DB::table('cms_contents')->insert(['uuid' => 'content-1']);
        DB::table('cms_content_videos')->insert([
            ['content_uuid' => 'content-1', 'path' => 'legacy/video.mp4', 'rendition' => 'hd'],
            ['content_uuid' => 'missing-content', 'path' => 'legacy/missing.mp4', 'rendition' => 'sd'],
        ]);

        $this->artisan('cms:media:migrate-v4', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY] video content-1 legacy/video.mp4 (hd)')
            ->expectsOutputToContain('Brak Content uuid=missing-content')
            ->expectsOutputToContain('[DRY RUN]')
            ->assertSuccessful();

        self::assertTrue(Schema::hasTable('cms_content_videos'));
        self::assertSame(2, DB::table('cms_content_videos')->count());
    }
}
