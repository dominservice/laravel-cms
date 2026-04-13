@php($routePrefix = rtrim(config('cms.admin.route_name_prefix', 'cms.'), '.'))
@php($routePrefix = $routePrefix === '' ? '' : $routePrefix . '.')
@php($fields = $fields ?? [])
@php($editorJsConfig = (array) config('cms.admin.category.editorjs', config('cms.admin.content.editorjs', [])))
@php($editorJsFields = array_keys((array) ($editorJsConfig['fields'] ?? [])))
@php($editorJsProfiles = (array) ($editorJsConfig['profiles'] ?? []))

<form wire:submit.prevent="save" enctype="multipart/form-data">
    @if($errors->any())
        <div class="{{ $cmsUi['card'] ?? 'card' }}">
            <div class="{{ $cmsUi['card_body'] ?? 'card-body' }}">
                <p>{{ __('cms::laravel_cms.input_problems') }}</p>
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
            <h3>{{ $category->uuid ? __('cms::laravel_cms.edit_category') : __('cms::laravel_cms.create_category') }}</h3>

            @if(in_array('type', $fields, true))
                @if(!empty($fixedType))
                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label class="{{ $cmsUi['label'] ?? '' }}">{{ __('cms::laravel_cms.type') }}</label>
                        <div>{{ $fixedType }}</div>
                    </div>
                @else
                    <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                        <label class="{{ $cmsUi['label'] ?? '' }}" for="type">{{ __('cms::laravel_cms.type') }}</label>
                        <select id="type" wire:model.defer="type" class="{{ $cmsUi['select'] ?? 'form-select' }}">
                            @foreach($types as $typeOption)
                                <option value="{{ $typeOption }}">{{ $typeOption }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @endif

            @if(in_array('parent_uuid', $fields, true))
                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="parent_uuid">{{ __('cms::laravel_cms.parent_category') }}</label>
                    <select id="parent_uuid" wire:model.defer="parent_uuid" class="{{ $cmsUi['select'] ?? 'form-select' }}">
                        <option value="">-- {{ __('cms::laravel_cms.select') }} --</option>
                        @foreach($parents as $parent)
                            <option value="{{ $parent['uuid'] }}">{{ $parent['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(in_array('media', $fields, true))
                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}">{{ __('cms::laravel_cms.media_type') }}</label>
                    <div>
                        <label>
                            <input type="radio" wire:model="media_type" value="image">
                            {{ __('cms::laravel_cms.image') }}
                        </label>
                        <label style="margin-left: 1rem;">
                            <input type="radio" wire:model="media_type" value="video">
                            {{ __('cms::laravel_cms.video') }}
                        </label>
                    </div>
                </div>

                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="image_default">{{ __('cms::laravel_cms.image_video_default') }}</label>
                    <input id="image_default" type="file" wire:model="avatar" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                    @if($avatar)
                        <div><img src="{{ $avatar->temporaryUrl() }}" alt="{{ __('cms::laravel_cms.preview') }}" style="max-width: 240px;"></div>
                    @elseif($category->video_poster_path)
                        <div><img src="{{ $category->video_poster_path }}" alt="{{ __('cms::laravel_cms.poster') }}" style="max-width: 240px;"></div>
                    @elseif($category->avatar_path)
                        <div><img src="{{ $category->avatar_path }}" alt="{{ __('cms::laravel_cms.avatar') }}" style="max-width: 240px;"></div>
                    @endif
                </div>

                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="image_small">{{ __('cms::laravel_cms.image_video_small') }}</label>
                    <input id="image_small" type="file" wire:model="avatar_small" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                    @if($avatar_small)
                        <div><img src="{{ $avatar_small->temporaryUrl() }}" alt="{{ __('cms::laravel_cms.preview') }}" style="max-width: 240px;"></div>
                    @elseif($category->small_video_poster_path)
                        <div><img src="{{ $category->small_video_poster_path }}" alt="{{ __('cms::laravel_cms.small_poster') }}" style="max-width: 240px;"></div>
                    @elseif($category->small_avatar_path)
                        <div><img src="{{ $category->small_avatar_path }}" alt="{{ __('cms::laravel_cms.small_avatar') }}" style="max-width: 240px;"></div>
                    @endif
                </div>

                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="poster">{{ __('cms::laravel_cms.poster_default') }}</label>
                    <input id="poster" type="file" wire:model="poster" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                </div>

                <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                    <label class="{{ $cmsUi['label'] ?? '' }}" for="poster_small">{{ __('cms::laravel_cms.poster_small') }}</label>
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
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="name_{{ $locale }}">{{ __('cms::laravel_cms.name') }}</label>
                                <input id="name_{{ $locale }}" type="text" wire:model.defer="translations.{{ $locale }}.name" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                            </div>
                        @endif

                        <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                            <label class="{{ $cmsUi['label'] ?? '' }}" for="slug_{{ $locale }}">{{ __('cms::laravel_cms.slug') }}</label>
                            <input id="slug_{{ $locale }}" type="text" wire:model.defer="translations.{{ $locale }}.slug" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                        </div>

                        @if(in_array('description', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="description_{{ $locale }}">{{ __('cms::laravel_cms.description') }}</label>
                                @if(in_array('description', $editorJsFields, true))
                                    @php($descriptionProfile = (string) data_get($editorJsConfig, 'fields.description.profile', 'default'))
                                    @php($descriptionMinHeight = (int) data_get($editorJsProfiles, $descriptionProfile . '.min_height', 180))
                                    <textarea id="description_{{ $locale }}" wire:model.defer="translations.{{ $locale }}.description" class="{{ $cmsUi['textarea'] ?? 'form-control' }}" rows="8" data-editorjs-input="1">{{ $translations[$locale]['description'] ?? '' }}</textarea>
                                    <div class="border border-gray-300 rounded-3 bg-white overflow-hidden d-none" data-editorjs-holder="description_{{ $locale }}" data-editorjs-profile="{{ $descriptionProfile }}" data-editorjs-min-height="{{ $descriptionMinHeight }}" wire:ignore></div>
                                @else
                                    <textarea id="description_{{ $locale }}" wire:model.defer="translations.{{ $locale }}.description" class="{{ $cmsUi['textarea'] ?? 'form-control' }}"></textarea>
                                @endif
                            </div>
                        @endif

                        @if(in_array('meta_title', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="meta_title_{{ $locale }}">{{ __('cms::laravel_cms.meta_title') }}</label>
                                <input id="meta_title_{{ $locale }}" type="text" wire:model.defer="translations.{{ $locale }}.meta_title" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                            </div>
                        @endif

                        @if(in_array('meta_keywords', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="meta_keywords_{{ $locale }}">{{ __('cms::laravel_cms.meta_keywords') }}</label>
                                <input id="meta_keywords_{{ $locale }}" type="text" wire:model.defer="translations.{{ $locale }}.meta_keywords" class="{{ $cmsUi['input'] ?? 'form-control' }}">
                            </div>
                        @endif

                        @if(in_array('meta_description', $fields, true))
                            <div class="{{ $cmsUi['form_group'] ?? 'mb-3' }}">
                                <label class="{{ $cmsUi['label'] ?? '' }}" for="meta_description_{{ $locale }}">{{ __('cms::laravel_cms.meta_description') }}</label>
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
                        {{ __('cms::laravel_cms.status') }}
                    </label>
                </div>
            @endif
        </div>
        <div class="{{ $cmsUi['card_footer'] ?? 'card-footer text-end' }}">
            <a class="{{ $cmsUi['button_secondary'] ?? 'btn btn-outline-secondary' }}" href="{{ route($routePrefix . 'category.index') }}" wire:navigate>{{ __('cms::laravel_cms.cancel') }}</a>
            <button type="submit" class="{{ $cmsUi['button'] ?? 'btn btn-primary' }}" wire:loading.attr="disabled">{{ __('cms::laravel_cms.save') }}</button>
        </div>
    </div>
</form>
