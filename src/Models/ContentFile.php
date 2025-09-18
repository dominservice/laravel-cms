<?php

namespace Dominservice\LaravelCms\Models;

use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $uuid
 * @property string $content_uuid
 * @property string $kind
 * @property string|null $type  // 'image' or 'video'
 * @property array $names
 */
class ContentFile extends Model
{
    use HasUuidPrimary, SoftDeletes;

    protected $fillable = [
        'content_uuid',
        'kind',
        'type',
        'names',
    ];

    protected $casts = [
        'names' => 'array',
    ];

    public function getTable()
    {
        return config('cms.tables.content_files');
    }

    public function content()
    {
        return $this->belongsTo(Content::class, 'content_uuid', 'uuid');
    }
}
