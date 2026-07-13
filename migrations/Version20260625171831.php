<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogId;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Yaml\Yaml;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260625171831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $gearMaintenanceConfigFile = dirname(__DIR__).'/config/app/gear-maintenance.yaml';

        if (!file_exists($gearMaintenanceConfigFile)) {
            return;
        }

        $config = $this->migrateLegacyComponents(Yaml::parseFile($gearMaintenanceConfigFile));

        $this->addSql(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => Key::GEAR_MAINTENANCE->value,
                'value' => Json::encode($config),
            ]
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function migrateLegacyComponents(array $config): array
    {
        foreach ($config['components'] ?? [] as $i => $component) {
            // Legacy images were referenced by paths/filenames that the new image
            // storage can no longer resolve, so void them; they have to be re-uploaded.
            unset($config['components'][$i]['imgSrc']);
            $config['components'][$i]['localImagePath'] = null;

            $componentTag = $component['tag'] ?? $component['id'] ?? null;
            if (null === $componentTag) {
                continue;
            }

            $config['components'][$i]['id'] = (string) GearComponentId::fromUnprefixed($componentTag);
            unset($config['components'][$i]['tag']);

            foreach ($component['maintenance'] ?? [] as $j => $task) {
                if (!isset($task['tag'])) {
                    continue;
                }
                // The task id stays globally unique by keeping its component as a prefix,
                // which also matches the "#<prefix>-<component>-<task>" hashtag we backfill from.
                $config['components'][$i]['maintenance'][$j]['id'] = (string) MaintenanceTaskId::fromUnprefixed($componentTag.'-'.$task['tag']);
                unset($config['components'][$i]['maintenance'][$j]['tag']);
            }
        }

        return $config;
    }

    public function postUp(Schema $schema): void
    {
        $rawConfig = $this->connection->fetchOne(
            'SELECT value FROM KeyValue WHERE `key` = :key',
            ['key' => Key::GEAR_MAINTENANCE->value]
        );
        if (false === $rawConfig) {
            return;
        }

        $config = Json::decode($rawConfig);
        if (empty($config['hashtagPrefix']) || empty($config['components'])) {
            return;
        }

        $activities = $this->connection->fetchAllAssociative(
            'SELECT name, gearId, startDateTime FROM Activity WHERE gearId IS NOT NULL'
        );
        if ([] === $activities) {
            return;
        }

        // Config gear ids may be copy-pasted unprefixed (e.g. "10130856") while the
        // database stores them with the Strava "b"/"g" prefix ("gear-g10130856").
        $normalizeGearId = static function (string $gearId): string {
            if (str_starts_with($gearId, 'gear-')) {
                $gearId = substr($gearId, 5);
            }
            if (str_starts_with($gearId, 'b') || str_starts_with($gearId, 'g')) {
                $gearId = substr($gearId, 1);
            }

            return $gearId;
        };

        $hashtagPrefix = $config['hashtagPrefix'];
        foreach ($config['components'] as $component) {
            if (empty($component['id']) || empty($component['maintenance']) || empty($component['attachedTo'])) {
                continue;
            }

            $attachedToGearIds = array_map($normalizeGearId, $component['attachedTo']);

            foreach ($component['maintenance'] as $task) {
                if (empty($task['id'])) {
                    continue;
                }

                // Legacy data was tagged in activity titles as "#<hashtagPrefix>-<component>-<task>",
                // which is exactly "#<hashtagPrefix>-<unprefixed task id>".
                $maintenanceTaskId = MaintenanceTaskId::fromString($task['id']);
                $legacyHashtag = '#'.implode('-', [$hashtagPrefix, $maintenanceTaskId->toUnprefixedString()]);

                foreach ($activities as $activity) {
                    if (!str_contains((string) $activity['name'], $legacyHashtag)) {
                        continue;
                    }
                    if (!in_array($normalizeGearId((string) $activity['gearId']), $attachedToGearIds, true)) {
                        continue;
                    }

                    $this->connection->executeStatement(
                        'INSERT INTO GearMaintenanceLog (gearMaintenanceLogId, gearId, maintenanceTaskId, performedOn)
                         VALUES (:id, :gearId, :maintenanceTaskId, :performedOn)',
                        [
                            'id' => (string) GearMaintenanceLogId::random(),
                            'gearId' => $activity['gearId'],
                            'maintenanceTaskId' => (string) $maintenanceTaskId,
                            'performedOn' => $activity['startDateTime'],
                        ]
                    );
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => Key::GEAR_MAINTENANCE->value]);
        $this->addSql('DELETE FROM GearMaintenanceLog');
    }
}
