<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\CumulativeMovingTimeContext;
use App\Domain\Milestone\FunComparison\MovingTimeFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class CumulativeMovingTimeMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    private const array THRESHOLDS = [
        24, 48, 100, 168, 250, 500, 750, 1_000,
        1_500, 2_000, 2_500, 3_000, 4_000, 5_000,
        7_500, 10_000,
    ];

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT startDateTime, sportType, movingTimeInSeconds
             FROM Activity
             ORDER BY startDateTime ASC'
        )->fetchAllAssociative();

        $milestones = [];
        $globalSeconds = 0;
        $globalThresholdIndex = 0;
        /** @var ?Milestone $globalPreviousMilestone */
        $globalPreviousMilestone = null;

        /** @var array<string, int> $sportSeconds */
        $sportSeconds = [];
        /** @var array<string, int> $sportThresholdIndices */
        $sportThresholdIndices = [];
        /** @var array<string, ?Milestone> $sportPreviousMilestones */
        $sportPreviousMilestones = [];

        foreach ($rows as $row) {
            $seconds = (int) $row['movingTimeInSeconds'];
            if ($seconds <= 0) {
                continue;
            }

            $sportType = SportType::from($row['sportType']);
            $sportTypeValue = $row['sportType'];
            $achievedOn = SerializableDateTime::fromString($row['startDateTime']);

            $globalSeconds += $seconds;
            $globalHours = $globalSeconds / 3600;

            while ($globalThresholdIndex < count(self::THRESHOLDS) && $globalHours >= self::THRESHOLDS[$globalThresholdIndex]) {
                $threshold = self::THRESHOLDS[$globalThresholdIndex];
                $milestone = $this->createMilestone(
                    achievedOn: $achievedOn,
                    sportType: null,
                    threshold: $threshold,
                    previousMilestone: $globalPreviousMilestone
                );
                $milestones[] = $milestone;
                $globalPreviousMilestone = $milestone;
                ++$globalThresholdIndex;
            }

            if (!isset($sportSeconds[$sportTypeValue])) {
                $sportSeconds[$sportTypeValue] = 0;
                $sportThresholdIndices[$sportTypeValue] = 0;
                $sportPreviousMilestones[$sportTypeValue] = null;
            }
            $sportSeconds[$sportTypeValue] += $seconds;
            $sportHours = $sportSeconds[$sportTypeValue] / 3600;

            while ($sportThresholdIndices[$sportTypeValue] < count(self::THRESHOLDS) && $sportHours >= self::THRESHOLDS[$sportThresholdIndices[$sportTypeValue]]) {
                $threshold = self::THRESHOLDS[$sportThresholdIndices[$sportTypeValue]];
                $milestone = $this->createMilestone(
                    achievedOn: $achievedOn,
                    sportType: $sportType,
                    threshold: $threshold,
                    previousMilestone: $sportPreviousMilestones[$sportTypeValue]
                );
                $milestones[] = $milestone;
                $sportPreviousMilestones[$sportTypeValue] = $milestone;
                ++$sportThresholdIndices[$sportTypeValue];
            }
        }

        return Milestones::fromArray($milestones);
    }

    private function createMilestone(
        SerializableDateTime $achievedOn,
        ?SportType $sportType,
        int $threshold,
        ?Milestone $previousMilestone,
    ): Milestone {
        $thresholdHour = Hour::from($threshold);

        return Milestone::create(
            id: $this->milestoneIdFactory->random(),
            achievedOn: $achievedOn,
            category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
            context: new CumulativeMovingTimeContext(
                threshold: $thresholdHour,
            ),
        )
            ->withSportType($sportType)
            ->withPrevious($this->buildPreviousMilestone($previousMilestone))
            ->withFunComparison(MovingTimeFunComparison::resolve($thresholdHour));
    }

    private function buildPreviousMilestone(?Milestone $previousMilestone): ?PreviousMilestone
    {
        if (!$previousMilestone instanceof Milestone) {
            return null;
        }

        $previousContext = $previousMilestone->getContext();
        assert($previousContext instanceof CumulativeMovingTimeContext);

        return PreviousMilestone::create(
            previousMilestoneId: $previousMilestone->getId(),
            threshold: $previousContext->getThreshold(),
            achievedOn: $previousMilestone->getAchievedOn(),
        );
    }
}
