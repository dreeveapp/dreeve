<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827181621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ActivityGearUsage (activityId VARCHAR(255) NOT NULL, position VARCHAR(255) NOT NULL, gearNumber INTEGER NOT NULL, teeth INTEGER NOT NULL, timeInSeconds INTEGER NOT NULL, shiftCount INTEGER NOT NULL, PRIMARY KEY (activityId, position, gearNumber))');
        $this->addSql('CREATE INDEX ActivityGearUsage_positionTeeth ON ActivityGearUsage (position, teeth)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ActivityGearUsage');
    }
}
