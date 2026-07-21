<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Math;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721144528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        $activities = $this->connection->executeQuery(
            'SELECT activityId FROM Activity
                WHERE importSource IN (:importSources)
                AND (averageHeartRate IS NULL OR maxHeartRate IS NULL OR averagePower IS NULL OR maxPower IS NULL OR averageCadence IS NULL)',
            ['importSources' => [ImportSource::FIT_FILE->value, ImportSource::TCX_FILE->value, ImportSource::GPX_FILE->value]],
            ['importSources' => ArrayParameterType::STRING],
        );

        while ($activity = $activities->fetchAssociative()) {
            $heartRates = $this->streamData($activity['activityId'], StreamType::HEART_RATE);
            if ([] !== $heartRates && max($heartRates) > 300) {
                $heartRates = [];
            }
            $watts = $this->streamData($activity['activityId'], StreamType::WATTS);
            $cadences = $this->streamData($activity['activityId'], StreamType::CADENCE);

            if ([] === $heartRates && [] === $watts && [] === $cadences) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE Activity SET
                    averageHeartRate = COALESCE(averageHeartRate, :averageHeartRate),
                    maxHeartRate = COALESCE(maxHeartRate, :maxHeartRate),
                    averagePower = COALESCE(averagePower, :averagePower),
                    maxPower = COALESCE(maxPower, :maxPower),
                    averageCadence = COALESCE(averageCadence, :averageCadence)
                    WHERE activityId = :activityId',
                [
                    'averageHeartRate' => Math::average($heartRates),
                    'maxHeartRate' => Math::max($heartRates),
                    'averagePower' => Math::average($watts),
                    'maxPower' => Math::max($watts),
                    'averageCadence' => Math::average($cadences),
                    'activityId' => $activity['activityId'],
                ],
            );
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function streamData(string $activityId, StreamType $streamType): array
    {
        $data = $this->connection->fetchOne(
            'SELECT data FROM ActivityStream WHERE activityId = :activityId AND streamType = :streamType',
            [
                'activityId' => $activityId,
                'streamType' => $streamType->value,
            ],
        );
        if (false === $data || null === $data) {
            return [];
        }

        $data = Json::uncompressAndDecode($data);

        return is_array($data) ? $data : [];
    }

    public function down(Schema $schema): void
    {
    }
}
