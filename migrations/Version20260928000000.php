<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX FileImport_originalFilename ON FileImport (originalFilename)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX FileImport_originalFilename');
    }
}
