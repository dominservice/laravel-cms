<?php

namespace Dominservice\LaravelCms\Models;


use Astrotomic\Translatable\Translatable;
use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Dominservice\LaravelCms\Traits\TranslatableLocales;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Support\Facades\Storage;
use Kalnoy\Nestedset\NodeTrait;

/**
 * @property string $uuid
 * @property string $type
 * @property null|string $parent_uuid
 * @property bool $status
 * @property int $_lft
 * @property int $_rgt
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property null|\Carbon\Carbon $deleted_at
 */
class Category extends Model
{
    use HasUuidPrimary, Translatable, TranslatableLocales, SoftDeletes, NodeTrait;

    protected $fillable = [
        'type',
        'parent_uuid',
        'status',
        '_lft',
        '_rgt',
    ];

    public $translatedAttributes = [
        'slug',
        'name',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ];

    protected $appends = [
        'avatar_path'
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('cms.tables.categories');
    }

    /**
     * Get the parent id key name.
     *
     * @return  string
     */
    public function getParentIdName()
    {
        return 'parent_uuid';
    }

    public function getAvatarPathAttribute()
    {
        $avatar = 'category_' . $this->attributes['uuid'] . '.' . config('cms.avatar.extension');
        
        if (Storage::disk(config('cms.disks.category'))->exists($avatar)) {
            return Storage::disk(config('cms.disks.category'))->url($avatar);
        }

        return null;
    }

    public function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Carbon\Carbon::parse($value)->format(config('cms.date_format') . ' ' . config('cms.time_format')),
            set: fn ($value) => $value,
        );
    }

    public function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Carbon\Carbon::parse($value)->format(config('cms.date_format') . ' ' . config('cms.time_format')),
            set: fn ($value) => $value,
        );
    }

    public function contents()
    {
        return $this->belongsToMany(\Dominservice\LaravelCms\Models\Content::class
            , config('cms.tables.content_categories')
            , 'category_uuid'
            , 'version_uuid'
        );
    }
}
