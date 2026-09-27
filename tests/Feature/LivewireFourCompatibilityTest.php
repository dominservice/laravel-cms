<?php

declare(strict_types=1);

namespace Tests\Feature;

use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryIndex;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentIndex;
use Dominservice\LaravelCms\Http\Livewire\Admin\SettingsDashboard;
use Dominservice\LaravelCms\ServiceProvider;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;

final class LivewireFourCompatibilityTest extends TestCase
{
    /**
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    public function test_package_registers_its_livewire_four_components(): void
    {
        $components = [
            'dominservice.laravel-cms.http.livewire.admin.content-index' => ContentIndex::class,
            'dominservice.laravel-cms.http.livewire.admin.content-form' => ContentForm::class,
            'dominservice.laravel-cms.http.livewire.admin.category-index' => CategoryIndex::class,
            'dominservice.laravel-cms.http.livewire.admin.category-form' => CategoryForm::class,
            'dominservice.laravel-cms.http.livewire.admin.settings-dashboard' => SettingsDashboard::class,
        ];

        foreach ($components as $name => $class) {
            self::assertTrue(Livewire::exists($name));
            self::assertInstanceOf($class, Livewire::new($name));
        }
    }
}
