<?php

return [
    'date_format' => 'd/m/Y',
    'time_format' => 'H:i',
    'url_route' => null,

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
];
