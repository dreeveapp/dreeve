<?php

namespace App\Tests\Infrastructure\CQRS\Command\Deserialize;

use App\Domain\Import\UploadActivityFile\UploadActivityFile;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommandRegistry;
use App\Tests\ContainerTestCase;

class DeserializableCommandRegistryTest extends ContainerTestCase
{
    public function testItAutoDiscoversCommandsByName(): void
    {
        $registry = $this->getContainer()->get(DeserializableCommandRegistry::class);

        $this->assertSame(UploadActivityFile::class, $registry->resolve('upload-activity-file'));
    }

    public function testItThrowsForUnknownId(): void
    {
        $registry = $this->getContainer()->get(DeserializableCommandRegistry::class);

        $this->expectExceptionObject(CouldNotDeserializeCommand::unknownCommand('not-a-known-command'));

        $registry->resolve('not-a-known-command');
    }
}
