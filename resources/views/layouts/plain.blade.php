<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CMS')</title>
    @stack('styles')
</head>
<body>
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')
    <header>
        <p>
            <a href="{{ route($routePrefix . 'pages.index') }}">Pages</a> |
            <a href="{{ route($routePrefix . 'categories.index') }}">Categories</a>
        </p>
    </header>

    @if(session('status'))
        <p>{{ session('status') }}</p>
    @endif

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
