<?php

namespace Dominservice\LaravelCms\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContentLink extends Pivot
{
    protected $table = 'cms_content_links';
    protected $casts = [
        'meta'         => 'array',
        'visible_from' => 'datetime',
        'visible_to'   => 'datetime',
        'position'     => 'integer',
    ];
}
