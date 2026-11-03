<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\Progress\EveryXDaysProgressCalculation;
use App\Domain\Gear\Maintenance\Task\Progress\EveryXDaysUsedProgressCalculation;
use App\Domain\Gear\Maintenance\Task\Progress\EveryXDistanceUsedProgressCalculation;
use App\Domain\Gear\Maintenance\Task\Progress\EveryXHoursUsedProgressCalculation;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculation;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MaintenanceTaskProgressCalculationSupportsTest extends ContainerTestCase
{
    /**
     * @param class-string<MaintenanceTaskProgressCalculation> $calculationClass
     */
    #[DataProvider('provideSupports')]
    public function testSupports(string $calculationClass, IntervalUnit $intervalUnit, bool $expected): void
    {
        $calculation = $this->getContainer()->get($calculationClass);
        \assert($calculation instanceof MaintenanceTaskProgressCalculation);

        $this->assertSame($expected, $calculation->supports($intervalUnit));
    }

    /**
     * @return array<string, array{class-string<MaintenanceTaskProgressCalculation>, IntervalUnit, bool}>
     */
    public static function provideSupports(): array
    {
        return [
            'days supports days' => [EveryXDaysProgressCalculation::class, IntervalUnit::EVERY_X_DAYS, true],
            'days rejects km' => [EveryXDaysProgressCalculation::class, IntervalUnit::EVERY_X_KILOMETERS_USED, false],

            'days used supports days used' => [EveryXDaysUsedProgressCalculation::class, IntervalUnit::EVERY_X_DAYS_USED, true],
            'days used rejects days' => [EveryXDaysUsedProgressCalculation::class, IntervalUnit::EVERY_X_DAYS, false],

            'hours used supports hours used' => [EveryXHoursUsedProgressCalculation::class, IntervalUnit::EVERY_X_HOURS_USED, true],
            'hours used rejects days' => [EveryXHoursUsedProgressCalculation::class, IntervalUnit::EVERY_X_DAYS, false],

            'distance used supports km' => [EveryXDistanceUsedProgressCalculation::class, IntervalUnit::EVERY_X_KILOMETERS_USED, true],
            'distance used supports miles' => [EveryXDistanceUsedProgressCalculation::class, IntervalUnit::EVERY_X_MILES_USED, true],
            'distance used rejects hours used' => [EveryXDistanceUsedProgressCalculation::class, IntervalUnit::EVERY_X_HOURS_USED, false],
        ];
    }
}
