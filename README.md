<p align="center">
  <img src="docs/logo.png" alt="Dominservice Laravel CMS" width="600" />
</p>

<h1 align="center">dominservice/laravel-cms</h1>

<p align="center">
  Reusable Laravel CMS for multilingual pages, blocks, categories and media-driven content structures.
</p>

<p align="center">
  <a href="https://packagist.org/packages/dominservice/laravel-cms"><img src="https://img.shields.io/packagist/v/dominservice/laravel-cms.svg" alt="Packagist"></a>
  <a href="https://packagist.org/packages/dominservice/laravel-cms/stats"><img src="https://img.shields.io/packagist/dt/dominservice/laravel-cms.svg" alt="Downloads"></a>
  <a href="#"><img src="https://img.shields.io/badge/PHP-8.3%2B-777bb3" alt="PHP 8.3+"></a>
  <a href="#"><img src="https://img.shields.io/badge/Laravel-13-ff2d20" alt="Laravel 13"></a>
  <a href="#"><img src="https://img.shields.io/badge/Livewire-4-fb70a9" alt="Livewire 4"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
</p>

## What This Package Solves

`dominservice/laravel-cms` is designed for projects that need a practical CMS layer on top of Laravel, without locking the application into a closed website builder.

It gives you:
- multilingual `page` and `block` content models,
- configurable admin sections for content and categories,
- config-driven settings dashboards,
- Media Kit integration for images and video slots,
- publishable views that can be adapted to any admin theme,
- a structure that can start as a company website and grow into a larger CRM, ERP or portal.

## Key Features

- multilingual contents and categories,
- nested category trees,
- configurable admin routes, middleware, layout mode and route names,
- block-oriented homepage and static page management via `cms.default_pages.*`,
- configurable block schemas stored in `meta`,
- repeater fields for cards, FAQ items and similar structured content,
- Editor.js profile support for rich text fields,
- media slots for desktop/mobile image and video handling,
- direct integration with `dominservice/laravel-media-kit`, including reusable library assets,
- publishable views and language files.

## Requirements

The 5.x package line targets the current DominPress application stack:

- PHP 8.3 or newer,
- Laravel 13.32 or newer,
- Livewire 4.4 or newer,
- `kalnoy/nestedset` 7.x.

Laravel 9–12 and Livewire 3 remain supported by the previous 4.x package line. They are not supported by 5.x because the current `kalnoy/nestedset` release requires Illuminate 13.

## Installation

```bash
composer require dominservice/laravel-cms dominservice/laravel-media-kit

php artisan vendor:publish --provider="Dominservice\LaravelCms\ServiceProvider" --tag=config
php artisan vendor:publish --provider="Dominservice\LaravelCms\ServiceProvider" --tag=views
php artisan vendor:publish --provider="Dominservice\LaravelCms\ServiceProvider" --tag=lang
php artisan vendor:publish --provider="Dominservice\LaravelCms\ServiceProvider" --tag=migrations

php artisan vendor:publish --provider="Dominservice\MediaKit\MediaKitServiceProvider" --tag=mediakit-config
php artisan vendor:publish --provider="Dominservice\MediaKit\MediaKitServiceProvider" --tag=mediakit-migrations

php artisan migrate
```

## Admin Panel

The package includes a configurable admin panel for content, categories and settings.

Main configuration lives in `config/cms.php`:

- `cms.admin.enabled`
- `cms.admin.prefix`
- `cms.admin.route_name_prefix`
- `cms.admin.middleware`
- `cms.admin.components.*`
- `cms.admin.layout.mode`
- `cms.admin.layout.view`
- `cms.admin.layout.section`
- `cms.admin.layout.component`

Layout integration supports three modes:

- `package`: use package-provided layout,
- `extends`: render inside your existing Blade layout,
- `component`: render inside a Blade component.

This makes it easy to keep package logic inside the package, while styling the UI in the host project through published views.

Applications may replace individual admin Livewire classes without duplicating the package routes. Every configured class must extend `Livewire\Component`; omitted keys keep the package implementation:

```php
'admin' => [
    'components' => [
        'content_index' => \App\Livewire\Cms\ContentIndex::class,
        'content_form' => \App\Livewire\Cms\ContentForm::class,
        'category_index' => \App\Livewire\Cms\CategoryIndex::class,
        'category_form' => \App\Livewire\Cms\CategoryForm::class,
        'settings_dashboard' => \App\Livewire\Cms\SettingsDashboard::class,
    ],
],
```

The content and category index classes expose `filterMappedItems()` as a protected extension hook. A host integration can therefore add allowlisted search and sorting while retaining section resolution, URLs and delete behavior from the package.

## Content Types, Blocks and Schemas

The package supports configurable content and category types. You can keep simple arrays in config or point to your own enum classes.

```php
'types' => [
    'content' => \App\Enums\CmsContentType::class,
    'category' => \App\Enums\CmsCategoryType::class,
],
```

For block-based pages you can define sections in config and assign form fields, media slots and schema fields per section or block.

Typical use cases:
- homepage sections such as `hero`, `about`, `services`, `faq`, `cta`,
- static pages with configurable visual variants,
- reusable block types for landing pages and company websites.

### Schema Fields in `meta`

Block-specific configuration is stored in `meta`, so the host project can render frontend components using its own Blade views and CSS.

The Livewire form renders and validates these fields automatically. Adding a field does not require a migration, controller, Livewire component or another Bootstrap/Tailwind template:

```php
'admin' => [
    'content' => [
        'schema_presets' => [
            'cta' => [
                'cta_label' => [
                    'type' => 'text',
                    'translatable' => true,
                ],
                'cta_url' => 'url',
            ],
        ],
        'sections' => [
            'home' => [
                'label' => 'Home',
                'blocks' => [
                    'hero' => [
                        'type' => 'block',
                        'schema_presets' => ['cta'],
                        'schema_fields' => [
                            'eyebrow' => [
                                'translatable' => true,
                                'required' => true,
                            ],
                            'alignment' => [
                                'type' => 'select',
                                'options' => [
                                    'left' => 'Left',
                                    'center' => 'Center',
                                ],
                                'default' => 'left',
                            ],
                            'metrics' => [
                                'type' => 'repeater',
                                'translatable' => true,
                                'synchronize_structure' => true,
                                'fields' => [
                                    'value' => ['required' => true],
                                    'label' => 'text',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
],
```

Supported field types are `text`, `textarea`, `editorjs`, `url`, `email`, `number`, `date`, `datetime-local`, `select`, `checkbox`/`boolean`/`toggle` and one-level `repeater`. A string value is shorthand for a type, for example `'cta_url' => 'url'`. Use `required`, `default`, `placeholder`, `help`, `options` and `rules` to customize a field. Explicit `rules` replace the inferred validation rules.

Non-translatable values are stored directly in `Content::meta`; translated values are stored under `meta._translations.{locale}`. This schema layer changes form generation only and does not replace the host project's theme or public Blade components.

## Media Integration

The package uses `dominservice/laravel-media-kit` as the media layer.

Current integration supports:
- desktop and mobile images,
- desktop and mobile video posters,
- image and video slots in CMS forms,
- importing already uploaded assets from the shared media library,
- keeping CMS views publishable, so the host project can replace the UI without changing package logic.

### Media Picker Configuration

```php
'admin' => [
    'content' => [
        'media_picker' => [
            'enabled' => true,
            'browse_route' => 'admin.media.index',
            'label' => 'Biblioteka mediów',
            'helper' => 'Wybierz istniejący asset z biblioteki lub wgraj nowy plik.',
        ],
    ],
],
```

The package exposes backend support for selecting library asset UUIDs. The host project can publish and extend the form views to provide a custom picker UX.

## Editor.js Profiles

The package supports Editor.js configuration profiles so projects can choose a richer or more compact toolset per field.

Example:

```php
'admin' => [
    'content' => [
        'editorjs' => [
            'profiles' => [
                'default' => [
                    'tools' => ['paragraph', 'header', 'list', 'quote'],
                ],
                'compact' => [
                    'tools' => ['paragraph', 'list'],
                ],
            ],
            'field_profiles' => [
                'description' => 'default',
                'faq_items.answer' => 'compact',
            ],
        ],
    ],
],
```

## Settings Dashboard

The settings dashboard is driven by configuration and can sync UUID-based assignments back into structured config arrays.

Useful for:
- assigning homepage page UUIDs,
- assigning homepage blocks,
- syncing public menu structures,
- storing multilingual meta fields,
- managing config-driven CMS sections without writing extra controllers.

## Views and Overrides

The package is meant to be published and themed.

```bash
php artisan vendor:publish --provider="Dominservice\LaravelCms\ServiceProvider" --tag=views
php artisan vendor:publish --provider="Dominservice\LaravelCms\ServiceProvider" --tag=lang
```

Recommended approach:
- keep routes, controllers, Livewire classes and storage logic in the package,
- publish package views,
- override the views in the host project to match the chosen admin theme.

This keeps the CMS reusable between projects while still allowing tailored UI.

## Extensibility

The package is intentionally config-first and open to project-level extension.

You can extend it through:
- your own enum classes for content and category types,
- additional block schemas in app config,
- published views,
- custom frontend renderers,
- custom permissions and middleware on admin routes,
- custom settings panels and sync indexes.

## Upgrade Notes

When upgrading existing projects:
- merge new config keys instead of overwriting the whole file,
- republish views only if you want the new default UI,
- republish migrations only when needed,
- verify `media-kit` config and routes if you use the shared media library.

### Upgrading from 4.x to 5.x

- upgrade the host application to PHP 8.3+, Laravel 13.32+ and Livewire 4.4+ first,
- review published Livewire views before replacing them with the package defaults,
- if the host application customizes `Livewire::setUpdateRoute()`, update its callback to accept both `$handle` and `$path`, then register the route using the hash-aware `$path` supplied by Livewire 4,
- clear application caches after updating the package and run the host application's CMS and browser-facing regression tests.

## License

MIT
