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
            $size = substr($key, 0, -strlen('_avatar_path'));
            // Guard against the special plain 'avatar_path' which we already handle via accessor above
            if ($size !== 'avatar') {
                $val = $this->resolveAvatarUrlForSize($size);
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

    protected function resolveAvatarUrlForSize(string $size): ?string
    {
        // Try to get the avatar file record
        $file = method_exists($this, 'avatarFile') ? $this->avatarFile()->first() : (method_exists($this, 'files') ? $this->files()->where('kind', 'avatar')->first() : null);
        if (!$file || !is_array($file->names) || !isset($file->names[$size])) {
            return null;
        }
        $name = $file->names[$size];
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
