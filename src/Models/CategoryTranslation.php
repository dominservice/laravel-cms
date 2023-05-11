<?php

namespace Dominservice\LaravelCms\Models;


use Illuminate\Database\Eloquent\Model;

class CategoryTranslation extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ];

    public $timestamps = false;
    
    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('cms.tables.category_translations');
    }
}
