<?php

namespace Dominservice\LaravelCms\Models;


use Illuminate\Database\Eloquent\Model;

class ArticleCategory extends Model
{
    protected $table = 'docs_article_categories';

    public $timestamps = false;

    public function category()
    {
        return $this->hasOne(\App\Models\Docs\Category::class, 'id', 'category_id');
    }
}
