<?php

namespace Dominservice\LaravelCms\Models;


use Illuminate\Database\Eloquent\Model;

class ContentCategory extends Model
{
    public $timestamps = false;

    public function category()
    {
        return $this->hasOne(\Dominservice\LaravelCms\Models\Category::class, 'uuid', 'category_uuid');
    }

    public function content()
    {
        return $this->hasOne(\Dominservice\LaravelCms\Models\Content::class, 'uuid', 'content_uuid');
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('cms.tables.content_categories');
    }
    
}
