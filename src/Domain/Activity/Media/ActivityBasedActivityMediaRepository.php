<?php

namespace App\Domain\Activity\Media;

use App\Domain\Activity\Activities;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Image\ImagePath;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\String\Path;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class ActivityBasedActivityMediaRepository implements ActivityMediaRepository
{
    public function __construct(
        private Connection $connection,
        private ActivityRepository $activityRepository,
        #[Target('file.storage')]
        private FilesystemOperator $fileStorage,
        private SettingsRepository $settingsRepository,
        private MediaItemRepository $mediaItemRepository,
    ) {
    }

    public function findAll(): ActivityMediaItems
    {
        return $this->buildMediaItemsFor($this->activityRepository->findAll());
    }

    public function findByActivityIds(ActivityIds $activityIds): ActivityMediaItems
    {
        return $this->buildMediaItemsFor($this->activityRepository->findByIds($activityIds));
    }

    public function findRandomFor(SportTypes $sportTypes, Years $years): ActivityMediaItem
    {
        $activities = $this->activityRepository->findAll()->toArray();
        shuffle($activities);

        foreach ($activities as $activity) {
            if (!$years->has(Year::fromInt($activity->getStartDate()->getYear()))) {
                continue;
            }

            if (!$sportTypes->has($activity->getSportType())) {
                continue;
            }

            $mediaItems = $this->mediaItemRepository->findByIds(MediaItemIds::fromArray(array_map(
                static fn (string $localImagePath): MediaItemId => MediaItemId::fromUnprefixed(Path::fromString($localImagePath)->getFilenameWithoutExtension()),
                $activity->getLocalImagePaths(),
            )))->toArray();
            if (!$mediaItems) {
                continue;
            }

            return ActivityMediaItem::create(
                mediaItem: $mediaItems[array_rand($mediaItems)],
                activity: $activity,
            );
        }

        throw new EntityNotFound('Random image not found');
    }

    public function count(): int
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('SUM(totalImageCount)')
            ->from('Activity');

        $hiddenSportTypes = $this->settingsRepository->appearance()->getHidePhotosForSportTypes();
        if (!$hiddenSportTypes->isEmpty()) {
            $queryBuilder->andWhere('sportType NOT IN (:hiddenSportTypes)')
                ->setParameter(
                    'hiddenSportTypes',
                    $hiddenSportTypes->toValues(),
                    ArrayParameterType::STRING
                );
        }

        return (int) $queryBuilder->executeQuery()->fetchOne();
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        try {
            $activity = $this->activityRepository->find($activityId);
        } catch (EntityNotFound) {
            return;
        }

        foreach ($activity->getLocalImagePaths() as $localImagePath) {
            $fileSystemPath = ImagePath::fromLocalImagePath($localImagePath)->toFileSystemPath();
            if ($this->fileStorage->fileExists($fileSystemPath)) {
                $this->fileStorage->delete($fileSystemPath);
            }
        }
    }

    private function buildMediaItemsFor(Activities $activities): ActivityMediaItems
    {
        $hiddenSportTypes = $this->settingsRepository->appearance()->getHidePhotosForSportTypes();
        $mediaItemIdsPerActivity = [];
        foreach ($activities as $activity) {
            if (0 === $activity->getTotalImageCount()) {
                continue;
            }

            if ($hiddenSportTypes->has($activity->getSportType())) {
                continue;
            }

            $mediaItemIdsPerActivity[(string) $activity->getId()] = array_map(
                static fn (string $localImagePath): MediaItemId => MediaItemId::fromUnprefixed(Path::fromString($localImagePath)->getFilenameWithoutExtension()),
                $activity->getLocalImagePaths(),
            );
        }

        $mediaItemsById = [];
        foreach ($this->mediaItemRepository->findByIds(MediaItemIds::fromArray(array_merge(...array_values($mediaItemIdsPerActivity)))) as $mediaItem) {
            $mediaItemsById[(string) $mediaItem->getId()] = $mediaItem;
        }

        $activityMediaItems = ActivityMediaItems::empty();
        foreach ($activities as $activity) {
            foreach ($mediaItemIdsPerActivity[(string) $activity->getId()] ?? [] as $mediaItemId) {
                if (!$mediaItem = $mediaItemsById[(string) $mediaItemId] ?? null) {
                    continue;
                }

                $activityMediaItems->add(ActivityMediaItem::create(
                    mediaItem: $mediaItem,
                    activity: $activity,
                ));
            }
        }

        return $activityMediaItems;
    }
}
