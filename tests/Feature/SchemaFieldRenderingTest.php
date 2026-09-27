<?php

namespace Tests\Feature;

use Dominservice\LaravelCms\ServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;

final class SchemaFieldRenderingTest extends TestCase
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

    public function test_it_renders_a_schema_driven_select_without_a_custom_view(): void
    {
        $view = $this->view('cms::livewire.admin.content.schema-field', [
            'cmsUi' => [],
            'errors' => new ViewErrorBag,
            'fieldKey' => 'alignment',
            'schema' => [
                'label' => 'Alignment',
                'type' => 'select',
                'required' => false,
                'options' => ['left' => 'Left', 'center' => 'Center'],
            ],
            'modelPath' => 'metaData.alignment',
            'value' => 'center',
            'locale' => null,
            'fieldId' => 'schema_alignment',
        ]);

        $view->assertSee('wire:model.defer="metaData.alignment"', false);
        $view->assertSee('<option value="center" selected>Center</option>', false);
    }

    public function test_it_renders_repeater_rows_with_livewire_paths(): void
    {
        $view = $this->view('cms::livewire.admin.content.schema-field', [
            'cmsUi' => [],
            'errors' => new ViewErrorBag,
            'fieldKey' => 'metrics',
            'schema' => [
                'label' => 'Metrics',
                'type' => 'repeater',
                'fields' => [
                    'value' => [
                        'label' => 'Value',
                        'type' => 'number',
                        'required' => true,
                    ],
                ],
            ],
            'modelPath' => 'metaTranslations.pl.metrics',
            'value' => [['value' => 12]],
            'locale' => 'pl',
            'fieldId' => 'schema_metrics_pl',
        ]);

        $view->assertSee('wire:model.defer="metaTranslations.pl.metrics.0.value"', false);
        $view->assertSee('wire:click="removeRepeaterItem(', false);
    }
}
