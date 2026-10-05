<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\DeleteGear;

use App\Domain\Gear\DeleteGear\DeleteGear;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DeleteGearTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteGear::fromPayload([
            'gearId' => '  gear-1  ',
        ]);

        $this->assertSame('gear-1', (string) $command->getGearId());
    }

    #[TestWith([[]])]
    #[TestWith([['gearId' => '   ']])]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "gearId" is required.'));

        DeleteGear::fromPayload($payload);
    }
}
