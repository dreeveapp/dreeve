<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateAthleteSettings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateAthleteSettings\UpdateAthleteSettings;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Tests\ContainerTestCase;

class UpdateAthleteSettingsCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private SettingsRepository $settingsRepository;
    private KeyValueStore $keyValueStore;

    public function testItOnlyUpdatesTheAthleteAndFlagsForceRebuild(): void
    {
        $this->settingsRepository->save(
            group: SettingsGroup::GENERAL,
            data: [
                'appSubTitle' => 'A subtitle that should be left alone',
                'profilePictureUrl' => 'https://example.com/picture.png',
                'athlete' => [
                    'birthday' => '1980-01-01',
                    'firstName' => 'John',
                    'maxHeartRateFormula' => 'fox',
                ],
            ]
        );

        $athlete = [
            'birthday' => '1990-01-01',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'maxHeartRateFormula' => 'arena',
        ];

        $this->commandBus->dispatch(UpdateAthleteSettings::fromPayload([
            'athlete' => $athlete,
        ]));

        $this->assertSame([
            'appSubTitle' => 'A subtitle that should be left alone',
            'profilePictureUrl' => 'https://example.com/picture.png',
            'athlete' => $athlete,
        ], $this->settingsRepository->find(SettingsGroup::GENERAL));
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
