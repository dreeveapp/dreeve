<?php

namespace App\Application\Import\StravaImport\ImportActivities\Pipeline;

use App\Domain\Activity\Activity;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Media\NewMediaItem;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\Path;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Uri\Rfc3986\Uri;

#[AsTaggedItem(priority: 10)]
final readonly class DownloadActivityImages implements ActivityImportStep
{
    public function __construct(
        private Strava $strava,
        private MediaItemStorage $mediaItemStorage,
        private MediaItemRepository $mediaItemRepository,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function process(ActivityImportContext $context): ActivityImportContext
    {
        $activity = $context->getActivity() ?? throw new \RuntimeException('Activity not set on $context');
        $rawStravaData = $context->getRawStravaData();

        if (!$totalImageCount = ($rawStravaData['total_photo_count'] ?? 0)) {
            return $context->withActivity($activity->withMediaItemIds(MediaItemIds::empty()));
        }

        $shouldDownloadImages = !$this->settingsRepository->import()->shouldSkipImageDownloadDuringImport()
            && ($context->isNewActivity() || count($activity->getMediaItemIds()) !== $totalImageCount);
        if (!$shouldDownloadImages) {
            return $context->withActivity($context->isNewActivity() ? $activity->withMediaItemIds(MediaItemIds::empty()) : $activity);
        }

        $mediaItemIds = MediaItemIds::empty();
        try {
            $photos = $this->strava->getActivityPhotos($activity->getId());
            foreach ($photos as $photo) {
                if (empty($photo['urls'][5000])) {
                    continue;
                }

                $uri = new Uri($photo['urls'][5000]);
                $mediaItem = $this->mediaItemStorage->store(
                    newMediaItem: new NewMediaItem(
                        filename: Path::fromString(basename($uri->getPath())),
                        content: $this->strava->downloadImage($photo['urls'][5000]),
                    ),
                    directory: MediaItemDirectory::ACTIVITIES,
                );
                $mediaItemIds->add($mediaItem->getId());

                if (is_array($photo['location'] ?? null) && 2 === count($photo['location'])) {
                    try {
                        $mediaItem = $mediaItem->withCoordinate(Coordinate::createFromLatAndLng(
                            latitude: Latitude::fromString((string) $photo['location'][0]),
                            longitude: Longitude::fromString((string) $photo['location'][1]),
                        ));
                    } catch (\InvalidArgumentException) {
                    }
                }
                if (is_string($photo['created_at_local'] ?? null)) {
                    try {
                        $mediaItem = $mediaItem->withTakenOn(SerializableDateTime::createFromFormat(
                            format: Activity::DATE_TIME_FORMAT,
                            datetime: $photo['created_at_local'],
                        ));
                    } catch (\InvalidArgumentException) {
                    }
                }
                $this->mediaItemRepository->update($mediaItem);
            }

            $activity = $activity->withMediaItemIds($mediaItemIds);
        } catch (\Throwable $exception) {
            foreach ($mediaItemIds as $mediaItemId) {
                $this->mediaItemStorage->remove($mediaItemId);
            }

            if (!$exception instanceof RequestException) {
                throw $exception;
            }
        }

        return $context->withActivity($activity);
    }
}
