@extends(config('cms.admin.layout', 'cms::layouts.bootstrap'))

@section('title', 'Pages')

@section('content')
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

    <h1>Pages</h1>

    @if(empty($pages))
        <p>No pages configured. Add pages in config/cms.php under structure.pages.</p>
    @else
        <ul>
            @foreach($pages as $pageKey => $page)
                <li>
                    <strong>{{ $page['label'] ?? $pageKey }}</strong>
                    <span>({{ $pageKey }})</span>
                    <a href="{{ route($routePrefix . 'pages.edit', ['pageKey' => $pageKey]) }}">Edit page</a>

                    @if(!empty($page['sections']))
                        <ul>
                            @foreach($page['sections'] as $sectionKey => $section)
                                <li>
                                    {{ $section['label'] ?? $sectionKey }}
                                    <a href="{{ route($routePrefix . 'pages.sections.edit', ['pageKey' => $pageKey, 'sectionKey' => $sectionKey]) }}">Edit section</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
