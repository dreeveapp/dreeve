<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260804193020 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX GearMaintenanceLog_maintenanceTask ON GearMaintenanceLog (maintenanceTaskId, performedOn)');
        $this->addSql('CREATE INDEX Activity_gearIdStartDateTime ON Activity (gearId, startDateTime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX GearMaintenanceLog_maintenanceTask');
        $this->addSql('DROP INDEX Activity_gearIdStartDateTime');
    }
}
