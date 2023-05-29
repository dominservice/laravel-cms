<?php

namespace Dominservice\LaravelCms\Models;


use Astrotomic\Translatable\Translatable;
use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Dominservice\LaravelCms\Traits\TranslatableLocales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasUuidPrimary, Translatable, TranslatableLocales, SoftDeletes;

    protected $fillable = [
        'type',
        'parent_uuid',
        'status',
    ];

    public $translatedAttributes = [
        'slug',
        'name',
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
        return config('cms.tables.categories');
    }

    /**
     * @param $value
     * @return string
     */
    public function getCreatedAtAttribute($value): string
    {
        return \Carbon\Carbon::parse($value)->format(config('cms.date_format') . ' ' . config('cms.time_format'));
    }

    /**
     * @param $value
     * @return string
     */
    public function getUpdatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format(config('cms.date_format') . ' ' . config('cms.time_format'));
    }

    public function contents()
    {
        return $this->belongsToMany(\Dominservice\LaravelCms\Models\Content::class
            , config('cms.tables.content_categories')
            , 'category_uuid'
            , 'version_uuid'
        );
    }

    public function parent()
    {
        return $this->belongsTo(static::class,'parent_uuid');
    }

    public function children()
    {
        return $this->hasMany(static::class, 'parent_uuid');
    }

    // and here is the trick for nestable child.
    public static function nestable($categories) {
        foreach ($categories as $category) {
            if (!$category->children->isEmpty()) {
                $category->children = self::nestable($category->children);
            }
        }

        return $categories;
    }

    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    public static function allToTree()
    {
        return self::nestable(self::allRoot('index'));
    }

    public static function allRoot($permissions = null)
    {
        return self::whereNull('parent_uuid')->can($permissions)->get();
    }
}
