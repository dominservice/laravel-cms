@php($schemaType = (string) ($schema['type'] ?? 'text'))
@php($schemaLabel = (string) ($schema['label'] ?? ucfirst(str_replace('_', ' ', $fieldKey))))
@php($schemaPlaceholder = $schema['placeholder'] ?? null)
@php($schemaHelp = $schema['help'] ?? null)

@if($schemaType === 'repeater')
    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}" wire:key="{{ $fieldId }}">
        <div class="{{ $cmsUi['header_row'] ?? 'd-flex justify-content-between align-items-center' }}">
            <label class="{{ $cmsUi['label'] ?? '' }}">{{ $schemaLabel }}</label>
            <button type="button" class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" wire:click="addRepeaterItem(@js($fieldKey), @js($locale))">
                {{ __('cms::laravel_cms.add_item') }}
            </button>
        </div>

        @if($schemaHelp)
            <small>{{ $schemaHelp }}</small>
        @endif

        @forelse((array) $value as $rowIndex => $row)
            <div class="{{ $cmsUi['card'] ?? 'card' }}" wire:key="{{ $fieldId }}_{{ $rowIndex }}">
                <div class="{{ $cmsUi['card_body'] ?? 'card-body' }}">
                    @foreach((array) ($schema['fields'] ?? []) as $subFieldKey => $subFieldSchema)
                        @include('cms::livewire.admin.content.schema-field', [
                            'fieldKey' => $subFieldKey,
                            'schema' => $subFieldSchema,
                            'modelPath' => $modelPath . '.' . $rowIndex . '.' . $subFieldKey,
                            'value' => data_get($row, $subFieldKey),
                            'locale' => $locale,
                            'fieldId' => $fieldId . '_' . $rowIndex . '_' . $subFieldKey,
                        ])
                    @endforeach

                    <button type="button" class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" wire:click="removeRepeaterItem(@js($fieldKey), {{ $rowIndex }}, @js($locale))">
                        {{ __('cms::laravel_cms.delete') }}
                    </button>
                </div>
            </div>
        @empty
            <p>{{ __('cms::laravel_cms.no_items') }}</p>
        @endforelse

        @error($modelPath)<div>{{ $message }}</div>@enderror
    </div>
@elseif(in_array($schemaType, ['checkbox', 'boolean', 'toggle'], true))
    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}" wire:key="{{ $fieldId }}">
        <label>
            <input id="{{ $fieldId }}" type="checkbox" wire:model.defer="{{ $modelPath }}" @checked((bool) $value)>
            {{ $schemaLabel }}
        </label>
        @if($schemaHelp)
            <small>{{ $schemaHelp }}</small>
        @endif
        @error($modelPath)<div>{{ $message }}</div>@enderror
    </div>
@else
    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}" wire:key="{{ $fieldId }}">
        <label class="{{ $cmsUi['label'] ?? '' }}" for="{{ $fieldId }}">
            {{ $schemaLabel }}@if(!empty($schema['required'])) *@endif
        </label>

        @if($schemaType === 'select')
            <select id="{{ $fieldId }}" wire:model.defer="{{ $modelPath }}" class="{{ $cmsUi['select'] ?? 'form-select' }}">
                @if(empty($schema['required']))
                    <option value="">-- {{ __('cms::laravel_cms.select') }} --</option>
                @endif
                @foreach((array) ($schema['options'] ?? []) as $optionValue => $optionLabel)
                    @php($resolvedOptionValue = is_int($optionValue) ? $optionLabel : $optionValue)
                    <option value="{{ $resolvedOptionValue }}" @selected((string) $value === (string) $resolvedOptionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif(in_array($schemaType, ['textarea', 'editorjs'], true))
            <textarea
                id="{{ $fieldId }}"
                wire:model.defer="{{ $modelPath }}"
                class="{{ $cmsUi['textarea'] ?? 'form-control' }}"
                @if($schemaPlaceholder) placeholder="{{ $schemaPlaceholder }}" @endif
                @if($schemaType === 'editorjs') data-editorjs-input="1" @endif
            >{{ $value }}</textarea>
            @if($schemaType === 'editorjs')
                @php($schemaProfile = (string) ($schema['profile'] ?? 'default'))
                @php($schemaMinHeight = (int) data_get(config('cms.admin.content.editorjs.profiles', []), $schemaProfile . '.min_height', 180))
                <div class="border border-gray-300 rounded-3 bg-white overflow-hidden d-none" data-editorjs-holder="{{ $fieldId }}" data-editorjs-profile="{{ $schemaProfile }}" data-editorjs-min-height="{{ $schemaMinHeight }}" wire:ignore></div>
            @endif
        @else
            <input
                id="{{ $fieldId }}"
                type="{{ in_array($schemaType, ['url', 'email', 'number', 'date', 'datetime-local'], true) ? $schemaType : 'text' }}"
                wire:model.defer="{{ $modelPath }}"
                class="{{ $cmsUi['input'] ?? 'form-control' }}"
                value="{{ $value }}"
                @if($schemaPlaceholder) placeholder="{{ $schemaPlaceholder }}" @endif
            >
        @endif

        @if($schemaHelp)
            <small>{{ $schemaHelp }}</small>
        @endif
        @error($modelPath)<div>{{ $message }}</div>@enderror
    </div>
@endif
