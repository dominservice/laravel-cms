<?php

declare(strict_types=1);

namespace Dominservice\LaravelCms\Support;

use InvalidArgumentException;
use Livewire\Component;

final class AdminComponentResolver
{
    /** @param class-string<Component> $default */
    public static function resolve(string $key, string $default): string
    {
        $component = config("cms.admin.components.{$key}", $default);

        if (! is_string($component) || ! is_a($component, Component::class, true)) {
            throw new InvalidArgumentException(
                "Configured CMS admin component [{$key}] must extend ".Component::class.'.',
            );
        }

        return $component;
    }
}
