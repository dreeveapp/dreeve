<?php

declare(strict_types=1);

namespace App\Domain\Activity\Search;

use App\Domain\Activity\ActivityHydrator;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalActivitySearchRepository extends DbalRepository implements ActivitySearchRepository
{
    private const string HAS_GPX_EXPRESSION = 'EXISTS (SELECT 1 FROM ActivityStream s WHERE s.activityId = a.activityId AND s.streamType = :gpxStreamType)';

    public function find(ActivitySearchCriteria $criteria, Pagination $pagination): Overview
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select(
                ActivityHydrator::columns('a'),
                self::HAS_GPX_EXPRESSION.' AS hasGpx',
            )
            ->from('Activity', 'a')
            ->setParameter('gpxStreamType', StreamType::TIME->value)
            ->orderBy('a.startDateTime', 'DESC')
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit());

        $countQueryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Activity', 'a');

        foreach ([$queryBuilder, $countQueryBuilder] as $builder) {
            if ($from = $criteria->getFrom()) {
                $builder
                    ->andWhere('a.startDateTime >= :from')
                    ->setParameter('from', $from->format('Y-m-d H:i:s'));
            }
            if ($till = $criteria->getTill()) {
                $builder
                    ->andWhere('a.startDateTime < :till')
                    ->setParameter('till', $till->format('Y-m-d H:i:s'));
            }
            if (!$criteria->getSportTypes()->isEmpty()) {
                $builder
                    ->andWhere('a.sportType IN (:sportTypes)')
                    ->setParameter(
                        key: 'sportTypes',
                        value: array_map(
                            static fn (SportType $sportType): string => $sportType->value,
                            $criteria->getSportTypes()->toArray()
                        ),
                        type: ArrayParameterType::STRING
                    );
            }
            if (null !== $hasGpx = $criteria->hasGpx()) {
                $builder
                    ->andWhere($hasGpx ? self::HAS_GPX_EXPRESSION : 'NOT '.self::HAS_GPX_EXPRESSION)
                    ->setParameter('gpxStreamType', StreamType::TIME->value);
            }
        }

        $results = $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();

        $total = (int) $countQueryBuilder
            ->executeQuery()
            ->fetchOne();

        return Overview::create(
            pagination: $pagination,
            total: $total,
            items: array_map($this->hydrate(...), $results),
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): ActivitySearchResult
    {
        return ActivitySearchResult::fromState(
            activity: ActivityHydrator::hydrate($result),
            hasGpx: (bool) $result['hasGpx'],
        );
    }
}
