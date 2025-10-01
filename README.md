# Dominservice Laravel CMS
[![Latest Version on Packagist](https://img.shields.io/packagist/v/dominservice/laravel-cms.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-cms)
[![Total Downloads](https://img.shields.io/packagist/dt/dominservice/laravel-cms.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-cms)
[![License](https://img.shields.io/packagist/l/dominservice/laravel-cms.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-cms)

A complete CMS package for Laravel (9–12) that provides:
- Models and migrations for contents and categories (multilingual, nested categories tree),
- File metadata (avatar and arbitrary file kinds),
- Video avatars and posters (thumbnails extracted from video),
- Flexible configuration of sizes, file-kind mapping and disks,
- NEW: Content-to-Content Links with time visibility window and metadata,
- NEW: Content metadata as an object (Content.meta JSON cast).

This README is a full rewrite in English, expanded with practical examples, a v2→v3 migration guide, and a step-by-step “Build your own CMS in Laravel (WordPress alternative)” walkthrough.

Table of contents
- Requirements
- Installation
- Publishing and migrations
- Configuration (config/cms.php) — detailed with examples
  - Tables
  - Disks
  - Image extension (avatar.extension)
  - Files and sizes (files.*)
  - Mappings: file_kind_map and file_config_key_map (ready-to-use scenarios)
  - Date/time formats (date_format/time_format)
- Models, traits and relations
  - Content, Category
  - ContentFile, CategoryFile (file metadata)
  - ContentLink (NEW) and the HasContentLinks trait
  - DynamicAvatarAccessor (images, videos, posters, dynamic accessors)
  - Content metadata as an object (NEW)
- Media Helper (image upload and processing)
  - uploadModelImage
  - uploadModelImageWithDefaults
  - Notes about videos and posters
- Usage examples (extended)
  - Categories and contents (multilingual)
  - Image upload and URL retrieval
  - Video and poster
  - Content ↔ Content links (NEW)
  - Advanced mappings and multiple disks
- Migration from v2 to v3 (checklist + snippet)
- Build your own CMS (WordPress alternative) — step by step
- Backward compatibility and fallbacks
- FAQ / Troubleshooting
- Contributing & Collaboration
- Support this project (Ko‑fi)
- License

Requirements
- PHP >= 8.0
- Laravel 9.x, 10.x, 11.x or 12.x
- astrotomic/laravel-translatable ^11.13 (multilingual)
- kalnoy/nestedset ^6.0 (nested tree)
- PHP ext-gd (image processing)

Installation
1) Install the package:
```bash
composer require dominservice/laravel-cms
```

2) The ServiceProvider is auto‑discovered (Laravel Package Discovery).

Publishing and migrations
- Publish configuration:
```bash
php artisan vendor:publish --provider="Dominservice\\LaravelCms\\ServiceProvider" --tag=config
```

- Publish migrations (CMS tables, files metadata, content links, etc.):
```bash
php artisan vendor:publish --provider="Dominservice\\LaravelCms\\ServiceProvider" --tag=migrations
```

- Run migrations:
```bash
php artisan migrate
```

Published migrations include (may vary by version):
- create_cms_tables
- add_columns_content_table
- create_redirects_table
- add_columns_category_content_table
- add_columns_categoryies_table
- create_video_table
- add_column_content_table
- create_files_tables (cms_content_files, cms_category_files)
- add_external_url_to_contents_table
- add_parent_to_content_table
- create_cms_content_links_table (NEW — content ↔ content links)
- add_meta_to_contents_table (NEW — adds a JSON meta column to contents)

Configuration (config/cms.php) — highlights
Check the published file to see the current values. Key options:

- tables — table names used by the package, e.g., cms_contents, cms_categories, cms_content_files, cms_category_files, cms_content_links, etc.
- disks — maps entity → Storage disk.
  - Multiple disks example: define disks in config/filesystems.php (S3, CDN-backed public, etc.), then in cms.php:
    ```php
    'disks' => [
      'content' => 'public',       // images for contents
      'content_video' => 's3',     // videos go to S3
      'category' => 'public',
    ],
    ```
- avatar.extension — output image extension (webp by default).
- files — definition of file types and their sizes per entity:
  - files.content.types.avatar.display — which size is returned by $model->avatar_path,
  - files.content.types.avatar.sizes — list of sizes to generate (except 'original' => null which is skipped on upload),
  - video_avatar (video) and video_poster (image poster) for Content,
  - analogous sections for Category,
  - you can add your own kinds, e.g., 'gallery', 'document', 'banner'.
- file_kind_map — map logical names (avatar, video_avatar, video_poster) to actual kind values stored in *_files.
  - Scenario: migrating from custom kind names. You want the accessor to look for 'hero' first, then 'avatar':
    ```php
    'file_kind_map' => [
      'avatar' => ['hero', 'avatar'],
      'video_avatar' => ['movie', 'video_avatar'],
      'video_poster' => ['movie_poster', 'video_poster'],
    ],
    ```
- file_config_key_map — map a kind to the configuration key (and disk) used to build URLs.
  - Globally or per-entity:
    ```php
    'file_config_key_map' => [
      // global
      'avatar' => 'content',
      'video_poster' => 'content',
      'video_avatar' => 'content_video',

      // scoped by base key (content/category)
      'content' => [
        'test' => 'test_123', // when ContentFile.kind = 'test' → use config key 'test_123'
      ],
    ],
    ```
- date_format, time_format — formats used by date accessors.

Models, traits and relations
- Content (Dominservice\LaravelCms\Models\Content)
  - Multilingual (Astrotomic Translatable), SoftDeletes.
  - Relations: categories() (MTM), children()/parent(), files(), avatarFile(), video()/videoPoster() (aliases for files), rootCategory().
  - Accessors: avatar_path, dynamic sizes {size}_avatar_path, video_path / {size}_video_avatar_path, video_poster_path / {size}_video_poster_path.
  - external_url normalization (empty → null; trimmed on set/get).
  - NEW: meta attribute cast to object (see next section).

- Category (Dominservice\LaravelCms\Models\Category)
  - Multilingual, nested tree (Nestedset), SoftDeletes.
  - Relations: contents() (MTM), files(), avatarFile().

- ContentFile and CategoryFile
  - Store file metadata: kind, type ('image'|'video'), names (JSON: size → filename). You can add custom kinds (e.g., 'gallery').

- ContentLink (NEW) and HasContentLinks trait
  - Table: cms_content_links; columns: from_uuid, to_uuid, relation (optional), position, meta (JSON), visible_from, visible_to.
  - Trait API: links(), backlinks(), linksOf(), backlinksOf(), visibleLinks(), attachLink(), detachLink().

DynamicAvatarAccessor (images, videos, posters)
- Provides URLs according to configuration; adds cache-busting (?v=mtime); per-request cache.
- Fallbacks: legacy naming for avatars/posters (content_{uuid}.webp, {uuid}.webp).
- Mappings: file_kind_map and file_config_key_map let you rename kinds and move disks without changing model code.

Content metadata as an object (NEW)
- The Content model defines a JSON column meta casted to an object:
  ```php
  // Dominservice\\LaravelCms\\Models\\Content
  protected $casts = [
      'external_url' => 'string',
      'meta' => 'object', // <— NEW: access as $content->meta (stdClass)
  ];
  ```
- Migration: publish and run the migration that adds the meta column to the contents table:
  ```bash
  php artisan vendor:publish --provider="Dominservice\\LaravelCms\\ServiceProvider" --tag=migrations
  php artisan migrate
  ```
  Look for a migration named similar to add_meta_to_contents_table.
- Usage examples:
  ```php
  $content = Content::create(['type' => 'article', 'status' => 1]);

  // Set as array (will be encoded to JSON and cast to object on read):
  $content->meta = [
      'reading_time' => 5,
      'featured' => true,
      'tags' => ['laravel','cms'],
  ];
  $content->save();

  // Read as object
  $meta = $content->meta;           // stdClass
  $isFeatured = $meta->featured ?? false;
  $tags = $meta->tags ?? [];

  // Update one field safely
  $meta = (array) $content->meta;   // cast to array if you need to mutate
  $meta['reading_time'] = 6;
  $content->meta = $meta;
  $content->save();
  ```

Media Helper (image upload and processing)
- uploadModelImage(Model $model, UploadedFile|string $source, string $kind = 'avatar', ?string $type = null, ?array $onlySizes = null, bool $replaceExisting = true)
  - Generates sizes based on config (skips 'original' => null), writes to disk, updates *_files.
  - When replaceExisting is true, removes previous variants.
- uploadModelImageWithDefaults(Model $model, array $sourcesBySize, ...)
  - 'default' plus per-size overrides (e.g., separate file only for 'thumb').
- Note: responsive profiles (mobile/desktop) on upload are intentionally disabled — stick to configured sizes.
- Videos: 'video_avatar' expects ready-made files (no transcoding). 'video_poster' is a regular image.

Usage examples (extended)
1) Categories and contents (create with translations)
```php
use Dominservice\\LaravelCms\\Models\\Category;
use Dominservice\\LaravelCms\\Models\\Content;

$cat = Category::create(['type' => 'section', 'status' => 1]);
$cat->translateOrNew('en')->name = 'News';
$cat->translateOrNew('en')->slug = 'news';
$cat->save();

$content = Content::create(['type' => 'article', 'status' => 1]);
$content->translateOrNew('en')->name = 'First post';
$content->translateOrNew('en')->slug = 'first-post';
$content->save();

$content->categories()->attach($cat->uuid);
```

2) Image upload and URL retrieval (controller + view)
```php
use Dominservice\\LaravelCms\\Helpers\\Media;
use Illuminate\\Http\\Request;

public function store(Request $request) {
    $content = Content::create(['type' => 'article', 'status' => 1]);
    $content->translateOrNew('en')->fill([
        'name' => (string) $request->string('name'),
        'slug' => (string) $request->string('slug'),
    ]);
    $content->save();

    if ($request->hasFile('avatar')) {
        Media::uploadModelImage($content, $request->file('avatar'), 'avatar');
    }

    return redirect()->route('content.show', $content->uuid);
}
```
View (Blade):
```blade
<img src="{{ $content->avatar_path }}" alt="{{ $content->name }}" />
<img src="{{ $content->thumb_avatar_path }}" alt="Thumbnail" />
```

3) Video and poster
```php
// Media::uploadModelVideos($content, ['hd' => $fileHd, 'sd' => $fileSd], 'video_avatar'); // conceptual example
Media::uploadModelImage($content, request()->file('poster'), 'video_poster');

$video = $content->video_avatar_path;         // e.g., hd
$poster = $content->video_poster_path;        // e.g., large
$mobileVideo = $content->mobile_video_avatar_path; // dynamic accessor if 'mobile' exists in names
```

4) Content ↔ Content links (NEW)
```php
use Dominservice\\LaravelCms\\Models\\Content;

$a = Content::first();
$b = Content::find($someUuid);

$a->attachLink($b, [
    'relation' => 'related',
    'position' => 10,
    'meta' => ['note' => 'editorial relation'],
    'visible_from' => now(),
]);

$visible = $a->visibleLinks('related')->get();
$incoming = $a->backlinks()->get();
```

5) Advanced mappings and multiple disks
- Serve avatars from an 'images' disk and videos from 's3':
```php
// config/cms.php
'disks' => [
  'content' => 'images',
  'content_video' => 's3',
],
'file_config_key_map' => [
  'video_avatar' => 'content_video',
  'video_poster' => 'content',
],
```
- Rename DB kinds without touching code (configuration only):
```php
'file_kind_map' => [
  'avatar' => ['hero', 'avatar'],
],
```

Migration from v2 to v3 (checklist + snippet)
Goal: move from v2 to v3 while keeping URLs and data intact.

Checklist:
1) Update the package to v3 and publish new config and migrations:
   ```bash
   composer require dominservice/laravel-cms:^3
   php artisan vendor:publish --provider="Dominservice\\LaravelCms\\ServiceProvider" --tag=config
   php artisan vendor:publish --provider="Dominservice\\LaravelCms\\ServiceProvider" --tag=migrations
   php artisan migrate
   ```
2) Configure disks in config/cms.php (especially content_video if video should live elsewhere).
3) Set file_kind_map if v2 used custom kind values in DB/files:
   ```php
   'file_kind_map' => [
     'avatar' => ['avatar'],
     'video_avatar' => ['video_avatar'],
     'video_poster' => ['video_poster'],
   ],
   ```
4) Set file_config_key_map so that videos are served from another disk if needed:
   ```php
   'file_config_key_map' => [
     'video_avatar' => 'content_video',
     'video_poster' => 'content',
   ],
   ```
5) File metadata (cms_content_files, cms_category_files):
   - v3 prefers metadata (names JSON). If you only have legacy files (e.g., content_{uuid}.webp), the accessor still finds them, but we recommend migrating to metadata.

Migration helper snippet (one‑off console example) which reads a legacy file as 'large' and saves it into *_files without removing the original:
```php
use Dominservice\\LaravelCms\\Models\\Content;
use Dominservice\\LaravelCms\\Models\\ContentFile;
use Illuminate\\Support\\Facades\\Storage;

Artisan::command('cms:migrate-avatars-legacy', function () {
    $diskKey = config('cms.disks.content');
    $ext = ltrim((string)config('cms.avatar.extension', 'webp'), '.');

    Content::query()->chunk(200, function ($chunk) use ($diskKey, $ext) {
        foreach ($chunk as $c) {
            $uuid = $c->uuid;
            $candidates = ["content_{$uuid}.{$ext}", "content{$uuid}.{$ext}", "{$uuid}.{$ext}"];
            foreach ($candidates as $name) {
                if (Storage::disk($diskKey)->exists($name)) {
                    ContentFile::firstOrCreate(
                        ['content_uuid' => $uuid, 'kind' => 'avatar'],
                        ['type' => 'image', 'names' => ['large' => $name]]
                    );
                    break;
                }
            }
        }
    });
    $this->info('Legacy avatars backfilled into metadata.');
});
```
6) If v2 stored video as a single file (video_{uuid}.mp4), in v3 you can:
   - leave it as a fallback (the accessor may detect it in some scenarios),
   - or create ContentFile(kind='video_avatar', type='video', names=['hd' => 'video_{uuid}.mp4']).
7) Dynamic properties in v3: you can access {size}_avatar_path, {size}_video_avatar_path, {size}_video_poster_path — update views if you want specific sizes.

Build your own CMS (WordPress alternative) — step by step
Below is a minimal publishing flow you can grow into a full CMS.

1) Routing (public + admin)
- Public (e.g., routes/web.php):
```php
use App\\Http\\Controllers\\ContentController;
Route::get('/{slug}', [ContentController::class, 'show'])->name('content.show');
```
- Admin (e.g., routes/admin.php): CRUD for contents and categories (Filament/Nova/Voyager work well since the models are standard Eloquent).

2) Content controller (public)
```php
namespace App\\Http\\Controllers;
use Dominservice\\LaravelCms\\Models\\Content;

class ContentController extends Controller
{
    public function show(string $slug)
    {
        $content = Content::whereTranslation('slug', $slug)->firstOrFail();
        $links = $content->visibleLinks()->get();
        return view('content.show', compact('content','links'));
    }
}
```

3) Admin form (create post)
- Validate translation fields (name, slug), status, type, and 'avatar' image.
- After save: `Media::uploadModelImage($content, $request->file('avatar'), 'avatar');`

4) Categories and navigation
- Build a tree with Category and the MTM relation. For menus, fetch categories of type 'section' and render according to the nested set.

5) SEO and internal linking
- Use ContentLink for "related posts", "see also", "featured" blocks.
- Set meta_title/meta_description in translations.

6) Media and performance
- Keep large videos on S3 (file_config_key_map → 'content_video').
- Serve images from a CDN (configure the URL for the 'public' or a dedicated 'images' disk).

7) Migrating from WordPress (conceptual)
- Export posts/pages to CSV/JSON.
- Import into Content (type = 'post'|'page').
- Convert media: upload to disk and create entries in *_files (avatar/gallery/banner).
- Use redirects (cms_redirects) to preserve legacy URLs.

Backward compatibility and fallbacks
- Avatar and poster: if *_files metadata is missing, the trait checks legacy names on disk (prefix + uuid + extension) and returns their URL when present.
- Video: for Content, legacy naming (video_{uuid}.mp4|webm) is supported as a fallback (helpers are available to clean old files when replaceExisting is used).
- Dynamic properties (e.g., small_avatar_path) are handled gracefully — if a specific size is missing, the trait tries other variants and profiles.

FAQ / Troubleshooting
- No URL in avatar_path — check the *_files record with kind = 'avatar' and the file presence on the disk pointed by config('cms.disks.*').
- Missing sizes — complete config('cms.files.{entity}.types.{kind}.sizes') or use uploadModelImageWithDefaults.
- Issues with video/poster — verify file_config_key_map for 'video_avatar' and 'video_poster' and the 'content_video' disk settings.
- Multilingual — remember to use translateOrNew('locale') and run translation migrations.
- v2→v3 migration — follow the checklist and snippet above; publish migrations and configure mappings first.

Contributing & Collaboration
- Contributions, ideas, and collaboration offers are very welcome. Feel free to open issues and pull requests.
- If you want to discuss a feature or commercial collaboration, please reach out via GitHub issues.

Support this project (Ko‑fi)
If this package saves you time, consider buying me a coffee: https://ko-fi.com/dominservice — thank you!

License
MIT
