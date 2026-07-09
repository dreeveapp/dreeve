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
use App\Infrastructure\ValueObject\String\CompressedString;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\Console\ConsoleOutputSnapshotDriver;
use App\Tests\ContainerTestCase;
use App\Tests\SpyOutput;
use League\Flysystem\FilesystemOperator;
use Spatie\Snapshots\MatchesSnapshots;

class ImportActivityFilesCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ImportActivityFilesCommandHandler $handler;
    private FilesystemOperator $watchStorage;

    public function testHandleImportsActivityFile(): void
    {
        $this->dropInWatchFolder('ride.tcx', $this->fixture('activity.tcx'));

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $fileImports = $this->getFileImports();
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

        $this->assertMatchesSnapshot($output, new ConsoleOutputSnapshotDriver());
    }

    public function testHandleSkipsAlreadyImportedFile(): void
    {
        $bytes = $this->fixture('activity.tcx');

        $this->dropInWatchFolder('ride1.tcx', $bytes);
        $this->dropInWatchFolder('ride2.tcx', $bytes);
        $this->dropInWatchFolder('ride3.tcx', $bytes);

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $this->assertCount(3, $this->getFileImports());
        $this->assertEquals(
            [FileImportStatus::SUCCESS, FileImportStatus::SKIPPED,  FileImportStatus::SKIPPED],
            array_map(
                static fn (array $file): FileImportStatus => FileImportStatus::from($file['status']),
                array_values($this->getFileImports())
            )
        );
        $this->assertMatchesSnapshot($output, new ConsoleOutputSnapshotDriver());
    }

    public function testHandleSkipsUnsupportedFileType(): void
    {
        $this->dropInWatchFolder('notes.txt', 'just some text');

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $this->assertCount(0, $this->getFileImports());
        $this->assertMatchesSnapshot($output, new ConsoleOutputSnapshotDriver());
    }

    public function testHandleRecordsFailureForCorruptFile(): void
    {
        $this->dropInWatchFolder('broken.tcx', 'this is not valid xml');

        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $fileImports = $this->getFileImports();
        $this->assertCount(1, $fileImports);
        $this->assertSame(FileImportStatus::FAILED->value, $fileImports[0]['status']);
        $this->assertNull($fileImports[0]['activityId']);
        $this->assertSame('this is not valid xml', CompressedString::fromCompressed($fileImports[0]['fileContents'])->uncompress());

        $this->assertMatchesSnapshot($output, new ConsoleOutputSnapshotDriver());
    }

    public function testHandleWithoutImportDirectoryIsNoOp(): void
    {
        $output = new SpyOutput();
        $this->handler->handle(new ImportActivityFiles($output));

        $this->assertCount(0, $this->getFileImports());
        $this->assertMatchesSnapshot($output, new ConsoleOutputSnapshotDriver());
    }

    private function getFileImports(): array
    {
        return $this->getConnection()
            ->executeQuery('SELECT * FROM FileImport ORDER BY importedOn ASC')
            ->fetchAllAssociative();
    }

    private function dropInWatchFolder(string $filename, string $contents): void
    {
        $this->watchStorage->write('watch/'.$filename, $contents);
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
        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importDataOrBuildApp', 'value' => '{"lockAcquiredBy": "test"}']
        );
    }

    protected function tearDown(): void
    {
        $this->watchStorage->deleteDirectory('watch');

        parent::tearDown();
    }
}
