@extends(config('cms.admin.layout', 'cms::layouts.bootstrap'))

@section('title', 'Category Contents')

@section('content')
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

    <h1>Contents for {{ optional($category->translate($locales[0] ?? app()->getLocale()))->name ?? $category->uuid }}</h1>

    <p>
        <a href="{{ route($routePrefix . 'categories.contents.create', $category) }}">Create content</a>
    </p>

    @if($contents->isEmpty())
        <p>No content yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contents as $content)
                    <tr>
                        <td>{{ optional($content->translate($locales[0] ?? app()->getLocale()))->name ?? $content->uuid }}</td>
                        <td>{{ $content->type }}</td>
                        <td>{{ $content->status ? 'Enabled' : 'Disabled' }}</td>
                        <td>
                            <a href="{{ route($routePrefix . 'categories.contents.edit', [$category, $content]) }}">Edit</a>
                            <form method="post" action="{{ route($routePrefix . 'categories.contents.destroy', [$category, $content]) }}" style="display:inline;">
                                @csrf
                                @method('delete')
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
