<?php

declare(strict_types=1);

namespace Tests\Feature;

use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\CategoryIndex;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentForm;
use Dominservice\LaravelCms\Http\Livewire\Admin\ContentIndex;
use Dominservice\LaravelCms\Http\Livewire\Admin\SettingsDashboard;
use Livewire\Livewire;
use Tests\TestCase;

final class LivewireFourCompatibilityTest extends TestCase
{
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

    public function test_forms_use_livewire_four_submit_handling(): void
    {
        foreach (['content', 'category'] as $form) {
            $template = file_get_contents(dirname(__DIR__, 2)."/resources/views/livewire/admin/{$form}/form.blade.php");

            self::assertIsString($template);
            self::assertStringContainsString('wire:submit="save"', $template);
            self::assertStringContainsString('wire:target="save"', $template);
            self::assertStringNotContainsString('wire:submit.prevent="save"', $template);
        }
    }
}
