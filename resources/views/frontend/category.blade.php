<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $category->translate(app()->getLocale())?->meta_title ?? $category->translate(app()->getLocale())?->name ?? 'Category' }}</title>
</head>
<body>
    <main>
        <h1>{{ $category->translate(app()->getLocale())?->name ?? $category->uuid }}</h1>
        @if($category->translate(app()->getLocale())?->description)
            <div>{!! $category->translate(app()->getLocale())->description !!}</div>
        @endif

        @if($contents->isNotEmpty())
            <ul>
                @foreach($contents as $content)
                    <li>
                        <strong>{{ $content->translate(app()->getLocale())?->name ?? $content->uuid }}</strong>
                        {!! $content->translate(app()->getLocale())?->description !!}
                    </li>
                @endforeach
            </ul>
        @endif
    </main>
</body>
</html>
