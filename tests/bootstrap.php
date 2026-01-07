<?php
// Lightweight bootstrap to allow using Storage facade and config() without full Laravel app

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Database\ConnectionResolverInterface;

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

    public function extend(string $name, $callback): void
    {
        if ($callback instanceof FakeDisk) {
            $this->disks[$name] = $callback;
        } else {
            // Handle closure case
            $this->disks[$name] = $callback();
        }
    }

    public function disk(string $name): FakeDisk
    {
        if (!isset($this->disks[$name])) {
            throw new RuntimeException("Disk '$name' not registered in FakeFilesystem");
        }
        return $this->disks[$name];
    }
    
    public function build(string $name): FakeDisk
    {
        return $this->disk($name);
    }
    
    public function cloud(): FakeDisk 
    {
        return $this->disk('cloud');
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

// Set up translatable config to prevent errors
$config->set('translatable', [
    'locales' => ['en', 'pl'],
    'locale_separator' => '-',
    'default_locale' => 'en',
    'fallback_locale' => 'en',
    'locale_suffix' => '',
    'use_property_fallback' => true,
]);

// Bind app locale service
$config->set('app.locale', 'en');
$config->set('app.fallback_locale', 'en');

// Create a mock Locales instance using reflection to bypass constructor
$localesMock = new class {
    private string $current = 'en';
    
    public function current(): string { return $this->current; }
    public function default(): string { return 'en'; }
    public function setCurrent(string $locale): void { $this->current = $locale ?: 'en'; }
    public function all(): array { return ['en']; }
    public function get(string $locale = null): string { return $locale ?? $this->current; }
    public function getLocale(): string { return $this->current; }
    public function setLocale(string $locale): void { $this->setCurrent($locale); }
    public function isSupported(string $locale): bool { return in_array($locale, $this->all()); }
    public function getFallback(): string { return $this->default(); }
    public function getCountryLocale(string $locale, string $country): string { return $locale; }
    public function getLanguageFromCountryBasedLocale(string $locale): string { return explode('_', $locale)[0]; }
    public function load(): void { /* no-op */ }
};

// Use reflection to create an instance without calling the constructor
try {
    $reflection = new ReflectionClass(\Astrotomic\Translatable\Locales::class);
    $localesInstance = $reflection->newInstanceWithoutConstructor();
    
    // Copy over the mock methods via a trick
    $app->instance(\Astrotomic\Translatable\Locales::class, $localesInstance);
} catch (Exception $e) {
    // Fallback to the mock object
    $app->instance(\Astrotomic\Translatable\Locales::class, $localesMock);
}

// Create fake database services
class FakeSchemaBuilder
{
    public function hasTable(string $table): bool { return false; }
    public function dropIfExists(string $table): void {}
}

class FakeQueryBuilder extends \Illuminate\Database\Query\Builder
{
    private $returnData;
    
    public function __construct($connection = null, $grammar = null, $processor = null, $data = null)
    {
        // Create fake instances if not provided
        $connection = $connection ?? new FakeConnection();
        $grammar = $grammar ?? $connection->getQueryGrammar();
        $processor = $processor ?? $connection->getPostProcessor();
        
        parent::__construct($connection, $grammar, $processor);
        
        $this->returnData = $data ?? collect();
    }
    
    public function where($column, $operator = null, $value = null, $boolean = 'and') { 
        return $this; 
    }
    public function orderBy($column, $direction = 'asc') { 
        return $this; 
    }
    public function first($columns = ['*']) { 
        return is_callable($this->returnData) ? ($this->returnData)() : $this->returnData->first(); 
    }
    public function get($columns = ['*']) { 
        return is_callable($this->returnData) ? ($this->returnData)() : $this->returnData; 
    }
    public function latest($column = 'created_at') { 
        return $this; 
    }
    public function create(array $attributes) { 
        $model = new class { 
            public $attributes = [];
            public function __construct($attrs = []) { $this->attributes = $attrs; }
            public function getKey() { return 'fake-key'; }
            public function save() { return true; }
            public $meta = [];
        };
        $model->attributes = $attributes;
        return $model;
    }
    
    // Override parent methods that might cause issues
    public function toSql() { return 'SELECT * FROM fake'; }
    public function getBindings() { return []; }
}

class FakeConnection
{
    public function table(string $table) { 
        return new FakeQueryBuilder();
    }
    public function beginTransaction(): void {}
    public function commit(): void {}
    public function rollBack(): void {}
    public function getSchemaBuilder() {
        return new FakeSchemaBuilder();
    }
    
    public function query() {
        return new FakeQueryBuilder();
    }
    
    public function getQueryGrammar() {
        return new class {
            public function compileSelect($query) { return 'SELECT * FROM fake'; }
        };
    }
    
    public function getPostProcessor() {
        return new class {
            public function processSelect($query, $results) { return $results; }
        };
    }
    
    public function getName() {
        return 'fake';
    }
}

class FakeDatabaseManager implements ConnectionResolverInterface
{
    private $defaultConnection;
    
    public function __construct()
    {
        $this->defaultConnection = new FakeConnection();
    }
    
    public function table(string $table) { 
        return new FakeQueryBuilder();
    }
    public function beginTransaction(): void {}
    public function commit(): void {}
    public function rollBack(): void {}
    
    public function connection($name = null) {
        return $this->defaultConnection;
    }
    
    public function getDefaultConnection() {
        return 'default';
    }
    
    public function setDefaultConnection($name) {
        // No-op for fake implementation
    }
}

// Bind database services - use real Laravel services for Mockery compatibility
$dbManager = new FakeDatabaseManager();
$app->bind('db.schema', function() {
    return new FakeSchemaBuilder();
});
$app->bind('db', function() use ($dbManager) {
    return $dbManager;
});

// Bind additional Laravel services that might be needed
$app->instance(\Illuminate\Contracts\Foundation\Application::class, $app);
$app->instance(\Illuminate\Container\Container::class, $app);

// Mock additional services that tests might try to resolve
$app->bind('Dominservice\MediaKit\Services\MediaUploader', function() {
    return new class {
        public function upload($file, $collection = 'default') { return null; }
    };
});

$app->bind('Dominservice\MediaKit\Support\Kinds\KindRegistry', function() {
    return new class {
        public static function collectionFor($kind, $default = 'default') { return $default; }
    };
});

// Bind translator service for translatable package
$app->bind('translator', function() use ($config) {
    return new class {
        public function getLocale(): string { return 'en'; }
        public function setLocale(string $locale): void {}
        public function get($key, array $replace = [], $locale = null): string { return $key; }
        public function has($key, $locale = null): bool { return true; }
    };
});

// Bind translatable services to fix locale issues - using proper constructor
$app->bind('translatable.locales', function() use ($config, $app) {
    $translator = $app->make('translator');
    return new \Astrotomic\Translatable\Locales($config, $translator);
});

// Set up Eloquent to use our fake database
\Illuminate\Database\Eloquent\Model::setConnectionResolver($dbManager);

// Create a base model class with query method for anonymous classes to extend
if (!class_exists('FakeModel')) {
    class FakeModel extends \Illuminate\Database\Eloquent\Model
    {
        protected $fillable = ['*'];
        
        public static function query()
        {
            return new FakeQueryBuilder(function() {
                $model = new static;
                $model->uuid = 'fake-uuid';
                $model->exists = true;
                return $model;
            });
        }
        
        public function media()
        {
            return new FakeQueryBuilder();
        }
        
        public function getKey()
        {
            return $this->uuid ?? 'fake-key';
        }
    }
}

// Create actual model classes that extend FakeModel for tests
if (!class_exists('Dominservice\\LaravelCms\\Models\\Content')) {
    eval('
    namespace Dominservice\\LaravelCms\\Models {
        class Content extends \\FakeModel {
            protected $table = "contents";
            protected $fillable = ["*"];
            
            public static function query() {
                return new \\FakeQueryBuilder(function() {
                    $model = new static;
                    $model->uuid = "fake-content-uuid";
                    $model->exists = true;
                    return $model;
                });
            }
            
            public function media() {
                return new \\FakeQueryBuilder();
            }
        }
    }');
}

if (!class_exists('Dominservice\\LaravelCms\\Models\\Category')) {
    eval('
    namespace Dominservice\\LaravelCms\\Models {
        class Category extends \\FakeModel {
            protected $table = "categories";
            protected $fillable = ["*"];
            
            public static function query() {
                return new \\FakeQueryBuilder(function() {
                    $model = new static;
                    $model->uuid = "fake-category-uuid";
                    $model->exists = true;
                    return $model;
                });
            }
            
            public function media() {
                return new \\FakeQueryBuilder();
            }
        }
    }');
}

// Prepare fake filesystem and bind for Storage facade
$fs = new FakeFilesystem();
$app->instance('filesystem', $fs);

// Helper to register a disk quickly
function register_fake_disk(string $key, string $baseUrl = 'http://localhost/storage'): FakeDisk {
    $fs = app('filesystem');
    $tmp = sys_get_temp_dir() . '/laravel_cms_pkg_tests/' . $key;
    $disk = new FakeDisk($tmp, $baseUrl);
    if ($fs) {
        $fs->extend($key, $disk);
    }
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
