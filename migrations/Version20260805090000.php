<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Moves the map settings out of the appearance settings and into their own settings group.
 *
 * Before this, the map configuration lived under "appearance.maps.*" (originally migrated there
 * from the legacy config.yaml). Without this migration anyone who had configured a custom tile
 * layer would silently fall back to the OpenStreetMap defaults, because the new Maps settings
 * page reads a different key.
 */
final class Version20260805090000 extends AbstractMigration
{
    private const string APPEARANCE_KEY = 'settingsAppearance';
    private const string MAPS_KEY = 'settingsMaps';

    public function getDescription(): string
    {
        return 'Move appearance.maps.* settings to their own settingsMaps key';
    }

    public function up(Schema $schema): void
    {
        if (null === $appearance = $this->findSettings(self::APPEARANCE_KEY)) {
            return;
        }

        $maps = $appearance['maps'] ?? null;
        if (!is_array($maps) || [] === $maps) {
            return;
        }

        // Never overwrite settings that were already saved through the new Maps page.
        if (null !== $this->findSettings(self::MAPS_KEY)) {
            return;
        }

        // The legacy key accepted a single url as a plain string. MapsSettings only reads arrays,
        // so normalize it here or the configured layer would be dropped.
        if (isset($maps['tileLayerUrl']) && is_string($maps['tileLayerUrl'])) {
            $maps['tileLayerUrl'] = [$maps['tileLayerUrl']];
        }

        $this->saveSettings(self::MAPS_KEY, $maps);

        unset($appearance['maps']);
        $this->saveSettings(self::APPEARANCE_KEY, $appearance);
    }

    public function down(Schema $schema): void
    {
        if (null === $maps = $this->findSettings(self::MAPS_KEY)) {
            return;
        }

        $appearance = $this->findSettings(self::APPEARANCE_KEY) ?? [];
        $appearance['maps'] = $maps;

        $this->saveSettings(self::APPEARANCE_KEY, $appearance);
        $this->addSql('DELETE FROM KeyValue WHERE `key` = :key', ['key' => self::MAPS_KEY]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSettings(string $key): ?array
    {
        $value = $this->connection->fetchOne(
            'SELECT value FROM KeyValue WHERE `key` = :key',
            ['key' => $key],
        );

        if (!is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function saveSettings(string $key, array $settings): void
    {
        $this->addSql(
            'REPLACE INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            [
                'key' => $key,
                'value' => (string) json_encode($settings),
            ],
        );
    }
}
