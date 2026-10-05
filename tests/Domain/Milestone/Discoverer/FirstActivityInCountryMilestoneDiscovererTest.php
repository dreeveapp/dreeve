<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\FirstActivityInCountryContext;
use App\Domain\Milestone\Discoverer\FirstActivityInCountryMilestoneDiscoverer;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FirstActivityInCountryMilestoneDiscovererTest extends ContainerTestCase
{
    private FirstActivityInCountryMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverWithNoCountryData(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 'Morning ride', null);

        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverFirstActivityInEachCountry(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 'Ride in Belgium', 'BE');
        $this->insertActivity(2, '2024-01-02', SportType::RUN, 'Run in France', 'FR');
        $this->insertActivity(3, '2024-01-03', SportType::RIDE, 'Another ride in Belgium', 'BE');

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertCount(2, $milestones);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::FIRST_ACTIVITY_IN_COUNTRY,
                    context: new FirstActivityInCountryContext(countryCode: 'be', activityName: 'Ride in Belgium'),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-1')),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::FIRST_ACTIVITY_IN_COUNTRY,
                    context: new FirstActivityInCountryContext(countryCode: 'fr', activityName: 'Run in France'),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-2')),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverRespectsChronologicalOrder(): void
    {
        $this->insertActivity(1, '2024-01-02', SportType::RIDE, 'Second ride in Belgium', 'BE');
        $this->insertActivity(2, '2024-01-01', SportType::RIDE, 'First ride in Belgium', 'BE');

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::FIRST_ACTIVITY_IN_COUNTRY,
                    context: new FirstActivityInCountryContext(countryCode: 'be', activityName: 'First ride in Belgium'),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-2')),
            ],
            $milestones->toArray(),
        );
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new FirstActivityInCountryMilestoneDiscoverer($this->getConnection());
    }

    private function insertActivity(int $id, string $date, SportType $sportType, string $name, ?string $countryCode): void
    {
        $builder = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed($id))
            ->withStartDateTime(SerializableDateTime::fromString($date))
            ->withSportType($sportType)
            ->withName($name);

        if (null !== $countryCode) {
            $builder = $builder->withRouteGeography(RouteGeography::create(['country_code' => $countryCode]));
        }

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            $builder->build(), []
        ));
    }
}
