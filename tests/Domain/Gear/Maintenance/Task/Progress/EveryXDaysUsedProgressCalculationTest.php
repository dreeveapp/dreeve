<?php

namespace App\Tests\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\Progress\EveryXDaysUsedProgressCalculation;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Domain\Gear\Maintenance\Task\Progress\ProgressCalculationContext;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

class EveryXDaysUsedProgressCalculationTest extends ContainerTestCase
{
    private EveryXDaysUsedProgressCalculation $calculation;

    public function testCalculate(): void
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
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('include-2'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 02:00:00'))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('include-3'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-02 02:00:00'))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::random())
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01 00:00:00'))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $this->assertEquals(
            MaintenanceTaskProgress::from(
                elapsed: 2,
                interval: 10,
                elapsedDescription: '2 days',
                remainingDescription: '8 days',
            ),
            $this->calculation->calculate(
                ProgressCalculationContext::from(
                    gearIds: GearIds::fromArray([GearId::fromUnprefixed('test')]),
                    lastTaggedOn: SerializableDateTime::fromString('01-01-2025'),
                    intervalUnit: IntervalUnit::EVERY_X_HOURS_USED,
                    intervalValue: 10,
                )
            )
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculation = new EveryXDaysUsedProgressCalculation(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(TranslatorInterface::class),
        );
    }
}
