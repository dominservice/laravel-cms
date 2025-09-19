<?php

namespace Dominservice\LaravelCms\Traits;

use Illuminate\Support\Facades\Storage;

trait DynamicAvatarAccessor
{
    /**
     * The model using this trait should define a protected string $fileConfigKey
     * to determine which config branch and disk to use (e.g. 'content' or 'category').
     * If it's not defined in the model, we will gracefully fall back to 'content'.
     */

    /**
     * Main avatar accessor. Returns URL for the configured display size.
     */
    public function getAvatarPathAttribute()
    {
        $size = $this->getConfiguredAvatarDisplaySize();
        return $this->resolveAvatarUrlForSize($size);
    }

    /**
     * Intercept dynamic access like `small_avatar_path`, `large_avatar_path`, `thumb_avatar_path`.
     * Fallback to parent implementation for anything else.
     */
    public function __get($key)
    {
        if (is_string($key) && str_ends_with($key, '_avatar_path')) {
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

        if (is_string($name) && $name !== '' && Storage::disk($diskKey)->exists($name)) {
            return Storage::disk($diskKey)->url($name);
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
                return Storage::disk($diskKey)->url($name);
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
}
