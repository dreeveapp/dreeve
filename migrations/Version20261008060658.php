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
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX Segment_type');
        $this->addSql('ALTER TABLE Segment DROP COLUMN type');
    }
}
