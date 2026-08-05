<?php

namespace App\Tests\Domain\Activity\Eddington;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class EddingtonCalculatorTest extends ContainerTestCase
{
    private EddingtonCalculator $eddingtonCalculator;

    public function testCalculate(): void
    {
        $this->provideActivities();

        $eddingtons = $this->eddingtonCalculator->calculate(UnitSystem::METRIC);

        $this->assertCount(1, $eddingtons);
        $eddington = $eddingtons[0];

        $this->assertEquals('ridemetric', $eddington->getId());
        $this->assertEquals('Ride', $eddington->getLabel());
        $this->assertEquals(UnitSystem::METRIC, $eddington->getUnitSystem());
        $this->assertEquals(3, $eddington->getNumber());
        $this->assertEquals(5, $eddington->getLongestDistanceInADay());
        $this->assertEquals(
            [1 => 5, 2 => 4, 3 => 3, 4 => 2, 5 => 1],
            $eddington->getTimesCompletedData()
        );
        $this->assertEquals(
            [4 => 2, 5 => 4],
            $eddington->getDaysToCompleteForFutureNumbers()
        );
        $this->assertEquals(
            [
                1 => SerializableDateTime::fromString('2023-01-01'),
                2 => SerializableDateTime::fromString('2023-01-03'),
                3 => SerializableDateTime::fromString('2023-01-05'),
            ],
            $eddington->getEddingtonHistory()
        );
    }

    public function testCalculateForImperial(): void
    {
        $this->provideActivities();

        $eddingtons = $this->eddingtonCalculator->calculate(UnitSystem::IMPERIAL);

        $this->assertCount(1, $eddingtons);
        $eddington = $eddingtons[0];

        $this->assertEquals('rideimperial', $eddington->getId());
        $this->assertEquals(UnitSystem::IMPERIAL, $eddington->getUnitSystem());
        $this->assertEquals(2, $eddington->getNumber());
        $this->assertEquals(3, $eddington->getLongestDistanceInADay());
        $this->assertEquals(
            [1 => 4, 2 => 2, 3 => 1],
            $eddington->getTimesCompletedData()
        );
        $this->assertEquals(
            [3 => 2],
            $eddington->getDaysToCompleteForFutureNumbers()
        );
        $this->assertEquals(
            [
                1 => SerializableDateTime::fromString('2023-01-02'),
                2 => SerializableDateTime::fromString('2023-01-05'),
            ],
            $eddington->getEddingtonHistory()
        );
    }

    public function testCalculateWithoutActivities(): void
    {
        $this->assertEquals([], $this->eddingtonCalculator->calculate(UnitSystem::METRIC));
    }

    private function provideActivities(): void
    {
        $this->addActivity('1', '2023-01-01 10:00:00', SportType::RIDE, 1);
        $this->addActivity('2', '2023-01-02 10:00:00', SportType::RIDE, 2);
        $this->addActivity('3', '2023-01-03 10:00:00', SportType::RIDE, 3);
        $this->addActivity('4', '2023-01-04 10:00:00', SportType::RIDE, 4);
        $this->addActivity('5', '2023-01-05 09:00:00', SportType::RIDE, 2.5);
        $this->addActivity('6', '2023-01-05 18:00:00', SportType::RIDE, 2.5);
        $this->addActivity('7', '2023-01-01 10:00:00', SportType::RUN, 0.5);
    }

    private function addActivity(string $activityId, string $startDateTime, SportType $sportType, float $distanceInKm): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                ->withSportType($sportType)
                ->withDistance(Kilometer::from($distanceInKm))
                ->build(),
            []
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->eddingtonCalculator = $this->getContainer()->get(EddingtonCalculator::class);
    }
}
