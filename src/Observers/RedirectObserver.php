<?php

namespace Dominservice\LaravelCms\Observers;

use Dominservice\LaravelCms\Models\Redirect;
use Illuminate\Support\Facades\Artisan;

class RedirectObserver
{
    public function created(Redirect $redirect): void
    {
        Artisan::call('regenerate-redirect');
    }

    public function updated(Redirect $redirect): void
    {
        Artisan::call('regenerate-redirect');
    }
}
