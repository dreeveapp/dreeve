<?php

declare(strict_types=1);

namespace App\Domain\Challenge\Consistency;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Calendar\Months;
use App\Domain\Challenge\Consistency\FindConsistencyMetricsPerMonth\FindConsistencyMetricsPerMonth;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\ValueObject\Measurement\Unit;

final readonly class ConsistencyChallengeCalculator
{
    public function __construct(
        private ActivityIdRepository $activityIdRepository,
        private QueryBus $queryBus,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function calculateFor(Months $months, ConsistencyChallenges $challenges): array
    {
        $months = $months->reverse();

        $consistency = [];
        foreach ($challenges as $challenge) {
            if (!$this->activityIdRepository->hasForSportTypes($challenge->getSportTypesToInclude())) {
                continue;
            }

            $response = $this->queryBus->ask(new FindConsistencyMetricsPerMonth($challenge->getSportTypesToInclude()));

            foreach ($months as $month) {
                if (!$metrics = $response->getConsistencyMetricsForMonth($month)) {
                    $consistency[$challenge->getId()][$month->getId()] = [
                        'goalHasBeenReached' => false,
                        'actualValue' => 0,
                    ];
                    continue;
                }

                $challengeGoal = $challenge->getGoal();
                [$numberOfActivities, $totalDistance, $maxDistance, $totalElevation, $maxElevation, $movingTime, $totalCaloriesBurnt] = $metrics;

                $consistency[$challenge->getId()][$month->getId()] = match ($challenge->getType()) {
                    ChallengeConsistencyType::DISTANCE => [
                        'goalHasBeenReached' => $this->checkIfGoalHasBeenReached(
                            goal: $challengeGoal,
                            actualValue: $challenge->convertKilometerToGoalUnit($totalDistance)
                        ),
                        'actualValue' => $challenge->convertKilometerToGoalUnit($totalDistance),
                    ],
                    ChallengeConsistencyType::DISTANCE_IN_ONE_ACTIVITY => [
                        'goalHasBeenReached' => $this->checkIfGoalHasBeenReached(
                            goal: $challengeGoal,
                            actualValue: $challenge->convertKilometerToGoalUnit($maxDistance)
                        ),
                        'actualValue' => $challenge->convertKilometerToGoalUnit($maxDistance),
                    ],
                    ChallengeConsistencyType::ELEVATION => [
                        'goalHasBeenReached' => $this->checkIfGoalHasBeenReached(
                            goal: $challengeGoal,
                            actualValue: $challenge->convertMeterToGoalUnit($totalElevation)
                        ),
                        'actualValue' => $challenge->convertMeterToGoalUnit($totalElevation),
                    ],
                    ChallengeConsistencyType::ELEVATION_IN_ONE_ACTIVITY => [
                        'goalHasBeenReached' => $this->checkIfGoalHasBeenReached(
                            goal: $challengeGoal,
                            actualValue: $challenge->convertMeterToGoalUnit($maxElevation)
                        ),
                        'actualValue' => $challenge->convertMeterToGoalUnit($maxElevation),
                    ],
                    ChallengeConsistencyType::MOVING_TIME => [
                        'goalHasBeenReached' => $this->checkIfGoalHasBeenReached(
                            goal: $challengeGoal,
                            actualValue: $challenge->convertSecondsToGoalUnit($movingTime)
                        ),
                        'actualValue' => $challenge->convertSecondsToGoalUnit($movingTime),
                    ],
                    ChallengeConsistencyType::NUMBER_OF_ACTIVITIES => [
                        'goalHasBeenReached' => $numberOfActivities >= $challengeGoal->toInt(),
                        'actualValue' => $numberOfActivities,
                    ],
                    ChallengeConsistencyType::CALORIES => [
                        'goalHasBeenReached' => $totalCaloriesBurnt >= $challengeGoal->toInt(),
                        'actualValue' => $totalCaloriesBurnt,
                    ],
                };
            }
        }

        return $consistency;
    }

    private function checkIfGoalHasBeenReached(Unit $goal, Unit $actualValue): bool
    {
        return $actualValue->toFloat() >= $goal->toFloat();
    }
}
