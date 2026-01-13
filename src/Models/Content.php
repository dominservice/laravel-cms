<?php

namespace Dominservice\LaravelCms\Models;


use Astrotomic\Translatable\Translatable;
use Carbon\Carbon;
use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Dominservice\LaravelCms\Traits\TranslatableLocales;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $uuid
 * @property string $parent_uuid
 * @property string $type
 * @property bool $status
 * @property bool $is_nofollow
 * @property string|null $external_url
 * @property object|null $meta
 * @property int|null $sort
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property string $slug
 * @property string $name
 * @property string $sub_name
 * @property string $description
 * @property string $meta_title
 * @property string $meta_keywords
 * @property string $meta_description
 *
 * @property string $avatar_path
 * @property string $video_path
 */
class Content extends Model
{
    use HasUuidPrimary,
        Translatable,
        TranslatableLocales,
        SoftDeletes,
        \Dominservice\LaravelCms\Traits\DynamicAvatarAccessor,
        \Dominservice\LaravelCms\Traits\HasContentLinks;

    protected string $fileConfigKey = 'content';

    protected $fillable = [
        'parent_uuid',
        'type',
        'status',
        'is_nofollow',
        'external_url',
        'meta',
        'sort',
    ];

    public $translatedAttributes = [
        'slug',
        'name',
        'sub_name',
        'short_description',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'type' => config('cms.types.category'),
            'external_url' => 'string',
            'meta'         => 'object',
        ];
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('cms.tables.contents');
    }

    /**
     * @return Attribute
     */
    public function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Carbon\Carbon::parse($value)->format(config('cms.date_format') . ' ' . config('cms.time_format')),
            set: fn ($value) => $value,
        );
    }

    /**
     * @return Attribute
     */
    public function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Carbon\Carbon::parse($value)->format(config('cms.date_format') . ' ' . config('cms.time_format')),
            set: fn ($value) => $value,
        );
    }

    /**
     * Normalize external_url: trim spaces; store empty as null
     */
    public function externalUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $value = is_string($value) ? trim($value) : $value;
                return $value === '' ? null : $value;
            },
            set: function ($value) {
                if (is_string($value)) {
                    $value = trim($value);
                    return $value === '' ? null : $value;
                }
                return $value ?: null;
            }
        );
    }

    public function scopeWhereCategories($categories)
    {
        if (!is_array($categories)) {
            $categories = [$categories];
        }

        $this->whereHas('categories', function ($q) use ($categories) {
            $q->whereIn(config('cms.tables.categories') .'.uuid', $categories)->orWhereIn('slug', $categories);
        });
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'uuid', 'parent_uuid');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_uuid', 'uuid');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class
            , config('cms.tables.content_categories')
            , 'content_uuid'
            , 'category_uuid'
        );
    }

    public function rootCategory()
    {
        return $this->belongsTo(ContentCategoryRoot::class, 'uuid', 'content_uuid')
            ->where('is_root', 1);
    }

    public function video()
    {
        return $this->belongsTo(ContentFile::class, 'uuid', 'content_uuid')
            ->where('kind', 'video_avatar');
    }

    public function videoPoster()
    {
        return $this->belongsTo(ContentFile::class, 'uuid', 'content_uuid')
            ->where('kind', 'video_poster');
    }

    public function files()
    {
        return $this->hasMany(ContentFile::class, 'content_uuid', 'uuid');
    }

    public function avatarFile()
    {
        return $this->hasOne(ContentFile::class, 'content_uuid', 'uuid')
            ->where('kind', 'avatar');
    }

}
