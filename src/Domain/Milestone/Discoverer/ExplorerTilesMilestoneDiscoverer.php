<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\Route\ExplorerTile\ExplorerTileHistory;
use App\Domain\Milestone\Context\MaxClusterContext;
use App\Domain\Milestone\Context\MaxSquareContext;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\SimpleUnit;

final readonly class ExplorerTilesMilestoneDiscoverer implements MilestoneDiscoverer
{
    private const int MIN_MAX_SQUARE_SIZE = 3;
    private const array MAX_CLUSTER_THRESHOLDS = [10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000];

    public function __construct(
        private ExplorerTileHistory $explorerTileHistory,
    ) {
    }

    public function discover(MilestoneIdFactory $milestoneIdFactory): Milestones
    {
        $milestones = [];
        $previousMaxSquareMilestone = null;
        $previousMaxSquareSize = self::MIN_MAX_SQUARE_SIZE - 1;
        $previousMaxClusterMilestone = null;
        $previousMaxClusterThreshold = 0;
        $maxClusterThresholdIndex = 0;

        foreach ($this->explorerTileHistory->calculate() as $entry) {
            $maxSquareSize = $entry->getMaxSquareSize();
            if ($maxSquareSize > $previousMaxSquareSize) {
                $milestone = Milestone::create(
                    id: $milestoneIdFactory->next(),
                    achievedOn: $entry->getStartDateTime(),
                    category: MilestoneCategory::EXPLORER_TILES_MAX_SQUARE,
                    context: new MaxSquareContext(size: $maxSquareSize),
                )
                    ->withActivityId($entry->getActivityId())
                    ->withPrevious($previousMaxSquareMilestone ? PreviousMilestone::create(
                        previousMilestoneId: $previousMaxSquareMilestone->getId(),
                        threshold: SimpleUnit::from($previousMaxSquareSize),
                        achievedOn: $previousMaxSquareMilestone->getAchievedOn(),
                    ) : null);

                $milestones[] = $milestone;
                $previousMaxSquareMilestone = $milestone;
                $previousMaxSquareSize = $maxSquareSize;
            }

            while ($maxClusterThresholdIndex < count(self::MAX_CLUSTER_THRESHOLDS)
                && $entry->getMaxClusterSize() >= self::MAX_CLUSTER_THRESHOLDS[$maxClusterThresholdIndex]) {
                $threshold = self::MAX_CLUSTER_THRESHOLDS[$maxClusterThresholdIndex];

                $milestone = Milestone::create(
                    id: $milestoneIdFactory->next(),
                    achievedOn: $entry->getStartDateTime(),
                    category: MilestoneCategory::EXPLORER_TILES_MAX_CLUSTER,
                    context: new MaxClusterContext(tileCount: $threshold),
                )
                    ->withActivityId($entry->getActivityId())
                    ->withPrevious($previousMaxClusterMilestone ? PreviousMilestone::create(
                        previousMilestoneId: $previousMaxClusterMilestone->getId(),
                        threshold: SimpleUnit::from($previousMaxClusterThreshold),
                        achievedOn: $previousMaxClusterMilestone->getAchievedOn(),
                    ) : null);

                $milestones[] = $milestone;
                $previousMaxClusterMilestone = $milestone;
                $previousMaxClusterThreshold = $threshold;
                ++$maxClusterThresholdIndex;
            }
        }

        return Milestones::fromArray($milestones);
    }
}
