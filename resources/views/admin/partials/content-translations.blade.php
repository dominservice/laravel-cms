@foreach($locales as $locale)
    @php($translation = $content->translate($locale) ?? null)
    <fieldset style="margin-bottom: 1.5rem;">
        <legend>{{ strtoupper($locale) }}</legend>

        <div>
            <label for="name_{{ $locale }}">Name</label>
            <input id="name_{{ $locale }}" type="text" name="translations[{{ $locale }}][name]" value="{{ old("translations.$locale.name", $translation->name ?? '') }}">
        </div>

        <div>
            <label for="sub_name_{{ $locale }}">Sub name</label>
            <input id="sub_name_{{ $locale }}" type="text" name="translations[{{ $locale }}][sub_name]" value="{{ old("translations.$locale.sub_name", $translation->sub_name ?? '') }}">
        </div>

        <div>
            <label for="short_description_{{ $locale }}">Short description</label>
            <textarea id="short_description_{{ $locale }}" name="translations[{{ $locale }}][short_description]">{{ old("translations.$locale.short_description", $translation->short_description ?? '') }}</textarea>
        </div>

        <div>
            <label for="description_{{ $locale }}">Description</label>
            <textarea id="description_{{ $locale }}" name="translations[{{ $locale }}][description]">{{ old("translations.$locale.description", $translation->description ?? '') }}</textarea>
        </div>

        <div>
            <label for="meta_title_{{ $locale }}">Meta title</label>
            <input id="meta_title_{{ $locale }}" type="text" name="translations[{{ $locale }}][meta_title]" value="{{ old("translations.$locale.meta_title", $translation->meta_title ?? '') }}">
        </div>

        <div>
            <label for="meta_keywords_{{ $locale }}">Meta keywords</label>
            <input id="meta_keywords_{{ $locale }}" type="text" name="translations[{{ $locale }}][meta_keywords]" value="{{ old("translations.$locale.meta_keywords", $translation->meta_keywords ?? '') }}">
        </div>

        <div>
            <label for="meta_description_{{ $locale }}">Meta description</label>
            <textarea id="meta_description_{{ $locale }}" name="translations[{{ $locale }}][meta_description]">{{ old("translations.$locale.meta_description", $translation->meta_description ?? '') }}</textarea>
        </div>
    </fieldset>
@endforeach
