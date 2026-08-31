<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\Split\ActivitySplitCalculator;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Console\ProgressIndicator;
use App\Infrastructure\Measurement\UnitSystem;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 35)]
final readonly class CalculateActivitySplits implements CalculateActivityMetricsStep
{
    public function __construct(
        private ActivitySplitRepository $activitySplitRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivitySplitCalculator $activitySplitCalculator,
        private RenderCache $renderCache,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Calculated splits for 0 activities');

        $cacheTags = [];
        foreach ($this->activitySplitRepository->findActivityIdsThatNeedSplitCalculation() as $activityId) {
            $streams = $this->activityStreamRepository->findByActivityId($activityId);

            $splitsCalculated = false;
            foreach (UnitSystem::cases() as $unitSystem) {
                foreach ($this->activitySplitCalculator->calculate($streams, $activityId, $unitSystem) as $split) {
                    $this->activitySplitRepository->add($split);
                    $splitsCalculated = true;
                }
            }

            if (!$splitsCalculated) {
                continue;
            }

            $cacheTags[] = ActivityCacheTag::for($activityId);
            $progressIndicator->updateMessage(sprintf('=> Calculated splits for %d activities', count($cacheTags)));
        }

        $this->renderCache->invalidateTags(...$cacheTags);
        $progressIndicator->finish(sprintf('=> Calculated splits for %d activities', count($cacheTags)));
    }
}
