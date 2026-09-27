<?php

namespace Dominservice\LaravelCms\Support;

use Illuminate\Validation\Rule;

final class CmsFieldSchema
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_TYPES = [
        'text',
        'textarea',
        'editorjs',
        'url',
        'email',
        'number',
        'date',
        'datetime-local',
        'select',
        'checkbox',
        'boolean',
        'toggle',
        'repeater',
    ];

    /**
     * Resolve reusable presets and fields declared for a section or block.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function resolve(?array $section, ?array $block = null): array
    {
        $definition = $section ?? [];

        if ($block !== null && (array_key_exists('schema_fields', $block) || array_key_exists('schema_presets', $block))) {
            $definition = $block;
        }

        $fields = [];
        foreach ((array) ($definition['schema_presets'] ?? []) as $preset) {
            if (! is_string($preset) || $preset === '') {
                continue;
            }

            $presetFields = (array) config("cms.admin.content.schema_presets.{$preset}", []);
            $fields = self::merge($fields, $presetFields);
        }

        return self::merge($fields, (array) ($definition['schema_fields'] ?? []));
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @return array<string, array<string, mixed>>
     */
    public static function normalize(array $fields): array
    {
        $normalized = [];

        foreach (self::definitions($fields) as $fieldKey => $schema) {
            $type = strtolower((string) ($schema['type'] ?? 'text'));
            if (! in_array($type, self::SUPPORTED_TYPES, true)) {
                $type = 'text';
            }

            $schema = array_replace([
                'label' => self::labelFromKey($fieldKey),
                'type' => $type,
                'translatable' => false,
                'required' => false,
                'options' => [],
                'default' => null,
                'placeholder' => null,
                'help' => null,
                'rules' => null,
            ], $schema);
            $schema['type'] = $type;
            $schema['translatable'] = (bool) $schema['translatable'];
            $schema['required'] = (bool) $schema['required'];
            $schema['options'] = (array) $schema['options'];

            if ($type === 'repeater') {
                $schema['fields'] = self::normalize((array) ($schema['fields'] ?? []));
                $schema['synchronize_structure'] = (bool) ($schema['synchronize_structure'] ?? false);
            }

            $normalized[$fieldKey] = $schema;
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<array-key, mixed>  $overrides
     * @return array<string, array<string, mixed>>
     */
    private static function merge(array $fields, array $overrides): array
    {
        foreach (self::definitions($overrides) as $fieldKey => $override) {
            $schema = array_replace($fields[$fieldKey] ?? [], $override);
            $normalized = self::normalize([$fieldKey => $schema]);

            if (isset($normalized[$fieldKey])) {
                $fields[$fieldKey] = $normalized[$fieldKey];
            }
        }

        return $fields;
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @return array<string, array<string, mixed>>
     */
    private static function definitions(array $fields): array
    {
        $definitions = [];

        foreach ($fields as $fieldKey => $fieldSchema) {
            if (is_int($fieldKey) && is_string($fieldSchema)) {
                $fieldKey = $fieldSchema;
                $fieldSchema = [];
            } elseif (is_int($fieldKey) && is_array($fieldSchema)) {
                $fieldKey = $fieldSchema['key'] ?? null;
                unset($fieldSchema['key']);
            } elseif (is_string($fieldKey) && is_string($fieldSchema)) {
                $fieldSchema = ['type' => $fieldSchema];
            }

            if (! is_string($fieldKey) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $fieldKey) !== 1) {
                continue;
            }

            $definitions[$fieldKey] = is_array($fieldSchema) ? $fieldSchema : [];
        }

        return $definitions;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  list<string>  $locales
     * @return array<string, mixed>
     */
    public static function validationRules(array $fields, array $locales): array
    {
        $rules = [];

        foreach ($fields as $fieldKey => $schema) {
            if (! empty($schema['translatable'])) {
                foreach ($locales as $locale) {
                    self::addFieldRules($rules, "metaTranslations.{$locale}.{$fieldKey}", $schema);
                }

                continue;
            }

            self::addFieldRules($rules, "metaData.{$fieldKey}", $schema);
        }

        return $rules;
    }

    public static function defaultValue(array $schema, ?string $locale = null): mixed
    {
        $default = $schema['default'] ?? null;
        if ($locale !== null && is_array($default)) {
            return $default[$locale] ?? null;
        }

        return $default;
    }

    public static function normalizeValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'checkbox', 'boolean', 'toggle' => (bool) $value,
            'number' => is_numeric($value) ? $value + 0 : null,
            'repeater' => is_array($value) ? array_values($value) : [],
            'editorjs', 'textarea', 'text', 'url', 'email', 'date', 'datetime-local', 'select' => is_scalar($value) || $value === null
                ? (string) ($value ?? '')
                : '',
            default => $value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultRepeaterItem(array $schema, ?string $locale = null): array
    {
        $defaults = [];

        foreach ((array) ($schema['fields'] ?? []) as $fieldKey => $fieldSchema) {
            if (! is_string($fieldKey) || ! is_array($fieldSchema)) {
                continue;
            }

            $defaults[$fieldKey] = self::normalizeValue(
                self::defaultValue($fieldSchema, $locale),
                (string) ($fieldSchema['type'] ?? 'text')
            );
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $schema
     */
    private static function addFieldRules(array &$rules, string $path, array $schema): void
    {
        $rules[$path] = self::rulesForField($schema);

        if (($schema['type'] ?? null) !== 'repeater') {
            return;
        }

        foreach ((array) ($schema['fields'] ?? []) as $fieldKey => $fieldSchema) {
            if (! is_string($fieldKey) || ! is_array($fieldSchema)) {
                continue;
            }

            self::addFieldRules($rules, "{$path}.*.{$fieldKey}", $fieldSchema);
        }
    }

    /**
     * @return array<int, mixed>|string
     */
    private static function rulesForField(array $schema): array|string
    {
        $customRules = $schema['rules'] ?? null;
        if (is_string($customRules) && $customRules !== '') {
            return $customRules;
        }

        if (is_array($customRules) && $customRules !== []) {
            return $customRules;
        }

        $rules = [! empty($schema['required']) ? 'required' : 'nullable'];
        $type = (string) ($schema['type'] ?? 'text');

        $rules[] = match ($type) {
            'url' => 'url',
            'email' => 'email',
            'number' => 'numeric',
            'date', 'datetime-local' => 'date',
            'checkbox', 'boolean', 'toggle' => 'boolean',
            'repeater' => 'array',
            default => 'string',
        };

        if ($type === 'select' && ($schema['options'] ?? []) !== []) {
            $options = (array) $schema['options'];
            $values = array_is_list($options) ? array_values($options) : array_keys($options);
            $rules[] = Rule::in($values);
        }

        return $rules;
    }

    private static function labelFromKey(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
