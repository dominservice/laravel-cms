@php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
@php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

<div>
    @foreach($sections as $section)
        <div class="{{ $cmsUi['card'] ?? 'card mb-4' }}" wire:key="section-{{ $section['key'] }}">
            <div class="{{ $cmsUi['card_header'] ?? 'card-header' }}">
                <div class="{{ $cmsUi['header_row'] ?? '' }}">
                    <div class="{{ $cmsUi['card_title'] ?? 'card-title' }}">{{ $section['label'] }}</div>
                    @if(!empty($section['allow_create']))
                        <a class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}" href="{{ route($routePrefix . 'category.create') }}" wire:navigate>New</a>
                    @endif
                </div>
            </div>
            <div class="{{ $cmsUi['card_body'] ?? 'card-body' }}">
                @if(empty($section['items']))
                    <p>No categories configured.</p>
                @else
                    <table class="{{ $cmsUi['table'] ?? 'table' }}">
                        <thead>
                        <tr>
                            @foreach($section['columns'] as $column)
                                <th>{{ ucfirst(str_replace('_', ' ', $column)) }}</th>
                            @endforeach
                            <th>Actions</th>
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
                                        <a class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" href="{{ $item['edit_url'] }}" wire:navigate>Edit</a>
                                        @if($item['contents_url'])
                                            <a class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" href="{{ $item['contents_url'] }}" wire:navigate>Contents</a>
                                        @endif
                                        <button type="button"
                                                class="{{ $cmsUi['button_link'] ?? 'btn btn-link p-0' }}"
                                                onclick="confirm('Delete this category?') || event.stopImmediatePropagation()"
                                                wire:click="deleteCategory('{{ $item['model']->uuid }}')">
                                            Delete
                                        </button>
                                    @else
                                        <a class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}" href="{{ $item['create_url'] }}" wire:navigate>Create</a>
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
