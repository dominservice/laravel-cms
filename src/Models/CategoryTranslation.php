<?php

namespace Dominservice\LaravelCms\Models;


use Illuminate\Database\Eloquent\Model;

class CategoryTranslation extends Model
{
    protected $table = 'docs_category_langs';

    public $timestamps = false;

    public function lang()
    {
        return $this->hasOne(\App\Models\Lang::class, 'lang_id');
    }
}
