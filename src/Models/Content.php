<?php

namespace Dominservice\LaravelCms\Models;


use Astrotomic\Translatable\Translatable;
use DateTimeInterface;
use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Dominservice\LaravelCms\Traits\TranslatableLocales;
use Hootlex\Moderation\Moderatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Content extends Model
{
    use HasUuidPrimary, Translatable, TranslatableLocales, SoftDeletes;

    protected $fillable = [
        'type',
        'status',
    ];

    public $translatedAttributes = [
        'slug',
        'name',
        'sub_name',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
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
            $q->whereIn('uuid', $categories)->orWhereIn('slug', $categories);
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
}
