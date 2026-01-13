<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CMS')</title>
    @stack('styles')
</head>
<body class="bg-light">
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route($routePrefix . 'pages.index') }}">CMS</a>
            <div class="navbar-nav">
                <a class="nav-link" href="{{ route($routePrefix . 'pages.index') }}">Pages</a>
                <a class="nav-link" href="{{ route($routePrefix . 'categories.index') }}">Categories</a>
            </div>
        </div>
    </nav>

    <main class="container mb-5">
        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
