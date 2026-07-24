<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\PersonalBestContext;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class PersonalBestMilestoneDiscoverer implements MilestoneDiscoverer
{
    use ProvideTimeFormats;

    public function __construct(
        private Connection $connection,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT be.activityId, be.sportType, be.distanceInMeter, be.timeInSeconds, a.startDateTime
             FROM ActivityBestEffort be
             INNER JOIN Activity a ON a.activityId = be.activityId
             ORDER BY a.startDateTime ASC, be.distanceInMeter ASC'
        )->fetchAllAssociative();

        $distanceMap = $this->buildDistanceMap();

        $milestones = [];
        /** @var array<string, Milestone> $records */
        $records = [];

        foreach ($rows as $row) {
            $sportType = SportType::from($row['sportType']);
            $distanceInMeter = (int) $row['distanceInMeter'];
            $timeInSeconds = (int) $row['timeInSeconds'];

            $distance = $distanceMap[$distanceInMeter];
            $recordKey = $sportType->value.'_'.$distanceInMeter;

            $previousTime = null;
            if (isset($records[$recordKey])) {
                $previousContext = $records[$recordKey]->getContext();
                assert($previousContext instanceof PersonalBestContext);
                $previousTime = $previousContext->getTime()->toInt();
            }

            if (null !== $previousTime && $timeInSeconds >= $previousTime) {
                continue;
            }

            $previous = null;
            if (null !== $previousTime) {
                $previous = PreviousMilestone::create(
                    previousMilestoneId: $records[$recordKey]->getId(),
                    threshold: Seconds::from($previousTime),
                    achievedOn: $records[$recordKey]->getAchievedOn(),
                );
            }

            $activityId = ActivityId::fromString($row['activityId']);
            $milestone = Milestone::create(
                id: $this->milestoneIdFactory->random(),
                achievedOn: SerializableDateTime::fromString($row['startDateTime']),
                category: MilestoneCategory::PERSONAL_BEST,
                context: new PersonalBestContext(
                    distance: $distance,
                    time: Seconds::from($timeInSeconds),
                ),
            )
                ->withSportType($sportType)
                ->withActivityId($activityId)
                ->withPrevious($previous);

            $milestones[] = $milestone;
            $records[$recordKey] = $milestone;
        }

        return Milestones::fromArray($milestones);
    }

    /**
     * @return array<int, ConvertableToMeter>
     */
    private function buildDistanceMap(): array
    {
        $map = [];

        foreach (ActivityType::cases() as $activityType) {
            foreach ($activityType->getDistancesForBestEffortCalculation() as $distance) {
                $meter = $distance->toMeter()->toInt();
                if (!isset($map[$meter])) {
                    $map[$meter] = $distance;
                }
            }
        }

        return $map;
    }
}
