<?php

namespace Dominservice\LaravelCms\Traits;

use Illuminate\Support\Facades\Storage;

trait DynamicAvatarAccessor
{
    /**
     * Simple per-request cache for resolved media URLs to avoid repeated disk I/O.
     * Keys format: kind:size (e.g., avatar:large, video:hd, poster:small, poster:display)
     */
    protected array $_mediaUrlCache = [];

    /**
     * The model using this trait should define a protected string $fileConfigKey
     * to determine which config branch and disk to use (e.g. 'content' or 'category').
     * If it's not defined in the model, we will gracefully fall back to 'content'.
     */

    /**
     * Build URL with cache-busting version parameter based on last modified time.
     */
    protected function urlWithVersion(string $diskKey, string $name): ?string
    {
        try {
            $disk = Storage::disk($diskKey);
            if (!$disk->exists($name)) {
                return null;
            }
            $url = $disk->url($name);
            $ver = null;
            try {
                $ver = (string) $disk->lastModified($name);
            } catch (\Throwable $e) {
                $ver = null;
            }
            return $url . ($ver ? ('?v=' . $ver) : '');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Main avatar accessor. Returns URL for the configured display size.
     */
    public function getAvatarPathAttribute()
    {
        $size = $this->getConfiguredAvatarDisplaySize();
        $cacheKey = 'avatar:' . $size;
        if (array_key_exists($cacheKey, $this->_mediaUrlCache)) {
            return $this->_mediaUrlCache[$cacheKey];
        }
        $url = $this->resolveAvatarUrlForSize($size);
        $this->_mediaUrlCache[$cacheKey] = $url;
        return $url;
    }

    /**
     * Backward-compatible video path accessor shared by models using this trait.
     * It resolves URL from new *File(kind='video_avatar') by configured display size
     * and, for Content models only, falls back to legacy naming video_{uuid}.mp4
     * on the content_video disk if no metadata-based file is available.
     */
    public function getVideoPathAttribute(): ?string
    {
        $configKey = $this->getFileConfigKey(); // 'content' or 'category'
        $display = (string) (config("cms.files.$configKey.types.video_avatar.display") ?: 'hd');
        $cacheKey = 'video:' . $display;
        if (array_key_exists($cacheKey, $this->_mediaUrlCache)) {
            return $this->_mediaUrlCache[$cacheKey];
        }

        // Determine proper disk. For content videos prefer a dedicated disk.
        if ($configKey === 'content') {
            $diskKey = config('cms.disks.content_video') ?: config('cms.disks.content');
        } else {
            $diskKey = config("cms.disks.$configKey");
        }
        if (!$diskKey) {
            $this->_mediaUrlCache[$cacheKey] = null;
            return null;
        }

        // Try to locate the video file record.
        $file = null;
        if (method_exists($this, 'files')) {
            $file = $this->files()->where('kind', 'video_avatar')->first();
        } elseif (method_exists($this, 'video')) {
            $file = $this->video()->first();
        }

        if ($file && is_array($file->names)) {
            $name = $file->names[$display] ?? null;
            if (is_string($name) && $name !== '') {
                $u = $this->urlWithVersion($diskKey, $name);
                if ($u !== null) {
                    return $this->_mediaUrlCache[$cacheKey] = $u;
                }
            }
            // Fallback to any available variant
            foreach ($file->names as $n) {
                if (is_string($n) && $n !== '') {
                    $u2 = $this->urlWithVersion($diskKey, $n);
                    if ($u2 !== null) {
                        return $this->_mediaUrlCache[$cacheKey] = $u2;
                    }
                }
            }
        }

        // Legacy fallback only for Content models: video_{uuid}.mp4 on content_video disk
        if ($configKey === 'content') {
            $uuid = $this->uuid ?? null;
            if (is_string($uuid) && $uuid !== '') {
                $legacy = 'video_' . $uuid . '.mp4';
                if (Storage::disk($diskKey)->exists($legacy)) {
                    $u3 = $this->urlWithVersion($diskKey, $legacy);
                    if ($u3 !== null) {
                        return $this->_mediaUrlCache[$cacheKey] = $u3;
                    }
                }
            }
        }
        $this->_mediaUrlCache[$cacheKey] = null;
        return null;
    }

    /**
     * Main video poster accessor. Returns URL for the configured display size.
     * Backward compatible with v2: if no metadata-based poster found, tries legacy filenames
     * on the image disk and finally falls back to avatar image.
     */
    public function getVideoPosterPathAttribute(): ?string
    {
        $size = $this->getConfiguredPosterDisplaySize();
        $cacheKey = 'poster:' . $size;
        if (array_key_exists($cacheKey, $this->_mediaUrlCache)) {
            return $this->_mediaUrlCache[$cacheKey];
        }
        $url = $this->resolvePosterUrlForSize($size);
        if ($url !== null) {
            return $this->_mediaUrlCache[$cacheKey] = $url;
        }
        $diskKey = config("cms.disks." . $this->getFileConfigKey());
        if (is_string($diskKey) && $diskKey !== '') {
            $legacy = $this->resolveLegacyPosterUrl($diskKey);
            if ($legacy !== null) {
                return $this->_mediaUrlCache[$cacheKey] = $legacy;
            }
        }
        // Final fallback: use avatar image if available
        return $this->_mediaUrlCache[$cacheKey] = ($this->avatar_path ?? null);
    }

    /**
     * Intercept dynamic access like `small_avatar_path`, `large_avatar_path`, `thumb_avatar_path`.
     * Also handles `*_video_avatar_path` and `*_video_poster_path`.
     * Fallback to parent implementation for anything else.
     */
    public function __get($key)
    {
        if (!is_string($key)) {
            return parent::__get($key);
        }

        // 1) Dynamic video avatar sizes: e.g. mobile_video_avatar_path, hd_video_avatar_path
        if (str_ends_with($key, '_video_avatar_path')) {
            $size = substr($key, 0, -strlen('_video_avatar_path'));
            if ($size !== '') {
                $val = $this->resolveVideoUrlForSize($size);
                if ($val !== null) {
                    return $val;
                }
            }
        }

        // 2) Dynamic video poster sizes: e.g. small_video_poster_path, large_video_poster_path, thumb_video_poster_path
        if (str_ends_with($key, '_video_poster_path')) {
            $size = substr($key, 0, -strlen('_video_poster_path'));
            if ($size !== '') {
                $val = $this->resolvePosterUrlForSize($size);
                if ($val !== null) {
                    return $val;
                }
            }
        }

        // 3) Dynamic image avatar sizes: small_avatar_path, thumb_avatar_path (optionally mobile_thumb_avatar_path)
        if (str_ends_with($key, '_avatar_path')) {
            $raw = substr($key, 0, -strlen('_avatar_path'));
            if ($raw !== 'avatar') {
                $profile = null;
                $size = $raw;
                // Support composite key: mobile_large_avatar_path or desktop_thumb_avatar_path
                if (str_contains($raw, '_')) {
                    [$maybeProfile, $maybeSize] = explode('_', $raw, 2);
                    if (in_array($maybeProfile, ['mobile','desktop'], true) && $maybeSize) {
                        $profile = $maybeProfile;
                        $size = $maybeSize;
                    }
                }
                $val = $this->resolveAvatarUrlForSize($size, $profile);
                if ($val !== null) {
                    return $val;
                }
            }
        }
        return parent::__get($key);
    }

    protected function getConfiguredAvatarDisplaySize(): string
    {
        $key = "cms.files." . $this->getFileConfigKey() . ".types.avatar.display";
        $size = config($key);
        if (is_string($size) && $size !== '') {
            return $size;
        }
        // Sensible default if not configured
        return 'large';
    }

    protected function getConfiguredPosterDisplaySize(): string
    {
        $key = "cms.files." . $this->getFileConfigKey() . ".types.video_poster.display";
        $size = config($key);
        if (is_string($size) && $size !== '') {
            return $size;
        }
        return 'large';
    }

    protected function resolveAvatarUrlForSize(string $size, ?string $profile = null): ?string
    {
        // Try to get the avatar file record
        $file = method_exists($this, 'avatarFile') ? $this->avatarFile()->first() : (method_exists($this, 'files') ? $this->files()->where('kind', 'avatar')->first() : null);
        $diskKey = config("cms.disks." . $this->getFileConfigKey());
        if (!$diskKey) {
            return null;
        }

        if (!$file || !is_array($file->names)) {
            // No new metadata -> try legacy filename directly
            return $this->resolveLegacyAvatarUrl($diskKey);
        }

        $names = $file->names;
        // If nested by profile, pick requested profile or default to desktop if available, otherwise first profile
        if (isset($names['mobile']) || isset($names['desktop'])) {
            $bucket = null;
            if ($profile && isset($names[$profile]) && is_array($names[$profile])) {
                $bucket = $names[$profile];
            } elseif (isset($names['desktop']) && is_array($names['desktop'])) {
                $bucket = $names['desktop'];
            } else {
                // pick the first array bucket
                foreach ($names as $v) {
                    if (is_array($v)) { $bucket = $v; break; }
                }
            }
            $name = $bucket[$size] ?? null;
        } else {
            $name = $names[$size] ?? null;
        }

        if (is_string($name) && $name !== '') {
            $u = $this->urlWithVersion($diskKey, $name);
            if ($u !== null) {
                return $u;
            }
        }

        // Fallback: try legacy single-file avatar naming (prefix + uuid + .ext)
        return $this->resolveLegacyAvatarUrl($diskKey);
    }

    protected function getFileConfigKey(): string
    {
        // Use model-defined property if present and string, otherwise fallback to 'content'
        if (property_exists($this, 'fileConfigKey') && is_string($this->fileConfigKey) && $this->fileConfigKey !== '') {
            return $this->fileConfigKey;
        }
        return 'content';
    }

    /**
     * Try to resolve avatar using legacy naming like `content_{uuid}.webp` or `category{uuid}.webp`.
     */
    protected function resolveLegacyAvatarUrl(string $diskKey): ?string
    {
        // Only for images; videos are handled elsewhere and not affected.
        $prefix = $this->getFileConfigKey(); // 'content' or 'category'
        $uuid = $this->uuid ?? null;
        if (!$uuid || !is_string($uuid)) {
            return null;
        }
        $ext = ltrim((string)config('cms.avatar.extension', 'webp'), '.');

        $candidates = [
            $prefix . '_' . $uuid . '.' . $ext,
            $prefix . $uuid . '.' . $ext,
            // Also try without prefix, just in case older installs saved as {uuid}.ext
            $uuid . '.' . $ext,
        ];

        foreach ($candidates as $name) {
            if (Storage::disk($diskKey)->exists($name)) {
                $u = $this->urlWithVersion($diskKey, $name);
                if ($u !== null) {
                    return $u;
                }
            }
        }
        return null;
    }

    /**
     * Return a collection of file records that are images (files.type = 'image').
     * Falls back to empty collection if the model doesn't define files() relation.
     */
    public function imageFilesList()
    {
        if (method_exists($this, 'files')) {
            return $this->files()->where('type', 'image')->get();
        }
        return collect();
    }

    /**
     * Return a collection of file records that are videos (files.type = 'video').
     * Falls back to empty collection if the model doesn't define files() relation.
     */
    public function videoFilesList()
    {
        if (method_exists($this, 'files')) {
            return $this->files()->where('type', 'video')->get();
        }
        return collect();
    }

    /**
     * Resolve URL for a concrete video size key, e.g. 'mobile', 'sd', 'hd'.
     */
    protected function resolveVideoUrlForSize(string $size): ?string
    {
        $configKey = $this->getFileConfigKey();
        // Determine proper disk. For content videos prefer a dedicated disk.
        if ($configKey === 'content') {
            $diskKey = config('cms.disks.content_video') ?: config('cms.disks.content');
        } else {
            $diskKey = config("cms.disks.$configKey");
        }
        if (!$diskKey) {
            return null;
        }

        // Locate the video file record
        $file = null;
        if (method_exists($this, 'files')) {
            $file = $this->files()->where('kind', 'video_avatar')->first();
        } elseif (method_exists($this, 'video')) {
            $file = $this->video()->first();
        }
        if (!$file || !is_array($file->names)) {
            return null;
        }

        $name = $file->names[$size] ?? null;
        if (is_string($name) && $name !== '') {
            $u = $this->urlWithVersion($diskKey, $name);
            if ($u !== null) {
                return $u;
            }
        }
        return null;
    }

    /**
     * Resolve URL for a concrete video poster size key, e.g. 'small', 'large', 'thumb'.
     * Falls back to legacy single-file poster names from v2 if metadata is missing.
     */
    protected function resolvePosterUrlForSize(string $size): ?string
    {
        $configKey = $this->getFileConfigKey();
        $diskKey = config("cms.disks.$configKey"); // posters are images, use image disk
        if (!$diskKey) {
            return null;
        }

        // Locate the video poster file record
        $file = null;
        if (method_exists($this, 'files')) {
            $file = $this->files()->where('kind', 'video_poster')->first();
        } elseif (method_exists($this, 'videoPoster')) {
            $file = $this->videoPoster()->first();
        }
        if (!$file || !is_array($file->names)) {
            // Try legacy poster naming from v2
            return $this->resolveLegacyPosterUrl($diskKey);
        }

        $name = $file->names[$size] ?? null;
        if (is_string($name) && $name !== '') {
            $u = $this->urlWithVersion($diskKey, $name);
            if ($u !== null) {
                return $u;
            }
        }
        return null;
    }

    /**
     * Try to resolve legacy poster using common v2 naming patterns like
     * video_{uuid}.webp|jpg|jpeg|png or poster_{uuid}.webp|jpg|jpeg|png on the image disk.
     */
    protected function resolveLegacyPosterUrl(string $diskKey): ?string
    {
        $uuid = $this->uuid ?? null;
        if (!is_string($uuid) || $uuid === '') {
            return null;
        }
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
            if (Storage::disk($diskKey)->exists($name)) {
                $u = $this->urlWithVersion($diskKey, $name);
                if ($u !== null) {
                    return $u;
                }
            }
        }
        return null;
    }
}
