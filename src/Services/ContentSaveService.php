<?php

namespace Dominservice\LaravelCms\Services;

use Dominservice\LaravelCms\Models\Content;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentSaveService
{
    public function validationRules(): array
    {
        return [
            'media_type' => 'nullable|in:image,video',
            'avatar' => 'nullable|file|mimes:mp4,mov,avi,jpeg,png,jpg,webp,avif,webm',
            'avatar_small' => 'nullable|file|mimes:mp4,mov,avi,jpeg,png,jpg,webp,avif,webm',
            'poster' => 'nullable|file|mimes:jpeg,png,jpg,webp,avif',
            'small_poster' => 'nullable|file|mimes:jpeg,png,jpg,webp,avif',
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
            $data[$locale]['meta_description'] = $this->truncateMetaDescription($data[$locale]['meta_description'] ?? null);
            $hasName = true;
        }

        unset($type);

        return ['data' => $data, 'hasName' => $hasName];
    }

    public function handleMedia(Content $content, Request $request, bool $isUpdate = false): void
    {
        $selectedType = $request->input('media_type');
        $detectedType = ($request->file('avatar') && str_starts_with((string) $request->file('avatar')->getMimeType(), 'video/'))
            || ($request->file('avatar_small') && str_starts_with((string) $request->file('avatar_small')->getMimeType(), 'video/'))
            ? 'video'
            : 'image';
        $mediaType = $selectedType ?: $detectedType;

        if ($mediaType === 'video') {
            if ($isUpdate) {
                $this->deleteLegacyFileRecord($content, 'avatar', (string) config('cms.disks.content'));
                $this->clearMediaCollection($content, 'avatar');
            }

            $this->syncVideoRenditions($content, $request);
            $this->syncImageMedia(
                $content,
                $request->file('poster') ?: $request->file('small_poster'),
                (string) ($request->input('selected_poster_asset_uuid') ?: $request->input('selected_small_poster_asset_uuid')),
                'video_poster'
            );

            return;
        }

        if ($isUpdate) {
            $videoDisk = (string) (config('cms.disks.content_video') ?: config('cms.disks.content'));
            $imageDisk = (string) config('cms.disks.content');

            $this->deleteLegacyFileRecord($content, 'video_avatar', $videoDisk);
            $this->deleteLegacyFileRecord($content, 'video_poster', $imageDisk);
            $this->clearMediaCollection($content, 'video_avatar');
            $this->clearMediaCollection($content, 'video_poster');
        }

        $this->syncImageMedia(
            $content,
            $request->file('avatar') ?: $request->file('avatar_small'),
            (string) ($request->input('selected_avatar_asset_uuid') ?: $request->input('selected_avatar_small_asset_uuid')),
            (string) ($request->input('avatar_kind') ?: 'avatar')
        );
    }

    private function truncateMetaDescription(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::limit((string) $value, $this->metaDescriptionLength(), '');
    }

    private function metaDescriptionLength(): int
    {
        return max(1, (int) config('cms.meta_description_length', 255));
    }

    private function syncVideoRenditions(Content $content, Request $request): void
    {
        if (! $this->supportsMediaKit($content) || ! class_exists(\Dominservice\MediaKit\Services\MediaUploader::class)) {
            $this->syncLegacyVideos($content, $request);
            return;
        }

        $hasAnyVideo = false;
        $collection = (string) (\Dominservice\MediaKit\Support\Kinds\KindRegistry::collectionFor('video_avatar', 'video'));

        if ($request->file('avatar')) {
            $content->clearMediaCollection($collection);
            \Dominservice\MediaKit\Services\MediaUploader::uploadVideoRendition($content, $request->file('avatar'), 'hd');
            $hasAnyVideo = true;
        }

        if ($request->file('avatar_small')) {
            if (! $hasAnyVideo) {
                $content->clearMediaCollection($collection);
            }

            \Dominservice\MediaKit\Services\MediaUploader::uploadVideoRendition($content, $request->file('avatar_small'), 'mobile');
        }
    }

    private function syncLegacyVideos(Content $content, Request $request): void
    {
        $videoFiles = [];
        $onlySizes = null;

        if ($request->file('avatar') && ! $request->file('avatar_small')) {
            $videoFiles['hd'] = $request->file('avatar');
            $onlySizes = ['hd'];
        } elseif (! $request->file('avatar') && $request->file('avatar_small')) {
            $videoFiles['mobile'] = $request->file('avatar_small');
            $onlySizes = ['mobile'];
        } elseif ($request->file('avatar') && $request->file('avatar_small')) {
            $videoFiles['hd'] = $request->file('avatar');
            $videoFiles['mobile'] = $request->file('avatar_small');
        }

        if ($videoFiles !== []) {
            \Dominservice\LaravelCms\Helpers\Media::uploadModelVideos($content, $videoFiles, 'video_avatar', null, $onlySizes, true);
        }
    }

    private function syncImageMedia(Content $content, mixed $source, string $selectedAssetUuid, string $kind): void
    {
        if ($source === null && $selectedAssetUuid === '') {
            return;
        }

        $diskKey = (string) config('cms.disks.content');
        $this->deleteLegacyFileRecord($content, $kind, $diskKey);

        if ($source !== null && $this->supportsMediaKit($content)) {
            \Dominservice\LaravelCms\Media\MediaKitBridge::uploadImage($content, $source, $kind, 'replace');
            return;
        }

        if ($selectedAssetUuid !== '' && $this->attachExistingAsset($content, $selectedAssetUuid, $kind)) {
            return;
        }

        if ($source !== null) {
            \Dominservice\LaravelCms\Helpers\Media::uploadModelImageWithDefaults($content, ['default' => $source], $kind, null, null, true);
        }
    }

    private function attachExistingAsset(Content $content, string $uuid, string $kind): bool
    {
        if (! $this->supportsMediaKit($content) || ! class_exists(\Dominservice\MediaKit\Models\MediaAsset::class)) {
            return false;
        }

        $asset = \Dominservice\MediaKit\Models\MediaAsset::query()->find($uuid);
        if (! $asset) {
            return false;
        }

        $collection = (string) (\Dominservice\MediaKit\Support\Kinds\KindRegistry::collectionFor($kind, $kind));
        $content->attachExistingMedia($asset, $collection, 'replace');

        return true;
    }

    private function clearMediaCollection(Content $content, string $kind): void
    {
        if (! $this->supportsMediaKit($content)) {
            return;
        }

        $collection = (string) (\Dominservice\MediaKit\Support\Kinds\KindRegistry::collectionFor($kind, $kind));
        $content->clearMediaCollection($collection);
    }

    private function deleteLegacyFileRecord(Content $content, string $kind, string $diskKey): void
    {
        $file = $content->files()->where('kind', $kind)->first();
        if (! $file) {
            return;
        }

        $this->deletePhysicalNames($file->names, $diskKey);
        $file->delete();
    }

    private function deletePhysicalNames(mixed $names, string $diskKey): void
    {
        $deleteName = function (string $name) use ($diskKey): void {
            if ($name === '') {
                return;
            }

            try {
                \Storage::disk($diskKey)->delete($name);
            } catch (\Throwable $e) {
            }
        };

        if (is_array($names)) {
            array_walk_recursive($names, function ($value) use ($deleteName): void {
                if (is_string($value)) {
                    $deleteName($value);
                }
            });

            return;
        }

        if (is_string($names)) {
            $deleteName($names);
        }
    }

    private function supportsMediaKit(Model $model): bool
    {
        return method_exists($model, 'media')
            && method_exists($model, 'clearMediaCollection')
            && method_exists($model, 'attachExistingMedia')
            && class_exists(\Dominservice\LaravelCms\Media\MediaKitBridge::class);
    }
}
