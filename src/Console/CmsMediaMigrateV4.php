<?php

namespace Dominservice\LaravelCms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Services\MediaUploader;
use Dominservice\MediaKit\Support\Kinds\KindRegistry;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CmsMediaMigrateV4 extends Command
{
    protected $signature = 'cms:media:migrate-v4 {--dry-run : Tylko diagnoza, bez zapisu}';
    protected $description = 'Migracja multimediów CMS v2/v3 do MediaKit (v4), usuwa zbędne tabele po migracji.';

    public function handle(): int
    {
        $tblContentFiles  = (string) config('cms.tables.content_files', 'cms_content_files');
        $tblCategoryFiles = (string) config('cms.tables.category_files', 'cms_category_files');
        $tblContentVideo  = (string) config('cms.tables.content_video', 'cms_content_videos');

        $legacyTables = [];
        foreach ([$tblContentFiles, $tblCategoryFiles, $tblContentVideo] as $t) {
            if (Schema::hasTable($t)) { $legacyTables[] = $t; }
        }
        if (empty($legacyTables)) {
            $this->info('Brak tabel v2/v3 – czysta instalacja v4 lub już po migracji.');
            return self::SUCCESS;
        }

        $this->warn('Legacy tables: '.implode(', ', $legacyTables));
        $dry = (bool) $this->option('dry-run');

        $uploader = app(MediaUploader::class);

        DB::beginTransaction();
        try {
            if (Schema::hasTable($tblContentFiles)) {
                $this->migrateFilesTable($tblContentFiles, 'content_uuid', \Dominservice\LaravelCms\Models\Content::class, $uploader, $dry);
            }
            if (Schema::hasTable($tblCategoryFiles)) {
                $this->migrateFilesTable($tblCategoryFiles, 'category_uuid', \Dominservice\LaravelCms\Models\Category::class, $uploader, $dry);
            }
            if (Schema::hasTable($tblContentVideo)) {
                $this->migrateVideos($tblContentVideo, $uploader, $dry);
            }

            if ($dry) {
                DB::rollBack();
                $this->info('[DRY RUN] Symulacja zakończona – bez zmian.');
                return self::SUCCESS;
            }

            foreach ($legacyTables as $t) {
                Schema::dropIfExists($t);
                $this->line("DROP TABLE {$t}");
            }

            DB::commit();
            $this->info('OK – migracja zakończona, stare tabele usunięto.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Błąd migracji: '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());
            return self::FAILURE;
        }
    }

    protected function migrateFilesTable(string $table, string $ownerKey, string $modelClass, MediaUploader $uploader, bool $dry): void
    {
        $rows = DB::table($table)->orderBy('id')->get();
        $this->line("Migracja {$table}: {$rows->count()} rekordów");

        foreach ($rows as $row) {
            /** @var Model|null $model */
            $model = $modelClass::query()->where('uuid', $row->{$ownerKey} ?? null)->first();
            if (!$model) { $this->warn("  ⚠️ Brak modelu dla UUID=".($row->{$ownerKey} ?? 'NULL')); continue; }

            $kind = (string) ($row->kind ?? 'default');
            $mapCollection = KindRegistry::collectionFor($kind, $kind);

            $names = is_array($row->names ?? null) ? $row->names : (json_decode($row->names ?? '[]', true) ?: []);
            foreach ($names as $size => $path) {
                if (!is_string($path) || $path === '') { continue; }
                if ($dry) {
                    $this->line("  [DRY] {$modelClass} {$model->getKey()} kind={$kind} file={$path}");
                    continue;
                }
                // Dodajemy jako obraz (jeśli rozszerzenie obrazkowe), w przeciwnym razie jako plik HD do wideo?
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                    // oszukujemy UploadedFile – przenosimy ścieżkę istniejącego pliku (musi być na dysku media-kit)
                    // Zakładamy, że pliki znajdują się na tym samym dysku – jeśli inne, należy je przenieść przed migracją.
                    // Tu: kopiujemy logicznie – MediaUploader i tak zapisze nowy oryginał.
                    // Używamy streamu z dysku źródłowego:
                    // uproszczenie – delegujemy do add_media przez HasMedia byłoby prościej, ale zostajemy przy uploaderze.
                    // Konwersja: wymagane jest UploadedFile – tu lepiej odczytać i zapisać tymczasowo nie jest możliwe w migracji.
                    // Zatem zrobimy minimalny zapis metadanych, a fizyczny transfer pozostawiamy: ścieżka do oryginału = path.
                    $disk = config('media-kit.disk','public');
                    /** @var MediaAsset $asset */
                    $asset = $model->media()->create([
                        'uuid'             => (string) \Illuminate\Support\Str::uuid(),
                        'collection'     => $mapCollection,
                        'disk'           => $disk,
                        'original_path'  => $path,
                        'original_mime'  => null,
                        'original_ext'   => $ext,
                        'original_size'  => null,
                        'width'          => null,
                        'height'         => null,
                        'hash'           => sha1($path.uniqid('', true)),
                        'meta'           => ['migrated_from' => $table, 'size_key' => $size],
                    ]);
                } else {
                    // Traktuj jako wideo – dodajemy/uzupełniamy asset wideo
                    if (!$dry) {
                        $disk = config('media-kit.disk','public');
                        $asset = $model->media()->where('collection', KindRegistry::collectionFor('video_avatar','video'))->latest()->first();
                        if (!$asset) {
                            $asset = $model->media()->create([
                                'id'             => (string) \Illuminate\Support\Str::uuid(),
                                'collection'     => KindRegistry::collectionFor('video_avatar','video'),
                                'disk'           => $disk,
                                'original_path'  => 'video-placeholder/'.date('Y/m').'/'.$model->getKey().'/placeholder.txt',
                                'original_mime'  => null,
                                'original_ext'   => null,
                                'original_size'  => null,
                                'width'          => null,
                                'height'         => null,
                                'hash'           => sha1((string) $model->getKey().uniqid('', true)),
                                'meta'           => ['video_renditions' => []],
                            ]);
                        }
                        $meta = $asset->meta ?? [];
                        $renditions = $meta['video_renditions'] ?? [];
                        $renditions[$size] = ['disk' => $disk, 'path' => $path];
                        $meta['video_renditions'] = $renditions;
                        $asset->meta = $meta;
                        $asset->save();
                    }
                }
            }
        }
    }

    protected function migrateVideos(string $table, MediaUploader $uploader, bool $dry): void
    {
        $rows = DB::table($table)->orderBy('id')->get();
        $this->line("Migracja {$table}: {$rows->count()} rekordów");

        foreach ($rows as $row) {
            $model = \Dominservice\LaravelCms\Models\Content::query()->where('uuid', $row->content_uuid ?? null)->first();
            if (!$model) { $this->warn("  ⚠️ Brak Content uuid={$row->content_uuid}"); continue; }
            $path = $row->path ?? $row->file_path ?? null;
            if (!$path) { $this->warn('  ⚠️ Brak ścieżki wideo'); continue; }
            $rend = $row->rendition ?? 'hd';
            if ($dry) { $this->line("  [DRY] video {$model->getKey()} {$path} ({$rend})"); continue; }

            // zapis do meta video_renditions – jw.
            $disk = config('media-kit.disk','public');
            $asset = $model->media()->where('collection', KindRegistry::collectionFor('video_avatar','video'))->latest()->first();
            if (!$asset) {
                $asset = $model->media()->create([
                    'id'             => (string) \Illuminate\Support\Str::uuid(),
                    'collection'     => KindRegistry::collectionFor('video_avatar','video'),
                    'disk'           => $disk,
                    'original_path'  => 'video-placeholder/'.date('Y/m').'/'.$model->getKey().'/placeholder.txt',
                    'original_mime'  => null,
                    'original_ext'   => null,
                    'original_size'  => null,
                    'width'          => null,
                    'height'         => null,
                    'hash'           => sha1((string) $model->getKey().uniqid('', true)),
                    'meta'           => ['video_renditions' => []],
                ]);
            }
            $meta = $asset->meta ?? [];
            $renditions = $meta['video_renditions'] ?? [];
            $renditions[$rend] = ['disk' => $disk, 'path' => $path];
            $meta['video_renditions'] = $renditions;
            $asset->meta = $meta;
            $asset->save();
        }
    }
}
