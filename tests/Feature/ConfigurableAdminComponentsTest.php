<?php

declare(strict_types=1);

namespace Tests\Feature;

use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryIndex;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentIndex;
use Dominservice\LaravelCms\Http\Livewire\Admin\SettingsDashboard;
use Dominservice\LaravelCms\Support\AdminComponentResolver;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

final class ConfigurableAdminComponentsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cms.admin.enabled', true);
        $app['config']->set('cms.admin.middleware', []);
        $app['config']->set('cms.admin.components', [
            'content_index' => TestContentIndex::class,
            'content_form' => TestContentForm::class,
            'category_index' => TestCategoryIndex::class,
            'category_form' => TestCategoryForm::class,
            'settings_dashboard' => TestSettingsDashboard::class,
        ]);
    }

    public function test_configured_components_are_used_by_livewire_and_admin_routes(): void
    {
        $components = [
            'dominservice.laravel-cms.http.livewire.admin.content-index' => TestContentIndex::class,
            'dominservice.laravel-cms.http.livewire.admin.content-form' => TestContentForm::class,
            'dominservice.laravel-cms.http.livewire.admin.category-index' => TestCategoryIndex::class,
            'dominservice.laravel-cms.http.livewire.admin.category-form' => TestCategoryForm::class,
            'dominservice.laravel-cms.http.livewire.admin.settings-dashboard' => TestSettingsDashboard::class,
        ];

        foreach ($components as $name => $class) {
            self::assertInstanceOf($class, Livewire::new($name));
        }

        self::assertSame(TestContentIndex::class, Route::getRoutes()->getByName('cms.content.index')?->getActionName());
        self::assertSame(TestContentForm::class, Route::getRoutes()->getByName('cms.content.section.create')?->getActionName());
        self::assertSame(TestContentForm::class, Route::getRoutes()->getByName('cms.content.edit')?->getActionName());
        self::assertSame(TestCategoryIndex::class, Route::getRoutes()->getByName('cms.category.index')?->getActionName());
        self::assertSame(TestCategoryForm::class, Route::getRoutes()->getByName('cms.category.create')?->getActionName());
        self::assertSame(TestCategoryForm::class, Route::getRoutes()->getByName('cms.category.edit')?->getActionName());
        self::assertSame(TestSettingsDashboard::class, Route::getRoutes()->getByName('cms.settings')?->getActionName());
    }

    public function test_invalid_component_override_is_rejected_before_it_reaches_a_route(): void
    {
        config()->set('cms.admin.components.content_index', \stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must extend '.Component::class);

        AdminComponentResolver::resolve('content_index', ContentIndex::class);
    }
}

final class TestContentIndex extends ContentIndex {}

final class TestContentForm extends ContentForm {}

final class TestCategoryIndex extends CategoryIndex {}

final class TestCategoryForm extends CategoryForm {}

final class TestSettingsDashboard extends SettingsDashboard {}
