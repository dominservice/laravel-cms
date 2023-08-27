<?php

namespace Dominservice\LaravelCms\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ContentCategoryRoot extends Pivot
{
//    protected $table    = 'customertype_material';
    protected $fillable = ['is_root', 'category_uuid', 'content_uuid'];

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
