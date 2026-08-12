<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorldType;
use App\Domain\Milestone\Context\FirstActivityInCountryContext;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FirstActivityInCountryMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function discover(MilestoneIdFactory $milestoneIdFactory): Milestones
    {
        $rows = $this->connection->executeQuery(
            "SELECT activityId, startDateTime, sportType, name,
                    JSON_EXTRACT(routeGeography, '$.country_code') AS countryCode
             FROM Activity
             WHERE JSON_EXTRACT(routeGeography, '$.country_code') IS NOT NULL
             AND worldType = :worldType
             ORDER BY startDateTime ASC",
            [
                'worldType' => WorldType::REAL_WORLD->value,
            ],
        )->fetchAllAssociative();

        $milestones = [];
        /** @var array<string, true> $seenCountries */
        $seenCountries = [];

        foreach ($rows as $row) {
            $countryCode = strtolower((string) $row['countryCode']);
            if (isset($seenCountries[$countryCode])) {
                continue;
            }

            $seenCountries[$countryCode] = true;

            $milestones[] = Milestone::create(
                id: $milestoneIdFactory->next(),
                achievedOn: SerializableDateTime::fromString($row['startDateTime']),
                category: MilestoneCategory::FIRST_ACTIVITY_IN_COUNTRY,
                context: new FirstActivityInCountryContext(
                    countryCode: $countryCode,
                    activityName: $row['name'],
                ),
            )
            ->withSportType(SportType::from($row['sportType']))
            ->withActivityId(ActivityId::fromString($row['activityId']));
        }

        return Milestones::fromArray($milestones);
    }
}
