<?php

namespace App\Tests\Domain\Challenge\Consistency;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Calendar\Months;
use App\Domain\Challenge\Consistency\ChallengeConsistencyType;
use App\Domain\Challenge\Consistency\ConsistencyChallenge;
use App\Domain\Challenge\Consistency\ConsistencyChallengeCalculator;
use App\Domain\Challenge\Consistency\ConsistencyChallenges;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ConsistencyChallengeCalculatorTest extends ContainerTestCase
{
    use ProvideTestData;
    use MatchesSnapshots;

    private ConsistencyChallengeCalculator $calculator;

    public function testCalculateFor(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesJsonSnapshot(Json::encode(
            $this->calculator->calculateFor(
                months: Months::create(
                    startDate: SerializableDateTime::fromString('2023-01-01'),
                    endDate: SerializableDateTime::fromString('2023-12-31'),
                ),
                challenges: ConsistencyChallenges::fromArray([
                    ConsistencyChallenge::create(
                        label: 'Ride a total of 1km',
                        type: ChallengeConsistencyType::DISTANCE,
                        goal: 1,
                        unit: ConsistencyChallenge::KILOMETER,
                        sportTypesToInclude: SportTypes::fromArray([
                            SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE,
                        ]),
                    ),
                    ConsistencyChallenge::create(
                        label: 'Ride a total of 200km',
                        type: ChallengeConsistencyType::DISTANCE,
                        goal: 200,
                        unit: ConsistencyChallenge::KILOMETER,
                        sportTypesToInclude: SportTypes::fromArray([
                            SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE,
                        ]),
                    ),
                    ConsistencyChallenge::create(
                        label: 'a 2km ride',
                        type: ChallengeConsistencyType::DISTANCE_IN_ONE_ACTIVITY,
                        goal: 2,
                        unit: ConsistencyChallenge::KILOMETER,
                        sportTypesToInclude: SportTypes::fromArray([
                            SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE,
                        ]),
                    ),
                    ConsistencyChallenge::create(
                        label: 'Total elevation',
                        type: ChallengeConsistencyType::ELEVATION,
                        goal: 2,
                        unit: ConsistencyChallenge::METER,
                        sportTypesToInclude: SportTypes::fromArray([
                            SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE,
                        ]),
                    ),
                    ConsistencyChallenge::create(
                        label: 'a 1m elevation ride',
                        type: ChallengeConsistencyType::ELEVATION_IN_ONE_ACTIVITY,
                        goal: 2,
                        unit: ConsistencyChallenge::METER,
                        sportTypesToInclude: SportTypes::fromArray([
                            SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE,
                        ]),
                    ),
                    ConsistencyChallenge::create(
                        label: 'Swim a total of 200km',
                        type: ChallengeConsistencyType::DISTANCE,
                        goal: 200,
                        unit: ConsistencyChallenge::KILOMETER,
                        sportTypesToInclude: SportTypes::fromArray([
                            SportType::SWIM,
                        ]),
                    ),
                    ConsistencyChallenge::create(
                        label: 'Quantity',
                        type: ChallengeConsistencyType::NUMBER_OF_ACTIVITIES,
                        goal: 2,
                        unit: ConsistencyChallenge::METER,
                        sportTypesToInclude: SportTypes::fromArray([
                            SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE,
                        ]),
                    ),
                    ConsistencyChallenge::create(
                        label: 'Calories',
                        type: ChallengeConsistencyType::CALORIES,
                        goal: 2,
                        unit: ConsistencyChallenge::METER,
                        sportTypesToInclude: SportTypes::fromArray([
                            SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE,
                        ]),
                    ),
                ]),
            )
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ConsistencyChallengeCalculator(
            $this->getContainer()->get(ActivityIdRepository::class),
            $this->getContainer()->get(QueryBus::class),
        );
    }
}
