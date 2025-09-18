<?php

namespace Dominservice\LaravelCms\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated - this model while be deleted in next big project update
 *
 *
 * @property int $id
 * @property string $content_uuid
 * @property string $name
 */
class ContentVideo extends Model
{
    protected $fillable = ['content_uuid', 'name'];

    public $timestamps = false;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('cms.tables.content_video');
    }
}
