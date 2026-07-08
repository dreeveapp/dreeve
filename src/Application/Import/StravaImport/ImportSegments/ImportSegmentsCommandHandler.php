<?php

declare(strict_types=1);

namespace App\Application\Import\StravaImport\ImportSegments;

use App\Domain\Segment\SegmentRepository;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\RateLimit\StravaRateLimitHasBeenReached;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

#[WithMutex(lockName: LockName::IMPORT_DATA_OR_BUILD_APP)]
final readonly class ImportSegmentsCommandHandler implements CommandHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private SettingsRepository $settingsRepository,
        private Strava $strava,
        private Mutex $mutex,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ImportSegments);

        if (!$this->settingsRepository->import()->getOptInToSegmentDetailsImport()->hasOptedIn()) {
            return;
        }
        $this->strava->setConsoleOutput($command->getOutput());

        $command->getOutput()->writeln('Importing segments...');

        $segmentIdsMissingDetails = $this->segmentRepository->findSegmentsIdsMissingDetails();
        $numberOfSegmentIdsMissingDetails = count($segmentIdsMissingDetails);
        $delta = 1;
        foreach ($segmentIdsMissingDetails as $segmentId) {
            $segment = $this->segmentRepository->find($segmentId);
            try {
                $stravaSegment = $this->strava->getSegment($segmentId);
                $this->mutex->heartbeat();

                $segment = $segment
                    ->withPolyline(
                        EncodedPolyline::fromOptionalString(
                            $stravaSegment['map']['polyline'] ?? null
                        ))
                    ->flagDetailsAsImported();
                $this->segmentRepository->update($segment);

                $command->getOutput()->writeln(
                    sprintf(
                        '  => [%d/%d] Imported segment details: "%s"',
                        $delta,
                        $numberOfSegmentIdsMissingDetails,
                        $segment->getName()
                    )
                );

                ++$delta;
            } catch (StravaRateLimitHasBeenReached $exception) {
                $command->getOutput()->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

                return;
            } catch (ClientException|RequestException $exception) {
                if (404 === $exception->getResponse()?->getStatusCode()) {
                    // Segment does not exist anymore. Mark as imported.
                    $this->segmentRepository->update($segment->flagDetailsAsImported());
                }

                $command->getOutput()->writeln(sprintf('<error>Strava API threw error: %s</error>', $exception->getMessage()));
                continue;
            }
        }
    }
}
