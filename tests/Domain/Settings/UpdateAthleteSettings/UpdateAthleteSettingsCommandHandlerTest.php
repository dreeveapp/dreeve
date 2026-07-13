<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateAthleteSettings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateAthleteSettings\UpdateAthleteSettings;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Tests\ContainerTestCase;

class UpdateAthleteSettingsCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private SettingsRepository $settingsRepository;

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
                    'weightHistory' => [['on' => '2020-01-01', 'weight' => 70]],
                    'heartRateZones' => ['mode' => 'absolute'],
                ],
            ]
        );

        $this->commandBus->dispatch(UpdateAthleteSettings::fromPayload([
            'athlete' => [
                'birthday' => '1990-01-01',
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'maxHeartRateFormula' => 'arena',
            ],
        ]));

        $this->assertSame([
            'appSubTitle' => 'A subtitle that should be left alone',
            'profilePictureUrl' => 'https://example.com/picture.png',
            'athlete' => [
                'birthday' => '1990-01-01',
                'firstName' => 'Jane',
                'maxHeartRateFormula' => 'arena',
                // The weight history and heart rate zones are managed on the general settings form.
                'weightHistory' => [['on' => '2020-01-01', 'weight' => 70]],
                'heartRateZones' => ['mode' => 'absolute'],
                'restingHeartRateFormula' => 'heuristicAgeBased',
                'lastName' => 'Doe',
            ],
        ], $this->settingsRepository->find(SettingsGroup::GENERAL));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->settingsRepository = $this->getContainer()->get(SettingsRepository::class);
    }
}
