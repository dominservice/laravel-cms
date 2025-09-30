<?php
require __DIR__ . '/bootstrap.php';

use Dominservice\LaravelCms\Traits\DynamicAvatarAccessor;
use Dominservice\LaravelCms\Helpers\Media;
use Illuminate\Database\Eloquent\Model;

class ArrayRelation
{
    private array $records;
    private ?string $whereField = null;
    private $whereValue = null;

    public function __construct(array $records)
    {
        $this->records = $records;
    }

    public function where(string $field, $value): self
    {
        $clone = clone $this;
        $clone->whereField = $field;
        $clone->whereValue = $value;
        return $clone;
    }

    public function first()
    {
        $list = $this->get();
        return $list[0] ?? null;
    }

    public function get(): array
    {
        if ($this->whereField === null) {
            return $this->records;
        }
        return array_values(array_filter($this->records, function ($rec) {
            return isset($rec->{$this->whereField}) && $rec->{$this->whereField} === $this->whereValue;
        }));
    }
}

class TestModel extends Model
{
    use DynamicAvatarAccessor;

    protected $guarded = [];
    public string $uuid;
    protected string $fileConfigKey = 'content';

    /** @var array<int,object> */
    private array $fileRecords = [];

    public function __construct(?string $uuid = null, ?array $fileRecords = null, string $fileConfigKey = 'content')
    {
        parent::__construct();
        if ($uuid !== null) { $this->uuid = $uuid; }
        if ($fileRecords !== null) { $this->fileRecords = $fileRecords; }
        $this->fileConfigKey = $fileConfigKey;
    }

    public function files(): ArrayRelation
    {
        return new ArrayRelation($this->fileRecords);
    }
}

function run_test(string $name, callable $fn)
{
    try {
        $fn();
        echo "[OK] $name\n";
    } catch (Throwable $e) {
        echo "[FAIL] $name: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Prepare fake disk according to config (public)
$disk = register_fake_disk('public');

// Seed some files on disk
$disk->put('img/ava_large.webp', 'L');
$disk->put('img/ava_small.webp', 'S');
$disk->put('img/ava_thumb.webp', 'T');
$disk->put('img/mob_small.webp', 'M');
$disk->put('video/poster_large.webp', 'PL');
$disk->put('video/poster_small.webp', 'PS');
$disk->put('video/vid_hd.mp4', 'VHD');
$disk->put('video/vid_sd.mp4', 'VSD');
$disk->put('content_legacy-uuid.webp', 'LEG');
$disk->put('legacy-uuid.webp', 'LEG2');

run_test('DynamicAvatarAccessor: flat avatar size + main avatar_path', function () {
    $files = [];
    $f = (object)[
        'kind' => 'avatar',
        'type' => 'image',
        'names' => [
            'large' => 'img/ava_large.webp',
            'small' => 'img/ava_small.webp',
            'thumb' => 'img/ava_thumb.webp',
        ],
    ];
    $files[] = $f;

    $m = new TestModel('uuid-1', $files);
    $url = $m->avatar_path; // should pick display size 'large' from config
    assert_true(str_contains($url, 'img/ava_large.webp'), 'Expected large avatar URL');
    assert_true(str_contains($url, '?v='), 'Expected version param');
    $url2 = $m->small_avatar_path;
    assert_true(str_contains($url2, 'img/ava_small.webp'), 'Expected small avatar URL');
});

run_test('DynamicAvatarAccessor: profiled avatar size resolution and fallbacks', function () use ($disk) {
    $files = [];
    $files[] = (object) [
        'kind' => 'avatar',
        'type' => 'image',
        'names' => [
            'desktop' => [
                'large' => 'img/ava_large.webp',
                'thumb' => 'img/ava_thumb.webp',
            ],
            'mobile' => [
                'small' => 'img/mob_small.webp',
            ],
        ],
    ];

    $m = new TestModel('uuid-2', $files);
    assert_true($disk->exists('img/ava_large.webp'), 'Seed file missing: img/ava_large.webp');
    $existsFacade = \Illuminate\Support\Facades\Storage::disk('public')->exists('img/ava_large.webp');
    assert_true($existsFacade, 'Facade exists() false for img/ava_large.webp');
    $u1 = $m->desktop_large_avatar_path; // exact
    echo "DEBUG desktop_large URL: $u1\n";
    assert_true(str_contains($u1, 'img/ava_large.webp'), 'Expected desktop large to map to img/ava_large.webp');
    $u2 = $m->mobile_large_avatar_path; // fallback to any available in mobile -> small
    assert_true(str_contains($u2, 'img/mob_small.webp'), 'Expected mobile large fallback to mobile small');
});

run_test('DynamicAvatarAccessor: legacy avatar fallback', function () {
    $files = []; // no avatar file
    $m = new TestModel('legacy-uuid', $files);
    $u = $m->avatar_path;
    // Should resolve one of legacy names: content_legacy-uuid.webp or legacy-uuid.webp
    assert_true(str_contains($u, 'legacy-uuid.webp'));
});

run_test('DynamicAvatarAccessor: video and poster resolution', function () {
    $files = [];
    $files[] = (object) [
        'kind' => 'video_avatar',
        'type' => 'video',
        'names' => [
            'hd' => 'video/vid_hd.mp4',
            'sd' => 'video/vid_sd.mp4',
        ],
    ];
    $files[] = (object) [
        'kind' => 'video_poster',
        'type' => 'image',
        'names' => [
            'large' => 'video/poster_large.webp',
            'small' => 'video/poster_small.webp',
        ],
    ];

    $m = new TestModel('uuid-3', $files);
    $v = $m->video_path; // display defaults to hd
    assert_true(str_contains($v, 'video/vid_hd.mp4'));
    $p = $m->video_poster_path; // display defaults to large
    assert_true(str_contains($p, 'video/poster_large.webp'));
});

run_test('Helpers\\Media: mergeNamesWithDeletions', function () {
    $media = new Media();
    $existing = ['a' => 'A', 'b' => 'B'];
    $new = ['b' => 'B2', 'c' => 'C'];
    [$merged, $toDelete] = (new ReflectionClass(Media::class))
        ->getMethod('mergeNamesWithDeletions')
        ->invoke($media, $existing, $new);
    assert_equals($merged, ['a' => 'A', 'b' => 'B2', 'c' => 'C']);
    assert_equals($toDelete, ['B']);
});

run_test('Helpers\\Media: deletePhysicalFiles', function () {
    $media = new Media();
    $diskKey = 'public';
    $d = register_fake_disk($diskKey);
    $d->put('x/one.txt', '1');
    $d->put('x/two.txt', '2');

    (new ReflectionClass(Media::class))
        ->getMethod('deletePhysicalFiles')
        ->invoke($media, ['x/one.txt', 'x/two.txt'], $diskKey);

    assert_true(!$d->exists('x/one.txt') && !$d->exists('x/two.txt'), 'Files should be deleted');
});

run_test('Models: Content externalUrl normalizacja', function () {
    $model = new Dominservice\LaravelCms\Models\Content();
    $model->external_url = '  ';
    assert_true($model->external_url === null, 'Empty string should be normalized to null');
    $model->external_url = ' https://ex.am/ple ';
    assert_equals($model->external_url, 'https://ex.am/ple');
});

echo "\nWszystkie testy zakończone powodzeniem.\n";
