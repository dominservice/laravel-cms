<?php

namespace Dominservice\LaravelCms\Models;


use Illuminate\Database\Eloquent\Model;

class ContentTranslation extends Model
{
    use \Dominservice\LaravelCms\Traits\Slugable;

    public $fillable = [
        'slug',
        'name',
        'sub_name',
        'short_description',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ];

    public $timestamps = false;

    protected static bool $canUpdateName = true;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('cms.tables.content_translations');
    }
    
    public function extendByShortCodes() {
        $objectShortcode = new \Dominservice\LaravelCms\Helpers\Shortcode($this->description);

        foreach ($objectShortcode->getCodesParams() as $shortcode=>$params) {
            $extend = [];

            if (!empty($params->values->id) || !empty($params->values->cat_id)) {
                $ids = !empty($params->values->id) ? explode(',', $params->values->id) : false;
                $uuids = !empty($params->values->uuid) ? explode(',', $params->values->uuid) : false;
                $catIds = !empty($params->values->cat_id) ? explode(',', $params->values->cat_id) : false;

                $contents = Content::where('status', 1)
                    ->with('lang')
                    ->where('uuid', '!=', $this->content_uuid)
                    ->where('type', $params->type);

                if ($ids) {
                    $contents->whereIn('id', $ids);
                }

                if ($uuids) {
                    $contents->whereIn('uuid', $uuids);
                }

                if ($catIds) {
                    $contents->whereCategories($catIds);
                }

                $contents = $contents->get();

                foreach ($contents as $content) {
                    if (!empty($content->lang->description)) {
                        $content->lang->extendByShortCodes() ;
                        $title = '<h3>'. $content->lang->name .'</h3>';
                        $extend[] = $title . ($params->type == 'faq' ? "\t" : '') . $content->lang->description;
                    }
                }
            }

            $extend = '<div class="clearfix"></div><div class="row"><div class="col-12">' . implode('</div><div>', $extend) . '</div></div><hr>';
            $this->description = str_replace($shortcode, $extend, $this->description);
        };
    }
}
