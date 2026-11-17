<?php

declare(strict_types=1);

namespace DoctrineMigrations;

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
    }

    public function down(Schema $schema): void
    {
    }
}
