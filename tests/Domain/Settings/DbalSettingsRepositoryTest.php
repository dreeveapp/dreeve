<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\DaemonSettings;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Tests\ContainerTestCase;

class DbalSettingsRepositoryTest extends ContainerTestCase
{
    private SettingsRepository $settingsRepository;

    public function testFindGroupReturnsDefaultsWhenNothingIsStored(): void
    {
        $this->assertSame([], $this->settingsRepository->findGroup(SettingsGroup::DAEMON));
        $this->assertEquals(
            DaemonSettings::fromArray([]),
            $this->settingsRepository->daemon()
        );
    }

    public function testSaveGroupAndFindGroupKeepValueTypesAndKeyOrder(): void
    {
        $data = [
            'numberOfNewActivitiesToProcessPerImport' => '250',
            'skipActivitiesRecordedBefore' => null,
            'optInToSegmentDetailImport' => true,
            'sportTypesToImport' => ['Ride', 'GravelRide'],
            'webhooks' => ['enabled' => false, 'checkIntervalInMinutes' => 5],
        ];
        $this->settingsRepository->saveGroup(SettingsGroup::IMPORT, $data);

        $this->assertSame($data, $this->settingsRepository->findGroup(SettingsGroup::IMPORT));
    }

    public function testSaveGroupReplacesTheWholeGroup(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::ZWIFT, ['level' => 80, 'racingScore' => 495]);
        $this->settingsRepository->saveGroup(SettingsGroup::ZWIFT, ['level' => 81]);

        $this->assertSame(['level' => 81], $this->settingsRepository->findGroup(SettingsGroup::ZWIFT));
    }

    public function testSaveGroupDoesNotTouchOtherGroups(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::MAPS, ['polylineColor' => 'red']);
        $this->settingsRepository->saveGroup(SettingsGroup::SECURITY, []);

        $this->assertSame(['polylineColor' => 'red'], $this->settingsRepository->findGroup(SettingsGroup::MAPS));
        $this->assertSame([], $this->settingsRepository->findGroup(SettingsGroup::SECURITY));
    }

    public function testSaveUpdatesASingleSettingInPlaceAndAppendsNewOnes(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::APPEARANCE, [
            'unitSystem' => 'metric',
            'timeFormat' => 24,
        ]);

        $this->settingsRepository->save(SettingsGroup::APPEARANCE, SettingsName::UNIT_SYSTEM, 'imperial');
        $this->settingsRepository->save(SettingsGroup::APPEARANCE, SettingsName::LOCALE, 'nl_BE');

        $this->assertSame([
            'unitSystem' => 'imperial',
            'timeFormat' => 24,
            'locale' => 'nl_BE',
        ], $this->settingsRepository->findGroup(SettingsGroup::APPEARANCE));
    }

    public function testFindReturnsASingleSetting(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::APPEARANCE, ['locale' => 'nl_BE']);

        $this->assertSame('nl_BE', $this->settingsRepository->find(SettingsGroup::APPEARANCE, SettingsName::LOCALE));
        $this->assertNull($this->settingsRepository->find(SettingsGroup::APPEARANCE, SettingsName::UNIT_SYSTEM));
    }

    public function testFindAppliesDefaultsToASingleSetting(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::GENERAL, []);

        $this->assertSame('fox', $this->settingsRepository->find(SettingsGroup::GENERAL, SettingsName::MAX_HEART_RATE_FORMULA));
        $this->assertSame('heuristicAgeBased', $this->settingsRepository->find(SettingsGroup::GENERAL, SettingsName::RESTING_HEART_RATE_FORMULA));
    }

    public function testFindGroupAppliesTheDefaultHeartRateFormulas(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::GENERAL, [
            'birthday' => '1990-01-01',
        ]);

        $this->assertEquals(
            [
                'birthday' => '1990-01-01',
                'maxHeartRateFormula' => 'fox',
                'restingHeartRateFormula' => 'heuristicAgeBased',
            ],
            $this->settingsRepository->findGroup(SettingsGroup::GENERAL)
        );
    }

    public function testFindGroupDoesNotOverrideConfiguredHeartRateFormulas(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::GENERAL, [
            'maxHeartRateFormula' => ['2023-01-01' => 180],
            'restingHeartRateFormula' => 58,
        ]);

        $this->assertEquals(
            [
                'maxHeartRateFormula' => ['2023-01-01' => 180],
                'restingHeartRateFormula' => 58,
            ],
            $this->settingsRepository->findGroup(SettingsGroup::GENERAL)
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = $this->getContainer()->get(DbalSettingsRepository::class);
    }
}
