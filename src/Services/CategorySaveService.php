<?php

namespace Dominservice\LaravelCms\Services;

use Dominservice\LaravelCms\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategorySaveService
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
        $detectedType = ($request->file('avatar') && str_starts_with($request->file('avatar')->getMimeType(), 'video/'))
            || ($request->file('avatar_small') && str_starts_with($request->file('avatar_small')->getMimeType(), 'video/'))
            ? 'video'
            : 'image';
        $mediaType = $selectedType ?: $detectedType;

        if ($mediaType === 'video') {
            if ($isUpdate) {
                $avatarFile = $category->files()->where('kind', 'avatar')->first();
                if ($avatarFile) {
                    $diskKey = config('cms.disks.category');
                    $names = $avatarFile->names;
                    $deleteName = function ($name) use ($diskKey) {
                        if (is_string($name) && $name !== '') {
                            try {
                                \Storage::disk($diskKey)->delete($name);
                            } catch (\Throwable $e) {
                            }
                        }
                    };

                    if (is_array($names)) {
                        foreach ($names as $value) {
                            if (is_array($value)) {
                                foreach ($value as $nested) {
                                    $deleteName($nested);
                                }
                            } else {
                                $deleteName($value);
                            }
                        }
                    } else {
                        $deleteName($names);
                    }

                    $avatarFile->delete();
                }
            }

            $hasHd = (bool) $request->file('avatar');
            $hasMobile = (bool) $request->file('avatar_small');
            $videoFiles = [];
            $onlySizes = null;

            if ($hasHd && !$hasMobile) {
                $videoFiles['hd'] = $request->file('avatar');
                $onlySizes = ['hd'];
            } elseif (!$hasHd && $hasMobile) {
                $videoFiles['mobile'] = $request->file('avatar_small');
                $onlySizes = ['mobile'];
            } elseif ($hasHd && $hasMobile) {
                $videoFiles['hd'] = $request->file('avatar');
                $videoFiles['mobile'] = $request->file('avatar_small');
            }

            if (!empty($videoFiles)) {
                \Dominservice\LaravelCms\Helpers\Media::uploadModelVideos(
                    $category,
                    $videoFiles,
                    'video_avatar',
                    null,
                    $onlySizes,
                    true
                );
            }

            $defaultPoster = $request->file('poster');
            $smallPoster = $request->file('small_poster');
            $posterPayload = [];
            $posterOnlySizes = null;

            if ($defaultPoster && !$smallPoster) {
                $posterPayload['default'] = $defaultPoster;
                $posterOnlySizes = ['large'];
            } elseif (!$defaultPoster && $smallPoster) {
                $posterPayload['small'] = $smallPoster;
                $posterOnlySizes = ['small'];
            } elseif ($defaultPoster && $smallPoster) {
                $posterPayload = ['default' => $defaultPoster, 'small' => $smallPoster];
            }

            if (!empty($posterPayload)) {
                \Dominservice\LaravelCms\Helpers\Media::uploadModelImageWithDefaults(
                    $category,
                    $posterPayload,
                    'video_poster',
                    null,
                    $posterOnlySizes,
                    true
                );
            } else {
                $this->importSelectedImageAssets($category, [
                    'default' => $request->input('selected_poster_asset_uuid'),
                    'small' => $request->input('selected_small_poster_asset_uuid'),
                ], 'video_poster');
            }

            return;
        }

        if ($isUpdate) {
            $videoFile = $category->files()->where('kind', 'video_avatar')->first();
            if ($videoFile) {
                $diskKey = config('cms.disks.category');
                if (is_array($videoFile->names)) {
                    foreach ($videoFile->names as $name) {
                        try {
                            \Storage::disk($diskKey)->delete($name);
                        } catch (\Throwable $e) {
                        }
                    }
                } elseif (is_string($videoFile->names) && $videoFile->names) {
                    try {
                        \Storage::disk($diskKey)->delete($videoFile->names);
                    } catch (\Throwable $e) {
                    }
                }
                $videoFile->delete();
            }

            $posterFile = $category->files()->where('kind', 'video_poster')->first();
            if ($posterFile) {
                $diskKey = config('cms.disks.category');
                $names = $posterFile->names;
                $deleteName = function ($name) use ($diskKey) {
                    if (is_string($name) && $name !== '') {
                        try {
                            \Storage::disk($diskKey)->delete($name);
                        } catch (\Throwable $e) {
                        }
                    }
                };

                if (is_array($names)) {
                    foreach ($names as $value) {
                        if (is_array($value)) {
                            foreach ($value as $nested) {
                                $deleteName($nested);
                            }
                        } else {
                            $deleteName($value);
                        }
                    }
                } else {
                    $deleteName($names);
                }

                $posterFile->delete();
            }
        }

        $hasDefaultImg = (bool) $request->file('avatar');
        $hasSmallImg = (bool) $request->file('avatar_small');
        $imagePayload = [];
        $imageOnlySizes = null;

        if ($hasDefaultImg && !$hasSmallImg) {
            $imagePayload['default'] = $request->file('avatar');
            $imageOnlySizes = ['large'];
        } elseif (!$hasDefaultImg && $hasSmallImg) {
            $imagePayload['small'] = $request->file('avatar_small');
            $imageOnlySizes = ['small'];
        } elseif ($hasDefaultImg && $hasSmallImg) {
            $imagePayload = [
                'default' => $request->file('avatar'),
                'small' => $request->file('avatar_small'),
            ];
        }

        if (!empty($imagePayload)) {
            \Dominservice\LaravelCms\Helpers\Media::uploadModelImageWithDefaults(
                $category,
                $imagePayload,
                'avatar',
                null,
                $imageOnlySizes,
                true
            );
        } else {
            $this->importSelectedImageAssets($category, [
                'default' => $request->input('selected_avatar_asset_uuid'),
                'small' => $request->input('selected_avatar_small_asset_uuid'),
            ], 'avatar');
        }
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

    private function importSelectedImageAssets(Category $category, array $selectedAssets, string $kind): void
    {
        $payload = [];
        $onlySizes = null;

        if (!empty($selectedAssets['default'])) {
            $path = $this->temporaryPathFromMediaAsset((string) $selectedAssets['default']);
            if ($path) {
                $payload['default'] = $path;
                $onlySizes = ['large'];
            }
        }

        if (!empty($selectedAssets['small'])) {
            $path = $this->temporaryPathFromMediaAsset((string) $selectedAssets['small']);
            if ($path) {
                $payload['small'] = $path;
                $onlySizes = isset($payload['default']) ? null : ['small'];
            }
        }

        if (empty($payload)) {
            return;
        }

        \Dominservice\LaravelCms\Helpers\Media::uploadModelImageWithDefaults(
            $category,
            $payload,
            $kind,
            'image',
            $onlySizes,
            true
        );
    }

    private function temporaryPathFromMediaAsset(string $uuid): ?string
    {
        if (!class_exists(\Dominservice\MediaKit\Models\MediaAsset::class)) {
            return null;
        }

        $asset = \Dominservice\MediaKit\Models\MediaAsset::query()->find($uuid);
        if (!$asset) {
            return null;
        }

        $disk = \Storage::disk($asset->disk);
        if (!$disk->exists($asset->original_path)) {
            return null;
        }

        $binary = $disk->get($asset->original_path);
        $tmp = tempnam(sys_get_temp_dir(), 'cms-media-');
        if ($tmp === false) {
            return null;
        }

        $ext = $asset->original_ext ?: pathinfo($asset->original_path, PATHINFO_EXTENSION) ?: 'bin';
        $target = $tmp . '.' . $ext;
        file_put_contents($target, $binary);
        @unlink($tmp);

        return $target;
    }
}
