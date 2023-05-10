<?php

namespace Dominservice\LaravelCms\Models;


use Illuminate\Database\Eloquent\Model;

class ArticleTranslation extends Model
{
    protected $table = 'cms_article_translations';

    protected $fillable = ['name'];

    public $timestamps = false;


    public function extendByShortCodes() {
        $objectShortcode = new \Dominservice\LaravelCms\Helpers\Shortcode($this->description);

        foreach ($objectShortcode->getCodesParams() as $shortcode=>$params) {
            $extend = [];

            if (!empty($params->values->id) || !empty($params->values->cat_id)) {
                $ids = !empty($params->values->id) ? explode(',', $params->values->id) : false;
                $uuids = !empty($params->values->uuid) ? explode(',', $params->values->uuid) : false;
                $catIds = !empty($params->values->cat_id) ? explode(',', $params->values->cat_id) : false;

                $articles = Article::where('status', 1)
                    ->with('lang')
                    ->where('id', '!=', $this->article_id)
                    ->where('type', $params->type);

                if ($ids) {
                    $articles->whereIn('id', $ids);
                }

                if ($uuids) {
                    $articles->whereIn('uuid', $uuids);
                }

                if ($catIds) {
                    $articles->whereCategories($catIds);
                }

                $articles = $articles->get();

                foreach ($articles as $article) {
                    if (!empty($article->lang->description)) {
                        $article->lang->extendByShortCodes() ;
                        $title = '<h3>'. $article->lang->name .'</h3>';
                        $content = $article->lang->description;
                        $extend[] = $title . ($params->type == 'faq' ? "\t" : '') . $content;
                    }
                }
            }

            $extend = '<div class="clearfix"></div><div class="row"><div class="col-12">' . implode('</div><div>', $extend) . '</div></div><hr>';
            $this->description = str_replace($shortcode, $extend, $this->description);
        };
    }
}
