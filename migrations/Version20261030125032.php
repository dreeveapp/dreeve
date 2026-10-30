<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030125032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__Setting AS SELECT name, value FROM Setting');
        $this->addSql('DROP TABLE Setting');
        $this->addSql('CREATE TABLE Setting (name VARCHAR(255) NOT NULL, value CLOB NOT NULL, PRIMARY KEY (name))');
        $this->addSql('INSERT INTO Setting (name, value) SELECT name, value FROM __temp__Setting');
        $this->addSql('DROP TABLE __temp__Setting');
    }

    public function down(Schema $schema): void
    {
    }
}
