@php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
@php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

<div>
    @foreach($sections as $section)
        <div class="{{ $cmsUi['card'] ?? 'card mb-4' }}" wire:key="section-{{ $section['key'] }}">
            <div class="{{ $cmsUi['card_header'] ?? 'card-header' }}">
                <div class="{{ $cmsUi['header_row'] ?? '' }}">
                    <div class="{{ $cmsUi['card_title'] ?? 'card-title' }}">{{ $section['label'] }}</div>
                @if(!empty($section['allow_create']) && !empty($section['create_url']))
                    <a class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}" href="{{ $section['create_url'] }}" wire:navigate>{{ __('cms::laravel_cms.new') }}</a>
                @endif
            </div>
        </div>
            <div class="{{ $cmsUi['card_body'] ?? 'card-body' }}">
                @if(empty($section['items']))
                    <p>{{ __('cms::laravel_cms.no_categories_configured') }}</p>
                @else
                    <table class="{{ $cmsUi['table'] ?? 'table' }}">
                        <thead>
                        <tr>
                            @foreach($section['columns'] as $column)
                                <th>{{ ucfirst(str_replace('_', ' ', $column)) }}</th>
                            @endforeach
                            <th>{{ __('cms::laravel_cms.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($section['items'] as $item)
                            <tr wire:key="category-{{ $section['key'] }}-{{ $item['key'] }}">
                                @foreach($section['columns'] as $column)
                                    <td>{{ $item['columns'][$column] ?? '-' }}</td>
                                @endforeach
                                <td>
                                    @if($item['edit_url'])
                                        <a class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" href="{{ $item['edit_url'] }}" wire:navigate>{{ __('cms::laravel_cms.edit') }}</a>
                                        @if($item['contents_url'])
                                            <a class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" href="{{ $item['contents_url'] }}" wire:navigate>{{ __('cms::laravel_cms.contents') }}</a>
                                        @endif
                                        <button type="button"
                                                class="{{ $cmsUi['button_link'] ?? 'btn btn-link p-0' }}"
                                                onclick="confirm('{{ __('cms::laravel_cms.confirm_delete_category') }}') || event.stopImmediatePropagation()"
                                                wire:click="deleteCategory('{{ $item['model']->uuid }}')">
                                            {{ __('cms::laravel_cms.delete') }}
                                        </button>
                                    @else
                                        <a class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}" href="{{ $item['create_url'] }}" wire:navigate>{{ __('cms::laravel_cms.create') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endforeach
</div>
