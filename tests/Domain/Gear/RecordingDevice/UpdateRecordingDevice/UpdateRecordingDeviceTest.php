<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\RecordingDevice\UpdateRecordingDevice;

use App\Domain\Gear\RecordingDevice\UpdateRecordingDevice\UpdateRecordingDevice;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use Money\Money;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class UpdateRecordingDeviceTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UpdateRecordingDevice::fromPayload([
            'name' => 'Garmin Edge 530',
            'purchasePriceAmount' => '299.50',
            'purchasePriceCurrency' => 'EUR',
        ]);

        $this->assertSame('Garmin Edge 530', $command->getName());
        $this->assertEquals(Money::EUR(29950), $command->getPurchasePrice());
    }

    public function testFromPayloadWithoutPurchasePrice(): void
    {
        $command = UpdateRecordingDevice::fromPayload([
            'name' => 'Garmin Edge 530',
        ]);

        $this->assertSame('Garmin Edge 530', $command->getName());
        $this->assertNull($command->getPurchasePrice());
    }

    public function testFromPayloadTrimsName(): void
    {
        $command = UpdateRecordingDevice::fromPayload([
            'name' => '  Garmin Edge 530  ',
        ]);

        $this->assertSame('Garmin Edge 530', $command->getName());
    }

    #[TestWith([['purchasePriceAmount' => '299.50'], 'A "name" is required.'])]
    #[TestWith([['name' => '   '], 'The name cannot be empty.'])]
    public function testFromPayloadThrowsOnInvalidName(array $payload, string $expectedMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedMessage));

        UpdateRecordingDevice::fromPayload($payload);
    }

    public function testFromPayloadThrowsOnInvalidPurchasePrice(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('The purchase price is invalid.'));

        UpdateRecordingDevice::fromPayload([
            'name' => 'Garmin Edge 530',
            'purchasePriceAmount' => 'not-a-number',
        ]);
    }
}
