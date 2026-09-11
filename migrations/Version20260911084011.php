<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Gear\RecordingDevice\RecordingDeviceId;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911084011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $deviceNames = [];
        foreach ($this->connection->fetchFirstColumn(
            'SELECT deviceName FROM Activity WHERE deviceName IS NOT NULL AND TRIM(deviceName) != ""
             UNION
             SELECT name FROM RecordingDevice WHERE TRIM(name) != ""'
        ) as $deviceName) {
            $deviceNames[RecordingDeviceId::fromName((string) $deviceName)->toUnprefixedString()] ??= (string) $deviceName;
        }

        foreach ($this->connection->fetchAllAssociative('SELECT automationRuleId, conditions FROM AutomationRule') as $automationRule) {
            /** @var list<array{type: string, config: array<string, mixed>}> $conditions */
            $conditions = Json::decode($automationRule['conditions']);

            $hasChanged = false;
            foreach ($conditions as $index => $condition) {
                if (ConditionType::DEVICE->value !== $condition['type'] || !isset($condition['config']['deviceId']) || !is_string($condition['config']['deviceId'])) {
                    continue;
                }

                $deviceId = $condition['config']['deviceId'];
                unset($conditions[$index]['config']['deviceId']);
                $conditions[$index]['config']['deviceName'] = $deviceNames[$deviceId] ?? $deviceId;
                $hasChanged = true;
            }

            if (!$hasChanged) {
                continue;
            }

            $this->addSql(
                'UPDATE AutomationRule SET conditions = :conditions WHERE automationRuleId = :automationRuleId',
                [
                    'conditions' => Json::encode($conditions),
                    'automationRuleId' => $automationRule['automationRuleId'],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
    }
}
