<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Media\MediaItemId;
use App\Domain\Media\Metadata\MediaItemMetadataReader;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117155202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE MediaItem (mediaItemId VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, width INTEGER DEFAULT NULL, height INTEGER DEFAULT NULL, takenOn DATETIME DEFAULT NULL, coordinateLatitude DOUBLE PRECISION DEFAULT NULL, coordinateLongitude DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY (mediaItemId))');
        $this->addSql('ALTER TABLE Activity RENAME COLUMN localImagePaths TO mediaItemIds');
        $this->addSql('ALTER TABLE ActivityCollection RENAME COLUMN coverImagePath TO coverMediaItemId');
        $this->addSql('ALTER TABLE Gear RENAME COLUMN localImagePath TO mediaItemId');
        $this->addSql('ALTER TABLE GearComponent RENAME COLUMN localImagePath TO mediaItemId');
    }

    public function postUp(Schema $schema): void
    {
        $localImagePathsPerActivity = $this->connection->fetchAllKeyValue('SELECT activityId, mediaItemIds FROM Activity');

        foreach ($localImagePathsPerActivity as $activityId => $localImagePaths) {
            $mediaItemIds = array_values(array_filter(array_map(
                $this->migrateLocalImagePath(...),
                explode(',', (string) $localImagePaths),
            )));

            $this->connection->executeStatement(
                'UPDATE Activity SET mediaItemIds = :mediaItemIds WHERE activityId = :activityId',
                [
                    'mediaItemIds' => Json::encode($mediaItemIds),
                    'activityId' => $activityId,
                ]
            );
        }

        $this->migrateSingleMediaItemColumn(
            table: 'ActivityCollection',
            primaryKey: 'activityCollectionId',
            column: 'coverMediaItemId',
        );
        $this->migrateSingleMediaItemColumn(
            table: 'Gear',
            primaryKey: 'gearId',
            column: 'mediaItemId',
        );
        $this->migrateSingleMediaItemColumn(
            table: 'GearComponent',
            primaryKey: 'gearComponentId',
            column: 'mediaItemId',
        );
    }

    private function migrateSingleMediaItemColumn(string $table, string $primaryKey, string $column): void
    {
        $localImagePathPerPrimaryKey = $this->connection->fetchAllKeyValue(sprintf(
            'SELECT %s, %s FROM %s WHERE %s IS NOT NULL',
            $primaryKey,
            $column,
            $table,
            $column,
        ));

        foreach ($localImagePathPerPrimaryKey as $primaryKeyValue => $localImagePath) {
            $this->connection->executeStatement(
                sprintf('UPDATE %s SET %s = :mediaItemId WHERE %s = :primaryKey', $table, $column, $primaryKey),
                [
                    'mediaItemId' => $this->migrateLocalImagePath((string) $localImagePath),
                    'primaryKey' => $primaryKeyValue,
                ]
            );
        }
    }

    private function migrateLocalImagePath(string $localImagePath): ?string
    {
        $localImagePath = trim($localImagePath);
        if (!preg_match('#^/?files/#', $localImagePath)) {
            return null;
        }

        $path = (string) preg_replace('#^/?files/#', '', $localImagePath);
        $mediaItemId = (string) MediaItemId::fromUnprefixed(pathinfo($path, PATHINFO_FILENAME));

        $fileSystemPath = dirname(__DIR__).'/storage/files/'.$path;
        $content = is_file($fileSystemPath) ? @file_get_contents($fileSystemPath) : false;
        $metadata = false !== $content ? new MediaItemMetadataReader()->read($content) : null;

        $this->connection->executeStatement(
            'INSERT INTO MediaItem (mediaItemId, path, width, height, coordinateLatitude, coordinateLongitude, takenOn)
             VALUES (:mediaItemId, :path, :width, :height, :coordinateLatitude, :coordinateLongitude, :takenOn)',
            [
                'mediaItemId' => $mediaItemId,
                'path' => $path,
                'width' => $metadata?->getWidth(),
                'height' => $metadata?->getHeight(),
                'coordinateLatitude' => $metadata?->getCoordinate()?->getLatitude()->toFloat(),
                'coordinateLongitude' => $metadata?->getCoordinate()?->getLongitude()->toFloat(),
                'takenOn' => $metadata?->getTakenOn(),
            ]
        );

        return $mediaItemId;
    }

    public function down(Schema $schema): void
    {
    }
}
