<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\DeleteFileImport;

use App\Domain\Import\DeleteFileImport\DeleteFileImport;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Import\FileImportBuilder;

class DeleteFileImportCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private FileImportRepository $fileImportRepository;

    public function testHandle(): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('2'))
                ->build()
        );

        $this->commandBus->dispatch(DeleteFileImport::fromPayload([
            'fileImportId' => (string) FileImportId::fromUnprefixed('1'),
        ]));

        $this->assertSame(
            [(string) FileImportId::fromUnprefixed('2')],
            $this->getConnection()->executeQuery('SELECT fileImportId FROM FileImport')->fetchFirstColumn(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->fileImportRepository = $this->getContainer()->get(FileImportRepository::class);
    }
}
