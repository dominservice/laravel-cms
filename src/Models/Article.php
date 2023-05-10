<?php

namespace Dominservice\LaravelCms\Models;


use Astrotomic\Translatable\Translatable;
use DateTimeInterface;
use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Hootlex\Moderation\Moderatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasUuidPrimary, Translatable, SoftDeletes;

    protected $table = 'docs_articles';

    protected $casts = [
        'status' => 'integer'
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @return Attribute
     */
    public function getCreatedAtAttribute(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Carbon\Carbon::parse($value)->format(config('cms.date_format') . ' ' . config('cms.time_format')),
            set: fn ($value) => $value,
        );
    }

    /**
     * @return Attribute
     */
    public function getUpdatedAtAttribute(): Attribute
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
            $q->whereIn('id', $categories)->orWhereIn('slug', $categories);
        });
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class
            , 'cms_article_categories'
            , 'article_id'
            , 'category_id'
        );
    }
}
