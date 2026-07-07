<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\AthleteHasNotBeenConfigured;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\CachingSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

class CachingSettingsRepositoryTest extends TestCase
{
    public function testGeneralIsReadFromInnerOnlyOnce(): void
    {
        $inner = $this->createMock(SettingsRepository::class);
        $inner->expects($this->once())
            ->method('general')
            ->willReturn($this->aGeneralSettings());

        $repository = new CachingSettingsRepository($inner);

        $first = $repository->general();
        $second = $repository->general();

        $this->assertSame($first, $second);
    }

    public function testFindIsReadFromInnerOncePerGroup(): void
    {
        $inner = $this->createMock(SettingsRepository::class);
        $inner->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(fn (SettingsGroup $group): array => ['group' => $group->value]);

        $repository = new CachingSettingsRepository($inner);

        $this->assertSame(['group' => 'general'], $repository->find(SettingsGroup::GENERAL));
        $this->assertSame(['group' => 'general'], $repository->find(SettingsGroup::GENERAL));
        $this->assertSame(['group' => 'zwift'], $repository->find(SettingsGroup::ZWIFT));
        $this->assertSame(['group' => 'zwift'], $repository->find(SettingsGroup::ZWIFT));
    }

    public function testSaveDelegatesToInnerAndInvalidatesTheMemo(): void
    {
        $inner = $this->createMock(SettingsRepository::class);
        // general() is re-read after the save invalidation => inner is hit twice.
        $inner->expects($this->exactly(2))
            ->method('general')
            ->willReturn($this->aGeneralSettings());
        $inner->expects($this->once())
            ->method('save')
            ->with(SettingsGroup::GENERAL, ['foo' => 'bar']);

        $repository = new CachingSettingsRepository($inner);

        $repository->general();                                        // fills the memo
        $repository->save(SettingsGroup::GENERAL, ['foo' => 'bar']);   // invalidates it
        $repository->general();                                        // re-reads inner
    }

    public function testAthleteNotConfiguredExceptionIsNotMemoized(): void
    {
        $inner = $this->createMock(SettingsRepository::class);
        // A failing read must not be cached: every call re-hits inner.
        $inner->expects($this->exactly(2))
            ->method('general')
            ->willThrowException(AthleteHasNotBeenConfigured::because('nope'));

        $repository = new CachingSettingsRepository($inner);

        foreach (range(1, 2) as $ignored) {
            try {
                $repository->general();
                $this->fail('Expected AthleteHasNotBeenConfigured to be thrown');
            } catch (AthleteHasNotBeenConfigured) {
                // expected on every call
            }
        }
    }

    private function aGeneralSettings(): GeneralSettings
    {
        return GeneralSettings::fromArray([
            'athlete' => [
                'birthday' => '1989-08-14',
                'maxHeartRateFormula' => 'fox',
            ],
        ]);
    }
}
