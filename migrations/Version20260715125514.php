<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715125514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE AutomationRule (automationRuleId VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, isEnabled BOOLEAN NOT NULL, sortOrder INTEGER NOT NULL, conditions CLOB NOT NULL, actions CLOB NOT NULL, createdOn DATETIME NOT NULL, PRIMARY KEY (automationRuleId))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE AutomationRule');
    }
}
