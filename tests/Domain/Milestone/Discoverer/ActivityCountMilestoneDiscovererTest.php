<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\ActivityCountContext;
use App\Domain\Milestone\Discoverer\ActivityCountMilestoneDiscoverer;
use App\Domain\Milestone\FunComparison\ActivityCountFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\SimpleUnit;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class ActivityCountMilestoneDiscovererTest extends ContainerTestCase
{
    private ActivityCountMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertTrue($milestones->isEmpty());
    }

    public function testDiscoverFirstThreshold(): void
    {
        $this->insertActivities(10);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-10 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_COUNT,
                    context: new ActivityCountContext(threshold: 10),
                )->withFunComparison(ActivityCountFunComparison::ONE_PER_WEEK_TWO_MONTHS),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-10 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_COUNT,
                    context: new ActivityCountContext(threshold: 10),
                )->withSportType(SportType::RIDE)->withFunComparison(ActivityCountFunComparison::ONE_PER_WEEK_TWO_MONTHS),
            ],
            $this->discoverer->discover($this->milestoneIdFactory)->toArray(),
        );
    }

    public function testDiscoverMultipleThresholds(): void
    {
        $this->insertActivities(50);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertCount(6, $milestones);

        $global50 = $milestones->toArray()[4];
        $this->assertNull($global50->getSportType());
        $this->assertNotNull($global50->getPrevious());
        $this->assertEquals('25', $global50->getPrevious()->getThreshold());

        $sport50 = $milestones->toArray()[5];
        $this->assertEquals(SportType::RIDE, $sport50->getSportType());
        $this->assertNotNull($sport50->getPrevious());
        $this->assertEquals('25', $sport50->getPrevious()->getThreshold());
    }

    public function testDiscoverWithMultipleSportTypes(): void
    {
        for ($i = 1; $i <= 15; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i)))
                    ->withSportType(SportType::RIDE)
                    ->withDistance(Kilometer::from(10))
                    ->build(), []
            ));
        }
        for ($i = 16; $i <= 25; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i)))
                    ->withSportType(SportType::RUN)
                    ->withDistance(Kilometer::from(10))
                    ->build(), []
            ));
        }

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-10 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_COUNT,
                    context: new ActivityCountContext(threshold: 10),
                )->withFunComparison(ActivityCountFunComparison::ONE_PER_WEEK_TWO_MONTHS),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-10 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_COUNT,
                    context: new ActivityCountContext(threshold: 10),
                )->withSportType(SportType::RIDE)->withFunComparison(ActivityCountFunComparison::ONE_PER_WEEK_TWO_MONTHS),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-25 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_COUNT,
                    context: new ActivityCountContext(threshold: 25),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: SimpleUnit::from(10.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-10 00:00:00'),
                ))->withFunComparison(ActivityCountFunComparison::ONE_PER_WEEK_HALF_YEAR),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-25 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_COUNT,
                    context: new ActivityCountContext(threshold: 10),
                )->withSportType(SportType::RUN)->withFunComparison(ActivityCountFunComparison::ONE_PER_WEEK_TWO_MONTHS),
            ],
            $this->discoverer->discover($this->milestoneIdFactory)->toArray(),
        );
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new ActivityCountMilestoneDiscoverer($this->getConnection());
    }

    private function insertActivities(int $count): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', min($i, 28))))
                    ->withDistance(Kilometer::from(10))
                    ->build(), []
            ));
        }
    }
}
