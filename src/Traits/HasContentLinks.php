<?php

namespace Dominservice\LaravelCms\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasContentLinks
{
    /** Linki wychodzące (this -> other) */
    public function links(): BelongsToMany
    {
        return $this->belongsToMany(
            static::class,
            'cms_content_links',
            'from_uuid',
            'to_uuid',
            'uuid',
            'uuid',
        )
            ->withPivot(['relation','position','meta','visible_from','visible_to','created_at','updated_at'])
            ->using(\Dominservice\LaravelCms\Models\ContentLink::class);
    }

    /** Linki przychodzące (other -> this) */
    public function backlinks(): BelongsToMany
    {
        return $this->belongsToMany(
            static::class,
            'cms_content_links',
            'to_uuid',
            'from_uuid',
            'uuid',
            'uuid'
        )
            ->withPivot(['relation','position','meta','visible_from','visible_to','created_at','updated_at'])
            ->using(\Dominservice\LaravelCms\Models\ContentLink::class);
    }

    /** Filtrowanie po nazwie relacji, ale nie jest wymagane aby ją mieć */
    public function linksOf(?string $relation): BelongsToMany
    {
        return $this->links()->when($relation !== null, fn($q) => $q->wherePivot('relation', $relation));
    }

    public function backlinksOf(?string $relation): BelongsToMany
    {
        return $this->backlinks()->when($relation !== null, fn($q) => $q->wherePivot('relation', $relation));
    }

    /** Widoczne w danym momencie (opcjonalne użycie widoczności czasowej) */
    public function visibleLinks(?string $relation = null): BelongsToMany
    {
        $now = now();
        return $this->linksOf($relation)
            ->where(function ($q) use ($now) {
                $q->whereNull('cms_content_links.visible_from')
                    ->orWhere('cms_content_links.visible_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('cms_content_links.visible_to')
                    ->orWhere('cms_content_links.visible_to', '>=', $now);
            })
            ->orderBy('cms_content_links.position');
    }

    /** Pomocnicze metody API — neutralne semantycznie */
    public function attachLink(self $target, array $attributes = []): void
    {
        $this->links()->syncWithoutDetaching([
            $target->getKey() => $attributes,
        ]);
    }

    public function detachLink(self $target, ?string $relation = null): void
    {
        $query = $this->links();
        if ($relation !== null) {
            $query->wherePivot('relation', $relation);
        }
        // pobieramy id-e pivotu do odpięcia
        $ids = $query->pluck($this->getKeyName())->all();
        if (!empty($ids)) {
            $this->links()->detach($target->getKey());
        }
    }
}
