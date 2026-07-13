<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\UpdateGear;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\UpdateGear\UpdateGear;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use League\Flysystem\FilesystemOperator;
use Money\Money;

class UpdateGearCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private GearRepository $gearRepository;
    private FilesystemOperator $fileStorage;

    public function testHandle(): void
    {
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('Original gear')
                ->withIsRetired(false)
                ->build()
        );

        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'Updated gear',
            'status' => 'retired',
            'purchasePriceAmount' => '2000.00',
            'purchasePriceCurrency' => 'EUR',
        ]));

        $gear = $this->gearRepository->find(GearId::fromUnprefixed('1'));
        $this->assertSame('Updated gear', $gear->getOriginalName());
        $this->assertTrue($gear->isRetired());
        $this->assertEquals(Money::EUR(200000), $gear->getPurchasePrice());
    }

    public function testHandleKeepsExistingPurchasePriceWhenNoneGiven(): void
    {
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('Original gear')
                ->withPurchasePrice(Money::EUR(150000))
                ->build()
        );

        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'Updated gear',
        ]));

        $gear = $this->gearRepository->find(GearId::fromUnprefixed('1'));
        $this->assertSame('Updated gear', $gear->getOriginalName());
        $this->assertFalse($gear->isRetired());
        $this->assertEquals(Money::EUR(150000), $gear->getPurchasePrice());
    }

    public function testHandleReplacesImageAndDeletesPreviousFile(): void
    {
        $this->fileStorage->write('gear/old.jpg', 'old-content');
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('Original gear')
                ->withLocalImagePath('files/gear/old.jpg')
                ->build()
        );

        // Replacing the single image: the dropzone removes the existing one before adding the new one.
        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'Updated gear',
            'localImagePath' => json_encode([
                ['status' => 'removed', 'path' => '/files/gear/old.jpg'],
                ['status' => 'new', 'filename' => 'new.jpg', 'content' => base64_encode('new-content')],
            ]),
        ]));

        $gear = $this->gearRepository->find(GearId::fromUnprefixed('1'));
        $this->assertSame('/files/gear/0025176c-5652-11ee-923d-02424dd627d5.jpg', $gear->getLocalImagePath());
        $this->assertFalse($this->fileStorage->fileExists('gear/old.jpg'));
        $this->assertTrue($this->fileStorage->fileExists('gear/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
        $this->assertSame('new-content', $this->fileStorage->read('gear/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
    }

    public function testHandleRemovesImageAndDeletesFile(): void
    {
        $this->fileStorage->write('gear/old.jpg', 'old-content');
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('Original gear')
                ->withLocalImagePath('files/gear/old.jpg')
                ->build()
        );

        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'Updated gear',
            'localImagePath' => json_encode([
                ['status' => 'removed', 'path' => '/files/gear/old.jpg'],
            ]),
        ]));

        $gear = $this->gearRepository->find(GearId::fromUnprefixed('1'));
        $this->assertNull($gear->getLocalImagePath());
        $this->assertFalse($this->fileStorage->fileExists('gear/old.jpg'));
    }

    public function testHandleKeepsImageWhenUnchanged(): void
    {
        $this->fileStorage->write('gear/old.jpg', 'old-content');
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('Original gear')
                ->withLocalImagePath('files/gear/old.jpg')
                ->build()
        );

        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'Updated gear',
            'localImagePath' => json_encode([
                ['status' => 'unchanged', 'path' => '/files/gear/old.jpg'],
            ]),
        ]));

        $gear = $this->gearRepository->find(GearId::fromUnprefixed('1'));
        $this->assertSame('/files/gear/old.jpg', $gear->getLocalImagePath());
        $this->assertTrue($this->fileStorage->fileExists('gear/old.jpg'));
    }

    public function testHandleThrowsWhenGearNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Gear "gear-999" not found'));

        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-999',
            'name' => 'Updated gear',
        ]));
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
