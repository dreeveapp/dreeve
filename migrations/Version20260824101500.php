<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260824101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Activity ADD COLUMN isGroupActivity BOOLEAN DEFAULT NULL');
        $this->addSql("UPDATE Activity SET isGroupActivity = CASE WHEN json_extract(data, '$.athlete_count') > 1 THEN 1 ELSE 0 END");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Activity DROP COLUMN isGroupActivity');
    }
}
