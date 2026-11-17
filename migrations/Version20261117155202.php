<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
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
    }

    public function postUp(Schema $schema): void
    {
        $fileStorageDirectory = dirname(__DIR__).'/storage/files';
        $mediaItemMetadataReader = new MediaItemMetadataReader();
        $existingMediaItemIds = array_flip($this->connection->fetchFirstColumn('SELECT mediaItemId FROM MediaItem'));

        $localImagePathsPerActivity = $this->connection->fetchAllKeyValue('SELECT activityId, mediaItemIds FROM Activity');

        foreach ($localImagePathsPerActivity as $activityId => $localImagePaths) {
            $mediaItemIds = [];
            foreach (array_filter(array_map(trim(...), explode(',', (string) $localImagePaths))) as $localImagePath) {
                $path = MediaItemPath::fromLocalPath($localImagePath);
                $mediaItemId = MediaItemId::fromUnprefixed(pathinfo($localImagePath, PATHINFO_FILENAME));
                $mediaItemIds[] = (string) $mediaItemId;
                if (isset($existingMediaItemIds[(string) $mediaItemId])) {
                    continue;
                }

                $fileSystemPath = $fileStorageDirectory.'/'.$path->toFileSystemPath();
                $content = is_file($fileSystemPath) ? @file_get_contents($fileSystemPath) : false;
                $metadata = false !== $content ? $mediaItemMetadataReader->read($content) : null;

                $this->connection->executeStatement(
                    'INSERT INTO MediaItem (mediaItemId, path, width, height, coordinateLatitude, coordinateLongitude, takenOn)
                     VALUES (:mediaItemId, :path, :width, :height, :coordinateLatitude, :coordinateLongitude, :takenOn)',
                    [
                        'mediaItemId' => (string) $mediaItemId,
                        'path' => $path->toLocalPath(),
                        'width' => $metadata?->getWidth(),
                        'height' => $metadata?->getHeight(),
                        'coordinateLatitude' => $metadata?->getCoordinate()?->getLatitude()->toFloat(),
                        'coordinateLongitude' => $metadata?->getCoordinate()?->getLongitude()->toFloat(),
                        'takenOn' => $metadata?->getTakenOn(),
                    ]
                );
                $existingMediaItemIds[(string) $mediaItemId] = true;
            }

            $this->connection->executeStatement(
                'UPDATE Activity SET mediaItemIds = :mediaItemIds WHERE activityId = :activityId',
                [
                    'mediaItemIds' => Json::encode($mediaItemIds),
                    'activityId' => $activityId,
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
    }
}
