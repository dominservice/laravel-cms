<?php

namespace Dominservice\LaravelCms\Services;

use Dominservice\LaravelCms\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentSaveService
{
    public function validationRules(): array
    {
        return [
            'media_type' => 'nullable|in:image,video',
            'avatar' => 'nullable|file|mimes:mp4,mov,avi,jpeg,png,jpg,webp,webm',
            'avatar_small' => 'nullable|file|mimes:mp4,mov,avi,jpeg,png,jpg,webp,webm',
            'poster' => 'nullable|file|mimes:jpeg,png,jpg,webp',
            'small_poster' => 'nullable|file|mimes:jpeg,png,jpg,webp',
            'selected_avatar_asset_uuid' => 'nullable|uuid',
            'selected_avatar_small_asset_uuid' => 'nullable|uuid',
            'selected_poster_asset_uuid' => 'nullable|uuid',
            'selected_small_poster_asset_uuid' => 'nullable|uuid',
        ];
    }

    public function prepareTranslatableData(array $data, string $type): array
    {
        $hasName = false;
        unset($data['_token'], $data['_method']);

        $locales = (new Content())->getLocales();
        foreach ($locales as $locale) {
            if (empty($data[$locale]['name'])) {
                unset($data[$locale]);
                continue;
            }

            $providedSlug = trim((string) ($data[$locale]['slug'] ?? ''));
            $data[$locale]['slug'] = Str::limit($providedSlug !== '' ? str($providedSlug)->slug() : str()->slug($data[$locale]['name']), 255, '');
            $data[$locale]['description'] = (string) ($data[$locale]['description'] ?? '');
            $hasName = true;
        }

        return ['data' => $data, 'hasName' => $hasName];
    }

    public function handleMedia(Content $content, Request $request, bool $isUpdate = false): void
    {
        (new \Dominservice\LaravelCms\Services\ContentSaveService())->handleMedia($content, $request, $isUpdate);
    }
}
