<?php

declare(strict_types=1);

namespace Tests;

use Dominservice\LaravelCms\ServiceProvider;
use Dominservice\MediaKit\MediaKitServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            MediaKitServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cms.admin.enabled', false);
        $app['config']->set('cms.routes.enabled', false);
        $app['config']->set('translatable.locales', ['pl', 'en']);
        $app['config']->set('translatable.fallback_locale', 'pl');
    }
}
