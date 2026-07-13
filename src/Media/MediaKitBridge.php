<?php

namespace Dominservice\LaravelCms\Media;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Services\MediaUploader;
use Dominservice\MediaKit\Support\Kinds\KindRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;

class MediaKitBridge
{
    public static function uploadImage(Model $model, UploadedFile|File|string $file, string $kind = 'avatar', string $policy = 'replace', ?array $filters = null): Model
    {
        /** @var MediaUploader $uploader */
        $uploader = app(MediaUploader::class);
        $uploader->uploadImage($model, $kind, $file, $policy, $filters);
        return $model;
    }

    public static function uploadVideoRendition(Model $model, UploadedFile $file, string $rendition = 'hd'): Model
    {
        /** @var MediaUploader $uploader */
        $uploader = app(MediaUploader::class);
        $uploader->uploadVideoRendition($model, $file, $rendition);
        return $model;
    }

    public static function firstUrl(Model $model, string $kind, ?string $variant = null): ?string
    {
        $collection = KindRegistry::collectionFor($kind, $kind);
        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('collection', $collection)
            ->latest()->first();

        if (!$asset) return null;
        $display = $variant ?: KindRegistry::displayVariant($kind, 'md');
        return route('mediakit.media.show', [$asset->uuid, $display, $asset->uuid.'-'.$display.'.jpg']);
    }

    public static function allUrls(Model $model, string $kind, ?string $variant = null): array
    {
        $collection = KindRegistry::collectionFor($kind, $kind);
        $display = $variant ?: KindRegistry::displayVariant($kind, 'md');
        return MediaAsset::query()
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('collection', $collection)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(MediaAsset $a) => route('mediakit.media.show', [$a->uuid, $display, $a->uuid.'-'.$display.'.jpg']))
            ->filter()
            ->values()
            ->all();
    }

    public static function videoUrl(Model $model, string $kind = 'video_avatar', ?string $rendition = null): ?string
    {
        $collection = KindRegistry::collectionFor($kind, 'video');

        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('collection', $collection)
            ->latest()
            ->first();

        if (!$asset) {
            return null;
        }

        $selectedRendition = $rendition ?: (KindRegistry::displayVariant($kind, 'hd') ?: 'hd');
        $meta = (array) ($asset->meta ?? []);
        $renditions = (array) ($meta['video_renditions'] ?? []);
        $path = $renditions[$selectedRendition] ?? null;

        if (!is_string($path) || $path === '') {
            return null;
        }

        return \Storage::disk($asset->disk)->url($path);
    }
}
