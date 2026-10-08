<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008155722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO ActivityScan (activityId, type, subjectId) SELECT activityId, \'streams\', \'\' FROM Activity WHERE streamsAreImported = 1');
        $this->addSql('DROP INDEX Activity_streamsAreImported');
        $this->addSql('ALTER TABLE Activity DROP COLUMN streamsAreImported');
    }

    public function down(Schema $schema): void
    {

    }
}
