<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CMS')</title>
    @livewireStyles
    @stack('styles')
</head>
<body class="bg-slate-50 text-slate-900">
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')

    <nav class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a class="text-lg font-semibold" href="{{ route($routePrefix . 'content.index') }}">CMS</a>
            <div class="flex gap-4 text-sm font-medium">
                <a class="text-slate-600 hover:text-slate-900" href="{{ route($routePrefix . 'content.index') }}">Content</a>
                <a class="text-slate-600 hover:text-slate-900" href="{{ route($routePrefix . 'category.index') }}">Categories</a>
            </div>
        </div>
    </nav>

    <main class="{{ $cmsUi['container'] ?? 'mx-auto max-w-6xl px-6 py-6' }}">
        @if(session('status'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @yield('content')
    </main>

    @livewireScripts
    @stack('scripts')
</body>
</html>
