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

    public function find(SettingsGroup $group): array
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
        if (SettingsGroup::METRICS === $group && empty($data['eddington'])) {
            $data['eddington'] = EddingtonConfiguration::getDefaultConfig();
        }

        if (SettingsGroup::GENERAL === $group) {
            if (empty($data['maxHeartRateFormula'])) {
                $data['maxHeartRateFormula'] = 'fox';
            }
            if (empty($data['restingHeartRateFormula'])) {
                $data['restingHeartRateFormula'] = 'heuristicAgeBased';
            }
        }

        return $data;
    }

    public function save(SettingsGroup $group, array $data): void
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
        return GeneralSettings::fromArray($this->find(SettingsGroup::GENERAL));
    }

    public function appearance(): AppearanceSettings
    {
        return AppearanceSettings::fromArray($this->find(SettingsGroup::APPEARANCE));
    }

    public function maps(): MapsSettings
    {
        return MapsSettings::fromArray($this->find(SettingsGroup::MAPS));
    }

    public function import(): ImportSettings
    {
        return ImportSettings::fromArray($this->find(SettingsGroup::IMPORT));
    }

    public function metrics(): MetricsSettings
    {
        return MetricsSettings::fromArray($this->find(SettingsGroup::METRICS));
    }

    public function zwift(): ZwiftSettings
    {
        return ZwiftSettings::fromArray($this->find(SettingsGroup::ZWIFT));
    }

    public function integrations(): IntegrationsSettings
    {
        return IntegrationsSettings::fromArray($this->find(SettingsGroup::INTEGRATIONS));
    }

    public function daemon(): DaemonSettings
    {
        return DaemonSettings::fromArray($this->find(SettingsGroup::DAEMON));
    }

    public function security(): SecuritySettings
    {
        return SecuritySettings::fromArray($this->find(SettingsGroup::SECURITY));
    }
}
