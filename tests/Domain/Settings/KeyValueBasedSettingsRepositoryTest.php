<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\DaemonSettings;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Tests\ContainerTestCase;

class KeyValueBasedSettingsRepositoryTest extends ContainerTestCase
{
    private SettingsRepository $settingsRepository;

    public function testFindReturnsEmptyArrayWhenAbsent(): void
    {
        $this->assertEquals(
            DaemonSettings::fromArray([]),
            $this->settingsRepository->daemon()
        );
    }

    public function testFindAppliesTheDefaultHeartRateFormulas(): void
    {
        $this->settingsRepository->save(SettingsGroup::GENERAL, [
            'athlete' => ['birthday' => '1990-01-01'],
        ]);

        $this->assertEquals(
            [
                'athlete' => [
                    'birthday' => '1990-01-01',
                    'maxHeartRateFormula' => 'fox',
                    'restingHeartRateFormula' => 'heuristicAgeBased',
                ],
            ],
            $this->settingsRepository->find(SettingsGroup::GENERAL)
        );
    }

    public function testFindDoesNotOverrideConfiguredHeartRateFormulas(): void
    {
        $this->settingsRepository->save(SettingsGroup::GENERAL, [
            'athlete' => [
                'maxHeartRateFormula' => ['2023-01-01' => 180],
                'restingHeartRateFormula' => 58,
            ],
        ]);

        $this->assertEquals(
            [
                'athlete' => [
                    'maxHeartRateFormula' => ['2023-01-01' => 180],
                    'restingHeartRateFormula' => 58,
                ],
            ],
            $this->settingsRepository->find(SettingsGroup::GENERAL)
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = $this->getContainer()->get(KeyValueBasedSettingsRepository::class);
    }
}
