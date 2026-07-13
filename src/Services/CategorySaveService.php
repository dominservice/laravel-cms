<?php

namespace Dominservice\LaravelCms\Services;

use Dominservice\LaravelCms\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategorySaveService
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

    public function prepareTranslatableData(array $data): array
    {
        $hasName = false;
        unset($data['_token'], $data['_method']);

        $locales = (new Category())->getLocales();
        foreach ($locales as $locale) {
            if (empty($data[$locale]['name'])) {
                unset($data[$locale]);
                continue;
            }

            $providedSlug = trim((string) ($data[$locale]['slug'] ?? ''));
            $data[$locale]['slug'] = Str::limit($providedSlug !== '' ? str($providedSlug)->slug() : str()->slug($data[$locale]['name']), 255, '');
            $data[$locale]['meta_description'] = $this->truncateMetaDescription($data[$locale]['meta_description'] ?? null);
            $hasName = true;
        }

        return ['data' => $data, 'hasName' => $hasName];
    }

    public function handleMedia(Category $category, Request $request, bool $isUpdate = false): void
    {
        $selectedType = $request->input('media_type');
        $detectedType = ($request->file('avatar') && str_starts_with((string) $request->file('avatar')->getMimeType(), 'video/'))
            || ($request->file('avatar_small') && str_starts_with((string) $request->file('avatar_small')->getMimeType(), 'video/'))
            ? 'video'
            : 'image';
        $mediaType = $selectedType ?: $detectedType;

        if ($mediaType === 'video') {
            if ($isUpdate) {
                $this->deleteLegacyFileRecord($category, 'avatar', (string) config('cms.disks.category'));
                $this->clearMediaCollection($category, 'avatar');
            }

            $this->syncVideoRenditions($category, $request);
            $this->syncImageMedia(
                $category,
                $request->file('poster') ?: $request->file('small_poster'),
                (string) ($request->input('selected_poster_asset_uuid') ?: $request->input('selected_small_poster_asset_uuid')),
                'video_poster'
            );

            return;
        }

        if ($isUpdate) {
            $diskKey = (string) config('cms.disks.category');
            $this->deleteLegacyFileRecord($category, 'video_avatar', $diskKey);
            $this->deleteLegacyFileRecord($category, 'video_poster', $diskKey);
            $this->clearMediaCollection($category, 'video_avatar');
            $this->clearMediaCollection($category, 'video_poster');
        }

        $this->syncImageMedia(
            $category,
            $request->file('avatar') ?: $request->file('avatar_small'),
            (string) ($request->input('selected_avatar_asset_uuid') ?: $request->input('selected_avatar_small_asset_uuid')),
            'avatar'
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

    private function syncVideoRenditions(Category $category, Request $request): void
    {
        if (! $this->supportsMediaKit($category) || ! class_exists(\Dominservice\MediaKit\Services\MediaUploader::class)) {
            $this->syncLegacyVideos($category, $request);
            return;
        }

        $hasAnyVideo = false;
        $collection = (string) (\Dominservice\MediaKit\Support\Kinds\KindRegistry::collectionFor('video_avatar', 'video'));

        if ($request->file('avatar')) {
            $category->clearMediaCollection($collection);
            \Dominservice\MediaKit\Services\MediaUploader::uploadVideoRendition($category, $request->file('avatar'), 'hd');
            $hasAnyVideo = true;
        }

        if ($request->file('avatar_small')) {
            if (! $hasAnyVideo) {
                $category->clearMediaCollection($collection);
            }

            \Dominservice\MediaKit\Services\MediaUploader::uploadVideoRendition($category, $request->file('avatar_small'), 'mobile');
        }
    }

    private function syncLegacyVideos(Category $category, Request $request): void
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
            \Dominservice\LaravelCms\Helpers\Media::uploadModelVideos($category, $videoFiles, 'video_avatar', null, $onlySizes, true);
        }
    }

    private function syncImageMedia(Category $category, mixed $source, string $selectedAssetUuid, string $kind): void
    {
        if ($source === null && $selectedAssetUuid === '') {
            return;
        }

        $diskKey = (string) config('cms.disks.category');
        $this->deleteLegacyFileRecord($category, $kind, $diskKey);

        if ($source !== null && $this->supportsMediaKit($category)) {
            \Dominservice\LaravelCms\Media\MediaKitBridge::uploadImage($category, $source, $kind, 'replace');
            return;
        }

        if ($selectedAssetUuid !== '' && $this->attachExistingAsset($category, $selectedAssetUuid, $kind)) {
            return;
        }

        if ($source !== null) {
            \Dominservice\LaravelCms\Helpers\Media::uploadModelImageWithDefaults($category, ['default' => $source], $kind, null, null, true);
        }
    }

    private function attachExistingAsset(Category $category, string $uuid, string $kind): bool
    {
        if (! $this->supportsMediaKit($category) || ! class_exists(\Dominservice\MediaKit\Models\MediaAsset::class)) {
            return false;
        }

        $asset = \Dominservice\MediaKit\Models\MediaAsset::query()->find($uuid);
        if (! $asset) {
            return false;
        }

        $collection = (string) (\Dominservice\MediaKit\Support\Kinds\KindRegistry::collectionFor($kind, $kind));
        $category->attachExistingMedia($asset, $collection, 'replace');

        return true;
    }

    private function clearMediaCollection(Category $category, string $kind): void
    {
        if (! $this->supportsMediaKit($category)) {
            return;
        }

        $collection = (string) (\Dominservice\MediaKit\Support\Kinds\KindRegistry::collectionFor($kind, $kind));
        $category->clearMediaCollection($collection);
    }

    private function deleteLegacyFileRecord(Category $category, string $kind, string $diskKey): void
    {
        $file = $category->files()->where('kind', $kind)->first();
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
