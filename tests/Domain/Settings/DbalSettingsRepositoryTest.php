<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\DaemonSettings;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Tests\ContainerTestCase;

class DbalSettingsRepositoryTest extends ContainerTestCase
{
    private SettingsRepository $settingsRepository;

    public function testFindReturnsDefaultsWhenNothingIsStored(): void
    {
        $this->assertSame([], $this->settingsRepository->find(SettingsGroup::DAEMON));
        $this->assertEquals(
            DaemonSettings::fromArray([]),
            $this->settingsRepository->daemon()
        );
    }

    public function testSaveAndFindKeepValueTypesAndKeyOrder(): void
    {
        $data = [
            'numberOfNewActivitiesToProcessPerImport' => '250',
            'skipActivitiesRecordedBefore' => null,
            'optInToSegmentDetailImport' => true,
            'sportTypesToImport' => ['Ride', 'GravelRide'],
            'webhooks' => ['enabled' => false, 'checkIntervalInMinutes' => 5],
        ];
        $this->settingsRepository->save(SettingsGroup::IMPORT, $data);

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::IMPORT));
    }

    public function testSaveReplacesTheWholeGroup(): void
    {
        $this->settingsRepository->save(SettingsGroup::ZWIFT, ['level' => 80, 'racingScore' => 495]);
        $this->settingsRepository->save(SettingsGroup::ZWIFT, ['level' => 81]);

        $this->assertSame(['level' => 81], $this->settingsRepository->find(SettingsGroup::ZWIFT));
    }

    public function testSaveDoesNotTouchOtherGroups(): void
    {
        $this->settingsRepository->save(SettingsGroup::MAPS, ['polylineColor' => 'red']);
        $this->settingsRepository->save(SettingsGroup::SECURITY, []);

        $this->assertSame(['polylineColor' => 'red'], $this->settingsRepository->find(SettingsGroup::MAPS));
        $this->assertSame([], $this->settingsRepository->find(SettingsGroup::SECURITY));
    }

    public function testFindAppliesTheDefaultHeartRateFormulas(): void
    {
        $this->settingsRepository->save(SettingsGroup::GENERAL, [
            'birthday' => '1990-01-01',
        ]);

        $this->assertEquals(
            [
                'birthday' => '1990-01-01',
                'maxHeartRateFormula' => 'fox',
                'restingHeartRateFormula' => 'heuristicAgeBased',
            ],
            $this->settingsRepository->find(SettingsGroup::GENERAL)
        );
    }

    public function testFindDoesNotOverrideConfiguredHeartRateFormulas(): void
    {
        $this->settingsRepository->save(SettingsGroup::GENERAL, [
            'maxHeartRateFormula' => ['2023-01-01' => 180],
            'restingHeartRateFormula' => 58,
        ]);

        $this->assertEquals(
            [
                'maxHeartRateFormula' => ['2023-01-01' => 180],
                'restingHeartRateFormula' => 58,
            ],
            $this->settingsRepository->find(SettingsGroup::GENERAL)
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = $this->getContainer()->get(DbalSettingsRepository::class);
    }
}
