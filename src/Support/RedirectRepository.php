<?php

declare(strict_types=1);

namespace Dominservice\LaravelCms\Support;

use Dominservice\LaravelCms\Models\Redirect;
use Illuminate\Filesystem\Filesystem;
use Throwable;

final class RedirectRepository
{
    /** @var array<string, array{url_from:string,url_to:string,code:int}>|null */
    private ?array $redirects = null;

    /** @var array<string, true> */
    private array $suppressedPaths = [];

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /** @return array<int, array{url_from:string,url_to:string,code:int}> */
    public function records(): array
    {
        return array_values($this->availableRedirects());
    }

    /** @return array{url_from:string,url_to:string,code:int}|null */
    public function find(string $urlOrPath): ?array
    {
        $path = $this->normalizePath($urlOrPath);

        if (isset($this->suppressedPaths[$path])) {
            return null;
        }

        return $this->redirectMap()[$path] ?? null;
    }

    /** @return array<string, array{url_from:string,url_to:string,code:int}> */
    public function rebuild(): array
    {
        $this->redirects = $this->redirectsFromDatabase();
        $this->writeCache($this->redirects);

        return $this->redirects;
    }

    /** @param array<int, string> $urlsOrPaths */
    public function suppressPaths(array $urlsOrPaths): void
    {
        foreach ($urlsOrPaths as $urlOrPath) {
            $this->suppressedPaths[$this->normalizePath($urlOrPath)] = true;
        }
    }

    /** @return array<string, array{url_from:string,url_to:string,code:int}> */
    private function availableRedirects(): array
    {
        return array_diff_key($this->redirectMap(), $this->suppressedPaths);
    }

    /** @return array<string, array{url_from:string,url_to:string,code:int}> */
    private function redirectMap(): array
    {
        if ($this->redirects !== null) {
            return $this->redirects;
        }

        $cachedRedirects = $this->readCache();
        if ($cachedRedirects !== null) {
            return $this->redirects = $cachedRedirects;
        }

        try {
            return $this->redirects = $this->redirectsFromDatabase();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, array{url_from:string,url_to:string,code:int}> */
    private function redirectsFromDatabase(): array
    {
        $redirects = [];

        foreach (Redirect::query()
            ->whereNotNull('active_at')
            ->orderBy('id')
            ->get(['url_from', 'url_to', 'code']) as $redirect) {
            $path = $this->normalizePath((string) $redirect->url_from);

            if (isset($redirects[$path])) {
                continue;
            }

            $redirects[$path] = [
                'url_from' => (string) $redirect->url_from,
                'url_to' => (string) $redirect->url_to,
                'code' => (int) $redirect->code,
            ];
        }

        return $redirects;
    }

    /** @return array<string, array{url_from:string,url_to:string,code:int}>|null */
    private function readCache(): ?array
    {
        $path = $this->cachePath();

        if (! $this->files->isFile($path)) {
            return null;
        }

        try {
            $redirects = (static fn (string $cachePath): mixed => require $cachePath)($path);

            return is_array($redirects) ? $redirects : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, array{url_from:string,url_to:string,code:int}> $redirects */
    private function writeCache(array $redirects): void
    {
        $path = $this->cachePath();
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->replace(
            $path,
            '<?php return '.var_export($redirects, true).';'.PHP_EOL,
        );

        clearstatcache(true, $path);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    private function cachePath(): string
    {
        $configuredPath = trim((string) config('cms.redirects_cache_path', ''));

        return $configuredPath !== ''
            ? $configuredPath
            : app()->bootstrapPath('cache/cms_redirects.php');
    }

    private function normalizePath(string $urlOrPath): string
    {
        $path = parse_url($urlOrPath, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return '/'.ltrim($path, '/');
    }
}
