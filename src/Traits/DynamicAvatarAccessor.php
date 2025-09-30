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
     * Caches for resolving files and config keys without triggering Eloquent magic getters.
     * Declared explicitly to avoid "Indirect modification of overloaded property" errors.
     */
    protected array $__fileByLogicalKindCache = [];
    protected array $__resolvedFileConfigKeyMap = [];

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
        // Resolve the actual file by logical kind
        $file = $this->resolveFileByLogicalKind('video_avatar');
        $resolvedKind = $file->kind ?? 'video_avatar';
        $configKey = $this->getFileConfigKey($resolvedKind); // mapped by actual file kind when possible
        $display = (string) (config("cms.files.$configKey.types.video_avatar.display") ?: 'hd');
        $cacheKey = 'video:' . $display;
        if (array_key_exists($cacheKey, $this->_mediaUrlCache)) {
            return $this->_mediaUrlCache[$cacheKey];
        }

        // Determine proper disk. For content videos prefer a dedicated disk.
        $baseKey = (property_exists($this, 'fileConfigKey') && is_string($this->fileConfigKey) && $this->fileConfigKey !== '') ? $this->fileConfigKey : 'content';
        if ($configKey === 'content') {
            $diskKey = config('cms.disks.content_video') ?: config('cms.disks.content');
        } else {
            $diskKey = config("cms.disks.$configKey");
            if (!$diskKey) {
                // Fallback to base model disk (and for content base use content_video when available)
                $diskKey = $baseKey === 'content'
                    ? (config('cms.disks.content_video') ?: config('cms.disks.content'))
                    : config("cms.disks.$baseKey");
            }
        }
        if (!$diskKey) {
            $this->_mediaUrlCache[$cacheKey] = null;
            return null;
        }

        // Use the resolved file if available
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
        $file = $this->resolveFileByLogicalKind('video_poster');
        $configKey = $this->getFileConfigKey($file->kind ?? 'video_poster');
        $diskKey = config("cms.disks." . $configKey);
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
        $file = $this->resolveFileByLogicalKind('avatar');
        $configKey = $this->getFileConfigKey($file->kind ?? 'avatar');
        $key = "cms.files." . $configKey . ".types.avatar.display";
        $size = config($key);
        if (is_string($size) && $size !== '') {
            return $size;
        }
        // Sensible default if not configured
        return 'large';
    }

    protected function getConfiguredPosterDisplaySize(): string
    {
        $file = $this->resolveFileByLogicalKind('video_poster');
        $configKey = $this->getFileConfigKey($file->kind ?? 'video_poster');
        $key = "cms.files." . $configKey . ".types.video_poster.display";
        $size = config($key);
        if (is_string($size) && $size !== '') {
            return $size;
        }
        return 'large';
    }

    protected function resolveAvatarUrlForSize(string $size, ?string $profile = null): ?string
    {
        // Resolve file by logical kind using optional mapping (e.g., avatar -> testowy)
        $file = $this->resolveFileByLogicalKind('avatar');
        $configKey = $this->getFileConfigKey($file->kind ?? 'avatar');
        $baseKey = (property_exists($this, 'fileConfigKey') && is_string($this->fileConfigKey) && $this->fileConfigKey !== '') ? $this->fileConfigKey : 'content';
        $diskKey = config("cms.disks." . $configKey) ?: config("cms.disks." . $baseKey);
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
            $name = is_array($bucket) ? ($bucket[$size] ?? null) : null;

            // If the requested size is missing, try any available variant in the selected bucket
            if ((!is_string($name) || $name === '') && is_array($bucket)) {
                foreach ($bucket as $candidate) {
                    if (is_string($candidate) && $candidate !== '') {
                        $u = $this->urlWithVersion($diskKey, $candidate);
                        if ($u !== null) {
                            return $u;
                        }
                    }
                }
            }

            // If still not found, try other profiles and their variants
            foreach ($names as $prof => $profBucket) {
                if (!is_array($profBucket)) { continue; }
                if ($profile !== null && $prof === $profile) { continue; }
                // exact size in other profile
                if (isset($profBucket[$size]) && is_string($profBucket[$size]) && $profBucket[$size] !== '') {
                    $u = $this->urlWithVersion($diskKey, $profBucket[$size]);
                    if ($u !== null) { return $u; }
                }
                // any variant in other profile
                foreach ($profBucket as $candidate) {
                    if (is_string($candidate) && $candidate !== '') {
                        $u = $this->urlWithVersion($diskKey, $candidate);
                        if ($u !== null) { return $u; }
                    }
                }
            }
        } else {
            // Flat structure: try requested size, then any available variant
            $name = $names[$size] ?? null;
            if (is_string($name) && $name !== '') {
                $u = $this->urlWithVersion($diskKey, $name);
                if ($u !== null) {
                    return $u;
                }
            }
            foreach ($names as $candidate) {
                if (is_string($candidate) && $candidate !== '') {
                    $u = $this->urlWithVersion($diskKey, $candidate);
                    if ($u !== null) {
                        return $u;
                    }
                }
            }
        }

        // Fallback: try legacy single-file avatar naming (prefix + uuid + .ext)
        return $this->resolveLegacyAvatarUrl($diskKey);
    }

    protected function getFileConfigKey(?string $forKind = null): string
    {
        // Per-kind cache to avoid recomputation; use null key for default context
        if (!property_exists($this, '__resolvedFileConfigKeyMap')) {
            $this->__resolvedFileConfigKeyMap = [];
        }
        $cacheKey = $forKind ?? '__default__';
        if (isset($this->__resolvedFileConfigKeyMap[$cacheKey]) && is_string($this->__resolvedFileConfigKeyMap[$cacheKey]) && $this->__resolvedFileConfigKeyMap[$cacheKey] !== '') {
            return $this->__resolvedFileConfigKeyMap[$cacheKey];
        }

        // Base key from model or default
        $baseKey = (property_exists($this, 'fileConfigKey') && is_string($this->fileConfigKey) && $this->fileConfigKey !== '')
            ? $this->fileConfigKey
            : 'content';

        $resolved = $baseKey;

        // Determine mapping key priority:
        // 1) Provided file kind (from cms_*_files.kind)
        // 2) Model's own type (backward compatibility)
        $kind = null;
        if (is_string($forKind) && $forKind !== '') {
            $kind = $forKind;
        } elseif (isset($this->type) && is_string($this->type) && $this->type !== '') {
            $kind = $this->type;
        }

        $map = (array) config('cms.file_config_key_map', []);
        if ($kind !== null) {
            // Prefer mapping scoped by base key, e.g. ['content' => ['video_avatar' => 'content_video']]
            if (isset($map[$baseKey]) && is_array($map[$baseKey]) && isset($map[$baseKey][$kind]) && is_string($map[$baseKey][$kind]) && $map[$baseKey][$kind] !== '') {
                $resolved = $map[$baseKey][$kind];
            } elseif (isset($map[$kind]) && is_string($map[$kind]) && $map[$kind] !== '') {
                // Or use a global flat mapping, e.g. ['avatar' => 'content_images'] or ['test' => 'test_123']
                $resolved = $map[$kind];
            }
        }

        // Cache and return
        $this->__resolvedFileConfigKeyMap[$cacheKey] = $resolved;
        return $resolved;
    }

    /**
     * Try to resolve avatar using legacy naming like `content_{uuid}.webp` or `category{uuid}.webp`.
     */
    protected function resolveLegacyAvatarUrl(string $diskKey): ?string
    {
        // Only for images; videos are handled elsewhere and not affected.
        $prefix = $this->getFileConfigKey('avatar'); // 'content' or 'category' (or mapped by avatar kind)
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
     * Resolve a file record by logical kind using optional config mapping.
     * Config: cms.file_kind_map.<logicalKind> can be a string or array of kinds to try (in order).
     * We always include the logical kind as a fallback if not listed.
     */
    protected function resolveFileByLogicalKind(string $logicalKind)
    {
        if (!method_exists($this, 'files')) {
            return null;
        }
        if (!property_exists($this, '__fileByLogicalKindCache')) {
            $this->__fileByLogicalKindCache = [];
        }
        if (array_key_exists($logicalKind, $this->__fileByLogicalKindCache)) {
            return $this->__fileByLogicalKindCache[$logicalKind];
        }

        $map = config('cms.file_kind_map.' . $logicalKind);
        $kinds = [];
        if (is_string($map) && $map !== '') {
            $kinds[] = $map;
        } elseif (is_array($map)) {
            foreach ($map as $k) {
                if (is_string($k) && $k !== '') {
                    $kinds[] = $k;
                }
            }
        }
        if (!in_array($logicalKind, $kinds, true)) {
            $kinds[] = $logicalKind;
        }

        $found = null;
        foreach ($kinds as $k) {
            $candidate = $this->files()->where('kind', $k)->first();
            if ($candidate) { $found = $candidate; break; }
        }
        $this->__fileByLogicalKindCache[$logicalKind] = $found;
        return $found;
    }

    /**
     * Resolve URL for a concrete video size key, e.g. 'mobile', 'sd', 'hd'.
     */
    protected function resolveVideoUrlForSize(string $size): ?string
    {
        $file = $this->resolveFileByLogicalKind('video_avatar');
        $configKey = $this->getFileConfigKey($file->kind ?? 'video_avatar');
        // Determine proper disk. For content videos prefer a dedicated disk.
        $baseKey = (property_exists($this, 'fileConfigKey') && is_string($this->fileConfigKey) && $this->fileConfigKey !== '') ? $this->fileConfigKey : 'content';
        if ($configKey === 'content') {
            $diskKey = config('cms.disks.content_video') ?: config('cms.disks.content');
        } else {
            $diskKey = config("cms.disks.$configKey");
            if (!$diskKey) {
                $diskKey = $baseKey === 'content'
                    ? (config('cms.disks.content_video') ?: config('cms.disks.content'))
                    : config("cms.disks.$baseKey");
            }
        }
        if (!$diskKey) {
            return null;
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
        $file = $this->resolveFileByLogicalKind('video_poster');
        $configKey = $this->getFileConfigKey($file->kind ?? 'video_poster');
        $baseKey = (property_exists($this, 'fileConfigKey') && is_string($this->fileConfigKey) && $this->fileConfigKey !== '') ? $this->fileConfigKey : 'content';
        $diskKey = config("cms.disks.$configKey") ?: config("cms.disks.$baseKey"); // posters are images, use image disk
        if (!$diskKey) {
            return null;
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
