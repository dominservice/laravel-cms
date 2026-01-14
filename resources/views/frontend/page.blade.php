<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($translation = $content->translateOrDefault(app()->getLocale()))
    <title>{{ $translation->meta_title ?: $translation->name }}</title>
</head>
<body>
    <main>
        <h1>{{ $translation->name }}</h1>
        <div>{{ $translation->description }}</div>

        @if(!empty($blocks))
            <section>
                @foreach($blocks as $block)
                    @php($blockTranslation = $block['model']?->translateOrDefault(app()->getLocale()))
                    @if($blockTranslation)
                        <article>
                            <h2>{{ $blockTranslation->name }}</h2>
                            <div>{{ $blockTranslation->description }}</div>
                        </article>
                    @endif
                @endforeach
            </section>
        @endif
    </main>
</body>
</html>
