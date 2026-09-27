<?php

namespace Tests\Unit\Http\Livewire\Admin;

use Dominservice\LaravelCms\Http\Livewire\Admin\ContentForm;
use PHPUnit\Framework\TestCase;

class ContentFormRepeaterTest extends TestCase
{
    public function test_removing_synchronized_translatable_item_updates_every_locale(): void
    {
        $form = new ContentForm();
        $form->locales = ['pl', 'de', 'en'];
        $form->schemaFields = [
            'metrics' => [
                'type' => 'repeater',
                'translatable' => true,
                'synchronize_structure' => true,
            ],
        ];
        $form->metaTranslations = [
            'pl' => ['metrics' => [['value' => 'A'], ['value' => 'B']]],
            'de' => ['metrics' => [['value' => 'DE A'], ['value' => 'DE B']]],
            'en' => ['metrics' => [['value' => 'EN A'], ['value' => 'EN B']]],
        ];

        $form->removeRepeaterItem('metrics', 0, 'pl');

        $this->assertSame([['value' => 'B']], $form->metaTranslations['pl']['metrics']);
        $this->assertSame([['value' => 'DE B']], $form->metaTranslations['de']['metrics']);
        $this->assertSame([['value' => 'EN B']], $form->metaTranslations['en']['metrics']);
    }

    public function test_regular_translatable_repeater_remains_locale_specific(): void
    {
        $form = new ContentForm();
        $form->locales = ['pl', 'de'];
        $form->schemaFields = [
            'items' => ['type' => 'repeater', 'translatable' => true],
        ];
        $form->metaTranslations = [
            'pl' => ['items' => [['value' => 'PL']]],
            'de' => ['items' => [['value' => 'DE']]],
        ];

        $form->removeRepeaterItem('items', 0, 'pl');

        $this->assertSame([], $form->metaTranslations['pl']['items']);
        $this->assertSame([['value' => 'DE']], $form->metaTranslations['de']['items']);
    }

    public function test_clearing_source_locale_clears_previously_drifted_locales(): void
    {
        $form = new ContentForm();
        $form->locales = ['pl', 'de'];
        $form->schemaFields = [
            'metrics' => [
                'type' => 'repeater',
                'translatable' => true,
                'synchronize_structure' => true,
            ],
        ];
        $form->metaTranslations = [
            'pl' => ['metrics' => [['value' => 'ostatnia']]],
            'de' => ['metrics' => [['value' => 'A'], ['value' => 'B']]],
        ];

        $form->removeRepeaterItem('metrics', 0, 'pl');

        $this->assertSame([], $form->metaTranslations['pl']['metrics']);
        $this->assertSame([], $form->metaTranslations['de']['metrics']);
    }
}
