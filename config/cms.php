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
    ],
    
    'disks' => [
        'category' => 'public',
        'content' => 'public',
        'content_video' => 'public',
    ],

    'avatar' => [
        'extension' => 'webp',
    ],
];
