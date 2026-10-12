<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\DbalFileImportRepository;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\Eventing\SpyEventBus;

class DbalFileImportRepositoryTest extends ContainerTestCase
{
    private FileImportRepository $fileImportRepository;

    public function testFindUncompressesTheStoredFile(): void
    {
        $fileImportId = FileImportId::fromUnprefixed('1');
        $fileContents = (string) file_get_contents($this->getContainer()->get(KernelProjectDir::class).'/tests/Domain/Import/FileParser/fixtures/activity.fit');

        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId($fileImportId)
                ->withOriginalFilename('morning-run.fit')
                ->withFileContents($fileContents)
                ->withSource(ImportSource::FIT_FILE)
                ->withStatus(FileImportStatus::SUCCESS)
                ->withActivityId(ActivityId::fromUnprefixed('42'))
                ->withImportedOn(SerializableDateTime::fromString('2026-06-04 10:00:00'))
                ->build()
        );

        $fileImport = $this->fileImportRepository->find($fileImportId);

        $this->assertEquals($fileImportId, $fileImport->getId());
        $this->assertSame('morning-run.fit', $fileImport->getOriginalFilename());
        $this->assertSame($fileContents, $fileImport->getFileContents());
        $this->assertSame(ImportSource::FIT_FILE, $fileImport->getSource());
        $this->assertSame(FileImportStatus::SUCCESS, $fileImport->getStatus());
        $this->assertNull($fileImport->getErrorMessage());
        $this->assertEquals(ActivityId::fromUnprefixed('42'), $fileImport->getActivityId());
        $this->assertEquals(SerializableDateTime::fromString('2026-06-04 10:00:00'), $fileImport->getImportedOn());
    }

    public function testFindReturnsNoContentsForAnImportThatNeverStoredTheFile(): void
    {
        $fileImportId = FileImportId::fromUnprefixed('1');
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId($fileImportId)
                ->withFileContents(null)
                ->withStatus(FileImportStatus::SKIPPED)
                ->build()
        );

        $this->assertNull($this->fileImportRepository->find($fileImportId)->getFileContents());
    }

    public function testFindThrowsForAnUnknownFileImport(): void
    {
        $fileImportId = FileImportId::fromUnprefixed('nope');

        $this->expectExceptionObject(new EntityNotFound(sprintf('File import "%s" is no longer available', $fileImportId)));

        $this->fileImportRepository->find($fileImportId);
    }

    public function testFindForActivityReturnsTheNewestImportWithAStoredFile(): void
    {
        $activityId = ActivityId::fromUnprefixed('42');
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withFileContents('older bytes')
                ->withActivityId($activityId)
                ->withImportedOn(SerializableDateTime::fromString('2026-06-04 10:00:00'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('2'))
                ->withFileContents('newer bytes')
                ->withActivityId($activityId)
                ->withImportedOn(SerializableDateTime::fromString('2026-06-05 10:00:00'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('3'))
                ->withFileContents(null)
                ->withActivityId($activityId)
                ->withImportedOn(SerializableDateTime::fromString('2026-06-06 10:00:00'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('4'))
                ->withFileContents('other activity bytes')
                ->withActivityId(ActivityId::fromUnprefixed('43'))
                ->withImportedOn(SerializableDateTime::fromString('2026-06-07 10:00:00'))
                ->build()
        );

        $fileImport = $this->fileImportRepository->findForActivity($activityId);

        $this->assertEquals(FileImportId::fromUnprefixed('2'), $fileImport->getId());
        $this->assertSame('newer bytes', $fileImport->getFileContents());
        $this->assertTrue($this->fileImportRepository->hasFileContentsForActivity($activityId));
    }

    public function testFindForActivityThrowsWhenNoImportStoredItsFile(): void
    {
        $activityId = ActivityId::fromUnprefixed('42');
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withFileContents(null)
                ->withActivityId($activityId)
                ->build()
        );

        $this->assertFalse($this->fileImportRepository->hasFileContentsForActivity($activityId));

        $this->expectExceptionObject(new EntityNotFound(sprintf('Activity "%s" has no stored original file', $activityId)));

        $this->fileImportRepository->findForActivity($activityId);
    }

    public function testDeleteForActivity(): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('2'))
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->build()
        );

        $this->fileImportRepository->deleteForActivity(ActivityId::fromUnprefixed('1'));

        $this->assertSame(
            0,
            (int) $this->getConnection()->executeQuery(
                'SELECT COUNT(*) FROM FileImport WHERE activityId = :activityId',
                ['activityId' => (string) ActivityId::fromUnprefixed('1')]
            )->fetchOne()
        );
        $this->assertSame(
            1,
            (int) $this->getConnection()->executeQuery(
                'SELECT COUNT(*) FROM FileImport WHERE activityId = :activityId',
                ['activityId' => (string) ActivityId::fromUnprefixed('2')]
            )->fetchOne()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->fileImportRepository = new DbalFileImportRepository(
            $this->getConnection(),
            new SpyEventBus(),
        );
    }
}
