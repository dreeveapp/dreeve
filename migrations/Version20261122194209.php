<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Infrastructure\Doctrine\Migrations\ActivityFieldRefresh\ActivityFieldRefreshMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261122194209 extends ActivityFieldRefreshMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Activity ADD COLUMN workoutRpe INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE Activity ADD COLUMN workoutFeel INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
