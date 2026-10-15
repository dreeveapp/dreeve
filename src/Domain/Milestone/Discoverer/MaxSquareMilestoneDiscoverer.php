<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\Route\ExplorerTile\ExplorerTileHistory;
use App\Domain\Milestone\Context\MaxSquareContext;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\SimpleUnit;

final readonly class MaxSquareMilestoneDiscoverer implements MilestoneDiscoverer
{
    private const int MIN_SIZE = 3;

    public function __construct(
        private ExplorerTileHistory $explorerTileHistory,
    ) {
    }

    public function discover(MilestoneIdFactory $milestoneIdFactory): Milestones
    {
        $milestones = [];
        $previousMilestone = null;
        $previousSize = self::MIN_SIZE - 1;

        foreach ($this->explorerTileHistory->calculate() as $entry) {
            $size = $entry->getMaxSquareSize();
            if ($size <= $previousSize) {
                continue;
            }

            $milestone = Milestone::create(
                id: $milestoneIdFactory->next(),
                achievedOn: $entry->getStartDateTime(),
                category: MilestoneCategory::EXPLORER_TILES_MAX_SQUARE,
                context: new MaxSquareContext(size: $size),
            )
                ->withActivityId($entry->getActivityId())
                ->withPrevious($previousMilestone ? PreviousMilestone::create(
                    previousMilestoneId: $previousMilestone->getId(),
                    threshold: SimpleUnit::from($previousSize),
                    achievedOn: $previousMilestone->getAchievedOn(),
                ) : null);

            $milestones[] = $milestone;
            $previousMilestone = $milestone;
            $previousSize = $size;
        }

        return Milestones::fromArray($milestones);
    }
}
