<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012123050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE GearComponent (maintenanceTasks CLOB NOT NULL, gearComponentId VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, installations CLOB NOT NULL, localImagePath VARCHAR(255) DEFAULT NULL, isRetired BOOLEAN NOT NULL, notes CLOB DEFAULT NULL, purchasePriceAmount BIGINT DEFAULT NULL, purchasePriceCurrency VARCHAR(3) DEFAULT NULL, PRIMARY KEY (gearComponentId))');
    }

    public function postUp(Schema $schema): void
    {
        $value = $this->connection->fetchOne('SELECT value FROM KeyValue WHERE "key" = :key', ['key' => 'gearMaintenance']);
        if (!is_string($value)) {
            return;
        }

        $config = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($config) || !is_array($config['components'] ?? null)) {
            return;
        }

        foreach ($config['components'] as $index => $component) {
            if (!array_key_exists('attachedTo', $component)) {
                continue;
            }

            $migratedComponent = [
                'id' => $component['id'],
                'label' => $component['label'],
                'localImagePath' => $component['localImagePath'] ?? null,
                'isRetired' => false,
                'notes' => null,
                'installations' => array_map(
                    static fn (string $gearId): array => [
                        'parent' => 'gear:'.$gearId,
                        'from' => null,
                        'until' => null,
                        'sportTypes' => [],
                    ],
                    array_values($component['attachedTo']),
                ),
                'maintenance' => $component['maintenance'] ?? [],
            ];
            if (isset($component['purchasePrice'])) {
                $migratedComponent['purchasePrice'] = $component['purchasePrice'];
            }

            $config['components'][$index] = $migratedComponent;
        }

        $this->connection->executeStatement(
            'UPDATE KeyValue SET value = :value WHERE "key" = :key',
            ['value' => json_encode($config, JSON_THROW_ON_ERROR), 'key' => 'gearMaintenance'],
        );
    }

    public function down(Schema $schema): void
    {
    }
}
