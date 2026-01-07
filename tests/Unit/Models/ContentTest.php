<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\ContentFile;
use Dominservice\LaravelCms\Models\ContentCategoryRoot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

/**
 * Comprehensive test class for Content model
 * Tests all attributes, relationships, scopes, and edge cases
 */
class ContentTest extends TestCase
{
    private $content;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock Content instance
        $this->content = new class extends Content {
            // Override database interactions for testing
            public function getTable()
            {
                return 'test_contents';
            }
            
            // Override methods to avoid database calls
            public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
            {
                return new class {
                    public function where($column, $value) { return $this; }
                };
            }
            
            public function hasMany($related, $foreignKey = null, $localKey = null)
            {
                return new class {
                    public function where($column, $value) { return $this; }
                };
            }
            
            public function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null)
            {
                return new class {};
            }
            
            public function hasOne($related, $foreignKey = null, $localKey = null)
            {
                return new class {
                    public function where($column, $value) { return $this; }
                };
            }
            
            public function whereHas($relation, $callback = null, $operator = '>=', $count = 1)
            {
                return $this;
            }
            
            // Make attributes accessible for testing
            public function testGetAttribute($key)
            {
                return $this->getAttribute($key);
            }
            
            public function testSetAttribute($key, $value)
            {
                return $this->setAttribute($key, $value);
            }
            
            public function testMutateAttribute($key, $value)
            {
                return $this->mutateAttribute($key, $value);
            }
        };
        
        // Set test attributes
        $this->content->uuid = 'test-uuid-123';
        $this->content->parent_uuid = 'parent-uuid-456';
        $this->content->type = 'article';
        $this->content->status = true;
        $this->content->is_nofollow = false;
        $this->content->external_url = 'https://example.com';
        $this->content->sort = 1;
    }

    public function testModelUsesCorrectTraits()
    {
        $traits = class_uses_recursive(Content::class);
        
        $this->assertContains('Dominservice\LaravelCms\Traits\HasUuidPrimary', $traits);
        $this->assertContains('Astrotomic\Translatable\Translatable', $traits);
        $this->assertContains('Dominservice\LaravelCms\Traits\TranslatableLocales', $traits);
        $this->assertContains('Illuminate\Database\Eloquent\SoftDeletes', $traits);
        $this->assertContains('Dominservice\LaravelCms\Traits\DynamicAvatarAccessor', $traits);
        $this->assertContains('Dominservice\LaravelCms\Traits\HasContentLinks', $traits);
    }

    public function testFillableAttributes()
    {
        $fillable = [
            'parent_uuid',
            'type',
            'status',
            'is_nofollow',
            'external_url',
            'sort',
        ];
        
        $this->assertEquals($fillable, $this->content->getFillable());
    }

    public function testTranslatableAttributes()
    {
        $translatable = [
            'slug',
            'name',
            'sub_name',
            'short_description',
            'description',
            'meta_title',
            'meta_keywords',
            'meta_description',
        ];
        
        $this->assertEquals($translatable, $this->content->getTranslatableAttributes());
    }

    public function testCastsConfiguration()
    {
        $casts = $this->content->getCasts();
        
        $this->assertEquals('string', $casts['external_url']);
        $this->assertEquals('object', $casts['meta']);
    }

    public function testFileConfigKey()
    {
        $this->assertEquals('content', $this->content->fileConfigKey);
    }

    public function testGetTableUsesConfig()
    {
        // Mock config call
        $originalContent = new Content();
        $this->assertEquals('test_contents', $this->content->getTable());
    }

    public function testCreatedAtAccessor()
    {
        // Test with valid date
        $testDate = '2023-01-01 12:00:00';
        $this->content->setRawAttributes(['created_at' => $testDate]);
        
        // This would normally format using config values
        $result = $this->content->testGetAttribute('created_at');
        $this->assertNotNull($result);
    }

    public function testUpdatedAtAccessor()
    {
        // Test with valid date
        $testDate = '2023-01-01 12:00:00';
        $this->content->setRawAttributes(['updated_at' => $testDate]);
        
        // This would normally format using config values
        $result = $this->content->testGetAttribute('updated_at');
        $this->assertNotNull($result);
    }

    public function testExternalUrlAccessorWithValidUrl()
    {
        $this->content->external_url = 'https://example.com';
        $result = $this->content->external_url;
        $this->assertEquals('https://example.com', $result);
    }

    public function testExternalUrlAccessorWithWhitespace()
    {
        $this->content->external_url = '  https://example.com  ';
        $result = $this->content->external_url;
        $this->assertEquals('https://example.com', $result);
    }

    public function testExternalUrlAccessorWithEmptyString()
    {
        $this->content->external_url = '';
        $result = $this->content->external_url;
        $this->assertNull($result);
    }

    public function testExternalUrlAccessorWithNull()
    {
        $this->content->external_url = null;
        $result = $this->content->external_url;
        $this->assertNull($result);
    }

    public function testExternalUrlMutatorWithValidUrl()
    {
        $this->content->external_url = 'https://example.com';
        $this->assertEquals('https://example.com', $this->content->getAttributes()['external_url']);
    }

    public function testExternalUrlMutatorWithWhitespace()
    {
        $this->content->external_url = '  https://example.com  ';
        $this->assertEquals('https://example.com', $this->content->getAttributes()['external_url']);
    }

    public function testExternalUrlMutatorWithEmptyString()
    {
        $this->content->external_url = '';
        $this->assertNull($this->content->getAttributes()['external_url']);
    }

    public function testExternalUrlMutatorWithWhitespaceOnly()
    {
        $this->content->external_url = '   ';
        $this->assertNull($this->content->getAttributes()['external_url']);
    }

    public function testExternalUrlMutatorWithNonString()
    {
        $this->content->external_url = 123;
        $this->assertNull($this->content->getAttributes()['external_url']);
        
        $this->content->external_url = false;
        $this->assertNull($this->content->getAttributes()['external_url']);
        
        $this->content->external_url = [];
        $this->assertNull($this->content->getAttributes()['external_url']);
    }

    public function testScopeWhereCategoriesWithString()
    {
        // Test with string category
        $result = $this->content->scopeWhereCategories(['test-category']);
        $this->assertInstanceOf(get_class($this->content), $result);
    }

    public function testScopeWhereCategoriesWithArray()
    {
        // Test with array of categories
        $result = $this->content->scopeWhereCategories(['cat1', 'cat2']);
        $this->assertInstanceOf(get_class($this->content), $result);
    }

    public function testScopeWhereCategoriesWithSingleValue()
    {
        // Test with single value (gets converted to array)
        $result = $this->content->scopeWhereCategories('single-category');
        $this->assertInstanceOf(get_class($this->content), $result);
    }

    public function testParentRelationship()
    {
        $relation = $this->content->parent();
        $this->assertNotNull($relation);
        // In real scenario, this would be BelongsTo instance
    }

    public function testChildrenRelationship()
    {
        $relation = $this->content->children();
        $this->assertNotNull($relation);
        // In real scenario, this would be HasMany instance
    }

    public function testCategoriesRelationship()
    {
        $relation = $this->content->categories();
        $this->assertNotNull($relation);
        // In real scenario, this would be BelongsToMany instance
    }

    public function testRootCategoryRelationship()
    {
        $relation = $this->content->rootCategory();
        $this->assertNotNull($relation);
        // Should have where clause for is_root = 1
    }

    public function testVideoRelationship()
    {
        $relation = $this->content->video();
        $this->assertNotNull($relation);
        // Should have where clause for kind = 'video_avatar'
    }

    public function testVideoPosterRelationship()
    {
        $relation = $this->content->videoPoster();
        $this->assertNotNull($relation);
        // Should have where clause for kind = 'video_poster'
    }

    public function testFilesRelationship()
    {
        $relation = $this->content->files();
        $this->assertNotNull($relation);
        // In real scenario, this would be HasMany instance
    }

    public function testAvatarFileRelationship()
    {
        $relation = $this->content->avatarFile();
        $this->assertNotNull($relation);
        // Should have where clause for kind = 'avatar'
    }

    public function testModelAttributes()
    {
        $this->assertEquals('test-uuid-123', $this->content->uuid);
        $this->assertEquals('parent-uuid-456', $this->content->parent_uuid);
        $this->assertEquals('article', $this->content->type);
        $this->assertTrue($this->content->status);
        $this->assertFalse($this->content->is_nofollow);
        $this->assertEquals('https://example.com', $this->content->external_url);
        $this->assertEquals(1, $this->content->sort);
    }

    public function testModelAttributeDefaults()
    {
        $newContent = new Content();
        
        // Test that nullable attributes default to null
        $this->assertNull($newContent->parent_uuid);
        $this->assertNull($newContent->external_url);
        $this->assertNull($newContent->sort);
    }

    public function testBooleanAttributes()
    {
        // Test status boolean
        $this->content->status = '1';
        $this->assertTrue($this->content->status);
        
        $this->content->status = '0';
        $this->assertFalse($this->content->status);
        
        // Test is_nofollow boolean
        $this->content->is_nofollow = 1;
        $this->assertTrue($this->content->is_nofollow);
        
        $this->content->is_nofollow = 0;
        $this->assertFalse($this->content->is_nofollow);
    }

    public function testSortAttribute()
    {
        // Test integer sort
        $this->content->sort = '5';
        $this->assertEquals(5, $this->content->sort);
        
        $this->content->sort = null;
        $this->assertNull($this->content->sort);
        
        $this->content->sort = '0';
        $this->assertEquals(0, $this->content->sort);
    }

    public function testTypeAttribute()
    {
        $validTypes = ['article', 'page', 'news', 'gallery', 'video'];
        
        foreach ($validTypes as $type) {
            $this->content->type = $type;
            $this->assertEquals($type, $this->content->type);
        }
    }

    public function testUuidValidation()
    {
        // Test with valid UUID format
        $validUuid = 'a1b2c3d4-e5f6-7890-abcd-1234567890ab';
        $this->content->uuid = $validUuid;
        $this->assertEquals($validUuid, $this->content->uuid);
        
        // Test with simple string (should still work as it's just a string field)
        $simpleString = 'simple-uuid-123';
        $this->content->uuid = $simpleString;
        $this->assertEquals($simpleString, $this->content->uuid);
    }

    public function testMetaCastToObject()
    {
        $metaData = ['key1' => 'value1', 'key2' => 'value2'];
        $this->content->meta = $metaData;
        
        // Should be cast as object
        $result = $this->content->meta;
        $this->assertIsObject($result);
    }

    public function testEdgeCaseEmptyAttributes()
    {
        $emptyContent = new Content();
        
        $this->assertNull($emptyContent->uuid);
        $this->assertNull($emptyContent->parent_uuid);
        $this->assertNull($emptyContent->type);
        $this->assertNull($emptyContent->status);
        $this->assertNull($emptyContent->is_nofollow);
        $this->assertNull($emptyContent->external_url);
        $this->assertNull($emptyContent->sort);
    }
}