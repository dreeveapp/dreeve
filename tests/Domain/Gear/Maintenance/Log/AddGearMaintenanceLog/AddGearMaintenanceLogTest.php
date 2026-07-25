<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\Log\AddGearMaintenanceLog;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Log\AddGearMaintenanceLog\AddGearMaintenanceLog;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddGearMaintenanceLogTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = AddGearMaintenanceLog::fromPayload([
            'gearId' => '  '.GearId::fromUnprefixed('b1').'  ',
            'maintenanceTaskId' => '  '.MaintenanceTaskId::fromUnprefixed('chain-lubed').'  ',
            'performedOn' => '  2025-01-01 00:00:00  ',
        ]);

        $this->assertEquals(GearId::fromUnprefixed('b1'), $command->getGearId());
        $this->assertEquals(MaintenanceTaskId::fromUnprefixed('chain-lubed'), $command->getMaintenanceTaskId());
        $this->assertEquals(SerializableDateTime::fromString('2025-01-01 00:00:00'), $command->getPerformedOn());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        AddGearMaintenanceLog::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'gearId is missing' => [
            [
                'maintenanceTaskId' => 'chain-lubed',
                'performedOn' => '2025-01-01 00:00:00',
            ],
            'A "gearId", "maintenanceTaskId" and "performedOn" are required.',
        ];

        yield 'gearId is not a string' => [
            [
                'gearId' => ['b1'],
                'maintenanceTaskId' => 'chain-lubed',
                'performedOn' => '2025-01-01 00:00:00',
            ],
            'A "gearId", "maintenanceTaskId" and "performedOn" are required.',
        ];

        yield 'gearId is empty' => [
            [
                'gearId' => '   ',
                'maintenanceTaskId' => 'chain-lubed',
                'performedOn' => '2025-01-01 00:00:00',
            ],
            'The "gearId" and "maintenanceTaskId" cannot be empty.',
        ];

        yield 'maintenanceTaskId is empty' => [
            [
                'gearId' => 'b1',
                'maintenanceTaskId' => '   ',
                'performedOn' => '2025-01-01 00:00:00',
            ],
            'The "gearId" and "maintenanceTaskId" cannot be empty.',
        ];

        yield 'performedOn is not a valid date' => [
            [
                'gearId' => 'b1',
                'maintenanceTaskId' => 'chain-lubed',
                'performedOn' => 'not-a-date',
            ],
            'The "performedOn" is not a valid date.',
        ];
    }
}
