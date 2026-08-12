<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Milestone\Context\StreakContext;
use App\Domain\Milestone\Discoverer\StreakMilestoneDiscoverer;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class StreakMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private StreakMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverSevenDayStreak(): void
    {
        for ($i = 0; $i < 7; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i + 1))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i + 1)))
                    ->build(), []
            ));
        }

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(StreakContext::class, $context);
        $this->assertEquals(7, $context->getDays());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverMultipleThresholds(): void
    {
        for ($i = 0; $i < 14; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i + 1))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i + 1)))
                    ->build(), []
            ));
        }

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverNoMilestoneForShortStreak(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i + 1))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i + 1)))
                    ->build(), []
            ));
        }

        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverResetsStreakOnGap(): void
    {
        // 5-day streak, then gap, then 7-day streak
        for ($i = 0; $i < 5; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i + 1))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i + 1)))
                    ->build(), []
            ));
        }
        // Gap on Jan 6
        for ($i = 0; $i < 7; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i + 10))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i + 7)))
                    ->build(), []
            ));
        }

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverHandlesDuplicateDaysInStreak(): void
    {
        for ($i = 0; $i < 7; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i + 1))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i + 1)))
                    ->build(), []
            ));
        }
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(100))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-03'))
                ->build(), []
        ));

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testFunComparisonIsSet(): void
    {
        for ($i = 0; $i < 21; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i + 1))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i + 1)))
                    ->build(), []
            ));
        }

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $twentyOneDayMilestone = null;
        foreach ($milestones->toArray() as $milestone) {
            if (21 === $milestone->getContext()->getDays()) {
                $twentyOneDayMilestone = $milestone;
            }
        }

        $this->assertNotNull($twentyOneDayMilestone);
        $this->assertNotNull($twentyOneDayMilestone->getFunComparison());
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new StreakMilestoneDiscoverer($this->getConnection());
    }
}
