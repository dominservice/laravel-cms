<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($translation = $category->translateOrDefault(app()->getLocale()))
    <title>{{ $translation->meta_title ?: $translation->name }}</title>
</head>
<body>
    <main>
        <h1>{{ $translation->name }}</h1>
        <div>{{ $translation->description }}</div>

        @if($contents->isNotEmpty())
            <section>
                <h2>Contents</h2>
                <ul>
                    @foreach($contents as $content)
                        @php($contentTranslation = $content->translateOrDefault(app()->getLocale()))
                        <li>{{ $contentTranslation?->name }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</body>
</html>
