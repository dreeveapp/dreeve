<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\DeleteFileImport;

use App\Domain\Import\DeleteFileImport\DeleteFileImport;
use App\Domain\Import\FileImportId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class DeleteFileImportTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteFileImport::fromPayload([
            'fileImportId' => (string) FileImportId::fromUnprefixed('abc'),
        ]);

        $this->assertEquals(
            FileImportId::fromUnprefixed('abc'),
            $command->getFileImportId(),
        );
    }

    public function testFromPayloadThrowsWhenMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "fileImportId" is required.'));

        DeleteFileImport::fromPayload([]);
    }

    public function testFromPayloadThrowsWhenNotAString(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "fileImportId" is required.'));

        DeleteFileImport::fromPayload([
            'fileImportId' => ['nope'],
        ]);
    }
}
