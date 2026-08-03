<?php

namespace App\Domain\Challenge;

use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class DbalChallengeRepository extends DbalRepository implements ChallengeRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
    ) {
        parent::__construct($connection);
    }

    public function add(Challenge $challenge): void
    {
        $sql = 'INSERT INTO Challenge (challengeId, createdOn, name, logoUrl, localLogoUrl, slug)
        VALUES (:challengeId, :createdOn, :name, :logoUrl, :localLogoUrl, :slug)';

        $this->connection->executeStatement($sql, [
            'challengeId' => (string) $challenge->getId(),
            'createdOn' => $challenge->getCreatedOn(),
            'name' => $challenge->getName(),
            'logoUrl' => $challenge->getLogoUrl(),
            'localLogoUrl' => $challenge->getLocalLogoUrl(),
            'slug' => $challenge->getSlug(),
        ]);

        $this->eventBus->publishEvents($challenge->getRecordedEvents());
    }

    public function findAll(): Challenges
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('Challenge')
            ->orderBy('createdOn', 'DESC');

        return Challenges::fromArray(array_map(
            $this->hydrate(...),
            $queryBuilder->executeQuery()->fetchAllAssociative()
        ));
    }

    public function count(): int
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('COUNT(*)')
            ->from('Challenge');

        return (int) $queryBuilder->executeQuery()->fetchOne();
    }

    public function find(ChallengeId $challengeId): Challenge
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('Challenge')
            ->andWhere('challengeId = :challengeId')
            ->setParameter('challengeId', (string) $challengeId);

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('Challenge "%s" not found', $challengeId));
        }

        return $this->hydrate($result);
    }

    public function updateChallengeId(ChallengeId $oldChallengeId, ChallengeId $newChallengeId): void
    {
        $sql = 'UPDATE Challenge SET challengeId = :newChallengeId WHERE challengeId = :oldChallengeId';

        $this->connection->executeStatement($sql, [
            'newChallengeId' => (string) $newChallengeId,
            'oldChallengeId' => (string) $oldChallengeId,
        ]);
    }

    public function deleteWithNonAlphanumericIds(): void
    {
        $challengeIds = $this->connection->createQueryBuilder()
            ->select('challengeId')
            ->from('Challenge')
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($challengeIds as $challengeId) {
            $challengeId = ChallengeId::fromString($challengeId);
            if (preg_match('/[^a-zA-Z0-9_-]/', $challengeId->toUnprefixedString())) {
                $this->connection->executeStatement(
                    'DELETE FROM Challenge WHERE challengeId = :challengeId',
                    ['challengeId' => $challengeId]
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): Challenge
    {
        return Challenge::fromState(
            challengeId: ChallengeId::fromString($result['challengeId']),
            createdOn: SerializableDateTime::fromString($result['createdOn']),
            name: $result['name'],
            logoUrl: $result['logoUrl'],
            localLogoUrl: $result['localLogoUrl'],
            slug: $result['slug'],
        );
    }
}
