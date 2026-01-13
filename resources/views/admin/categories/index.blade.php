@extends(config('cms.admin.layout', 'cms::layouts.bootstrap'))

@section('title', 'Categories')

@section('content')
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

    <h1>Categories</h1>

    <p>
        <a href="{{ route($routePrefix . 'categories.create') }}">Create category</a>
    </p>

    @if($categories->isEmpty())
        <p>No categories yet.</p>
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
                @foreach($categories as $category)
                    <tr>
                        <td>{{ optional($category->translate($locales[0] ?? app()->getLocale()))->name ?? $category->uuid }}</td>
                        <td>{{ $category->type }}</td>
                        <td>{{ $category->status ? 'Enabled' : 'Disabled' }}</td>
                        <td>
                            <a href="{{ route($routePrefix . 'categories.edit', $category) }}">Edit</a>
                            <a href="{{ route($routePrefix . 'categories.contents.index', $category) }}">Contents</a>
                            <form method="post" action="{{ route($routePrefix . 'categories.destroy', $category) }}" style="display:inline;">
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
