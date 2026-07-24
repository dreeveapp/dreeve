<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\ActivityRecordContext;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class ActivityMovingTimeMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT activityId, startDateTime, sportType, movingTimeInSeconds
             FROM Activity
             ORDER BY startDateTime ASC'
        )->fetchAllAssociative();

        $milestones = [];
        /** @var array<string, array{raw: int, milestone: Milestone}> $records */
        $records = [];

        foreach ($rows as $row) {
            $movingTime = (int) $row['movingTimeInSeconds'];
            if ($movingTime <= 0) {
                continue;
            }

            $sportType = SportType::from($row['sportType']);
            $sportKey = $sportType->value;

            if (isset($records[$sportKey]) && $movingTime <= $records[$sportKey]['raw']) {
                continue;
            }

            $previous = null;
            if (isset($records[$sportKey])) {
                $previousMilestone = $records[$sportKey]['milestone'];
                $previous = PreviousMilestone::create(
                    previousMilestoneId: $previousMilestone->getId(),
                    threshold: Seconds::from($records[$sportKey]['raw']),
                    achievedOn: $previousMilestone->getAchievedOn(),
                );
            }

            $activityId = ActivityId::fromString($row['activityId']);
            $milestone = Milestone::create(
                id: $this->milestoneIdFactory->random(),
                achievedOn: SerializableDateTime::fromString($row['startDateTime']),
                category: MilestoneCategory::ACTIVITY_MOVING_TIME,
                context: new ActivityRecordContext(
                    value: Seconds::from($movingTime),
                ),
            )
                ->withSportType($sportType)
                ->withActivityId($activityId)
                ->withPrevious($previous);

            $milestones[] = $milestone;
            $records[$sportKey] = [
                'raw' => $movingTime,
                'milestone' => $milestone,
            ];
        }

        return Milestones::fromArray($milestones);
    }
}
