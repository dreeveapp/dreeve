<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015085042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM CombinedActivityStream');
        $this->addSql(
            'UPDATE Setting SET settingsGroup = :to WHERE settingsGroup = :from AND name = :name',
            ['to' => 'athleteWeight', 'from' => 'general', 'name' => 'weightHistory']
        );

        $value = $this->connection->fetchOne(
            'SELECT value FROM Setting WHERE settingsGroup = :settingsGroup AND name = :name',
            ['settingsGroup' => 'metrics', 'name' => 'excludeActivitiesFromPeakPowerOutputs']
        );
        if (!is_string($value)) {
            return;
        }

        $activityIds = Json::decode($value);

        $this->addSql(
            'INSERT INTO Setting (settingsGroup, name, value) VALUES (:settingsGroup, :name, :value)',
            [
                'settingsGroup' => 'activityExclusions',
                'name' => 'excludedFromPeakPowerOutputs',
                'value' => Json::encode(array_values(array_map(
                    static fn (string $activityId): string => (string) ActivityId::fromPrefixedOrUnprefixed($activityId),
                    array_filter(
                        array_map(static fn (mixed $activityId): string => trim((string) $activityId), is_array($activityIds) ? $activityIds : []),
                        static fn (string $activityId): bool => '' !== $activityId,
                    ),
                ))),
            ]
        );
        $this->addSql(
            'DELETE FROM Setting WHERE settingsGroup = :settingsGroup AND name = :name',
            ['settingsGroup' => 'metrics', 'name' => 'excludeActivitiesFromPeakPowerOutputs']
        );
    }

    public function down(Schema $schema): void
    {
    }
}
