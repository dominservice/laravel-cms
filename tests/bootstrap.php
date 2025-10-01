<?php
// Lightweight bootstrap to allow using Storage facade and config() without full Laravel app

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Config\Repository as ConfigRepository;

require_once __DIR__ . '/../vendor/autoload.php';

class FakeDisk
{
    private string $root;
    private string $baseUrl;

    public function __construct(string $root, string $baseUrl)
    {
        $this->root = rtrim($root, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        if (!is_dir($this->root)) {
            mkdir($this->root, 0777, true);
        }
    }

    public function put(string $name, string $contents): void
    {
        $path = $this->root . '/' . ltrim($name, '/');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $contents);
    }

    public function exists(string $name): bool
    {
        $path = $this->root . '/' . ltrim($name, '/');
        return is_file($path);
    }

    public function url(string $name): string
    {
        return $this->baseUrl . '/' . ltrim($name, '/');
    }

    public function lastModified(string $name): int
    {
        $path = $this->root . '/' . ltrim($name, '/');
        return filemtime($path) ?: time();
    }

    public function delete($paths): void
    {
        $items = is_array($paths) ? $paths : [$paths];
        foreach ($items as $name) {
            $path = $this->root . '/' . ltrim((string)$name, '/');
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}

class FakeFilesystem
{
    private array $disks = [];

    public function extend(string $name, FakeDisk $disk): void
    {
        $this->disks[$name] = $disk;
    }

    public function disk(string $name): FakeDisk
    {
        if (!isset($this->disks[$name])) {
            throw new RuntimeException("Disk '$name' not registered in FakeFilesystem");
        }
        return $this->disks[$name];
    }
}

// Create container and bind config + filesystem for Facades
$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);

$config = new ConfigRepository();
$app['config'] = $config;
$app->instance(\Illuminate\Contracts\Config\Repository::class, $config);

// Load package config
$cmsConfig = require __DIR__ . '/../config/cms.php';
$config->set('cms', $cmsConfig);

// Provide a minimal Locales stub for astrotomic/translatable
if (!class_exists('FakeLocales')) {
    class FakeLocales
    {
        private string $current = 'en';
        public function current(): string { return $this->current; }
        public function default(): string { return 'en'; }
        public function setCurrent(string $locale): void { $this->current = $locale ?: 'en'; }
        public function all(): array { return ['en']; }
    }
}
$app->instance(\Astrotomic\Translatable\Locales::class, new FakeLocales());

// Prepare fake filesystem and bind for Storage facade
$fs = new FakeFilesystem();
$app->instance('filesystem', $fs);

// Helper to register a disk quickly
function register_fake_disk(string $key, string $baseUrl = 'http://localhost/storage'): FakeDisk {
    global $fs;
    $tmp = sys_get_temp_dir() . '/laravel_cms_pkg_tests/' . $key;
    $disk = new FakeDisk($tmp, $baseUrl);
    $fs->extend($key, $disk);
    return $disk;
}

// Small assertion helpers
function assert_true($cond, string $message = 'Assertion failed'): void {
    if (!$cond) throw new RuntimeException($message);
}
function assert_equals($a, $b, string $message = ''): void {
    if ($a !== $b) {
        $m = $message !== '' ? $message : ('Assertion failed: ' . var_export($a, true) . ' !== ' . var_export($b, true));
        throw new RuntimeException($m);
    }
}
function assert_not_null($v, string $message = 'Expected not null'): void {
    if ($v === null) throw new RuntimeException($message);
}
