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

            $data[$locale]['slug'] = Str::limit(str()->slug($data[$locale]['name']), 255, '');
            $data[$locale]['description'] = (string) ($data[$locale]['description'] ?? '');
            $hasName = true;
        }

        return ['data' => $data, 'hasName' => $hasName];
    }

    public function handleMedia(Content $content, Request $request, bool $isUpdate = false): void
    {
        $selectedType = $request->input('media_type');
        $detectedType = ($request->file('avatar') && str_starts_with($request->file('avatar')->getMimeType(), 'video/'))
            || ($request->file('avatar_small') && str_starts_with($request->file('avatar_small')->getMimeType(), 'video/'))
            ? 'video' : 'image';
        $mediaType = $selectedType ?: $detectedType;

        if ($mediaType === 'video') {
            if ($isUpdate) {
                $avatarFile = $content->files()->where('kind', 'avatar')->first();
                if ($avatarFile) {
                    $diskKey = config('cms.disks.content');
                    $names = $avatarFile->names;
                    $deleteName = function ($name) use ($diskKey) {
                        if (is_string($name) && $name !== '') {
                            try { \Storage::disk($diskKey)->delete($name); } catch (\Throwable $e) {}
                        }
                    };
                    if (is_array($names)) {
                        foreach ($names as $v) {
                            if (is_array($v)) {
                                foreach ($v as $vv) { $deleteName($vv); }
                            } else {
                                $deleteName($v);
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
                $onlySizes = null;
            }

            if (!empty($videoFiles)) {
                \Dominservice\LaravelCms\Helpers\Media::uploadModelVideos(
                    $content,
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
                $posterOnlySizes = null;
            }

            if (!empty($posterPayload)) {
                \Dominservice\LaravelCms\Helpers\Media::uploadModelImageWithDefaults(
                    $content,
                    $posterPayload,
                    'video_poster',
                    null,
                    $posterOnlySizes,
                    true
                );
            }

            return;
        }

        if ($isUpdate) {
            $videoFile = $content->files()->where('kind', 'video_avatar')->first();
            if ($videoFile) {
                $diskKey = config('cms.disks.content_video');
                if (is_array($videoFile->names)) {
                    foreach ($videoFile->names as $name) { try { \Storage::disk($diskKey)->delete($name); } catch (\Throwable $e) {} }
                } elseif (is_string($videoFile->names) && $videoFile->names) {
                    try { \Storage::disk($diskKey)->delete($videoFile->names); } catch (\Throwable $e) {}
                }
                $videoFile->delete();
            }

            $posterFile = $content->files()->where('kind', 'video_poster')->first();
            if ($posterFile) {
                $diskKeyImg = config('cms.disks.content');
                $names = $posterFile->names;
                $deleteName = function ($name) use ($diskKeyImg) {
                    if (is_string($name) && $name !== '') {
                        try { \Storage::disk($diskKeyImg)->delete($name); } catch (\Throwable $e) {}
                    }
                };
                if (is_array($names)) {
                    foreach ($names as $v) {
                        if (is_array($v)) {
                            foreach ($v as $vv) { $deleteName($vv); }
                        } else {
                            $deleteName($v);
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
            $imageOnlySizes = null;
        }

        if (!empty($imagePayload)) {
            $kind = (string) ($request->input('avatar_kind') ?: 'avatar');
            $type = (string) ($request->input('avatar_type') ?: 'image');
            \Dominservice\LaravelCms\Helpers\Media::uploadModelImageWithDefaults(
                $content,
                $imagePayload,
                $kind,
                $type,
                $imageOnlySizes,
                true
            );
        }
    }
}
