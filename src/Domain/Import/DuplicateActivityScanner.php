<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DuplicateActivityScanner
{
    private const int MATCH_TOLERANCE_IN_SECONDS = 60;

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
        // matched on the uploaded file's name and start time.
        if ($this->existsStravaActivityForFilename(
            filename: $file->getPath()->getFilename(),
            startDateTime: $startDateTime,
        )) {
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

    private function existsStravaActivityForFilename(
        string $filename,
        SerializableDateTime $startDateTime,
    ): bool {
        $count = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Activity')
            ->andWhere('externalReferenceId = :externalReferenceId')
            ->andWhere('importSource = :importSource')
            ->andWhere('ABS(STRFTIME(\'%s\', JSON_EXTRACT(data, \'$.start_date\')) - :startTimestamp) <= :toleranceInSeconds')
            ->setParameter('externalReferenceId', $filename)
            ->setParameter('importSource', ImportSource::STRAVA_API->value)
            ->setParameter('startTimestamp', $startDateTime->getTimestamp(), ParameterType::INTEGER)
            ->setParameter('toleranceInSeconds', self::MATCH_TOLERANCE_IN_SECONDS, ParameterType::INTEGER)
            ->executeQuery()
            ->fetchOne();

        return (int) $count > 0;
    }
}
