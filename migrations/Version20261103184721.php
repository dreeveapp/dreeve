<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103184721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ActivityCollection (activityCollectionId VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, type VARCHAR(255) NOT NULL, activityIds CLOB NOT NULL, coverImagePath VARCHAR(255) DEFAULT NULL, useRouteMapAsCover BOOLEAN NOT NULL, createdOn DATETIME NOT NULL, PRIMARY KEY (activityCollectionId))');
    }

    public function down(Schema $schema): void
    {
    }
}
