<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\UpdateGear;

use App\Domain\Gear\GearId;
use App\Domain\Gear\UpdateGear\UpdateGear;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateGearTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'My custom gear',
            'status' => 'retired',
            'purchasePriceAmount' => '1500.00',
            'purchasePriceCurrency' => 'EUR',
        ]);

        $this->assertEquals(GearId::fromUnprefixed('1'), $command->getGearId());
        $this->assertSame('My custom gear', $command->getName());
        $this->assertTrue($command->isRetired());
        $this->assertEquals(Money::EUR(150000), $command->getPurchasePrice());
    }

    public function testFromPayloadDefaultsToActiveWithoutPurchasePrice(): void
    {
        $command = UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'My custom gear',
        ]);

        $this->assertFalse($command->isRetired());
        $this->assertNull($command->getPurchasePrice());
        $this->assertNull($command->getNewImage());
        $this->assertNull($command->getRemovedImage());
    }

    public function testFromPayloadWithNewImage(): void
    {
        $command = UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'My custom gear',
            'localImagePath' => json_encode([
                ['status' => 'new', 'filename' => 'gear.png', 'content' => base64_encode('image-content')],
            ]),
        ]);

        $this->assertNotNull($command->getNewImage());
        $this->assertSame('gear.png', (string) $command->getNewImage()->getFilename());
        $this->assertNull($command->getRemovedImage());
    }

    public function testFromPayloadWithRemovedImage(): void
    {
        $command = UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'My custom gear',
            'localImagePath' => json_encode([
                ['status' => 'removed', 'path' => '/files/gear/old.jpg'],
            ]),
        ]);

        $this->assertNull($command->getNewImage());
        $this->assertNotNull($command->getRemovedImage());
        $this->assertSame('files/gear/old.jpg', $command->getRemovedImage()->getPath()->toLocalImagePath());
    }

    public function testFromPayloadTrimsName(): void
    {
        $command = UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => '  My custom gear  ',
        ]);

        $this->assertSame('My custom gear', $command->getName());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UpdateGear::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing gearId' => [
            ['name' => 'My custom gear'],
            'A "gearId" and "name" are required.',
        ];

        yield 'missing name' => [
            ['gearId' => 'gear-1'],
            'A "gearId" and "name" are required.',
        ];

        yield 'empty name' => [
            ['gearId' => 'gear-1', 'name' => '   '],
            'The name cannot be empty.',
        ];

        yield 'invalid status' => [
            ['gearId' => 'gear-1', 'name' => 'My custom gear', 'status' => 'not-a-status'],
            'The status is invalid.',
        ];

        yield 'invalid purchase price' => [
            ['gearId' => 'gear-1', 'name' => 'My custom gear', 'purchasePriceAmount' => 'not-a-number'],
            'The purchase price is invalid.',
        ];
    }
}
