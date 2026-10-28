<?php

namespace Dominservice\LaravelCms\Http\Middleware;

use Closure;
use Dominservice\LaravelCms\Support\RedirectRepository;
use Illuminate\Http\Request;

class Redirects
{
    public function __construct(
        private readonly RedirectRepository $redirects,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $redirect = $this->redirects->find($request->getPathInfo());

        if ($redirect !== null) {
            return redirect($redirect['url_to'], $redirect['code'] ?? 302);
        }

        return $next($request);
    }
}
