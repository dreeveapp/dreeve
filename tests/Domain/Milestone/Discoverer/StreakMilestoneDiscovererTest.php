<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Milestone\Context\StreakContext;
use App\Domain\Milestone\Discoverer\StreakMilestoneDiscoverer;
use App\Domain\Milestone\FunComparison\StreakFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\SimpleUnit;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class StreakMilestoneDiscovererTest extends ContainerTestCase
{
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

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-07 00:00:00'),
                    category: MilestoneCategory::STREAK,
                    context: new StreakContext(days: 7),
                )->withFunComparison(StreakFunComparison::FULL_WEEK),
            ],
            $milestones->toArray(),
        );
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
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-07 00:00:00'),
                    category: MilestoneCategory::STREAK,
                    context: new StreakContext(days: 7),
                )->withFunComparison(StreakFunComparison::FULL_WEEK),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-14 00:00:00'),
                    category: MilestoneCategory::STREAK,
                    context: new StreakContext(days: 14),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: SimpleUnit::from(7.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-07 00:00:00'),
                ))->withFunComparison(StreakFunComparison::FORTNIGHT),
            ],
            $milestones->toArray(),
        );
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
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-13 00:00:00'),
                    category: MilestoneCategory::STREAK,
                    context: new StreakContext(days: 7),
                )->withFunComparison(StreakFunComparison::FULL_WEEK),
            ],
            $milestones->toArray(),
        );
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
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-07 00:00:00'),
                    category: MilestoneCategory::STREAK,
                    context: new StreakContext(days: 7),
                )->withFunComparison(StreakFunComparison::FULL_WEEK),
            ],
            $milestones->toArray(),
        );
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
