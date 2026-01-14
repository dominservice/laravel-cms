<?php

return [
    'date_format' => 'd/m/Y',
    'time_format' => 'H:i',
    'url_route' => null,

    // Content/category types can be declared as enum classes or plain arrays.
    // You can override these in your app config to use custom enums.
    'types' => [
        'content' => \Dominservice\LaravelCms\Enums\ContentType::class,
        'category' => \Dominservice\LaravelCms\Enums\CategoryType::class,
    ],

    'tables' => [
        'categories' => 'cms_categories',
        'category_translations' => 'cms_categories_translations',
        'contents' => 'cms_contents',
        'content_translations' => 'cms_contents_translations',
        'content_categories' => 'cms_content_categories',
        'content_video' => 'cms_content_videos',
        'redirects' => 'cms_redirects',
        // New dependent tables for file metadata
        'content_files' => 'cms_content_files',
        'category_files' => 'cms_category_files',

        'content_links' => 'cms_content_links',
    ],
    
    'disks' => [
        'category' => 'public',
        'content' => 'public',
        'content_video' => 'public',
    ],

    // Optional: map file "kind" (from cms_*_files.kind) or model kind/type to a different
    // file config key (and disk). Mapping priority: file.kind → model kind/type.
    // Examples:
    // 'file_config_key_map' => [
    //     // Map by file kind globally (works for both content/category models)
    //     'avatar' => 'content_images',
    //     'video_avatar' => 'content_video',
    //     'video_poster' => 'content_images',
    //     // Or scoped mapping per base fileConfigKey (e.g., content/category)
    //     'content' => [
    //         'test' => 'test_123',        // file.kind = 'test' → use config key 'test_123'
    //         'avatar' => 'content_images'
    //     ],
    // ],
    'file_config_key_map' => [
        // empty by default
    ],

    // Optional: map logical kind names used by accessors (avatar, video_avatar, video_poster, ...)
    // to actual ContentFile/CategoryFile.kind values stored in DB. Order matters.
    // Examples:
    // 'file_kind_map' => [
    //     'avatar' => ['testowy', 'avatar'],          // prefer files saved as kind 'testowy', fallback to 'avatar'
    //     'video_avatar' => ['movie', 'video_avatar'],
    //     'video_poster' => ['movie_poster', 'video_poster'],
    // ],
    'file_kind_map' => [
        // empty by default
    ],

    // Backward-compat for legacy avatar naming (still used as fallback)
    'avatar' => [
        'extension' => 'webp',
    ],

    // New configurable file types and sizes per entity
    'files' => [
        'content' => [
            // Types of files and their size variants
            // You can add more types (e.g., 'gallery', 'document') and custom sizes
            'types' => [
                'avatar' => [
                    // Which size should be exposed as the main `avatar_path`
                    'display' => 'large',
                    'sizes' => [
                        'original' => null, // keep original
                        'large' => ['w' => 1920, 'h' => 1080, 'fit' => 'contain'],
                        'small' => ['w' => 640, 'h' => 360, 'fit' => 'contain'],
                        'thumb' => ['w' => 160, 'h' => 160, 'fit' => 'cover'],
                    ],
                ],
                'additional' => [
                    'sizes' => [
                        'original' => null,
                        'large' => ['w' => 1920, 'h' => 1080, 'fit' => 'contain'],
                        'small' => ['w' => 640, 'h' => 360, 'fit' => 'contain'],
                        'thumb' => ['w' => 160, 'h' => 160, 'fit' => 'cover'],
                    ],
                ],
                // New: video avatar variants (no transcoding here, just naming/validation)
                'video_avatar' => [
                    'display' => 'hd',
                    'sizes' => [
                        'hd' => [],      // 1080p/720p file provided by user
                        'sd' => [],      // 480p file provided by user
                        'mobile' => [],  // smaller/mobile
                    ],
                ],
                // New: poster image (first frame) for video avatar; uses image pipeline
                'video_poster' => [
                    'display' => 'large',
                    'sizes' => [
                        'original' => null,
                        'large' => ['w' => 1920, 'h' => 1080, 'fit' => 'contain'],
                        'small' => ['w' => 640, 'h' => 360, 'fit' => 'contain'],
                        'thumb' => ['w' => 160, 'h' => 160, 'fit' => 'cover'],
                    ],
                ],
            ],
        ],
        'category' => [
            'types' => [
                'avatar' => [
                    // Which size should be exposed as the main `avatar_path`
                    'display' => 'large',
                    'sizes' => [
                        'original' => null,
                        'large' => ['w' => 1920, 'h' => 1080, 'fit' => 'contain'],
                        'small' => ['w' => 640, 'h' => 360, 'fit' => 'contain'],
                        'thumb' => ['w' => 160, 'h' => 160, 'fit' => 'cover'],
                    ],
                ],
                'additional' => [
                    'sizes' => [
                        'original' => null,
                        'large' => ['w' => 1920, 'h' => 1080, 'fit' => 'contain'],
                        'small' => ['w' => 640, 'h' => 360, 'fit' => 'contain'],
                        'thumb' => ['w' => 160, 'h' => 160, 'fit' => 'cover'],
                    ],
                ],
            ],
        ],
    ],

    'default_pages' => [
        'home' => [
            'page_uuid' => null,
            'meta_title' => null,
            'meta_description' => null,
            'meta_keywords' => null,
            'blocks' => [
                'hero_section' => null,
            ],
        ],
        'contact' => [
            'page_uuid' => null,
        ],
        'regulations' => [
            'page_uuid' => null,
        ],
        'blog_category' => [
            'page_uuid' => null,
        ],
        'business' => [
            'training' => [
                'page_uuid' => null,
                'top_menu' => true,
                'footer_menu' => false,
                'category' => false,
                'order' => 1,
            ],
        ],
        'other' => [
            'about' => [
                'page_uuid' => null,
                'top_menu' => false,
                'footer_menu' => false,
                'category' => false,
                'order' => 1,
            ],
        ],
    ],

    'pages' => [
        /**
         * 'unique-id-for-cms-page' => [
         *      'top_menu' => true,
         *      'footer_menu' => false,
         *      'category' => false,
         *      'order' => 6,
         *      'slug' => [
         *          'pl' => 'slug-pl',
         *          'en' => 'slug-en',
         *      ],
         *      'name' => [
         *          'pl' => 'name-pl',
         *          'en' => 'name-en',
         *      ],
         *      'pages' => [
         *          'unique-id-for-cms-page' => [
         *              'order' => 6,
         *              'slug' => [
         *                  'pl' => 'slug-pl',
         *                  'en' => 'slug-en',
         *              ],
         *              'name' => [
         *                  'pl' => 'name-pl',
         *                  'en' => 'name-en',
         *              ],
         *          ],
         *      ],
         * ],
         */
    ],

    'business_pages' => [
        /**
         * 'unique-id-for-cms-page' => [
         *      'top_menu' => true,
         *      'footer_menu' => false,
         *      'category' => false,
         *      'slug' => [
         *          'pl' => 'slug-pl',
         *          'en' => 'slug-en',
         *      ],
         *      'name' => [
         *          'pl' => 'name-pl',
         *          'en' => 'name-en',
         *      ],
         * ],
         */
    ],

    'blog_pages' => [
        /**
         * 'unique-id-for-cms-page' => [
         *      'slug' => [
         *          'pl' => 'slug-pl',
         *          'en' => 'slug-en',
         *      ],
         *      'name' => [
         *          'pl' => 'name-pl',
         *          'en' => 'name-en',
         *      ],
         * ],
         */
    ],

    'admin' => [
        'enabled' => true,
        'prefix' => 'cms',
        'route_name_prefix' => 'cms.',
        'middleware' => ['web', 'auth'],
        'layout' => [
            // package|extends|component
            'mode' => 'package',
            // Used by package or extends modes.
            'view' => 'cms::layouts.bootstrap',
            // Section name for extends mode.
            'section' => 'content',
            // Component name for component mode (<x-dynamic-component>).
            'component' => 'admin-layout',
        ],
        'ui' => [
            // bootstrap|tailwind
            'theme' => 'bootstrap',
            // Optional overrides for the active theme.
            'classes' => [],
            // Presets used by the package layouts.
            'presets' => [
                'bootstrap' => [
                    'container' => 'container py-4',
                    'card' => 'card mb-4',
                    'card_header' => 'card-header',
                    'card_title' => 'card-title',
                    'card_body' => 'card-body',
                    'card_footer' => 'card-footer text-end',
                    'header_row' => 'd-flex justify-content-between align-items-center',
                    'table' => 'table table-striped',
                    'button' => 'btn btn-primary',
                    'button_secondary' => 'btn btn-outline-secondary',
                    'button_link' => 'btn btn-link p-0',
                    'input' => 'form-control',
                    'select' => 'form-select',
                    'textarea' => 'form-control',
                    'label' => 'form-label',
                    'form_group' => 'mb-3',
                    'badge' => 'badge bg-secondary',
                    'tabs' => 'nav nav-tabs mb-3',
                    'tab_item' => 'nav-item',
                    'tab_link' => 'nav-link',
                    'tab_content' => 'tab-content',
                    'tab_pane' => 'tab-pane fade',
                ],
                'tailwind' => [
                    'container' => 'mx-auto max-w-6xl px-6 py-6',
                    'card' => 'rounded-xl border border-slate-200 bg-white shadow-sm mb-6',
                    'card_header' => 'border-b border-slate-200 px-6 py-4',
                    'card_title' => 'text-lg font-semibold text-slate-900',
                    'card_body' => 'px-6 py-4',
                    'card_footer' => 'border-t border-slate-200 px-6 py-4 text-right',
                    'header_row' => 'flex items-center justify-between',
                    'table' => 'w-full text-sm text-left',
                    'button' => 'inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white',
                    'button_secondary' => 'inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700',
                    'button_link' => 'text-slate-600 hover:text-slate-900',
                    'input' => 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm',
                    'select' => 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm',
                    'textarea' => 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm',
                    'label' => 'block text-sm font-medium text-slate-700',
                    'form_group' => 'mb-4',
                    'badge' => 'inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700',
                    'tabs' => 'flex gap-2 border-b border-slate-200 mb-4',
                    'tab_item' => '',
                    'tab_link' => 'px-3 py-2 text-sm font-medium text-slate-600',
                    'tab_content' => '',
                    'tab_pane' => '',
                ],
            ],
        ],
        'content' => [
            'include_all' => true,
            'default_columns' => ['uuid', 'name', 'type', 'status'],
            'default_form_fields' => [
                'type',
                'name',
                'sub_name',
                'short_description',
                'description',
                'meta_title',
                'meta_keywords',
                'meta_description',
                'status',
                'is_nofollow',
                'external_url',
                'category_uuid',
                'media',
            ],
            'sections' => [
                // Example of a single page with blocks.
                // 'home' => [
                //     'label' => 'Home',
                //     'type' => 'page',
                //     'config_key' => 'cms.default_pages.home.page_uuid',
                //     'columns' => ['uuid', 'name', 'type', 'status'],
                //     'blocks' => [
                //         'hero_section' => [
                //             'label' => 'Hero section',
                //             'type' => 'block',
                //             'config_key' => 'cms.default_pages.home.blocks.hero_section',
                //         ],
                //     ],
                // ],
                // Example of a group section stored under cms.default_pages.other.*.
                // 'other' => [
                //     'label' => 'Other pages',
                //     'type' => 'page',
                //     'group_key' => 'cms.default_pages.other',
                //     'item_key' => 'page_uuid',
                //     'allow_create' => true,
                //     'defaults' => [
                //         'top_menu' => false,
                //         'footer_menu' => false,
                //         'category' => false,
                //     ],
                // ],
            ],
        ],
        'category' => [
            'include_all' => true,
            'default_columns' => ['uuid', 'name', 'type', 'parent_uuid', 'status'],
            'default_form_fields' => [
                'type',
                'name',
                'description',
                'meta_title',
                'meta_keywords',
                'meta_description',
                'parent_uuid',
                'status',
                'media',
            ],
            'sections' => [
                // Example:
                // 'categories' => [
                //     'label' => 'Categories',
                //     'columns' => ['uuid', 'name', 'type', 'status'],
                // ],
            ],
        ],
    ],

    'routes' => [
        'enabled' => false,
        'middleware' => ['web'],
        'use_locales' => true,
        'use_locale_prefix' => true,
        'locale_middleware' => 'language',
        'translation_group' => 'routes',
        'translated_slugs' => true,
        'page_view' => 'cms::frontend.page',
        'pages' => [
            // Example:
            // 'home' => [
            //     'label' => 'Home',
            //     'slug' => '/',
            //     'translated' => true,
            //     'content_key' => 'cms.default_pages.home.page_uuid',
            //     'view' => 'cms::frontend.page',
            // ],
        ],
        'category' => [
            'enabled' => true,
            'prefix' => 'category',
            'route_name' => 'category.show',
            'view' => 'cms::frontend.category',
        ],
    ],

    'redirects' => [],
];
