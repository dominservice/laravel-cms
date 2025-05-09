<?php

namespace Dominservice\LaravelCms\Models;


use Astrotomic\Translatable\Translatable;
use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Dominservice\LaravelCms\Traits\TranslatableLocales;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $uuid
 * @property string $status
 * @property bool $is_nofollow
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property string $slug
 * @property string $name
 * @property string $sub_name
 * @property string $description
 * @property string $meta_title
 * @property string $meta_keywords
 * @property string $meta_description
 *
 * @property string $avatar_path
 * @property string $video_path
 */
class Content extends Model
{
    use HasUuidPrimary, Translatable, TranslatableLocales, SoftDeletes;

    protected $fillable = [
        'type',
        'status',
        'is_nofollow',
    ];

    public $translatedAttributes = [
        'slug',
        'name',
        'sub_name',
        'short_description',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ];

    protected $appends = [
        'avatar_path',
        'video_path'
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('cms.tables.contents');
    }

    public function getAvatarPathAttribute()
    {
        $avatar = 'content_' . $this->attributes['uuid'] . '.' . config('cms.avatar.extension');

        if(\Storage::disk(config('cms.disks.content'))->exists($avatar)) {
            return \Storage::disk(config('cms.disks.content'))->url($avatar);
        }

        return null;
    }

    public function getVideoPathAttribute()
    {
        if ($this->video && \Storage::disk(config('cms.disks.content_video'))->exists($this->video->name)) {
            return \Storage::disk(config('cms.disks.content_video'))->url($this->video->name);
        }

        return null;
    }

    /**
     * @return Attribute
     */
    public function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Carbon\Carbon::parse($value)->format(config('cms.date_format') . ' ' . config('cms.time_format')),
            set: fn ($value) => $value,
        );
    }

    /**
     * @return Attribute
     */
    public function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Carbon\Carbon::parse($value)->format(config('cms.date_format') . ' ' . config('cms.time_format')),
            set: fn ($value) => $value,
        );
    }

    public function scopeWhereCategories($categories)
    {
        if (!is_array($categories)) {
            $categories = [$categories];
        }

        $this->whereHas('categories', function ($q) use ($categories) {
            $q->whereIn(config('cms.tables.categories') .'.uuid', $categories)->orWhereIn('slug', $categories);
        });
    }

    public function categories()
    {
        return $this->belongsToMany(\Dominservice\LaravelCms\Models\Category::class
            , config('cms.tables.content_categories')
            , 'content_uuid'
            , 'category_uuid'
        );
    }

    public function rootCategory()
    {
        return $this->belongsTo(\Dominservice\LaravelCms\Models\ContentCategoryRoot::class, 'uuid', 'content_uuid')
            ->where('is_root', 1);
    }

    public function video()
    {
        return $this->belongsTo(\Dominservice\LaravelCms\Models\ContentVideo::class, 'uuid', 'content_uuid');
    }
}
