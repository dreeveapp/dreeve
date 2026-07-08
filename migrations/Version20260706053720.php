<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Yaml\Yaml;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706053720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $configFile = dirname(__DIR__).'/config/app/config.yaml';

        $this->skipIf(
            !file_exists($configFile),
            'No config.yaml found, nothing to migrate'
        );

        $config = Yaml::parseFile($configFile);

        $this->migrateGeneral($config);
        $this->migrateAppearance($config);
        $this->migrateImport($config);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::GENERAL->keyValueKey()->value]);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::APPEARANCE->keyValueKey()->value]);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::IMPORT->keyValueKey()->value]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function migrateGeneral(array $config): void
    {
        $subtree = $config[SettingsGroup::GENERAL->value] ?? null;
        if (empty($subtree)) {
            return;
        }

        $subtree = $this->normalizeKeys($subtree);
        $subtree = $this->applyStoredAthlete($subtree);
        $subtree = $this->normalizeAthleteHistories($subtree);

        $this->connection->executeStatement(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => SettingsGroup::GENERAL->keyValueKey()->value,
                'value' => Json::encode($subtree),
            ]
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function migrateAppearance(array $config): void
    {
        $subtree = $config[SettingsGroup::APPEARANCE->value] ?? null;
        if (empty($subtree)) {
            return;
        }

        $subtree = $this->normalizeKeys($subtree);
        // The dashboard layout is stored separately under Key::DASHBOARD.
        unset($subtree['dashboard']);
        // Convert the legacy string date format to the modern {short, normal} shape.
        if (isset($subtree['dateFormat']) && is_string($subtree['dateFormat'])) {
            $subtree['dateFormat'] = $this->normalizeDateFormat($subtree['dateFormat']);
        }
        if (empty($subtree)) {
            return;
        }

        $this->connection->executeStatement(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => SettingsGroup::APPEARANCE->keyValueKey()->value,
                'value' => Json::encode($subtree),
            ]
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function migrateImport(array $config): void
    {
        $subtree = $config[SettingsGroup::IMPORT->value] ?? null;
        if (empty($subtree)) {
            return;
        }

        $subtree = $this->normalizeKeys($subtree);

        $this->connection->executeStatement(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => SettingsGroup::IMPORT->keyValueKey()->value,
                'value' => Json::encode($subtree),
            ]
        );
    }

    /**
     * @return array{short: string, normal: string}
     */
    private function normalizeDateFormat(string $legacyDateFormat): array
    {
        [$short, $normal] = match ($legacyDateFormat) {
            'DAY-MONTH-YEAR' => ['d-m-y', 'd-m-Y'],
            'MONTH-DAY-YEAR' => ['m-d-y', 'm-d-Y'],
            default => throw new \InvalidArgumentException(sprintf('Invalid date format "%s"', $legacyDateFormat)),
        };

        return ['short' => $short, 'normal' => $normal];
    }

    /**
     * @param array<string, mixed> $subtree
     *
     * @return array<string, mixed>
     */
    private function applyStoredAthlete(array $subtree): array
    {
        $stored = $this->connection->fetchOne('SELECT value FROM KeyValue WHERE `key` = :key', ['key' => 'athlete']);
        if (!is_string($stored)) {
            return $subtree;
        }

        $athlete = Json::decode($stored);
        if (!is_array($athlete)) {
            return $subtree;
        }

        $current = is_array($subtree['athlete'] ?? null) ? $subtree['athlete'] : [];
        foreach (['firstname' => 'firstName', 'lastname' => 'lastName', 'sex' => 'gender', 'birthDate' => 'birthday'] as $from => $to) {
            if (!empty($current[$to])) {
                continue;
            }
            if (isset($athlete[$from]) && '' !== (string) $athlete[$from]) {
                $current[$to] = $athlete[$from];
            }
        }
        $subtree['athlete'] = $current;

        return $subtree;
    }

    /**
     * @param array<string, mixed> $subtree
     *
     * @return array<string, mixed>
     */
    private function normalizeAthleteHistories(array $subtree): array
    {
        if (!is_array($subtree['athlete'] ?? null)) {
            return $subtree;
        }
        $athlete = $subtree['athlete'];

        $weightHistory = [];
        foreach ((is_array($athlete['weightHistory'] ?? null) ? $athlete['weightHistory'] : []) as $on => $weight) {
            $weightHistory[] = ['on' => (string) $on, 'weight' => $weight];
        }
        $athlete['weightHistory'] = $weightHistory;

        $ftpHistory = is_array($athlete['ftpHistory'] ?? null) ? $athlete['ftpHistory'] : [];
        if (!array_key_exists('cycling', $ftpHistory) && !array_key_exists('running', $ftpHistory)) {
            $ftpHistory = ['cycling' => $ftpHistory, 'running' => []];
        }
        $cycling = [];
        foreach ((is_array($ftpHistory['cycling'] ?? null) ? $ftpHistory['cycling'] : []) as $on => $ftp) {
            $cycling[] = ['on' => (string) $on, 'ftp' => $ftp];
        }
        $running = [];
        foreach ((is_array($ftpHistory['running'] ?? null) ? $ftpHistory['running'] : []) as $on => $ftp) {
            $running[] = ['on' => (string) $on, 'ftp' => $ftp];
        }
        $athlete['ftpHistory'] = ['cycling' => $cycling, 'running' => $running];

        if (is_array($athlete['heartRateZones'] ?? null)) {
            $heartRateZones = $athlete['heartRateZones'];
            $default = is_array($heartRateZones['default'] ?? null) ? $heartRateZones['default'] : [];

            $zones = [];
            foreach (['zone1', 'zone2', 'zone3', 'zone4', 'zone5'] as $name) {
                $zone = is_array($default[$name] ?? null) ? $default[$name] : [];
                $zones[] = ['from' => $zone['from'] ?? null, 'to' => $zone['to'] ?? null];
            }

            $flat = [
                'mode' => $heartRateZones['mode'] ?? 'relative',
                'zones' => $zones,
            ];

            $advanced = [];
            foreach (['dateRanges', 'sportTypes'] as $key) {
                if (isset($heartRateZones[$key])) {
                    $advanced[$key] = $heartRateZones[$key];
                }
            }
            if ([] !== $advanced) {
                $flat['advanced'] = Yaml::dump($advanced, 6, 2);
            }

            $athlete['heartRateZones'] = $flat;
        }

        $subtree['athlete'] = $athlete;

        return $subtree;
    }

    /**
     * @param array<string|int, mixed> $config
     *
     * @return array<string|int, mixed>
     */
    private function normalizeKeys(array $config): array
    {
        $normalized = [];
        foreach ($config as $key => $value) {
            if (is_string($key) && str_contains($key, '_')) {
                $key = lcfirst(str_replace('_', '', ucwords($key, '_')));
            }
            $normalized[$key] = is_array($value) ? $this->normalizeKeys($value) : $value;
        }

        return $normalized;
    }
}
