<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RedirectConfigTest extends TestCase
{
    private string $redirectCachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redirectCachePath = storage_path('framework/testing/cms-redirects.php');
        config()->set('cms.redirects_cache_path', $this->redirectCachePath);
        (new Filesystem)->delete($this->redirectCachePath);

        Schema::create('cms_redirects', function (Blueprint $table): void {
            $table->id();
            $table->text('url_from');
            $table->text('url_to');
            $table->unsignedInteger('code')->default(301);
            $table->timestamp('active_at');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        (new Filesystem)->delete($this->redirectCachePath);

        parent::tearDown();
    }

    public function test_command_builds_redirect_cache_without_a_settings_table(): void
    {
        $this->insertRedirects(600);

        $this->artisan('regenerate-redirect')->assertSuccessful();

        self::assertFileExists($this->redirectCachePath);

        $redirects = require $this->redirectCachePath;

        self::assertCount(600, $redirects);
        self::assertSame([
            'url_from' => 'https://example.test/legacy-0-'.str_repeat('x', 120),
            'url_to' => 'https://example.test/current-0-'.str_repeat('y', 120),
            'code' => 301,
        ], $redirects['/legacy-0-'.str_repeat('x', 120)]);
    }

    public function test_command_removes_the_legacy_aggregated_setting(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
        });
        DB::table('settings')->insert([
            'key' => 'cms.redirects',
            'value' => '[{"url_from":"https://example.test/old"}]',
        ]);
        $this->insertRedirects(600);

        $this->artisan('regenerate-redirect')->assertSuccessful();

        $this->assertDatabaseMissing('settings', ['key' => 'cms.redirects']);
        self::assertFileExists($this->redirectCachePath);
    }

    private function insertRedirects(int $count): void
    {
        $now = now();
        $redirects = [];

        for ($index = 0; $index < $count; $index++) {
            $redirects[] = [
                'url_from' => 'https://example.test/legacy-'.$index.'-'.str_repeat('x', 120),
                'url_to' => 'https://example.test/current-'.$index.'-'.str_repeat('y', 120),
                'code' => 301,
                'active_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($redirects, 100) as $chunk) {
            DB::table('cms_redirects')->insert($chunk);
        }
    }
}
