<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\FirstActivityInCountryContext;
use App\Domain\Milestone\Discoverer\FirstActivityInCountryMilestoneDiscoverer;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class FirstActivityInCountryMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

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

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(FirstActivityInCountryContext::class, $context);
        $this->assertEquals('be', $context->getCountryCode());
        $this->assertEquals('Ride in Belgium', $context->getActivityName());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverRespectsChronologicalOrder(): void
    {
        $this->insertActivity(1, '2024-01-02', SportType::RIDE, 'Second ride in Belgium', 'BE');
        $this->insertActivity(2, '2024-01-01', SportType::RIDE, 'First ride in Belgium', 'BE');

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertCount(1, $milestones);
        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(FirstActivityInCountryContext::class, $context);
        $this->assertEquals('First ride in Belgium', $context->getActivityName());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
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
