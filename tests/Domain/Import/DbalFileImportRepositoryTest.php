<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Import\DbalFileImportRepository;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;

class DbalFileImportRepositoryTest extends ContainerTestCase
{
    private FileImportRepository $fileImportRepository;

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

    public function testFind(): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withFileContents('the raw FIT bytes')
                ->build()
        );

        $fileImport = $this->fileImportRepository->find(FileImportId::fromUnprefixed('1'));

        $this->assertEquals(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withFileContents('the raw FIT bytes')
                ->build(),
            $fileImport
        );
    }

    public function testFindWithoutFileContents(): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withFileContents(null)
                ->build()
        );

        $this->assertNull($this->fileImportRepository->find(FileImportId::fromUnprefixed('1'))->getFileContents());
    }

    public function testFindWhenItDoesNotExist(): void
    {
        $this->expectException(EntityNotFound::class);

        $this->fileImportRepository->find(FileImportId::fromUnprefixed('1'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->fileImportRepository = new DbalFileImportRepository(
            $this->getConnection()
        );
    }
}
