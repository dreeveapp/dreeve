<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\AddGear;

use App\Domain\Gear\AddGear\AddGear;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearType;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use League\Flysystem\FilesystemOperator;
use Money\Money;

class AddGearCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private GearRepository $gearRepository;
    private FilesystemOperator $fileStorage;

    public function testHandle(): void
    {
        $this->commandBus->dispatch(AddGear::fromPayload([
            'name' => 'My custom gear',
            'status' => 'retired',
            'purchasePriceAmount' => '1500.00',
            'purchasePriceCurrency' => 'EUR',
            'notes' => 'Bought in Ghent',
        ]));

        $gears = $this->gearRepository->findAll();
        $this->assertCount(1, $gears);

        $gear = $gears->getFirst();
        $this->assertNotNull($gear);
        $this->assertSame('My custom gear', $gear->getOriginalName());
        $this->assertSame(GearType::CUSTOM, $gear->getType());
        $this->assertTrue($gear->isRetired());
        $this->assertEquals(Money::EUR(150000), $gear->getPurchasePrice());
        $this->assertSame('Bought in Ghent', $gear->getNotes());
        $this->assertEquals(
            SerializableDateTime::fromString('2023-10-17 16:15:04'),
            $gear->getCreatedOn()
        );
    }

    public function testHandleWithoutPurchasePrice(): void
    {
        $this->commandBus->dispatch(AddGear::fromPayload([
            'name' => 'My custom gear',
        ]));

        $gear = $this->gearRepository->findAll()->getFirst();
        $this->assertNotNull($gear);
        $this->assertSame('My custom gear', $gear->getOriginalName());
        $this->assertSame(GearType::CUSTOM, $gear->getType());
        $this->assertFalse($gear->isRetired());
        $this->assertNull($gear->getPurchasePrice());
    }

    public function testHandleWithImage(): void
    {
        $this->commandBus->dispatch(AddGear::fromPayload([
            'name' => 'My custom gear',
            'localImagePath' => json_encode([
                ['status' => 'new', 'filename' => 'gear.jpg', 'content' => base64_encode('image-content')],
            ]),
        ]));

        $gear = $this->gearRepository->findAll()->getFirst();
        $this->assertNotNull($gear);
        $this->assertSame('/files/gear/0025176c-5652-11ee-923d-02424dd627d5.jpg', $gear->getLocalImagePath());
        $this->assertTrue($this->fileStorage->fileExists('gear/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
        $this->assertSame('image-content', $this->fileStorage->read('gear/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->gearRepository = $this->getContainer()->get(GearRepository::class);
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
