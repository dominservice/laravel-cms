<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use Dominservice\LaravelCms\Traits\DynamicAvatarAccessor;
use Illuminate\Support\Facades\Storage;

/**
 * Test class for DynamicAvatarAccessor trait
 * Tests all avatar/video/poster resolution methods and edge cases
 */
class DynamicAvatarAccessorTest extends TestCase
{
    private $testObject;
    private $imageDisk;
    private $videoDisk;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup fake disks
        $this->imageDisk = register_fake_disk('images', 'http://localhost/images');
        $this->videoDisk = register_fake_disk('videos', 'http://localhost/videos');
        
        // Create test object with trait
        $this->testObject = new class {
            use DynamicAvatarAccessor;
            
            public $uuid = 'test-uuid-123';
            public $fileConfigKey = 'content';
            public $files = [];
            public $version = null;
            
            public function resolveFileByLogicalKind($kind) {
                return $this->files[$kind] ?? null;
            }
            
            public function getFileConfigKey($kind) {
                return $kind;
            }
            
            // Make protected methods public for testing
            public function testResolveAvatarUrlForSize($size) {
                return $this->resolveAvatarUrlForSize($size);
            }
            
            public function testResolveVideoUrlForSize($size) {
                return $this->resolveVideoUrlForSize($size);
            }
            
            public function testResolvePosterUrlForSize($size) {
                return $this->resolvePosterUrlForSize($size);
            }
            
            public function testResolveLegacyAvatarUrl($diskKey) {
                return $this->resolveLegacyAvatarUrl($diskKey);
            }
            
            public function testResolveLegacyVideoUrl($diskKey) {
                return $this->resolveLegacyVideoUrl($diskKey);
            }
            
            public function testResolveLegacyPosterUrl($diskKey) {
                return $this->resolveLegacyPosterUrl($diskKey);
            }
            
            public function testUrlWithVersion($diskKey, $name) {
                return $this->urlWithVersion($diskKey, $name);
            }
            
            public function testGet($key, $profile = null) {
                return $this->_get($key, $profile);
            }
        };
    }

    public function testResolveAvatarUrlForSizeWithValidFile()
    {
        // Setup file with names array
        $this->testObject->files['avatar'] = (object)[
            'kind' => 'avatar',
            'names' => [
                'small' => 'avatar_small.jpg',
                'medium' => 'avatar_medium.jpg'
            ]
        ];
        
        // Create test file
        $this->imageDisk->put('avatar_small.jpg', 'test content');
        
        $result = $this->testObject->testResolveAvatarUrlForSize('small');
        $this->assertEquals('http://localhost/images/avatar_small.jpg', $result);
    }

    public function testResolveAvatarUrlForSizeWithInvalidSize()
    {
        $this->testObject->files['avatar'] = (object)[
            'kind' => 'avatar',
            'names' => ['small' => 'avatar_small.jpg']
        ];
        
        $result = $this->testObject->testResolveAvatarUrlForSize('nonexistent');
        $this->assertNull($result);
    }

    public function testResolveAvatarUrlForSizeWithNoFile()
    {
        $result = $this->testObject->testResolveAvatarUrlForSize('small');
        $this->assertNull($result);
    }

    public function testResolveAvatarUrlForSizeWithNonArrayNames()
    {
        $this->testObject->files['avatar'] = (object)[
            'kind' => 'avatar',
            'names' => 'not-an-array'
        ];
        
        // Should fall back to legacy
        $this->imageDisk->put('avatar_test-uuid-123.jpg', 'legacy content');
        
        $result = $this->testObject->testResolveAvatarUrlForSize('small');
        $this->assertEquals('http://localhost/images/avatar_test-uuid-123.jpg', $result);
    }

    public function testResolveVideoUrlForSizeWithValidFile()
    {
        $this->testObject->files['video_avatar'] = (object)[
            'kind' => 'video_avatar',
            'names' => ['hd' => 'video_hd.mp4']
        ];
        
        $this->videoDisk->put('video_hd.mp4', 'video content');
        
        $result = $this->testObject->testResolveVideoUrlForSize('hd');
        $this->assertEquals('http://localhost/videos/video_hd.mp4', $result);
    }

    public function testResolvePosterUrlForSizeWithValidFile()
    {
        $this->testObject->files['video_poster'] = (object)[
            'kind' => 'video_poster',
            'names' => ['display' => 'poster_display.jpg']
        ];
        
        $this->imageDisk->put('poster_display.jpg', 'poster content');
        
        $result = $this->testResolvePosterUrlForSize('display');
        $this->assertEquals('http://localhost/images/poster_display.jpg', $result);
    }

    public function testResolveLegacyAvatarUrlWithWebp()
    {
        $this->imageDisk->put('avatar_test-uuid-123.webp', 'legacy webp');
        
        $result = $this->testObject->testResolveLegacyAvatarUrl('images');
        $this->assertEquals('http://localhost/images/avatar_test-uuid-123.webp', $result);
    }

    public function testResolveLegacyAvatarUrlWithJpg()
    {
        $this->imageDisk->put('avatar_test-uuid-123.jpg', 'legacy jpg');
        
        $result = $this->testObject->testResolveLegacyAvatarUrl('images');
        $this->assertEquals('http://localhost/images/avatar_test-uuid-123.jpg', $result);
    }

    public function testResolveLegacyAvatarUrlWithNoFiles()
    {
        $result = $this->testObject->testResolveLegacyAvatarUrl('images');
        $this->assertNull($result);
    }

    public function testResolveLegacyAvatarUrlWithEmptyUuid()
    {
        $this->testObject->uuid = '';
        $result = $this->testObject->testResolveLegacyAvatarUrl('images');
        $this->assertNull($result);
        
        $this->testObject->uuid = null;
        $result = $this->testObject->testResolveLegacyAvatarUrl('images');
        $this->assertNull($result);
    }

    public function testResolveLegacyVideoUrlWithAllFormats()
    {
        // Test priority order: webm, mp4, avi, mov
        $this->videoDisk->put('video_test-uuid-123.mp4', 'mp4 content');
        $this->videoDisk->put('video_test-uuid-123.webm', 'webm content');
        
        $result = $this->testObject->testResolveLegacyVideoUrl('videos');
        // Should return webm first (higher priority)
        $this->assertEquals('http://localhost/videos/video_test-uuid-123.webm', $result);
    }

    public function testResolveLegacyPosterUrlWithAllFormats()
    {
        // Test video_ prefix priority over poster_ prefix
        $this->imageDisk->put('poster_test-uuid-123.jpg', 'poster jpg');
        $this->imageDisk->put('video_test-uuid-123.webp', 'video webp');
        
        $result = $this->testObject->testResolveLegacyPosterUrl('images');
        // Should return video_ prefix first
        $this->assertEquals('http://localhost/images/video_test-uuid-123.webp', $result);
    }

    public function testUrlWithVersionWithoutVersion()
    {
        $this->imageDisk->put('test.jpg', 'content');
        
        $result = $this->testObject->testUrlWithVersion('images', 'test.jpg');
        $this->assertEquals('http://localhost/images/test.jpg', $result);
    }

    public function testUrlWithVersionWithVersion()
    {
        $this->testObject->version = 123;
        $this->imageDisk->put('test.jpg', 'content');
        
        $result = $this->testObject->testUrlWithVersion('images', 'test.jpg');
        $this->assertEquals('http://localhost/images/test.jpg?v=123', $result);
    }

    public function testUrlWithVersionNonExistentFile()
    {
        $result = $this->testObject->testUrlWithVersion('images', 'nonexistent.jpg');
        $this->assertNull($result);
    }

    public function testGetWithAvatarKey()
    {
        $this->imageDisk->put('avatar_test-uuid-123.jpg', 'avatar content');
        
        $result = $this->testObject->testGet('avatar');
        $this->assertNotNull($result);
        $this->assertStringContains('avatar_test-uuid-123.jpg', $result);
    }

    public function testGetWithAvatarVariant()
    {
        $result = $this->testObject->testGet('avatar_small');
        // Should try to resolve avatar with small variant
        $this->assertNull($result); // No MediaKitBridge setup in test
    }

    public function testGetWithVideoKey()
    {
        $result = $this->testObject->testGet('video');
        $this->assertNull($result); // No MediaKitBridge setup
    }

    public function testGetWithVideoVariant()
    {
        $result = $this->testObject->testGet('video_hd');
        $this->assertNull($result); // No MediaKitBridge setup
    }

    public function testGetWithPosterKey()
    {
        $result = $this->testObject->testGet('poster');
        $this->assertNull($result); // No MediaKitBridge setup
    }

    public function testGetWithPosterVariant()
    {
        $result = $this->testObject->testGet('poster_display');
        $this->assertNull($result); // No MediaKitBridge setup
    }

    public function testGetWithUnknownKey()
    {
        $result = $this->testObject->testGet('unknown_key');
        $this->assertNull($result);
    }

    public function testGetWithEmptyKey()
    {
        $result = $this->testObject->testGet('');
        $this->assertNull($result);
    }

    public function testVariantNormalization()
    {
        // Test that variants are properly cleaned
        $result = $this->testObject->testGet('avatar_path');
        $this->assertNull($result); // variant should be null for 'path'
        
        $result = $this->testObject->testGet('avatar_avatar');
        $this->assertNull($result); // variant should be null for 'avatar'
        
        $result = $this->testObject->testGet('video_video');
        $this->assertNull($result); // variant should be null for 'video'
        
        $result = $this->testObject->testGet('poster_poster');
        $this->assertNull($result); // variant should be null for 'poster'
    }

    public function testEdgeCaseWithNullDiskKey()
    {
        // Test when config doesn't return disk key
        $this->testObject->fileConfigKey = 'nonexistent';
        
        $result = $this->testObject->testResolveAvatarUrlForSize('small');
        $this->assertNull($result);
    }

    public function testEdgeCaseWithEmptyFileName()
    {
        $this->testObject->files['avatar'] = (object)[
            'kind' => 'avatar',
            'names' => ['small' => ''] // Empty filename
        ];
        
        $result = $this->testObject->testResolveAvatarUrlForSize('small');
        $this->assertNull($result);
    }

    public function testEdgeCaseWithNullFileName()
    {
        $this->testObject->files['avatar'] = (object)[
            'kind' => 'avatar',
            'names' => ['small' => null] // Null filename
        ];
        
        $result = $this->testObject->testResolveAvatarUrlForSize('small');
        $this->assertNull($result);
    }
}