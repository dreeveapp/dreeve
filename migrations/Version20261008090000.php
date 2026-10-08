<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE SegmentActivityScan (segmentId VARCHAR(255) NOT NULL, activityId VARCHAR(255) NOT NULL, PRIMARY KEY (segmentId, activityId))');
        $this->addSql('CREATE INDEX SegmentActivityScan_activityId ON SegmentActivityScan (activityId)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE SegmentActivityScan');
    }
}
