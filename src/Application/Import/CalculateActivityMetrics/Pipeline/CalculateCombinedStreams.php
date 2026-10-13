<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\Stream\CombinedStream\ActivityStreamCombiner;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Console\ProgressIndicator;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 40)]
#[WithMutex(lockName: LockName::IMPORT_DATA)]
final readonly class CalculateCombinedStreams implements CalculateActivityMetricsStep
{
    public function __construct(
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private ActivityStreamCombiner $activityStreamCombiner,
        private SettingsRepository $settingsRepository,
        private Mutex $mutex,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Calculated combined activity streams for 0 activities');

        $activityIdsThatNeedCombining = $this->combinedActivityStreamRepository->findActivityIdsThatNeedStreamCombining(
            $unitSystem
        );
        $activityWithCombinedStreamCalculatedCount = 0;
        foreach ($activityIdsThatNeedCombining as $activityId) {
            if (!$this->activityStreamCombiner->combineFor($activityId, $unitSystem)) {
                continue; // @codeCoverageIgnore
            }

            ++$activityWithCombinedStreamCalculatedCount;
            $progressIndicator->updateMessage(sprintf(
                '=> Calculated combined activity streams for %d activities',
                $activityWithCombinedStreamCalculatedCount
            ));
            $this->mutex->heartbeat();
        }

        $progressIndicator->finish(sprintf(
            '=> Calculated combined activity streams for %d activities',
            $activityWithCombinedStreamCalculatedCount
        ));
    }
}
