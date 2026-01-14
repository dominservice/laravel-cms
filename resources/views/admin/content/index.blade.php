@extends('cms::layouts.admin')

@section('title', 'Content')

@section('content')
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

    @foreach($sections as $section)
        <div class="{{ $cmsUi['card'] ?? 'card mb-4' }}">
            <div class="{{ $cmsUi['card_header'] ?? 'card-header' }}">
                <div class="{{ $cmsUi['header_row'] ?? '' }}">
                    <div class="{{ $cmsUi['card_title'] ?? 'card-title' }}">{{ $section['label'] }}</div>
                    @if(!empty($section['allow_create']))
                        <a class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}" href="{{ route($routePrefix . 'content.section.create', ['section' => $section['key']]) }}">New</a>
                    @endif
                </div>
            </div>
            <div class="{{ $cmsUi['card_body'] ?? 'card-body' }}">
                @if(empty($section['items']))
                    <p>No content configured.</p>
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
                                <tr>
                                    @foreach($section['columns'] as $column)
                                        <td>{{ $item['columns'][$column] ?? '-' }}</td>
                                    @endforeach
                                    <td>
                                        @if($item['edit_url'])
                                            <a class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" href="{{ $item['edit_url'] }}">Edit</a>
                                            <form method="post" action="{{ $item['delete_url'] }}" style="display:inline;">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="{{ $cmsUi['button_link'] ?? 'btn btn-link p-0' }}">Delete</button>
                                            </form>
                                        @else
                                            <a class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}" href="{{ $item['create_url'] }}">Create</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if(!empty($section['blocks']))
                    <div class="mt-4">
                        <h4>Blocks</h4>
                        <table class="{{ $cmsUi['table'] ?? 'table' }}">
                            <thead>
                                <tr>
                                    <th>Block</th>
                                    @foreach($section['columns'] as $column)
                                        <th>{{ ucfirst(str_replace('_', ' ', $column)) }}</th>
                                    @endforeach
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($section['blocks'] as $block)
                                    <tr>
                                        <td>{{ $block['label'] }}</td>
                                        @foreach($section['columns'] as $column)
                                            <td>{{ $block['columns'][$column] ?? '-' }}</td>
                                        @endforeach
                                        <td>
                                            @if($block['edit_url'])
                                                <a class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" href="{{ $block['edit_url'] }}">Edit</a>
                                                <form method="post" action="{{ $block['delete_url'] }}" style="display:inline;">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" class="{{ $cmsUi['button_link'] ?? 'btn btn-link p-0' }}">Delete</button>
                                                </form>
                                            @else
                                                <a class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}" href="{{ $block['create_url'] }}">Create</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endsection
