@php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
@php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')
@php($fields = $fields ?? [])

<form wire:submit.prevent="save" enctype="multipart/form-data">
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
                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label class="{{ $cmsUi['label'] ?? '' }}">Type</label>
                        <div>{{ $fixedType }}</div>
                    </div>
                @else
                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label class="{{ $cmsUi['label'] ?? '' }}" for="type">Type</label>
                        <select id="type" wire:model.defer="type" class="{{ $cmsUi['select'] ?? 'form-select' }}">
                            @foreach($types as $typeOption)
                                <option value="{{ $typeOption }}">{{ $typeOption }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @endif

            @if(in_array('category_uuid', $fields, true))
                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="category_uuid">Category</label>
                    <select id="category_uuid" wire:model.defer="category_uuid" class="{{ $cmsUi['select'] ?? 'form-select' }}">
                        <option value="">-- Select --</option>
                        @foreach($categories as $category)
                            <option value="{{ $category['uuid'] }}">{{ $category['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(in_array('media', $fields, true))
                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}">Media type</label>
                    <div>
                        <label>
                            <input type="radio" wire:model="media_type" value="image">
                            Image
                        </label>
                        <label style="margin-left: 1rem;">
                            <input type="radio" wire:model="media_type" value="video">
                            Video
                        </label>
                    </div>
                </div>

                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="image_default">Image / Video (default)</label>
                    <input id="image_default" type="file" wire:model="avatar" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                    @if($avatar)
                        <div><img src="{{ $avatar->temporaryUrl() }}" alt="Preview" style="max-width: 240px;"></div>
                    @elseif($content->video_poster_path)
                        <div><img src="{{ $content->video_poster_path }}" alt="Poster" style="max-width: 240px;"></div>
                    @elseif($content->avatar_path)
                        <div><img src="{{ $content->avatar_path }}" alt="Avatar" style="max-width: 240px;"></div>
                    @endif
                </div>

                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="image_small">Image / Video (small)</label>
                    <input id="image_small" type="file" wire:model="avatar_small" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                    @if($avatar_small)
                        <div><img src="{{ $avatar_small->temporaryUrl() }}" alt="Preview" style="max-width: 240px;"></div>
                    @elseif($content->small_video_poster_path)
                        <div><img src="{{ $content->small_video_poster_path }}" alt="Small poster" style="max-width: 240px;"></div>
                    @elseif($content->small_avatar_path)
                        <div><img src="{{ $content->small_avatar_path }}" alt="Small avatar" style="max-width: 240px;"></div>
                    @endif
                </div>

                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="poster">Poster (default)</label>
                    <input id="poster" type="file" wire:model="poster" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                </div>

                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="poster_small">Poster (small)</label>
                    <input id="poster_small" type="file" wire:model="small_poster" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                </div>
            @endif

            @foreach($locales as $locale)
                <div class="{{ $cmsUi['card'] ?? 'card' }}" style="margin-bottom: 1rem;" wire:key="locale-{{ $locale }}">
                    <div class="{{ $cmsUi['card_header'] ?? 'card-header' }}">
                        <div class="{{ $cmsUi['card_title'] ?? 'card-title' }}">{{ strtoupper($locale) }}</div>
                    </div>
                    <div class="{{ $cmsUi['card_body'] ?? 'card-body' }}">
                        @if(in_array('name', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="name_{{ $locale }}">Name</label>
                                <input id="name_{{ $locale }}" type="text" wire:model.defer="translations.{{ $locale }}.name" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                            </div>
                        @endif

                        @if(in_array('sub_name', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="sub_name_{{ $locale }}">Sub name</label>
                                <input id="sub_name_{{ $locale }}" type="text" wire:model.defer="translations.{{ $locale }}.sub_name" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                            </div>
                        @endif

                        @if(in_array('short_description', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="short_description_{{ $locale }}">Short description</label>
                                <textarea id="short_description_{{ $locale }}" wire:model.defer="translations.{{ $locale }}.short_description" class="{{ $cmsUi['textarea'] ?? 'form-control' }}"></textarea>
                            </div>
                        @endif

                        @if(in_array('description', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="description_{{ $locale }}">Description</label>
                                <textarea id="description_{{ $locale }}" wire:model.defer="translations.{{ $locale }}.description" class="{{ $cmsUi['textarea'] ?? 'form-control' }}"></textarea>
                            </div>
                        @endif

                        @if(in_array('meta_title', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="meta_title_{{ $locale }}">Meta title</label>
                                <input id="meta_title_{{ $locale }}" type="text" wire:model.defer="translations.{{ $locale }}.meta_title" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                            </div>
                        @endif

                        @if(in_array('meta_keywords', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="meta_keywords_{{ $locale }}">Meta keywords</label>
                                <input id="meta_keywords_{{ $locale }}" type="text" wire:model.defer="translations.{{ $locale }}.meta_keywords" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                            </div>
                        @endif

                        @if(in_array('meta_description', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="meta_description_{{ $locale }}">Meta description</label>
                                <textarea id="meta_description_{{ $locale }}" wire:model.defer="translations.{{ $locale }}.meta_description" class="{{ $cmsUi['textarea'] ?? 'form-control' }}"></textarea>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            @if(in_array('status', $fields, true))
                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label>
                        <input type="checkbox" wire:model.defer="status">
                        Status
                    </label>
                </div>
            @endif

            @if(in_array('is_nofollow', $fields, true))
                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label>
                        <input type="checkbox" wire:model.defer="is_nofollow">
                        No follow
                    </label>
                </div>
            @endif

            @if(in_array('external_url', $fields, true))
                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="external_url">External URL</label>
                    <input id="external_url" type="text" wire:model.defer="external_url" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                </div>
            @endif
        </div>
        <div class="{{ $cmsUi['card_footer'] ?? 'card-footer text-end' }}">
            <a class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" href="{{ route($routePrefix . 'content.index') }}" wire:navigate>Cancel</a>
            <button type="submit" class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}" wire:loading.attr="disabled">Save</button>
        </div>
    </div>
</form>
