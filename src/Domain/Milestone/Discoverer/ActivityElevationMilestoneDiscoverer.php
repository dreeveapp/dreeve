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

final readonly class ActivityElevationMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT activityId, startDateTime, sportType, elevation
             FROM Activity
             ORDER BY startDateTime ASC'
        )->fetchAllAssociative();

        $milestones = [];
        /** @var array<string, array{raw: float, milestone: Milestone}> $records */
        $records = [];

        foreach ($rows as $row) {
            $elevationRaw = (float) $row['elevation'];
            if ($elevationRaw <= 0) {
                continue;
            }

            $sportType = SportType::from($row['sportType']);
            $sportKey = $sportType->value;

            if (isset($records[$sportKey]) && $elevationRaw <= $records[$sportKey]['raw']) {
                continue;
            }

            $elevation = Meter::from($elevationRaw);

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
                category: MilestoneCategory::ACTIVITY_ELEVATION,
                context: new ActivityRecordContext(
                    value: $elevation,
                ),
            )
                ->withSportType($sportType)
                ->withActivityId($activityId)
                ->withPrevious($previous);

            $milestones[] = $milestone;
            $records[$sportKey] = [
                'raw' => $elevationRaw,
                'milestone' => $milestone,
            ];
        }

        return Milestones::fromArray($milestones);
    }
}
