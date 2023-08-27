<?php

namespace Dominservice\LaravelCms;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    private  $lpMigration = 0;

    public function boot(Filesystem $filesystem) {
        $this->publishes([
            __DIR__ . '/../config/cms.php' => config_path('cms.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_cms_tables.php.stub' => $this->getMigrationFileName($filesystem, 'create_cms_tables'),
            __DIR__.'/../database/migrations/add_columns_content_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_columns_content_table'),
            __DIR__.'/../database/migrations/create_redirects_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_redirects_table'),
            __DIR__.'/../database/migrations/add_columns_category_content_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_columns_category_content_table'),
            __DIR__.'/../database/migrations/add_columns_categoryies_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_columns_categoryies_table'),
        ], 'migrations');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cms.php',
            'cms'
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @param Filesystem $filesystem
     * @return string
     */
    protected function getMigrationFileName(Filesystem $filesystem, $name): string
    {
                $this->lpMigration++;
        $timestamp = date('Y_m_d_Hi0'.$this->lpMigration);

        return Collection::make($this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem, $name) {
                return $filesystem->glob($path.'*'.$name.'.php');
            })->push($this->app->databasePath()."/migrations/{$timestamp}_{$name}.php")
            ->first();
    }
}
