<?php

namespace Dominservice\LaravelCms\Models;


use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $table = 'docs_categories';

    /**
     * @param $value
     * @return string
     */
    public function getCreatedAtAttribute($value): string
    {
        return \Carbon\Carbon::parse($value)->format(config('global.date_format') . ' ' . config('global.time_format'));
    }

    /**
     * @param $value
     * @return string
     */
    public function getUpdatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format(config('global.date_format') . ' ' . config('global.time_format'));
    }

    public function articles()
    {
        return $this->belongsToMany(\App\Models\Docs\Article::class
            , 'docs_article_categories'
            , 'category_id'
            , 'version_id'
        );
    }

    // Define Eloquent parent child relationship
    public function scopeToTree()
    {
        $categories = $this->with('lang')->where(function ($q) {
            $q->where('parent_id', 0)
                ->orWhere('parent_id', null);
        })->get();

        return $this->nestable($categories);
    }

    // Define Eloquent parent child relationship
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id')->with('lang');
    }

    // for first level child this will works enough
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // and here is the trick for nestable child.
    public static function nestable($categories)
    {
        foreach ($categories as $cid=>$category) {
            if (!$category->children->isEmpty()) {
                $category->children = self::nestable($category->children);
            }
            $categories[$cid]->name = !empty($category->lang->name) ? $category->lang->name : '';
            $categories[$cid]->slug = !empty($category->lang->slug) ? $category->lang->slug : '';
        }

        return $categories;
    }

    public function allLangs()
    {
        return $this->hasMany(CategoryTranslation::class, 'category_id');
    }

    public function lang()
    {
        return $this->hasOne(CategoryTranslation::class, 'category_id')
            ->where('lang_id', config('app.lang.id'));
    }

    public function getLang($lang_id)
    {
        return CategoryTranslation::where('category_id', $this->id)
            ->where('lang_id', $lang_id)
            ->first();
    }
}
