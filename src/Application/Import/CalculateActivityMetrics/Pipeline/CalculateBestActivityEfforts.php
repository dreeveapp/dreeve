<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\BestEffort\ActivityBestEffortCalculator;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Infrastructure\Console\ProgressIndicator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 60)]
final readonly class CalculateBestActivityEfforts implements CalculateActivityMetricsStep
{
    public function __construct(
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private ActivityBestEffortCalculator $activityBestEffortCalculator,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Calculated best efforts for 0 activities');

        $activityIdsWithoutBestEfforts = $this->activityBestEffortRepository->findActivityIdsThatNeedBestEffortsCalculation();

        $activityWithBestEffortsCalculatedCount = 0;
        foreach ($activityIdsWithoutBestEfforts as $activityId) {
            if ($this->activityBestEffortCalculator->calculateFor($activityId)) {
                ++$activityWithBestEffortsCalculatedCount;
                $progressIndicator->updateMessage(sprintf(
                    '=> Calculated best efforts for %d activities',
                    $activityWithBestEffortsCalculatedCount
                ));
            }
        }

        $progressIndicator->finish(sprintf(
            '=> Calculated best efforts for %d activities',
            $activityWithBestEffortsCalculatedCount
        ));
    }
}
