<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\EddingtonContext;
use App\Domain\Milestone\Discoverer\EddingtonMilestoneDiscoverer;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Milestone\IncrementingMilestoneIdFactory;
use Spatie\Snapshots\MatchesSnapshots;

class EddingtonMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private EddingtonMilestoneDiscoverer $discoverer;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover()->isEmpty());
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

        $milestones = $this->discoverer->discover();

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(EddingtonContext::class, $context);
        $this->assertEquals(1, $context->getNumber());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
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

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->discoverer = new EddingtonMilestoneDiscoverer(
            $this->getContainer()->get(EddingtonCalculator::class),
            $this->getContainer()->get(SettingsRepository::class),
            new IncrementingMilestoneIdFactory(),
        );
    }
}
