@extends('cms::layouts.admin')

@section('title', $content->uuid ? 'Edit Content' : 'Create Content')

@section('content')
    @php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
    @php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')
    @php($fields = $fields ?? [])

    @php($createRoute = $blockKey
        ? route($routePrefix . 'content.block.store', ['section' => $sectionKey, 'blockKey' => $blockKey])
        : route($routePrefix . 'content.section.store', ['section' => $sectionKey])
    )

    <form method="post" action="{{ $content->uuid ? route($routePrefix . 'content.update', $content) : $createRoute }}" enctype="multipart/form-data">
        @csrf
        @if($content->uuid)
            @method('put')
        @endif

        <input type="hidden" name="section" value="{{ $sectionKey }}">
        @if($blockKey)
            <input type="hidden" name="block" value="{{ $blockKey }}">
        @endif
        @if(!empty($configKey))
            <input type="hidden" name="config_key" value="{{ $configKey }}">
        @endif
        @if(!empty($configHandle))
            <input type="hidden" name="config_handle" value="{{ $configHandle }}">
        @endif

        @if($errors->any())
            <div class="{{ $cmsUi['card'] ?? 'card' }}">
                <div class="{{ $cmsUi['card_body'] ?? 'card-body' }}">
                    <p>There were some problems with your input.</p>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="{{ $cmsUi['card'] ?? 'card' }}">
            <div class="{{ $cmsUi['card_body'] ?? 'card-body' }}">
                <h3>{{ $content->uuid ? 'Edit Content' : 'Create Content' }}</h3>

                @if(in_array('type', $fields, true))
                    @if(!empty($fixedType))
                        <input type="hidden" name="type" value="{{ $fixedType }}">
                    @else
                        <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                            <label class="{{ $cmsUi['label'] ?? '' }}" for="type">Type</label>
                            <select id="type" name="type" class="{{ $cmsUi['select'] ?? 'form-select' }}">
                                @foreach($types as $type)
                                    <option value="{{ $type }}" @selected(old('type', $content->type) === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endif

                @if(in_array('category_uuid', $fields, true))
                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label class="{{ $cmsUi['label'] ?? '' }}" for="category_uuid">Category</label>
                        <select id="category_uuid" name="category_uuid" class="{{ $cmsUi['select'] ?? 'form-select' }}">
                            <option value="">-- Select --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->uuid }}" @selected(old('category_uuid', $content->categories->pluck('uuid')->first()) === $category->uuid)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if(in_array('media', $fields, true))
                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label class="{{ $cmsUi['label'] ?? '' }}">Media type</label>
                        <div>
                            <label>
                                <input type="radio" name="media_type" value="image" @checked(!$content->video_path)>
                                Image
                            </label>
                            <label style="margin-left: 1rem;">
                                <input type="radio" name="media_type" value="video" @checked((bool) $content->video_path)>
                                Video
                            </label>
                        </div>
                    </div>

                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label class="{{ $cmsUi['label'] ?? '' }}" for="image_default">Image / Video (default)</label>
                        <input id="image_default" type="file" name="avatar" class="{{ $cmsUi['input'] ?? 'form-control' }}" accept="image/*">
                        <input type="file" id="posterInput" name="poster" hidden>
                        <input type="hidden" id="posterInputWidth" name="poster_width">
                        <input type="hidden" id="posterInputHeight" name="poster_height">
                        @if($content->video_poster_path)
                            <div><img src="{{ $content->video_poster_path }}" alt="Poster" style="max-width: 240px;"></div>
                        @elseif($content->avatar_path)
                            <div><img src="{{ $content->avatar_path }}" alt="Avatar" style="max-width: 240px;"></div>
                        @endif
                    </div>

                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label class="{{ $cmsUi['label'] ?? '' }}" for="image_small">Image / Video (small)</label>
                        <input id="image_small" type="file" name="avatar_small" class="{{ $cmsUi['input'] ?? 'form-control' }}" accept="image/*">
                        <input type="file" id="smallPosterInput" name="small_poster" hidden>
                        <input type="hidden" id="smallPosterInputWidth" name="small_poster_width">
                        <input type="hidden" id="smallPosterInputHeight" name="small_poster_height">
                        @if($content->small_video_poster_path)
                            <div><img src="{{ $content->small_video_poster_path }}" alt="Small poster" style="max-width: 240px;"></div>
                        @elseif($content->small_avatar_path)
                            <div><img src="{{ $content->small_avatar_path }}" alt="Small avatar" style="max-width: 240px;"></div>
                        @endif
                    </div>
                @endif

                @foreach($locales as $locale)
                    @php($translation = $content->translate($locale) ?? null)
                    <div class="{{ $cmsUi['card'] ?? 'card' }}" style="margin-bottom: 1rem;">
                        <div class="{{ $cmsUi['card_header'] ?? 'card-header' }}">
                            <div class="{{ $cmsUi['card_title'] ?? 'card-title' }}">{{ strtoupper($locale) }}</div>
                        </div>
                        <div class="{{ $cmsUi['card_body'] ?? 'card-body' }}">
                            @if(in_array('name', $fields, true))
                                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                    <label class="{{ $cmsUi['label'] ?? '' }}" for="name_{{ $locale }}">Name</label>
                                    <input id="name_{{ $locale }}" type="text" name="{{ $locale }}[name]" class="{{ $cmsUi['input'] ?? 'form-control' }}" value="{{ old($locale . '.name', $translation->name ?? '') }}">
                                </div>
                            @endif

                            @if(in_array('sub_name', $fields, true))
                                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                    <label class="{{ $cmsUi['label'] ?? '' }}" for="sub_name_{{ $locale }}">Sub name</label>
                                    <input id="sub_name_{{ $locale }}" type="text" name="{{ $locale }}[sub_name]" class="{{ $cmsUi['input'] ?? 'form-control' }}" value="{{ old($locale . '.sub_name', $translation->sub_name ?? '') }}">
                                </div>
                            @endif

                            @if(in_array('short_description', $fields, true))
                                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                    <label class="{{ $cmsUi['label'] ?? '' }}" for="short_description_{{ $locale }}">Short description</label>
                                    <textarea id="short_description_{{ $locale }}" name="{{ $locale }}[short_description]" class="{{ $cmsUi['textarea'] ?? 'form-control' }}">{{ old($locale . '.short_description', $translation->short_description ?? '') }}</textarea>
                                </div>
                            @endif

                            @if(in_array('description', $fields, true))
                                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                    <label class="{{ $cmsUi['label'] ?? '' }}" for="description_{{ $locale }}">Description</label>
                                    <textarea id="description_{{ $locale }}" name="{{ $locale }}[description]" class="{{ $cmsUi['textarea'] ?? 'form-control' }}">{{ old($locale . '.description', $translation->description ?? '') }}</textarea>
                                </div>
                            @endif

                            @if(in_array('meta_title', $fields, true))
                                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                    <label class="{{ $cmsUi['label'] ?? '' }}" for="meta_title_{{ $locale }}">Meta title</label>
                                    <input id="meta_title_{{ $locale }}" type="text" name="{{ $locale }}[meta_title]" class="{{ $cmsUi['input'] ?? 'form-control' }}" value="{{ old($locale . '.meta_title', $translation->meta_title ?? '') }}">
                                </div>
                            @endif

                            @if(in_array('meta_keywords', $fields, true))
                                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                    <label class="{{ $cmsUi['label'] ?? '' }}" for="meta_keywords_{{ $locale }}">Meta keywords</label>
                                    <input id="meta_keywords_{{ $locale }}" type="text" name="{{ $locale }}[meta_keywords]" class="{{ $cmsUi['input'] ?? 'form-control' }}" value="{{ old($locale . '.meta_keywords', $translation->meta_keywords ?? '') }}">
                                </div>
                            @endif

                            @if(in_array('meta_description', $fields, true))
                                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                    <label class="{{ $cmsUi['label'] ?? '' }}" for="meta_description_{{ $locale }}">Meta description</label>
                                    <textarea id="meta_description_{{ $locale }}" name="{{ $locale }}[meta_description]" class="{{ $cmsUi['textarea'] ?? 'form-control' }}">{{ old($locale . '.meta_description', $translation->meta_description ?? '') }}</textarea>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if(in_array('status', $fields, true))
                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label>
                            <input type="checkbox" name="status" value="1" @checked(old('status', $content->status))>
                            Status
                        </label>
                    </div>
                @endif

                @if(in_array('is_nofollow', $fields, true))
                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label>
                            <input type="checkbox" name="is_nofollow" value="1" @checked(old('is_nofollow', $content->is_nofollow))>
                            No follow
                        </label>
                    </div>
                @endif

                @if(in_array('external_url', $fields, true))
                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label class="{{ $cmsUi['label'] ?? '' }}" for="external_url">External URL</label>
                        <input id="external_url" type="text" name="external_url" class="{{ $cmsUi['input'] ?? 'form-control' }}" value="{{ old('external_url', $content->external_url) }}">
                    </div>
                @endif
            </div>
            <div class="{{ $cmsUi['card_footer'] ?? 'card-footer text-end' }}">
                <a class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" href="{{ route($routePrefix . 'content.index') }}">Cancel</a>
                <button type="submit" class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}">Save</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    const mediaTypeRadios = document.querySelectorAll('input[name="media_type"]');
    const mainInput = document.getElementById('image_default');
    const smallInput = document.getElementById('image_small');

    const mapping = {
        image_default: { poster: 'posterInput', w: 'posterInputWidth', h: 'posterInputHeight', key: 'main' },
        image_small:   { poster: 'smallPosterInput', w: 'smallPosterInputWidth', h: 'smallPosterInputHeight', key: 'small' }
    };

    function updateAccept() {
        const selected = document.querySelector('input[name="media_type"]:checked')?.value || 'image';
        const accept = selected === 'video' ? 'video/*' : 'image/*';
        if (mainInput) { mainInput.value = ''; mainInput.setAttribute('accept', accept); }
        if (smallInput) { smallInput.value = ''; smallInput.setAttribute('accept', accept); }
        clearPoster('main');
        clearPoster('small');
    }

    function clearPoster(whichKey) {
        const map = Object.values(mapping).find(m => m.key === whichKey);
        if (!map) return;
        const p = document.getElementById(map.poster);
        const pw = document.getElementById(map.w);
        const ph = document.getElementById(map.h);
        if (p) p.value = '';
        if (pw) pw.value = '';
        if (ph) ph.value = '';
    }

    mediaTypeRadios.forEach(r => r.addEventListener('change', updateAccept));
    updateAccept();

    Object.keys(mapping).forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('change', async (e) => {
            const file = e.target.files && e.target.files[0];
            const map = mapping[id];
            const posterInput = document.getElementById(map.poster);
            const posterInputWidth = document.getElementById(map.w);
            const posterInputHeight = document.getElementById(map.h);

            clearPoster(map.key);
            if (!file) return;
            if (!file.type || !file.type.startsWith('video/')) {
                return;
            }

            const video = document.createElement('video');
            video.preload = 'metadata';
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            video.src = URL.createObjectURL(file);
            await new Promise((resolve) => {
                video.addEventListener('loadeddata', resolve, { once: true });
            });

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const webpBlob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', 0.9));
            if (!webpBlob) return;

            const posterFile = new File([webpBlob], 'poster.webp', { type: 'image/webp' });
            const dt = new DataTransfer();
            dt.items.add(posterFile);
            posterInput.files = dt.files;
            posterInputWidth.value = video.videoWidth;
            posterInputHeight.value = video.videoHeight;

            URL.revokeObjectURL(video.src);
        });
    });
</script>
@endpush
