<?php

namespace App\Application\Import\StravaImport\ImportActivities\Pipeline;

use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\Stream\ActivityStream;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\ActivityStreams;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Strava\Strava;
use App\Infrastructure\Time\Clock\Clock;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 90)]
final readonly class FetchActivityStreams implements ActivityImportStep
{
    public function __construct(
        private ActivityScanRepository $activityScanRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private Strava $strava,
        private Clock $clock,
    ) {
    }

    public function process(ActivityImportContext $context): ActivityImportContext
    {
        $activityId = $context->getActivityId();

        if (!$context->isNewActivity() && $this->activityScanRepository->isScanned(activityId: $activityId, type: ActivityScanType::STREAMS)) {
            return $context;
        }

        try {
            $stravaStreams = $this->strava->getAllActivityStreams($activityId);
        } catch (ClientException|RequestException $exception) {
            if (404 === $exception->getResponse()?->getStatusCode()) {
                // Streams do not exist for this activity.
                return $context->withStreamsWereImported();
            }

            throw $exception;
        }

        $streams = ActivityStreams::empty();
        foreach ($stravaStreams as $stravaStream) {
            if (!$streamType = StreamType::tryFrom($stravaStream['type'])) {
                continue;
            }

            if ($this->activityStreamRepository->hasOneForActivityAndStreamType($activityId, $streamType)) {
                continue;
            }

            $streams->add(ActivityStream::create(
                activityId: $activityId,
                streamType: $streamType,
                streamData: $stravaStream['data'],
                createdOn: $this->clock->getCurrentDateTimeImmutable(),
            ));
        }

        return $context
            ->withStreams($streams)
            ->withStreamsWereImported();
    }
}
