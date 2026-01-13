<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page?->translate(app()->getLocale())?->meta_title ?? $pageConfig['label'] ?? $pageKey }}</title>
</head>
<body>
    <main>
        <h1>{{ $page?->translate(app()->getLocale())?->name ?? $pageConfig['label'] ?? $pageKey }}</h1>

        @if($page?->translate(app()->getLocale())?->description)
            <div>{!! $page->translate(app()->getLocale())->description !!}</div>
        @endif

        @if($sections->isNotEmpty())
            @foreach($sections as $sectionKey => $section)
                <section>
                    <h2>{{ $section?->translate(app()->getLocale())?->name ?? $sectionKey }}</h2>
                    {!! $section?->translate(app()->getLocale())?->description !!}
                </section>
            @endforeach
        @endif
    </main>
</body>
</html>
