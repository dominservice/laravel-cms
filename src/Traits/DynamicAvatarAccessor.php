<?php

namespace Dominservice\LaravelCms\Traits;

use Illuminate\Support\Facades\Storage;

trait DynamicAvatarAccessor
{
    /**
     * Must be defined in the model using this trait to determine which config branch and disk to use.
     * Example values: 'content' or 'category'.
     * @var string
     */
    protected string $fileConfigKey = 'content';

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
        $key = "cms.files.{$this->fileConfigKey}.types.avatar.display";
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
        if (!$file || !is_array($file->names)) {
            return null;
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
            if (!$bucket || !isset($bucket[$size])) {
                return null;
            }
            $name = $bucket[$size];
        } else {
            if (!isset($names[$size])) {
                return null;
            }
            $name = $names[$size];
        }
        $diskKey = config("cms.disks.{$this->fileConfigKey}");
        if (!$diskKey) {
            return null;
        }
        if (Storage::disk($diskKey)->exists($name)) {
            return Storage::disk($diskKey)->url($name);
        }
        return null;
    }
}
