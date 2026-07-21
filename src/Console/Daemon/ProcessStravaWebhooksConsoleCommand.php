<?php

declare(strict_types=1);

namespace App\Console\Daemon;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Import\ImportMode;
use App\Domain\Strava\Webhook\WebhookAspectType;
use App\Domain\Strava\Webhook\WebhookEventRepository;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\Mutex\LockIsAlreadyAcquired;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[WithMutex(lockName: LockName::IMPORT_DATA_OR_BUILD_APP)]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: ProcessStravaWebhooksConsoleCommand::NAME, description: 'Process webhooks')]
final class ProcessStravaWebhooksConsoleCommand extends Command
{
    public const string NAME = 'app:cron:process-webhooks';

    public function __construct(
        private readonly WebhookEventRepository $webhookEventRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly Mutex $mutex,
        private readonly ImportMode $importMode,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->importMode->isStravaApi()) {
            return Command::SUCCESS;
        }

        try {
            $this->mutex->acquireLock('runStravaImportAndBuildApp');
            $this->mutex->releaseLock();
        } catch (LockIsAlreadyAcquired) {
            // Another process is importing data, postpone import.
            $output->writeln('<comment>Postponing Strava import, another process is importing data.</comment>');

            return Command::SUCCESS;
        }

        $application = $this->getApplication();
        assert($application instanceof Application);

        if (!$webhookEvents = $this->webhookEventRepository->grab()) {
            // No webhooks to process.
            $output->writeln('No webhook events left to process...');

            return Command::SUCCESS;
        }

        $activityIdsToDelete = ActivityIds::empty();
        $createOrUpdateActivityIds = ActivityIds::empty();
        foreach ($webhookEvents as $webhookEvent) {
            $activityId = ActivityId::fromUnprefixed($webhookEvent->getObjectId());
            if (WebhookAspectType::DELETE === $webhookEvent->getAspectType()) {
                $activityIdsToDelete->add($activityId);
            } else {
                $createOrUpdateActivityIds->add($activityId);
            }
        }

        if (!$activityIdsToDelete->isEmpty()) {
            $this->activityRepository->markActivitiesForDeletion($activityIdsToDelete);
        }

        $input = new ArrayInput([
            'command' => RunStravaImportAndBuildAppConsoleCommand::NAME,
            RunStravaImportAndBuildAppConsoleCommand::RESTRICT_TO_ACTIVITY_IDS_ARGUMENT => implode(',', array_map(strval(...), $createOrUpdateActivityIds->toArray())),
            '--'.RunStravaImportAndBuildAppConsoleCommand::IMPORT_OPTION => true,
            '--'.RunStravaImportAndBuildAppConsoleCommand::BUILD_OPTION => true,
        ]);
        $input->setInteractive(false);

        return $application->doRun($input, $output);
    }
}
