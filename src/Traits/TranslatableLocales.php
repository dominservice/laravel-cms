<?php

namespace App\Traits;

trait TranslatableLocales
{
    public function getLocales()
    {
        return $this->getLocalesHelper()->all();
    }
}
