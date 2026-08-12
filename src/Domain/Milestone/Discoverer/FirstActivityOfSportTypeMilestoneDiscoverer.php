<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\FirstContext;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FirstActivityOfSportTypeMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function discover(MilestoneIdFactory $milestoneIdFactory): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT activityId, startDateTime, sportType, name
             FROM Activity
             ORDER BY startDateTime ASC'
        )->fetchAllAssociative();

        $milestones = [];
        /** @var array<string, true> $seenSportTypes */
        $seenSportTypes = [];

        foreach ($rows as $row) {
            $sportTypeValue = $row['sportType'];
            if (isset($seenSportTypes[$sportTypeValue])) {
                continue;
            }

            $sportType = SportType::from($sportTypeValue);
            $seenSportTypes[$sportTypeValue] = true;

            $milestones[] = Milestone::create(
                id: $milestoneIdFactory->next(),
                achievedOn: SerializableDateTime::fromString($row['startDateTime']),
                category: MilestoneCategory::FIRST_ACTIVITY_OF_SPORT_TYPE,
                context: new FirstContext(
                    sportType: $sportType,
                    activityName: $row['name'],
                ),
            )
            ->withSportType($sportType)
            ->withActivityId(ActivityId::fromString($row['activityId']));
        }

        return Milestones::fromArray($milestones);
    }
}
