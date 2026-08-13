<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813052023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ActivityRouteSignature (activityId VARCHAR(255) NOT NULL, polylineChecksum VARCHAR(8) NOT NULL, cellCount INTEGER NOT NULL, cells BLOB NOT NULL, waypoints BLOB NOT NULL, PRIMARY KEY (activityId))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ActivityRouteSignature');
    }
}
