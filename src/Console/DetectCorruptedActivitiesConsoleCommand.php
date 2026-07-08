<?php

namespace App\Console;

use App\Application\Import\StravaImport\DeleteActivitiesMarkedForDeletion\DeleteActivitiesMarkedForDeletion;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivitySummaryRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Webhook\WebhookAspectType;
use App\Domain\Strava\Webhook\WebhookEvent;
use App\Domain\Strava\Webhook\WebhookEventRepository;
use App\Infrastructure\Console\ProvideConsoleIntro;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMutex(lockName: LockName::IMPORT_DATA_OR_BUILD_APP)]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: 'app:data:detect-corrupted-activities', description: 'Checks for corrupted activities and deletes them')]
class DetectCorruptedActivitiesConsoleCommand extends Command
{
    use ProvideConsoleIntro;

    public function __construct(
        private readonly ActivityIdRepository $activityIdRepository,
        private readonly ActivitySummaryRepository $activitySummaryRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityStreamRepository $activityStreamRepository,
        private readonly CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private readonly WebhookEventRepository $webhookEventRepository,
        private readonly CommandBus $commandBus,
        private readonly SettingsRepository $settingsRepository,
        private readonly Mutex $mutex,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $this->mutex->acquireLock('DetectCorruptedActivitiesConsoleCommand');

        $this->outputConsoleIntro($output);

        $progressIndicator = new ProgressIndicator(
            output: $output,
            indicatorChangeInterval: 100,
            indicatorValues: ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']
        );
        $progressIndicator->start('Scanning activities...');

        $activityIds = $this->activityIdRepository->findAll();
        $activityIdsToDelete = ActivityIds::empty();
        foreach ($activityIds as $activityId) {
            $progressIndicator->advance();
            try {
                $this->activityRepository->findWithRawData($activityId);
            } catch (\JsonException) {
                $activityIdsToDelete->add($activityId);
                continue;
            }

            try {
                $this->activityStreamRepository->findByActivityId($activityId);
            } catch (\JsonException) {
                $activityIdsToDelete->add($activityId);
                continue;
            }

            try {
                $this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
                    activityId: $activityId,
                    unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
                );
            } catch (EntityNotFound) {
            } catch (\JsonException) {
                $activityIdsToDelete->add($activityId);
                continue;
            }
        }

        if ($activityIdsToDelete->isEmpty()) {
            $progressIndicator->finish('No activities with corrupted data found');

            return Command::SUCCESS;
        }

        $progressIndicator->finish(sprintf('Found %d activities with corrupted data', count($activityIdsToDelete)));
        $output->newLine();
        $output->listing(array_map(function (ActivityId $activityId): string {
            $activity = $this->activitySummaryRepository->find($activityId);

            return sprintf(
                'Activity "%s - %s"',
                $activity->getName(),
                $activity->getStartDate()->format('d-m-Y')
            );
        }, $activityIdsToDelete->toArray()));

        if (!$output->confirm('Do you want to delete these activities so they can be re-imported in the next run?')) {
            return Command::SUCCESS;
        }

        $this->activityRepository->markActivitiesForDeletion($activityIdsToDelete);
        $this->commandBus->dispatch(new DeleteActivitiesMarkedForDeletion($output));

        if (!$this->settingsRepository->import()->getWebhookConfig()->isEnabled()) {
            return Command::SUCCESS;
        }

        foreach ($activityIdsToDelete as $activityId) {
            // Add activities to the WebhookEvent table so they get imported through the webhook flow.
            $this->webhookEventRepository->add(WebhookEvent::create(
                objectId: $activityId->toUnprefixedString(),
                objectType: 'activity',
                aspectType: WebhookAspectType::CREATE,
                payload: [],
            ));
        }

        return Command::SUCCESS;
    }
}
