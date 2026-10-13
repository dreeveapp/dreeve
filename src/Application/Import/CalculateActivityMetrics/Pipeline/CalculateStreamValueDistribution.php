<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamValueDistributionCalculator;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Console\ProgressIndicator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 10)]
final readonly class CalculateStreamValueDistribution implements CalculateActivityMetricsStep
{
    public function __construct(
        private ActivityStreamMetricRepository $activityStreamMetricRepository,
        private ActivityStreamValueDistributionCalculator $activityStreamValueDistributionCalculator,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Calculated value distribution for 0 streams');

        $countCalculatedStreams = 0;
        $activityIdsToProcess = $this->activityStreamMetricRepository->findActivityIdsWithoutDistributionValues();
        foreach ($activityIdsToProcess as $activityId) {
            $countCalculatedStreams += $this->activityStreamValueDistributionCalculator->calculateFor($activityId, $unitSystem);
            $progressIndicator->updateMessage(sprintf('=> Calculated value distribution for %d streams', $countCalculatedStreams));
        }

        $progressIndicator->finish(sprintf('=> Calculated value distribution for %d streams', $countCalculatedStreams));
    }
}
