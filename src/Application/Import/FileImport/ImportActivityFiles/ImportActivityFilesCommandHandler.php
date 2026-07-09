<?php

declare(strict_types=1);

namespace App\Application\Import\FileImport\ImportActivityFiles;

use App\Application\Import\FileImport\ImportActivityFiles\Pipeline\ActivityImportContext;
use App\Application\Import\FileImport\ImportActivityFiles\Pipeline\ImportActivityFileStep;
use App\Application\Import\FileImport\ImportActivityFiles\Pipeline\SkipDuplicateActivity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Import\FileImport;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Domain\Import\FileParser\CouldNotParseActivityFile;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Domain\Import\FileParser\UnsupportedFileType;
use App\Domain\Import\WatchDirectory;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[WithMutex(lockName: LockName::IMPORT_DATA_OR_BUILD_APP)]
final readonly class ImportActivityFilesCommandHandler implements CommandHandler
{
    /**
     * @param iterable<ImportActivityFileStep> $steps
     */
    public function __construct(
        private WatchDirectory $watchDirectory,
        #[AutowireIterator('app.activity_import_file.pipeline_step')]
        private iterable $steps,
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityLapRepository $activityLapRepository,
        private FileImportRepository $fileImportRepository,
        private Mutex $mutex,
        private Clock $clock,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ImportActivityFiles);
        $output = $command->getOutput();
        $output->writeln('Importing activity files...');

        if (!$this->watchDirectory->exists()) {
            $output->writeln('  => No "watch" directory found, nothing to import');

            return;
        }

        $countImported = 0;
        $countSkipped = 0;
        $countFailed = 0;

        $files = $this->watchDirectory->listFiles()->toArray();
        $countTotalFilesInWatchDirectory = count($files);
        $delta = 0;
        foreach ($files as $item) {
            $this->mutex->heartbeat();
            ++$delta;
            $filePath = $this->watchDirectory->getAbsolutePathFor($item);
            $context = ActivityImportContext::create($filePath);

            try {
                foreach ($this->steps as $step) {
                    $context = $step->process($context);
                }
            } catch (SkipDuplicateActivity) {
                $this->watchDirectory->deleteFile($filePath);
                $this->fileImportRepository->add(FileImport::create(
                    fileImportId: FileImportId::random(),
                    originalFilename: $context->getFilePath()->getFilename(),
                    source: $context->getImportSource(),
                    status: FileImportStatus::SKIPPED,
                    errorMessage: 'Skipped, activity was already imported',
                    activityId: null,
                    importedOn: $this->clock->getCurrentDateTimeImmutable(),
                ));

                $output->writeln(sprintf('  => [%d/%d] Skipping "%s", activity was already imported', $delta, $countTotalFilesInWatchDirectory, $filePath->getFilename()));
                ++$countSkipped;
                continue;
            } catch (UnsupportedFileType) {
                $output->writeln(sprintf('  => [%d/%d] Skipping "%s", unsupported file type', $delta, $countTotalFilesInWatchDirectory, $filePath->getFilename()));
                ++$countSkipped;
                continue;
            } catch (CouldNotParseActivityFile $e) {
                $this->fileImportRepository->add(FileImport::createFromRawFile(
                    fileImportId: FileImportId::random(),
                    file: $e->getActivityFile(),
                    source: $context->getImportSource(),
                    status: FileImportStatus::FAILED,
                    errorMessage: $e->getMessage(),
                    activityId: null,
                    importedOn: $this->clock->getCurrentDateTimeImmutable(),
                ));
                $this->watchDirectory->deleteFile($filePath);

                $output->writeln(sprintf('  => <error>[%d/%d] Could not import "%s": %s</error>', $delta, $countTotalFilesInWatchDirectory, $filePath->getFilename(), $e->getMessage()));
                ++$countFailed;
                continue;
            }

            $activity = $context->getActivity() ?? throw new \RuntimeException('Activity not set on $context');
            $activityId = $activity->getId();

            $this->activityRepository->add(ActivityWithRawData::fromState(
                activity: $activity,
                rawData: [],
            ));

            foreach ($context->getStreams() as $stream) {
                $this->activityStreamRepository->add($stream);
            }
            $this->activityRepository->markActivityStreamsAsImported($activityId);

            foreach ($context->getLaps() as $lap) {
                $this->activityLapRepository->add($lap);
            }

            $file = $context->getFile();
            assert($file instanceof RawActivityFile);

            $this->fileImportRepository->add(FileImport::createFromRawFile(
                fileImportId: FileImportId::random(),
                file: $file,
                source: $activity->getImportSource(),
                status: FileImportStatus::SUCCESS,
                errorMessage: null,
                activityId: $activityId,
                importedOn: $this->clock->getCurrentDateTimeImmutable(),
            ));

            $this->watchDirectory->deleteFile($filePath);

            $output->writeln(sprintf(
                '  => [%d/%d] Imported "%s" as activity "%s - %s"',
                $delta,
                $countTotalFilesInWatchDirectory,
                $filePath->getFilename(),
                $activity->getName(),
                $activity->getStartDate()->format('d-m-Y'),
            ));
            ++$countImported;
        }

        $output->writeln(sprintf(
            '  => Imported %d, skipped %d, failed %d activity file(s)',
            $countImported,
            $countSkipped,
            $countFailed,
        ));
    }
}
