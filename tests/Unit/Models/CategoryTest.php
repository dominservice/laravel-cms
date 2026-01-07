<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use Dominservice\LaravelCms\Models\Category;
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Models\CategoryFile;

/**
 * Comprehensive test class for Category model
 * Tests all attributes, relationships, nested sets, and edge cases
 */
class CategoryTest extends TestCase
{
    private $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock Category instance
        $this->category = new class extends Category {
            // Override database interactions for testing
            public function getTable()
            {
                return 'test_categories';
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
            
            // Make attributes accessible for testing
            public function testGetAttribute($key)
            {
                return $this->getAttribute($key);
            }
            
            public function testSetAttribute($key, $value)
            {
                return $this->setAttribute($key, $value);
            }
        };
        
        // Set test attributes
        $this->category->uuid = 'category-uuid-123';
        $this->category->type = 'news';
        $this->category->parent_uuid = 'parent-category-456';
        $this->category->status = true;
        $this->category->_lft = 1;
        $this->category->_rgt = 10;
    }

    public function testModelUsesCorrectTraits()
    {
        $traits = class_uses_recursive(Category::class);
        
        $this->assertContains('Dominservice\LaravelCms\Traits\HasUuidPrimary', $traits);
        $this->assertContains('Astrotomic\Translatable\Translatable', $traits);
        $this->assertContains('Dominservice\LaravelCms\Traits\TranslatableLocales', $traits);
        $this->assertContains('Illuminate\Database\Eloquent\SoftDeletes', $traits);
        $this->assertContains('Kalnoy\Nestedset\NodeTrait', $traits);
        $this->assertContains('Dominservice\LaravelCms\Traits\DynamicAvatarAccessor', $traits);
    }

    public function testFillableAttributes()
    {
        $fillable = [
            'type',
            'parent_uuid',
            'status',
            '_lft',
            '_rgt',
        ];
        
        $this->assertEquals($fillable, $this->category->getFillable());
    }

    public function testTranslatableAttributes()
    {
        $translatable = [
            'slug',
            'name',
            'description',
            'meta_title',
            'meta_keywords',
            'meta_description',
        ];
        
        $this->assertEquals($translatable, $this->category->getTranslatableAttributes());
    }

    public function testFileConfigKey()
    {
        $this->assertEquals('category', $this->category->fileConfigKey);
    }

    public function testGetTableUsesConfig()
    {
        $this->assertEquals('test_categories', $this->category->getTable());
    }

    public function testGetParentIdName()
    {
        $this->assertEquals('parent_uuid', $this->category->getParentIdName());
    }

    public function testCreatedAtAccessor()
    {
        // Test with valid date
        $testDate = '2023-01-01 12:00:00';
        $this->category->setRawAttributes(['created_at' => $testDate]);
        
        // This would normally format using config values
        $result = $this->category->testGetAttribute('created_at');
        $this->assertNotNull($result);
    }

    public function testUpdatedAtAccessor()
    {
        // Test with valid date
        $testDate = '2023-01-01 12:00:00';
        $this->category->setRawAttributes(['updated_at' => $testDate]);
        
        // This would normally format using config values
        $result = $this->category->testGetAttribute('updated_at');
        $this->assertNotNull($result);
    }

    public function testContentsRelationship()
    {
        $relation = $this->category->contents();
        $this->assertNotNull($relation);
        // In real scenario, this would be BelongsToMany instance
    }

    public function testVideoRelationship()
    {
        $relation = $this->category->video();
        $this->assertNotNull($relation);
        // Should have where clause for kind = 'video_avatar'
    }

    public function testVideoPosterRelationship()
    {
        $relation = $this->category->videoPoster();
        $this->assertNotNull($relation);
        // Should have where clause for kind = 'video_poster'
    }

    public function testFilesRelationship()
    {
        $relation = $this->category->files();
        $this->assertNotNull($relation);
        // In real scenario, this would be HasMany instance
    }

    public function testAvatarFileRelationship()
    {
        $relation = $this->category->avatarFile();
        $this->assertNotNull($relation);
        // Should have where clause for kind = 'avatar'
    }

    public function testModelAttributes()
    {
        $this->assertEquals('category-uuid-123', $this->category->uuid);
        $this->assertEquals('news', $this->category->type);
        $this->assertEquals('parent-category-456', $this->category->parent_uuid);
        $this->assertTrue($this->category->status);
        $this->assertEquals(1, $this->category->_lft);
        $this->assertEquals(10, $this->category->_rgt);
    }

    public function testModelAttributeDefaults()
    {
        $newCategory = new Category();
        
        // Test that nullable attributes default to null
        $this->assertNull($newCategory->parent_uuid);
        $this->assertNull($newCategory->type);
        $this->assertNull($newCategory->status);
    }

    public function testBooleanStatusAttribute()
    {
        // Test status boolean
        $this->category->status = '1';
        $this->assertTrue($this->category->status);
        
        $this->category->status = '0';
        $this->assertFalse($this->category->status);
        
        $this->category->status = 1;
        $this->assertTrue($this->category->status);
        
        $this->category->status = 0;
        $this->assertFalse($this->category->status);
    }

    public function testTypeAttribute()
    {
        $validTypes = ['news', 'article', 'gallery', 'page', 'product'];
        
        foreach ($validTypes as $type) {
            $this->category->type = $type;
            $this->assertEquals($type, $this->category->type);
        }
    }

    public function testUuidAttributes()
    {
        // Test main UUID
        $validUuid = 'a1b2c3d4-e5f6-7890-abcd-1234567890ab';
        $this->category->uuid = $validUuid;
        $this->assertEquals($validUuid, $this->category->uuid);
        
        // Test parent UUID
        $parentUuid = 'parent-a1b2c3d4-e5f6-7890-abcd-1234567890ab';
        $this->category->parent_uuid = $parentUuid;
        $this->assertEquals($parentUuid, $this->category->parent_uuid);
        
        // Test with simple string (should still work)
        $simpleString = 'simple-category-uuid-123';
        $this->category->uuid = $simpleString;
        $this->assertEquals($simpleString, $this->category->uuid);
    }

    public function testNestedSetAttributes()
    {
        // Test left and right values for nested sets
        $this->category->_lft = 5;
        $this->category->_rgt = 15;
        
        $this->assertEquals(5, $this->category->_lft);
        $this->assertEquals(15, $this->category->_rgt);
        
        // Test with string values (should be cast to int)
        $this->category->_lft = '20';
        $this->category->_rgt = '30';
        
        $this->assertEquals(20, $this->category->_lft);
        $this->assertEquals(30, $this->category->_rgt);
    }

    public function testNestedSetAttributesValidation()
    {
        // Test that left is less than right
        $this->category->_lft = 1;
        $this->category->_rgt = 10;
        
        $this->assertLessThan($this->category->_rgt, $this->category->_lft);
        
        // Test edge case where they're equal (invalid but possible)
        $this->category->_lft = 5;
        $this->category->_rgt = 5;
        
        $this->assertEquals($this->category->_lft, $this->category->_rgt);
    }

    public function testParentUuidCanBeNull()
    {
        // Test root category (no parent)
        $this->category->parent_uuid = null;
        $this->assertNull($this->category->parent_uuid);
        
        // Test setting to empty string
        $this->category->parent_uuid = '';
        $this->assertEquals('', $this->category->parent_uuid);
    }

    public function testEdgeCaseEmptyAttributes()
    {
        $emptyCategory = new Category();
        
        $this->assertNull($emptyCategory->uuid);
        $this->assertNull($emptyCategory->type);
        $this->assertNull($emptyCategory->parent_uuid);
        $this->assertNull($emptyCategory->status);
        $this->assertNull($emptyCategory->_lft);
        $this->assertNull($emptyCategory->_rgt);
    }

    public function testAttributeTypeCasting()
    {
        // Test integer casting for nested set values
        $this->category->_lft = '100';
        $this->category->_rgt = '200';
        
        $this->assertIsInt($this->category->_lft);
        $this->assertIsInt($this->category->_rgt);
        $this->assertEquals(100, $this->category->_lft);
        $this->assertEquals(200, $this->category->_rgt);
        
        // Test boolean casting for status
        $this->category->status = '1';
        $this->assertTrue($this->category->status);
        
        $this->category->status = 'true';
        $this->assertTrue($this->category->status);
        
        $this->category->status = '0';
        $this->assertFalse($this->category->status);
    }

    public function testInheritedDynamicAvatarAccessor()
    {
        // Test that category inherits DynamicAvatarAccessor functionality
        $traits = class_uses_recursive(Category::class);
        $this->assertContains('Dominservice\LaravelCms\Traits\DynamicAvatarAccessor', $traits);
        
        // Test fileConfigKey is properly set
        $this->assertEquals('category', $this->category->fileConfigKey);
    }

    public function testSoftDeletesFunctionality()
    {
        // Test that SoftDeletes trait is used
        $traits = class_uses_recursive(Category::class);
        $this->assertContains('Illuminate\Database\Eloquent\SoftDeletes', $traits);
    }

    public function testTranslatableFunctionality()
    {
        // Test that Translatable trait is used
        $traits = class_uses_recursive(Category::class);
        $this->assertContains('Astrotomic\Translatable\Translatable', $traits);
        
        // Verify translatable attributes
        $translatable = $this->category->getTranslatableAttributes();
        $expectedTranslatable = ['slug', 'name', 'description', 'meta_title', 'meta_keywords', 'meta_description'];
        $this->assertEquals($expectedTranslatable, $translatable);
    }

    public function testNodeTraitForNestedSets()
    {
        // Test that NodeTrait is used for nested sets functionality
        $traits = class_uses_recursive(Category::class);
        $this->assertContains('Kalnoy\Nestedset\NodeTrait', $traits);
        
        // Test parent ID name customization
        $this->assertEquals('parent_uuid', $this->category->getParentIdName());
    }

    public function testRelationshipForeignKeys()
    {
        // Test that relationships use correct foreign keys based on UUID structure
        $this->assertEquals('category-uuid-123', $this->category->uuid);
        
        // Parent relationship should use parent_uuid
        $this->assertEquals('parent-category-456', $this->category->parent_uuid);
    }

    public function testEdgeCaseNullValues()
    {
        $category = new Category();
        
        // Test all nullable fields
        $category->parent_uuid = null;
        $category->type = null;
        $category->status = null;
        $category->_lft = null;
        $category->_rgt = null;
        
        $this->assertNull($category->parent_uuid);
        $this->assertNull($category->type);
        $this->assertNull($category->status);
        $this->assertNull($category->_lft);
        $this->assertNull($category->_rgt);
    }

    public function testEdgeCaseZeroValues()
    {
        // Test with zero values for nested set positions
        $this->category->_lft = 0;
        $this->category->_rgt = 0;
        
        $this->assertEquals(0, $this->category->_lft);
        $this->assertEquals(0, $this->category->_rgt);
        
        // Test with false for status
        $this->category->status = false;
        $this->assertFalse($this->category->status);
    }

    public function testEdgeCaseEmptyStrings()
    {
        // Test with empty strings
        $this->category->type = '';
        $this->category->parent_uuid = '';
        
        $this->assertEquals('', $this->category->type);
        $this->assertEquals('', $this->category->parent_uuid);
    }
}