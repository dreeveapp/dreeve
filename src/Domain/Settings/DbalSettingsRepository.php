<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Activity\Eddington\Config\EddingtonConfiguration;
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

    public function find(SettingsGroup $group, SettingsName $name): mixed
    {
        $value = $this->connection->fetchOne(
            'SELECT value FROM Setting WHERE settingsGroup = :settingsGroup AND name = :name',
            ['settingsGroup' => $group->value, 'name' => $name->value]
        );

        return $this->applyDefaults($group, [
            $name->value => false === $value ? null : Json::decode((string) $value),
        ])[$name->value];
    }

    public function findGroup(SettingsGroup $group): array
    {
        $values = $this->connection->executeQuery(
            'SELECT name, value FROM Setting WHERE settingsGroup = :settingsGroup ORDER BY rowid',
            ['settingsGroup' => $group->value]
        )->fetchAllKeyValue();

        return $this->applyDefaults($group, array_map(
            static fn (string $value): mixed => Json::decode($value),
            $values,
        ));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function applyDefaults(SettingsGroup $group, array $data): array
    {
        if (SettingsGroup::METRICS === $group && empty($data[SettingsName::EDDINGTON->value])) {
            $data[SettingsName::EDDINGTON->value] = EddingtonConfiguration::getDefaultConfig();
        }

        if (SettingsGroup::GENERAL === $group) {
            if (empty($data[SettingsName::MAX_HEART_RATE_FORMULA->value])) {
                $data[SettingsName::MAX_HEART_RATE_FORMULA->value] = 'fox';
            }
            if (empty($data[SettingsName::RESTING_HEART_RATE_FORMULA->value])) {
                $data[SettingsName::RESTING_HEART_RATE_FORMULA->value] = 'heuristicAgeBased';
            }
        }

        return $data;
    }

    public function save(SettingsGroup $group, SettingsName $name, mixed $value): void
    {
        $this->connection->executeStatement(
            'INSERT INTO Setting (settingsGroup, name, value) VALUES (:settingsGroup, :name, :value)
             ON CONFLICT (settingsGroup, name) DO UPDATE SET value = excluded.value',
            [
                'settingsGroup' => $group->value,
                'name' => $name->value,
                'value' => Json::encode($value),
            ]
        );

        $this->eventBus->publishEvents([new SettingsWereUpdated($group)]);
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
