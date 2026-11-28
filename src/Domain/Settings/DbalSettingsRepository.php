<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Infrastructure\Config\ManagedMode;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DbalSettingsRepository extends DbalRepository implements SettingsRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
        private ManagedMode $managedMode,
    ) {
        parent::__construct($connection);
    }

    public function find(SettingsName $name): mixed
    {
        return $this->findGroup($name->group())[$name->value] ?? null;
    }

    public function findGroup(SettingsGroup $group): array
    {
        $data = array_map(
            static fn (string $value): mixed => Json::decode($value),
            $this->connection->executeQuery(
                'SELECT name, value FROM Setting WHERE name IN (:names)',
                ['names' => array_map(static fn (SettingsName $name): string => $name->value, $group->getSettingsNames())],
                ['names' => ArrayParameterType::STRING]
            )->fetchAllKeyValue(),
        );

        foreach ($group->getSettingsNames() as $name) {
            if (empty($data[$name->value] ?? null) && null !== $name->defaultValue()) {
                $data[$name->value] = $name->defaultValue();
            }
        }

        if (SettingsGroup::SECURITY === $group && $this->managedMode->isEnabled()) {
            $data[SettingsName::REQUIRES_AUTHENTICATION->value] ??= true;
        }

        return $data;
    }

    public function save(SettingsName $name, mixed $value): void
    {
        $this->connection->executeStatement(
            'INSERT INTO Setting (name, value) VALUES (:name, :value)
             ON CONFLICT (name) DO UPDATE SET value = excluded.value',
            [
                'name' => $name->value,
                'value' => Json::encode($value),
            ]
        );

        $this->eventBus->publishEvents([new SettingsWereUpdated($name->group())]);
    }

    public function saveGroup(SettingsGroup $group, array $data): void
    {
        $group->assertOwnsSettings($data);
        $names = array_map(static fn (SettingsName $name): string => $name->value, $group->getSettingsNames());

        $this->connection->transactional(static function (Connection $connection) use ($names, $data): void {
            $connection->executeStatement(
                'DELETE FROM Setting WHERE name IN (:names)',
                ['names' => $names],
                ['names' => ArrayParameterType::STRING]
            );

            foreach ($data as $name => $value) {
                $connection->executeStatement(
                    'INSERT INTO Setting (name, value) VALUES (:name, :value)',
                    [
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

    public function ai(): AISettings
    {
        return AISettings::fromArray($this->findGroup(SettingsGroup::AI));
    }

    public function notifications(): NotificationSettings
    {
        return NotificationSettings::fromArray($this->findGroup(SettingsGroup::NOTIFICATIONS));
    }

    public function daemon(): DaemonSettings
    {
        return DaemonSettings::fromArray($this->findGroup(SettingsGroup::DAEMON));
    }

    public function security(): SecuritySettings
    {
        return SecuritySettings::fromArray($this->findGroup(SettingsGroup::SECURITY));
    }

    public function athleteWeight(): AthleteWeightSettings
    {
        return AthleteWeightSettings::fromArray($this->findGroup(SettingsGroup::ATHLETE_WEIGHT));
    }

    public function athleteFtp(): AthleteFtpSettings
    {
        return AthleteFtpSettings::fromArray($this->findGroup(SettingsGroup::ATHLETE_FTP));
    }
}
