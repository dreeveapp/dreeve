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
        $this->addSql(<<<'SQL'
            UPDATE Setting
            SET settingsGroup = 'appearance'
            WHERE settingsGroup = 'general' AND name = 'appSubTitle'
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE Setting
            SET settingsGroup = 'athleteFtp'
            WHERE settingsGroup = 'general' AND name = 'ftpHistory'
            SQL);
    }

    public function down(Schema $schema): void
    {
    }
}
