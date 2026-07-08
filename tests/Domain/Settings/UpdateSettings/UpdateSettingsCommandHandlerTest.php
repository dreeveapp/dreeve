<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateSettings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Tests\ContainerTestCase;

class UpdateSettingsCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private SettingsRepository $settingsRepository;
    private KeyValueStore $keyValueStore;

    public function testItUpdatesGeneralSettingsAndFlagsForceRebuild(): void
    {
        $data = [
            'profilePictureUrl' => null,
            'appSubTitle' => 'A brand new subtitle',
            'athlete' => [
                'birthday' => '1990-01-01',
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'maxHeartRateFormula' => 'fox',
            ],
        ];

        $this->commandBus->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::GENERAL->value,
            'data' => $data,
        ]));

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::GENERAL));
        $this->assertSame('1', (string) $this->keyValueStore->find(Key::FORCE_REBUILD));
    }

    public function testItUpdatesAppearanceSettingsAndFlagsForceRebuild(): void
    {
        $data = [
            'unitSystem' => 'imperial',
            'locale' => 'nl_BE',
            'timeFormat' => 12,
            'dateFormat' => [
                'short' => 'm-d-y',
                'normal' => 'm-d-Y',
            ],
            'photos' => [
                'hidePhotosForSportTypes' => ['VirtualRide'],
            ],
        ];

        $this->commandBus->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::APPEARANCE->value,
            'data' => $data,
        ]));

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::APPEARANCE));
        $this->assertSame('1', (string) $this->keyValueStore->find(Key::FORCE_REBUILD));
    }

    public function testItUpdatesImportSettingsAndFlagsForceRebuild(): void
    {
        $data = [
            'numberOfNewActivitiesToProcessPerImport' => 100,
            'sportTypesToImport' => ['Ride'],
            'activityVisibilitiesToImport' => ['everyone'],
            'skipActivitiesRecordedBefore' => '2023-09-01',
            'activitiesToSkipDuringImport' => ['123'],
            'optInToSegmentDetailImport' => false,
            'webhooks' => [
                'enabled' => true,
                'verifyToken' => 'el-token',
            ],
        ];

        $this->commandBus->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::IMPORT->value,
            'data' => $data,
        ]));

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::IMPORT));
        $this->assertSame('1', (string) $this->keyValueStore->find(Key::FORCE_REBUILD));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
    }
}
