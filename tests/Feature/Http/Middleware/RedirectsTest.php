<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use Dominservice\LaravelCms\Http\Middleware\Redirects;
use Dominservice\LaravelCms\Support\RedirectRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RedirectsTest extends TestCase
{
    private string $redirectCachePath;

    protected function defineRoutes($router): void
    {
        $router->middleware(Redirects::class)->get('/legacy', static fn () => 'legacy');
        $router->middleware(Redirects::class)->get('/current', static fn () => 'current');
    }

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

    public function test_request_falls_back_to_the_redirect_table_when_cache_is_missing(): void
    {
        $this->insertRedirect();

        $this->get('/legacy')
            ->assertStatus(301)
            ->assertRedirect('https://example.test/current');
    }

    public function test_request_uses_compiled_redirect_cache_without_querying_the_table(): void
    {
        $this->insertRedirect();
        app(RedirectRepository::class)->rebuild();
        Schema::drop('cms_redirects');

        $this->get('/legacy')
            ->assertStatus(301)
            ->assertRedirect('https://example.test/current');
    }

    public function test_suppressed_source_path_does_not_redirect(): void
    {
        $this->insertRedirect();
        $repository = app(RedirectRepository::class);
        $repository->rebuild();
        $repository->suppressPaths(['/legacy']);

        $this->get('/legacy')
            ->assertOk()
            ->assertSeeText('legacy');
    }

    private function insertRedirect(): void
    {
        DB::table('cms_redirects')->insert([
            'url_from' => 'https://old-domain.example/legacy',
            'url_to' => 'https://example.test/current',
            'code' => 301,
            'active_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
