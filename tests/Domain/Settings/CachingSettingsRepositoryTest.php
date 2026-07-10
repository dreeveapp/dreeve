<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\AppearanceSettings;
use App\Domain\Settings\AthleteHasNotBeenConfigured;
use App\Domain\Settings\CachingSettingsRepository;
use App\Domain\Settings\DaemonSettings;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\ImportSettings;
use App\Domain\Settings\IntegrationsSettings;
use App\Domain\Settings\MetricsSettings;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\ZwiftSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CachingSettingsRepositoryTest extends TestCase
{
    #[DataProvider('cachedAccessorProvider')]
    public function testAccessorIsReadFromInnerOnlyOnce(string $method, object $value, callable $act): void
    {
        $inner = $this->createMock(SettingsRepository::class);
        $inner->expects($this->once())
            ->method($method)
            ->willReturn($value);

        $repository = new CachingSettingsRepository($inner);

        $this->assertSame($act($repository), $act($repository));
    }

    public static function cachedAccessorProvider(): iterable
    {
        yield 'general' => ['general', GeneralSettings::fromArray(['athlete' => ['birthday' => '1989-08-14', 'maxHeartRateFormula' => 'fox']]), fn (SettingsRepository $r) => $r->general()];
        yield 'appearance' => ['appearance', AppearanceSettings::fromArray(null), fn (SettingsRepository $r) => $r->appearance()];
        yield 'import' => ['import', ImportSettings::fromArray(null), fn (SettingsRepository $r) => $r->import()];
        yield 'metrics' => ['metrics', MetricsSettings::fromArray(null), fn (SettingsRepository $r) => $r->metrics()];
        yield 'zwift' => ['zwift', ZwiftSettings::fromArray(null), fn (SettingsRepository $r) => $r->zwift()];
        yield 'integrations' => ['integrations', IntegrationsSettings::fromArray(null), fn (SettingsRepository $r) => $r->integrations()];
        yield 'daemon' => ['daemon', DaemonSettings::fromArray(null), fn (SettingsRepository $r) => $r->daemon()];
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
            ->willReturn(GeneralSettings::fromArray([
                'athlete' => [
                    'birthday' => '1989-08-14',
                    'maxHeartRateFormula' => 'fox',
                ],
            ]));
        $inner->expects($this->once())
            ->method('save')
            ->with(SettingsGroup::GENERAL, ['foo' => 'bar']);

        $repository = new CachingSettingsRepository($inner);

        $repository->general();
        $repository->save(SettingsGroup::GENERAL, ['foo' => 'bar']);
        $repository->general();
    }

    public function testAthleteNotConfiguredExceptionIsNotCached(): void
    {
        $inner = $this->createMock(SettingsRepository::class);

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
}
