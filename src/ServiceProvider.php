<?php

namespace Dominservice\LaravelCms;

use Dominservice\LaravelCms\Console\CmsMediaMigrateV4;
use Dominservice\LaravelCms\Console\Commands\RedirectConfig;
use Dominservice\LaravelCms\Http\Middleware\Redirects;
use Dominservice\LaravelCms\Models\Redirect;
use Dominservice\LaravelCms\Observers\RedirectObserver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    private int $lpMigration = 0;

    public function boot(Filesystem $filesystem, Router $router): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CmsMediaMigrateV4::class,
                RedirectConfig::class,
            ]);
        }

        Redirect::observe(RedirectObserver::class);

        $this->publishes([
            __DIR__ . '/../config/cms.php' => config_path('cms.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_cms_tables.php.stub' => $this->getMigrationFileName($filesystem, 'create_cms_tables'),
            __DIR__.'/../database/migrations/add_columns_content_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_columns_content_table'),
            __DIR__.'/../database/migrations/create_redirects_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_redirects_table'),
            __DIR__.'/../database/migrations/add_columns_category_content_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_columns_category_content_table'),
            __DIR__.'/../database/migrations/add_columns_categoryies_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_columns_categoryies_table'),
            __DIR__.'/../database/migrations/create_video_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_video_table'),
            __DIR__.'/../database/migrations/add_column_content_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_column_content_table'),
            __DIR__.'/../database/migrations/create_files_tables.php.stub' => $this->getMigrationFileName($filesystem, 'create_files_tables'),
            __DIR__.'/../database/migrations/add_external_url_to_contents_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_external_url_to_contents_table'),
            __DIR__.'/../database/migrations/add_parent_to_content_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_parent_to_content_table'),
            __DIR__.'/../database/migrations/create_cms_content_links_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_cms_content_links_table'),
            __DIR__.'/../database/migrations/add_order_column_content_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_order_column_content_table'),
            __DIR__.'/../database/migrations/add_meta_to_contents_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_meta_to_contents_table'),
        ], 'migrations');

        $router->prependMiddlewareToGroup('web', Redirects::class);

    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cms.php',
            'cms'
        );
    }

    protected function getMigrationFileName(Filesystem $filesystem, string $name): string
    {
        $this->lpMigration++;
        $timestamp = now()->format('Y_m_d_Hi') . str_pad((string)$this->lpMigration, 2, "0", STR_PAD_RIGHT);

        return Collection::make($this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR)
            ->flatMap(fn($path) => $filesystem->glob($path . '*' . $name . '.php'))
            ->push($this->app->databasePath() . "/migrations/{$timestamp}_{$name}.php")
            ->first();
    }
}
