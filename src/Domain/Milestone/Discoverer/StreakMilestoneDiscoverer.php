<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Milestone\Context\StreakContext;
use App\Domain\Milestone\FunComparison\StreakFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\SimpleUnit;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class StreakMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    private const array THRESHOLDS = [
        7, 14, 21, 30, 45, 60, 90, 100,
        120, 150, 180, 250, 365, 500, 730,
    ];

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT DISTINCT DATE(startDateTime) as activityDate
             FROM Activity
             ORDER BY activityDate ASC'
        )->fetchAllAssociative();

        $milestones = [];
        $streakDays = 0;
        $longestStreakReached = 0;
        $previousDate = null;
        $thresholdIndex = 0;
        /** @var ?Milestone $previousMilestone */
        $previousMilestone = null;

        foreach ($rows as $row) {
            $currentDate = SerializableDateTime::fromString($row['activityDate']);
            if (!$previousDate instanceof SerializableDateTime) {
                $streakDays = 1;
            } else {
                $diff = (int) $previousDate->diff($currentDate)->days;
                if (1 === $diff) {
                    ++$streakDays;
                } else {
                    $streakDays = 1;
                    $thresholdIndex = $this->findThresholdIndex($longestStreakReached);
                }
            }

            if ($streakDays > $longestStreakReached) {
                $longestStreakReached = $streakDays;
            }

            while ($thresholdIndex < count(self::THRESHOLDS) && $streakDays >= self::THRESHOLDS[$thresholdIndex]) {
                $threshold = self::THRESHOLDS[$thresholdIndex];
                $achievedOn = SerializableDateTime::fromString($row['activityDate']);

                $previous = null;
                if ($previousMilestone) {
                    $previousContext = $previousMilestone->getContext();
                    assert($previousContext instanceof StreakContext);
                    $previous = PreviousMilestone::create(
                        previousMilestoneId: $previousMilestone->getId(),
                        threshold: SimpleUnit::from($previousContext->getDays()),
                        achievedOn: $previousMilestone->getAchievedOn(),
                    );
                }

                $milestone = Milestone::create(
                    id: $this->milestoneIdFactory->random(),
                    achievedOn: $achievedOn,
                    category: MilestoneCategory::STREAK,
                    context: new StreakContext(
                        days: $threshold,
                    ),
                )
                    ->withPrevious($previous)
                    ->withFunComparison(StreakFunComparison::resolve($threshold));

                $milestones[] = $milestone;
                $previousMilestone = $milestone;
                ++$thresholdIndex;
            }

            $previousDate = $currentDate;
        }

        return Milestones::fromArray($milestones);
    }

    private function findThresholdIndex(int $longestStreakReached): int
    {
        foreach (self::THRESHOLDS as $index => $threshold) {
            if ($threshold > $longestStreakReached) {
                return $index;
            }
        }

        return count(self::THRESHOLDS);
    }
}
