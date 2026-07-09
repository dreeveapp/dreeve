<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621191636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Gear DROP COLUMN distanceInMeter');
        $this->addSql('ALTER TABLE Gear ADD COLUMN purchasePriceAmount BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE Gear ADD COLUMN purchasePriceCurrency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE Gear ADD COLUMN localImagePath VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE TABLE RecordingDevice (id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, purchasePriceAmount BIGINT DEFAULT NULL, purchasePriceCurrency VARCHAR(3) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE GearMaintenanceLog (gearMaintenanceLogId VARCHAR(255) NOT NULL, gearId VARCHAR(255) NOT NULL, maintenanceTaskId VARCHAR(255) NOT NULL, performedOn DATETIME NOT NULL, PRIMARY KEY (gearMaintenanceLogId))');
        $this->addSql('CREATE INDEX GearMaintenanceLog_gearIndex ON GearMaintenanceLog (gearId)');
        $this->addSql('DELETE FROM FileImport');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_FileImport_fileHash');
        $this->addSql('ALTER TABLE FileImport DROP COLUMN fileHash');
        $this->addSql('ALTER TABLE FileImport ADD COLUMN fileHash VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FileImport_fileHash ON FileImport (fileHash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Gear ADD COLUMN distanceInMeter INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE Gear DROP COLUMN purchasePriceAmount');
        $this->addSql('ALTER TABLE Gear DROP COLUMN purchasePriceCurrency');
        $this->addSql('ALTER TABLE Gear DROP COLUMN localImagePath');
        $this->addSql('DROP TABLE RecordingDevice');
        $this->addSql('DROP TABLE GearMaintenanceLog');
    }
}
