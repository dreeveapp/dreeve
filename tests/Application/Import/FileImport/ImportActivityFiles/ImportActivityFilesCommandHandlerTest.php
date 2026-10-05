<?php

declare(strict_types=1);

namespace App\Tests\Application\Import\FileImport\ImportActivityFiles;

use App\Application\Import\FileImport\ImportActivityFiles\ImportActivityFiles;
use App\Application\Import\FileImport\ImportActivityFiles\ImportActivityFilesCommandHandler;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\ValueObject\String\CompressedString;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\Cache\RenderStub;
use App\Tests\SpyOutput;
use League\Flysystem\FilesystemOperator;

class ImportActivityFilesCommandHandlerTest extends ContainerTestCase
{
    private ImportActivityFilesCommandHandler $handler;
    private FilesystemOperator $watchStorage;
    private CacheableRenderer $cacheableRenderer;

    public function testHandleImportsActivityFile(): void
    {
        $this->watchStorage->write('watch/ride.tcx', $this->fixture('activity.tcx'));

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $fileImports = $this->getConnection()
            ->executeQuery('SELECT * FROM FileImport ORDER BY importedOn ASC')
            ->fetchAllAssociative();
        $this->assertCount(1, $fileImports);
        $fileImport = $fileImports[0];
        $this->assertSame(FileImportStatus::SUCCESS->value, $fileImport['status']);
        $this->assertSame('ride.tcx', $fileImport['originalFilename']);
        $this->assertSame($this->fixture('activity.tcx'), CompressedString::fromCompressed($fileImport['fileContents'])->uncompress());

        $this->assertNotNull($fileImport['activityId']);
        $activityId = ActivityId::fromString($fileImport['activityId']);
        $activity = $this->getContainer()->get(ActivityRepository::class)->find($activityId);

        $this->assertSame(ImportSource::TCX_FILE, $activity->getImportSource());
        $this->assertSame('Night Ride', $activity->getName());
        $this->assertSame('Garmin Edge 530', $activity->getDeviceName());
        $this->assertNotNull($activity->getStartingCoordinate());
        $this->assertNotNull($activity->getEncodedPolyline());

        $this->assertCount(1, $this->getContainer()->get(ActivityLapRepository::class)->findBy($activityId));

        $streams = $this->getContainer()->get(ActivityStreamRepository::class)->findByActivityId($activityId);
        $this->assertNotNull($streams->filterOnType(StreamType::HEART_RATE));
        $this->assertNotNull($streams->filterOnType(StreamType::LAT_LNG));

        $this->assertSame(
            implode("\n", [
                'Importing activity files...',
                '  => [1/1] Imported "ride.tcx" as activity "Night Ride - 08-09-2021"',
                '  => Imported 1, skipped 0, failed 0 activity file(s)',
            ]),
            (string) $output,
        );
    }

    public function testHandleImportsFileWithUppercaseExtension(): void
    {
        $this->watchStorage->write('watch/Ride_2026-07-02.TCX', $this->fixture('activity.tcx'));

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $fileImports = $this->getConnection()
            ->executeQuery('SELECT * FROM FileImport ORDER BY importedOn ASC')
            ->fetchAllAssociative();
        $this->assertCount(1, $fileImports);
        $this->assertSame(FileImportStatus::SUCCESS->value, $fileImports[0]['status']);
        $this->assertFalse(
            $this->watchStorage->fileExists('watch/Ride_2026-07-02.TCX'),
            'File with uppercase extension should be deleted after import',
        );
    }

    public function testHandleSkipsAlreadyImportedFile(): void
    {
        $bytes = $this->fixture('activity.tcx');

        $this->watchStorage->write('watch/ride1.tcx', $bytes);
        $this->watchStorage->write('watch/ride2.tcx', $bytes);
        $this->watchStorage->write('watch/ride3.tcx', $bytes);

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $this->assertCount(3, $this->getConnection()
            ->executeQuery('SELECT * FROM FileImport ORDER BY importedOn ASC')
            ->fetchAllAssociative());
        $this->assertEquals(
            [FileImportStatus::SUCCESS, FileImportStatus::SKIPPED,  FileImportStatus::SKIPPED],
            array_map(
                static fn (array $file): FileImportStatus => FileImportStatus::from($file['status']),
                array_values($this->getConnection()
                    ->executeQuery('SELECT * FROM FileImport ORDER BY importedOn ASC')
                    ->fetchAllAssociative())
            )
        );
        $this->assertSame(
            implode("\n", [
                'Importing activity files...',
                '  => [1/3] Imported "ride1.tcx" as activity "Night Ride - 08-09-2021"',
                '  => [2/3] Skipping "ride2.tcx", activity was already imported',
                '  => [3/3] Skipping "ride3.tcx", activity was already imported',
                '  => Imported 1, skipped 2, failed 0 activity file(s)',
            ]),
            (string) $output,
        );
    }

    public function testHandleImportsGpxFileAndSkipsDuplicate(): void
    {
        $bytes = $this->fixture('activity.gpx');

        $this->watchStorage->write('watch/run1.gpx', $bytes);
        $this->watchStorage->write('watch/run2.gpx', $bytes);

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $fileImports = array_values($this->getConnection()
            ->executeQuery('SELECT * FROM FileImport ORDER BY importedOn ASC')
            ->fetchAllAssociative());
        $this->assertCount(2, $fileImports);
        $this->assertSame(FileImportStatus::SUCCESS->value, $fileImports[0]['status']);
        $this->assertSame(ImportSource::GPX_FILE->value, $fileImports[0]['source']);
        $this->assertSame(FileImportStatus::SKIPPED->value, $fileImports[1]['status']);
        $this->assertSame(ImportSource::GPX_FILE->value, $fileImports[1]['source']);
    }

    public function testHandleSkipsUnsupportedFileType(): void
    {
        $this->watchStorage->write('watch/notes.txt', 'just some text');

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $this->assertCount(0, $this->getConnection()
            ->executeQuery('SELECT * FROM FileImport ORDER BY importedOn ASC')
            ->fetchAllAssociative());
        $this->assertSame(
            implode("\n", [
                'Importing activity files...',
                '  => [1/1] Skipping "notes.txt", unsupported file type',
                '  => Imported 0, skipped 1, failed 0 activity file(s)',
            ]),
            (string) $output,
        );
    }

    public function testHandleRecordsFailureForCorruptFile(): void
    {
        $this->watchStorage->write('watch/broken.tcx', 'this is not valid xml');

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $fileImports = $this->getConnection()
            ->executeQuery('SELECT * FROM FileImport ORDER BY importedOn ASC')
            ->fetchAllAssociative();
        $this->assertCount(1, $fileImports);
        $this->assertSame(FileImportStatus::FAILED->value, $fileImports[0]['status']);
        $this->assertNull($fileImports[0]['activityId']);
        $this->assertSame('this is not valid xml', CompressedString::fromCompressed($fileImports[0]['fileContents'])->uncompress());

        $this->assertSame(
            implode("\n", [
                'Importing activity files...',
                '  => <error>[1/1] Could not import "broken.tcx": "broken.tcx" is not valid TCX XML</error>',
                '  => Imported 0, skipped 0, failed 1 activity file(s)',
            ]),
            (string) $output,
        );
    }

    public function testHandleWithoutImportDirectoryIsNoOp(): void
    {
        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $this->assertCount(0, $this->getConnection()
            ->executeQuery('SELECT * FROM FileImport ORDER BY importedOn ASC')
            ->fetchAllAssociative());
        $this->assertSame(
            implode("\n", [
                'Importing activity files...',
                '  => No "watch" directory found, nothing to import',
            ]),
            (string) $output,
        );
    }

    public function testHandleKeepsRenderedPagesBecauseImportedFilesCarryNoImages(): void
    {
        $cacheability = Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES));
        $render = new RenderStub();
        $this->cacheableRenderer->render($cacheability, $render(...));

        $this->watchStorage->write('watch/ride.tcx', $this->fixture('activity.tcx'));
        $this->handler->handle(new ImportActivityFiles(new SpyOutput()));

        $this->cacheableRenderer->render($cacheability, $render(...));
        $this->assertEquals(1, $render->renderCount);
    }

    private function fixture(string $name): string
    {
        $projectDir = $this->getContainer()->get(KernelProjectDir::class);
        $contents = file_get_contents($projectDir.'/tests/Domain/Import/FileParser/fixtures/'.$name);
        if (false === $contents) {
            self::fail(sprintf('Could not read fixture "%s"', $name));
        }

        return $contents;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->getContainer()->get(ImportActivityFilesCommandHandler::class);
        $this->watchStorage = $this->getContainer()->get('default.storage');
        $this->cacheableRenderer = $this->getContainer()->get(CacheableRenderer::class);
        $this->getContainer()->get(RenderCache::class)->clear();
        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importData', 'value' => '{"lockAcquiredBy": "test"}']
        );
    }

    protected function tearDown(): void
    {
        $this->watchStorage->deleteDirectory('watch');

        parent::tearDown();
    }
}
