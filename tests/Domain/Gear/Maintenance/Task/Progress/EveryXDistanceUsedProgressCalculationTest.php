<?php

namespace App\Tests\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\Progress\EveryXDistanceUsedProgressCalculation;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Domain\Gear\Maintenance\Task\Progress\ProgressCalculationContext;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;

class EveryXDistanceUsedProgressCalculationTest extends ContainerTestCase
{
    private EveryXDistanceUsedProgressCalculation $calculation;

    #[DataProvider('provideIntervalUnits')]
    public function testCalculate(IntervalUnit $intervalUnit, MaintenanceTaskProgress $expectedProgress): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('last-tagged'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('include'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 01:00:00'))
                ->withDistance(Kilometer::from(100))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('include-2'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 02:00:00'))
                ->withDistance(Kilometer::from(150))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::random())
                ->withDistance(Kilometer::from(100))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01 00:00:00'))
                ->withDistance(Kilometer::from(100))
                ->build(),
            []
        ));

        $this->assertEquals(
            $expectedProgress,
            $this->calculation->calculate(
                ProgressCalculationContext::from(
                    gearIds: GearIds::fromArray([GearId::fromUnprefixed('test')]),
                    lastTaggedOn: SerializableDateTime::fromString('01-01-2025'),
                    intervalUnit: $intervalUnit,
                    intervalValue: 1000,
                )
            )
        );
    }

    /**
     * @return iterable<string, array{IntervalUnit, MaintenanceTaskProgress}>
     */
    public static function provideIntervalUnits(): iterable
    {
        yield 'kilometers' => [IntervalUnit::EVERY_X_KILOMETERS_USED, MaintenanceTaskProgress::from(250, 1000, '250 km', '750 km')];
        yield 'miles' => [IntervalUnit::EVERY_X_MILES_USED, MaintenanceTaskProgress::from(250 * Kilometer::FACTOR_TO_MILES, 1000, '155 mi', '844 mi')];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculation = new EveryXDistanceUsedProgressCalculation(
            $this->getContainer()->get(Connection::class),
        );
    }
}
