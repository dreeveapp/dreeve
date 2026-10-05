<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\EddingtonContext;
use App\Domain\Milestone\Discoverer\EddingtonMilestoneDiscoverer;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class EddingtonMilestoneDiscovererTest extends ContainerTestCase
{
    private EddingtonMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverWithSufficientActivities(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i)))
                    ->withSportType(SportType::RIDE)
                    ->withDistance(Kilometer::from(10.0))
                    ->build(), []
            ));
        }

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 1, distance: Kilometer::from(1.0)),
                ),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 2, distance: Kilometer::from(2.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Kilometer::from(1.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-03 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 3, distance: Kilometer::from(3.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-2'),
                    threshold: Kilometer::from(2.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-04 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 4, distance: Kilometer::from(4.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-3'),
                    threshold: Kilometer::from(3.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-03 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-5'),
                    achievedOn: SerializableDateTime::fromString('2024-01-05 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 5, distance: Kilometer::from(5.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-4'),
                    threshold: Kilometer::from(4.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-04 00:00:00'),
                )),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverPreviousMilestoneTracking(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i)))
                    ->withSportType(SportType::RIDE)
                    ->withDistance(Kilometer::from(15.0))
                    ->build(), []
            ));
        }

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 1, distance: Kilometer::from(1.0)),
                ),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 2, distance: Kilometer::from(2.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Kilometer::from(1.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-03 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 3, distance: Kilometer::from(3.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-2'),
                    threshold: Kilometer::from(2.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-04 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 4, distance: Kilometer::from(4.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-3'),
                    threshold: Kilometer::from(3.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-03 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-5'),
                    achievedOn: SerializableDateTime::fromString('2024-01-05 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 5, distance: Kilometer::from(5.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-4'),
                    threshold: Kilometer::from(4.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-04 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-6'),
                    achievedOn: SerializableDateTime::fromString('2024-01-06 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 6, distance: Kilometer::from(6.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-5'),
                    threshold: Kilometer::from(5.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-05 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-7'),
                    achievedOn: SerializableDateTime::fromString('2024-01-07 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 7, distance: Kilometer::from(7.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-6'),
                    threshold: Kilometer::from(6.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-06 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-8'),
                    achievedOn: SerializableDateTime::fromString('2024-01-08 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 8, distance: Kilometer::from(8.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-7'),
                    threshold: Kilometer::from(7.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-07 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-9'),
                    achievedOn: SerializableDateTime::fromString('2024-01-09 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 9, distance: Kilometer::from(9.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-8'),
                    threshold: Kilometer::from(8.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-08 00:00:00'),
                )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-10'),
                    achievedOn: SerializableDateTime::fromString('2024-01-10 00:00:00'),
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(label: 'Ride', number: 10, distance: Kilometer::from(10.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-9'),
                    threshold: Kilometer::from(9.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-09 00:00:00'),
                )),
            ],
            $milestones->toArray(),
        );
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new EddingtonMilestoneDiscoverer(
            $this->getContainer()->get(EddingtonCalculator::class),
            $this->getContainer()->get(SettingsRepository::class),
        );
    }
}
