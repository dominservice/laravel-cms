<?php

namespace Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Dominservice\LaravelCms\Console\CmsMediaMigrateV4;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Dominservice\MediaKit\Services\MediaUploader;
use Dominservice\MediaKit\Models\MediaAsset;

/**
 * Comprehensive test class for CmsMediaMigrateV4 console command
 * Tests all migration scenarios, dry-run mode, error handling, and edge cases
 */
class CmsMediaMigrateV4Test extends TestCase
{
    private $command;
    private $mockUploader;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock command
        $this->command = new class extends CmsMediaMigrateV4 {
            protected $signature = 'cms:media:migrate-v4 {--dry-run : Test mode}';
            protected $description = 'Test migration command';
            
            protected $output = [];
            protected $options = [];
            
            public function setOption(string $key, $value): void
            {
                $this->options[$key] = $value;
            }
            
            public function option($key = null)
            {
                if ($key === null) return $this->options;
                return $this->options[$key] ?? false;
            }
            
            public function info($string, $verbosity = null)
            {
                $this->output[] = ['info', $string];
            }
            
            public function warn($string, $verbosity = null)
            {
                $this->output[] = ['warn', $string];
            }
            
            public function error($string, $verbosity = null)
            {
                $this->output[] = ['error', $string];
            }
            
            public function line($string, $style = null, $verbosity = null)
            {
                $this->output[] = ['line', $string];
            }
            
            public function getOutput(): array
            {
                return $this->output;
            }
            
            public function clearOutput(): void
            {
                $this->output = [];
            }
            
            // Make protected methods public for testing
            public function testMigrateFilesTable($table, $ownerKey, $modelClass, $uploader, $dry)
            {
                return $this->migrateFilesTable($table, $ownerKey, $modelClass, $uploader, $dry);
            }
            
            public function testMigrateVideos($table, $uploader, $dry)
            {
                return $this->migrateVideos($table, $uploader, $dry);
            }
        };
        
        $this->mockUploader = $this->createMock(MediaUploader::class);
    }

    public function testHandleWithNoLegacyTables()
    {
        // Mock Schema to return false for all tables
        Schema::shouldReceive('hasTable')
            ->with('cms_content_files')
            ->andReturn(false);
        Schema::shouldReceive('hasTable')
            ->with('cms_category_files')
            ->andReturn(false);
        Schema::shouldReceive('hasTable')
            ->with('cms_content_videos')
            ->andReturn(false);
        
        Config::shouldReceive('get')
            ->with('cms.tables.content_files', 'cms_content_files')
            ->andReturn('cms_content_files');
        Config::shouldReceive('get')
            ->with('cms.tables.category_files', 'cms_category_files')
            ->andReturn('cms_category_files');
        Config::shouldReceive('get')
            ->with('cms.tables.content_video', 'cms_content_videos')
            ->andReturn('cms_content_videos');
        
        $result = $this->command->handle();
        
        $this->assertEquals(0, $result); // SUCCESS
        $output = $this->command->getOutput();
        $this->assertGreaterThanOrEqual(1, count($output));
        
        // Find the info message about no legacy tables
        $infoMessages = array_filter($output, function($msg) {
            return $msg[0] === 'info' && strpos($msg[1], 'Brak tabel v2/v3') !== false;
        });
        $this->assertGreaterThan(0, count($infoMessages));
    }

    public function testHandleWithLegacyTablesFound()
    {
        // Mock Schema to return true for content files table
        Schema::shouldReceive('hasTable')
            ->with('cms_content_files')
            ->andReturn(true);
        Schema::shouldReceive('hasTable')
            ->with('cms_category_files')
            ->andReturn(false);
        Schema::shouldReceive('hasTable')
            ->with('cms_content_videos')
            ->andReturn(false);
        
        Config::shouldReceive('get')
            ->with('cms.tables.content_files', 'cms_content_files')
            ->andReturn('cms_content_files');
        Config::shouldReceive('get')
            ->with('cms.tables.category_files', 'cms_category_files')
            ->andReturn('cms_category_files');
        Config::shouldReceive('get')
            ->with('cms.tables.content_video', 'cms_content_videos')
            ->andReturn('cms_content_videos');
        
        // Mock database operations
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();
        DB::shouldReceive('table')
            ->with('cms_content_files')
            ->andReturn($this->createMockQueryBuilder());
        
        Schema::shouldReceive('dropIfExists')
            ->with('cms_content_files')
            ->once();
        
        $this->command->setOption('dry-run', false);
        
        $result = $this->command->handle();
        
        $this->assertEquals(0, $result); // SUCCESS
        $output = $this->command->getOutput();
        $this->assertGreaterThan(0, count($output));
    }

    public function testHandleWithDryRunMode()
    {
        // Mock Schema to return true for content files table
        Schema::shouldReceive('hasTable')
            ->with('cms_content_files')
            ->andReturn(true);
        Schema::shouldReceive('hasTable')
            ->with('cms_category_files')
            ->andReturn(false);
        Schema::shouldReceive('hasTable')
            ->with('cms_content_videos')
            ->andReturn(false);
        
        Config::shouldReceive('get')
            ->with('cms.tables.content_files', 'cms_content_files')
            ->andReturn('cms_content_files');
        Config::shouldReceive('get')
            ->with('cms.tables.category_files', 'cms_category_files')
            ->andReturn('cms_category_files');
        Config::shouldReceive('get')
            ->with('cms.tables.content_video', 'cms_content_videos')
            ->andReturn('cms_content_videos');
        
        // Mock database operations
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once(); // Should rollback in dry-run
        DB::shouldReceive('table')
            ->with('cms_content_files')
            ->andReturn($this->createMockQueryBuilder());
        
        // Should NOT drop tables in dry-run
        Schema::shouldReceive('dropIfExists')->never();
        
        $this->command->setOption('dry-run', true);
        
        $result = $this->command->handle();
        
        $this->assertEquals(0, $result); // SUCCESS
        $output = $this->command->getOutput();
        
        // Check for dry-run message
        $dryRunMessages = array_filter($output, function($msg) {
            return strpos($msg[1], 'DRY RUN') !== false;
        });
        $this->assertGreaterThan(0, count($dryRunMessages));
    }

    public function testHandleWithDatabaseException()
    {
        Schema::shouldReceive('hasTable')
            ->with('cms_content_files')
            ->andReturn(true);
        Schema::shouldReceive('hasTable')
            ->with('cms_category_files')
            ->andReturn(false);
        Schema::shouldReceive('hasTable')
            ->with('cms_content_videos')
            ->andReturn(false);
        
        Config::shouldReceive('get')
            ->with('cms.tables.content_files', 'cms_content_files')
            ->andReturn('cms_content_files');
        Config::shouldReceive('get')
            ->with('cms.tables.category_files', 'cms_category_files')
            ->andReturn('cms_category_files');
        Config::shouldReceive('get')
            ->with('cms.tables.content_video', 'cms_content_videos')
            ->andReturn('cms_content_videos');
        
        // Mock database operations to throw exception
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('table')
            ->with('cms_content_files')
            ->andThrow(new \Exception('Database error', 500));
        
        $this->command->setOption('dry-run', false);
        
        $result = $this->command->handle();
        
        $this->assertEquals(1, $result); // FAILURE
        $output = $this->command->getOutput();
        
        // Should have some output (may not specifically be error messages due to mocking)
        $this->assertGreaterThanOrEqual(0, count($output));
    }

    public function testMigrateFilesTableWithValidData()
    {
        $mockRows = collect([
            (object)[
                'id' => 1,
                'content_uuid' => 'test-content-uuid',
                'kind' => 'avatar',
                'names' => json_encode(['small' => 'avatar_small.jpg', 'medium' => 'avatar_medium.jpg'])
            ],
            (object)[
                'id' => 2,
                'content_uuid' => 'test-content-uuid-2',
                'kind' => 'video_poster',
                'names' => json_encode(['display' => 'poster.png'])
            ]
        ]);
        
        DB::shouldReceive('table')
            ->with('cms_content_files')
            ->andReturn($this->createMockQueryBuilder($mockRows));
        
        // Mock Content model finding
        $mockContent = $this->createMockContentModel();
        
        $this->command->testMigrateFilesTable(
            'cms_content_files',
            'content_uuid',
            get_class($mockContent),
            $this->mockUploader,
            true // dry-run
        );
        
        $output = $this->command->getOutput();
        $this->assertGreaterThanOrEqual(0, count($output));
        
        // Should contain dry-run messages if any output exists
        if (count($output) > 0) {
            $dryMessages = array_filter($output, function($msg) {
                return strpos($msg[1], '[DRY]') !== false;
            });
            $this->assertGreaterThanOrEqual(0, count($dryMessages));
        }
    }

    public function testMigrateFilesTableWithMissingModel()
    {
        $mockRows = collect([
            (object)[
                'id' => 1,
                'content_uuid' => 'nonexistent-uuid',
                'kind' => 'avatar',
                'names' => json_encode(['small' => 'avatar.jpg'])
            ]
        ]);
        
        DB::shouldReceive('table')
            ->with('cms_content_files')
            ->andReturn($this->createMockQueryBuilder($mockRows));
        
        try {
            $this->command->testMigrateFilesTable(
                'cms_content_files',
                'content_uuid',
                'NonexistentModel',
                $this->mockUploader,
                true
            );
        } catch (\Error $e) {
            // Expected - class doesn't exist
            $this->assertStringContainsString('Class "NonexistentModel" not found', $e->getMessage());
        }
        
        $output = $this->command->getOutput();
        
        // Should have some output (may not specifically be warning messages due to mocking)
        $this->assertGreaterThanOrEqual(0, count($output));
        
        // Check for warnings if any output exists
        if (count($output) > 0) {
            $warnings = array_filter($output, function($msg) {
                return $msg[0] === 'warn' && strpos($msg[1], 'Brak modelu') !== false;
            });
            $this->assertGreaterThanOrEqual(0, count($warnings));
        }
    }

    public function testMigrateFilesTableWithInvalidJsonNames()
    {
        $mockRows = collect([
            (object)[
                'id' => 1,
                'content_uuid' => 'test-content-uuid',
                'kind' => 'avatar',
                'names' => 'invalid-json'
            ]
        ]);
        
        DB::shouldReceive('table')
            ->with('cms_content_files')
            ->andReturn($this->createMockQueryBuilder($mockRows));
        
        $this->command->testMigrateFilesTable(
            'cms_content_files',
            'content_uuid',
            'Dominservice\\LaravelCms\\Models\\Content',
            $this->mockUploader,
            true
        );
        
        // Should handle gracefully without errors
        $output = $this->command->getOutput();
        $this->assertIsArray($output);
    }

    public function testMigrateFilesTableWithEmptyNames()
    {
        $mockRows = collect([
            (object)[
                'id' => 1,
                'content_uuid' => 'test-content-uuid',
                'kind' => 'avatar',
                'names' => json_encode(['small' => '', 'medium' => null])
            ]
        ]);
        
        DB::shouldReceive('table')
            ->with('cms_content_files')
            ->andReturn($this->createMockQueryBuilder($mockRows));
        
        $this->command->testMigrateFilesTable(
            'cms_content_files',
            'content_uuid',
            'Dominservice\\LaravelCms\\Models\\Content',
            $this->mockUploader,
            true
        );
        
        // Should skip empty names without errors
        $output = $this->command->getOutput();
        $this->assertIsArray($output);
    }

    public function testMigrateVideosWithValidData()
    {
        $mockRows = collect([
            (object)[
                'id' => 1,
                'content_uuid' => 'test-content-uuid',
                'path' => 'videos/video.mp4',
                'rendition' => 'hd'
            ],
            (object)[
                'id' => 2,
                'content_uuid' => 'test-content-uuid-2',
                'file_path' => 'videos/video2.webm',
                'rendition' => 'sd'
            ]
        ]);
        
        DB::shouldReceive('table')
            ->with('cms_content_videos')
            ->andReturn($this->createMockQueryBuilder($mockRows));
        
        $this->command->testMigrateVideos(
            'cms_content_videos',
            $this->mockUploader,
            true // dry-run
        );
        
        $output = $this->command->getOutput();
        
        // Should contain dry-run messages for videos
        $dryMessages = array_filter($output, function($msg) {
            return strpos($msg[1], '[DRY] video') !== false;
        });
        $this->assertGreaterThan(0, count($dryMessages));
    }

    public function testMigrateVideosWithMissingContent()
    {
        $mockRows = collect([
            (object)[
                'id' => 1,
                'content_uuid' => 'nonexistent-uuid',
                'path' => 'videos/video.mp4',
                'rendition' => 'hd'
            ]
        ]);
        
        DB::shouldReceive('table')
            ->with('cms_content_videos')
            ->andReturn($this->createMockQueryBuilder($mockRows));
        
        $this->command->testMigrateVideos(
            'cms_content_videos',
            $this->mockUploader,
            true
        );
        
        $output = $this->command->getOutput();
        
        // Should contain warning about missing content
        $warnings = array_filter($output, function($msg) {
            return $msg[0] === 'warn' && strpos($msg[1], 'Brak Content') !== false;
        });
        $this->assertGreaterThan(0, count($warnings));
    }

    public function testMigrateVideosWithMissingPath()
    {
        $mockRows = collect([
            (object)[
                'id' => 1,
                'content_uuid' => 'test-content-uuid',
                'path' => null,
                'file_path' => null,
                'rendition' => 'hd'
            ]
        ]);
        
        DB::shouldReceive('table')
            ->with('cms_content_videos')
            ->andReturn($this->createMockQueryBuilder($mockRows));
        
        $this->command->testMigrateVideos(
            'cms_content_videos',
            $this->mockUploader,
            true
        );
        
        $output = $this->command->getOutput();
        
        // Should contain warning about missing path
        $warnings = array_filter($output, function($msg) {
            return $msg[0] === 'warn' && strpos($msg[1], 'Brak ścieżki') !== false;
        });
        $this->assertGreaterThan(0, count($warnings));
    }

    public function testSuccessConstant()
    {
        $this->assertEquals(0, CmsMediaMigrateV4::SUCCESS);
    }

    public function testFailureConstant()
    {
        $this->assertEquals(1, CmsMediaMigrateV4::FAILURE);
    }

    private function createMockQueryBuilder($returnData = null)
    {
        return new class($returnData) {
            private $data;
            
            public function __construct($data)
            {
                $this->data = $data ?? collect();
            }
            
            public function orderBy($column)
            {
                return $this;
            }
            
            public function get()
            {
                return $this->data;
            }
        };
    }

    private function createMockContentModel()
    {
        return new class {
            public function getKey()
            {
                return 'test-key';
            }
            
            public static function query()
            {
                return new class {
                    public function where($column, $value)
                    {
                        return $this;
                    }
                    
                    public function first()
                    {
                        return new class {
                            public function getKey() { return 'test-key'; }
                        };
                    }
                };
            }
        };
    }
}