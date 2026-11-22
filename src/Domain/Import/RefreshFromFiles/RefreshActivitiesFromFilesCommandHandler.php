<?php

declare(strict_types=1);

namespace App\Domain\Import\RefreshFromFiles;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\FileImportRepository;
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
        private FileImportRepository $fileImportRepository,
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
        $countTotal = $this->fileImportRepository->countWithFileContents();
        $countRefreshed = 0;
        $delta = 0;

        foreach ($this->storedActivityFiles->parse(static function (string $failure) use ($output, $countTotal, &$delta): void {
            ++$delta;
            $output->writeln(sprintf('  => <error>[%d/%d] Could not refresh %s</error>', $delta, $countTotal, $failure));
        }) as $activityId => $parsedActivityFile) {
            $this->mutex->heartbeat();
            ++$delta;

            try {
                $activityWithRawData = $this->activityRepository->findWithRawData($activityId);
            } catch (EntityNotFound) {
                $output->writeln(sprintf('  => [%d/%d] Skipping %s, the activity no longer exists', $delta, $countTotal, $activityId));

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
            $output->writeln(sprintf('  => [%d/%d] Refreshed activity "%s"', $delta, $countTotal, $activity->getName()));
        }

        $this->activityFieldsToRefresh->clear();

        $output->writeln(sprintf('  => Refreshed %d activities from stored files', $countRefreshed));
    }
}
