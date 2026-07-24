<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\ActivityCountContext;
use App\Domain\Milestone\FunComparison\ActivityCountFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\SimpleUnit;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class ActivityCountMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    private const array THRESHOLDS = [
        10, 25, 50, 100, 250, 500, 750, 1_000,
        1_500, 2_000, 2_500, 3_000, 4_000, 5_000,
        7_500, 10_000,
    ];

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT startDateTime, sportType
             FROM Activity
             ORDER BY startDateTime ASC'
        )->fetchAllAssociative();

        $milestones = [];
        $globalCount = 0;
        $globalThresholdIndex = 0;
        /** @var ?Milestone $globalPreviousMilestone */
        $globalPreviousMilestone = null;

        /** @var array<string, int> $sportCounts */
        $sportCounts = [];
        /** @var array<string, int> $sportThresholdIndices */
        $sportThresholdIndices = [];
        /** @var array<string, ?Milestone> $sportPreviousMilestones */
        $sportPreviousMilestones = [];

        foreach ($rows as $row) {
            $sportType = SportType::from($row['sportType']);
            $sportTypeValue = $row['sportType'];
            $achievedOn = SerializableDateTime::fromString($row['startDateTime']);

            ++$globalCount;
            while ($globalThresholdIndex < count(self::THRESHOLDS) && $globalCount >= self::THRESHOLDS[$globalThresholdIndex]) {
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

            if (!isset($sportCounts[$sportTypeValue])) {
                $sportCounts[$sportTypeValue] = 0;
                $sportThresholdIndices[$sportTypeValue] = 0;
                $sportPreviousMilestones[$sportTypeValue] = null;
            }
            ++$sportCounts[$sportTypeValue];

            while ($sportThresholdIndices[$sportTypeValue] < count(self::THRESHOLDS) && $sportCounts[$sportTypeValue] >= self::THRESHOLDS[$sportThresholdIndices[$sportTypeValue]]) {
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
        return Milestone::create(
            id: $this->milestoneIdFactory->random(),
            achievedOn: $achievedOn,
            category: MilestoneCategory::ACTIVITY_COUNT,
            context: new ActivityCountContext(
                threshold: $threshold,
            ),
        )
            ->withSportType($sportType)
            ->withPrevious($this->buildPreviousMilestone($previousMilestone))
            ->withFunComparison(ActivityCountFunComparison::resolve($threshold));
    }

    private function buildPreviousMilestone(?Milestone $previousMilestone): ?PreviousMilestone
    {
        if (!$previousMilestone instanceof Milestone) {
            return null;
        }

        $previousContext = $previousMilestone->getContext();
        assert($previousContext instanceof ActivityCountContext);

        return PreviousMilestone::create(
            previousMilestoneId: $previousMilestone->getId(),
            threshold: SimpleUnit::from($previousContext->getThreshold()),
            achievedOn: $previousMilestone->getAchievedOn(),
        );
    }
}
