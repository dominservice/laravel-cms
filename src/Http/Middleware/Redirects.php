<?php

namespace Dominservice\LaravelCms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Redirects
{
    public function handle(Request $request, Closure $next)
    {
        $redirectList = config('cms.redirects')
            ? collect(json_decode(json_encode(config('cms.redirects'))))
            : collect();

        if ($redirectList->count()) {
            $currentUrlHost = parse_url(url()->current(), PHP_URL_HOST);
            $currentUrl = preg_replace('@https?://' . $currentUrlHost . '/@', '', url()->current());

            if ($redirectItem = $redirectList->filter(function ($item) use ($currentUrl) {
                $itemHost = parse_url($item->url_from, PHP_URL_HOST);
                $itemOldUrl = preg_replace('@https?://' . $itemHost . '/@', '', $item->url_from);

                return $itemOldUrl === $currentUrl;
            })->first()) {
                return redirect($redirectItem->url_to, ($redirectItem->code ?? 302));
            }
        }

        return $next($request);
    }
}
