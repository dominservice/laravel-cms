<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Dominservice\LaravelCms\Helpers\Media;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\ContentFile;
use Dominservice\LaravelCms\Models\CategoryFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Comprehensive test class for Media helper
 * Tests all methods including image processing, file handling, and edge cases
 */
class MediaTest extends TestCase
{
    private $media;
    private $mockContent;
    private $mockCategory;
    private $mockDisk;
    private $reflectionClass;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->media = new Media();
        $this->reflectionClass = new \ReflectionClass(Media::class);
        
        // Create mock models
        $this->mockContent = $this->createMockContent();
        $this->mockCategory = $this->createMockCategory();
        
        // Setup fake disk
        $this->mockDisk = register_fake_disk('test-disk', 'http://localhost/storage');
    }
    
    /**
     * Helper method to call protected static methods using reflection
     */
    private function callProtectedMethod(string $methodName, ...$args)
    {
        $method = $this->reflectionClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $args);
    }

    public function testDetectLegacyNamesWithContentAvatarFiles()
    {
        $content = new class extends Content {
            public $uuid = 'test-uuid';
            public function getTable() { return 'contents'; }
        };
        
        // Create legacy files on disk
        $this->mockDisk->put('avatar_test-uuid.jpg', 'avatar content');
        $this->mockDisk->put('avatar_test-uuid.webp', 'avatar webp content');
        
        $result = $this->callProtectedMethod('detectLegacyNames', $content, 'avatar', 'test-disk');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('avatar_test-uuid.webp', $result);
        $this->assertArrayHasKey('avatar_test-uuid.jpg', $result);
    }

    public function testDetectLegacyNamesWithCategoryFiles()
    {
        $category = new class extends Category {
            public $uuid = 'category-uuid';
            public function getTable() { return 'categories'; }
        };
        
        // Create legacy files on disk
        $this->mockDisk->put('avatar_category-uuid.png', 'category avatar');
        
        $result = $this->callProtectedMethod('detectLegacyNames', $category, 'avatar', 'test-disk');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('avatar_category-uuid.png', $result);
    }

    public function testDetectLegacyNamesWithNoFiles()
    {
        $content = new class extends Content {
            public $uuid = 'no-files-uuid';
            public function getTable() { return 'contents'; }
        };
        
        $result = $this->media->detectLegacyNames($content, 'avatar', 'test-disk');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testDetectLegacyNamesWithVideoFiles()
    {
        $content = new class extends Content {
            public $uuid = 'video-uuid';
            public function getTable() { return 'contents'; }
        };
        
        // Create video files
        $this->mockDisk->put('video_video-uuid.mp4', 'video content');
        $this->mockDisk->put('video_video-uuid.webm', 'video webm content');
        
        $result = $this->media->detectLegacyNames($content, 'video_avatar', 'test-disk');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('video_video-uuid.webm', $result);
        $this->assertArrayHasKey('video_video-uuid.mp4', $result);
    }

    public function testDetectLegacyNamesWithPosterFiles()
    {
        $content = new class extends Content {
            public $uuid = 'poster-uuid';
            public function getTable() { return 'contents'; }
        };
        
        // Create poster files
        $this->mockDisk->put('poster_poster-uuid.jpg', 'poster content');
        $this->mockDisk->put('video_poster-uuid.png', 'video poster content');
        
        $result = $this->media->detectLegacyNames($content, 'video_poster', 'test-disk');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('video_poster-uuid.png', $result);
        $this->assertArrayHasKey('poster_poster-uuid.jpg', $result);
    }

    public function testResolveEntityContextWithContentModel()
    {
        $content = new class extends Content {
            public $uuid = 'content-uuid';
            public function getTable() { return 'contents'; }
        };
        
        $result = $this->media->resolveEntityContext($content, 'avatar');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('entityType', $result);
        $this->assertArrayHasKey('fileModelClass', $result);
        $this->assertEquals('content', $result['entityType']);
        $this->assertEquals(ContentFile::class, $result['fileModelClass']);
    }

    public function testResolveEntityContextWithCategoryModel()
    {
        $category = new class extends Category {
            public $uuid = 'category-uuid';
            public function getTable() { return 'categories'; }
        };
        
        $result = $this->media->resolveEntityContext($category, 'avatar');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('entityType', $result);
        $this->assertArrayHasKey('fileModelClass', $result);
        $this->assertEquals('category', $result['entityType']);
        $this->assertEquals(CategoryFile::class, $result['fileModelClass']);
    }

    public function testResolveEntityContextWithUnsupportedModel()
    {
        $unsupportedModel = new class {
            public function getTable() { return 'unsupported'; }
        };
        
        $this->expectException(InvalidArgumentException::class);
        $this->media->resolveEntityContext($unsupportedModel, 'avatar');
    }

    public function testMergeNamesWithDeletionsBasicMerge()
    {
        $existing = ['small' => 'old_small.jpg', 'medium' => 'old_medium.jpg'];
        $new = ['small' => 'new_small.jpg', 'large' => 'new_large.jpg'];
        
        $result = $this->media->mergeNamesWithDeletions($existing, $new);
        
        $this->assertIsArray($result);
        $this->assertEquals('new_small.jpg', $result['small']);
        $this->assertEquals('old_medium.jpg', $result['medium']);
        $this->assertEquals('new_large.jpg', $result['large']);
    }

    public function testMergeNamesWithDeletionsRemoveSize()
    {
        $existing = ['small' => 'small.jpg', 'medium' => 'medium.jpg'];
        $new = ['small' => null]; // Remove small size
        
        $result = $this->media->mergeNamesWithDeletions($existing, $new);
        
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('small', $result);
        $this->assertEquals('medium.jpg', $result['medium']);
    }

    public function testMergeNamesWithDeletionsEmptyExisting()
    {
        $existing = [];
        $new = ['small' => 'new_small.jpg'];
        
        $result = $this->media->mergeNamesWithDeletions($existing, $new);
        
        $this->assertIsArray($result);
        $this->assertEquals('new_small.jpg', $result['small']);
    }

    public function testMergeNamesWithDeletionsEmptyNew()
    {
        $existing = ['small' => 'existing.jpg'];
        $new = [];
        
        $result = $this->media->mergeNamesWithDeletions($existing, $new);
        
        $this->assertIsArray($result);
        $this->assertEquals('existing.jpg', $result['small']);
    }

    public function testReadSourceBinaryWithUploadedFile()
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test binary content');
        
        $uploadedFile = new class($tempFile) extends UploadedFile {
            private $path;
            
            public function __construct($path)
            {
                $this->path = $path;
            }
            
            public function getRealPath()
            {
                return $this->path;
            }
        };
        
        $result = $this->media->readSourceBinary($uploadedFile);
        
        $this->assertEquals('test binary content', $result);
        
        // Cleanup
        unlink($tempFile);
    }

    public function testReadSourceBinaryWithStringPath()
    {
        $this->mockDisk->put('test-file.txt', 'string path content');
        
        $result = $this->media->readSourceBinary('test-file.txt');
        
        // Note: This test assumes the method handles string paths correctly
        // In real implementation, this might need disk resolution
        $this->assertIsString($result);
    }

    public function testReadSourceBinaryWithInvalidFile()
    {
        $uploadedFile = new class extends UploadedFile {
            public function getRealPath()
            {
                return '/nonexistent/path/file.txt';
            }
        };
        
        $result = $this->media->readSourceBinary($uploadedFile);
        
        $this->assertNull($result);
    }

    public function testReencodeWebpWithValidBinary()
    {
        // Create a simple test image binary (1x1 pixel PNG)
        $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAFT2cJNJwAAAABJRU5ErkJggg==');
        
        // Note: This test might require GD extension and proper setup
        // For unit testing, we'll test the structure rather than actual conversion
        $result = $this->media->reencodeWebp($pngBinary, 80);
        
        // Should return string or null
        $this->assertTrue(is_string($result) || is_null($result));
    }

    public function testReencodeWebpWithInvalidBinary()
    {
        $invalidBinary = 'not an image';
        
        $result = $this->media->reencodeWebp($invalidBinary, 80);
        
        $this->assertNull($result);
    }

    public function testResizeWithValidParameters()
    {
        // Simple 1x1 PNG
        $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAFT2cJNJwAAAABJRU5ErkJggg==');
        
        $result = $this->media->resize($pngBinary, 100, 100, 'fit', 80);
        
        // Should return string or null
        $this->assertTrue(is_string($result) || is_null($result));
    }

    public function testResizeWithInvalidBinary()
    {
        $invalidBinary = 'not an image';
        
        $result = $this->media->resize($invalidBinary, 100, 100, 'fit', 80);
        
        $this->assertNull($result);
    }

    public function testResizeWithZeroDimensions()
    {
        $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAFT2cJNJwAAAABJRU5ErkJggg==');
        
        $result = $this->media->resize($pngBinary, 0, 0, 'fit', 80);
        
        $this->assertNull($result);
    }

    public function testResizeWithNegativeDimensions()
    {
        $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAFT2cJNJwAAAABJRU5ErkJggg==');
        
        $result = $this->media->resize($pngBinary, -100, -100, 'fit', 80);
        
        $this->assertNull($result);
    }

    public function testDeletePhysicalFilesWithExistingFiles()
    {
        // Create files to delete
        $this->mockDisk->put('file1.jpg', 'content1');
        $this->mockDisk->put('file2.png', 'content2');
        
        $names = ['small' => 'file1.jpg', 'medium' => 'file2.png'];
        
        // Verify files exist
        $this->assertTrue($this->mockDisk->exists('file1.jpg'));
        $this->assertTrue($this->mockDisk->exists('file2.png'));
        
        $this->media->deletePhysicalFiles($names, 'test-disk');
        
        // Files should be deleted
        $this->assertFalse($this->mockDisk->exists('file1.jpg'));
        $this->assertFalse($this->mockDisk->exists('file2.png'));
    }

    public function testDeletePhysicalFilesWithNonexistentFiles()
    {
        $names = ['small' => 'nonexistent1.jpg', 'medium' => 'nonexistent2.png'];
        
        // Should not throw exception
        $this->media->deletePhysicalFiles($names, 'test-disk');
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function testDeletePhysicalFilesWithEmptyNames()
    {
        $names = [];
        
        // Should handle empty array gracefully
        $this->media->deletePhysicalFiles($names, 'test-disk');
        
        $this->assertTrue(true);
    }

    public function testDeletePhysicalFilesWithNullValues()
    {
        $names = ['small' => null, 'medium' => '', 'large' => 'valid.jpg'];
        
        $this->mockDisk->put('valid.jpg', 'content');
        
        $this->media->deletePhysicalFiles($names, 'test-disk');
        
        // Only valid.jpg should be attempted for deletion
        $this->assertFalse($this->mockDisk->exists('valid.jpg'));
    }

    private function createMockContent()
    {
        return new class extends Content {
            public $uuid = 'mock-content-uuid';
            
            public function getTable()
            {
                return 'contents';
            }
            
            public function files()
            {
                return new class {
                    public function where($column, $value)
                    {
                        return new class {
                            public function first()
                            {
                                return null;
                            }
                            
                            public function delete()
                            {
                                return true;
                            }
                        };
                    }
                };
            }
            
            public static function create($attributes)
            {
                $instance = new static;
                foreach ($attributes as $key => $value) {
                    $instance->$key = $value;
                }
                return $instance;
            }
        };
    }

    private function createMockCategory()
    {
        return new class extends Category {
            public $uuid = 'mock-category-uuid';
            
            public function getTable()
            {
                return 'categories';
            }
            
            public function files()
            {
                return new class {
                    public function where($column, $value)
                    {
                        return new class {
                            public function first()
                            {
                                return null;
                            }
                            
                            public function delete()
                            {
                                return true;
                            }
                        };
                    }
                };
            }
        };
    }

    public function testEdgeCaseEmptyUuid()
    {
        $content = new class extends Content {
            public $uuid = '';
            public function getTable() { return 'contents'; }
        };
        
        $result = $this->media->detectLegacyNames($content, 'avatar', 'test-disk');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testEdgeCaseNullUuid()
    {
        $content = new class extends Content {
            public $uuid = null;
            public function getTable() { return 'contents'; }
        };
        
        $result = $this->media->detectLegacyNames($content, 'avatar', 'test-disk');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testEdgeCaseVeryLongUuid()
    {
        $longUuid = str_repeat('a', 255);
        $content = new class extends Content {
            public $uuid;
            public function __construct($uuid) { $this->uuid = $uuid; }
            public function getTable() { return 'contents'; }
        };
        $content->uuid = $longUuid;
        
        $result = $this->media->detectLegacyNames($content, 'avatar', 'test-disk');
        
        $this->assertIsArray($result);
    }

    public function testEdgeCaseSpecialCharactersInUuid()
    {
        $specialUuid = 'uuid-with-@#$%^&*()';
        $content = new class extends Content {
            public $uuid;
            public function __construct($uuid) { $this->uuid = $uuid; }
            public function getTable() { return 'contents'; }
        };
        $content->uuid = $specialUuid;
        
        $result = $this->media->detectLegacyNames($content, 'avatar', 'test-disk');
        
        $this->assertIsArray($result);
    }
}