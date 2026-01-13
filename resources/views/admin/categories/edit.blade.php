@extends(config('cms.admin.layout', 'cms::layouts.bootstrap'))

@section('title', $isNew ? 'Create Category' : 'Edit Category')

@section('content')
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

    <h1>{{ $isNew ? 'Create Category' : 'Edit Category' }}</h1>

    <form method="post" action="{{ $isNew ? route($routePrefix . 'categories.store') : route($routePrefix . 'categories.update', $category) }}">
        @csrf
        @if(!$isNew)
            @method('put')
        @endif

        <div>
            <label for="type">Type</label>
            <select id="type" name="type">
                @foreach($types as $type)
                    <option value="{{ $type }}" @selected(old('type', $category->type) === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="0" @selected(old('status', $category->status) == 0)>Disabled</option>
                <option value="1" @selected(old('status', $category->status) == 1)>Enabled</option>
            </select>
        </div>

        @include('cms::admin.partials.category-translations', ['category' => $category, 'locales' => $locales])

        <button type="submit">Save</button>
    </form>
@endsection
