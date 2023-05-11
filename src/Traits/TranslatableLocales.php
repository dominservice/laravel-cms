<?php

namespace Dominservice\LaravelCms\Traits;

trait TranslatableLocales
{
    public function getLocales()
    {
        return $this->getLocalesHelper()->all();
    }
}
