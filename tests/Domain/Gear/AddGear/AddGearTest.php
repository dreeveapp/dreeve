<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\AddGear;

use App\Domain\Gear\AddGear\AddGear;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddGearTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = AddGear::fromPayload([
            'name' => 'My custom gear',
            'status' => 'retired',
            'purchasePriceAmount' => '1500.00',
            'purchasePriceCurrency' => 'EUR',
        ]);

        $this->assertSame('My custom gear', $command->getName());
        $this->assertTrue($command->isRetired());
        $this->assertEquals(Money::EUR(150000), $command->getPurchasePrice());
    }

    public function testFromPayloadDefaultsToActiveWithoutPurchasePrice(): void
    {
        $command = AddGear::fromPayload([
            'name' => 'My custom gear',
        ]);

        $this->assertSame('My custom gear', $command->getName());
        $this->assertFalse($command->isRetired());
        $this->assertNull($command->getPurchasePrice());
        $this->assertNull($command->getNewImage());
    }

    public function testFromPayloadWithImage(): void
    {
        $command = AddGear::fromPayload([
            'name' => 'My custom gear',
            'localImagePath' => json_encode([
                ['status' => 'new', 'filename' => 'gear.jpg', 'content' => base64_encode('image-content')],
            ]),
        ]);

        $this->assertNotNull($command->getNewImage());
        $this->assertSame('gear.jpg', (string) $command->getNewImage()->getFilename());
        $this->assertSame('image-content', $command->getNewImage()->getContent());
    }

    public function testFromPayloadWithEmptyImageList(): void
    {
        $command = AddGear::fromPayload([
            'name' => 'My custom gear',
            'localImagePath' => '[]',
        ]);

        $this->assertNull($command->getNewImage());
    }

    public function testFromPayloadTrimsName(): void
    {
        $command = AddGear::fromPayload([
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

        AddGear::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'unsupported image type' => [
            [
                'name' => 'My custom gear',
                'localImagePath' => json_encode([
                    ['status' => 'new', 'filename' => 'gear.gif', 'content' => base64_encode('image-content')],
                ]),
            ],
            'Unsupported image file type.',
        ];

        yield 'missing name' => [
            ['status' => 'active'],
            'A "name" is required.',
        ];

        yield 'empty name' => [
            ['name' => '   '],
            'The name cannot be empty.',
        ];

        yield 'invalid status' => [
            ['name' => 'My custom gear', 'status' => 'not-a-status'],
            'The status is invalid.',
        ];

        yield 'invalid purchase price' => [
            ['name' => 'My custom gear', 'purchasePriceAmount' => 'not-a-number'],
            'The purchase price is invalid.',
        ];
    }
}
