<?php

namespace Dominservice\LaravelCms\Models;


use Astrotomic\Translatable\Translatable;
use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Dominservice\LaravelCms\Traits\TranslatableLocales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Redirect extends Model
{
    protected $fillable = [
        'url_from',
        'url_to',
        'code',
        'active_at',
    ];

    protected $casts = ['active_at' => 'datetime'];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('cms.tables.redirects');
    }
}
