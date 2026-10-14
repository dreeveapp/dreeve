<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014061102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ActivityExplorerTiles (activityId VARCHAR(255) NOT NULL, tileCount INTEGER NOT NULL, tiles BLOB NOT NULL, PRIMARY KEY (activityId))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ActivityExplorerTiles');
    }
}
