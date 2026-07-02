<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Yaml\Yaml;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260702152023 extends AbstractMigration
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
        $layout = $config['appearance']['dashboard']['layout'] ?? null;

        $this->skipIf(
            empty($layout),
            'No dashboard layout configured, nothing to migrate'
        );

        // Skip disabled widgets and drop the "enabled" flag.
        $layout = array_values(array_filter(
            $layout,
            static fn (array $widget): bool => (bool) ($widget['enabled'] ?? true),
        ));
        foreach ($layout as $i => $widget) {
            unset($layout[$i]['enabled']);
        }

        $this->connection->executeStatement(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => Key::DASHBOARD->value,
                'value' => Json::encode($layout),
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => Key::DASHBOARD->value]);
    }
}
