<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Connection;

final readonly class DbalSettingsRepository extends DbalRepository implements SettingsRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
    ) {
        parent::__construct($connection);
    }

    public function find(SettingsName $name): mixed
    {
        $value = $this->connection->fetchOne(
            'SELECT value FROM Setting WHERE settingsGroup = :settingsGroup AND name = :name',
            ['settingsGroup' => $name->group()->value, 'name' => $name->value]
        );

        return $this->applyDefault($name, false === $value ? null : Json::decode((string) $value));
    }

    public function findGroup(SettingsGroup $group): array
    {
        $data = array_map(
            static fn (string $value): mixed => Json::decode($value),
            $this->connection->executeQuery(
                'SELECT name, value FROM Setting WHERE settingsGroup = :settingsGroup',
                ['settingsGroup' => $group->value]
            )->fetchAllKeyValue(),
        );

        foreach (SettingsName::cases() as $name) {
            if ($group !== $name->group()) {
                continue;
            }

            $value = $this->applyDefault($name, $data[$name->value] ?? null);
            if (null !== $value) {
                $data[$name->value] = $value;
            }
        }

        return $data;
    }

    private function applyDefault(SettingsName $name, mixed $value): mixed
    {
        return empty($value) ? ($name->defaultValue() ?? $value) : $value;
    }

    public function save(SettingsName $name, mixed $value): void
    {
        $this->connection->executeStatement(
            'INSERT INTO Setting (settingsGroup, name, value) VALUES (:settingsGroup, :name, :value)
             ON CONFLICT (settingsGroup, name) DO UPDATE SET value = excluded.value',
            [
                'settingsGroup' => $name->group()->value,
                'name' => $name->value,
                'value' => Json::encode($value),
            ]
        );

        $this->eventBus->publishEvents([new SettingsWereUpdated($name->group())]);
    }

    public function saveGroup(SettingsGroup $group, array $data): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($group, $data): void {
            $connection->executeStatement(
                'DELETE FROM Setting WHERE settingsGroup = :settingsGroup',
                ['settingsGroup' => $group->value]
            );

            foreach ($data as $name => $value) {
                $connection->executeStatement(
                    'INSERT INTO Setting (settingsGroup, name, value) VALUES (:settingsGroup, :name, :value)',
                    [
                        'settingsGroup' => $group->value,
                        'name' => $name,
                        'value' => Json::encode($value),
                    ]
                );
            }
        });

        $this->eventBus->publishEvents([new SettingsWereUpdated($group)]);
    }

    public function general(): GeneralSettings
    {
        return GeneralSettings::fromArray($this->findGroup(SettingsGroup::GENERAL));
    }

    public function appearance(): AppearanceSettings
    {
        return AppearanceSettings::fromArray($this->findGroup(SettingsGroup::APPEARANCE));
    }

    public function maps(): MapsSettings
    {
        return MapsSettings::fromArray($this->findGroup(SettingsGroup::MAPS));
    }

    public function import(): ImportSettings
    {
        return ImportSettings::fromArray($this->findGroup(SettingsGroup::IMPORT));
    }

    public function metrics(): MetricsSettings
    {
        return MetricsSettings::fromArray($this->findGroup(SettingsGroup::METRICS));
    }

    public function zwift(): ZwiftSettings
    {
        return ZwiftSettings::fromArray($this->findGroup(SettingsGroup::ZWIFT));
    }

    public function integrations(): IntegrationsSettings
    {
        return IntegrationsSettings::fromArray($this->findGroup(SettingsGroup::INTEGRATIONS));
    }

    public function daemon(): DaemonSettings
    {
        return DaemonSettings::fromArray($this->findGroup(SettingsGroup::DAEMON));
    }

    public function security(): SecuritySettings
    {
        return SecuritySettings::fromArray($this->findGroup(SettingsGroup::SECURITY));
    }
}
