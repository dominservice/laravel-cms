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
            $filename = Name::generateImageName($entityKey . '-' . $kind . '-' . $sizeKey);
            if ($cfg === null) {
                // original: keep original dimensions, but re-encode to configured extension
                $processed = self::reencodeWebp($binary);
            } else {
                $w = Arr::get($cfg, 'w');
                $h = Arr::get($cfg, 'h');
                $fit = Arr::get($cfg, 'fit', 'contain'); // contain | cover
                $processed = self::resize($binary, (int)$w, (int)$h, (string)$fit);
            }
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
            $record = self::upsertContentFile($model, $kind, $type, $names, $diskKey, $replaceExisting);
        } elseif ($model instanceof Category) {
            $record = self::upsertCategoryFile($model, $kind, $type, $names, $diskKey, $replaceExisting);
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

    protected static function upsertContentFile(Content $model, string $kind, ?string $type, array $names, string $diskKey, bool $replace): ContentFile
    {
        $query = ContentFile::query()->where('content_uuid', $model->uuid)->where('kind', $kind);
        if ($type !== null) {
            $query->where('type', $type);
        }
        $existing = $query->first();

        if ($existing && $replace) {
            self::deletePhysicalFiles($existing->names ?? [], $diskKey);
            $existing->names = $names;
            $existing->save();
            return $existing;
        }

        return ContentFile::create([
            'content_uuid' => $model->uuid,
            'kind' => $kind,
            'type' => $type,
            'names' => $names,
        ]);
    }

    protected static function upsertCategoryFile(Category $model, string $kind, ?string $type, array $names, string $diskKey, bool $replace): CategoryFile
    {
        $query = CategoryFile::query()->where('category_uuid', $model->uuid)->where('kind', $kind);
        if ($type !== null) {
            $query->where('type', $type);
        }
        $existing = $query->first();

        if ($existing && $replace) {
            self::deletePhysicalFiles($existing->names ?? [], $diskKey);
            $existing->names = $names;
            $existing->save();
            return $existing;
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
        foreach ($names as $name) {
            if (is_string($name) && $disk->exists($name)) {
                $disk->delete($name);
            }
        }
    }
}
