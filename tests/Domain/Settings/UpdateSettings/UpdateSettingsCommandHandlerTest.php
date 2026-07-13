<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateSettings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
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
                'restingHeartRateFormula' => 'heuristicAgeBased',
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

    public function testItUpdatesMetricsSettingsAndFlagsForceRebuild(): void
    {
        $data = [
            'excludeActivitiesFromPeakPowerOutputs' => ['123456'],
            'eddington' => [
                [
                    'label' => 'Ride',
                    'showInNavBar' => true,
                    'showInDashboardWidget' => false,
                    'sportTypesToInclude' => ['Ride', 'VirtualRide'],
                ],
            ],
        ];

        $this->commandBus->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::METRICS->value,
            'data' => $data,
        ]));

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::METRICS));
        $this->assertSame('1', (string) $this->keyValueStore->find(Key::FORCE_REBUILD));
    }

    public function testItUpdatesIntegrationsSettingsAndFlagsForceRebuild(): void
    {
        $data = [
            'ai' => [
                'enabled' => true,
                'enableUI' => true,
                'provider' => 'openAI',
                'configuration' => [
                    'key' => 'my-key',
                    'model' => 'cool-model',
                ],
                'agent' => [
                    'commands' => [
                        ['command' => 'ftp', 'message' => 'What is my FTP?'],
                    ],
                ],
            ],
            'notifications' => [
                'services' => ['discord://token@webhookid?thread_id=123456789'],
            ],
        ];

        $this->commandBus->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::INTEGRATIONS->value,
            'data' => $data,
        ]));

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::INTEGRATIONS));
        $this->assertSame('1', (string) $this->keyValueStore->find(Key::FORCE_REBUILD));
    }

    public function testItRejectsInvalidIntegrationsSettings(): void
    {
        $this->expectExceptionObject(new CouldNotDeserializeCommand('commands must not start with a slash. (/ftp)'));

        UpdateSettings::fromPayload([
            'group' => SettingsGroup::INTEGRATIONS->value,
            'data' => [
                'ai' => [
                    'agent' => [
                        'commands' => [
                            ['command' => '/ftp', 'message' => 'What is my FTP?'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testItUpdatesZwiftSettingsAndFlagsForceRebuild(): void
    {
        $data = [
            'level' => 100,
            'racingScore' => 511,
        ];

        $this->commandBus->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::ZWIFT->value,
            'data' => $data,
        ]));

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::ZWIFT));
        $this->assertSame('1', (string) $this->keyValueStore->find(Key::FORCE_REBUILD));
    }

    public function testItUpdatesDaemonSettingsAndFlagsForceRebuild(): void
    {
        $data = [
            'cron' => [
                'runStravaImportAndBuildApp' => ['expression' => '0 3 * * *', 'enabled' => true],
                'gearMaintenanceNotification' => ['expression' => '0 4 * * *', 'enabled' => false],
                'appUpdateAvailableNotification' => ['expression' => '0 5 * * *', 'enabled' => false],
            ],
        ];

        $this->commandBus->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::DAEMON->value,
            'data' => $data,
        ]));

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::DAEMON));
        $this->assertSame('1', (string) $this->keyValueStore->find(Key::FORCE_REBUILD));
    }

    public function testItRejectsInvalidDaemonSettings(): void
    {
        $this->expectExceptionObject(new CouldNotDeserializeCommand('"not-a-cron" is not a valid cron expression'));

        UpdateSettings::fromPayload([
            'group' => SettingsGroup::DAEMON->value,
            'data' => [
                'cron' => [
                    'runStravaImportAndBuildApp' => ['expression' => 'not-a-cron', 'enabled' => true],
                ],
            ],
        ]);
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
