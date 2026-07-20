<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class DuplicateActivityScanner
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function isDuplicate(
        RawActivityFile $file,
        SportType $sportType,
        SerializableDateTime $startDateTime,
    ): bool {
        // Same activity already imported from Strava (strava -> file),
        // matched on the uploaded file's name.
        if ($this->existsStravaActivityForFilename($file->getPath()->getFilename())) {
            return true;
        }

        return $this->existsForSportTypeAndStartDate(
            sportType: $sportType,
            startDateTime: $startDateTime,
        );
    }

    private function existsForSportTypeAndStartDate(
        SportType $sportType,
        SerializableDateTime $startDateTime,
    ): bool {
        $count = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Activity')
            ->andWhere('startDateTime = :startDateTime')
            ->andWhere('sportType = :sportType')
            ->setParameter('startDateTime', $startDateTime->iso())
            ->setParameter('sportType', $sportType->value)
            ->executeQuery()
            ->fetchOne();

        return (int) $count > 0;
    }

    private function existsStravaActivityForFilename(string $filename): bool
    {
        $count = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Activity')
            ->andWhere('externalReferenceId = :externalReferenceId')
            ->andWhere('importSource = :importSource')
            ->setParameter('externalReferenceId', $filename)
            ->setParameter('importSource', ImportSource::STRAVA_API->value)
            ->executeQuery()
            ->fetchOne();

        return (int) $count > 0;
    }
}
