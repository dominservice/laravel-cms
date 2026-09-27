<?php

namespace Tests\Unit\Support;

use Dominservice\LaravelCms\Support\CmsFieldSchema;
use Illuminate\Validation\Rules\In;
use Tests\TestCase;

final class CmsFieldSchemaTest extends TestCase
{
    public function test_it_combines_reusable_presets_with_local_field_overrides(): void
    {
        config()->set('cms.admin.content.schema_presets.cta', [
            'cta_label' => ['translatable' => true],
            'cta_url' => ['type' => 'url', 'translatable' => true],
        ]);

        $fields = CmsFieldSchema::resolve([
            'schema_presets' => ['cta'],
            'schema_fields' => [
                'cta_url' => ['required' => true],
                'variant' => 'select',
            ],
        ]);

        self::assertSame(['cta_label', 'cta_url', 'variant'], array_keys($fields));
        self::assertTrue($fields['cta_label']['translatable']);
        self::assertSame('url', $fields['cta_url']['type']);
        self::assertTrue($fields['cta_url']['translatable']);
        self::assertTrue($fields['cta_url']['required']);
    }

    public function test_it_normalizes_shorthand_and_repeater_fields(): void
    {
        $fields = CmsFieldSchema::normalize([
            'title',
            'cta_url' => 'url',
            'metrics' => [
                'type' => 'repeater',
                'translatable' => true,
                'fields' => [
                    'value' => ['type' => 'number', 'required' => true],
                    'label' => 'text',
                ],
            ],
            'invalid.field' => 'text',
        ]);

        self::assertSame(['title', 'cta_url', 'metrics'], array_keys($fields));
        self::assertSame('text', $fields['title']['type']);
        self::assertSame('url', $fields['cta_url']['type']);
        self::assertTrue($fields['metrics']['translatable']);
        self::assertSame('number', $fields['metrics']['fields']['value']['type']);
        self::assertTrue($fields['metrics']['fields']['value']['required']);
    }

    public function test_it_builds_rules_for_translations_selects_and_repeater_items(): void
    {
        $fields = CmsFieldSchema::normalize([
            'variant' => [
                'type' => 'select',
                'options' => ['light' => 'Light', 'dark' => 'Dark'],
            ],
            'metrics' => [
                'type' => 'repeater',
                'translatable' => true,
                'fields' => [
                    'value' => ['type' => 'number', 'required' => true],
                ],
            ],
        ]);

        $rules = CmsFieldSchema::validationRules($fields, ['pl', 'en']);

        $selectRules = array_filter(
            $rules['metaData.variant'],
            static fn (mixed $rule): bool => $rule instanceof In
        );

        self::assertCount(1, $selectRules);
        self::assertContainsOnlyInstancesOf(In::class, $selectRules);
        self::assertSame(['nullable', 'array'], $rules['metaTranslations.pl.metrics']);
        self::assertSame(['required', 'numeric'], $rules['metaTranslations.pl.metrics.*.value']);
        self::assertSame(['required', 'numeric'], $rules['metaTranslations.en.metrics.*.value']);
    }

    public function test_it_creates_normalized_repeater_defaults(): void
    {
        $schema = CmsFieldSchema::normalize([
            'items' => [
                'type' => 'repeater',
                'fields' => [
                    'enabled' => ['type' => 'checkbox', 'default' => true],
                    'count' => ['type' => 'number', 'default' => '2'],
                    'label' => ['default' => ['pl' => 'Etykieta', 'en' => 'Label']],
                ],
            ],
        ])['items'];

        self::assertSame([
            'enabled' => true,
            'count' => 2,
            'label' => 'Etykieta',
        ], CmsFieldSchema::defaultRepeaterItem($schema, 'pl'));
    }
}
