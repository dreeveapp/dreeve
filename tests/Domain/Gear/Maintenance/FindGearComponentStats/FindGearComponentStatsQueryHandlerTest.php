<?php

namespace App\Tests\Domain\Gear\Maintenance\FindGearComponentStats;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\FindGearComponentStats\FindGearComponentStats;
use App\Domain\Gear\Maintenance\FindGearComponentStats\FindGearComponentStatsResponse;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindGearComponentStatsQueryHandlerTest extends ContainerTestCase
{
    private QueryBus $queryBus;

    public function testHandle(): void
    {
        $activities = [
            ['bike-a-january', 'bike-a', '2025-01-10 10:00:00', 10, 3600],
            ['bike-a-june', 'bike-a', '2025-06-01 10:00:00', 40, 7200],
            ['bike-b-march', 'bike-b', '2025-03-20 10:00:00', 80, 3600],
            ['other-gear', 'other', '2025-02-01 10:00:00', 160, 3600],
        ];
        foreach ($activities as [$activityId, $gearId, $startDateTime, $distanceInKm, $movingTimeInSeconds]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withGearId(GearId::fromUnprefixed($gearId))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->withDistance(Kilometer::from($distanceInKm))
                    ->withMovingTimeInSeconds($movingTimeInSeconds)
                    ->build(),
                []
            ));
        }

        $this->assertEquals(
            new FindGearComponentStatsResponse(
                distance: Kilometer::from(90),
                movingTime: Seconds::from(7200),
                numberOfActivities: 2,
                firstUsedOn: SerializableDateTime::fromString('2025-01-10 10:00:00'),
                lastUsedOn: SerializableDateTime::fromString('2025-03-20 10:00:00'),
            ),
            $this->queryBus->ask(new FindGearComponentStats(UsageWindows::fromArray([
                UsageWindow::create(
                    gearId: GearId::fromUnprefixed('bike-a'),
                    from: null,
                    until: SerializableDateTime::fromString('2025-03-01'),
                    sportTypes: SportTypes::empty(),
                ),
                UsageWindow::create(
                    gearId: GearId::fromUnprefixed('bike-b'),
                    from: SerializableDateTime::fromString('2025-03-01'),
                    until: null,
                    sportTypes: SportTypes::empty(),
                ),
            ]))),
        );
    }

    public function testHandleWithoutUsageWindows(): void
    {
        $this->assertEquals(
            new FindGearComponentStatsResponse(
                distance: Kilometer::from(0),
                movingTime: Seconds::from(0),
                numberOfActivities: 0,
                firstUsedOn: null,
                lastUsedOn: null,
            ),
            $this->queryBus->ask(new FindGearComponentStats(UsageWindows::empty())),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryBus = $this->getContainer()->get(QueryBus::class);
    }
}
