<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008103952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE SegmentActivityScan (segmentId VARCHAR(255) NOT NULL, activityId VARCHAR(255) NOT NULL, PRIMARY KEY (segmentId, activityId))');
        $this->addSql('CREATE INDEX SegmentActivityScan_activityId ON SegmentActivityScan (activityId)');
        $this->addSql('ALTER TABLE SegmentEffort DROP COLUMN name');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE SegmentActivityScan');
    }
}
