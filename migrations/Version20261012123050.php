<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\Serialization\Json;
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
        $value = $this->connection->fetchOne('SELECT value FROM KeyValue WHERE "key" = :key', ['key' => Key::GEAR_MAINTENANCE->value]);
        if (!is_string($value)) {
            return;
        }

        $config = Json::decode($value);
        if (!is_array($config) || !is_array($config['components'] ?? null)) {
            return;
        }

        $gearIds = $this->connection->fetchFirstColumn('SELECT gearId FROM Gear');

        foreach ($config['components'] as $component) {
            $installations = array_map(
                static fn (string $gearId): array => [
                    'parent' => 'gear:'.(array_find(
                        ['gear-'.$gearId, 'gear-b'.$gearId, 'gear-g'.$gearId],
                        static fn (string $candidate): bool => in_array($candidate, $gearIds, true),
                    ) ?? 'gear-'.$gearId),
                    'from' => null,
                    'until' => null,
                    'sportTypes' => [],
                ],
                array_values($component['attachedTo'] ?? []),
            );

            $this->connection->executeStatement(
                'INSERT INTO GearComponent (gearComponentId, label, localImagePath, purchasePriceAmount, purchasePriceCurrency, isRetired, notes, installations, maintenanceTasks)
                 VALUES (:gearComponentId, :label, :localImagePath, :purchasePriceAmount, :purchasePriceCurrency, :isRetired, :notes, :installations, :maintenanceTasks)',
                [
                    'gearComponentId' => $component['id'],
                    'label' => $component['label'],
                    'localImagePath' => $component['localImagePath'] ?? null,
                    'purchasePriceAmount' => $component['purchasePrice']['amountInCents'] ?? null,
                    'purchasePriceCurrency' => $component['purchasePrice']['currency'] ?? null,
                    'isRetired' => (int) ($component['isRetired'] ?? false),
                    'notes' => $component['notes'] ?? null,
                    'installations' => Json::encode($installations),
                    'maintenanceTasks' => Json::encode($component['maintenance'] ?? []),
                ],
            );
        }

        $this->connection->executeStatement(
            'UPDATE KeyValue SET value = :value WHERE "key" = :key',
            [
                'value' => Json::encode([
                    'enabled' => (bool) ($config['enabled'] ?? false),
                    'ignoreRetiredGear' => (bool) ($config['ignoreRetiredGear'] ?? false),
                ]),
                'key' => Key::GEAR_MAINTENANCE->value,
            ],
        );
    }

    public function down(Schema $schema): void
    {
    }
}
