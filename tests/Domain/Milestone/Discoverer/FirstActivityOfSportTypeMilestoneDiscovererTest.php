<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\FirstContext;
use App\Domain\Milestone\Discoverer\FirstActivityOfSportTypeMilestoneDiscoverer;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FirstActivityOfSportTypeMilestoneDiscovererTest extends ContainerTestCase
{
    private FirstActivityOfSportTypeMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverFirstOfEachSportType(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 'Morning ride');
        $this->insertActivity(2, '2024-01-02', SportType::RUN, 'Evening run');
        $this->insertActivity(3, '2024-01-03', SportType::RIDE, 'Another ride');

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::FIRST_ACTIVITY_OF_SPORT_TYPE,
                    context: new FirstContext(sportType: SportType::RIDE, activityName: 'Morning ride'),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-1')),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::FIRST_ACTIVITY_OF_SPORT_TYPE,
                    context: new FirstContext(sportType: SportType::RUN, activityName: 'Evening run'),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-2')),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverRespectsChronologicalOrder(): void
    {
        $this->insertActivity(1, '2024-01-02', SportType::RIDE, 'Second ride');
        $this->insertActivity(2, '2024-01-01', SportType::RIDE, 'First ride');

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::FIRST_ACTIVITY_OF_SPORT_TYPE,
                    context: new FirstContext(sportType: SportType::RIDE, activityName: 'First ride'),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-2')),
            ],
            $milestones->toArray(),
        );
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new FirstActivityOfSportTypeMilestoneDiscoverer($this->getConnection());
    }

    private function insertActivity(int $id, string $date, SportType $sportType, string $name): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withSportType($sportType)
                ->withName($name)
                ->build(), []
        ));
    }
}
