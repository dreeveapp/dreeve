<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\FirstContext;
use App\Domain\Milestone\Discoverer\FirstActivityOfSportTypeMilestoneDiscoverer;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class FirstActivityOfSportTypeMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

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

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(FirstContext::class, $context);
        $this->assertEquals(SportType::RIDE, $context->getSportType());
        $this->assertEquals('Morning ride', $context->getActivityName());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverRespectsChronologicalOrder(): void
    {
        $this->insertActivity(1, '2024-01-02', SportType::RIDE, 'Second ride');
        $this->insertActivity(2, '2024-01-01', SportType::RIDE, 'First ride');

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
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
