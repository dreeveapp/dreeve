<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\DeleteGear;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\DeleteGear\DeleteGear;
use App\Domain\Gear\DeleteGear\DeleteGearCommandHandler;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearType;
use App\Domain\Gear\GearUsage;
use App\Domain\Image\ImageStorage;
use App\Domain\Import\ImportMode;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use League\Flysystem\FilesystemOperator;

class DeleteGearCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private GearRepository $gearRepository;
    private FilesystemOperator $fileStorage;

    public function testItDeletesUnusedCustomGearAndItsImage(): void
    {
        $this->fileStorage->write('gear/bike.jpg', 'content');
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::CUSTOM)
                ->withLocalImagePath('files/gear/bike.jpg')
                ->build()
        );

        $this->commandBus->dispatch(DeleteGear::fromPayload(['gearId' => 'gear-1']));

        $this->assertFalse($this->fileStorage->fileExists('gear/bike.jpg'));
        $this->expectExceptionObject(new EntityNotFound('Gear "gear-1" not found'));
        $this->gearRepository->find(GearId::fromUnprefixed('1'));
    }

    public function testItRefusesToDeleteGearThatIsInUse(): void
    {
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::CUSTOM)
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->build(),
            []
        ));

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('Gear that is still in use cannot be deleted. Retire it instead.'));

        $this->commandBus->dispatch(DeleteGear::fromPayload(['gearId' => 'gear-1']));
    }

    public function testItRefusesToDeleteImportedGearInStravaImportMode(): void
    {
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::IMPORTED)
                ->build()
        );

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('Imported gear can only be deleted when running in file import mode.'));

        $this->commandBus->dispatch(DeleteGear::fromPayload(['gearId' => 'gear-1']));
    }

    public function testItDeletesImportedGearInFileImportMode(): void
    {
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::IMPORTED)
                ->build()
        );

        new DeleteGearCommandHandler(
            gearRepository: $this->gearRepository,
            gearUsage: $this->getContainer()->get(GearUsage::class),
            imageStorage: $this->getContainer()->get(ImageStorage::class),
            importMode: ImportMode::FILES,
        )->handle(DeleteGear::fromPayload(['gearId' => 'gear-1']));

        $this->expectExceptionObject(new EntityNotFound('Gear "gear-1" not found'));
        $this->gearRepository->find(GearId::fromUnprefixed('1'));
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
