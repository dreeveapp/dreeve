<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\Log\DeleteGearMaintenanceLog;

use App\Domain\Gear\Maintenance\Log\DeleteGearMaintenanceLog\DeleteGearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DeleteGearMaintenanceLogTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteGearMaintenanceLog::fromPayload([
            'gearMaintenanceLogId' => (string) GearMaintenanceLogId::fromUnprefixed('abc'),
        ]);

        $this->assertEquals(
            GearMaintenanceLogId::fromUnprefixed('abc'),
            $command->getGearMaintenanceLogId(),
        );
    }

    #[TestWith([[]])]
    #[TestWith([['gearMaintenanceLogId' => ['nope']]])]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "gearMaintenanceLogId" is required.'));

        DeleteGearMaintenanceLog::fromPayload($payload);
    }
}
