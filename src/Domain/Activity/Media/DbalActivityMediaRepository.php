<?php

declare(strict_types=1);

namespace App\Domain\Activity\Media;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Time\Years;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalActivityMediaRepository extends DbalRepository implements ActivityMediaRepository
{
    public function __construct(
        Connection $connection,
        private ActivityRepository $activityRepository,
        private MediaItemRepository $mediaItemRepository,
        private MediaItemStorage $mediaItemStorage,
        private SettingsRepository $settingsRepository,
    ) {
        parent::__construct($connection);
    }

    public function findAll(): ActivityMediaItems
    {
        return $this->hydrateAll($this->createQueryBuilderForVisibleMediaItems()
            ->select('Activity.activityId', 'MediaItem.mediaItemId')
            ->orderBy('Activity.startDateTime', 'DESC')
            ->addOrderBy('Activity.activityId')
            ->addOrderBy('activityMediaItem.key')
            ->executeQuery()
            ->fetchAllAssociative()
        );
    }

    public function findByActivityIds(ActivityIds $activityIds): ActivityMediaItems
    {
        return $this->hydrateAll($this->createQueryBuilderForVisibleMediaItems()
            ->select('Activity.activityId', 'MediaItem.mediaItemId')
            ->andWhere('Activity.activityId IN (:activityIds)')
            ->setParameter(
                'activityIds',
                array_map(strval(...), $activityIds->toArray()),
                ArrayParameterType::STRING
            )
            ->orderBy('Activity.startDateTime', 'DESC')
            ->addOrderBy('Activity.activityId')
            ->addOrderBy('activityMediaItem.key')
            ->executeQuery()
            ->fetchAllAssociative()
        );
    }

    public function findRandomFor(SportTypes $sportTypes, Years $years): ActivityMediaItem
    {
        $result = $this->connection->createQueryBuilder()
            ->select('Activity.activityId', 'MediaItem.mediaItemId')
            ->from('Activity')
            ->from('json_each(Activity.mediaItemIds)', 'activityMediaItem')
            ->innerJoin('activityMediaItem', 'MediaItem', 'MediaItem', 'MediaItem.mediaItemId = activityMediaItem.value')
            ->andWhere('Activity.sportType IN (:sportTypes)')
            ->setParameter(
                'sportTypes',
                $sportTypes->toValues(),
                ArrayParameterType::STRING
            )
            ->andWhere("strftime('%Y', Activity.startDateTime) IN (:years)")
            ->setParameter(
                'years',
                array_map(strval(...), $years->toArray()),
                ArrayParameterType::STRING
            )
            ->orderBy('RANDOM()')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!$result) {
            throw new EntityNotFound('Random image not found');
        }

        return ActivityMediaItem::create(
            mediaItem: $this->mediaItemRepository->find(MediaItemId::fromString($result['mediaItemId'])),
            activity: $this->activityRepository->find(ActivityId::fromString($result['activityId'])),
        );
    }

    public function count(): int
    {
        return (int) $this->createQueryBuilderForVisibleMediaItems()
            ->select('COUNT(*)')
            ->executeQuery()
            ->fetchOne();
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $mediaItemIds = $this->connection->executeQuery(
            'SELECT MediaItem.mediaItemId FROM Activity, json_each(Activity.mediaItemIds) AS activityMediaItem
             INNER JOIN MediaItem ON MediaItem.mediaItemId = activityMediaItem.value
             WHERE Activity.activityId = :activityId',
            ['activityId' => $activityId],
        )->fetchFirstColumn();

        foreach ($mediaItemIds as $mediaItemId) {
            $this->mediaItemStorage->remove(MediaItemId::fromString($mediaItemId));
        }
    }

    private function createQueryBuilderForVisibleMediaItems(): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->from('Activity')
            ->from('json_each(Activity.mediaItemIds)', 'activityMediaItem')
            ->innerJoin('activityMediaItem', 'MediaItem', 'MediaItem', 'MediaItem.mediaItemId = activityMediaItem.value');

        $hiddenSportTypes = $this->settingsRepository->appearance()->getHidePhotosForSportTypes();
        if (!$hiddenSportTypes->isEmpty()) {
            $queryBuilder->andWhere('Activity.sportType NOT IN (:hiddenSportTypes)')
                ->setParameter(
                    'hiddenSportTypes',
                    $hiddenSportTypes->toValues(),
                    ArrayParameterType::STRING
                );
        }

        return $queryBuilder;
    }

    /**
     * @param list<array<string, mixed>> $results
     */
    private function hydrateAll(array $results): ActivityMediaItems
    {
        $activities = $this->activityRepository->findByIds(ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            array_values(array_unique(array_column($results, 'activityId'))),
        )))->keyByActivityId();

        $mediaItemsById = [];
        foreach ($this->mediaItemRepository->findByIds(MediaItemIds::fromArray(array_map(
            MediaItemId::fromString(...),
            array_column($results, 'mediaItemId'),
        ))) as $mediaItem) {
            $mediaItemsById[(string) $mediaItem->getId()] = $mediaItem;
        }

        return ActivityMediaItems::fromArray(array_map(
            static fn (array $result): ActivityMediaItem => ActivityMediaItem::create(
                mediaItem: $mediaItemsById[$result['mediaItemId']],
                activity: $activities[$result['activityId']],
            ),
            $results,
        ));
    }
}
