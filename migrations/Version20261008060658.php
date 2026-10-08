<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008060658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Segment ADD COLUMN type VARCHAR(255) DEFAULT \'imported\' NOT NULL');
        $this->addSql('CREATE INDEX Segment_type ON Segment (type)');
        $this->addSql('CREATE TABLE ActivityScan (activityId VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, subjectId VARCHAR(255) DEFAULT \'\' NOT NULL, PRIMARY KEY (activityId, type, subjectId))');
        $this->addSql('CREATE INDEX ActivityScan_typeSubject ON ActivityScan (type, subjectId)');
        $this->addSql('ALTER TABLE SegmentEffort DROP COLUMN name');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX Segment_type');
        $this->addSql('ALTER TABLE Segment DROP COLUMN type');
        $this->addSql('DROP TABLE ActivityScan');
    }
}
