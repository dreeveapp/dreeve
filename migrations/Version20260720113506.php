<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720113506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_FileImport_fileHash');
        $this->addSql('ALTER TABLE FileImport DROP COLUMN fileHash');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE FileImport ADD COLUMN fileHash VARCHAR(255) DEFAULT NULL');
    }
}
