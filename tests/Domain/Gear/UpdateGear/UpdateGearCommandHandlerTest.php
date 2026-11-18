<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\UpdateGear;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\UpdateGear\UpdateGear;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\MediaItems;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
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
                ->withNotes('Original notes')
                ->build()
        );

        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'Updated gear',
            'status' => 'retired',
            'purchasePriceAmount' => '2000.00',
            'purchasePriceCurrency' => 'EUR',
            'notes' => 'Updated notes',
        ]));

        $gear = $this->gearRepository->find(GearId::fromUnprefixed('1'));
        $this->assertSame('Updated gear', $gear->getOriginalName());
        $this->assertTrue($gear->isRetired());
        $this->assertEquals(Money::EUR(200000), $gear->getPurchasePrice());
        $this->assertSame('Updated notes', $gear->getNotes());
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
        $mediaItemRepository = $this->getContainer()->get(MediaItemRepository::class);
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
            ->withPath(MediaItemPath::fromString('gear/old.jpg'))
            ->build());
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('Original gear')
                ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
                ->build()
        );

        // Replacing the single image: the dropzone removes the existing one before adding the new one.
        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'Updated gear',
            'localImagePath' => json_encode([
                ['status' => 'removed', 'id' => 'mediaItem-old'],
                ['status' => 'new', 'filename' => 'new.jpg', 'content' => base64_encode('new-content')],
            ]),
        ]));

        $gear = $this->gearRepository->find(GearId::fromUnprefixed('1'));
        $this->assertEquals(MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5'), $gear->getMediaItemId());
        $this->assertFalse($this->fileStorage->fileExists('gear/old.jpg'));
        $this->assertTrue($this->fileStorage->fileExists('gear/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
        $this->assertSame('new-content', $this->fileStorage->read('gear/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
        $this->assertEquals(
            MediaItems::fromArray([
                MediaItemBuilder::fromDefaults()
                    ->withMediaItemId(MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5'))
                    ->withPath(MediaItemPath::fromString('gear/0025176c-5652-11ee-923d-02424dd627d5.jpg'))
                    ->build(),
            ]),
            $mediaItemRepository->findByIds(MediaItemIds::fromArray([
                MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5'),
                MediaItemId::fromUnprefixed('old'),
            ])),
        );
    }

    public function testHandleRemovesImageAndDeletesFile(): void
    {
        $this->fileStorage->write('gear/old.jpg', 'old-content');
        $mediaItemRepository = $this->getContainer()->get(MediaItemRepository::class);
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
            ->withPath(MediaItemPath::fromString('gear/old.jpg'))
            ->build());
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('Original gear')
                ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
                ->build()
        );

        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'Updated gear',
            'localImagePath' => json_encode([
                ['status' => 'removed', 'id' => 'mediaItem-old'],
            ]),
        ]));

        $gear = $this->gearRepository->find(GearId::fromUnprefixed('1'));
        $this->assertNull($gear->getMediaItemId());
        $this->assertFalse($this->fileStorage->fileExists('gear/old.jpg'));
        $this->assertTrue($mediaItemRepository->findByIds(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('old')]))->isEmpty());
    }

    public function testHandleKeepsImageWhenUnchanged(): void
    {
        $this->fileStorage->write('gear/old.jpg', 'old-content');
        $this->getContainer()->get(MediaItemRepository::class)->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
            ->withPath(MediaItemPath::fromString('gear/old.jpg'))
            ->build());
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('Original gear')
                ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
                ->build()
        );

        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => 'gear-1',
            'name' => 'Updated gear',
            'localImagePath' => json_encode([
                ['status' => 'unchanged', 'id' => 'mediaItem-old'],
            ]),
        ]));

        $gear = $this->gearRepository->find(GearId::fromUnprefixed('1'));
        $this->assertEquals(MediaItemId::fromUnprefixed('old'), $gear->getMediaItemId());
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
