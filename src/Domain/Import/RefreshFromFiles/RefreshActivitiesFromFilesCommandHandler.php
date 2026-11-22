<?php

declare(strict_types=1);

namespace App\Domain\Import\RefreshFromFiles;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\StoredActivityFiles;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;

#[WithMutex(lockName: LockName::IMPORT_DATA)]
final readonly class RefreshActivitiesFromFilesCommandHandler implements CommandHandler
{
    public function __construct(
        private StoredActivityFiles $storedActivityFiles,
        private ActivityRepository $activityRepository,
        private ActivityFieldsToRefresh $activityFieldsToRefresh,
        private Mutex $mutex,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof RefreshActivitiesFromFiles);

        $output = $command->getOutput();
        $output->writeln('Refreshing activities from stored files...');

        $fields = $this->activityFieldsToRefresh->find();
        $countRefreshed = 0;

        foreach ($this->storedActivityFiles->parse(static function (string $failure) use ($output): void {
            $output->writeln(sprintf('  => <error>Could not refresh %s</error>', $failure));
        }) as $activityId => $parsedActivityFile) {
            $this->mutex->heartbeat();

            try {
                $activityWithRawData = $this->activityRepository->findWithRawData($activityId);
            } catch (EntityNotFound) {
                continue;
            }

            $activity = $activityWithRawData->getActivity();
            foreach ($fields as $field) {
                $activity = match ($field) {
                    RefreshableActivityField::DESCENT => $activity->withDescent($parsedActivityFile->getActivity()->getDescent()),
                };
            }

            $this->activityRepository->update(ActivityWithRawData::fromState(
                activity: $activity,
                rawData: $activityWithRawData->getRawData(),
            ));
            ++$countRefreshed;
            $output->writeln(sprintf(
                '  => Refreshed activity "%s - %s"',
                $activity->getName(),
                $activity->getStartDate()->format('d-m-Y'),
            ));
        }

        $this->activityFieldsToRefresh->clear();

        $output->writeln(sprintf('  => Refreshed %d activities from stored files', $countRefreshed));
    }
}
