@php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
@php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

<div class="content pb-5">
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">{{ config('cms.admin.settings.title', 'CMS settings') }}</h3>
            </div>
        </div>
        <div class="card-body">
            @if($metaFields === [])
                <div class="text-muted">{{ __('No meta fields configured.') }}</div>
            @else
                @foreach($metaFields as $metaField)
                    @php($fieldKey = (string) ($metaField['key'] ?? ''))
                    @if($fieldKey === '')
                        @continue
                    @endif
                    @php($fieldType = (string) ($metaField['type'] ?? 'text'))
                    <div class="row g-3 align-items-start mb-4">
                        <div class="col-lg-4">
                            <label class="form-label mb-0">{{ $metaField['label'] ?? $fieldKey }}</label>
                            <div class="text-muted small">{{ $fieldKey }}</div>
                        </div>
                        <div class="col-lg-8">
                            @if($fieldType === 'textarea')
                                <textarea
                                    class="form-control"
                                    rows="3"
                                    wire:change="saveMetaField(@js($fieldKey), $event.target.value)"
                                >{{ (string) ($metaValues[$fieldKey] ?? '') }}</textarea>
                            @else
                                <input
                                    type="{{ $fieldType === 'number' ? 'number' : 'text' }}"
                                    class="form-control"
                                    value="{{ (string) ($metaValues[$fieldKey] ?? '') }}"
                                    wire:change="saveMetaField(@js($fieldKey), $event.target.value)"
                                >
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    @foreach($panels as $panel)
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ $panel['label'] }}</h3>
            </div>
            <div class="card-body">
                @forelse($panel['sections'] as $section)
                    <div class="border rounded p-3 mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h4 class="mb-0">{{ $section['label'] }}</h4>
                                <div class="text-muted small">{{ $section['key'] }}</div>
                            </div>
                            <a href="{{ $section['manage_url'] }}" class="btn btn-sm btn-outline-secondary" wire:navigate>
                                {{ __('Section list') }}
                            </a>
                        </div>

                        @if(empty($section['rows']))
                            <div class="text-muted">{{ __('No assignable items in this section.') }}</div>
                        @else
                            <div
                                class="cms-settings-sortable"
                                data-group-key="{{ $section['group_key'] }}"
                                data-order-key="{{ $section['order_key'] }}"
                                data-component-id="{{ $this->id }}"
                            >
                                @foreach($section['rows'] as $row)
                                    <div class="row g-2 align-items-center border-bottom py-3 cms-settings-row" data-handle="{{ $row['handle'] }}">
                                        <div class="col-lg-4">
                                            <div class="d-flex align-items-center gap-2">
                                                @if(!empty($section['group_key']) && $row['handle'])
                                                    <button type="button" class="btn btn-sm btn-light cursor-move cms-settings-drag">
                                                        <i class="fas fa-grip-vertical"></i>
                                                    </button>
                                                @endif
                                                <div>
                                                    <div class="fw-semibold">{{ $row['label'] }}</div>
                                                    <div class="text-muted small">{{ $row['config_key'] }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-4">
                                            @if($row['uuid'])
                                                <div class="text-muted small">{{ $row['uuid'] }}</div>
                                            @endif
                                            <div>{{ $row['entity_name'] ?: __('Not assigned') }}</div>
                                        </div>

                                        <div class="col-lg-4 text-lg-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                wire:click='openUuidPicker(@js($row["source"]), @js($row["section_key"]), @js($row["config_key"]), @js($row["group_key"]), @js($row["handle"]), @js($row["item_key"]))'
                                            >
                                                {{ __('Change UUID') }}
                                            </button>

                                            @if($row['create_url'])
                                                <a href="{{ $row['create_url'] }}" class="btn btn-sm btn-success" wire:navigate>{{ __('New') }}</a>
                                            @endif
                                            @if($row['edit_url'])
                                                <a href="{{ $row['edit_url'] }}" class="btn btn-sm btn-info" wire:navigate>{{ __('Edit') }}</a>
                                            @endif
                                            @if($row['list_url'])
                                                <a href="{{ $row['list_url'] }}" class="btn btn-sm btn-secondary" wire:navigate>{{ __('Items') }}</a>
                                            @endif
                                        </div>

                                        @if(!empty($row['options']))
                                            <div class="col-12">
                                                <div class="d-flex flex-wrap gap-4 pt-2">
                                                    @foreach($row['options'] as $option)
                                                        @if($option['type'] === 'boolean')
                                                            <div class="form-check form-switch">
                                                                <input
                                                                    class="form-check-input"
                                                                    type="checkbox"
                                                                    id="{{ md5($option['config_path']) }}"
                                                                    @checked($option['value'])
                                                                    wire:change='updateGroupField(@js($row["group_key"]), @js($row["handle"]), @js($option["field"]), $event.target.checked, "boolean")'
                                                                >
                                                                <label class="form-check-label" for="{{ md5($option['config_path']) }}">{{ $option['label'] }}</label>
                                                            </div>
                                                        @else
                                                            <div>
                                                                <label class="form-label mb-1">{{ $option['label'] }}</label>
                                                                <input
                                                                    type="{{ $option['type'] === 'number' ? 'number' : 'text' }}"
                                                                    class="form-control form-control-sm"
                                                                    value="{{ (string) $option['value'] }}"
                                                                    wire:change='updateGroupField(@js($row["group_key"]), @js($row["handle"]), @js($option["field"]), $event.target.value, @js($option["type"]))'
                                                                >
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-muted">{{ __('No sections configured in this panel.') }}</div>
                @endforelse
            </div>
        </div>
    @endforeach

    @if($pickerOpen)
        <div class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center" style="z-index: 1080;">
            <div class="card shadow" style="width: min(920px, 95vw); max-height: 90vh;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">{{ __('Choose UUID') }}</h4>
                    <button type="button" class="btn btn-sm btn-light" wire:click="closeUuidPicker">×</button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <input
                            type="text"
                            class="form-control"
                            wire:model.live.debounce.250ms="pickerSearch"
                            placeholder="{{ __('Search by name / slug / uuid') }}"
                        >
                    </div>

                    <div class="table-responsive" style="max-height: 56vh;">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('UUID') }}</th>
                                    <th class="text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pickerResults as $item)
                                    <tr>
                                        <td>{{ $item['name'] }}</td>
                                        <td><span class="text-muted small">{{ $item['uuid'] }}</span></td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-primary"
                                                wire:click='selectPickerUuid(@js($item["uuid"]))'
                                            >
                                                {{ __('Select') }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-muted">{{ __('No results.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
        <script>
            (function () {
                const initSortables = () => {
                    document.querySelectorAll('.cms-settings-sortable').forEach((el) => {
                        if (el.dataset.sortableReady === '1') {
                            return;
                        }

                        const groupKey = el.dataset.groupKey || '';
                        if (!groupKey) {
                            return;
                        }

                        const orderKey = el.dataset.orderKey || 'order';
                        const componentId = el.dataset.componentId;
                        const component = window.Livewire?.find(componentId);
                        if (!component) {
                            return;
                        }

                        Sortable.create(el, {
                            animation: 150,
                            handle: '.cms-settings-drag',
                            draggable: '.cms-settings-row',
                            onEnd: function () {
                                const handles = [];
                                el.querySelectorAll('.cms-settings-row[data-handle]').forEach((row) => {
                                    const handle = row.dataset.handle;
                                    if (handle) {
                                        handles.push(handle);
                                    }
                                });
                                if (handles.length > 0) {
                                    component.call('reorderGroup', groupKey, orderKey, handles);
                                }
                            }
                        });

                        el.dataset.sortableReady = '1';
                    });
                };

                document.addEventListener('livewire:init', initSortables);
                document.addEventListener('livewire:navigated', initSortables);
                setTimeout(initSortables, 150);
            })();
        </script>
    @endpush
</div>

