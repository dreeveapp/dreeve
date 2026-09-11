<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911091856 extends AbstractMigration
{
    private const array SETTINGS_GROUP_PER_KEY = [
        'settingsGeneral' => 'general',
        'settingsAppearance' => 'appearance',
        'settingsMaps' => 'maps',
        'settingsImport' => 'import',
        'settingsMetrics' => 'metrics',
        'settingsZwift' => 'zwift',
        'settingsIntegrations' => 'integrations',
        'settingsDaemon' => 'daemon',
        'settingsSecurity' => 'security',
    ];

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE Setting (settingsGroup VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, value CLOB NOT NULL, PRIMARY KEY (settingsGroup, name))');

        $storedGroups = $this->connection->fetchAllKeyValue(
            'SELECT `key`, `value` FROM KeyValue WHERE `key` IN (:keys)',
            ['keys' => array_keys(self::SETTINGS_GROUP_PER_KEY)],
            ['keys' => ArrayParameterType::STRING],
        );

        foreach ($storedGroups as $key => $value) {
            $settings = Json::decode((string) $value);

            if (is_array($settings)) {
                if ('general' === self::SETTINGS_GROUP_PER_KEY[$key] && is_array($settings['athlete'] ?? null)) {
                    $athlete = $settings['athlete'];
                    unset($settings['athlete']);
                    $settings = [...$settings, ...$athlete];
                }

                foreach ($settings as $name => $setting) {
                    $this->addSql(
                        'INSERT INTO Setting (settingsGroup, name, value) VALUES (:settingsGroup, :name, :value)',
                        [
                            'settingsGroup' => self::SETTINGS_GROUP_PER_KEY[$key],
                            'name' => (string) $name,
                            'value' => Json::encode($setting),
                        ]
                    );
                }
            }

            $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => $key]);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
