<?php

namespace Dominservice\LaravelCms\Traits;

use Dominservice\LaravelCms\Models\Redirect;
use Illuminate\Support\Str;

trait Slugable
{
    public static function booted() {
        parent::boot();

        //while creating/inserting item into db
        static::creating(function ($item) {
            $key = $item->getColumnForSlug();
            $item->slug = Str::limit(Str::slug($item->$key, '-', 'en', ['@' => 'at', '&' => 'and']), 255, '');

            if ($item->getKeyType() === 'ulid' || in_array('ulid', $item->getFillable())) {
                $item->ulid = mb_strtolower((string) Str::ulid());
            }
        });

        if (isset(self::$canUpdateName) && self::$canUpdateName === true) {
            static::updating(function ($item) {
                $oldUrl = $item->url ?? null;
                $key = $item->getColumnForSlug();
                $slug = Str::limit(str()->slug($item->$key), 255, '');

                // Ensure the slug is unique
                $item->slug = $item->generateUniqueSlug($slug);

                if ($oldUrl) {
                    try {
                        $redirectItem = new Redirect();
                        $redirectItem->code = 301;
                        $redirectItem->url_to = $item->url;
                        $redirectItem->url_from = $oldUrl;
                        $redirectItem->save();
                    } catch (\Exception $e) {
                    }
                }
            });
        }
    }

    protected function getColumnForSlug()
    {
        if (isset($this->columnForSlug)) {
            return $this->columnForSlug;
        }

        return $this->name ? 'name' : 'title';
    }

    public function scopeSlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    protected function generateUniqueSlug($slug)
    {
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            // Calculate the length of the suffix (e.g., "-1", "-2", etc.)
            $suffix = '-' . $count;
            $suffixLength = strlen($suffix);

            // Ensure the slug length does not exceed 255 characters
            if (strlen($originalSlug) + $suffixLength > 255) {
                $slug = Str::limit($originalSlug, 255 - $suffixLength, '') . $suffix;
            } else {
                $slug = $originalSlug . $suffix;
            }

            $count++;
        }

        return $slug;
    }
}
