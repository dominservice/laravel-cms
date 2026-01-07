<?php

namespace Dominservice\LaravelCms\Helpers;

use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\CategoryFile;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Models\ContentFile;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class Media
{
    /**
     * Detect legacy single-file assets for a given model/kind and map them into names array
     * using the configured display key. This is used to backfill records created in v2
     * where files existed on disk but no *_files metadata was present.
     *
     * For images (kind = 'avatar') we look for:
     *  - content_{uuid}.webp, content{uuid}.webp, {uuid}.webp on the entity disk
     * For content videos (kind = 'video_avatar') we look for:
     *  - video_{uuid}.mp4 or .webm on the content_video (or content) disk
     */
    protected static function detectLegacyNames(Model $model, string $kind, string $diskKey): array
    {
        $out = [];
        // Determine entity key and display size config for mapping
        $entityKey = $model instanceof Category ? 'category' : ($model instanceof Content ? 'content' : null);
        if (!$entityKey) { return $out; }

        $displayKey = (string) (config("cms.files.$entityKey.types.$kind.display") ?: ($kind === 'avatar' ? 'large' : ($kind === 'video_avatar' ? 'hd' : 'large')));

        // Legacy for avatar images
        if ($kind === 'avatar') {
            $uuid = $model->uuid ?? null;
            if (is_string($uuid) && $uuid !== '') {
                $ext = ltrim((string)config('cms.avatar.extension', 'webp'), '.');
                $candidates = [
                    $entityKey . '_' . $uuid . '.' . $ext,
                    $entityKey . $uuid . '.' . $ext,
                    $uuid . '.' . $ext,
                ];
                $disk = Storage::disk($diskKey);
                foreach ($candidates as $name) {
                    if ($disk->exists($name)) {
                        $out[$displayKey] = $name;
                        break;
                    }
                }
            }
        }

        // Legacy for content video single file
        if ($kind === 'video_avatar' && $model instanceof Content) {
            $uuid = $model->uuid ?? null;
            if (is_string($uuid) && $uuid !== '') {
                // Prefer dedicated video disk if configured
                $videoDiskKey = config('cms.disks.content_video') ?: $diskKey;
                $disk = Storage::disk($videoDiskKey);
                $candidates = [
                    'video_' . $uuid . '.mp4',
                    'video_' . $uuid . '.webm',
                ];
                foreach ($candidates as $name) {
                    if ($disk->exists($name)) {
                        $out[$displayKey] = $name;
                        break;
                    }
                }
            }
        }

        // Legacy for content video poster (single image stored without metadata in v2)
        if ($kind === 'video_poster' && $model instanceof Content) {
            $uuid = $model->uuid ?? null;
            if (is_string($uuid) && $uuid !== '') {
                $disk = Storage::disk($diskKey); // posters are on image disk
                $candidates = [
                    'video_' . $uuid . '.webp',
                    'video_' . $uuid . '.jpg',
                    'video_' . $uuid . '.jpeg',
                    'video_' . $uuid . '.png',
                    'poster_' . $uuid . '.webp',
                    'poster_' . $uuid . '.jpg',
                    'poster_' . $uuid . '.jpeg',
                    'poster_' . $uuid . '.png',
                ];
                foreach ($candidates as $name) {
                    if ($disk->exists($name)) {
                        $out[$displayKey] = $name;
                        break;
                    }
                }
            }
        }
        return $out;
    }
    /**
     * Upload one source image for a model and generate multiple sizes.
     * - If $onlySizes is null, all sizes from config will be generated (including 'original' if defined)
     * - If $onlySizes is provided (e.g. ['large','thumb']), only those keys will be generated (if present in config)
     * - Updates or creates the related *File record and synchronizes filenames in DB
     * - When replacing, old files will be removed from the storage disk
     *
     * @param Content|Category $model Target model instance
     * @param UploadedFile|string $source UploadedFile or absolute path to an existing image file
     * @param string $kind e.g. 'avatar' or 'additional' (must exist under config sizes)
     * @param string|null $type optional subtype to distinguish variants inside same kind (e.g. gallery)
     * @param array<string>|null $onlySizes subset of sizes to generate; null = all sizes from config
     * @param bool $replaceExisting when true and a record exists for (model,kind,type), old files will be deleted and DB will be updated
     * @return ContentFile|CategoryFile
     */
    public static function uploadModelImage(Model $model, UploadedFile|string $source, string $kind = 'avatar', ?string $type = null, ?array $onlySizes = null, bool $replaceExisting = true): Model
    {
        [$entityKey, $diskKey, $sizesCfg] = self::resolveEntityContext($model, $kind);
        // Enforce semantic: files.type indicates media kind ('image'|'video'). For images we default to 'image'.
        if ($type === null) {
            /** @deprecated od v4 – deleguj do MediaKitBridge::uploadImage() z zachowaniem sygnatury. */
            return \Dominservice\LaravelCms\Media\MediaKitBridge::uploadImage($model, $source, $kind, $replaceExisting ? 'replace' : 'keep', $filters ?? null);
        }


        // Filter sizes according to $onlySizes
        $sizes = $sizesCfg['sizes'] ?? [];
        if ($onlySizes !== null) {
            $sizes = array_intersect_key($sizes, array_flip($onlySizes));
        }
        if (empty($sizes)) {
            throw new InvalidArgumentException("No sizes defined for {$entityKey}.types.{$kind}");
        }

        // Read binary of source image
        $binary = self::readSourceBinary($source);
        if ($binary === null) {
            throw new InvalidArgumentException('Cannot read source image.');
        }

        // Prepare disk
        /** @var FilesystemContract $disk */
        $disk = Storage::disk($diskKey);

        // Generate and store files
        $names = [];
        foreach ($sizes as $sizeKey => $cfg) {
            // Skip 'original' entries (null config) to avoid storing original file
            if ($cfg === null) {
                continue;
            }
            $filename = Name::generateImageName($entityKey . '-' . $kind . '-' . $sizeKey);
            $w = Arr::get($cfg, 'w');
            $h = Arr::get($cfg, 'h');
            $fit = Arr::get($cfg, 'fit', 'contain'); // contain | cover
            $processed = self::resize($binary, (int)$w, (int)$h, (string)$fit);
            if ($processed === null) {
                // Skip if processing failed
                continue;
            }
            $disk->put($filename, $processed);
            $names[$sizeKey] = $filename;
        }

        if (empty($names)) {
            throw new InvalidArgumentException('No image variant has been generated.');
        }

        // Upsert DB record and cleanup old files if needed
        if ($model instanceof Content) {
            $record = self::upsertContentFile($model, $kind, $type, $names, $diskKey, $replaceExisting, $onlySizes);
        } elseif ($model instanceof Category) {
            $record = self::upsertCategoryFile($model, $kind, $type, $names, $diskKey, $replaceExisting, $onlySizes);
        } else {
            throw new InvalidArgumentException('Unsupported model given.');
        }

        return $record;
    }

    /**
     * Determine entity config branch and sizes definition for given model and kind.
     *
     * @return array{0:string,1:string,2:array}
     */
    protected static function resolveEntityContext(Model $model, string $kind): array
    {
        if ($model instanceof Content) {
            $entityKey = 'content';
        } elseif ($model instanceof Category) {
            $entityKey = 'category';
        } else {
            throw new InvalidArgumentException('Model must be instance of Content or Category.');
        }

        $diskKey = config("cms.disks.{$entityKey}");
        $sizesCfg = config("cms.files.{$entityKey}.types.{$kind}");
        if (!$diskKey || !$sizesCfg) {
            throw new InvalidArgumentException("Missing config for entity {$entityKey} or kind {$kind}.");
        }
        return [$entityKey, $diskKey, $sizesCfg];
    }

    /**
     * Merge new names into existing and compute files to delete (for partial replace).
     * Returns [array $mergedNames, array $toDeleteNames]
     */
    protected static function mergeNamesWithDeletions(array $existingNames, array $newNames): array
    {
        $toDelete = [];
        $walker = function($newPart, $oldPart) use (&$walker, &$toDelete) {
            if (!is_array($newPart) || !is_array($oldPart)) {
                return;
            }
            foreach ($newPart as $k => $v) {
                if (!array_key_exists($k, $oldPart)) {
                    continue;
                }
                if (is_array($v) && is_array($oldPart[$k])) {
                    $walker($v, $oldPart[$k]);
                } else {
                    $candidate = $oldPart[$k];
                    if (is_array($candidate)) {
                        foreach ($candidate as $vv) { if (is_string($vv)) { $toDelete[] = $vv; } }
                    } elseif (is_string($candidate)) {
                        $toDelete[] = $candidate;
                    }
                }
            }
        };
        $walker($newNames, $existingNames);
        $merged = array_replace_recursive($existingNames, $newNames);
        return [$merged, $toDelete];
    }

    protected static function upsertContentFile(Content $model, string $kind, ?string $type, array $names, string $diskKey, bool $replace, ?array $onlySizes = null): ContentFile
    {
        $query = ContentFile::query()->where('content_uuid', $model->uuid)->where('kind', $kind);
        if ($type !== null) {
            $query->where('type', $type);
        }
        $existing = $query->first();

        if ($existing && $replace) {
            // Also remove potential legacy single-file video before replacing
            if (str_starts_with($kind, 'video')) {
                self::deleteLegacyVideoForContent($model);
            }

            $existingNames = is_array($existing->names) ? $existing->names : [];

            if ($onlySizes === null) {
                // Full replace: delete all old files and overwrite names entirely
                self::deletePhysicalFiles($existingNames, $diskKey);
                $existing->names = $names;
            } else {
                // Partial replace: delete only the files for sizes being replaced and merge names
                [$merged, $toDelete] = self::mergeNamesWithDeletions($existingNames, $names);
                if (!empty($toDelete)) {
                    self::deletePhysicalFiles($toDelete, $diskKey);
                }
                $existing->names = $merged;
            }

            $existing->save();
            return $existing;
        }

        // When there is no existing record, try to backfill legacy single-file assets into metadata
        if (!$existing) {
            $legacy = self::detectLegacyNames($model, $kind, $diskKey);
            // Merge only if target key is missing
            foreach ($legacy as $k => $v) {
                if (!isset($names[$k])) {
                    $names[$k] = $v;
                }
            }
        }

        // If we are creating a new video record and replaceExisting is requested, clean legacy too (after we captured legacy name into metadata)
        if ($replace && !$existing && str_starts_with($kind, 'video')) {
            self::deleteLegacyVideoForContent($model);
        }

        return ContentFile::create([
            'content_uuid' => $model->uuid,
            'kind' => $kind,
            'type' => $type,
            'names' => $names,
        ]);
    }

    protected static function upsertCategoryFile(Category $model, string $kind, ?string $type, array $names, string $diskKey, bool $replace, ?array $onlySizes = null): CategoryFile
    {
        $query = CategoryFile::query()->where('category_uuid', $model->uuid)->where('kind', $kind);
        if ($type !== null) {
            $query->where('type', $type);
        }
        $existing = $query->first();

        if ($existing && $replace) {
            $existingNames = is_array($existing->names) ? $existing->names : [];

            if ($onlySizes === null) {
                // Full replace: delete all old files and overwrite names entirely
                self::deletePhysicalFiles($existingNames, $diskKey);
                $existing->names = $names;
            } else {
                // Partial replace: delete only the files for sizes being replaced and merge names
                [$merged, $toDelete] = self::mergeNamesWithDeletions($existingNames, $names);
                if (!empty($toDelete)) {
                    self::deletePhysicalFiles($toDelete, $diskKey);
                }
                $existing->names = $merged;
            }

            $existing->save();
            return $existing;
        }

        // Backfill legacy avatar file for categories when creating new record
        if (!$existing) {
            $legacy = self::detectLegacyNames($model, $kind, $diskKey);
            foreach ($legacy as $k => $v) {
                if (!isset($names[$k])) {
                    $names[$k] = $v;
                }
            }
        }

        return CategoryFile::create([
            'category_uuid' => $model->uuid,
            'kind' => $kind,
            'type' => $type,
            'names' => $names,
        ]);
    }

    /** Read binary content from UploadedFile or path */
    protected static function readSourceBinary(UploadedFile|string $source): ?string
    {
        if ($source instanceof UploadedFile) {
            return @file_get_contents($source->getRealPath());
        }
        if (is_string($source)) {
            return @file_get_contents($source);
        }
        return null;
    }

    /** Re-encode to webp with default quality */
    protected static function reencodeWebp(string $binary, int $quality = 90): ?string
    {
        $im = @imagecreatefromstring($binary);
        if (!$im) {
            return null;
        }
        // Preserve alpha
        imagealphablending($im, true);
        imagesavealpha($im, true);
        ob_start();
        imagewebp($im, null, $quality);
        $data = ob_get_clean();
        imagedestroy($im);
        return $data === false ? null : $data;
    }

    /** Resize according to fit mode: contain (letterbox) or cover (crop center) */
    protected static function resize(string $binary, int $targetW, int $targetH, string $fit = 'contain', int $quality = 90): ?string
    {
        $src = @imagecreatefromstring($binary);
        if (!$src) {
            return null;
        }
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        if ($fit === 'cover') {
            // scale to cover then crop center
            $scale = max($targetW / $srcW, $targetH / $srcH);
            $newW = (int)ceil($srcW * $scale);
            $newH = (int)ceil($srcH * $scale);
            $tmp = imagecreatetruecolor($newW, $newH);
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
            imagefilledrectangle($tmp, 0, 0, $newW, $newH, $transparent);
            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

            // crop center to target
            $x = (int)max(0, ($newW - $targetW) / 2);
            $y = (int)max(0, ($newH - $targetH) / 2);
            $dst = imagecreatetruecolor($targetW, $targetH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent2 = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent2);
            imagecopy($dst, $tmp, 0, 0, $x, $y, $targetW, $targetH);
            imagedestroy($tmp);
        } else { // contain
            $scale = min($targetW / $srcW, $targetH / $srcH);
            $newW = (int)floor($srcW * $scale);
            $newH = (int)floor($srcH * $scale);
            $dst = imagecreatetruecolor($targetW, $targetH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent);
            $dstX = (int)floor(($targetW - $newW) / 2);
            $dstY = (int)floor(($targetH - $newH) / 2);
            imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
        }

        ob_start();
        imagewebp($dst, null, $quality);
        $out = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        return $out === false ? null : $out;
    }

    /** Delete old files if they exist on disk */
    protected static function deletePhysicalFiles(array $names, string $diskKey): void
    {
        $disk = Storage::disk($diskKey);
        $walker = function($items) use (&$walker, $disk) {
            foreach ($items as $name) {
                if (is_array($name)) {
                    $walker($name);
                } elseif (is_string($name) && $disk->exists($name)) {
                    $disk->delete($name);
                }
            }
        };
        $walker($names);
    }

    /**
     * Delete legacy single-file video for Content imported from v2 (video_{uuid}.mp4 etc.).
     * Does nothing if file not found.
     */
    protected static function deleteLegacyVideoForContent(Content $model): void
    {
        $diskKey = config('cms.disks.content_video') ?: config('cms.disks.content');
        $uuid = $model->uuid ?? null;
        if (!is_string($uuid) || $uuid === '') {
            return;
        }
        $candidates = [
            'video_' . $uuid . '.mp4',
            'video_' . $uuid . '.webm',
        ];
        $disk = Storage::disk($diskKey);
        foreach ($candidates as $name) {
            if ($disk->exists($name)) {
                $disk->delete($name);
            }
        }
    }

    /**
     * Upload two sources in one call (responsive): separate images for mobile and desktop.
     * - Provide sources as ['mobile' => UploadedFile|string, 'desktop' => UploadedFile|string]
     * - Uses the same sizes config as single upload (no profile-specific sizes). Each profile generates its own set.
     * - Stores DB names as nested arrays: ['mobile' => [size=>filename,...], 'desktop' => [...]]
     * - Skips generation of any 'original' (null) entry to avoid storing the original file as requested.
     *
     * @param Content|Category $model
     * @param array{mobile?:UploadedFile|string,desktop?:UploadedFile|string} $sources
     * @param string $kind
     * @param string|null $type
     * @param array<string>|null $onlySizes
     * @param bool $replaceExisting
     * @return ContentFile|CategoryFile
     */
    public static function uploadModelResponsiveImages(Model $model, array $sources, string $kind = 'avatar', ?string $type = null, ?array $onlySizes = null, bool $replaceExisting = true): Model
    {
        // As of current version, uploads must NOT be split into mobile/desktop profiles.
        // Only sizes declared in configuration are allowed. Use uploadModelImage() instead.
        throw new InvalidArgumentException('Responsive uploads (mobile/desktop) are no longer supported. Use uploadModelImage() with sizes defined in config(cms.files.*).');
    }

    /**
     * Upload with default + per-size overrides in a single call.
     * Example sources:
     *  [
     *    'default' => UploadedFile|string, // used for all sizes unless overridden
     *    'thumb'   => UploadedFile|string, // optional: override only for 'thumb'
     *    'small'   => UploadedFile|string, // optional: override only for 'small'
     *  ]
     * Behavior:
     *  - Ignores 'original' (null) size entries in config (no original file stored)
     *  - For each configured size key (except 'original'), picks the override if present, otherwise uses 'default'
     *  - If a size has neither override nor default, it will be skipped
     *  - Throws if no variant could be generated
     *
     * @param Content|Category $model
     * @param array<string,UploadedFile|string> $sourcesBySize
     * @param string $kind
     * @param string|null $type
     * @param array<string>|null $onlySizes
     * @param bool $replaceExisting
     * @return ContentFile|CategoryFile
     */
    public static function uploadModelImageWithDefaults(Model $model, array $sourcesBySize, string $kind = 'avatar', ?string $type = null, ?array $onlySizes = null, bool $replaceExisting = true): Model
    {
        [$entityKey, $diskKey, $sizesCfg] = self::resolveEntityContext($model, $kind);

        $sizes = $sizesCfg['sizes'] ?? [];
        if ($onlySizes !== null) {
            /** @deprecated od v4 – deleguj do MediaKitBridge::uploadImage() z domyślnymi filtrami. */
            return \Dominservice\LaravelCms\Media\MediaKitBridge::uploadImage($model, $sourcesBySize, $kind, $replaceExisting ? 'replace' : 'keep', $filters ?? null);
        }

        if (empty($sizes)) {
            throw new InvalidArgumentException("No sizes defined for {$entityKey}.types.{$kind}");
        }

        /** @var FilesystemContract $disk */
        $disk = Storage::disk($diskKey);

        $names = [];
        foreach ($sizes as $sizeKey => $cfg) {
            // Skip 'original' (null) definitions
            if ($cfg === null) {
                continue;
            }
            $sourceForSize = $sourcesBySize[$sizeKey] ?? ($sourcesBySize['default'] ?? null);
            if ($sourceForSize === null) {
                // no source for this size; skip silently
                continue;
            }
            $binary = self::readSourceBinary($sourceForSize);
            if ($binary === null) {
                // cannot read provided source; skip this size
                continue;
            }
            $filename = Name::generateImageName($entityKey . '-' . $kind . '-' . $sizeKey);
            $w = Arr::get($cfg, 'w');
            $h = Arr::get($cfg, 'h');
            $fit = Arr::get($cfg, 'fit', 'contain');
            $processed = self::resize($binary, (int)$w, (int)$h, (string)$fit);
            if ($processed === null) {
                continue;
            }
            $disk->put($filename, $processed);
            $names[$sizeKey] = $filename;
        }

        if (empty($names)) {
            throw new InvalidArgumentException('No image variant has been generated. Provide at least a default source or per-size overrides for selected sizes.');
        }

        if ($model instanceof Content) {
            $record = self::upsertContentFile($model, $kind, $type, $names, $diskKey, $replaceExisting, $onlySizes);
        } elseif ($model instanceof Category) {
            $record = self::upsertCategoryFile($model, $kind, $type, $names, $diskKey, $replaceExisting, $onlySizes);
        } else {
            throw new InvalidArgumentException('Unsupported model given.');
        }

        return $record;
    }

    /**
     * Upload multiple pre-encoded video files (by sizes) for a model as a single logical entry.
     * - Does NOT transcode. It simply stores provided files and records their names per size.
     * - Uses files.{entity}.types.{kind}.sizes to validate allowed size keys and to pick a display size elsewhere.
     * - For Content videos, files are stored on the 'content_video' disk.
     *
     * @param Content|Category $model
     * @param array<string, UploadedFile|string> $sourcesBySize Map of sizeKey => UploadedFile|string
     * @param string $kind Logical kind, defaults to 'video_avatar'
     * @param string|null $type Optional subtype
     * @param array<string>|null $onlySizes Optional whitelist of size keys to accept
     * @param bool $replaceExisting Replace and delete previous files if record exists
     * @return ContentFile|CategoryFile
     */
    public static function uploadModelVideos(Model $model, array $sourcesBySize, string $kind = 'video_avatar', ?string $type = null, ?array $onlySizes = null, bool $replaceExisting = true): Model
    {
        // Default semantic for videos: files.type = 'video' unless explicitly set
        if ($type === null) {
            /** @deprecated od v4 – deleguj do MediaKitBridge::uploadVideoRendition() dla każdej pozycji. */
            foreach ($sourcesBySize as $rendition => $file) {
                if ($file) {
                    \Dominservice\LaravelCms\Media\MediaKitBridge::uploadVideoRendition($model, $file, is_string($rendition)?$rendition:'hd'); }
            }

            return $model;
        }

        // Determine entity and sizes config
        $entityKey = null;
        if ($model instanceof Content) {
            $entityKey = 'content';
        } elseif ($model instanceof Category) {
            $entityKey = 'category';
        } else {
            throw new InvalidArgumentException('Model must be instance of Content or Category.');
        }

        // For video we prefer a dedicated disk (only implemented for content). Fallback to entity disk if missing.
        $diskKey = $entityKey === 'content' ? (config('cms.disks.content_video') ?: config('cms.disks.content')) : config('cms.disks.category');

        $sizesCfg = config("cms.files.{$entityKey}.types.{$kind}");
        if (!$sizesCfg || !isset($sizesCfg['sizes']) || !is_array($sizesCfg['sizes'])) {
            throw new InvalidArgumentException("Missing sizes config for {$entityKey}.types.{$kind}");
        }
        $allowedSizes = array_keys($sizesCfg['sizes']);

        // Filter provided sources by allowed sizes (and optional onlySizes)
        $candidateKeys = array_keys($sourcesBySize);
        if ($onlySizes !== null) {
            $candidateKeys = array_values(array_intersect($candidateKeys, $onlySizes));
        }
        $keys = array_values(array_intersect($candidateKeys, $allowedSizes));
        if (empty($keys)) {
            throw new InvalidArgumentException('No acceptable video sizes provided.');
        }

        /** @var FilesystemContract $disk */
        $disk = Storage::disk($diskKey);

        $names = [];
        foreach ($keys as $sizeKey) {
            $src = $sourcesBySize[$sizeKey];
            $binary = self::readSourceBinary($src);
            if ($binary === null) {
                throw new InvalidArgumentException("Cannot read source for size {$sizeKey}.");
            }
            // Determine extension
            $ext = 'mp4';
            if ($src instanceof UploadedFile) {
                $ext = $src->getClientOriginalExtension() ?: ($src->extension() ?: 'mp4');
            } elseif (is_string($src)) {
                $pathExt = pathinfo($src, PATHINFO_EXTENSION);
                if (is_string($pathExt) && $pathExt !== '') {
                    $ext = $pathExt;
                }
            }
            $filename = Name::generateVideoName($entityKey . '-' . $kind . '-' . $sizeKey, (string)$ext);
            $disk->put($filename, $binary);
            $names[$sizeKey] = $filename;
        }

        if (empty($names)) {
            throw new InvalidArgumentException('No video files were stored.');
        }

        // Upsert DB metadata into the files table for consistency with images
        if ($model instanceof Content) {
            return self::upsertContentFile($model, $kind, $type, $names, $diskKey, $replaceExisting, $onlySizes);
        }
        // For Category, reuse category files table and its disk
        if ($model instanceof Category) {
            return self::upsertCategoryFile($model, $kind, $type, $names, $diskKey, $replaceExisting, $onlySizes);
        }

        // Should never reach here due to earlier guard
        throw new InvalidArgumentException('Unsupported model given.');
    }
}
