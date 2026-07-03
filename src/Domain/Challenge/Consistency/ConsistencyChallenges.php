<?php

declare(strict_types=1);

namespace App\Domain\Challenge\Consistency;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ConsistencyChallenge>
 */
final class ConsistencyChallenges extends Collection
{
    public function getItemClassName(): string
    {
        return ConsistencyChallenge::class;
    }

    /**
     * @return array<int, mixed>
     */
    public static function getDefaultConfig(): array
    {
        return [
            [
                'label' => 'Ride a total of 200km',
                'type' => 'distance',
                'unit' => 'km',
                'goal' => 200,
                'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
            ],
            [
                'label' => 'Ride a total of 600km',
                'type' => 'distance',
                'unit' => 'km',
                'goal' => 600,
                'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
            ],
            [
                'label' => 'Ride a total of 1250km',
                'type' => 'distance',
                'unit' => 'km',
                'goal' => 1250,
                'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
            ],
            [
                'label' => 'Complete a 100km ride',
                'type' => 'distanceInOneActivity',
                'unit' => 'km',
                'goal' => 100,
                'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
            ],
            [
                'label' => 'Climb a total of 7500m',
                'type' => 'elevation',
                'unit' => 'm',
                'goal' => 7500,
                'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
            ],
            [
                'label' => 'Complete a 5km run',
                'type' => 'distanceInOneActivity',
                'unit' => 'km',
                'goal' => 5,
                'sportTypesToInclude' => ['Run', 'TrailRun', 'VirtualRun'],
            ],
            [
                'label' => 'Complete a 10km run',
                'type' => 'distanceInOneActivity',
                'unit' => 'km',
                'goal' => 10,
                'sportTypesToInclude' => ['Run', 'TrailRun', 'VirtualRun'],
            ],
            [
                'label' => 'Complete a half marathon run',
                'type' => 'distanceInOneActivity',
                'unit' => 'km',
                'goal' => 21.1,
                'sportTypesToInclude' => ['Run', 'TrailRun', 'VirtualRun'],
            ],
            [
                'label' => 'Run a total of 100km',
                'type' => 'distance',
                'unit' => 'km',
                'goal' => 100,
                'sportTypesToInclude' => ['Run', 'TrailRun', 'VirtualRun'],
            ],
            [
                'label' => 'Climb a total of 2000m',
                'type' => 'elevation',
                'unit' => 'm',
                'goal' => 2000,
                'sportTypesToInclude' => ['Run', 'TrailRun', 'VirtualRun'],
            ],
        ];
    }

    /**
     * @param array<int, mixed> $items
     */
    public static function fromConfig(array $items): self
    {
        if ([] === $items) {
            // Make sure this new feature is backwards compatible.
            // Use the old default configuration.
            $items = self::getDefaultConfig();
        }

        $consistencyChallenges = [];
        foreach ($items as $challengeConfig) {
            if (!is_array($challengeConfig)) {
                throw new InvalidDashboardLayout('Invalid Challenge configuration provided');
            }

            foreach (['label', 'type', 'unit', 'goal', 'sportTypesToInclude'] as $requiredKey) {
                if (array_key_exists($requiredKey, $challengeConfig)) {
                    continue;
                }
                throw new InvalidDashboardLayout(sprintf('"%s" property is required', $requiredKey));
            }

            if (empty($challengeConfig['label'])) {
                throw new InvalidDashboardLayout('"label" property cannot be empty');
            }

            if (!is_numeric($challengeConfig['goal'])) {
                throw new InvalidDashboardLayout('"goal" property must be a valid number');
            }

            if (!$type = ChallengeConsistencyType::tryFrom($challengeConfig['type'])) {
                throw new InvalidDashboardLayout(sprintf('"%s" is not a valid type', $challengeConfig['type']));
            }

            if (!is_array($challengeConfig['sportTypesToInclude'])) {
                throw new InvalidDashboardLayout('"sportTypesToInclude" property must be an array');
            }

            $sportTypesToInclude = SportTypes::empty();
            foreach ($challengeConfig['sportTypesToInclude'] as $sportTypeToInclude) {
                if (!$sportType = SportType::tryFrom($sportTypeToInclude)) {
                    throw new InvalidDashboardLayout(sprintf('"%s" is not a valid sport type', $sportTypeToInclude));
                }
                $sportTypesToInclude->add($sportType);
            }

            if (in_array($type, ChallengeConsistencyType::lengthRelated()) && !in_array($challengeConfig['unit'], [
                ConsistencyChallenge::KILOMETER,
                ConsistencyChallenge::METER,
                ConsistencyChallenge::MILES,
                ConsistencyChallenge::FOOT,
            ])) {
                throw new InvalidDashboardLayout(sprintf('The unit "%s" is not valid for challenge type "%s"', $challengeConfig['unit'], $type->value));
            }

            if (ChallengeConsistencyType::MOVING_TIME === $type && !in_array($challengeConfig['unit'], [
                ConsistencyChallenge::HOUR,
                ConsistencyChallenge::MINUTE,
            ])) {
                throw new InvalidDashboardLayout(sprintf('The unit "%s" is not valid for challenge type "%s"', $challengeConfig['unit'], $type->value));
            }

            if (ChallengeConsistencyType::NUMBER_OF_ACTIVITIES === $type) {
                // Hardcode the unit to a random value, it won't be used anyway.
                $challengeConfig['unit'] = 'km';
            }

            if ($sportTypesToInclude->isEmpty()) {
                $sportTypesToInclude = SportTypes::all();
            }

            $consistencyChallenges[] = ConsistencyChallenge::create(
                label: $challengeConfig['label'],
                type: $type,
                goal: (float) $challengeConfig['goal'],
                unit: $challengeConfig['unit'],
                sportTypesToInclude: $sportTypesToInclude
            );
        }

        return self::fromArray($consistencyChallenges);
    }
}
