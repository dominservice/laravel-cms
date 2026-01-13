@extends(config('cms.admin.layout', 'cms::layouts.bootstrap'))

@section('title', 'Edit Section')

@section('content')
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

    <h1>{{ $sectionConfig['label'] ?? $sectionKey }}</h1>
    <p>Page: {{ $pageKey }}</p>

    <form method="post" action="{{ route($routePrefix . 'pages.sections.update', ['pageKey' => $pageKey, 'sectionKey' => $sectionKey]) }}">
        @csrf
        @method('put')

        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="0" @selected(old('status', $content->status) == 0)>Disabled</option>
                <option value="1" @selected(old('status', $content->status) == 1)>Enabled</option>
            </select>
        </div>

        <div>
            <label for="is_nofollow">No follow</label>
            <input id="is_nofollow" type="checkbox" name="is_nofollow" value="1" @checked(old('is_nofollow', $content->is_nofollow))>
        </div>

        <div>
            <label for="external_url">External URL</label>
            <input id="external_url" type="text" name="external_url" value="{{ old('external_url', $content->external_url) }}">
        </div>

        @include('cms::admin.partials.content-translations', ['content' => $content, 'locales' => $locales])

        <button type="submit">Save</button>
    </form>
@endsection
