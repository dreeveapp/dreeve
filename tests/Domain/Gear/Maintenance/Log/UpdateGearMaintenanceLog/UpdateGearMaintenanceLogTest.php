<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\Log\UpdateGearMaintenanceLog;

use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogId;
use App\Domain\Gear\Maintenance\Log\UpdateGearMaintenanceLog\UpdateGearMaintenanceLog;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateGearMaintenanceLogTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UpdateGearMaintenanceLog::fromPayload([
            'gearMaintenanceLogId' => (string) GearMaintenanceLogId::fromUnprefixed('abc'),
            'performedOn' => '  2025-01-01 00:00:00  ',
        ]);

        $this->assertEquals(
            GearMaintenanceLogId::fromUnprefixed('abc'),
            $command->getGearMaintenanceLogId(),
        );
        $this->assertEquals(SerializableDateTime::fromString('2025-01-01 00:00:00'), $command->getPerformedOn());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UpdateGearMaintenanceLog::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'gearMaintenanceLogId is missing' => [
            [
                'performedOn' => '2025-01-01 00:00:00',
            ],
            'A "gearMaintenanceLogId" and "performedOn" are required.',
        ];

        yield 'performedOn is missing' => [
            [
                'gearMaintenanceLogId' => (string) GearMaintenanceLogId::fromUnprefixed('abc'),
            ],
            'A "gearMaintenanceLogId" and "performedOn" are required.',
        ];

        yield 'performedOn is not a string' => [
            [
                'gearMaintenanceLogId' => (string) GearMaintenanceLogId::fromUnprefixed('abc'),
                'performedOn' => 12345,
            ],
            'A "gearMaintenanceLogId" and "performedOn" are required.',
        ];

        yield 'performedOn is not a valid date' => [
            [
                'gearMaintenanceLogId' => (string) GearMaintenanceLogId::fromUnprefixed('abc'),
                'performedOn' => 'not-a-date',
            ],
            'The "performedOn" is not a valid date.',
        ];
    }
}
