<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\Daemon\Cron\CronActionId;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
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
        $config = $this->loadLegacyConfig();

        $this->migrateDashboard($config);
        $this->migrateGeneral($config);
        $this->migrateAppearance($config);
        $this->migrateImport($config);
        $this->migrateMetrics($config);
        $this->migrateZwift($config);
        $this->migrateIntegrations($config);
        $this->migrateDaemon($config);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => Key::DASHBOARD->value]);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::GENERAL->keyValueKey()->value]);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::APPEARANCE->keyValueKey()->value]);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::IMPORT->keyValueKey()->value]);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::METRICS->keyValueKey()->value]);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::ZWIFT->keyValueKey()->value]);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::INTEGRATIONS->keyValueKey()->value]);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => SettingsGroup::DAEMON->keyValueKey()->value]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadLegacyConfig(): array
    {
        $basePath = dirname(__DIR__).'/config/app';
        $configFile = $basePath.'/config.yaml';

        if (!file_exists($configFile)) {
            return [];
        }

        $this->write('Detected valid configuration files:');
        $this->write('  * config.yaml');

        $finder = Finder::create()
            ->in($basePath)
            ->depth('== 0')
            ->files()
            ->sortByName()
            ->name('config-*.yaml');

        $config = Yaml::parseFile($configFile);
        foreach ($finder as $file) {
            try {
                $config = array_replace_recursive($config, Yaml::parseFile($file->getRealPath()));
                $this->write('  * '.$file->getFilename());
            } catch (ParseException) {
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function migrateDashboard(array $config): void
    {
        if (!$layout = $config['appearance']['dashboard']['layout'] ?? null) {
            return;
        }

        // Skip disabled widgets, drop the "enabled" flag, and give each widget an id.
        $layout = array_values(array_filter(
            $layout,
            static fn (array $widget): bool => (bool) ($widget['enabled'] ?? true),
        ));
        foreach ($layout as $i => $widget) {
            unset($widget['enabled']);
            $layout[$i] = ['id' => (string) DashboardWidgetId::random()] + $widget;
        }

        $this->addSql(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => Key::DASHBOARD->value,
                'value' => Json::encode($layout),
            ]
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function migrateGeneral(array $config): void
    {
        $subtree = is_array($config[SettingsGroup::GENERAL->value] ?? null) ? $config[SettingsGroup::GENERAL->value] : [];

        $subtree = $this->normalizeKeys($subtree);
        $subtree = $this->applyStoredAthlete($subtree);
        if ([] === $subtree) {
            // No general config and no stored athlete.
            return;
        }
        $subtree = $this->normalizeAthleteHistories($subtree);

        $this->addSql(
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

        $this->addSql(
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

        $this->addSql(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => SettingsGroup::IMPORT->keyValueKey()->value,
                'value' => Json::encode($subtree),
            ]
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function migrateMetrics(array $config): void
    {
        $subtree = $config[SettingsGroup::METRICS->value] ?? null;
        if (empty($subtree)) {
            return;
        }

        $subtree = $this->normalizeKeys($subtree);

        $this->addSql(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => SettingsGroup::METRICS->keyValueKey()->value,
                'value' => Json::encode($subtree),
            ]
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function migrateZwift(array $config): void
    {
        $subtree = $config[SettingsGroup::ZWIFT->value] ?? null;
        if (empty($subtree)) {
            return;
        }

        $subtree = $this->normalizeKeys($subtree);

        $this->addSql(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => SettingsGroup::ZWIFT->keyValueKey()->value,
                'value' => Json::encode($subtree),
            ]
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function migrateIntegrations(array $config): void
    {
        $subtree = $config[SettingsGroup::INTEGRATIONS->value] ?? null;
        if (empty($subtree)) {
            return;
        }

        $subtree = $this->normalizeKeys($subtree);

        // Fold the deprecated ntfy config into a regular notification service URL.
        if (is_array($subtree['notifications'] ?? null)) {
            $notifications = $subtree['notifications'];
            $services = is_array($notifications['services'] ?? null) ? array_values($notifications['services']) : [];

            $ntfyUrl = $notifications['ntfyUrl'] ?? null;
            if (is_string($ntfyUrl) && !in_array($ntfyUrl, ['', '0'], true)) {
                array_unshift($services, (string) ShoutrrrUrl::fromDeprecatedNtfyConfig(
                    ntfyUrl: $ntfyUrl,
                    ntfyUsername: isset($notifications['ntfyUsername']) ? (string) $notifications['ntfyUsername'] : null,
                    ntfyPassword: isset($notifications['ntfyPassword']) ? (string) $notifications['ntfyPassword'] : null,
                ));
            }

            unset($notifications['ntfyUrl'], $notifications['ntfyUsername'], $notifications['ntfyPassword']);
            $notifications['services'] = $services;
            $subtree['notifications'] = $notifications;
        }

        if (isset($subtree['ai']['config']['key'])) {
            // Do not store sensitive data in database.
            unset($subtree['ai']['config']['key']);
        }

        $this->addSql(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => SettingsGroup::INTEGRATIONS->keyValueKey()->value,
                'value' => Json::encode($subtree),
            ]
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function migrateDaemon(array $config): void
    {
        $cron = $config[SettingsGroup::DAEMON->value]['cron'] ?? null;
        if (empty($cron) || !is_array($cron)) {
            return;
        }

        $renamedActions = ['importDataAndBuildApp' => CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP->value];

        $actions = [];
        foreach ($cron as $item) {
            if (!is_array($item) || !isset($item['action'])) {
                continue;
            }
            $action = (string) $item['action'];
            $action = $renamedActions[$action] ?? $action;
            $actions[$action] = [
                'expression' => (string) ($item['expression'] ?? ''),
                'enabled' => (bool) ($item['enabled'] ?? false),
            ];
        }

        if ([] === $actions) {
            return;
        }

        $this->addSql(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => SettingsGroup::DAEMON->keyValueKey()->value,
                'value' => Json::encode(['cron' => $actions]),
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
            if (!isset($athlete[$from]) || '' === (string) $athlete[$from]) {
                continue;
            }
            // The legacy birthday can be a full datetime, the date input only accepts Y-m-d.
            $current[$to] = 'birthday' === $to
                ? SerializableDateTime::fromString((string) $athlete[$from])->format('Y-m-d')
                : $athlete[$from];
        }
        if ([] === $current) {
            return $subtree;
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
