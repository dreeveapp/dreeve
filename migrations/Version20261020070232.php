<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Activity\ActivityExclusion;
use App\Domain\Activity\ActivityId;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020070232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Activity ADD COLUMN excludedFrom CLOB DEFAULT NULL');
    }

    public function postUp(Schema $schema): void
    {
        $exclusionPerSettingsName = [
            'excludedFromPeakPowerOutputs' => ActivityExclusion::PEAK_POWER_OUTPUTS,
            'excludedFromBestEfforts' => ActivityExclusion::BEST_EFFORTS,
        ];
        $rows = $this->connection->fetchAllAssociative(
            'SELECT name, value FROM Setting WHERE settingsGroup = :settingsGroup',
            ['settingsGroup' => 'activityExclusions']
        );

        $exclusionsPerActivity = [];
        foreach ($rows as $row) {
            if (!$exclusion = $exclusionPerSettingsName[$row['name']] ?? null) {
                continue;
            }
            $activityIds = Json::decode((string) $row['value']);
            foreach (is_array($activityIds) ? $activityIds : [] as $activityId) {
                if ('' === $activityId = trim((string) $activityId)) {
                    continue;
                }
                $exclusionsPerActivity[(string) ActivityId::fromPrefixedOrUnprefixed($activityId)][] = $exclusion;
            }
        }

        foreach ($exclusionsPerActivity as $activityId => $exclusions) {
            $this->connection->executeStatement(
                'UPDATE Activity SET excludedFrom = :excludedFrom WHERE activityId = :activityId',
                [
                    'excludedFrom' => Json::encode(array_values(array_filter(
                        ActivityExclusion::cases(),
                        static fn (ActivityExclusion $exclusion): bool => in_array($exclusion, $exclusions, true),
                    ))),
                    'activityId' => $activityId,
                ]
            );
        }

        $this->connection->executeStatement(
            'DELETE FROM Setting WHERE settingsGroup = :settingsGroup',
            ['settingsGroup' => 'activityExclusions']
        );
    }

    public function down(Schema $schema): void
    {
    }
}
