<?php

namespace Dominservice\LaravelCms\Models;

use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $uuid
 * @property string $category_uuid
 * @property string $kind
 * @property string|null $type
 * @property array $names
 */
class CategoryFile extends Model
{
    use HasUuidPrimary, SoftDeletes;

    protected $fillable = [
        'category_uuid',
        'kind',
        'type',
        'names',
    ];

    protected $casts = [
        'names' => 'array',
    ];

    public function getTable()
    {
        return config('cms.tables.category_files');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_uuid', 'uuid');
    }
}
