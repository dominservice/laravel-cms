<?php

namespace Dominservice\LaravelCms\Helpers;

use Carbon\Carbon;

class Name
{
    public static function generateAvatarName($model, string $prefix = null): string
    {
        $format = explode('|', config('cms.avatar.format_name') ?? $model->getKeyName());
        return $prefix
            . (in_array($model->getKeyName(), $format) ? $model->{$model->getKeyName()}: '')
            . (in_array('type', $format) ? $model->attributes['type'] ?? '' : '')
            . (in_array('created_at', $format) ? Carbon::parse($model->attributes['created_at'])->format('Ymdhis') : '')
            . (in_array('updated_at', $format) ? Carbon::parse($model->attributes['updated_at'])->format('Ymdhis') : '')
            . '.' . config('cms.avatar.extension');
    }
}