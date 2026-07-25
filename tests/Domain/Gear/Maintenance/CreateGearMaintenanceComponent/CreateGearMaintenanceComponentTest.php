<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\CreateGearMaintenanceComponent;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\CreateGearMaintenanceComponent\CreateGearMaintenanceComponent;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CreateGearMaintenanceComponentTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = CreateGearMaintenanceComponent::fromPayload([
            'label' => '  Chain  ',
            'attachedTo' => ['b1', 'g2'],
            'purchasePriceAmount' => '123.45',
            'purchasePriceCurrency' => 'EUR',
            'maintenanceTasks' => [
                ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ['label' => 'Replace', 'interval' => ['value' => 1000, 'unit' => 'km']],
            ],
        ]);

        $this->assertSame('Chain', (string) $command->getLabel());
        $this->assertNull($command->getNewImage());
        $this->assertEquals(Money::EUR(12345), $command->getPurchasePrice());
        $this->assertEquals(
            [GearId::fromUnprefixed('b1'), GearId::fromUnprefixed('g2')],
            $command->getAttachedTo()->toArray(),
        );
        $this->assertCount(2, $command->getMaintenanceTasks());
    }

    public function testFromPayloadWithNewImage(): void
    {
        $command = CreateGearMaintenanceComponent::fromPayload([
            'label' => 'Chain',
            'attachedTo' => ['b1'],
            'localImagePath' => json_encode([
                ['status' => 'new', 'filename' => 'chain.png', 'content' => base64_encode('image-content')],
            ]),
            'maintenanceTasks' => [
                ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ]);

        $this->assertNotNull($command->getNewImage());
        $this->assertSame('chain.png', (string) $command->getNewImage()->getFilename());
        $this->assertSame('image-content', $command->getNewImage()->getContent());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        CreateGearMaintenanceComponent::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'image file type is unsupported' => [
            [
                'label' => 'Chain',
                'attachedTo' => ['b1'],
                'localImagePath' => json_encode([
                    ['status' => 'new', 'filename' => 'chain.gif', 'content' => base64_encode('image-content')],
                ]),
                'maintenanceTasks' => [
                    ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ],
            ],
            'Unsupported image file type.',
        ];

        yield 'label is missing' => [
            [
                'attachedTo' => ['b1'],
                'maintenanceTasks' => [
                    ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ],
            ],
            'A non-empty "label" is required.',
        ];

        yield 'maintenanceTasks is empty' => [
            [
                'label' => 'Chain',
                'attachedTo' => ['b1'],
                'maintenanceTasks' => [],
            ],
            'At least one maintenance task is required.',
        ];

        yield 'maintenance task ids are duplicated' => [
            [
                'label' => 'Chain',
                'attachedTo' => ['b1'],
                'maintenanceTasks' => [
                    ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                    ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Replace', 'interval' => ['value' => 1000, 'unit' => 'km']],
                ],
            ],
            'Duplicate maintenance task ids found.',
        ];
    }
}
