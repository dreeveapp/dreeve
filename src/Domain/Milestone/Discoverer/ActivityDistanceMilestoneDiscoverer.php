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
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class ActivityDistanceMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT activityId, startDateTime, sportType, distance
             FROM Activity
             ORDER BY startDateTime ASC'
        )->fetchAllAssociative();

        $milestones = [];
        /** @var array<string, array{raw: float, milestone: Milestone}> $records */
        $records = [];

        foreach ($rows as $row) {
            $distanceRaw = (float) $row['distance'];
            if ($distanceRaw <= 0) {
                continue;
            }

            $sportType = SportType::from($row['sportType']);
            $sportKey = $sportType->value;

            if (isset($records[$sportKey]) && $distanceRaw <= $records[$sportKey]['raw']) {
                continue;
            }

            $distanceInKm = Meter::from($distanceRaw)->toKilometer();

            $previous = null;
            if (isset($records[$sportKey])) {
                $previousMilestone = $records[$sportKey]['milestone'];
                $previousContext = $previousMilestone->getContext();
                assert($previousContext instanceof ActivityRecordContext);

                $previous = PreviousMilestone::create(
                    previousMilestoneId: $previousMilestone->getId(),
                    threshold: $previousContext->getValue(),
                    achievedOn: $previousMilestone->getAchievedOn(),
                );
            }

            $activityId = ActivityId::fromString($row['activityId']);
            $milestone = Milestone::create(
                id: $this->milestoneIdFactory->random(),
                achievedOn: SerializableDateTime::fromString($row['startDateTime']),
                category: MilestoneCategory::ACTIVITY_DISTANCE,
                context: new ActivityRecordContext(
                    value: $distanceInKm,
                ),
            )
                ->withSportType($sportType)
                ->withActivityId($activityId)
                ->withPrevious($previous);

            $milestones[] = $milestone;
            $records[$sportKey] = [
                'raw' => $distanceRaw,
                'milestone' => $milestone,
            ];
        }

        return Milestones::fromArray($milestones);
    }
}
